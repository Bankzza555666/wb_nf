<?php
// (ลบ Debug code ออกแล้ว)

require_once 'config.php';
require_once 'sendmail_conf.php';

header('Content-Type: application/json');

$email = $_POST['email'] ?? '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'รูปแบบอีเมลไม่ถูกต้อง']);
    exit;
}

// 1. ค้นหาผู้ใช้
$stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // 2A. ถ้าไม่เจออีเมล
    echo json_encode(['success' => false, 'message' => 'ไม่พบอีเมลนี้ในระบบ']);
    $stmt->close();
    $conn->close();
    exit;
}

// 2B. ถ้าเจออีเมล
$user = $result->fetch_assoc();

// 3. สร้าง Token และเวลาหมดอายุ
try {
    $token = bin2hex(random_bytes(32)); 
    $expiry = date('Y-m-d H:i:s', strtotime('+30 minutes'));

    // 4. บันทึก Token ลง DB
    $stmt_update = $conn->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE id = ?");
    $stmt_update->bind_param("ssi", $token, $expiry, $user['id']);
    
    if($stmt_update->execute()) {
        // 5. ส่งอีเมล
        $mail_sent = sendResetEmail($email, $user['username'], $token);

        if ($mail_sent) {
             echo json_encode(['success' => true, 'message' => 'ส่งอีเมลสำเร็จ! กรุณาตรวจสอบกล่องจดหมายของคุณ']);
        } else {
             echo json_encode(['success' => false, 'message' => 'พบอีเมลในระบบ แต่ส่งอีเมลล้มเหลว (SMTP Error)']);
        }

    } else {
        echo json_encode(['success' => false, 'message' => 'ล้มเหลวในการอัปเดต Token ใน DB']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'เกิด Error ขณะสร้าง Token']);
}

$stmt->close();
$conn->close();
?>