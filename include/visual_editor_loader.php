<?php
/**
 * Visual Editor Loader
 * Include ไฟล์นี้ที่ท้าย <body> ของทุกหน้า
 * - Customizations จะแสดงสำหรับทุกคน
 * - เครื่องมือแก้ไข (Visual Editor) โหลดเฉพาะ Admin
 */

// เริ่ม session ถ้ายังไม่ได้เริ่ม
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// โหลด config เพื่อให้มี $conn (ถ้ายังไม่มี)
if (!isset($conn) && file_exists(__DIR__ . '/../controller/config.php')) {
    require_once __DIR__ . '/../controller/config.php';
}

// ตรวจสอบว่า Admin login อยู่หรือไม่
$isAdminLoggedIn = false;
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $isAdminLoggedIn = true;
    } elseif (isset($conn) && $conn) {
        $uid = (int) $_SESSION['user_id'];
        $stmt = @$conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            if ($row && $row['role'] === 'admin') {
                $_SESSION['role'] = 'admin';
                $isAdminLoggedIn = true;
            }
            $stmt->close();
        }
    }
}

// ไม่โหลดบนหน้าแอดมิน
$current_page = isset($_GET['p']) ? $_GET['p'] : (isset($_GET['r']) ? $_GET['r'] : '');
$is_admin_panel = (strpos($current_page, 'admin_') === 0) || in_array($current_page, ['topup_packages'], true);

// โหลดเฉพาะหน้าฝั่ง User (ไม่โหลดบนหน้าแอดมิน)
if (!$is_admin_panel):
?>
<!-- Customizations Loader (โหลดสำหรับทุกคน) -->
<style id="ve-preload-hide">
.ve-content-loading { opacity: 0; transition: opacity 0.15s ease; }
.ve-content-ready { opacity: 1; }
</style>
<script>
window.VE_PAGE = <?php echo json_encode($current_page ?: '*'); ?>;
document.body.classList.add('ve-content-loading');
</script>
<script src="js/apply-customizations.js?v=<?php echo time(); ?>"></script>

<?php if ($isAdminLoggedIn): ?>
<!-- Visual Editor (เครื่องมือแก้ไข - เฉพาะ Admin) -->
<script>window.IS_ADMIN = true;</script>
<script src="js/visual-editor.js?v=<?php echo time(); ?>"></script>
<?php endif; ?>

<?php endif; ?>
