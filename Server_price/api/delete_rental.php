<?php
// Server_price/api/delete_rental.php
// API สำหรับลบ VPN (Soft Delete)

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
    // ดึงข้อมูล VPN
    $stmt = $conn->prepare("
        SELECT ur.* 
        FROM user_rentals ur
        WHERE ur.id = ? AND ur.user_id = ? AND ur.deleted_at IS NULL
    ");
    $stmt->bind_param("ii", $rental_id, $user_id);
    $stmt->execute();
    $rental = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$rental) {
        throw new Exception('ไม่พบการเช่านี้');
    }
    
    // ลบ Client ใน 3x-ui
    $api = new MultiXUIApi($conn);
    $delete_result = $api->deleteClient($rental['server_id'], $rental['inbound_id'], $rental['client_uuid']);
    
    if (!$delete_result['success']) {
        // ถึงแม้ลบใน 3x-ui ไม่สำเร็จ ก็ยังลบในฐานข้อมูลได้
        // เพราะอาจเป็นว่า Client ถูกลบไปแล้ว หรือ Server ไม่ทำงาน
    }
    
    // Soft Delete (ไม่ลบจริง แค่ mark ว่าถูกลบ)
    $deleted_at = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("
        UPDATE user_rentals 
        SET status = 'deleted', deleted_at = ? 
        WHERE id = ?
    ");
    $stmt->bind_param("si", $deleted_at, $rental_id);
    $stmt->execute();
    $stmt->close();
    
    // บันทึกประวัติ
    $notes = 'ลบ VPN: ' . $rental['rental_name'];
    $stmt = $conn->prepare("
        INSERT INTO vpn_management_history 
        (rental_id, user_id, action_type, old_value, new_value, amount_changed, price_paid, notes) 
        VALUES (?, ?, 'delete', 'active', 'deleted', 0, 0, ?)
    ");
    $stmt->bind_param("iis", $rental_id, $user_id, $notes);
    $stmt->execute();
    $stmt->close();
    
    // Notification
    $message = 'ลบ VPN ' . $rental['rental_name'] . ' สำเร็จ';
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, message) 
        VALUES (?, 'info', 'ลบ VPN สำเร็จ', ?)
    ");
    $stmt->bind_param("is", $user_id, $message);
    $stmt->execute();
    $stmt->close();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'ลบ VPN สำเร็จ'
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