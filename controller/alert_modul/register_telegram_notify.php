<?php
// controller/alert_modul/register_telegram_notify.php

/**
 * ฟังก์ชันส่ง Telegram แจ้งเตือน (สำหรับสมัครใหม่)
 */
function sendRegisterNotify($username, $email, $ip_address, $balance) {
    
    $token = TELEGRAM_BOT_TOKEN;
    $chat_id = TELEGRAM_CHAT_ID;
    $url = "https://api.telegram.org/bot" . $token . "/sendMessage";

    $message = "<b>🔔 V2BOX: สมัครสมาชิกใหม่!</b>\n\n";
    $message .= "👤 <b>Username:</b> " . htmlspecialchars($username) . "\n";
    $message .= "📧 <b>Email:</b> " . htmlspecialchars($email) . "\n";
    $message .= "🌐 <b>IP:</b> " . $ip_address . "\n";
    $message .= "💰 <b>ยอดเงิน:</b> " . number_format($balance, 2) . " บาท";

    $post_fields = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // (สำหรับ Localhost)
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // (สำหรับ Localhost)
    
    curl_exec($ch);
    curl_close($ch);
}

