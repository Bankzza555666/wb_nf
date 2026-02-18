<?php
// controller/admin_controller/admin_notify_api.php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config.php';

// 1. Auth Check (Admin Only)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$uid = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

// 2. Action Handler
switch ($action) {
    case 'get_all':
        // Fetch notifications with user info (LEFT JOIN)
// Global announcements have user_id = NULL
        $sql = "SELECT n.*, u.username
FROM notifications n
LEFT JOIN users u ON n.user_id = u.id
ORDER BY n.created_at DESC LIMIT 50";
        $result = $conn->query($sql);
        $data = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'create':
        $title = $_POST['title'] ?? '';
        $message = $_POST['message'] ?? '';
        $type = $_POST['type'] ?? 'info'; // info, warning, error, success, announcement
        $target = $_POST['target'] ?? 'global'; // global or specific user_id? (For now simplified to global)

        if (empty($title) || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Missing fields']);
            exit;
        }

        // Global Announcement: user_id IS NULL
// Note: You might want to distinguish 'announcement' type visually
        $sql = "INSERT INTO notifications (user_id, type, title, message, created_at) VALUES (NULL, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $type, $title, $message);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Announcement created']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;

    case 'update':
        $id = $_POST['id'] ?? 0;
        $title = $_POST['title'] ?? '';
        $message = $_POST['message'] ?? '';
        $type = $_POST['type'] ?? 'info';

        if (!$id || empty($title) || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Missing fields']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE notifications SET title = ?, message = ?, type = ? WHERE id = ?");
        $stmt->bind_param("sssi", $title, $message, $type, $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Notification updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed']);
        }
        break;

    case 'delete':
        $id = $_POST['id'] ?? 0;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Delete failed']);
        }
        break;

    case 'clear_all':
        // DANGEROUS: Clears ALL notifications from the table
// Or maybe just clear all 'announcement' types?
// User requested "Clear Warning All" -> implied clearing all bad stuff, or literally everything.
// Let's truncate or delete all for now as requested "button to delete all warnings".

        if ($conn->query("TRUNCATE TABLE notifications")) {
            echo json_encode(['success' => true, 'message' => 'All notifications cleared']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to clear']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>