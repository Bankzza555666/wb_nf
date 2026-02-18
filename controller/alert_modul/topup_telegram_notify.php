<?php
// controller/alert_modul/topup_telegram_notify.php

/**
 * ฟังก์ชันส่ง Telegram แจ้งเตือนเติมเงินสำเร็จ
 * 
 * @param string $username ชื่อผู้ใช้
 * @param float $amount จำนวนเงินที่เติม
 * @param float $bonus โบนัส
 * @param int $transaction_id รหัสรายการ
 * @param string $payment_method ช่องทางชำระเงิน
 */
function sendTopupSuccessNotify($username, $amount, $bonus, $transaction_id, $payment_method) {
    
    $token = TELEGRAM_BOT_TOKEN;
    $chat_id = TELEGRAM_CHAT_ID;
    $url = "https://api.telegram.org/bot" . $token . "/sendMessage";
    
    // แปลงเป็นตัวเลข (ป้องกัน Error)
    $amount = floatval($amount);
    $bonus = floatval($bonus);
    $total = $amount + $bonus;
    
    // สร้างข้อความ
    $message = "<b>🎉 V2BOX: เติมเงินสำเร็จ!</b>\n\n";
    $message .= "👤 <b>Username:</b> " . htmlspecialchars($username) . "\n";
    $message .= "💰 <b>จำนวนเงิน:</b> ฿" . number_format($amount, 2) . "\n";
    $message .= "🎁 <b>โบนัส:</b> ฿" . number_format($bonus, 2) . "\n";
    $message .= "💵 <b>รวมทั้งหมด:</b> ฿" . number_format($total, 2) . "\n";
    $message .= "💳 <b>ช่องทาง:</b> " . htmlspecialchars($payment_method) . "\n";
    $message .= "🔖 <b>รหัสรายการ:</b> #" . $transaction_id . "\n";
    $message .= "⏰ <b>เวลา:</b> " . date('d/m/Y H:i:s');
    
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

/**
 * ฟังก์ชันส่ง Telegram แจ้งเตือนมีคำขอเติมเงิน (เมื่อกดปุ่มเติมเงิน)
 * 
 * @param string $username ชื่อผู้ใช้
 * @param float $amount จำนวนเงินที่ต้องการเติม
 * @param int $transaction_id รหัสรายการ
 * @param string $payment_method ช่องทางชำระเงิน
 */
function sendTopupRequestNotify($username, $amount, $transaction_id, $payment_method) {
    
    $token = TELEGRAM_BOT_TOKEN;
    $chat_id = TELEGRAM_CHAT_ID;
    $url = "https://api.telegram.org/bot" . $token . "/sendMessage";
    
    // แปลงเป็นตัวเลข
    $amount = floatval($amount);
    
    // สร้างข้อความ
    $message = "<b>📋 V2BOX: คำขอเติมเงินใหม่</b>\n\n";
    $message .= "👤 <b>Username:</b> " . htmlspecialchars($username) . "\n";
    $message .= "💰 <b>จำนวนเงิน:</b> ฿" . number_format($amount, 2) . "\n";
    $message .= "💳 <b>ช่องทาง:</b> " . htmlspecialchars($payment_method) . "\n";
    $message .= "🔖 <b>รหัสรายการ:</b> #" . $transaction_id . "\n";
    $message .= "⏰ <b>เวลา:</b> " . date('d/m/Y H:i:s') . "\n\n";
    $message .= "⏳ <b>สถานะ:</b> รอชำระเงิน...";
    
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}
?>