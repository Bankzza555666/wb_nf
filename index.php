<?php
// index.php
// V5.9 Final Router (Full Admin Suite Support)

// เปิดโหมดดีบักเมื่อแก้ 500 บนโฮส: สร้างไฟล์ .debug_500 ในโฟลเดอร์เดียวกับ index.php แล้วโหลดหน้าใหม่ (ลบไฟล์ออกหลังเช็คเสร็จ)
if (file_exists(__DIR__ . '/.debug_500')) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    ini_set('log_errors', 1);
    $logFile = __DIR__ . '/logs/php_errors.log';
    if (is_dir(__DIR__ . '/logs') && is_writable(__DIR__ . '/logs')) {
        ini_set('error_log', $logFile);
    }
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// 1. เริ่มต้น Session และโหลด config ใน scope ของ index (ให้ $conn ใช้ได้เมื่อ include หน้า admin_api)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// ตรวจสอบว่า request ถึง index หรือไม่ (ก่อนโหลด config) — ใช้ ?p=admin_api&ping=1
if (!empty($_GET['ping']) && isset($_GET['p']) && $_GET['p'] === 'admin_api') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => 1, 'step' => 'index']);
    exit;
}
require_once __DIR__ . '/controller/config.php';
// ตรวจสอบหลังโหลด config — ใช้ ?p=admin_api&ping=2
if (!empty($_GET['ping']) && $_GET['ping'] === '2' && isset($_GET['p']) && $_GET['p'] === 'admin_api') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => 1, 'step' => 'after_config', 'conn' => isset($conn)]);
    exit;
}
require_once __DIR__ . '/controller/auth_check.php';
$is_logged_in = isAuthenticated();

// 2. กำหนดหน้าสำหรับ "ผู้เยี่ยมชม" (Guest Pages)
$allowed_pages_guest = [
    'landing' => 'page/landing.php',
    'forget' => 'page/forget_password.php',
    'otp' => 'page/otp_verification.php',
    'reset_password' => 'page/reset_password.php',
];
$default_guest_page = 'landing';

// 3. กำหนดหน้าสำหรับ "สมาชิก & แอดมิน" (Auth Pages)
$allowed_pages_auth = [
    // --- หน้าสำหรับ User ทั่วไป ---
    'home' => 'home/dashboard.php',
    'userdetail' => 'home/userdtail.php',
    'topup' => 'home/topup.php',
    'topup_success' => 'home/topup_success.php',
    'topup_history' => 'home/topup_history.php',
    'rent_vpn' => 'Server_price/custom_rental.php',
    'my_vpn' => 'Server_price/my_vpn.php',
    'rent_ssh' => 'Server_price/rent_ssh.php',       // ✅ NEW: เช่า SSH/NPV
    'my_ssh' => 'Server_price/my_ssh.php',           // ✅ NEW: SSH ของฉัน
    'products_all' => 'Server_price/products_all.php',
    'products_category' => 'Server_price/products_category.php',
    'contact' => 'home/contact.php', // หน้าแชทลูกค้า
    'referral' => 'home/referral.php', // ✅ NEW: ระบบแนะนำเพื่อน

    // --- ✅ หน้าสำหรับ Admin (เพิ่มครบทุกหน้า) ---
    'admin_dashboard' => 'admin/dashboard.php',
    'admin_chat' => 'admin/chat.php',
    'admin_users' => 'admin/users.php',
    'admin_servers' => 'admin/servers.php',
    'admin_products' => 'admin/products.php',
    'admin_ssh_servers' => 'admin/ssh_servers.php',   // ✅ NEW: จัดการ SSH Servers
    'admin_ssh_products' => 'admin/ssh_products.php', // ✅ NEW: จัดการแพ็กเกจ SSH
    'admin_vpn_products' => 'admin/vpn_products.php', // ✅ NEW: จัดการแพ็กเกจ VPN (Premium UI)
    'admin_topup' => 'admin/topup.php',
    'topup_packages' => 'admin/topup_packages.php', // ✅ Manage Packages
    'admin_notifications' => 'admin/notifications.php', // ✅ New Notification Page
    'admin_ai_training' => 'admin/ai_training.php', // ✅ Smart AI Training
    'admin_ai_analytics' => 'admin/ai_analytics.php', // ✅ NEW: AI Analytics Dashboard
    'admin_rentals' => 'admin/rentals.php', // ✅ NEW: Rentals Management
    'admin_reports' => 'admin/reports.php', // ✅ NEW: Reports & Analytics
    'admin_settings' => 'admin/settings.php', // ✅ NEW: System Settings
    'admin_referral' => 'admin/referral.php', // ✅ NEW: จัดการระบบแนะนำเพื่อน
    'admin_api' => 'controller/admin_controller/admin_api.php', // ✅ API สำหรับหน้าแอดมิน (ส่ง JSON อย่างเดียว)
    'admin_web_terminal' => 'admin/web_terminal.php', // ✅ NEW: Web SSH Terminal

    // User Pages
    'faq' => 'home/faq.php', // ✅ NEW: FAQ/Help
    'tutorials' => 'home/tutorials.php', // ✅ NEW: Tutorials
];

// ค่าเริ่มต้น (จะถูกเขียนทับด้วย Logic ตรวจสอบ Role)
$default_auth_page = 'home';

$file_to_include = null;

// 4. Router Logic
if ($is_logged_in) {
    // --- ดึง Role ล่าสุด (ใช้ทั้ง redirect และเช็คโหมดบำรุงรักษา) ---
    $uid = $_SESSION['user_id'];
    $curr_user = null;
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    $curr_user = $res->fetch_assoc();
    $stmt->close();
    $is_admin = ($curr_user && $curr_user['role'] === 'admin');
    if ($curr_user && isset($curr_user['role'])) {
        $_SESSION['role'] = $curr_user['role'];
    }

    // ออกจากระบบให้ทำก่อนเช็คโหมดบำรุงรักษา
    $page_key_early = $_GET['p'] ?? '';
    if ($page_key_early === 'logout') {
        header('Location: controller/logout.php');
        exit;
    }

    // --- โหมดบำรุงรักษา: ผู้ใช้ทั่วไปเห็นหน้าแจ้งปรับปรุง (แอดมินใช้ได้ตามปกติ) ---
    $maintenance_mode = '0';
    $rs = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode' LIMIT 1");
    if ($rs && $row = $rs->fetch_assoc()) {
        $maintenance_mode = $row['setting_value'];
    }
    if ($maintenance_mode === '1' && !$is_admin) {
        $file_to_include = 'page/maintenance.php';
    } else {
        $default_auth_page = $is_admin ? 'admin_dashboard' : 'home';
        // --- Logic: กำหนดหน้าแรกอัตโนมัติ ---
        if (!isset($_GET['p'])) {
            header("Location: ?p=" . $default_auth_page);
            exit;
        }
        // -----------------------------------------------------------

        $page_key = $_GET['p'] ?? $default_auth_page;
        $page_key = strip_tags($page_key);

        if (isset($_GET['r'])) {
            header('Location: ?p=' . $default_auth_page);
            exit;
        }

        if (array_key_exists($page_key, $allowed_pages_auth)) {
            $file_to_include = $allowed_pages_auth[$page_key];
        } else {
            $file_to_include = $allowed_pages_auth[$default_auth_page];
        }
    }

} else {
    // --- กรณี: ผู้ใช้ยังไม่ล็อกอิน (Guest) ---

    $page_key = $_GET['r'] ?? $default_guest_page;
    $page_key = strip_tags($page_key);

    // ป้องกัน Guest เข้าหน้า Auth (?p=)
    if (isset($_GET['p'])) {
        header('Location: ?r=' . $default_guest_page);
        exit;
    }

    if (array_key_exists($page_key, $allowed_pages_guest)) {
        $file_to_include = $allowed_pages_guest[$page_key];
    } else {
        $file_to_include = $allowed_pages_guest[$default_guest_page];
    }
}

// 5. โหลดไฟล์หน้าเว็บ
if (isset($file_to_include) && file_exists($file_to_include)) {
    include $file_to_include;

    // โหลด Visual Editor สำหรับ Admin (ถ้าหน้านั้นไม่ได้ใช้ footer.php)
    if (file_exists(__DIR__ . '/include/visual_editor_loader.php')) {
        include __DIR__ . '/include/visual_editor_loader.php';
    }
} else {
    // กรณีหาไฟล์ไม่เจอ (404)
    echo "<div style='text-align:center; padding:50px; font-family:sans-serif; background:#f8d7da; color:#721c24;'>";
    echo "<h1>Error 404</h1>";
    echo "<p>Page configuration error. File not found: <strong>" . htmlspecialchars($file_to_include) . "</strong></p>";
    echo "<p>Please check if the file exists in the folder.</p>";
    echo "<a href='index.php' style='color:#721c24; font-weight:bold;'>Go Back Home</a>";
    echo "</div>";
}
?>