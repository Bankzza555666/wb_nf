<?php
/**
 * SSH Rental Controller (User Frontend)
 * จัดการการเช่า SSH สำหรับผู้ใช้
 */

session_start();
require_once 'config.php';
require_once 'ssh_api/ssh_api.php';
require_once 'ssh_api/ssh_config_generator.php';

// ===== Notification Helper Functions =====

/**
 * ส่ง Telegram แจ้งเตือน Admin
 */
function sendSSHTelegramNotify($message)
{
    $token = TELEGRAM_BOT_TOKEN;
    $chatId = TELEGRAM_CHAT_ID;

    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
}

/**
 * บันทึก notification ในระบบ
 */
function addSSHNotification($conn, $user_id, $title, $message, $type = 'info')
{
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("isss", $user_id, $title, $message, $type);
    $stmt->execute();
    $stmt->close();
}

/**
 * ดึงข้อมูล username
 */
function getUsername($conn, $user_id)
{
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result['username'] ?? 'Unknown';
}


header('Content-Type: application/json');

// ตรวจสอบ login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'get_products':
        getProducts();
        break;
    case 'get_product':
        getProduct($_GET['id'] ?? 0);
        break;
    case 'rent':
        rentSSH();
        break;
    case 'get_my_rentals':
        getMyRentals();
        break;
    case 'update_custom_name':
        updateCustomName();
        break;
    case 'extend':
        extendRental();
        break;
    case 'cancel':
        cancelRental();
        break;
    case 'toggle_auto_renew':
        toggleAutoRenew();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getProducts()
{
    global $conn;

    $sql = "SELECT p.*, s.server_name, s.location 
            FROM ssh_products p 
            LEFT JOIN ssh_servers s ON p.server_id = s.server_id 
            WHERE p.is_active = 1 AND s.status = 'online'
            ORDER BY p.sort_order, p.product_name";
    $result = $conn->query($sql);
    $products = [];

    while ($row = $result->fetch_assoc()) {
        // ซ่อน config templates จาก response
        unset($row['ssh_config_template']);
        unset($row['npv_config_template']);
        $products[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $products]);
}

function getProduct($id)
{
    global $conn;

    $stmt = $conn->prepare("SELECT p.*, s.server_name, s.location 
                            FROM ssh_products p 
                            LEFT JOIN ssh_servers s ON p.server_id = s.server_id 
                            WHERE p.id = ? AND p.is_active = 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        unset($row['ssh_config_template']);
        unset($row['npv_config_template']);
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่พบแพ็กเกจ']);
    }
}

function rentSSH()
{
    global $conn, $user_id;

    $product_id = intval($_POST['product_id'] ?? 0);
    $days = intval($_POST['days'] ?? 0);
    $custom_name = trim($_POST['custom_name'] ?? '');

    // ดึงข้อมูล product
    $stmt = $conn->prepare("SELECT p.*, s.* FROM ssh_products p 
                            LEFT JOIN ssh_servers s ON p.server_id = s.server_id 
                            WHERE p.id = ? AND p.is_active = 1");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบแพ็กเกจ']);
        return;
    }

    // ตรวจสอบจำนวนวัน
    if ($days < $product['min_days'] || $days > $product['max_days']) {
        echo json_encode(['success' => false, 'message' => "จำนวนวันต้องอยู่ระหว่าง {$product['min_days']} - {$product['max_days']} วัน"]);
        return;
    }

    // คำนวณราคา
    $total_price = $product['price_per_day'] * $days;

    // ตรวจสอบเครดิต
    $stmt = $conn->prepare("SELECT credit FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user['credit'] < $total_price) {
        echo json_encode(['success' => false, 'message' => 'เครดิตไม่เพียงพอ']);
        return;
    }

    // Generate username & password
    $ssh_username = SSHPlusManagerAPI::generateUsername('nf');
    $ssh_password = SSHPlusManagerAPI::generatePassword(8);

    // สร้าง user บน SSH Server
    $sshApi = new SSHPlusManagerAPI($product, $conn);
    $createResult = $sshApi->createUser($ssh_username, $ssh_password, $days, $product['max_devices']);

    // ❌ ถ้า SSH ล้มเหลว ให้ return error ทันที (ไม่สร้าง rental)
    if (!$createResult['success']) {
        error_log("SSH User Creation Failed: " . $createResult['message']);
        echo json_encode([
            'success' => false,
            'message' => 'ไม่สามารถสร้าง user บน SSH Server: ' . $createResult['message'],
            'error_type' => 'ssh_creation_failed'
        ]);
        return;
    }

    // Generate configs
    if (empty($custom_name)) {
        $custom_name = $ssh_username;
    }

    $ssh_config = SSHConfigGenerator::generateSSHConfig(
        $product['ssh_config_template'],
        $ssh_username,
        $ssh_password,
        $custom_name
    );

    $npv_config = SSHConfigGenerator::generateNPVConfig(
        $product['npv_config_template'],
        $ssh_username,
        $ssh_password,
        $custom_name
    );

    // สร้าง rental name
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $username = $stmt->get_result()->fetch_assoc()['username'];
    $rental_name = $username . '_' . $product['product_name'] . '_' . date('YmdHis');

    // Transaction
    $conn->begin_transaction();

    try {
        // หักเครดิต
        $stmt = $conn->prepare("UPDATE users SET credit = credit - ? WHERE id = ? AND credit >= ?");
        $stmt->bind_param("did", $total_price, $user_id, $total_price);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            throw new Exception('เครดิตไม่เพียงพอ');
        }

        // สร้าง rental
        $expire_date = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        $transaction_ref = 'SSH' . time() . rand(1000, 9999);

        $stmt = $conn->prepare("INSERT INTO ssh_rentals 
            (user_id, product_id, server_id, ssh_username, ssh_password, custom_name, rental_name, days_rented, ssh_config_url, npv_config_url, expire_date, status, price_paid, transaction_ref) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)");
        $stmt->bind_param(
            "iisssssisssds",
            $user_id,
            $product_id,
            $product['server_id'],
            $ssh_username,
            $ssh_password,
            $custom_name,
            $rental_name,
            $days,
            $ssh_config,
            $npv_config,
            $expire_date,
            $total_price,
            $transaction_ref
        );
        $stmt->execute();
        $rental_id = $conn->insert_id;

        $conn->commit();

        // ===== Send Notifications =====
        $username = getUsername($conn, $user_id);

        // Telegram Notification
        $telegramMsg = "🎉 <b>SSH Rental ใหม่!</b>\n\n"
            . "👤 User: {$username}\n"
            . "📦 Product: {$product['product_name']}\n"
            . "📅 Days: {$days} วัน\n"
            . "💰 Price: ฿" . number_format($total_price, 2) . "\n"
            . "🔑 SSH User: {$ssh_username}\n"
            . "📆 Expire: {$expire_date}";
        sendSSHTelegramNotify($telegramMsg);

        // System Notification
        addSSHNotification(
            $conn,
            $user_id,
            'เช่า SSH สำเร็จ',
            "คุณได้เช่า {$product['product_name']} เป็นเวลา {$days} วัน ราคา ฿" . number_format($total_price, 2),
            'success'
        );

        echo json_encode([
            'success' => true,
            'message' => 'เช่า SSH สำเร็จ!',
            'data' => [
                'rental_id' => $rental_id,
                'username' => $ssh_username,
                'password' => $ssh_password,
                'expire_date' => $expire_date,
                'ssh_config' => $ssh_config,
                'npv_config' => $npv_config
            ]
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getMyRentals()
{
    global $conn, $user_id;

    $sql = "SELECT r.*, p.product_name, s.server_name, s.location
            FROM ssh_rentals r
            LEFT JOIN ssh_products p ON r.product_id = p.id
            LEFT JOIN ssh_servers s ON r.server_id = s.server_id
            WHERE r.user_id = ?
            ORDER BY r.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $rentals = [];
    while ($row = $result->fetch_assoc()) {
        // คำนวณสถานะ
        if ($row['status'] === 'active' && strtotime($row['expire_date']) < time()) {
            $row['status'] = 'expired';
            // Update DB
            $conn->query("UPDATE ssh_rentals SET status = 'expired' WHERE id = " . $row['id']);
        }
        $rentals[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $rentals]);
}

function updateCustomName()
{
    global $conn, $user_id;

    $rental_id = intval($_POST['rental_id'] ?? 0);
    $new_name = trim($_POST['custom_name'] ?? '');

    if (empty($new_name)) {
        echo json_encode(['success' => false, 'message' => 'กรุณาใส่ชื่อ']);
        return;
    }

    // ดึงข้อมูล rental
    $stmt = $conn->prepare("SELECT * FROM ssh_rentals WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $rental_id, $user_id);
    $stmt->execute();
    $rental = $stmt->get_result()->fetch_assoc();

    if (!$rental) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูล']);
        return;
    }

    // Update configs with new name
    $newConfigs = SSHConfigGenerator::updateCustomName(
        $rental['ssh_config_url'],
        $rental['npv_config_url'],
        $rental['custom_name'],
        $new_name
    );

    // Update DB
    $stmt = $conn->prepare("UPDATE ssh_rentals SET custom_name = ?, ssh_config_url = ?, npv_config_url = ? WHERE id = ?");
    $stmt->bind_param("sssi", $new_name, $newConfigs['ssh'], $newConfigs['npv'], $rental_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'อัปเดตชื่อสำเร็จ',
            'data' => [
                'ssh_config' => $newConfigs['ssh'],
                'npv_config' => $newConfigs['npv']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด']);
    }
}

function extendRental()
{
    global $conn, $user_id;

    $rental_id = intval($_POST['rental_id'] ?? 0);
    $days = intval($_POST['days'] ?? 0);

    // ดึงข้อมูล rental และ product
    $stmt = $conn->prepare("SELECT r.*, p.price_per_day FROM ssh_rentals r 
                            LEFT JOIN ssh_products p ON r.product_id = p.id 
                            WHERE r.id = ? AND r.user_id = ?");
    $stmt->bind_param("ii", $rental_id, $user_id);
    $stmt->execute();
    $rental = $stmt->get_result()->fetch_assoc();

    if (!$rental) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูล']);
        return;
    }

    if ($days < 1) {
        echo json_encode(['success' => false, 'message' => 'จำนวนวันไม่ถูกต้อง']);
        return;
    }

    $total_price = $rental['price_per_day'] * $days;

    // ตรวจสอบเครดิต
    $stmt = $conn->prepare("SELECT credit FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user['credit'] < $total_price) {
        echo json_encode(['success' => false, 'message' => 'เครดิตไม่เพียงพอ']);
        return;
    }

    // คำนวณวันหมดอายุใหม่
    $current_expire = $rental['expire_date'];
    if (strtotime($current_expire) < time()) {
        $new_expire = date('Y-m-d H:i:s', strtotime("+{$days} days"));
    } else {
        $new_expire = date('Y-m-d H:i:s', strtotime($current_expire . " +{$days} days"));
    }

    // ✅ Sync กับ SSH Server - ต่ออายุ user บน server
    $stmt_server = $conn->prepare("SELECT s.* FROM ssh_servers s 
                                    LEFT JOIN ssh_rentals r ON r.server_id = s.server_id 
                                    WHERE r.id = ?");
    $stmt_server->bind_param("i", $rental_id);
    $stmt_server->execute();
    $server = $stmt_server->get_result()->fetch_assoc();
    $stmt_server->close();

    if ($server) {
        $sshApi = new SSHPlusManagerAPI($server, $conn);
        // ✅ ส่ง new_expire ไปด้วยเพื่อให้ SSH server ได้วันหมดอายุที่ถูกต้อง
        $extendResult = $sshApi->extendUser($rental['ssh_username'], $days, $new_expire);

        if (!$extendResult['success']) {
            error_log("SSH Extend Failed: " . $extendResult['message']);
            echo json_encode([
                'success' => false,
                'message' => 'ไม่สามารถต่ออายุ user บน SSH Server: ' . $extendResult['message'],
                'error_type' => 'ssh_extend_failed'
            ]);
            return;
        }
    }

    // Transaction
    $conn->begin_transaction();

    try {
        // หักเครดิต
        $stmt = $conn->prepare("UPDATE users SET credit = credit - ? WHERE id = ? AND credit >= ?");
        $stmt->bind_param("did", $total_price, $user_id, $total_price);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            throw new Exception('เครดิตไม่เพียงพอ');
        }

        // Update rental
        $stmt = $conn->prepare("UPDATE ssh_rentals SET expire_date = ?, status = 'active', days_rented = days_rented + ? WHERE id = ?");
        $stmt->bind_param("sii", $new_expire, $days, $rental_id);
        $stmt->execute();

        $conn->commit();

        // ===== Send Notifications =====
        $username = getUsername($conn, $user_id);

        // Telegram Notification
        $telegramMsg = "🔄 <b>SSH ต่ออายุ!</b>\n\n"
            . "👤 User: {$username}\n"
            . "🔑 SSH: {$rental['ssh_username']}\n"
            . "📅 เพิ่ม: +{$days} วัน\n"
            . "💰 Price: ฿" . number_format($total_price, 2) . "\n"
            . "📆 หมดอายุใหม่: {$new_expire}";
        sendSSHTelegramNotify($telegramMsg);

        // System Notification
        addSSHNotification(
            $conn,
            $user_id,
            'ต่ออายุ SSH สำเร็จ',
            "คุณได้ต่ออายุ SSH +{$days} วัน ราคา ฿" . number_format($total_price, 2),
            'success'
        );

        echo json_encode([
            'success' => true,
            'message' => "ต่ออายุ +{$days} วัน สำเร็จ",
            'data' => ['new_expire' => $new_expire]
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * ยกเลิก Rental
 */
function cancelRental()
{
    global $conn, $user_id;

    $rental_id = intval($_POST['rental_id'] ?? 0);

    if (!$rental_id) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบ rental']);
        return;
    }

    // ดึงข้อมูล rental
    $stmt = $conn->prepare("SELECT r.*, s.* FROM ssh_rentals r 
                            LEFT JOIN ssh_servers s ON r.server_id = s.server_id 
                            WHERE r.id = ? AND r.user_id = ?");
    $stmt->bind_param("ii", $rental_id, $user_id);
    $stmt->execute();
    $rental = $stmt->get_result()->fetch_assoc();

    if (!$rental) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบ rental หรือไม่มีสิทธิ์']);
        return;
    }

    // ลบ user จาก SSH Server
    try {
        $sshApi = new SSHPlusManagerAPI($rental, $conn);
        $deleteResult = $sshApi->deleteUser($rental['ssh_username']);

        if (!$deleteResult['success']) {
            error_log("Warning: Could not delete SSH user: " . $deleteResult['message']);
            // Continue anyway - user might already be deleted
        }
    } catch (Exception $e) {
        error_log("SSH delete error: " . $e->getMessage());
    }

    // ลบจาก database
    $stmt = $conn->prepare("DELETE FROM ssh_rentals WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $rental_id, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // ===== Send Notifications =====
        $username = getUsername($conn, $user_id);

        // Telegram Notification
        $telegramMsg = "❌ <b>SSH ยกเลิก!</b>\n\n"
            . "👤 User: {$username}\n"
            . "🔑 SSH: {$rental['ssh_username']}\n"
            . "📦 Product: " . ($rental['product_name'] ?? 'N/A');
        sendSSHTelegramNotify($telegramMsg);

        // System Notification
        addSSHNotification(
            $conn,
            $user_id,
            'ยกเลิก SSH สำเร็จ',
            "คุณได้ยกเลิก SSH: {$rental['ssh_username']}",
            'info'
        );

        echo json_encode([
            'success' => true,
            'message' => 'ยกเลิก rental สำเร็จ'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถลบ rental ได้']);
    }
}

/**
 * Toggle Auto-Renew for SSH Rental
 */
function toggleAutoRenew()
{
    global $conn, $user_id;

    $rental_id = intval($_POST['rental_id'] ?? $_REQUEST['rental_id'] ?? 0);
    $status = intval($_POST['status'] ?? $_REQUEST['status'] ?? 0); // 1 = on, 0 = off

    // Debug log
    error_log("[SSH AUTO-RENEW] rental_id: {$rental_id}, status: {$status}, user_id: {$user_id}");

    if (!$rental_id) {
        error_log("[SSH AUTO-RENEW] Error: rental_id is 0 or empty");
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูล (rental_id is empty)']);
        return;
    }

    // ยืนยันว่า rental มีอยู่และเป็นของ user
    $check = $conn->prepare("SELECT id, user_id FROM ssh_rentals WHERE id = ?");
    $check->bind_param("i", $rental_id);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$row) {
        error_log("[SSH AUTO-RENEW] Error: rental not found for id: {$rental_id}");
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูล rental (id: ' . $rental_id . ')']);
        return;
    }

    if ($row['user_id'] != $user_id) {
        error_log("[SSH AUTO-RENEW] Error: user_id mismatch. Rental owner: {$row['user_id']}, Current user: {$user_id}");
        echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง rental นี้']);
        return;
    }

    $checkCol = @$conn->query("SHOW COLUMNS FROM ssh_rentals LIKE 'auto_renew'");
    if (!$checkCol || $checkCol->num_rows === 0) {
        @$conn->query("ALTER TABLE ssh_rentals ADD COLUMN auto_renew TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
    }
    if ($checkCol)
        $checkCol->close();

    $stmt = $conn->prepare("UPDATE ssh_rentals SET auto_renew = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("iii", $status, $rental_id, $user_id);

    if (!$stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัปเดต']);
        return;
    }
    $stmt->close();

    $msg = $status ? 'เปิดการต่ออายุอัตโนมัติสำเร็จ' : 'ปิดการต่ออายุอัตโนมัติสำเร็จ';
    echo json_encode(['success' => true, 'message' => $msg]);
}

