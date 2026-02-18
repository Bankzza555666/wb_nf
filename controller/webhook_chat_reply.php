<?php
// controller/webhook_chat_reply.php
// ใช้สำหรับรับ Webhook จาก Telegram เมื่อแอดมินตอบกลับข้อความ

error_reporting(0);
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

// รับข้อมูล JSON จาก Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    echo json_encode(['status' => 'error', 'message' => 'No data']);
    exit;
}

// DEBUG: Log incoming webhook (Current Directory)
file_put_contents(__DIR__ . '/webhook.log', date('[Y-m-d H:i:s] ') . print_r($update, true) . "\n\n", FILE_APPEND);

// ตรวจสอบว่าเป็นข้อความ (Message)
if (isset($update['message'])) {
    $msg = $update['message'];

    // เช็คว่ามีข้อความตอบกลับ หรือ รูปภาพ
    $is_reply = isset($msg['reply_to_message']);
    $has_text = isset($msg['text']);
    $has_photo = isset($msg['photo']);
    $has_doc = isset($msg['document']);

    if ($is_reply && ($has_text || $has_photo || $has_doc)) {

        $reply_text = isset($msg['text']) ? trim($msg['text']) : (isset($msg['caption']) ? trim($msg['caption']) : '');
        $original_text = $msg['reply_to_message']['text'] ?? $msg['reply_to_message']['caption'] ?? '';

        // ค้นหา Pattern: #User123 ในข้อความต้นทาง
        if (preg_match('/#User(\d+)/', $original_text, $matches)) {
            $user_id = intval($matches[1]);

            // ใช้ $conn จาก config.php ที่ require ไว้แล้วด้านบน (ไม่สร้างซ้ำ)

            // 1. Mark User Messages as READ (ให้แอดมินเห็นว่าอ่านแล้วในเว็บ)
            $conn->query("UPDATE chat_messages SET is_read = 1 WHERE user_id = $user_id AND sender = 'user' AND is_read = 0");

            // 2. Handle Image/Document Download
            $imagePath = null;
            if ($has_photo) {
                // Get the largest photo
                $photo = end($msg['photo']);
                $file_id = $photo['file_id'];
                $imagePath = downloadTelegramFile($file_id, $user_id);
            } elseif ($has_doc) {
                // Check if mime type is image
                if (strpos($msg['document']['mime_type'], 'image') !== false) {
                    $file_id = $msg['document']['file_id'];
                    $imagePath = downloadTelegramFile($file_id, $user_id);
                }
            }

            // บันทึกข้อความลงตาราง chat_messages
            $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, sender, message, image_path, is_read, is_ai) VALUES (?, 'admin', ?, ?, 0, 0)");
            $stmt->bind_param("iss", $user_id, $reply_text, $imagePath);

            if ($stmt->execute()) {
                sendReplyBackToAdmin($msg['chat']['id'], "ส่งเรียบร้อย" . ($imagePath ? " (มีรูป)" : ""));
                file_put_contents(__DIR__ . '/webhook_success.log', "User: $user_id | Msg: $reply_text | Img: $imagePath\n", FILE_APPEND);
            } else {
                $err = "SQL Error: " . $conn->error;
                sendReplyBackToAdmin($msg['chat']['id'], $err);
            }
            $conn->close();
        }
    }
}

// ฟังก์ชันดาวน์โหลดไฟล์จาก Telegram
function downloadTelegramFile($file_id, $user_id)
{
    if (!defined('TELEGRAM_CHAT_BOT_TOKEN'))
        return null;
    $token = TELEGRAM_CHAT_BOT_TOKEN;

    // 1. Get File Path
    $url = "https://api.telegram.org/bot{$token}/getFile?file_id={$file_id}";
    $json = file_get_contents($url);
    $data = json_decode($json, true);

    if (!$data || !$data['ok'])
        return null;

    $file_path_tg = $data['result']['file_path'];
    $file_url = "https://api.telegram.org/file/bot{$token}/{$file_path_tg}";

    // 2. Prepare Local Path
    $ext = strtolower(pathinfo($file_path_tg, PATHINFO_EXTENSION));
    if (!$ext)
        $ext = 'jpg'; // Default fallback

    // ตั้งชื่อไฟล์ป้องกันซ้ำ
    $fileName = 'tg_' . time() . '_' . $user_id . '_' . rand(1000, 9999) . '.' . $ext;
    $uploadDir = __DIR__ . '/../uploads/chat/';

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true))
            return null;
    }

    // 3. Download & Save
    $localPath = $uploadDir . $fileName;
    if (file_put_contents($localPath, file_get_contents($file_url))) {
        return 'uploads/chat/' . $fileName;
    }

    return null;
}

function sendReplyBackToAdmin($chat_id, $text)
{
    if (!defined('TELEGRAM_CHAT_BOT_TOKEN'))
        return;
    $url = "https://api.telegram.org/bot" . TELEGRAM_CHAT_BOT_TOKEN . "/sendMessage";
    $data = ['chat_id' => $chat_id, 'text' => $text];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}
?>
