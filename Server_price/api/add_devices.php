<?php
// Server_price/api/add_devices.php
// API สำหรับเพิ่มจำนวนเครื่อง

require_once __DIR__ . '/../../controller/auth_check.php';
require_once __DIR__ . '/../../controller/config.php';
require_once __DIR__ . '/../../controller/xui_api/multi_xui_api.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$rental_id = isset($_POST['rental_id']) ? intval($_POST['rental_id']) : 0;
$devices = isset($_POST['devices']) ? intval($_POST['devices']) : 0;
$user_id = $_SESSION['user_id'] ?? 0;

if ($rental_id <= 0 || $devices <= 0) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

$conn->begin_transaction();

try {
    // ดึงข้อมูล VPN
    $stmt = $conn->prepare("
        SELECT ur.* 
        FROM user_rentals ur
        WHERE ur.id = ? AND ur.user_id = ? AND ur.status = 'active' AND ur.can_add_devices = 1
    ");
    $stmt->bind_param("ii", $rental_id, $user_id);
    $stmt->execute();
    $rental = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$rental) {
        throw new Exception('ไม่พบการเช่านี้ หรือไม่สามารถเพิ่มเครื่องได้');
    }
    
    // ดึงราคา
    $stmt = $conn->prepare("SELECT price_per_unit, min_unit, max_unit FROM vpn_management_pricing WHERE action_type = 'add_device'");
    $stmt->execute();
    $pricing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$pricing) {
        throw new Exception('ไม่พบข้อมูลราคา');
    }
    
    // ตรวจสอบขั้นต่ำ/สูงสุด
    if ($devices < $pricing['min_unit']) {
        throw new Exception('ขั้นต่ำ ' . $pricing['min_unit'] . ' เครื่อง');
    }
    if ($pricing['max_unit'] && $devices > $pricing['max_unit']) {
        throw new Exception('สูงสุด ' . $pricing['max_unit'] . ' เครื่อง');
    }
    
    // คำนวณราคา
    $price = $devices * $pricing['price_per_unit'];
    
    // ดึงยอดเงินผู้ใช้
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
    
    // อัพเดทจำนวนเครื่อง
    $new_max_devices = $rental['max_devices'] + $devices;
    $stmt = $conn->prepare("UPDATE user_rentals SET max_devices = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_max_devices, $rental_id);
    $stmt->execute();
    $stmt->close();
    
    // อัพเดทใน 3x-ui
    $api = new MultiXUIApi($conn);
    $api->updateClientIPLimit($rental['server_id'], $rental['inbound_id'], $rental['client_uuid'], $new_max_devices);
    
    // บันทึกประวัติ
    $old_devices = $rental['max_devices'];
    $stmt = $conn->prepare("
        INSERT INTO vpn_management_history 
        (rental_id, user_id, action_type, old_value, new_value, amount_changed, price_paid) 
        VALUES (?, ?, 'add_devices', ?, ?, ?, ?)
    ");
    $stmt->bind_param("iissid", $rental_id, $user_id, $old_devices, $new_max_devices, $devices, $price);
    $stmt->execute();
    $stmt->close();
    
    // Notification
    $message = 'เพิ่มจำนวนเครื่องสำหรับ ' . $rental['rental_name'] . ' เพิ่ม ' . $devices . ' เครื่อง';
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, message) 
        VALUES (?, 'success', 'เพิ่มเครื่องสำเร็จ', ?)
    ");
    $stmt->bind_param("is", $user_id, $message);
    $stmt->execute();
    $stmt->close();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'เพิ่มเครื่องสำเร็จ +' . $devices . ' เครื่อง',
        'data' => [
            'new_max_devices' => $new_max_devices,
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