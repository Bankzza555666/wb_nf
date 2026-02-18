<?php
// controller/admin_controller/admin_chat_api.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

// เชื่อมต่อ Config (ถอยกลับไป 2 steps เพื่อหา config.php ที่ root/controller/)
require_once __DIR__ . '/../config.php';

// ตรวจสอบสิทธิ์ Admin
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

// =========================================================
// 1. GET USERS LIST (รายชื่อลูกค้า)
// =========================================================
if ($action === 'get_users') {
    $sql = "SELECT u.id, u.username, MAX(c.created_at) as last_msg_time,
            (SELECT message FROM chat_messages WHERE user_id = u.id ORDER BY id DESC LIMIT 1) as last_msg,
            (SELECT sender FROM chat_messages WHERE user_id = u.id ORDER BY id DESC LIMIT 1) as last_sender,
            (SELECT image_path FROM chat_messages WHERE user_id = u.id ORDER BY id DESC LIMIT 1) as last_img,
            (SELECT COUNT(*) FROM chat_messages WHERE user_id = u.id AND sender = 'user' AND is_read = 0) as unread
            FROM users u JOIN chat_messages c ON u.id = c.user_id
            GROUP BY u.id ORDER BY last_msg_time DESC";
    $result = $conn->query($sql);

    if (!$result) {
        // Return SQL error as JSON
        echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $conn->error]);
        exit;
    }

    $users = [];
    while ($row = $result->fetch_assoc()) {
        if (empty($row['last_msg']) && !empty($row['last_img']))
            $row['last_msg'] = '📎 [รูปภาพ]';
        $users[] = $row;
    }
    echo json_encode(['success' => true, 'users' => $users]);
}

// ... (Action 2-7 คงเดิม) ...
elseif ($action === 'fetch_chat') {
    $target_user_id = intval($_POST['user_id'] ?? 0);
    if ($target_user_id > 0) {
        $conn->query("UPDATE chat_messages SET is_read = 1 WHERE user_id = $target_user_id AND sender = 'user' AND is_read = 0");
        $stmt = $conn->prepare("SELECT sender, message, image_path, is_read, is_ai, created_at FROM chat_messages WHERE user_id = ? ORDER BY created_at ASC");
        $stmt->bind_param("i", $target_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        echo json_encode(['success' => true, 'messages' => $messages]);
    } else {
        echo json_encode(['success' => false]);
    }
} elseif ($action === 'send_reply') {
    // ✅ CSRF Check
    if (!verifyCsrfToken()) {
        echo json_encode(['success' => false, 'message' => 'CSRF Token Invalid']);
        exit;
    }

    $target_user_id = intval($_POST['user_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    $imagePath = null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES['image']['tmp_name']);

        if (in_array($mimeType, $allowedTypes)) {
            $ext = 'jpg';
            if ($mimeType == 'image/png')
                $ext = 'png';
            if ($mimeType == 'image/gif')
                $ext = 'gif';
            if ($mimeType == 'image/webp')
                $ext = 'webp';

            // ✅ Secure Filename
            $fileName = 'admin_' . md5(uniqid('admin_', true)) . '.' . $ext;
            $uploadDir = __DIR__ . '/../../uploads/chat/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0777, true);

            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fileName)) {
                $imagePath = 'uploads/chat/' . $fileName;
            }
        }
    }
    if ((!empty($message) || !empty($imagePath)) && $target_user_id > 0) {
        $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, sender, message, image_path, is_read, is_ai) VALUES (?, 'admin', ?, ?, 0, 0)");
        $stmt->bind_param("iss", $target_user_id, $message, $imagePath);
        if ($stmt->execute())
            echo json_encode(['success' => true]);
        else
            echo json_encode(['success' => false]);
    } else {
        echo json_encode(['success' => false]);
    }
} elseif ($action === 'get_ai_status') {
    $res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='ai_active'");
    $status = ($res && $res->fetch_assoc()['setting_value'] == '1');
    echo json_encode(['success' => true, 'active' => $status]);
} elseif ($action === 'toggle_ai') {
    $status = ($_POST['status'] === 'true') ? '1' : '0';
    $conn->query("INSERT INTO system_settings (setting_key, setting_value) VALUES ('ai_active', '$status') ON DUPLICATE KEY UPDATE setting_value = '$status'");
    echo json_encode(['success' => true]);
} elseif ($action === 'simulate_chat') {
    $target_user_id = intval($_POST['user_id'] ?? 0);
    $fake_sender = $_POST['sender'] ?? 'admin';
    $message = trim($_POST['message'] ?? '');
    if ($target_user_id > 0 && !empty($message)) {
        $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, sender, message, is_read, is_ai, created_at) VALUES (?, ?, ?, 1, 0, NOW())");
        $stmt->bind_param("iss", $target_user_id, $fake_sender, $message);
        if ($stmt->execute())
            echo json_encode(['success' => true]);
        else
            echo json_encode(['success' => false]);
    } else {
        echo json_encode(['success' => false]);
    }
} elseif ($action === 'get_suggestions') {
    $sql = "SELECT message FROM chat_messages WHERE sender = 'admin' AND LENGTH(message) > 3 AND message NOT LIKE '%||ACTION:%' GROUP BY message ORDER BY COUNT(*) DESC, MAX(created_at) DESC LIMIT 20";
    $result = $conn->query($sql);
    $suggestions = [];
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = $row['message'];
    }
    echo json_encode(['success' => true, 'data' => $suggestions]);
}

// =========================================================
// ✅ 8. VOICE STATUS (เพิ่มใหม่)
// =========================================================
elseif ($action === 'get_voice_status') {
    $res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='chat_voice_notify'");
    $row = $res->fetch_assoc();
    $status = ($row) ? ($row['setting_value'] == '1') : true; // Default ON
    echo json_encode(['success' => true, 'active' => $status]);
}

// =========================================================
// ✅ 9. TOGGLE VOICE (เพิ่มใหม่)
// =========================================================
elseif ($action === 'toggle_voice') {
    $status = ($_POST['status'] === 'true') ? '1' : '0';
    $conn->query("INSERT INTO system_settings (setting_key, setting_value) VALUES ('chat_voice_notify', '$status') ON DUPLICATE KEY UPDATE setting_value = '$status'");
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Action']);
}
?>