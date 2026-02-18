<?php
// controller/clear_all_notifications.php
// ลบการแจ้งเตือนทั้งหมดของสมาชิก (ไม่รวมประกาศทั่วไป)

session_start();
require_once 'config.php';

header('Content-Type: application/json');

// ตรวจสอบ login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

// ตรวจสอบ request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];

// ลบเฉพาะการแจ้งเตือนที่เป็นของ user นี้ (user_id = ?)
// ไม่รวมประกาศทั่วไป (user_id IS NULL)
$stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    $deleted_count = $stmt->affected_rows;
    echo json_encode([
        'success' => true,
        'message' => "ลบการแจ้งเตือน {$deleted_count} รายการเรียบร้อย",
        'deleted_count' => $deleted_count
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการลบ']);
}

$stmt->close();
$conn->close();
