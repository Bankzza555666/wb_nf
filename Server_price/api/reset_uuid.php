<?php
// Server_price/api/reset_uuid_fixed.php
// API เปลี่ยน UUID (แก้ Error sni แล้ว)

require_once __DIR__ . '/../../controller/auth_check.php';
require_once __DIR__ . '/../../controller/config.php';
require_once __DIR__ . '/../../controller/xui_api/multi_xui_api.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$rental_id = isset($_POST['rental_id']) ? intval($_POST['rental_id']) : 0;
$user_id = $_SESSION['user_id'] ?? 0;

if ($rental_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

$conn->begin_transaction();

try {
    // ดึงข้อมูล VPN และ Profile (ลบ p.sni ออก)
    $stmt = $conn->prepare("
        SELECT ur.*, p.config_template, p.host, p.port, p.network, p.security, p.protocol
        FROM user_rentals ur
        LEFT JOIN price_v2 p ON ur.price_id = p.id
        WHERE ur.id = ? AND ur.user_id = ? AND ur.status = 'active' AND ur.can_reset_uuid = 1
    ");
    $stmt->bind_param("ii", $rental_id, $user_id);
    $stmt->execute();
    $rental = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$rental) {
        throw new Exception('ไม่พบการเช่านี้ หรือไม่สามารถเปลี่ยน UUID ได้');
    }
    
    // ดึงราคา
    $stmt = $conn->prepare("SELECT price_per_unit FROM vpn_management_pricing WHERE action_type = 'reset_uuid'");
    $stmt->execute();
    $pricing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$pricing) {
        throw new Exception('ไม่พบข้อมูลราคา');
    }
    
    $price = $pricing['price_per_unit'];
    
    // ดึงยอดเงิน
    $stmt = $conn->prepare("SELECT credit FROM users WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$user || $user['credit'] < $price) {
        throw new Exception('ยอดเงินไม่เพียงพอ (ต้องการ ฿' . number_format($price, 2) . ')');
    }
    
    // หักเงิน
    $new_credit = $user['credit'] - $price;
    $stmt = $conn->prepare("UPDATE users SET credit = ? WHERE id = ?");
    $stmt->bind_param("di", $new_credit, $user_id);
    $stmt->execute();
    $stmt->close();
    
    // สร้าง UUID ใหม่
    $api = new MultiXUIApi($conn);
    $old_uuid = $rental['client_uuid'];
    
    // ลบ Client เดิม
    $delete_result = $api->deleteClient($rental['server_id'], $rental['inbound_id'], $rental['client_email']);
    
    // สร้าง Client ใหม่ด้วย UUID ใหม่
    $expire_days = ceil((strtotime($rental['expire_date']) - time()) / 86400);
    if ($expire_days < 1) $expire_days = 1;
    
    $client_data = [
        'email' => $rental['client_email'],
        'expire_days' => $expire_days,
        'data_gb' => round($rental['data_total_bytes'] / (1024*1024*1024)),
        'limit_ip' => $rental['max_devices']
    ];
    
    $add_result = $api->addClient($rental['server_id'], $rental['inbound_id'], $client_data);
    
    if (!$add_result['success']) {
        throw new Exception('ไม่สามารถสร้าง Client ใหม่ได้: ' . ($add_result['message'] ?? 'Unknown error'));
    }
    
    $new_uuid = $add_result['uuid'];
    
    // สร้าง Config URL ใหม่
    if (!empty($rental['config_template'])) {
        $template_data = [
            'uuid' => $new_uuid,
            'email' => $rental['client_email'],
            'host' => $rental['host'],
            'port' => $rental['port'],
            'network' => $rental['network'] ?? 'tcp',
            'path' => $rental['path'] ?? '/',
            'security' => $rental['security'],
            'protocol' => $rental['protocol'],
            'custom_name' => $rental['custom_name'] ?? $rental['rental_name']
        ];
        $new_config_url = $api->generateConfigFromTemplate($rental['config_template'], $template_data);
    } else {
        $new_config_url = $api->generateConfigUrl($rental['server_id'], $rental['inbound_id'], $new_uuid, $rental['client_email']);
    }
    
    // สร้าง QR Code ใหม่
    $new_qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($new_config_url);
    
    // อัพเดทฐานข้อมูล
    $stmt = $conn->prepare("
        UPDATE user_rentals 
        SET client_uuid = ?, config_url = ?, qr_code_url = ? 
        WHERE id = ?
    ");
    $stmt->bind_param("sssi", $new_uuid, $new_config_url, $new_qr_code_url, $rental_id);
    $stmt->execute();
    $stmt->close();
    
    // บันทึกประวัติ
    $stmt = $conn->prepare("
        INSERT INTO vpn_management_history 
        (rental_id, user_id, action_type, old_value, new_value, amount_changed, price_paid, notes) 
        VALUES (?, ?, 'reset_uuid', ?, ?, 1, ?, 'UUID เดิมถูกลบ, สร้าง UUID ใหม่')
    ");
    $stmt->bind_param("iissd", $rental_id, $user_id, $old_uuid, $new_uuid, $price);
    $stmt->execute();
    $stmt->close();
    
    // Notification
    $message = 'เปลี่ยน UUID สำหรับ ' . ($rental['custom_name'] ?? $rental['rental_name']) . ' สำเร็จ (Config เดิมไม่สามารถใช้งานได้)';
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, message) 
        VALUES (?, 'warning', 'เปลี่ยน UUID สำเร็จ', ?)
    ");
    $stmt->bind_param("is", $user_id, $message);
    $stmt->execute();
    $stmt->close();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'เปลี่ยน UUID สำเร็จ กรุณาใช้ Config ใหม่',
        'data' => [
            'new_uuid' => $new_uuid,
            'new_config_url' => $new_config_url,
            'new_qr_code_url' => $new_qr_code_url,
            'price_paid' => $price,
            'new_credit' => $new_credit
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>