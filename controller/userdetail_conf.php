<?php
// (ไฟล์นี้แก้ไข Syntax Error '=>' ทั้งหมดแล้ว)

require_once 'auth_check.php'; 
require_once 'sendmail_conf.php'; 

header('Content-Type: application/json');

// 1. ตรวจสอบว่าล็อกอินหรือยัง
if (!isAuthenticated()) {
    echo json_encode(['success' => false, 'message' => 'คุณยังไม่ได้เข้าสู่ระบบ']);
    exit;
}

// 2. ตรวจสอบการเชื่อมต่อ DB
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'เชื่อมต่อฐานข้อมูลล้มเหลว']);
    exit;
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

// 3. ดึงข้อมูลผู้ใช้ปัจจุบัน
$stmt = $conn->prepare("SELECT username, password, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลผู้ใช้']);
    exit;
}


// === ตรรกะ: แยกตาม ACTION ===

if ($action == 'change_password') {
    // --- 4A. กระบวนการเปลี่ยนรหัสผ่าน ---
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_new_password'] ?? '';

    // (Validate)
    if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']); exit;
    }
    if (strlen($new_pass) < 8) {
        echo json_encode(['success' => false, 'message' => 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร']); exit;
    }
    if ($new_pass !== $confirm_pass) {
        echo json_encode(['success' => false, 'message' => 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน']); exit;
    }
    if (!password_verify($current_pass, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง!']); exit;
    }

    // อัปเดตรหัสผ่านใหม่
    $new_hashed_password = password_hash($new_pass, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $new_hashed_password, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'เปลี่ยนรหัสผ่านสำเร็จ!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'อัปเดตฐานข้อมูลล้มเหลว']);
    }
    $stmt->close();

} 
else if ($action == 'change_email') {
    // --- 4B. กระบวนการเปลี่ยนอีเมล ---
    $new_email = $_POST['new_email'] ?? '';
    $current_pass = $_POST['current_password_for_email'] ?? '';

    // (Validate)
    if (empty($new_email) || empty($current_pass)) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']); exit;
    }
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'รูปแบบอีเมลใหม่ไม่ถูกต้อง']); exit;
    }
    if (!password_verify($current_pass, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง!']); exit;
    }
    if ($new_email == $user['email']) {
         echo json_encode(['success' => false, 'message' => 'นี่คืออีเมลปัจจุบันของคุณอยู่แล้ว']); exit;
    }

    // (ตรวจสอบอีเมลซ้ำ)
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $new_email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'อีเมลนี้ถูกใช้งานโดยบัญชีอื่นแล้ว']);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // (สร้าง OTP และอัปเดต DB)
    $otp_code = rand(100000, 999999);
    $otp_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    $stmt = $conn->prepare("UPDATE users SET email = ?, status = 'nonverify', otp_code = ?, otp_expiry = ? WHERE id = ?");
    
    // (โค้ด 'bind_param' ที่ถูกต้องจากครั้งที่แล้ว)
    $stmt->bind_param("sssi", $new_email, $otp_code, $otp_expiry, $user_id);

    if ($stmt->execute()) {
        // (ส่ง OTP)
        $mail_sent = sendOTPEmail($new_email, $user['username'], $otp_code);

        if ($mail_sent) {
            echo json_encode([
                'success' => true, 
                'message' => 'เปลี่ยนอีเมลสำเร็จ! เราได้ส่ง OTP ไปยังอีเมลใหม่ของคุณ กรุณาเข้าระบบและยืนยันตัวตนอีกครั้ง',
                'force_logout' => true 
            ]);
        } else {
             echo json_encode(['success' => false, 'message' => 'บันทึกอีเมลใหม่สำเร็จ แต่ส่งอีเมลยืนยันล้มเหลว (SMTP Error)']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'อัปเดตฐานข้อมูลล้มเหลว']);
    }
    $stmt->close();

} 
else {
    echo json_encode(['success' => false, 'message' => 'ไม่พบการดำเนินการที่ร้องขอ']);
}

$conn->close();
?>