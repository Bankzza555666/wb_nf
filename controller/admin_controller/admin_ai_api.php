<?php
// controller/admin_controller/admin_ai_api.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

// Check Admin Auth
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$chk = $conn->query("SELECT role FROM users WHERE id = {$_SESSION['user_id']}");
$user = $chk->fetch_assoc();
if (!$user || $user['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

// 1. GET ALL RULES
if ($action === 'get_rules') {
    $sql = "SELECT * FROM ai_training_rules ORDER BY priority DESC, created_at DESC";
    $result = $conn->query($sql);
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'DB Error: ' . $conn->error]);
        exit;
    }
    $rules = [];
    while ($row = $result->fetch_assoc()) {
        $rules[] = $row;
    }
    echo json_encode(['success' => true, 'rules' => $rules]);
}

// 2. ADD RULE
elseif ($action === 'add_rule') {
    $keywords = trim($_POST['keywords'] ?? '');
    $response = trim($_POST['response'] ?? '');
    $match_type = $_POST['match_type'] ?? 'fuzzy';
    $priority = intval($_POST['priority'] ?? 0);

    if (empty($keywords) || empty($response)) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบ']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO ai_training_rules (keywords, response, match_type, priority) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $keywords, $response, $match_type, $priority);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
}

// 3. EDIT RULE
elseif ($action === 'update_rule') {
    $id = intval($_POST['id'] ?? 0);
    $keywords = trim($_POST['keywords'] ?? '');
    $response = trim($_POST['response'] ?? '');
    $match_type = $_POST['match_type'] ?? 'fuzzy';
    $priority = intval($_POST['priority'] ?? 0);

    if ($id <= 0 || empty($keywords) || empty($response)) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE ai_training_rules SET keywords = ?, response = ?, match_type = ?, priority = ? WHERE id = ?");
    $stmt->bind_param("sssii", $keywords, $response, $match_type, $priority, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
}

// 4. DELETE RULE
elseif ($action === 'delete_rule') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        $conn->query("DELETE FROM ai_training_rules WHERE id = $id");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
}

// ==================== AI SUGGESTIONS ====================

// 5. GET SUGGESTIONS
elseif ($action === 'get_suggestions') {
    $result = $conn->query("SELECT * FROM ai_suggestions WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
    $suggestions = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $suggestions[] = $row;
        }
    }
    echo json_encode(['success' => true, 'suggestions' => $suggestions]);
}

// 6. ADD SUGGESTION
elseif ($action === 'add_suggestion') {
    $text = trim($_POST['text'] ?? '');
    if (empty($text)) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อความ']);
        exit;
    }
    if (mb_strlen($text) > 50) {
        echo json_encode(['success' => false, 'message' => 'ข้อความยาวเกิน 50 ตัวอักษร']);
        exit;
    }
    
    $stmt = $conn->prepare("INSERT INTO ai_suggestions (text) VALUES (?)");
    $stmt->bind_param("s", $text);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
}

// 7. DELETE SUGGESTION
elseif ($action === 'delete_suggestion') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        $conn->query("DELETE FROM ai_suggestions WHERE id = $id");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
}

// 8. GET SUGGESTION SETTING
elseif ($action === 'get_suggestion_setting') {
    $result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'use_custom_suggestions' LIMIT 1");
    $use_custom = '1'; // default ON
    if ($result && $row = $result->fetch_assoc()) {
        $use_custom = $row['setting_value'];
    }
    echo json_encode(['success' => true, 'use_custom' => $use_custom]);
}

// 9. SAVE SUGGESTION SETTING
elseif ($action === 'save_suggestion_setting') {
    $use_custom = $_POST['use_custom'] ?? '1';
    
    // ลบค่าเก่า
    $conn->query("DELETE FROM system_settings WHERE setting_key = 'use_custom_suggestions'");
    // Insert ใหม่
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('use_custom_suggestions', ?)");
    $stmt->bind_param("s", $use_custom);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
}

else {
    echo json_encode(['success' => false, 'message' => 'Invalid Action']);
}
?>