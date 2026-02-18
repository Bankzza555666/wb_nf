<?php
// controller/validation_email_conf.php
require_once 'config.php';

// (แก้ไข) ถ้าไม่มี session ให้เด้งกลับ ?r=otp
if (!isset($_SESSION['temp_user_id']) || !isset($_POST['otp'])) {
    header('Location: ../?r=otp'); 
    exit;
}

$otp_input = $_POST['otp'];
$user_id = $_SESSION['temp_user_id'];

// --- 1. ดึงข้อมูลผู้ใช้ ---
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: ../?r=otp&error=dberror'); // (แก้ไข) ใช้ ?r=
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// --- 2. ตรวจสอบ OTP ---
if ($user['otp_code'] !== $otp_input) {
    header('Location: ../?r=otp&error=invalid_otp'); // (แก้ไข) ใช้ ?r=
    exit;
}

// --- 3. ตรวจสอบเวลาหมดอายุ ---
if (strtotime($user['otp_expiry']) < time()) {
    header('Location: ../?r=otp&error=expired_otp'); // (แก้ไข) ใช้ ?r=
    exit;
}

// --- 4. ถ้าทุกอย่างถูกต้อง: อัปเดตสถานะผู้ใช้ ---
$stmt = $conn->prepare("UPDATE users SET 
                        status = 'verify', 
                        otp_code = NULL, 
                        otp_expiry = NULL, 
                        last_login = NOW() 
                      WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();

// --- 5. สร้าง Session ล็อกอินถาวร ---
unset($_SESSION['temp_user_id']);
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];

// --- 6. (แก้ไข) ส่งผู้ใช้ไปยังหน้า Dashboard ผ่าน Router (?p=home) ---
header('Location: ../?p=home');
exit;

?>