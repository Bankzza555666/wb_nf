<?php
// c:\xampp\htdocs\controller\xui_api\buy_notify.php

// ตรวจสอบว่ามีการโหลด config.php แล้วหรือยัง
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config.php';
}

// ตรวจสอบว่ามีการโหลด PHPMailer แล้วหรือยัง
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
    require_once __DIR__ . '/../PHPMailer/src/Exception.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * ส่งอีเมลแจ้งเตือนเมื่อผู้ใช้เช่า VPN สำเร็จ
 *
 * @param string $username ชื่อผู้ใช้
 * @param string $profile_name ชื่อโปรไฟล์ VPN
 * @param int $data_gb ปริมาณ Data (GB)
 * @param int $days จำนวนวัน
 * @param float $total_price ราคารวม
 * @param string $config_url URL ของ Config
 * @param string $user_email อีเมลของผู้ใช้
 * @param string $server_name ชื่อ Server
 * @return bool true ถ้าส่งสำเร็จ, false ถ้าส่งไม่สำเร็จ
 */
function sendServerRentalSuccessNotify($username, $profile_name, $data_gb, $days, $total_price, $config_url, $user_email, $server_name) {
    global $conn; // ใช้ $conn จาก config.php

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // หรือ PHPMailer::ENCRYPTION_SMTPS
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        // Recipients
        $mail->setFrom(SMTP_USERNAME, SITE_NAME);
        $mail->addAddress($user_email, $username); // ส่งถึงผู้ใช้

        // Content
        $mail->isHTML(true);
        $mail->Subject = '✅ รายละเอียด VPN ของคุณจาก ' . SITE_NAME;
        $mail->Body    = "
            <html>
            <head>
                <title>รายละเอียด VPN ของคุณ</title>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { width: 80%; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9; }
                    h2 { color: #0056b3; }
                    ul { list-style: none; padding: 0; }
                    li { margin-bottom: 10px; }
                    .button { display: inline-block; background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
                    .footer { margin-top: 20px; font-size: 0.8em; color: #777; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2>สวัสดีคุณ {$username},</h2>
                    <p>การเช่า VPN ของคุณสำเร็จแล้ว! นี่คือรายละเอียด:</p>
                    <ul>
                        <li><strong>ชื่อ Server:</strong> {$server_name}</li>
                        <li><strong>โปรไฟล์:</strong> {$profile_name}</li>
                        <li><strong>ปริมาณ Data:</strong> {$data_gb} GB</li>
                        <li><strong>ระยะเวลา:</strong> {$days} วัน</li>
                        <li><strong>ราคารวม:</strong> ฿" . number_format($total_price, 2) . "</li>
                        <li><strong>Config URL:</strong> <a href='{$config_url}'>คลิกที่นี่เพื่อดาวน์โหลด/คัดลอก Config</a></li>
                    </ul>
                    <p>คุณสามารถจัดการ VPN ของคุณได้ที่ <a href='" . SITE_URL . "?p=my_vpn' class='button'>VPN ของฉัน</a></p>
                    <div class='footer'>
                        <p>ขอบคุณที่ใช้บริการ <a href='" . SITE_URL . "'>" . SITE_NAME . "</a></p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // บันทึกข้อผิดพลาดลงใน Log
        error_log("Mailer Error (sendServerRentalSuccessNotify): " . $mail->ErrorInfo);
        return false;
    }
}

?>