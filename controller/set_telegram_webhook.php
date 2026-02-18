<?php
// controller/set_telegram_webhook.php
// รันไฟล์นี้เพื่อตั้งค่า Webhook (ต้องทำบน Server ที่มี HTTPS จริง หรือใช้ ngrok)

require_once __DIR__ . '/config.php';

if (!defined('TELEGRAM_CHAT_BOT_TOKEN')) {
    die("Error: Token not defined in config.php");
}

// ** แก้ไขตรงนี้เป็น URL ของไฟล์ webhook_chat_reply.php บน Server ของคุณ **
// ตัวอย่าง: https://your-domain.com/controller/webhook_chat_reply.php
$webhook_url = "https://netfree.in.th/controller/webhook_chat_reply.php";

$token = TELEGRAM_CHAT_BOT_TOKEN;
$url = "https://api.telegram.org/bot{$token}/setWebhook?url={$webhook_url}";

echo "<h1>Setting Webhook...</h1>";
echo "<p>Target URL: $webhook_url</p>";

$response = file_get_contents($url);
echo "<h2>Response from Telegram:</h2>";
echo "<pre>";
print_r(json_decode($response, true));
echo "</pre>";

echo "<hr>";
echo "<h3>วิธีใช้งาน:</h3>";
echo "1. แก้ไขไฟล์นี้ (`controller/set_telegram_webhook.php`) ตรงตัวแปร <b>`\$webhook_url`</b> ให้เป็น URL จริงของไฟล์ `webhook_chat_reply.php` บนเว็บของคุณ<br>";
echo "2. รันไฟล์นี้ผ่าน Browser";
?>