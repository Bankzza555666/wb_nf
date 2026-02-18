<?php
// controller/resend_otp_conf.php (ไฟล์ใหม่)
require_once 'config.php';
require_once 'sendmail_conf.php';

header('Content-Type: application/json');

// 1. ตรวจสอบว่ามี Session ชั่วคราว (temp_user_id) หรือไม่
if (!isset($_SESSION['temp_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session หมดอายุ กรุณาล็อกอินใหม่']);
    exit;
}

$user_id = $_SESSION['temp_user_id'];

// 2. ดึงข้อมูลผู้ใช้ (เพื่อเอา username, email)
$stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบผู้ใช้งาน']);
    exit;
}

// 3. สร้าง OTP และเวลาหมดอายุใหม่ (15 นาที)
$otp_code = rand(100000, 999999);
$otp_expiry_time = strtotime('+15 minutes'); // เวลาสำหรับ JS
$otp_expiry_db = date('Y-m-d H:i:s', $otp_expiry_time); // เวลาสำหรับ DB

// 4. อัปเดต OTP ใหม่ลงฐานข้อมูล
$stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE id = ?");
$stmt->bind_param("ssi", $otp_code, $otp_expiry_db, $user_id);
$stmt->execute();
$stmt->close();

// 5. ส่งอีเมล
$mail_sent = sendOTPEmail($user['email'], $user['username'], $otp_code);

if ($mail_sent) {
    echo json_encode([
        'success' => true, 
        'message' => 'ส่ง OTP ใหม่สำเร็จ!',
        'new_expiry_timestamp' => $otp_expiry_time * 1000 // ส่งเวลาใหม่ (ในหน่วย ms) กลับไปให้ JS
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'ส่ง OTP ล้มเหลว (SMTP Error)']);
}

$conn->close();
?>