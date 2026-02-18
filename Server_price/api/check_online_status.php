<?php
// Server_price/api/check_online_status.php
// API เช็คสถานะ Online/Offline

require_once __DIR__ . '/../../controller/config.php';
require_once __DIR__ . '/../../controller/xui_api/multi_xui_api.php';

header('Content-Type: application/json');

$rental_id = isset($_GET['rental_id']) ? intval($_GET['rental_id']) : 0;

if ($rental_id <= 0) {
    echo json_encode(['success' => false, 'is_online' => false]);
    exit;
}

try {
    // ดึงข้อมูล
    $stmt = $conn->prepare("SELECT server_id, client_email FROM user_rentals WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $rental_id);
    $stmt->execute();
    $rental = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$rental) {
        throw new Exception('Not found');
    }
    
    // เช็คจาก API
    $api = new MultiXUIApi($conn);
    $online_result = $api->getOnlineClients($rental['server_id']);
    
    $is_online = false;
    if ($online_result['success'] && is_array($online_result['data'])) {
        $is_online = in_array($rental['client_email'], $online_result['data']);
    }
    
    // อัพเดทสถานะ
    $stmt = $conn->prepare("UPDATE user_rentals SET is_online = ?, last_online_check = NOW() WHERE id = ?");
    $online_value = $is_online ? 1 : 0;
    $stmt->bind_param("ii", $online_value, $rental_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'is_online' => $is_online
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'is_online' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>