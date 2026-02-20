<?php
// home/navbar.php
// V5.10 Dynamic Navbar Router

// 1. ดึงข้อมูล User/Notifications (เหมือน V5.9 เดิม)
$user_id = $_SESSION['user_id'] ?? null;

if ($user_id) {
    if (!isset($user) || empty($user)) {
        $stmt = $conn->prepare("SELECT username, email, credit FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            header('Location: controller/logout.php');
            exit;
        }
    }

    $user['username'] = $user['username'] ?? 'Guest';
    $user['email'] = $user['email'] ?? 'no-email@example.com';
    $user['credit'] = $user['credit'] ?? 0;

    $unread_count = 0;
    $notifications = [];

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $unread_count = $row['count'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT n.*, t.transaction_ref, t.status as transaction_status, t.admin_note 
                            FROM notifications n 
                            LEFT JOIN topup_transactions t ON n.transaction_id = t.id 
                            WHERE (n.user_id = ? OR n.user_id IS NULL) 
                            ORDER BY (n.user_id IS NULL) DESC, n.created_at DESC LIMIT 20");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
}

$current_page = $_GET['p'] ?? '';

// 2. ดึงค่า Navbar และ Sidebar Style จาก System Settings
$navbar_style = 'style1'; // Default
$sidebar_style = 'style1'; // Default

$stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('navbar_style', 'sidebar_style')");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if ($row['setting_key'] === 'navbar_style') {
            $navbar_style = $row['setting_value'];
        } else if ($row['setting_key'] === 'sidebar_style') {
            $sidebar_style = $row['setting_value'];
        }
    }
    $stmt->close();
}

// 3. โหลด Navbar Template ตาม Style
$nav_style_file = __DIR__ . '/navbar_' . $navbar_style . '.php';

if (file_exists($nav_style_file)) {
    include $nav_style_file;
} else {
    include __DIR__ . '/navbar_style1.php';
}

// 4. โหลด Sidebar Template ตาม Style
$side_style_file = __DIR__ . '/sidebar_' . $sidebar_style . '.php';

if (file_exists($side_style_file)) {
    include $side_style_file;
} else {
    include __DIR__ . '/sidebar_style1.php';
}
?>