<?php
/**
 * Theme Head - โหลดธีมจาก system_settings
 * Version 2.1 - Full Theme Support + Custom Colors
 * ใช้ include ตอนท้าย <head> ของแต่ละหน้า
 */
if (!isset($conn) && file_exists(__DIR__ . '/../controller/config.php')) {
    require_once __DIR__ . '/../controller/config.php';
}
if (!function_exists('getSiteTheme')) {
    require_once __DIR__ . '/../controller/theme_helper.php';
}

// Helper function to get setting
function getThemeSetting($conn, $key, $default = '') {
    if (!$conn) return $default;
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        if (!$stmt) return $default;
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['setting_value'];
        }
        $stmt->close();
    } catch (Exception $e) {}
    return $default;
}

$currentTheme = isset($conn) ? getSiteTheme($conn) : 'red';

// Check for custom colors
$useCustomColors = isset($conn) ? getThemeSetting($conn, 'use_custom_colors', '0') : '0';
$customBgBody = isset($conn) ? getThemeSetting($conn, 'custom_bg_body', '') : '';
$customBgCard = isset($conn) ? getThemeSetting($conn, 'custom_bg_card', '') : '';
$customPrimaryColor = isset($conn) ? getThemeSetting($conn, 'custom_primary_color', '') : '';
?>
<style id="site-theme">
<?php echo getFullThemeCss($currentTheme); ?>

/* Force body background override - highest specificity */
html body, body.loaded, body {
    background-color: var(--bg-body) !important;
    background: var(--bg-body) !important;
}

<?php if ($useCustomColors == '1'): ?>
/* ========== CUSTOM COLORS OVERRIDE ========== */
<?php if (!empty($customBgBody)): ?>
body {
    background-color: <?php echo htmlspecialchars($customBgBody); ?> !important;
}
<?php endif; ?>

<?php if (!empty($customBgCard)): ?>
.card, .glass-card, .glass-box, .stat-card, .menu-card,
.server-item, .package-card, .rental-card, .notification-item,
.dropdown-menu, .user-dropdown, .nav-submenu, .sidebar,
.navbar, .admin-navbar, .bottom-nav, nav, header, .header,
.card-custom, .modal-content, .alert,
[class*='card'], [class*='box'] {
    background: <?php echo htmlspecialchars($customBgCard); ?> !important;
}

.card:hover, .glass-card:hover, .server-item:hover,
.package-card:hover, .menu-card:hover {
    background: <?php echo htmlspecialchars($customBgCard); ?> !important;
    filter: brightness(1.1);
}
<?php endif; ?>

<?php if (!empty($customPrimaryColor)): ?>
<?php 
// Calculate darker shade for gradients
$hex = ltrim($customPrimaryColor, '#');
$r = max(0, hexdec(substr($hex, 0, 2)) - 40);
$g = max(0, hexdec(substr($hex, 2, 2)) - 40);
$b = max(0, hexdec(substr($hex, 4, 2)) - 40);
$darkerColor = sprintf("#%02x%02x%02x", $r, $g, $b);
$shadowColor = "rgba(" . hexdec(substr($hex, 0, 2)) . ", " . hexdec(substr($hex, 2, 2)) . ", " . hexdec(substr($hex, 4, 2)) . ", 0.3)";
?>
:root {
    --primary: <?php echo htmlspecialchars($customPrimaryColor); ?> !important;
    --accent: <?php echo htmlspecialchars($customPrimaryColor); ?> !important;
    --primary-dark: <?php echo $darkerColor; ?> !important;
    --theme-gradient: linear-gradient(135deg, <?php echo htmlspecialchars($customPrimaryColor); ?>, <?php echo $darkerColor; ?>) !important;
    --border-color: <?php echo $shadowColor; ?> !important;
    --theme-shadow: <?php echo $shadowColor; ?> !important;
}

.btn-primary, .btn-danger {
    background: linear-gradient(135deg, <?php echo htmlspecialchars($customPrimaryColor); ?>, <?php echo $darkerColor; ?>) !important;
    border-color: <?php echo htmlspecialchars($customPrimaryColor); ?> !important;
}

.btn-outline-primary, .btn-outline-danger {
    color: <?php echo htmlspecialchars($customPrimaryColor); ?> !important;
    border-color: <?php echo htmlspecialchars($customPrimaryColor); ?> !important;
}

.btn-outline-primary:hover, .btn-outline-danger:hover {
    background: <?php echo htmlspecialchars($customPrimaryColor); ?> !important;
    color: #fff !important;
}

.text-primary, .text-danger { color: <?php echo htmlspecialchars($customPrimaryColor); ?> !important; }
.border-primary, .border-danger { border-color: <?php echo htmlspecialchars($customPrimaryColor); ?> !important; }
.bg-primary, .bg-danger, .badge-primary, .badge.bg-danger { 
    background: linear-gradient(135deg, <?php echo htmlspecialchars($customPrimaryColor); ?>, <?php echo $darkerColor; ?>) !important; 
}

.nav-link.active, .nav-menu-link.active, .menu-link.active,
.nav-tabs .nav-link.active {
    color: <?php echo htmlspecialchars($customPrimaryColor); ?> !important;
    border-bottom-color: <?php echo htmlspecialchars($customPrimaryColor); ?> !important;
}

.form-control:focus, .form-select:focus, input:focus, textarea:focus, select:focus {
    border-color: <?php echo htmlspecialchars($customPrimaryColor); ?> !important;
    box-shadow: 0 0 0 3px <?php echo $shadowColor; ?> !important;
}

.progress-bar { background: linear-gradient(135deg, <?php echo htmlspecialchars($customPrimaryColor); ?>, <?php echo $darkerColor; ?>) !important; }

.avatar, .user-avatar, .notification-badge, .step-number, .commission-badge {
    background: linear-gradient(135deg, <?php echo htmlspecialchars($customPrimaryColor); ?>, <?php echo $darkerColor; ?>) !important;
}

.referral-code, .server-name, .price { color: <?php echo htmlspecialchars($customPrimaryColor); ?> !important; }

::-webkit-scrollbar-thumb { background: <?php echo htmlspecialchars($customPrimaryColor); ?>; }
::selection { background: <?php echo htmlspecialchars($customPrimaryColor); ?> !important; }

a { color: <?php echo htmlspecialchars($customPrimaryColor); ?>; }
a:hover { color: <?php echo $darkerColor; ?>; }

.nav-pills .nav-link.active { background: <?php echo htmlspecialchars($customPrimaryColor); ?> !important; }
.theme-swatch.active { box-shadow: 0 0 0 3px <?php echo htmlspecialchars($customPrimaryColor); ?> !important; }
<?php endif; ?>
<?php endif; ?>
</style>
