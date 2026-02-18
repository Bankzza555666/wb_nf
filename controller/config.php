<?php
// controller/config.php

// (สำคัญ) บังคับ Timezone ให้เป็นของประเทศไทย (UTC+7)
date_default_timezone_set('Asia/Bangkok');

/**
 * โหลดค่าจากไฟล์ .env
 * @param string $file_path พาธของไฟล์ .env
 */
function load_env($file_path) {
    if (!file_exists($file_path)) {
        die("Error: .env file not found at $file_path");
    }
    
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // ข้าม comment
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // แยก key=value
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // ถ้าไม่มีใน $_ENV ให้เซ็ตค่า
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
        }
    }
}

// โหลดไฟล์ .env จาก root directory
$env_path = dirname(__DIR__) . '/.env';
load_env($env_path);

/**
 * ดึงค่าจาก .env (หรือ getenv ถ้าใช้บนโฮสที่ set ผ่าน environment)
 * @param string $key ชื่อ key
 * @param mixed $default ค่า default ถ้าไม่พบ
 * @return mixed
 */
function env($key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);
    return $value !== false ? $value : $default;
}

// ========================================
// การตั้งค่าฐานข้อมูล (จาก .env)
// ========================================
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_NAME', env('DB_NAME', 'netfreevpn'));

define('SITE_NAME', 'NF~SHOP');

// ========================================
// การตั้งค่า SMTP (จาก .env)
// ========================================
define('SMTP_HOST', env('SMTP_HOST', 'smtp.hostinger.com'));
define('SMTP_USER', env('SMTP_USER', ''));
define('SMTP_PASS', env('SMTP_PASS', ''));
define('SMTP_PORT', env('SMTP_PORT', 465));

// ========================================
// Telegram Bot API (จาก .env)
// ========================================
define('TELEGRAM_BOT_TOKEN', env('TELEGRAM_BOT_TOKEN', ''));
define('TELEGRAM_CHAT_ID', env('TELEGRAM_CHAT_ID', ''));

// ========================================
// XDroid API Key (จาก .env)
// ========================================
define('XDROID_API_KEY', env('XDROID_API_KEY', ''));

// ========================================
// Telegram Chat Support (จาก .env)
// ========================================
define('TELEGRAM_CHAT_BOT_TOKEN', env('TELEGRAM_CHAT_BOT_TOKEN', ''));
define('TELEGRAM_CHAT_ADMIN_ID', env('TELEGRAM_CHAT_ADMIN_ID', ''));

// ========================================
// Typhoon AI API Key (จาก .env)
// ========================================
define('TYPHOON_API_KEY', env('TYPHOON_API_KEY', ''));

// ========================================
// SSH Development Mode (จาก .env)
// ========================================
define('SSH_DEV_MODE', env('SSH_DEV_MODE', 'false') === 'true');

// เชื่อมต่อ Database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ✅ Security Headers (ส่งเฉพาะเมื่อยังไม่มี output — ลดโอกาส HTTP 500 จาก "headers already sent" บนโฮสจริง)
if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
}

// ✅ Include CSRF Helper
require_once __DIR__ . '/csrf_helper.php';
?>