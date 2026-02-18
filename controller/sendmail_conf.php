<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../Phpmailer/src/Exception.php';
require __DIR__ . '/../Phpmailer/src/PHPMailer.php';
require __DIR__ . '/../Phpmailer/src/SMTP.php';

// --- Helper: CSS Styles สำหรับ Email (ใช้ซ้ำได้) ---
$emailHeaderStyle = "background-color: #1e293b; padding: 20px; text-align: center; border-top-left-radius: 8px; border-top-right-radius: 8px;";
$emailBodyStyle = "background-color: #ffffff; padding: 30px; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);";
$footerStyle = "text-align: center; padding-top: 20px; font-size: 12px; color: #94a3b8;";
$btnStyle = "display: inline-block; background-color: #6366f1; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 20px 0;";

function sendOTPEmail($recipient_email, $recipient_name, $otp_code) {
    global $emailHeaderStyle, $emailBodyStyle, $footerStyle;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(SMTP_USER, SITE_NAME . ' Security Team'); // ปรับชื่อผู้ส่งให้ดูทางการ
        $mail->addAddress($recipient_email, $recipient_name);
        $mail->isHTML(true);
        $mail->Subject = '🔒 รหัสยืนยันตัวตน (OTP) - ' . SITE_NAME;
        
        $mail->Body = "
            <div style='background-color: #f1f5f9; padding: 40px 0; font-family: \"Sarabun\", \"Prompt\", Arial, sans-serif;'>
                <div style='max-width: 600px; margin: 0 auto;'>
                    
                    <div style='$emailHeaderStyle'>
                        <h2 style='color: #ffffff; margin: 0; font-size: 24px;'>" . SITE_NAME . "</h2>
                    </div>

                    <div style='$emailBodyStyle'>
                        <h3 style='color: #1e293b; margin-top: 0;'>เรียน คุณ $recipient_name</h3>
                        <p style='color: #475569; font-size: 16px; line-height: 1.6;'>
                            เราได้รับคำขอเพื่อยืนยันตัวตนสำหรับบัญชีของคุณ<br>
                            โปรดใช้รหัสยืนยันตัวตน (OTP) ด้านล่างนี้เพื่อดำเนินการต่อให้เสร็จสมบูรณ์
                        </p>

                        <div style='background-color: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 15px; text-align: center; margin: 25px 0;'>
                            <span style='font-size: 14px; color: #64748b; display: block; margin-bottom: 5px;'>รหัส OTP ของคุณ</span>
                            <span style='font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #6366f1;'>$otp_code</span>
                        </div>

                        <p style='color: #ef4444; font-size: 14px;'>
                            <strong>⚠️ คำเตือน:</strong> ห้ามเปิดเผยรหัสนี้แก่ผู้อื่น พนักงานของ " . SITE_NAME . " จะไม่ขอรหัสนี้จากคุณ
                        </p>
                        
                        <p style='color: #94a3b8; font-size: 14px;'>
                            รหัสนี้จะหมดอายุภายใน 15 นาที หากคุณไม่ได้ทำรายการนี้ โปรดเพิกเฉยต่ออีเมลฉบับนี้
                        </p>
                    </div>

                    <div style='$footerStyle'>
                        <p>&copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p>
                        <p>อีเมลฉบับนี้เป็นการแจ้งเตือนอัตโนมัติ กรุณาอย่าตอบกลับ</p>
                    </div>
                </div>
            </div>
        ";
        $mail->AltBody = "รหัส OTP ของคุณคือ: $otp_code (หมดอายุใน 15 นาที) - ทีมงาน " . SITE_NAME;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('OTP Email Error: ' . $e->getMessage());
        return false;
    }
}

function sendResetEmail($recipient_email, $recipient_name, $token) {
    global $emailHeaderStyle, $emailBodyStyle, $footerStyle, $btnStyle;
    $mail = new PHPMailer(true);
    $reset_link = "https://netfree.in.th/?r=reset_password&token=" . $token;
    
    try {
        $mail->SMTPDebug = 0; // ปิด Debug เพื่อความสะอาดของ Log (เปิดเมื่อจำเป็น)
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(SMTP_USER, SITE_NAME . ' Support');
        $mail->addAddress($recipient_email, $recipient_name);
        $mail->isHTML(true);
        $mail->Subject = '🔑 คำขอตั้งค่ารหัสผ่านใหม่ - ' . SITE_NAME;
        
        $mail->Body = "
            <div style='background-color: #f1f5f9; padding: 40px 0; font-family: \"Sarabun\", \"Prompt\", Arial, sans-serif;'>
                <div style='max-width: 600px; margin: 0 auto;'>
                    
                    <div style='$emailHeaderStyle'>
                        <h2 style='color: #ffffff; margin: 0; font-size: 24px;'>" . SITE_NAME . "</h2>
                    </div>

                    <div style='$emailBodyStyle'>
                        <h3 style='color: #1e293b; margin-top: 0;'>เรียน คุณ $recipient_name</h3>
                        <p style='color: #475569; font-size: 16px; line-height: 1.6;'>
                            เราได้รับคำขอให้รีเซ็ตรหัสผ่านสำหรับบัญชีของคุณ<br>
                            คุณสามารถตั้งรหัสผ่านใหม่ได้โดยการคลิกที่ปุ่มด้านล่างนี้
                        </p>

                        <div style='text-align: center;'>
                            <a href='$reset_link' style='$btnStyle' target='_blank'>ตั้งค่ารหัสผ่านใหม่</a>
                        </div>

                        <p style='color: #94a3b8; font-size: 14px; margin-top: 20px;'>
                            หากปุ่มด้านบนไม่ทำงาน สามารถคัดลอกลิงก์ด้านล่างไปวางในเบราว์เซอร์ของคุณได้:<br>
                            <a href='$reset_link' style='color: #6366f1; word-break: break-all;'>$reset_link</a>
                        </p>
                        
                        <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                        
                        <p style='color: #64748b; font-size: 14px;'>
                            ลิงก์นี้จะหมดอายุภายใน 30 นาที<br>
                            หากคุณไม่ได้เป็นผู้ร้องขอ โปรดเพิกเฉยต่ออีเมลฉบับนี้ บัญชีของคุณยังคงปลอดภัย
                        </p>
                    </div>

                    <div style='$footerStyle'>
                        <p>&copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p>
                        <p>ต้องการความช่วยเหลือ? ติดต่อฝ่ายบริการลูกค้า</p>
                    </div>
                </div>
            </div>
        ";
        
        $mail->AltBody = "เรียน $recipient_name, คลิกลิงก์เพื่อรีเซ็ตรหัสผ่าน: $reset_link (หมดอายุใน 30 นาที)";
        
        $result = $mail->send();
        return $result;
    } catch (Exception $e) {
        error_log('Reset Email Error: ' . $e->getMessage());
        return false;
    }
}
?>