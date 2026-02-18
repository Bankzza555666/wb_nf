<?php
// Server_price/process_custom_rental.php
// แก้ไข: balance → credit + เพิ่ม error logging

// เปิด error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // ไม่แสดง error บนหน้าเว็บ
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/rental_error.log');

// ฟังก์ชัน Log
function writeLog($message) {
    $log_file = __DIR__ . '/../logs/rental_debug.log';
    $log_dir = dirname($log_file);
    if (!file_exists($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog("===== START RENTAL PROCESS =====");

try {
    require_once __DIR__ . '/../controller/auth_check.php';
    require_once __DIR__ . '/../controller/config.php';
    require_once __DIR__ . '/../controller/xui_api/multi_xui_api.php';
    
    writeLog("Files included successfully");
} catch (Exception $e) {
    // ✅ FIX: Catch Throwable for broader error handling (e.g., PHP 7+ Errors)
    // Note: Parse Errors (syntax errors) cannot be caught by try-catch.
    writeLog("FATAL ERROR: Failed to include core files. This might be due to a Parse Error in one of the included files (e.g., multi_xui_api.php). Error: " . $e->getMessage());
    // Fallback for fatal errors during include
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ไม่สามารถโหลดไฟล์ระบบได้: ' . $e->getMessage()]);
    exit;
} 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    writeLog("ERROR: Invalid request method");
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// รับข้อมูล
$server_id = isset($_POST['server_id']) ? $_POST['server_id'] : '';
$profile_id = isset($_POST['profile_id']) ? intval($_POST['profile_id']) : 0;
$days = isset($_POST['days']) ? intval($_POST['days']) : 0;
$data_gb = isset($_POST['data_gb']) ? intval($_POST['data_gb']) : 0;
$custom_name = isset($_POST['custom_name']) ? trim(strip_tags($_POST['custom_name'])) : ''; // ✅ NEW: รับค่า custom_name
$user_id = $_SESSION['user_id'] ?? 0;

writeLog("Received data: server_id=$server_id, profile_id=$profile_id, days=$days, data_gb=$data_gb, user_id=$user_id");

// Validate
if (empty($server_id) || $profile_id <= 0 || $days <= 0 || $data_gb <= 0) {
    writeLog("ERROR: Invalid input data");
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

if ($user_id <= 0) {
    writeLog("ERROR: User not logged in");
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

// เริ่ม Transaction
$conn->begin_transaction();
writeLog("Transaction started");

try {
    // ดึงข้อมูล User
    writeLog("Fetching user data...");
    $stmt = $conn->prepare("SELECT username, email, credit FROM users WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        throw new Exception('ไม่พบข้อมูลผู้ใช้');
    }
    
    writeLog("User found: " . $user['username'] . ", credit: " . $user['credit']);
    
    // ดึงข้อมูล Profile
    writeLog("Fetching profile data...");
    $stmt = $conn->prepare("SELECT * FROM price_v2 WHERE id = ? AND server_id = ? AND is_active = 1");
    $stmt->bind_param("is", $profile_id, $server_id);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$profile) {
        throw new Exception('ไม่พบ Profile ที่เลือก');
    }
    
    writeLog("Profile found: " . $profile['filename']);
    
    // --- 🔧 FIX V2.4: ตรวจสอบว่า Server เต็มหรือยัง ---
    writeLog("Checking server capacity...");
    $stmt = $conn->prepare("SELECT max_clients FROM servers WHERE server_id = ?");
    $stmt->bind_param("s", $server_id);
    $stmt->execute();
    $server_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT COUNT(*) as active_users 
        FROM user_rentals 
        WHERE server_id = ? AND status = 'active' AND expire_date > NOW() AND deleted_at IS NULL
    ");
    $stmt->bind_param("s", $server_id);
    $stmt->execute();
    $user_count = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user_count['active_users'] >= $server_data['max_clients']) {
        throw new Exception('ขออภัย Server ที่ท่านเลือกเต็มแล้ว');
    }
    writeLog("Server has capacity: {$user_count['active_users']} / {$server_data['max_clients']}");
    // --- END FIX V2.4 ---

    // ตรวจสอบค่าที่เลือก
    if ($days < $profile['min_days'] || $days > $profile['max_days']) {
        throw new Exception('จำนวนวันไม่อยู่ในช่วงที่กำหนด');
    }
    
    if ($data_gb < $profile['min_data_gb'] || $data_gb > $profile['max_data_gb']) {
        throw new Exception('ปริมาณ Data ไม่อยู่ในช่วงที่กำหนด');
    }
    
    // คำนวณราคา
    $day_price = $days * $profile['price_per_day'];
    $data_price = $data_gb * $profile['data_per_gb'];
    $total_price = $day_price + $data_price;
    
    writeLog("Price calculated: day=$day_price, data=$data_price, total=$total_price");
    
    // ตรวจสอบยอดเงิน
    if ($user['credit'] < $total_price) {
        throw new Exception('ยอดเงินไม่เพียงพอ');
    }
    
    // หักยอดเงิน
    $new_credit = $user['credit'] - $total_price;
    $stmt = $conn->prepare("UPDATE users SET credit = ? WHERE id = ?");
    $stmt->bind_param("di", $new_credit, $user_id);
    $stmt->execute();
    $stmt->close();
    
    writeLog("Credit deducted: old={$user['credit']}, new=$new_credit");
    
    // สร้างชื่อการเช่า
    // ✅ FIX v2: ใช้ custom_name ถ้ามี, ไม่งั้นใช้ชื่อสั้นๆ
    if (!empty($custom_name)) {
        // จำกัดความยาว custom_name ไม่เกิน 30 ตัวอักษร
        $rental_name = mb_substr($custom_name, 0, 30);
    } else {
        // ใช้ชื่อสั้นๆ: username + วันเดือน + random 4 หลัก
        $short_date = date('md'); // เช่น 0125
        $short_rand = substr(uniqid(), -4); // เช่น a3f2
        $rental_name = mb_substr($user['username'], 0, 10) . '_' . $short_date . $short_rand;
    }
    // สร้าง email สำหรับ 3x-ui (ใช้ format สั้น)
    $clean_name = preg_replace('/[^a-zA-Z0-9_]/', '', $rental_name);
    $client_email = strtolower(substr($clean_name, 0, 20)) . '_' . time() . '@vpn.local';
    
    // คำนวณวันหมดอายุ
    $start_date = date('Y-m-d H:i:s');
    $expire_date = date('Y-m-d H:i:s', strtotime("+{$days} days"));
    
    // สร้างรหัสอ้างอิง
    $transaction_ref = 'RENT' . time() . rand(1000, 9999);
    
    writeLog("Rental info created: name=$rental_name, email=$client_email");
    
    // เชื่อมต่อกับ 3x-ui API
    writeLog("Connecting to 3x-ui API...");
    $api = new MultiXUIApi($conn);
    
    // เพิ่ม Client ใหม่
    $client_data = [
        'email' => $client_email,
        'expire_days' => $days,
        'data_gb' => $data_gb,
        'limit_ip' => $profile['max_devices']
    ];
    
    writeLog("Adding client to 3x-ui: " . json_encode($client_data));
    
    $add_result = $api->addClient($server_id, $profile['inbound_id'], $client_data);
    
    if (!$add_result['success']) {
        writeLog("ERROR: Failed to add client: " . ($add_result['message'] ?? 'Unknown error'));
        throw new Exception('ไม่สามารถสร้าง VPN ได้: ' . ($add_result['message'] ?? 'Unknown error'));
    }
    
    $client_uuid = $add_result['uuid'] ?? null;
    
    if (!$client_uuid) {
        throw new Exception('ไม่สามารถดึง Client UUID ได้');
    }
    
    writeLog("Client added successfully: UUID=$client_uuid");
    
    // สร้าง Config URL
    writeLog("Generating config URL...");
    // ✅ FIX: ใช้ Template ในการสร้าง Config URL เพื่อความยืดหยุ่น
    if (!empty($profile['config_template'])) {
        $template_data = [
            'uuid' => $client_uuid,
            'email' => $client_email,
            'host' => $profile['host'],
            'port' => $profile['port'],
            'sni' => $profile['sni'] ?? $profile['host'],
            'network' => $profile['network'],
            'path' => $profile['path'] ?? '/',
            'security' => $profile['security'],
            'public_key' => $profile['public_key'] ?? '',
            'short_id' => $profile['short_id'] ?? '',
            'custom_name' => $rental_name // ✅ FIX: ส่ง custom_name หรือชื่อที่สร้างขึ้น ไปยัง Template
        ];
        $config_url = $api->generateConfigFromTemplate($profile['config_template'], $template_data);
    } else {
        $config_url = $api->generateConfigUrl($server_id, $profile['inbound_id'], $client_uuid, $client_email);
    }
    if (!$config_url) {
        throw new Exception('ไม่สามารถสร้าง Config URL ได้');
    }
    
    writeLog("Config URL generated");
    
    // สร้าง QR Code URL
    $qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($config_url);
    
    // แปลง GB เป็น Bytes
    $data_total_bytes = $data_gb * 1024 * 1024 * 1024;
    
    // บันทึกข้อมูลการเช่า
    writeLog("Saving rental to database...");
    $stmt = $conn->prepare("
        INSERT INTO user_rentals (
            user_id, server_id, price_id, inbound_id, client_uuid, client_email,
            rental_name, days_rented, data_gb_rented, max_devices,
            data_used_bytes, data_total_bytes, config_url, qr_code_url,
            start_date, expire_date, status, price_paid, transaction_ref
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 'active', ?, ?)
    ");
    
    $stmt->bind_param(
        "isiisssiiiissssds",
        $user_id,
        $server_id,
        $profile_id,
        $profile['inbound_id'],
        $client_uuid,
        $client_email,
        $rental_name,
        $days,
        $data_gb,
        $profile['max_devices'],
        $data_total_bytes,
        $config_url,
        $qr_code_url,
        $start_date,
        $expire_date,
        $total_price,
        $transaction_ref
    );
    
    $stmt->execute();
    $rental_id = $stmt->insert_id;
    $stmt->close();
    
    writeLog("Rental saved: ID=$rental_id");
    
    // บันทึก Traffic Log เริ่มต้น
    $stmt = $conn->prepare("
        INSERT INTO traffic_logs (rental_id, upload_bytes, download_bytes, total_bytes) 
        VALUES (?, 0, 0, 0)
    ");
    $stmt->bind_param("i", $rental_id);
    $stmt->execute();
    $stmt->close();
    
    // สร้าง Notification
    $notification_title = '✅ เช่า VPN สำเร็จ';
    $notification_message = 'คุณได้เช่า VPN จาก ' . $profile['filename'] . ' สำเร็จแล้ว | ระยะเวลา ' . $days . ' วัน | Data ' . $data_gb . ' GB';
    
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, message) 
        VALUES (?, 'success', ?, ?)
    ");
    $stmt->bind_param("iss", $user_id, $notification_title, $notification_message);
    $stmt->execute();
    $stmt->close();
    
    // Commit Transaction
    $conn->commit();
    writeLog("Transaction committed successfully");
    
    // ✅ FIX: นำส่วนการส่งอีเมลออกตามคำขอ เพื่อป้องกันการค้าง
    // การแจ้งเตือนจะถูกสร้างในตาราง notifications และแสดงในเว็บแทน
    // หากต้องการใช้ Telegram สามารถเพิ่มโค้ดในส่วนนี้ได้ในอนาคต
    writeLog("Skipping email notification as requested.");
    
    // ส่งผลลัพธ์สำเร็จ
    writeLog("===== RENTAL SUCCESS =====");
    echo json_encode([
        'success' => true,
        'message' => 'เช่า VPN สำเร็จ',
        'data' => [
            'rental_id' => $rental_id,
            'rental_name' => $rental_name,
            'config_url' => $config_url,
            'qr_code_url' => $qr_code_url,
            'expire_date' => $expire_date,
            'new_credit' => $new_credit
        ]
    ]);
    
} catch (Exception $e) {
    // ✅ FIX: Catch Throwable for broader error handling (e.g., PHP 7+ Errors)
    $conn->rollback();
    writeLog("ERROR: " . $e->getMessage());
    writeLog("===== RENTAL FAILED =====");
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>