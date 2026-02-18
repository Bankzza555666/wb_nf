<?php
// controller/alert_modul/login_telagram_notify.php
// (ฉบับแก้ไขเรียบง่าย - เพิ่ม Logging เท่านั้น)

/**
 * ฟังก์ชันส่ง Telegram แจ้งเตือน (สำหรับเข้าระบบ)
 */
function sendLoginNotify($username, $ip_address, $balance) {
    
    // จับเวลาเริ่มต้น
    $start_time = microtime(true);
    
    $token = TELEGRAM_BOT_TOKEN;
    $chat_id = TELEGRAM_CHAT_ID;
    $url = "https://api.telegram.org/bot" . $token . "/sendMessage";

    // จัดการกรณี $balance เป็น NULL
    $balance_display = is_numeric($balance) ? number_format($balance, 2) : '0.00';

    $message = "<b>🔔 V2BOX: เข้าระบบสำเร็จ</b>\n\n";
    $message .= "👤 <b>Username:</b> " . htmlspecialchars($username) . "\n";
    $message .= "🌐 <b>IP:</b> " . htmlspecialchars($ip_address) . "\n";
    $message .= "💰 <b>ยอดเงิน:</b> " . $balance_display . " บาท\n";
    $message .= "⏰ <b>เวลา:</b> " . date('Y-m-d H:i:s');

    $post_fields = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML' 
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // เปลี่ยนเป็น true
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields)); // ใช้ http_build_query
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout 10 วินาที
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // จับเวลาสิ้นสุด
    $execution_time = round((microtime(true) - $start_time) * 1000, 2);

    // === บันทึก LOG (ไม่ว่าจะสำเร็จหรือล้มเหลว) ===
    if ($response === false) {
        // cURL Error
        error_log("❌ [LOGIN NOTIFY] cURL Error for user '{$username}': {$curl_error} (Time: {$execution_time}ms)");
    } else {
        $result = json_decode($response, true);
        
        if ($http_code === 200 && isset($result['ok']) && $result['ok'] === true) {
            // สำเร็จ
            error_log("✅ [LOGIN NOTIFY] Success for user '{$username}' (Message ID: {$result['result']['message_id']}, Time: {$execution_time}ms)");
        } else {
            // Telegram API Error
            $error_desc = isset($result['description']) ? $result['description'] : 'Unknown error';
            error_log("❌ [LOGIN NOTIFY] Telegram API Error (HTTP {$http_code}) for user '{$username}': {$error_desc} (Time: {$execution_time}ms)");
        }
    }
    
    // ไม่ต้อง return อะไร (เหมือนเดิม เพื่อ Backward Compatibility)
}