<?php
/**
 * AI Analytics Controller
 * ประเมินผลและวิเคราะห์การตอบของ AI
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

// Check Admin Auth
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$chk = $conn->prepare("SELECT role FROM users WHERE id = ?");
$chk->bind_param("i", $_SESSION['user_id']);
$chk->execute();
$user = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$user || $user['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'get_stats':
        getStats();
        break;
    case 'get_chat_users':
        getChatUsers();
        break;
    case 'get_chat_history':
        getChatHistory();
        break;
    case 'rate_response':
        rateResponse();
        break;
    case 'get_keywords':
        getKeywords();
        break;
    case 'get_rules_performance':
        getRulesPerformance();
        break;
    case 'get_feedback':
        getFeedback();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Get AI Statistics
 */
function getStats()
{
    global $conn;

    $stats = [
        'total_messages' => 0,
        'ai_responses' => 0,
        'rule_matches' => 0,
        'good_responses_used' => 0,
        'satisfaction' => 0
    ];

    // Total messages (last 30 days)
    $result = $conn->query("SELECT COUNT(*) as c FROM chat_messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['total_messages'] = intval($row['c']);
    }

    // AI Responses (messages from admin that are AI-generated)
    // Check if is_ai column exists
    $colCheck = $conn->query("SHOW COLUMNS FROM chat_messages LIKE 'is_ai'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $result = $conn->query("SELECT COUNT(*) as c FROM chat_messages WHERE is_ai = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['ai_responses'] = intval($row['c']);
        }
    } else {
        // Estimate AI responses (admin messages)
        $result = $conn->query("SELECT COUNT(*) as c FROM chat_messages WHERE sender = 'admin' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['ai_responses'] = intval($row['c']);
        }
    }

    // Rule Matches (check ai_response_logs or estimate)
    $tableCheck = $conn->query("SHOW TABLES LIKE 'ai_response_logs'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $result = $conn->query("SELECT COUNT(*) as c FROM ai_response_logs WHERE matched_rule_id IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['rule_matches'] = intval($row['c']);
        }
    }

    // Satisfaction Rate
    $ratingCheck = $conn->query("SHOW COLUMNS FROM chat_messages LIKE 'rating'");
    if ($ratingCheck && $ratingCheck->num_rows > 0) {
        $result = $conn->query("SELECT 
            SUM(CASE WHEN rating = 'good' THEN 1 ELSE 0 END) as good_count,
            SUM(CASE WHEN rating = 'bad' THEN 1 ELSE 0 END) as bad_count
            FROM chat_messages WHERE rating IS NOT NULL");
        if ($result && $row = $result->fetch_assoc()) {
            $total = intval($row['good_count']) + intval($row['bad_count']);
            if ($total > 0) {
                $stats['satisfaction'] = round((intval($row['good_count']) / $total) * 100);
            }
        }
    }

    // Good Responses Used (นับจาก log file)
    $logFile = __DIR__ . '/../../logs/ai_good_responses.log';
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        // นับจำนวนบรรทัดที่มี "Good Response Match"
        $stats['good_responses_used'] = substr_count($logContent, 'Good Response Match');
    }

    echo json_encode(['success' => true] + $stats);
}

/**
 * Get Users with Chat History
 */
function getChatUsers()
{
    global $conn;

    $users = [];
    $sql = "SELECT cm.user_id, u.username, 
            COUNT(cm.id) as message_count,
            MAX(DATE_FORMAT(cm.created_at, '%d/%m %H:%i')) as last_message
            FROM chat_messages cm
            LEFT JOIN users u ON cm.user_id = u.id
            WHERE cm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY cm.user_id
            ORDER BY MAX(cm.created_at) DESC
            LIMIT 50";

    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['username'] = $row['username'] ?? 'User #' . $row['user_id'];
            $users[] = $row;
        }
    }

    echo json_encode(['success' => true, 'users' => $users]);
}

/**
 * Get Chat History for a User
 */
function getChatHistory()
{
    global $conn;

    $userId = intval($_GET['user_id'] ?? 0);
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        return;
    }

    $messages = [];

    // Check if is_ai and rating columns exist
    $hasIsAi = $conn->query("SHOW COLUMNS FROM chat_messages LIKE 'is_ai'")->num_rows > 0;
    $hasRating = $conn->query("SHOW COLUMNS FROM chat_messages LIKE 'rating'")->num_rows > 0;

    $selectFields = "id, user_id, sender, message, DATE_FORMAT(created_at, '%d/%m %H:%i') as created_at";
    if ($hasIsAi) $selectFields .= ", is_ai";
    if ($hasRating) $selectFields .= ", rating";

    $stmt = $conn->prepare("SELECT $selectFields FROM chat_messages WHERE user_id = ? ORDER BY id ASC LIMIT 100");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        if (!$hasIsAi) $row['is_ai'] = ($row['sender'] === 'admin') ? 1 : 0;
        if (!$hasRating) $row['rating'] = null;
        $messages[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'messages' => $messages]);
}

/**
 * Rate AI Response
 */
function rateResponse()
{
    global $conn;

    $messageId = intval($_POST['message_id'] ?? 0);
    $rating = $_POST['rating'] ?? '';

    if ($messageId <= 0 || !in_array($rating, ['good', 'bad'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        return;
    }

    // Check if rating column exists, if not create it
    $colCheck = $conn->query("SHOW COLUMNS FROM chat_messages LIKE 'rating'");
    if ($colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE chat_messages ADD COLUMN rating ENUM('good', 'bad') NULL DEFAULT NULL");
    }

    $stmt = $conn->prepare("UPDATE chat_messages SET rating = ? WHERE id = ?");
    $stmt->bind_param("si", $rating, $messageId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    $stmt->close();
}

/**
 * Get Popular Keywords
 */
function getKeywords()
{
    global $conn;

    $keywords = [];
    $unmatched = [];
    $chartData = ['labels' => [], 'values' => []];

    // Common keywords dictionary
    $dictionary = [
        'vpn', 'ssh', 'เติมเงิน', 'โอน', 'ราคา', 'แพ็กเกจ', 'เช่า', 'ต่ออายุ',
        'connect', 'เชื่อมต่อ', 'ไม่ได้', 'หลุด', 'ช้า', 'ปัญหา', 'config',
        'สมัคร', 'login', 'รหัส', 'ลืม', 'แอดมิน', 'ติดต่อ', 'คืนเงิน'
    ];

    // Count keyword occurrences in user messages
    $keywordCounts = [];
    foreach ($dictionary as $kw) {
        $escaped = $conn->real_escape_string($kw);
        $result = $conn->query("SELECT COUNT(*) as c FROM chat_messages WHERE sender = 'user' AND message LIKE '%$escaped%' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        if ($result && $row = $result->fetch_assoc()) {
            if ($row['c'] > 0) {
                $keywordCounts[$kw] = intval($row['c']);
            }
        }
    }

    arsort($keywordCounts);
    foreach (array_slice($keywordCounts, 0, 15, true) as $kw => $count) {
        $keywords[] = ['keyword' => $kw, 'count' => $count];
    }

    // Chart data (top 8)
    $chartData['labels'] = array_slice(array_keys($keywordCounts), 0, 8);
    $chartData['values'] = array_slice(array_values($keywordCounts), 0, 8);

    // Find questions without rule matches (potential unmatched)
    // Get recent user questions and check if they might not have good responses
    $sql = "SELECT message, COUNT(*) as count 
            FROM chat_messages 
            WHERE sender = 'user' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND LENGTH(message) > 10
            GROUP BY message 
            HAVING count >= 2
            ORDER BY count DESC 
            LIMIT 10";

    $result = $conn->query($sql);
    if ($result) {
        // Load rules to check
        $rules = [];
        $ruleResult = $conn->query("SELECT keywords FROM ai_training_rules");
        if ($ruleResult) {
            while ($r = $ruleResult->fetch_assoc()) {
                $rules = array_merge($rules, array_map('trim', explode(',', strtolower($r['keywords']))));
            }
        }

        while ($row = $result->fetch_assoc()) {
            $msgLower = strtolower($row['message']);
            $matched = false;
            foreach ($rules as $ruleKw) {
                if (!empty($ruleKw) && strpos($msgLower, $ruleKw) !== false) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $unmatched[] = $row;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'keywords' => $keywords,
        'unmatched' => array_slice($unmatched, 0, 5),
        'chart_data' => $chartData
    ]);
}

/**
 * Get Rules Performance
 */
function getRulesPerformance()
{
    global $conn;

    $rules = [];

    // Check if use_count column exists
    $colCheck = $conn->query("SHOW COLUMNS FROM ai_training_rules LIKE 'use_count'");
    if ($colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE ai_training_rules ADD COLUMN use_count INT DEFAULT 0");
    }

    // Get rules with performance data
    $sql = "SELECT r.*, 
            COALESCE(r.use_count, 0) as use_count,
            0 as good_count,
            0 as bad_count
            FROM ai_training_rules r
            ORDER BY r.use_count DESC, r.priority DESC";

    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rules[] = $row;
        }
    }

    echo json_encode(['success' => true, 'rules' => $rules]);
}

/**
 * Get Feedback Data
 */
function getFeedback()
{
    global $conn;

    $feedback = [];
    $summary = ['good' => 0, 'bad' => 0];
    $suggestions = [];

    // Check if rating column exists
    $colCheck = $conn->query("SHOW COLUMNS FROM chat_messages LIKE 'rating'");
    if ($colCheck->num_rows > 0) {
        // Get recent feedback
        $sql = "SELECT cm.id, cm.message as answer, cm.rating, 
                DATE_FORMAT(cm.created_at, '%d/%m/%y %H:%i') as rated_at,
                u.username,
                (SELECT message FROM chat_messages WHERE user_id = cm.user_id AND id < cm.id AND sender = 'user' ORDER BY id DESC LIMIT 1) as question
                FROM chat_messages cm
                LEFT JOIN users u ON cm.user_id = u.id
                WHERE cm.rating IS NOT NULL
                ORDER BY cm.created_at DESC
                LIMIT 20";

        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['username'] = $row['username'] ?? 'User';
                $row['question'] = $row['question'] ?? '-';
                $feedback[] = $row;
            }
        }

        // Summary
        $result = $conn->query("SELECT rating, COUNT(*) as c FROM chat_messages WHERE rating IS NOT NULL GROUP BY rating");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if ($row['rating'] === 'good') $summary['good'] = intval($row['c']);
                if ($row['rating'] === 'bad') $summary['bad'] = intval($row['c']);
            }
        }
    }

    // Generate suggestions based on bad ratings
    if ($summary['bad'] > 0) {
        $suggestions[] = "มี {$summary['bad']} คำตอบที่ได้รับ Feedback ไม่ดี - ควรตรวจสอบและปรับปรุง Rule";
    }

    // Check for frequently asked questions without rules
    $result = $conn->query("SELECT COUNT(*) as c FROM ai_training_rules");
    $ruleCount = ($result && $row = $result->fetch_assoc()) ? intval($row['c']) : 0;
    if ($ruleCount < 10) {
        $suggestions[] = "มี Rule เพียง {$ruleCount} ข้อ - ควรเพิ่ม Rule สำหรับคำถามที่พบบ่อย";
    }

    $total = $summary['good'] + $summary['bad'];
    if ($total > 0 && ($summary['good'] / $total) < 0.7) {
        $suggestions[] = "อัตราความพอใจต่ำกว่า 70% - ควรปรับปรุงคำตอบ AI";
    }

    if (empty($suggestions)) {
        $suggestions[] = "AI ทำงานได้ดี! ยังไม่มีข้อแนะนำเพิ่มเติม";
    }

    echo json_encode([
        'success' => true,
        'feedback' => $feedback,
        'summary' => $summary,
        'suggestions' => $suggestions
    ]);
}
