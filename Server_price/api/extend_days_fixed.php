<?php
// Server_price/api/extend_days_fixed.php
// API ต่ออายุ VPN (แก้ไขแล้ว)

require_once __DIR__ . '/../../controller/auth_check.php';
require_once __DIR__ . '/../../controller/config.php';
require_once __DIR__ . '/../../controller/xui_api/multi_xui_api.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$rental_id = isset($_POST['rental_id']) ? intval($_POST['rental_id']) : 0;
$days = isset($_POST['days']) ? intval($_POST['days']) : 0;
$user_id = $_SESSION['user_id'] ?? 0;

if ($rental_id <= 0 || $days <= 0) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
}

$conn->begin_transaction();

try {
    // ดึงข้อมูล VPN
    $stmt = $conn->prepare("
        SELECT ur.* 
        FROM user_rentals ur
        WHERE ur.id = ? AND ur.user_id = ? AND ur.status = 'active' AND ur.can_extend = 1
    ");
    $stmt->bind_param("ii", $rental_id, $user_id);
    $stmt->execute();
    $rental = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$rental) {
        throw new Exception('ไม่พบการเช่านี้ หรือไม่สามารถต่ออายุได้');
    }
    
    // ดึงราคา
    $stmt = $conn->prepare("SELECT price_per_unit, min_unit, max_unit FROM vpn_management_pricing WHERE action_type = 'extend_day'");
    $stmt->execute();
    $pricing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$pricing) {
        throw new Exception('ไม่พบข้อมูลราคา');
    }
    
    // ตรวจสอบ
    if ($days < $pricing['min_unit']) {
        throw new Exception('ขั้นต่ำ ' . $pricing['min_unit'] . ' วัน');
    }
    if ($pricing['max_unit'] && $days > $pricing['max_unit']) {
        throw new Exception('สูงสุด ' . $pricing['max_unit'] . ' วัน');
    }
    
    // คำนวณราคา
    $price = $days * $pricing['price_per_unit'];
    
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
    
    // อัพเดทวันหมดอายุ
    $new_expire_date = date('Y-m-d H:i:s', strtotime($rental['expire_date'] . ' +' . $days . ' days'));
    $stmt = $conn->prepare("UPDATE user_rentals SET expire_date = ? WHERE id = ?");
    $stmt->bind_param("si", $new_expire_date, $rental_id);
    $stmt->execute();
    $stmt->close();
    
    // อัพเดทใน 3x-ui
    $api = new MultiXUIApi($conn);
    $extend_result = $api->extendClientExpiry($rental['server_id'], $rental['inbound_id'], $rental['client_email'], $days);
    
    if (!$extend_result['success']) {
        // ถึงแม้ API ล้มเหลว แต่ database อัพเดทแล้ว
        // Log warning แต่ไม่ rollback
        error_log("Warning: Failed to extend client in 3x-ui: " . ($extend_result['message'] ?? ''));
    }
    
    // บันทึกประวัติ
    $old_date = $rental['expire_date'];
    $stmt = $conn->prepare("
        INSERT INTO vpn_management_history 
        (rental_id, user_id, action_type, old_value, new_value, amount_changed, price_paid) 
        VALUES (?, ?, 'extend_days', ?, ?, ?, ?)
    ");
    $stmt->bind_param("iissid", $rental_id, $user_id, $old_date, $new_expire_date, $days, $price);
    $stmt->execute();
    $stmt->close();
    
    // Notification
    $message = 'ต่ออายุ VPN ' . ($rental['custom_name'] ?? $rental['rental_name']) . ' เพิ่ม ' . $days . ' วัน';
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, message) 
        VALUES (?, 'success', 'ต่ออายุสำเร็จ', ?)
    ");
    $stmt->bind_param("is", $user_id, $message);
    $stmt->execute();
    $stmt->close();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'ต่ออายุสำเร็จ +' . $days . ' วัน',
        'data' => [
            'new_expire_date' => $new_expire_date,
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