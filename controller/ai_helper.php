<?php
// controller/ai_helper.php

if (!defined('TYPHOON_API_KEY')) {
    if (file_exists(__DIR__ . '/config.php'))
        require_once __DIR__ . '/config.php';
    else if (file_exists(__DIR__ . '/../config.php'))
        require_once __DIR__ . '/../config.php';
}

// ✅ ฟังก์ชัน 1: ลบข้อมูลส่วนตัวลูกค้าคนอื่น (Privacy Guard)
function sanitizeData($text)
{
    // ลบ UUID (รูปแบบ 8-4-4-4-12 ตัวอักษร)
    $text = preg_replace('/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i', '[UUID_HIDDEN]', $text);
    // ลบ Email
    $text = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[EMAIL_HIDDEN]', $text);
    // ลบเบอร์โทร (รองรับขีดคั่น)
    $text = preg_replace('/(\d{3}[-\s]?\d{3}[-\s]?\d{4})/', '[PHONE_HIDDEN]', $text);
    // ลบบัตรประชาชน (13 หลัก)
    $text = preg_replace('/\d{13}/', '[ID_CARD_HIDDEN]', $text);
    return $text;
}

// ✅ ฟังก์ชัน 2.1: แยกคำไทยแบบบ้านๆ (Thai Keyword Extractor)
function extractThaiKeywords($text)
{
    // คำสำคัญที่มักพบในปัญหาลูกค้า (Support Keywords)
    $dictionary = [
        'เติมเงิน',
        'โอนเงิน',
        'สแกน',
        'ยอดไม่เข้า',
        'ซอง',
        'Wallet',
        'วอลเล็ท',
        'VPN',
        'vpn',
        'connect',
        'ต่อไม่ติด',
        'หลุด',
        'ช้า',
        'แลก',
        'กระตุก',
        'ราคา',
        'รายเดือน',
        'เช่า',
        'ซื้อ',
        'แพ็กเกจ',
        'โปร',
        'เข้าไม่ได้',
        'ลืมรหัส',
        'สมัคร',
        'login',
        'user',
        'pass',
        'คืนเงิน',
        'แจ้งปัญหา',
        'ติดต่อ',
        'แอดมิน',
        'เสีย'
    ];

    $matches = [];
    foreach ($dictionary as $word) {
        if (strpos($text, $word) !== false) {
            $matches[] = $word;
        }
    }
    return $matches;
}

// ✅ ฟังก์ชัน 2: ค้นหาวิธีแก้ปัญหาจากประวัติแชททั้งหมด (Global Knowledge)
function findGlobalSolutions($conn, $userMessage)
{
    // ใช้วิธีจับคำไทยจาก Dictionary
    $keywords = extractThaiKeywords($userMessage);

    // ถ้าไม่เจอคำใน Dict ให้ลองตัดช่องว่างเผื่อ User พิมพ์เว้นวรรค
    if (empty($keywords)) {
        $keywords = array_filter(explode(' ', str_replace(['ครับ', 'ค่ะ', 'อยาก', 'ขอ', 'ทำไม', 'ช่วย', 'ด้วย'], '', $userMessage)), function ($w) {
            return mb_strlen($w) > 3;
        });
    }

    if (empty($keywords))
        return "No specific keywords found in user message to search history.";

    $solutions = [];
    $limit = 3;

    // สร้าง Query หาข้อความที่มีคีย์เวิร์ดอย่างน้อย 1 คำ
    $conds = [];
    foreach ($keywords as $word) {
        $word = $conn->real_escape_string($word);
        $conds[] = "u.message LIKE '%$word%'";
    }
    $sqlCond = implode(' OR ', $conds);

    // Query: หาคู่ประโยคคำถาม-คำตอบ ที่มีคีย์เวิร์ด
    $sql = "SELECT a.message as admin_reply, u.message as user_ask 
            FROM chat_messages u 
            JOIN chat_messages a ON a.user_id = u.user_id 
            WHERE u.sender = 'user' 
            AND a.sender = 'admin' 
            AND a.id > u.id 
            AND ($sqlCond)
            AND LENGTH(a.message) > 10
            ORDER BY a.created_at DESC LIMIT $limit";

    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $cleanAsk = sanitizeData(strip_tags($row['user_ask']));
            $cleanReply = sanitizeData(strip_tags($row['admin_reply']));
            $solutions[] = "User asked: \"$cleanAsk\" -> Admin solved: \"$cleanReply\"";
        }
    }

    return implode("\n", array_unique($solutions));
}

// ✅ ฟังก์ชัน 3: ตรวจสอบกฎที่แอดมินสอน (Admin Training Rules)
function checkAdminRules($conn, $userMessage)
{
    if (!$conn)
        return null;
    $sql = "SELECT * FROM ai_training_rules ORDER BY priority DESC, created_at DESC";
    $result = $conn->query($sql);

    if (!$result)
        return null;

    while ($rule = $result->fetch_assoc()) {
        $keywords = array_map('trim', explode(',', $rule['keywords']));
        $match = false;

        foreach ($keywords as $kw) {
            if ($rule['match_type'] === 'exact') {
                if (trim($userMessage) === $kw) {
                    $match = true;
                    break;
                }
            } else {
                if (strpos($userMessage, $kw) !== false) {
                    $match = true;
                    break;
                }
            }
        }

        if ($match)
            return $rule['response'];
    }
    return null;
}

// ✅ ฟังก์ชัน 4: ค้นหาคำตอบที่ได้คะแนนดีจากคำถามคล้ายๆ กัน (Good Responses Auto-Reply)
function findGoodResponses($conn, $userMessage)
{
    if (!$conn)
        return null;

    // เช็คว่ามี column rating หรือไม่
    $colCheck = $conn->query("SHOW COLUMNS FROM chat_messages LIKE 'rating'");
    if (!$colCheck || $colCheck->num_rows === 0) {
        return null; // ยังไม่มี rating column
    }

    // แยกคำสำคัญจากคำถาม
    $keywords = extractThaiKeywords($userMessage);
    
    // ถ้าไม่เจอคำใน Dict ให้ลองตัดช่องว่าง
    if (empty($keywords)) {
        $keywords = array_filter(explode(' ', str_replace(['ครับ', 'ค่ะ', 'อยาก', 'ขอ', 'ทำไม', 'ช่วย', 'ด้วย'], '', $userMessage)), function ($w) {
            return mb_strlen($w) > 3;
        });
    }

    if (empty($keywords))
        return null;

    // สร้าง Query หาคำถามที่คล้ายกัน และมีคำตอบที่ได้คะแนน "good"
    $conds = [];
    foreach ($keywords as $word) {
        $word = $conn->real_escape_string($word);
        $conds[] = "u.message LIKE '%$word%'";
    }
    $sqlCond = implode(' OR ', $conds);

    // Query: หาคู่คำถาม-คำตอบ ที่:
    // 1. คำถามมี keyword ตรงกับคำถามปัจจุบัน
    // 2. คำตอบได้ rating = 'good'
    // 3. คำตอบเป็น AI (is_ai = 1) หรือ admin
    // 4. เรียงตามวันที่ล่าสุด
    $sql = "SELECT a.message as good_answer, u.message as original_question,
                   a.created_at, a.id as answer_id
            FROM chat_messages u 
            JOIN chat_messages a ON a.user_id = u.user_id 
            WHERE u.sender = 'user' 
            AND a.sender = 'admin' 
            AND a.id > u.id 
            AND a.rating = 'good'
            AND ($sqlCond)
            AND LENGTH(a.message) > 10
            AND LENGTH(u.message) > 5
            ORDER BY a.created_at DESC 
            LIMIT 1";

    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // Log ว่าใช้ Good Response
        $logMsg = date('[Y-m-d H:i:s] ') . "Good Response Match: \"{$row['original_question']}\" -> Using answer ID {$row['answer_id']}\n";
        file_put_contents(__DIR__ . '/../logs/ai_good_responses.log', $logMsg, FILE_APPEND);
        
        return $row['good_answer'];
    }

    return null;
}

function generateAIResponse($userId, $userMessage, $conn)
{
    $uid = (int) $userId; // ป้องกัน SQL Injection
    $apiKey = TYPHOON_API_KEY;
    
    // 1. ✅ CHECK ADMIN RULES (Training) - ตรวจสอบกฎที่แอดมินสอนไว้ก่อน
    $ruleReply = checkAdminRules($conn, $userMessage);
    if ($ruleReply) {
        return $ruleReply;
    }

    // 2. ✅ CHECK GOOD RESPONSES - ใช้คำตอบที่ได้คะแนนดีจากคำถามคล้ายๆ กัน
    $goodReply = findGoodResponses($conn, $userMessage);
    if ($goodReply) {
        return $goodReply;
    }

    // 1. ข้อมูล User ปัจจุบัน
    $userQ = $conn->query("SELECT username, credit FROM users WHERE id = $uid");
    $userData = $userQ->fetch_assoc();
    $userName = $userData['username'] ?? 'User';
    $userCredit = number_format($userData['credit'] ?? 0, 2);

    // 2. เช็คบิลค้าง (เหมือนเดิม)
    $pendingBillTxt = "No pending bills.";
    $pendingRef = "";
    $billQ = $conn->query("SELECT transaction_ref, amount FROM topup_transactions WHERE user_id = $uid AND status = 'pending' ORDER BY id DESC LIMIT 1");
    if ($billQ && $billQ->num_rows > 0) {
        $bill = $billQ->fetch_assoc();
        $amount = number_format($bill['amount'], 2);
        $pendingRef = $bill['transaction_ref'];
        $pendingBillTxt = "Pending bill: $amount THB (Ref: $pendingRef)";
    }

    $apiUrl = 'https://api.opentyphoon.ai/v1/chat/completions';

    // 3. ✅ ดึงความรู้จากแชททั้งหมด (The Hive Mind)
    $globalKnowledge = findGlobalSolutions($conn, $userMessage);

    // 4. ประวัติแชทส่วนตัว (Context)
    $histQuery = "SELECT sender, message FROM chat_messages WHERE user_id = $uid ORDER BY id DESC LIMIT 8";
    $histResult = $conn->query($histQuery);
    $history = [];
    while ($row = $histResult->fetch_assoc()) {
        $role = ($row['sender'] == 'admin') ? 'assistant' : 'user';
        $cleanMsg = preg_replace('/\|\|ACTION:.*?\|\|/', '', strip_tags($row['message']));
        array_unshift($history, ['role' => $role, 'content' => $cleanMsg]);
    }

    // 5. สร้าง Prompt (อัปเกรดใหม่)
    $systemPrompt = <<<EOT
You are 'NF~SHOP AI', an intelligent admin assistant.
    
    [Current User Profile]
    - Name: $userName
    - Credit: $userCredit THB
    - Status: $pendingBillTxt

    [Knowledge from Past Admin Solutions (Reference Only)]
    Use these strictly to learn HOW to solve the problem, but do NOT copy confidential info:
    $globalKnowledge

    [Site Service Knowledge]
    1. **SSH / Netmod / NPV Tunnel**:
       - High-speed tunneling service for anonymity, bypassing restrictions, and securing connections.
       - Supports apps like Netmod, HTTP Injector, KPN Tunnel.
       - **Action**: Direct user to `?p=rent_ssh` to browse servers.

    2. **V2RAY / VPN**:
       - Premium VPN service optimized for streaming (Netflix, Disney+, etc.) and gaming.
       - Uses V2Ray protocol for better stability and speed.
       - **Action**: Direct user to `?p=rent_vpn` to view packages.

    [Site Navigation Map - USE THESE LINKS]
    - **Rent SSH/Netmod**: `?p=rent_ssh` (For SSH, Tunel, Netmod users)
    - **Rent VPN/V2Ray**: `?p=rent_vpn` (For standard VPN users)
    - **Streaming Packages**: `?p=products_category&id=3` (Youtube, Netflix access)
    - **Manage My SSH**: `?p=my_ssh` (Check time, get config for SSH)
    - **Manage My VPN**: `?p=my_vpn` (Check time, get config for V2Ray)
    - **Topup / Add Credit**: `?p=topup` (PromptPay/Bank Transfer)
    - **Topup History**: `?p=topup_history`
    - **Contact Admin**: `?p=contact`

    [Instructions]
    1. **Role**: You are a helpful technical support AI for NF~SHOP. Answer in **Natural Thai (ภาษาไทยให้อ่านง่าย)**.
    2. **Navigation**: When a user asks about buying, checking status, or specific services, **ALWAYS** provide the specific link using `||ACTION:NAV:url||`.
       - Example: "อยากเช่า SSH" -> "ได้เลยครับ คุณสามารถเลือก Server SSH คุณภาพสูงได้ที่นี่: ||ACTION:NAV:?p=rent_ssh||"
    3. **Billing**: If `Pending Bill: ...` is present above, explain it and offer: "คุณมียอดค้างชำระ... ||ACTION:PAY:$pendingRef||".
    4. **Technical Help**: Use `[Knowledge from Past Admin Solutions]` to suggest fixes if available.
    5. **Tone**: Professional, friendly, and concise (2-4 sentences is best).
EOT;

    $messages = array_merge(
        [['role' => 'system', 'content' => $systemPrompt]],
        $history,
        [['role' => 'user', 'content' => $userMessage]]
    );

    // 6. ส่ง API
    $data = [
        'model' => 'typhoon-v2.1-12b-instruct',
        'messages' => $messages,
        'temperature' => 0.4,
        'max_tokens' => 1200
    ];

    // ✅ เช็คว่า curl extension มีหรือไม่
    if (!function_exists('curl_init')) {
        @file_put_contents(__DIR__ . '/../logs/ai_debug.log', date('[Y-m-d H:i:s] ') . "ERROR: curl extension not installed\n", FILE_APPEND);
        return null;
    }

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);

    // ✅ Timeout settings (ป้องกัน hang)
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 วินาทีสำหรับ connect
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);        // 30 วินาทีสำหรับ response

    // DEBUG: Disable SSL Verify to fix XAMPP/hosting issues
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // DEBUG LOGGING
    $logMsg = date('[Y-m-d H:i:s] ') . "User: $userMessage | HTTP: $httpCode | ";
    if ($err) {
        $logMsg .= "CURL Error: $err\n";
    } else {
        $logMsg .= "Response: " . substr($response, 0, 200) . "\n";
    }
    @file_put_contents(__DIR__ . '/../logs/ai_debug.log', $logMsg, FILE_APPEND);

    if ($err) {
        return null;
    }

    // ✅ เช็ค HTTP Status Code
    if ($httpCode !== 200) {
        @file_put_contents(__DIR__ . '/../logs/ai_debug.log', date('[Y-m-d H:i:s] ') . "API Error HTTP $httpCode: $response\n", FILE_APPEND);
        return null;
    }

    $json = json_decode($response, true);
    
    // ✅ เช็คว่า API ตอบกลับถูกต้อง
    if (isset($json['error'])) {
        @file_put_contents(__DIR__ . '/../logs/ai_debug.log', date('[Y-m-d H:i:s] ') . "API Error: " . json_encode($json['error']) . "\n", FILE_APPEND);
        return null;
    }

    return $json['choices'][0]['message']['content'] ?? null;
}
?>