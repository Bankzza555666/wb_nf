<?php
// controller/alert_modul/xdroid_notify.php

// ฟังก์ชันกลางสำหรับยิง Request
function sendXdroidRequest($title, $content) {
    if (!defined('XDROID_API_KEY') || empty(XDROID_API_KEY)) {
        return false;
    }

    $url = 'http://xdroid.net/api/message?' . http_build_query([
        'k' => XDROID_API_KEY,
        't' => $title,
        'c' => $content
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout เร็วหน่อยกันแชทหน่วง
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($http_code == 200);
}

// ฟังก์ชันเดิม (เติมเงิน)
function sendXdroidTopupNotify($username, $amount, $current_balance) {
    $title = '💰 รายงานการเติมเงิน';
    $content = sprintf('สมาชิก %s เติมเงิน %.0f บาท (คงเหลือ %.0f)', $username, $amount, $current_balance);
    return sendXdroidRequest($title, $content);
}

// ✅ ฟังก์ชันใหม่ (แจ้งเตือนแชท)
function sendXdroidChat($username, $message) {
    // ตัดข้อความถ้ายาวเกินไป
    $short_msg = mb_substr($message, 0, 100, 'UTF-8');
    if(mb_strlen($message) > 100) $short_msg .= '...';

    $title = "💬 ข้อความจาก: $username";
    $content = $short_msg;

    return sendXdroidRequest($title, $content);
}
?>