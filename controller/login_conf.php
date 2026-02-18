<?php
// controller/login_conf.php
// (ฉบับสุดท้าย - Debug Mode + Safe Telegram)



// === STEP 2: Load Dependencies ===
require_once 'config.php';
require_once 'alert_modul/login_telegram_notify.php';

header('Content-Type: application/json');

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'เชื่อมต่อฐานข้อมูลล้มเหลว (DB Config Error)']);
    exit;
}

// ✅ CSRF Check
if (!verifyCsrfToken()) {
    echo json_encode(['success' => false, 'message' => 'Security Token Invalid (CSRF) - Please refresh the page']);
    exit;
}

// --- 1. รับข้อมูล ---
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน']);
    exit;
}

// --- 2. ค้นหาผู้ใช้ ---
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'ชื่อผู้ใช้ หรือ รหัสผ่านไม่ถูกต้อง']);
    $stmt->close();
    $conn->close();
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// --- 3. ตรวจสอบรหัสผ่าน ---
if (!password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'ชื่อผู้ใช้ หรือ รหัสผ่านไม่ถูกต้อง']);
    $conn->close();
    exit;
}

// --- 4. ตรวจสอบสถานะ (Status) ---
if ($user['status'] == 'verify') {
    // 4.1 สถานะ: ยืนยันแล้ว
    $stmt = $conn->prepare("UPDATE users SET last_login = NOW(), ip_address = ? WHERE id = ?");
    $stmt->bind_param("si", $ip_address, $user['id']);
    $stmt->execute();
    $stmt->close();

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    if (!empty($user['role'])) {
        $_SESSION['role'] = $user['role'];
    }

    // (โค้ด "Remember Me")
    if (isset($_POST['rememberMe']) && $_POST['rememberMe'] == 'on') {
        try {
            $selector = bin2hex(random_bytes(16));
            $validator = bin2hex(random_bytes(32));
            $hashed_validator = password_hash($validator, PASSWORD_BCRYPT);
            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
            $user_id = $user['id'];
            $stmt_token = $conn->prepare("INSERT INTO auth_tokens (user_id, selector, hashed_validator, expires) VALUES (?, ?, ?, ?)");
            $stmt_token->bind_param("isss", $user_id, $selector, $hashed_validator, $expires);
            $stmt_token->execute();
            $stmt_token->close();
            setcookie('remember_me', $selector . ':' . $validator, time() + (86400 * 30), "/");
        } catch (Exception $e) {
            error_log("❌ Remember Me Error: " . $e->getMessage());
        }
    }

    // === STEP 3: ส่ง Telegram (Safe Mode) ===
    try {
        // ตรวจสอบว่า Function มีหรือไม่
        if (!function_exists('sendLoginNotify')) {
            error_log("⚠️ [LOGIN] sendLoginNotify() function not found!");
        } else {
            // ตรวจสอบว่ามี Config หรือไม่
            if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_CHAT_ID')) {
                error_log("⚠️ [LOGIN] Telegram config not defined!");
            } else {
                $balance = isset($user['credit']) ? $user['credit'] : 0;

                // เรียก Function
                error_log("🚀 [LOGIN] Calling sendLoginNotify for user: {$user['username']}");
                sendLoginNotify($user['username'], $ip_address, $balance);
                error_log("✅ [LOGIN] sendLoginNotify executed (check function logs for result)");
            }
        }
    } catch (Exception $e) {
        // ถ้า Telegram พัง ก็ไม่ให้กระทบกับการ Login
        error_log("❌ [LOGIN] Telegram Exception: " . $e->getMessage());
    } catch (Error $e) {
        // จับ Fatal Error ด้วย (PHP 7+)
        error_log("💥 [LOGIN] Telegram Fatal Error: " . $e->getMessage());
    }
    // === จบส่วน Telegram ===

    echo json_encode(['success' => true, 'verified' => true]);

} else {
    // 4.2 สถานะ: ยังไม่ยืนยัน (nonverify)
    $_SESSION['temp_user_id'] = $user['id'];
    echo json_encode(['success' => true, 'verified' => false]);
}

$conn->close();