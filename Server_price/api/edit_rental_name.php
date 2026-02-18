<?php
// Server_price/api/edit_rental_name.php

require_once __DIR__ . '/../../controller/auth_check.php';
require_once __DIR__ . '/../../controller/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!isAuthenticated()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$rental_id = isset($_POST['rental_id']) ? intval($_POST['rental_id']) : 0;
$new_name = isset($_POST['new_name']) ? trim(strip_tags($_POST['new_name'])) : '';

if ($rental_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid rental ID.']);
    exit;
}

if (empty($new_name)) {
    echo json_encode(['success' => false, 'message' => 'กรุณาระบุชื่อที่ต้องการ']);
    exit;
}

if (mb_strlen($new_name) > 50) {
    echo json_encode(['success' => false, 'message' => 'ชื่อต้องมีความยาวไม่เกิน 50 ตัวอักษร']);
    exit;
}

$conn->begin_transaction();

try {
    // ตรวจสอบว่าเป็นเจ้าของ rental จริงหรือไม่ และทำการอัปเดต
    $stmt = $conn->prepare("UPDATE user_rentals SET rental_name = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $new_name, $rental_id, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'เปลี่ยนชื่อสำเร็จแล้ว']);
    } else {
        throw new Exception('ไม่พบรายการที่ต้องการแก้ไข หรือคุณไม่มีสิทธิ์');
    }
    $stmt->close();
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>