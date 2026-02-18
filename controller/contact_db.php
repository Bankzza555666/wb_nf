<?php
// controller/contact_db.php
header('Content-Type: application/json');

// เรียกใช้ไฟล์ Config เพื่อเชื่อมต่อฐานข้อมูล (ใช้ไฟล์เดียว)
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Request Method']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if (empty($name) || empty($email) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน']);
    exit;
}

// Prepare Statement เพื่อความปลอดภัย
$stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("ssss", $name, $email, $subject, $message);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'ส่งข้อความเรียบร้อยแล้ว ทีมงานจะรีบติดต่อกลับครับ!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'บันทึกข้อมูลล้มเหลว: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $conn->error]);
}

$conn->close();
?>