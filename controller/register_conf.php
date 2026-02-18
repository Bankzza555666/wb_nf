<?php
/**
 * controller/register_conf.php
 * (ฉบับ v2.0 - Enhanced Security + Referral Anti-fraud)
 */

// === STEP 1: Error handling (log only, ไม่แสดงบนหน้าเว็บ) ===
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// === STEP 2: Load Dependencies ===
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sendmail_conf.php';
require_once __DIR__ . '/alert_modul/register_telegram_notify.php';
require_once __DIR__ . '/referral_helper.php'; // ✅ Referral System

header('Content-Type: application/json');

// ✅ Check registration toggle
try {
    $allow_register = '1';
    $stmt_setting = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'allow_register' LIMIT 1");
    if ($stmt_setting) {
        $stmt_setting->execute();
        $setting_res = $stmt_setting->get_result();
        if ($row = $setting_res->fetch_assoc()) {
            $allow_register = $row['setting_value'];
        }
        $stmt_setting->close();
    }

    if ($allow_register === '0') {
        echo json_encode(['success' => false, 'message' => 'ระบบปิดรับสมัครชั่วคราว']);
        exit;
    }
} catch (Exception $e) {
    // ถ้าอ่านค่าไม่ได้ ให้ทำงานต่อได้
}

// สร้างตาราง referral ถ้ายังไม่มี
initReferralTables($conn);

// --- 1. รับข้อมูล & ป้องกันบอท (Bot Protection) ---
$honeypot = $_POST['website_url'] ?? '';
if (!empty($honeypot)) {
    // 🛡️ Honeypot Triggered
    error_log("Bot detected (Honeypot): " . $_SERVER['REMOTE_ADDR']);
    die(); // Silent death
}

$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
$user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

// 🛡️ Rate Limiting: Max 3 accounts per IP per hour
try {
    $stmt_limit = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt_limit->bind_param("s", $ip_address);
    $stmt_limit->execute();
    $limit_res = $stmt_limit->get_result()->fetch_assoc();
    $stmt_limit->close();

    if ($limit_res['count'] >= 3) {
        echo json_encode(['success' => false, 'message' => 'คุณสมัครสมาชิกบ่อยเกินไป กรุณาลองใหม่ภายหลัง']);
        exit;
    }
} catch (Exception $e) {
    // ถ้า column ไม่มี ลองใช้ register_at
    try {
        $stmt_limit = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE ip_address = ? AND register_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt_limit->bind_param("s", $ip_address);
        $stmt_limit->execute();
        $limit_res = $stmt_limit->get_result()->fetch_assoc();
        $stmt_limit->close();

        if ($limit_res['count'] >= 3) {
            echo json_encode(['success' => false, 'message' => 'คุณสมัครสมาชิกบ่อยเกินไป กรุณาลองใหม่ภายหลัง']);
            exit;
        }
    } catch (Exception $e2) {}
}

$email = trim($_POST['email'] ?? '');
$username = trim($_POST['username_reg'] ?? '');
$password = $_POST['password_reg'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$referral_code_input = strtoupper(trim($_POST['referral_code'] ?? '')); // ✅ รหัสแนะนำ

// ✅ ตรวจสอบรหัสแนะนำ (ใช้ helper function ที่มี anti-fraud)
$referred_by = null;
$referrer_data = null;

if (!empty($referral_code_input)) {
    // เช็คว่า anti-fraud เปิดอยู่ไหม
    $anti_fraud = getReferralSetting($conn, 'anti_fraud_enabled', '1') == '1';
    $ip_limit = intval(getReferralSetting($conn, 'same_ip_referral_limit', 3));
    
    // หา referrer
    $ref_stmt = $conn->prepare("SELECT id, username, referral_locked, ip_address FROM users WHERE referral_code = ?");
    $ref_stmt->bind_param("s", $referral_code_input);
    $ref_stmt->execute();
    $referrer_data = $ref_stmt->get_result()->fetch_assoc();
    $ref_stmt->close();
    
    if ($referrer_data) {
        // เช็คว่า referrer ถูก lock ไหม
        if ($referrer_data['referral_locked']) {
            // ไม่แจ้ง error แค่ไม่ใส่ referrer
            $referrer_data = null;
        }
        // เช็ค IP ซ้ำกับ referrer
        elseif ($anti_fraud && $ip_address === $referrer_data['ip_address']) {
            $referrer_data = null;
        }
        // เช็คจำนวน accounts จาก IP เดียวกันที่ใช้ referrer คนนี้
        elseif ($anti_fraud) {
            $ip_check = $conn->prepare("SELECT COUNT(*) as c FROM users WHERE referred_by = ? AND ip_address = ?");
            $ip_check->bind_param("is", $referrer_data['id'], $ip_address);
            $ip_check->execute();
            $ip_count = $ip_check->get_result()->fetch_assoc()['c'];
            $ip_check->close();
            
            if ($ip_count >= $ip_limit) {
                $referrer_data = null;
            }
        }
        
        // เช็คจำนวน referrals ของ referrer
        if ($referrer_data) {
            $max_referrals = intval(getReferralSetting($conn, 'max_referrals_per_user', 100));
            $count_check = $conn->prepare("SELECT COUNT(*) as c FROM users WHERE referred_by = ?");
            $count_check->bind_param("i", $referrer_data['id']);
            $count_check->execute();
            $ref_count = $count_check->get_result()->fetch_assoc()['c'];
            $count_check->close();
            
            if ($ref_count >= $max_referrals) {
                $referrer_data = null;
            }
        }
        
        if ($referrer_data) {
            $referred_by = $referrer_data['id'];
        }
    }
}

// 2. ตรวจสอบข้อมูล (Validation)
if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
    exit;
}
if ($password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'รหัสผ่านและการยืนยันไม่ตรงกัน']);
    exit;
}
if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'รูปแบบอีเมลไม่ถูกต้อง']);
    exit;
}

// 3. ตรวจสอบ Username/Email ซ้ำ
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
$stmt->bind_param("ss", $username, $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Username หรือ Email นี้ถูกใช้งานแล้ว']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// 4. สร้าง OTP และ Hash รหัสผ่าน (ใช้ random_int เพื่อความปลอดภัย)
$otp_code = random_int(100000, 999999);
$otp_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

// 5. สร้าง referral_code สำหรับ user ใหม่
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
do {
    $new_referral_code = '';
    for ($i = 0; $i < 6; $i++) {
        $new_referral_code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $check_code = $conn->query("SELECT id FROM users WHERE referral_code = '$new_referral_code'");
} while ($check_code && $check_code->num_rows > 0);

// 6. บันทึกลงฐานข้อมูล (รวม referral_code และ referred_by)
$stmt = $conn->prepare("INSERT INTO users (username, email, password, otp_code, otp_expiry, status, ip_address, referral_code, referred_by) VALUES (?, ?, ?, ?, ?, 'nonverify', ?, ?, ?)");
$stmt->bind_param("sssssssi", $username, $email, $hashed_password, $otp_code, $otp_expiry, $ip_address, $new_referral_code, $referred_by);

if ($stmt->execute()) {
    $new_user_id = $conn->insert_id;
    
    // ✅ Log การสมัครสมาชิก
    if ($referred_by) {
        logReferralAction($conn, $new_user_id, 'register_with_referral', $referred_by, null, $referral_code_input, null, "New user registered with referral code");
    }
    
    // 6. ส่งอีเมล OTP
    $mail_sent = sendOTPEmail($email, $username, $otp_code);

    // === STEP 3: ส่ง Telegram (Safe Mode) ===
    try {
        // ตรวจสอบว่า Function มีหรือไม่
        if (!function_exists('sendRegisterNotify')) {
            error_log("⚠️ [REGISTER] sendRegisterNotify() function not found!");
        } else {
            // ตรวจสอบว่ามี Config หรือไม่
            if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_CHAT_ID')) {
                error_log("⚠️ [REGISTER] Telegram config not defined!");
            } else {
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'N/A';

                // เรียก Function
                error_log("🚀 [REGISTER] Calling sendRegisterNotify for user: {$username}");
                sendRegisterNotify($username, $email, $ip_address, 0.00);
                error_log("✅ [REGISTER] sendRegisterNotify executed (check function logs for result)");
            }
        }
    } catch (Exception $e) {
        // ถ้า Telegram พัง ก็ไม่ให้กระทบกับการสมัครสมาชิก
        error_log("❌ [REGISTER] Telegram Exception: " . $e->getMessage());
    } catch (Error $e) {
        // จับ Fatal Error ด้วย (PHP 7+)
        error_log("💥 [REGISTER] Telegram Fatal Error: " . $e->getMessage());
    }
    // === จบส่วน Telegram ===

    // 8. ตอบกลับสำเร็จ
    echo json_encode(['success' => true]);

} else {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการสมัครสมาชิก']);
}

$stmt->close();
$conn->close();
