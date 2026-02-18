<?php
/**
 * Visual Editor API Controller
 * ระบบแก้ไขเว็บแบบ Visual สำหรับ Admin
 * รองรับทั้งการแก้ไขผ่าน selector และ editable key
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/editable_content.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

// get_customizations อนุญาตสำหรับทุกคน (ไม่ต้อง login)
// action อื่นๆ ต้องเป็น Admin เท่านั้น
$publicActions = ['get_customizations'];
$isAdmin = false;

if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    $uid = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();
    if ($user && $user['role'] === 'admin') {
        $isAdmin = true;
    }
}

// ถ้าไม่ใช่ public action และไม่ใช่ admin → ไม่อนุญาต
if (!in_array($action, $publicActions) && !$isAdmin) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// สร้างตารางถ้ายังไม่มี
$conn->query("CREATE TABLE IF NOT EXISTS site_customizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    selector VARCHAR(500) NOT NULL,
    page_path VARCHAR(255) DEFAULT '*',
    property_name VARCHAR(100) NOT NULL,
    property_value TEXT NOT NULL,
    element_type VARCHAR(50) DEFAULT 'style',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_customization (selector(191), page_path, property_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// สร้างตารางสำหรับเก็บข้อความ
$conn->query("CREATE TABLE IF NOT EXISTS site_texts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    text_key VARCHAR(255) NOT NULL,
    page_path VARCHAR(255) DEFAULT '*',
    original_text TEXT,
    custom_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_text (text_key(191), page_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

switch ($action) {
    case 'save_style':
        saveStyle($conn);
        break;
    case 'save_text':
        saveText($conn);
        break;
    case 'get_customizations':
        getCustomizations($conn);
        break;
    case 'delete_customization':
        deleteCustomization($conn);
        break;
    case 'reset_all':
        resetAll($conn);
        break;
    case 'get_all_customizations':
        getAllCustomizations($conn);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function saveStyle($conn) {
    $selector = $_POST['selector'] ?? '';
    $property = $_POST['property'] ?? '';
    $value = $_POST['value'] ?? '';
    $page = $_POST['page'] ?? '*';
    
    // Debug log
    error_log("[VE] saveStyle: selector=$selector, property=$property, value=$value, page=$page");
    
    if (empty($selector) || empty($property)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    $stmt = $conn->prepare("INSERT INTO site_customizations (selector, page_path, property_name, property_value, element_type) 
                            VALUES (?, ?, ?, ?, 'style')
                            ON DUPLICATE KEY UPDATE property_value = ?, updated_at = CURRENT_TIMESTAMP");
    $stmt->bind_param("sssss", $selector, $page, $property, $value, $value);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Style saved']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    $stmt->close();
}

function saveText($conn) {
    $textKey = $_POST['text_key'] ?? '';
    $originalText = $_POST['original_text'] ?? '';
    $customText = $_POST['custom_text'] ?? '';
    $page = $_POST['page'] ?? '*';
    
    if (empty($textKey)) {
        echo json_encode(['success' => false, 'message' => 'Missing text key']);
        return;
    }
    
    $stmt = $conn->prepare("INSERT INTO site_texts (text_key, page_path, original_text, custom_text) 
                            VALUES (?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE custom_text = ?, updated_at = CURRENT_TIMESTAMP");
    $stmt->bind_param("sssss", $textKey, $page, $originalText, $customText, $customText);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Text saved']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    $stmt->close();
}

function getCustomizations($conn) {
    $page = $_GET['page'] ?? '*';
    
    // Debug log
    error_log("[VE] getCustomizations: page=$page");
    
    $customizations = [
        'styles' => [],
        'texts' => []
    ];
    
    // Get styles - ดึงทั้งหน้าที่ระบุและหน้า * (global)
    $stmt = $conn->prepare("SELECT selector, property_name, property_value, page_path FROM site_customizations WHERE page_path = ? OR page_path = '*'");
    $stmt->bind_param("s", $page);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $customizations['styles'][] = $row;
    }
    $stmt->close();
    
    // Get texts
    $stmt = $conn->prepare("SELECT text_key, custom_text FROM site_texts WHERE page_path = ? OR page_path = '*'");
    $stmt->bind_param("s", $page);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $customizations['texts'][$row['text_key']] = $row['custom_text'];
    }
    $stmt->close();
    
    echo json_encode(['success' => true, 'data' => $customizations]);
}

function getAllCustomizations($conn) {
    $data = [
        'styles' => [],
        'texts' => []
    ];
    
    // Get all styles
    $result = $conn->query("SELECT id, selector, page_path, property_name, property_value, updated_at FROM site_customizations ORDER BY updated_at DESC");
    while ($row = $result->fetch_assoc()) {
        $data['styles'][] = $row;
    }
    
    // Get all texts
    $result = $conn->query("SELECT id, text_key, page_path, original_text, custom_text, updated_at FROM site_texts ORDER BY updated_at DESC");
    while ($row = $result->fetch_assoc()) {
        $data['texts'][] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $data]);
}

function deleteCustomization($conn) {
    $type = $_POST['type'] ?? 'style';
    $id = intval($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        return;
    }
    
    $table = $type === 'text' ? 'site_texts' : 'site_customizations';
    $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    $stmt->close();
}

function resetAll($conn) {
    $page = $_POST['page'] ?? null;
    
    if ($page) {
        $stmt = $conn->prepare("DELETE FROM site_customizations WHERE page_path = ?");
        $stmt->bind_param("s", $page);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $conn->prepare("DELETE FROM site_texts WHERE page_path = ?");
        $stmt->bind_param("s", $page);
        $stmt->execute();
        $stmt->close();
    } else {
        $conn->query("TRUNCATE TABLE site_customizations");
        $conn->query("TRUNCATE TABLE site_texts");
    }
    
    echo json_encode(['success' => true, 'message' => 'Reset complete']);
}
