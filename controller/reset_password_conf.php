<?php
// controller/reset_password_conf.php
// (ฉบับ Clean ไม่มี Debug)

require_once 'config.php';

header('Content-Type: application/json');

$token = $_POST['token'] ?? '';
$new_pass = $_POST['new_password'] ?? '';
$confirm_pass = $_POST['confirm_new_password'] ?? '';

// 1. ตรวจสอบข้อมูล
if (empty($token) || empty($new_pass) || empty($confirm_pass)) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}
if (strlen($new_pass) < 8) {
    echo json_encode(['success' => false, 'message' => 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร']);
    exit;
}
if ($new_pass !== $confirm_pass) {
    echo json_encode(['success' => false, 'message' => 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน']);
    exit;
}

// 2. (สำคัญ) ตรวจสอบ Token อีกครั้ง
$stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expiry > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo json_encode(['success' => false, 'message' => 'Token ไม่ถูกต้อง หรือ หมดอายุแล้ว']);
    $stmt->close();
    $conn->close();
    exit;
}

// 3. ถ้า Token ถูกต้อง -> อัปเดตรหัสผ่าน
$user = $result->fetch_assoc();
$user_id = $user['id'];
$new_hashed_password = password_hash($new_pass, PASSWORD_BCRYPT);

// 4. อัปเดต และ *ล้าง Token ทิ้ง* (ป้องกันการใช้ซ้ำ)
$stmt_update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?");
$stmt_update->bind_param("si", $new_hashed_password, $user_id);

if ($stmt_update->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'อัปเดตฐานข้อมูลล้มเหลว']);
}

$stmt->close();
$stmt_update->close();
$conn->close();
?>