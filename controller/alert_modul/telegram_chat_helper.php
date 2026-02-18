<?php
// controller/alert_modul/telegram_chat_helper.php

// ฟังก์ชันสำหรับส่งแจ้งเตือน Chat ไปยัง Telegram Admin
function sendTelegramChatNotify($user_id, $username, $message, $imagePath = null)
{
    if (!defined('TELEGRAM_CHAT_BOT_TOKEN') || !defined('TELEGRAM_CHAT_ADMIN_ID')) {
        return false;
    }

    $token = TELEGRAM_CHAT_BOT_TOKEN;
    $chat_id = TELEGRAM_CHAT_ADMIN_ID;

    // สร้างข้อความ โดยระบุ User ID ไว้เพื่อให้ Webhook จับคู่ได้ถูกต้อง (สำคัญ!)
    $txt = "📩 *New Message from* `{$username}`\n";
    $txt .= "🆔 Reference: #User{$user_id}\n\n"; // Tag สำหรับ Reply Hook
    $txt .= "💬 {$message}";

    if ($imagePath) {
        // Auto-detect Protocol
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];

        // Auto-detect Folder Path
        // If localhost, keep '/Bankweb'. If production, assume root.
        $pathPrefix = "";
        if ($host === 'localhost' || strpos($host, '127.0.0.1') !== false) {
            $pathPrefix = "/Bankweb";
        }

        $fullImgUrl = $protocol . "://" . $host . $pathPrefix . "/" . $imagePath;
        $txt .= "\n\n🖼 [View Image]({$fullImgUrl})";
    }

    // ส่งแบบ Markdown
    $data = [
        'chat_id' => $chat_id,
        'text' => $txt,
        'parse_mode' => 'Markdown'
    ];

    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}
?>