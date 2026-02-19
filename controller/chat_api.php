<?php
// controller/chat_api.php
error_reporting(0);
ini_set('display_errors', 0);

// DEBUG: Top level
file_put_contents(__DIR__ . '/../logs/chat_entry.log', date('[Y-m-d H:i:s] ') . "Request received: " . print_r($_POST, true) . "\n", FILE_APPEND);

header('Content-Type: application/json');

session_start();

// เชื่อมต่อ Config (ใช้ไฟล์เดียว)
require_once __DIR__ . '/config.php';

// ✅ เรียกใช้งานไฟล์แจ้งเตือน
if (file_exists(__DIR__ . '/alert_modul/xdroid_notify.php')) {
    require_once __DIR__ . '/alert_modul/xdroid_notify.php';
}

// ✅ Fallback Response Function - ใช้เมื่อ AI ไม่ตอบ
function getFallbackResponse($msg) {
    $msg_lower = mb_strtolower($msg);
    
    // คำทักทาย
    if (preg_match('/(สวัสดี|หวัดดี|ดีครับ|ดีค่ะ|hello|hi)/u', $msg_lower)) {
        return "สวัสดีครับ! 😊 ยินดีให้บริการครับ มีอะไรให้ช่วยไหมครับ?";
    }
    
    // ถามเรื่อง VPN
    if (preg_match('/(vpn|v2ray|เช่า|แพ็ก|package)/u', $msg_lower)) {
        return "สนใจเช่า VPN/V2Ray ใช่ไหมครับ? 😊 สามารถดูแพ็กเกจและราคาได้ที่นี่เลยครับ 👇\n||ACTION:NAV:?p=rent_vpn||";
    }
    
    // ถามเรื่อง SSH
    if (preg_match('/(ssh|tunnel|netmod|http injector)/u', $msg_lower)) {
        return "สนใจเช่า SSH/Tunnel ใช่ไหมครับ? 🔐 ดูเซิร์ฟเวอร์ได้ที่นี่เลย 👇\n||ACTION:NAV:?p=rent_ssh||";
    }
    
    // เติมเงิน
    if (preg_match('/(เติมเงิน|topup|โอน|ชำระ|จ่าย)/u', $msg_lower)) {
        return "ต้องการเติมเงินใช่ไหมครับ? 💰 กดที่ลิงก์ด้านล่างเพื่อเติมเงินได้เลย 👇\n||ACTION:NAV:?p=topup||";
    }
    
    // ปัญหา/ติดต่อแอดมิน
    if (preg_match('/(ปัญหา|ช่วย|แอดมิน|admin|ติดต่อ|เสีย|ไม่ได้)/u', $msg_lower)) {
        return "รับทราบครับ! 🙏 ข้อความของคุณถูกส่งถึงแอดมินแล้ว รอสักครู่นะครับ แอดมินจะตอบกลับโดยเร็วที่สุดครับ";
    }
    
    // Default
    return "ขอบคุณสำหรับข้อความครับ! 😊 แอดมินจะตอบกลับโดยเร็วที่สุดนะครับ\n\nหากต้องการ:\n• เช่า VPN: ||ACTION:NAV:?p=rent_vpn||\n• เช่า SSH: ||ACTION:NAV:?p=rent_ssh||\n• เติมเงิน: ||ACTION:NAV:?p=topup||";
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- ACTION 1: SEND ---
if ($action === 'send') {
    // ✅ CSRF Check
    if (!verifyCsrfToken()) {
        echo json_encode(['success' => false, 'message' => 'Security Token Invalid (CSRF)']);
        exit;
    }

    $message = trim($_POST['message'] ?? '');
    $imagePath = null;
    $uploadError = null; // เก็บ error สำหรับแสดงให้ user

    // (ส่วน Upload รูปภาพ - Secure Version + Better Error Handling)
    if (isset($_FILES['image'])) {
        $fileError = $_FILES['image']['error'];
        
        // ✅ เช็ค PHP Upload Errors
        if ($fileError !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE   => 'ไฟล์ใหญ่เกินกำหนด (upload_max_filesize)',
                UPLOAD_ERR_FORM_SIZE  => 'ไฟล์ใหญ่เกินกำหนด (MAX_FILE_SIZE)',
                UPLOAD_ERR_PARTIAL    => 'อัปโหลดไม่สมบูรณ์ ลองใหม่อีกครั้ง',
                UPLOAD_ERR_NO_FILE    => 'ไม่มีไฟล์ถูกส่งมา',
                UPLOAD_ERR_NO_TMP_DIR => 'Server Error: ไม่พบ temp folder',
                UPLOAD_ERR_CANT_WRITE => 'Server Error: เขียนไฟล์ไม่ได้',
                UPLOAD_ERR_EXTENSION  => 'Server Error: Extension blocked',
            ];
            $uploadError = $errorMessages[$fileError] ?? "Upload Error Code: $fileError";
            error_log("Chat Upload Error: $uploadError");
        } else {
            // ไฟล์อัปโหลดสำเร็จ — ตรวจสอบ MIME Type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($_FILES['image']['tmp_name']);

            if (!in_array($mimeType, $allowedTypes)) {
                echo json_encode(['success' => false, 'message' => 'ประเภทไฟล์ไม่รองรับ รองรับเฉพาะ JPG, PNG, GIF, WEBP']);
                exit;
            }

            $ext = 'jpg';
            if ($mimeType == 'image/png') $ext = 'png';
            if ($mimeType == 'image/gif') $ext = 'gif';
            if ($mimeType == 'image/webp') $ext = 'webp';

            // ✅ Secure Filename
            $fileName = 'chat_' . md5(uniqid($_SESSION['user_id'] . '_', true)) . '.' . $ext;
            $dir = __DIR__ . '/../uploads/chat/';

            // สร้างโฟลเดอร์ถ้ายังไม่มี
            if (!is_dir($dir)) {
                if (!@mkdir($dir, 0755, true)) {
                    $uploadError = 'Server Error: สร้างโฟลเดอร์ uploads/chat ไม่ได้';
                    error_log("Mkdir Failed: $dir");
                }
            }
            
            // เช็คสิทธิ์เขียนโฟลเดอร์
            if (!$uploadError && !is_writable($dir)) {
                $uploadError = 'Server Error: โฟลเดอร์ uploads/chat ไม่มีสิทธิ์เขียน';
                error_log("Dir not writable: $dir");
            }

            // ย้ายไฟล์
            if (!$uploadError) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $dir . $fileName)) {
                    $imagePath = 'uploads/chat/' . $fileName;
                } else {
                    $uploadError = 'ย้ายไฟล์ไม่สำเร็จ กรุณาลองใหม่';
                    error_log("Move File Failed to: $dir$fileName");
                }
            }
        }
    }

    if (!empty($message) || !empty($imagePath)) {
        $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, sender, message, image_path, is_read, is_ai) VALUES (?, 'user', ?, ?, 0, 0)");
        $stmt->bind_param("iss", $user_id, $message, $imagePath);

        if ($stmt->execute()) {

            // ✅ [START] ระบบแจ้งเตือน (Notification System)
            // ดึงชื่อผู้ใช้ก่อน
            $u_name = 'User #' . $user_id;
            $u_stmt = $conn->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
            $u_stmt->bind_param("i", $user_id);
            if ($u_stmt->execute()) {
                $u_res = $u_stmt->get_result();
                if ($u_row = $u_res->fetch_assoc()) {
                    $u_name = $u_row['username'];
                }
            }
            $u_stmt->close();

            $noti_msg = !empty($message) ? $message : '[ส่งรูปภาพ]';

            // 1. แจ้งเตือน Telegram Chat
            if (file_exists(__DIR__ . '/alert_modul/telegram_chat_helper.php')) {
                require_once __DIR__ . '/alert_modul/telegram_chat_helper.php';
                sendTelegramChatNotify($user_id, $u_name, $noti_msg, $imagePath);
            }

            // 2. แจ้งเตือน XDroid (ตามคำขอ)
            if (function_exists('sendXdroidChat')) {
                 sendXdroidChat($u_name, $noti_msg);
            }
            // ✅ [END]

            // --- 🤖 SMART ACTIONS & AI LOGIC ---
            $msg_lower = strtolower($message);
            $action_triggered = false; // ตัวแปรเช็คว่ามีการตอบกลับอัตโนมัติหรือยัง

            // 1. Check Topup Issues
            $kw_topup = ['เติมเงิน', 'เงินไม่เข้า', 'topup', 'money'];
            foreach ($kw_topup as $k) {
                if (strpos($msg_lower, $k) !== false) {
                    $sql_topup = $conn->prepare("SELECT transaction_ref, amount FROM topup_transactions WHERE user_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
                    $sql_topup->bind_param("i", $user_id);
                    $sql_topup->execute();
                    $res = $sql_topup->get_result();
                    if ($res && $res->num_rows > 0) {
                        $bill = $res->fetch_assoc();
                        $auto_msg = "ระบบพบยอดเงิน " . number_format($bill['amount'], 2) . " บาท ที่ยังทำรายการไม่เสร็จ\nกดปุ่มเพื่อชำระเงินต่อได้เลยครับ 👇\n||ACTION:PAY:{$bill['transaction_ref']}||";
                        $stmt_auto = $conn->prepare("INSERT INTO chat_messages (user_id, sender, message, is_read, is_ai) VALUES (?, 'admin', ?, 0, 1)");
                        $stmt_auto->bind_param("is", $user_id, $auto_msg);
                        $stmt_auto->execute();
                        $stmt_auto->close();
                        $action_triggered = true;
                    }
                    $sql_topup->close();
                }
            }

            // 2. Check VPN Issues
            if (!$action_triggered) {
                $kw_vpn = ['vpn', 'หมดอายุ', 'expired', 'connect', 'เข้าไม่ได้'];
                foreach ($kw_vpn as $k) {
                    if (strpos($msg_lower, $k) !== false) {
                        $sql_vpn = $conn->prepare("SELECT rental_name FROM user_rentals WHERE user_id = ? AND status != 'deleted' AND expire_date < DATE_ADD(NOW(), INTERVAL 3 DAY) LIMIT 1");
                        $sql_vpn->bind_param("i", $user_id);
                        $sql_vpn->execute();
                        $res = $sql_vpn->get_result();
                        if ($res && $res->num_rows > 0) {
                            $vpn = $res->fetch_assoc();
                            $auto_msg = "แพ็กเกจ '{$vpn['rental_name']}' ใกล้หมดอายุหรือหมดอายุแล้วครับ\nจัดการได้ที่เมนูนี้ 👇\n||ACTION:VPN:MY_VPN||";
                            $stmt_vpn_msg = $conn->prepare("INSERT INTO chat_messages (user_id, sender, message, is_read, is_ai) VALUES (?, 'admin', ?, 0, 1)");
                            $stmt_vpn_msg->bind_param("is", $user_id, $auto_msg);
                            $stmt_vpn_msg->execute();
                            $stmt_vpn_msg->close();
                            $action_triggered = true;
                        }
                        $sql_vpn->close();
                    }
                }
            }

            // 3. Navigation Shortcuts (Fast Response)
            if (!$action_triggered) {
                $nav_map = [
                    'buy' => ['k' => ['ซื้อ', 'เช่า', 'แพ็กเกจ', 'ราคา', 'buy', 'rent'], 'u' => '?p=rent_vpn', 't' => 'ดูแพ็กเกจ/เช่าสินค้า'],
                    'hist' => ['k' => ['ประวัติ', 'history', 'log'], 'u' => '?p=topup_history', 't' => 'ประวัติการทำรายการ'],
                    'set' => ['k' => ['ตั้งค่า', 'รหัสผ่าน', 'password', 'setting'], 'u' => '?p=userdetail', 't' => 'ตั้งค่าบัญชี']
                ];
                foreach ($nav_map as $n) {
                    foreach ($n['k'] as $word) {
                        if (strpos($msg_lower, $word) !== false) {
                            $auto_msg = "ต้องการไปที่เมนู \"{$n['t']}\" ใช่ไหมครับ? 👇\n||ACTION:NAV:{$n['u']}||";
                            $stmt_nav = $conn->prepare("INSERT INTO chat_messages (user_id, sender, message, is_read, is_ai) VALUES (?, 'admin', ?, 0, 1)");
                            $stmt_nav->bind_param("is", $user_id, $auto_msg);
                            $stmt_nav->execute();
                            $stmt_nav->close();
                            $action_triggered = true;
                            break 2;
                        }
                    }
                }
            }

            // 4. AI Call (Typhoon) - ถ้ายังไม่มีการตอบรับจาก Hardcode
            if (!$action_triggered && !empty($message)) {
                // ✅ เช็ค ai_active แบบ robust - ถ้าไม่มีตาราง/row ให้ default เป็น ON
                $is_ai_on = true; // Default: ON
                try {
                    $ai_chk = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'ai_active' LIMIT 1");
                    if ($ai_chk && $row = $ai_chk->fetch_assoc()) {
                        $is_ai_on = ($row['setting_value'] == '1');
                    }
                } catch (Exception $e) {
                    // ถ้า query error ก็ให้ AI ทำงานต่อ
                    $is_ai_on = true;
                }

                // TRACE LOG
                $traceLog = date('[Y-m-d H:i:s] ') . "User#$user_id | Msg: '$message' | AI_Active: " . ($is_ai_on ? 'ON' : 'OFF');

                if ($is_ai_on) {
                    $ai_reply = null;
                    
                    if (file_exists(__DIR__ . '/ai_helper.php')) {
                        require_once __DIR__ . '/ai_helper.php';
                        $ai_reply = generateAIResponse($user_id, $message, $conn);
                        $traceLog .= " | API Called";
                    } else {
                        $traceLog .= " | Error: ai_helper.php not found";
                    }

                    // ✅ FALLBACK: ถ้า AI ไม่ตอบ ให้ส่งข้อความสำรอง
                    if (empty($ai_reply)) {
                        $traceLog .= " | Reply: NULL -> Using Fallback";
                        $ai_reply = getFallbackResponse($message);
                    } else {
                        $traceLog .= " | Reply: OK (" . mb_strlen($ai_reply) . " chars)";
                    }

                    // บันทึกคำตอบลง database
                    if (!empty($ai_reply)) {
                        $stmt_ai = $conn->prepare("INSERT INTO chat_messages (user_id, sender, message, is_read, is_ai) VALUES (?, 'admin', ?, 0, 1)");
                        $stmt_ai->bind_param("is", $user_id, $ai_reply);
                        $stmt_ai->execute();
                        $stmt_ai->close();
                    }
                } else {
                    $traceLog .= " | Skipped (AI OFF)";
                }
                
                // เขียน Log
                @file_put_contents(__DIR__ . '/../logs/chat_debug.log', $traceLog . "\n", FILE_APPEND);
            }

            echo json_encode(['success' => true]);
        } else {
            // DEBUG: Return specific SQL error
            echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $stmt->error]);
        }
    } else {
        // ERROR: Message and Image both empty — แสดง error ที่ชัดเจน
        if ($uploadError) {
            echo json_encode(['success' => false, 'message' => 'ส่งไม่สำเร็จ: ' . $uploadError]);
        } else {
            echo json_encode(['success' => false, 'message' => 'กรุณาพิมพ์ข้อความหรือเลือกรูปภาพ']);
        }
    }
}
// --- 🤖 Smart Suggestions (คำถามยอดฮิต) ---
elseif ($action === 'get_smart_suggestions') {
    $suggestions = [];
    
    // เช็คว่าใช้ Custom Suggestions หรือไม่
    $use_custom = '1'; // default ON
    $setting_result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'use_custom_suggestions' LIMIT 1");
    if ($setting_result && $row = $setting_result->fetch_assoc()) {
        $use_custom = $row['setting_value'];
    }
    
    // ถ้าใช้ Custom Suggestions - ดึงจาก ai_suggestions
    if ($use_custom === '1') {
        $result = $conn->query("SELECT text FROM ai_suggestions WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 6");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $suggestions[] = $row['text'];
            }
        }
    }
    
    // ถ้าไม่มี Custom หรือปิด Custom - ดึงจากคำถามยอดฮิต
    if (empty($suggestions)) {
        $sql = "SELECT message, COUNT(*) as c 
                    FROM chat_messages 
                    WHERE sender='user' 
                    AND LENGTH(message) > 3 
                    AND LENGTH(message) < 50 
                    AND message NOT LIKE '%||%'
                    GROUP BY message 
                    ORDER BY c DESC 
                    LIMIT 6";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $suggestions[] = $row['message'];
            }
        }
    }
    
    // ถ้ายังไม่มีข้อมูล ให้ใส่ Default
    if (empty($suggestions)) {
        $suggestions = ['สวัสดีครับ', 'เติมเงินยังไง', 'ขอเลขบัญชี', 'VPN หลุด', 'เน็ตช้า', 'ติดต่อแอดมิน'];
    }
    
    echo json_encode(['success' => true, 'suggestions' => $suggestions]);
}

// --- ACTION 2: FETCH ---
elseif ($action === 'fetch') {
    $conn->query("UPDATE chat_messages SET is_read = 1 WHERE user_id = $user_id AND sender = 'admin' AND is_read = 0");
    $stmt = $conn->prepare("SELECT sender, message, image_path, is_read, is_ai, created_at FROM chat_messages WHERE user_id = ? ORDER BY created_at ASC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    echo json_encode(['success' => true, 'messages' => $messages]);
}

// --- ACTION 3: CHECK NOTIFY ---
elseif ($action === 'check_notify') {
    $stmt = $conn->prepare("SELECT COUNT(*) as unread FROM chat_messages WHERE user_id = ? AND sender = 'admin' AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    echo json_encode(['success' => true, 'unread' => $stmt->get_result()->fetch_assoc()['unread']]);
}
?>