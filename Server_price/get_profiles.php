<?php
// Server_price/get_profiles.php
// แก้ไข: ใช้ __DIR__ สำหรับ path

require_once __DIR__ . '/../controller/auth_check.php';
require_once __DIR__ . '/../controller/config.php';

header('Content-Type: application/json');

$server_id = isset($_GET['server_id']) ? $_GET['server_id'] : '';

if (empty($server_id)) {
    echo json_encode(['success' => false, 'message' => 'Server ID required']);
    exit;
}

try {
    // ดึงข้อมูล Profiles ของ Server
    $stmt = $conn->prepare("
        SELECT * FROM price_v2 
        WHERE server_id = ? AND is_active = 1 
        ORDER BY is_popular DESC, sort_order ASC
    ");
    $stmt->bind_param("s", $server_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $profiles = [];
    while ($row = $result->fetch_assoc()) {
        $profiles[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'profiles' => $profiles
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}
?>