<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['notification_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$notification_id = intval($data['notification_id']);

$stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $notification_id, $user_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'ลบแจ้งเตือนสำเร็จ']);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่พบการแจ้งเตือนหรือไม่สามารถลบได้']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด']);
}

$stmt->close();
$conn->close(); 