<?php
// controller/admin_controller/admin_api.php
// V7.1 Full Edition (All Modules & Full Fields)
if (!empty($_GET['ping']) && $_GET['ping'] === '3') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => 1, 'step' => 'admin_api']);
    exit;
}
// 1. ตั้งค่าพื้นฐาน
define('ADMIN_API_REQUEST', true);
ob_start();
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
// ปิด display_errors เพื่อไม่ให้ HTML ปนกับ JSON แต่ยัง log ได้
ini_set('display_errors', 0);
error_reporting(E_ALL);

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (ob_get_level()) ob_end_clean();
        @http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . ($err['message'] ?? 'Unknown'),
            'file' => basename($err['file'] ?? ''),
            'line' => (int) ($err['line'] ?? 0)
        ]);
    }
});

// 2. เชื่อมต่อฐานข้อมูลและตรวจสอบสิทธิ์ (ถ้า $conn ยังไม่มี เช่น ถูก include จาก index ให้โหลด config)
if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once __DIR__ . '/../config.php';
}
global $conn;
if (!isset($conn) || !($conn instanceof mysqli)) {
    if (ob_get_level()) ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Database connection not available']);
    exit;
}
if ($conn->connect_error) {
    if (ob_get_level()) ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}
require_once __DIR__ . '/admin_config.php';
require_once __DIR__ . '/../referral_helper.php'; // ✅ Referral System

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ตรวจสอบสิทธิ์ Admin (Double Check) - ใช้ prepared statement
$uid = (int) ($_SESSION['user_id'] ?? 0);
$stmt_role = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
if (!$stmt_role) {
    if (ob_get_level()) ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt_role->bind_param("i", $uid);
$stmt_role->execute();
$user_role = $stmt_role->get_result()->fetch_assoc();
$stmt_role->close();

if (!$user_role || $user_role['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

// ล้าง output ที่อาจหลุดจาก config/require (BOM, whitespace) ให้เหลือแค่ JSON
if (ob_get_level() && ob_get_length()) ob_clean();

// =========================================================
// MODULE 1: USER MANAGEMENT (จัดการสมาชิก)
// =========================================================
if ($action === 'get_users') {
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'newest';

    $sql = "SELECT u.*, (SELECT COUNT(*) FROM user_rentals WHERE user_id = u.id) as rental_count FROM users u WHERE 1=1";

    if (!empty($search)) {
        $search = $conn->real_escape_string($search);
        $sql .= " AND (u.username LIKE '%$search%' OR u.email LIKE '%$search%')";
    }

    switch ($sort) {
        case 'credit_high':
            $sql .= " ORDER BY u.credit DESC";
            break;
        case 'credit_low':
            $sql .= " ORDER BY u.credit ASC";
            break;
        case 'rentals_high':
            $sql .= " ORDER BY rental_count DESC";
            break;
        case 'oldest':
            $sql .= " ORDER BY u.register_at ASC";
            break;
        case 'newest':
        default:
            $sql .= " ORDER BY u.register_at DESC";
            break;
    }
    $sql .= " LIMIT 100";

    $result = $conn->query($sql);
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $data]);
} elseif ($action === 'save_user') {
    $id = intval($_POST['id']);
    $email = trim($_POST['email']);
    $credit = floatval($_POST['credit']);
    $role = trim($_POST['role']);

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE users SET email=?, credit=?, role=? WHERE id=?");
        $stmt->bind_param("sdsi", $email, $credit, $role, $id);
        if ($stmt->execute())
            echo json_encode(['success' => true]);
        else
            echo json_encode(['success' => false, 'message' => $conn->error]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid User ID']);
    }
} elseif ($action === 'delete_user') {
    $id = intval($_POST['id']);
    if ($conn->query("DELETE FROM users WHERE id=$id"))
        echo json_encode(['success' => true]);
    else
        echo json_encode(['success' => false, 'message' => $conn->error]);
}

// =========================================================
// MODULE 2: SERVER MANAGEMENT (จัดการเซิร์ฟเวอร์)
// =========================================================
elseif ($action === 'get_servers') {
    $result = $conn->query("SELECT * FROM servers ORDER BY sort_order ASC, id DESC");
    if ($result === false) {
        echo json_encode(['success' => false, 'message' => 'Query failed: ' . $conn->error, 'data' => []]);
        exit;
    }
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $data]);
} elseif ($action === 'save_server') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['server_name']);
    $ip = trim($_POST['server_ip']);
    $port = intval($_POST['server_port']);
    $path = trim($_POST['server_path']);
    $user = trim($_POST['server_username']);
    $pass = trim($_POST['server_password']);
    $loc = trim($_POST['server_location']);
    $max_clients = intval($_POST['max_clients']);
    $status = $_POST['server_status'] ?? 'online';

    if ($id > 0) {
        // Update
        $stmt = $conn->prepare("UPDATE servers SET server_name=?, server_ip=?, server_port=?, server_path=?, server_username=?, server_password=?, server_location=?, max_clients=?, server_status=? WHERE id=?");
        $stmt->bind_param("ssissssisi", $name, $ip, $port, $path, $user, $pass, $loc, $max_clients, $status, $id);
    } else {
        // Insert
        $sid = 'sv-' . time() . rand(100, 999);
        $stmt = $conn->prepare("INSERT INTO servers (server_name, server_id, server_ip, server_port, server_path, server_username, server_password, server_location, max_clients, server_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssissssis", $name, $sid, $ip, $port, $path, $user, $pass, $loc, $max_clients, $status);
    }

    if ($stmt->execute())
        echo json_encode(['success' => true]);
    else
        echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $stmt->error]);
} elseif ($action === 'delete_server') {
    $id = intval($_POST['id']);
    if ($conn->query("DELETE FROM servers WHERE id=$id"))
        echo json_encode(['success' => true]);
    else
        echo json_encode(['success' => false, 'message' => $conn->error]);
}

// =========================================================
// MODULE 3: PRODUCT/PACKAGE MANAGEMENT (จัดการแพ็กเกจ - ครบทุกฟิลด์)
// =========================================================
elseif ($action === 'get_products') {
    $sql = "SELECT p.*, s.server_name, s.server_ip 
            FROM price_v2 p 
            LEFT JOIN servers s ON p.server_id = s.server_id 
            ORDER BY p.sort_order ASC, p.id DESC";
    $result = $conn->query($sql);
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $data]);
} elseif ($action === 'save_product') {
    $id = intval($_POST['id'] ?? 0);

    // รับค่าจากฟอร์มให้ครบถ้วน
    $filename = trim($_POST['filename']);
    $server_id = trim($_POST['server_id']);
    $inbound_id = intval($_POST['inbound_id']);
    $host = trim($_POST['host']);
    $port = intval($_POST['port']);

    $protocol = $_POST['protocol'] ?? 'vless';
    $network = $_POST['network'] ?? 'tcp';
    $security = $_POST['security'] ?? 'tls';
    $template = $_POST['config_template'] ?? '';

    $price_day = floatval($_POST['price_per_day']);
    $price_gb = floatval($_POST['data_per_gb']);

    $days = intval($_POST['max_days']);
    $min_gb = intval($_POST['min_data_gb']);
    $max_gb = intval($_POST['max_data_gb']);
    $devices = intval($_POST['max_devices']);
    $speed = intval($_POST['speed_limit_mbps']);

    $desc = trim($_POST['description']);
    $is_pop = isset($_POST['is_popular']) ? 1 : 0;
    $active = isset($_POST['is_active']) ? 1 : 0;

    if ($id > 0) {
        // Update
        $sql = "UPDATE price_v2 SET 
                filename=?, server_id=?, inbound_id=?, host=?, port=?, 
                protocol=?, network=?, security=?, 
                price_per_day=?, data_per_gb=?, 
                min_days=1, max_days=?, min_data_gb=?, max_data_gb=?, 
                max_devices=?, speed_limit_mbps=?, 
                description=?, is_popular=?, is_active=?, config_template=? 
                WHERE id=?";

        $stmt = $conn->prepare($sql);
        // types: s s i s i | s s s | d d | i i i | i i | s i i s | i
        $stmt->bind_param(
            "ssisisssddiiiiisiisi",
            $filename,
            $server_id,
            $inbound_id,
            $host,
            $port,
            $protocol,
            $network,
            $security,
            $price_day,
            $price_gb,
            $days,
            $min_gb,
            $max_gb,
            $devices,
            $speed,
            $desc,
            $is_pop,
            $active,
            $template,
            $id
        );
    } else {
        // Insert
        $sql = "INSERT INTO price_v2 (
                filename, server_id, inbound_id, host, port, 
                protocol, network, security, 
                price_per_day, data_per_gb, 
                min_days, max_days, min_data_gb, max_data_gb, 
                max_devices, speed_limit_mbps, 
                description, is_popular, is_active, config_template
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssisisssddiiiiisiis",
            $filename,
            $server_id,
            $inbound_id,
            $host,
            $port,
            $protocol,
            $network,
            $security,
            $price_day,
            $price_gb,
            $days,
            $min_gb,
            $max_gb,
            $devices,
            $speed,
            $desc,
            $is_pop,
            $active,
            $template
        );
    }

    if ($stmt->execute())
        echo json_encode(['success' => true]);
    else
        echo json_encode(['success' => false, 'message' => $stmt->error]);
} elseif ($action === 'delete_product') {
    $id = intval($_POST['id']);
    if ($conn->query("DELETE FROM price_v2 WHERE id=$id"))
        echo json_encode(['success' => true]);
    else
        echo json_encode(['success' => false, 'message' => $conn->error]);
}

// =========================================================
// MODULE 4: TOPUP MANAGEMENT (จัดการการเติมเงิน)
// =========================================================
elseif ($action === 'get_topups') {
    $sql = "SELECT t.*, u.username 
            FROM topup_transactions t 
            LEFT JOIN users u ON t.user_id = u.id 
            ORDER BY t.created_at DESC LIMIT 100";
    $result = $conn->query($sql);
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $data]);
} elseif ($action === 'approve_topup') {
    $id = intval($_POST['id']);
    $admin_id = $_SESSION['user_id'];

    $tx = $conn->query("SELECT * FROM topup_transactions WHERE id=$id AND status='pending'")->fetch_assoc();

    if ($tx) {
        $amount = floatval($tx['amount']) + floatval($tx['bonus']);
        $uid = $tx['user_id'];
        $topup_amount = floatval($tx['amount']); // ยอดจริงไม่รวมโบนัส

        $conn->begin_transaction();
        try {
            // 1. เพิ่มเครดิตให้ผู้เติมเงิน
            $conn->query("UPDATE users SET credit = credit + $amount WHERE id = $uid");
            $conn->query("UPDATE topup_transactions SET status='success', approved_by='$admin_id', approved_at=NOW() WHERE id=$id");

            $title = "เติมเงินสำเร็จ";
            $msg = "ยอดเงิน ฿" . number_format($amount, 2) . " เข้าบัญชีแล้ว";
            $conn->query("INSERT INTO notifications (user_id, type, title, message, transaction_id) VALUES ($uid, 'success', '$title', '$msg', $id)");

            // ✅ 2. ระบบแนะนำเพื่อน - ใช้ helper function (มี anti-fraud, logging, limits)
            $referral_result = processReferralCommission($conn, $uid, $topup_amount, $id);
            // referral_result contains: success, commission, referrer_id, referrer_name, error

            $conn->commit();
            
            $response = ['success' => true];
            if ($referral_result['success']) {
                $response['referral_commission'] = $referral_result['commission'];
                $response['referrer'] = $referral_result['referrer_name'];
            }
            echo json_encode($response);
            
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'System Error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'ทำรายการไม่ได้']);
    }
} elseif ($action === 'reject_topup') {
    $id = intval($_POST['id']);
    $admin_id = $_SESSION['user_id'];
    if ($conn->query("UPDATE topup_transactions SET status='cancelled', approved_by='$admin_id' WHERE id=$id AND status='pending'")) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed']);
    }
} elseif ($action === 'get_topup_stats') {
    // 1. Overall Stats
    $total_tx = $conn->query("SELECT COUNT(*) as c FROM topup_transactions")->fetch_assoc()['c'];
    $success_tx = $conn->query("SELECT COUNT(*) as c FROM topup_transactions WHERE status IN ('success', 'approved')")->fetch_assoc()['c'];
    $unique_users = $conn->query("SELECT COUNT(DISTINCT user_id) as c FROM topup_transactions")->fetch_assoc()['c'];

    // 2. Daily Stats (Last 30 Days)
    $daily_stats = [];
    $sql_chart = "SELECT DATE(created_at) as date, 
                         COUNT(*) as total, 
                         SUM(CASE WHEN status IN ('success', 'approved') THEN 1 ELSE 0 END) as success 
                  FROM topup_transactions 
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                  GROUP BY DATE(created_at) 
                  ORDER BY date ASC";
    $result = $conn->query($sql_chart);
    while ($row = $result->fetch_assoc()) {
        $daily_stats[] = $row;
    }

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_tx' => $total_tx,
            'success_tx' => $success_tx,
            'unique_users' => $unique_users,
            'success_rate' => $total_tx > 0 ? round(($success_tx / $total_tx) * 100, 1) : 0
        ],
        'chart_data' => $daily_stats
    ]);

} elseif ($action === 'cleanup_topups') {
    $scope = $_POST['scope'] ?? 'older'; // today, older, all
    $where = "status NOT IN ('success', 'approved')";
    $today = date('Y-m-d');

    if ($scope === 'today') {
        $where .= " AND DATE(created_at) = '$today'";
    } elseif ($scope === 'older') {
        $where .= " AND DATE(created_at) < '$today'";
    } elseif ($scope === 'all') {
        // No extra condition
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid Scope']);
        exit;
    }

    if ($conn->query("DELETE FROM topup_transactions WHERE $where")) {
        echo json_encode(['success' => true, 'affected' => $conn->affected_rows]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Action Request']);
}

$conn->close();
?>