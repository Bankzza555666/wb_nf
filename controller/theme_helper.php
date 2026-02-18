<?php
/**
 * Theme Helper - โปรแกรมเลือกสีธีมเว็บไซต์ (Admin Settings)
 * Version 2.0 - Full Theme Support
 */

// ค่าเริ่มต้นต้องมี $conn จาก config
if (!isset($conn) && file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

// ธีมที่รองรับ - ครบทุกสี (พื้นหลัง, card, text, border, accent)
$GLOBALS['SITE_THEMES'] = [
    'red' => [
        'name' => 'แดง Netflix',
        'primary' => '#E50914',
        'primary_dark' => '#99060d',
        'accent' => '#ff4757',
        'bg_body' => '#000000',
        'bg_card' => 'rgba(20, 20, 20, 0.95)',
        'bg_card_hover' => 'rgba(30, 30, 30, 0.95)',
        'bg_input' => 'rgba(255, 255, 255, 0.05)',
        'text_primary' => '#ffffff',
        'text_secondary' => '#a0a0a0',
        'text_muted' => '#666666',
        'border' => 'rgba(229, 9, 20, 0.3)',
        'border_light' => 'rgba(255, 255, 255, 0.1)',
        'gradient' => 'linear-gradient(135deg, #E50914, #99060d)',
        'shadow' => 'rgba(229, 9, 20, 0.2)',
    ],
    'blue' => [
        'name' => 'น้ำเงิน Ocean',
        'primary' => '#3b82f6',
        'primary_dark' => '#1d4ed8',
        'accent' => '#60a5fa',
        'bg_body' => '#0a0f1a',
        'bg_card' => 'rgba(15, 25, 45, 0.95)',
        'bg_card_hover' => 'rgba(25, 40, 70, 0.95)',
        'bg_input' => 'rgba(59, 130, 246, 0.1)',
        'text_primary' => '#ffffff',
        'text_secondary' => '#94a3b8',
        'text_muted' => '#64748b',
        'border' => 'rgba(59, 130, 246, 0.3)',
        'border_light' => 'rgba(59, 130, 246, 0.15)',
        'gradient' => 'linear-gradient(135deg, #3b82f6, #1d4ed8)',
        'shadow' => 'rgba(59, 130, 246, 0.2)',
    ],
    'green' => [
        'name' => 'เขียว Matrix',
        'primary' => '#22c55e',
        'primary_dark' => '#16a34a',
        'accent' => '#4ade80',
        'bg_body' => '#0a1a0f',
        'bg_card' => 'rgba(15, 35, 20, 0.95)',
        'bg_card_hover' => 'rgba(25, 55, 35, 0.95)',
        'bg_input' => 'rgba(34, 197, 94, 0.1)',
        'text_primary' => '#ffffff',
        'text_secondary' => '#86efac',
        'text_muted' => '#4ade80',
        'border' => 'rgba(34, 197, 94, 0.3)',
        'border_light' => 'rgba(34, 197, 94, 0.15)',
        'gradient' => 'linear-gradient(135deg, #22c55e, #16a34a)',
        'shadow' => 'rgba(34, 197, 94, 0.2)',
    ],
    'purple' => [
        'name' => 'ม่วง Galaxy',
        'primary' => '#a855f7',
        'primary_dark' => '#7c3aed',
        'accent' => '#c084fc',
        'bg_body' => '#0f0a1a',
        'bg_card' => 'rgba(25, 15, 45, 0.95)',
        'bg_card_hover' => 'rgba(40, 25, 70, 0.95)',
        'bg_input' => 'rgba(168, 85, 247, 0.1)',
        'text_primary' => '#ffffff',
        'text_secondary' => '#d8b4fe',
        'text_muted' => '#a78bfa',
        'border' => 'rgba(168, 85, 247, 0.3)',
        'border_light' => 'rgba(168, 85, 247, 0.15)',
        'gradient' => 'linear-gradient(135deg, #a855f7, #7c3aed)',
        'shadow' => 'rgba(168, 85, 247, 0.2)',
    ],
    'orange' => [
        'name' => 'ส้ม Sunset',
        'primary' => '#f97316',
        'primary_dark' => '#ea580c',
        'accent' => '#fb923c',
        'bg_body' => '#1a0f0a',
        'bg_card' => 'rgba(35, 20, 15, 0.95)',
        'bg_card_hover' => 'rgba(55, 35, 25, 0.95)',
        'bg_input' => 'rgba(249, 115, 22, 0.1)',
        'text_primary' => '#ffffff',
        'text_secondary' => '#fdba74',
        'text_muted' => '#fb923c',
        'border' => 'rgba(249, 115, 22, 0.3)',
        'border_light' => 'rgba(249, 115, 22, 0.15)',
        'gradient' => 'linear-gradient(135deg, #f97316, #ea580c)',
        'shadow' => 'rgba(249, 115, 22, 0.2)',
    ],
    'teal' => [
        'name' => 'ฟ้าอมเขียว Cyber',
        'primary' => '#14b8a6',
        'primary_dark' => '#0d9488',
        'accent' => '#2dd4bf',
        'bg_body' => '#0a1515',
        'bg_card' => 'rgba(15, 30, 30, 0.95)',
        'bg_card_hover' => 'rgba(25, 50, 50, 0.95)',
        'bg_input' => 'rgba(20, 184, 166, 0.1)',
        'text_primary' => '#ffffff',
        'text_secondary' => '#5eead4',
        'text_muted' => '#2dd4bf',
        'border' => 'rgba(20, 184, 166, 0.3)',
        'border_light' => 'rgba(20, 184, 166, 0.15)',
        'gradient' => 'linear-gradient(135deg, #14b8a6, #0d9488)',
        'shadow' => 'rgba(20, 184, 166, 0.2)',
    ],
    'pink' => [
        'name' => 'ชมพู Sakura',
        'primary' => '#ec4899',
        'primary_dark' => '#db2777',
        'accent' => '#f472b6',
        'bg_body' => '#1a0a12',
        'bg_card' => 'rgba(35, 15, 25, 0.95)',
        'bg_card_hover' => 'rgba(55, 25, 40, 0.95)',
        'bg_input' => 'rgba(236, 72, 153, 0.1)',
        'text_primary' => '#ffffff',
        'text_secondary' => '#f9a8d4',
        'text_muted' => '#f472b6',
        'border' => 'rgba(236, 72, 153, 0.3)',
        'border_light' => 'rgba(236, 72, 153, 0.15)',
        'gradient' => 'linear-gradient(135deg, #ec4899, #db2777)',
        'shadow' => 'rgba(236, 72, 153, 0.2)',
    ],
    'gold' => [
        'name' => 'ทอง Premium',
        'primary' => '#eab308',
        'primary_dark' => '#ca8a04',
        'accent' => '#fde047',
        'bg_body' => '#1a1508',
        'bg_card' => 'rgba(35, 30, 15, 0.95)',
        'bg_card_hover' => 'rgba(55, 45, 25, 0.95)',
        'bg_input' => 'rgba(234, 179, 8, 0.1)',
        'text_primary' => '#ffffff',
        'text_secondary' => '#fde68a',
        'text_muted' => '#fcd34d',
        'border' => 'rgba(234, 179, 8, 0.3)',
        'border_light' => 'rgba(234, 179, 8, 0.15)',
        'gradient' => 'linear-gradient(135deg, #eab308, #ca8a04)',
        'shadow' => 'rgba(234, 179, 8, 0.2)',
    ],
    'indigo' => [
        'name' => 'คราม Indigo Night',
        'primary' => '#6366f1',
        'primary_dark' => '#4f46e5',
        'accent' => '#818cf8',
        'bg_body' => '#0a0a1a',
        'bg_card' => 'rgba(20, 20, 45, 0.95)',
        'bg_card_hover' => 'rgba(30, 30, 70, 0.95)',
        'bg_input' => 'rgba(99, 102, 241, 0.1)',
        'text_primary' => '#ffffff',
        'text_secondary' => '#a5b4fc',
        'text_muted' => '#818cf8',
        'border' => 'rgba(99, 102, 241, 0.3)',
        'border_light' => 'rgba(99, 102, 241, 0.15)',
        'gradient' => 'linear-gradient(135deg, #6366f1, #4f46e5)',
        'shadow' => 'rgba(99, 102, 241, 0.2)',
    ],
    'cyan' => [
        'name' => 'ฟ้าสด Cyan Sky',
        'primary' => '#06b6d4',
        'primary_dark' => '#0891b2',
        'accent' => '#22d3ee',
        'bg_body' => '#0a151a',
        'bg_card' => 'rgba(15, 30, 40, 0.95)',
        'bg_card_hover' => 'rgba(25, 50, 65, 0.95)',
        'bg_input' => 'rgba(6, 182, 212, 0.1)',
        'text_primary' => '#ffffff',
        'text_secondary' => '#67e8f9',
        'text_muted' => '#22d3ee',
        'border' => 'rgba(6, 182, 212, 0.3)',
        'border_light' => 'rgba(6, 182, 212, 0.15)',
        'gradient' => 'linear-gradient(135deg, #06b6d4, #0891b2)',
        'shadow' => 'rgba(6, 182, 212, 0.2)',
    ],
    'lime' => [
        'name' => 'เขียวมะนาว Lime Fresh',
        'primary' => '#84cc16',
        'primary_dark' => '#65a30d',
        'accent' => '#a3e635',
        'bg_body' => '#0a1a08',
        'bg_card' => 'rgba(20, 40, 15, 0.95)',
        'bg_card_hover' => 'rgba(30, 60, 25, 0.95)',
        'bg_input' => 'rgba(132, 204, 22, 0.1)',
        'text_primary' => '#ffffff',
        'text_secondary' => '#bef264',
        'text_muted' => '#a3e635',
        'border' => 'rgba(132, 204, 22, 0.3)',
        'border_light' => 'rgba(132, 204, 22, 0.15)',
        'gradient' => 'linear-gradient(135deg, #84cc16, #65a30d)',
        'shadow' => 'rgba(132, 204, 22, 0.2)',
    ],
    'rose' => [
        'name' => 'กุหลาบ Rose Wine',
        'primary' => '#e11d48',
        'primary_dark' => '#be123c',
        'accent' => '#fb7185',
        'bg_body' => '#1a080c',
        'bg_card' => 'rgba(40, 15, 20, 0.95)',
        'bg_card_hover' => 'rgba(60, 25, 30, 0.95)',
        'bg_input' => 'rgba(225, 29, 72, 0.1)',
        'text_primary' => '#ffffff',
        'text_secondary' => '#fda4af',
        'text_muted' => '#fb7185',
        'border' => 'rgba(225, 29, 72, 0.3)',
        'border_light' => 'rgba(225, 29, 72, 0.15)',
        'gradient' => 'linear-gradient(135deg, #e11d48, #be123c)',
        'shadow' => 'rgba(225, 29, 72, 0.2)',
    ],
    'amber' => [
        'name' => 'ส้มอำพัน Amber Warm',
        'primary' => '#f59e0b',
        'primary_dark' => '#d97706',
        'accent' => '#fbbf24',
        'bg_body' => '#1a1208',
        'bg_card' => 'rgba(40, 30, 15, 0.95)',
        'bg_card_hover' => 'rgba(60, 45, 20, 0.95)',
        'bg_input' => 'rgba(245, 158, 11, 0.1)',
        'text_primary' => '#ffffff',
        'text_secondary' => '#fcd34d',
        'text_muted' => '#fbbf24',
        'border' => 'rgba(245, 158, 11, 0.3)',
        'border_light' => 'rgba(245, 158, 11, 0.15)',
        'gradient' => 'linear-gradient(135deg, #f59e0b, #d97706)',
        'shadow' => 'rgba(245, 158, 11, 0.2)',
    ],
    'violet' => [
        'name' => 'ม่วงอ่อน Violet Dream',
        'primary' => '#8b5cf6',
        'primary_dark' => '#7c3aed',
        'accent' => '#a78bfa',
        'bg_body' => '#120a1a',
        'bg_card' => 'rgba(30, 20, 50, 0.95)',
        'bg_card_hover' => 'rgba(45, 30, 75, 0.95)',
        'bg_input' => 'rgba(139, 92, 246, 0.1)',
        'text_primary' => '#ffffff',
        'text_secondary' => '#c4b5fd',
        'text_muted' => '#a78bfa',
        'border' => 'rgba(139, 92, 246, 0.3)',
        'border_light' => 'rgba(139, 92, 246, 0.15)',
        'gradient' => 'linear-gradient(135deg, #8b5cf6, #7c3aed)',
        'shadow' => 'rgba(139, 92, 246, 0.2)',
    ],
    'emerald' => [
        'name' => 'เขียวมรกต Emerald Forest',
        'primary' => '#10b981',
        'primary_dark' => '#059669',
        'accent' => '#34d399',
        'bg_body' => '#081a12',
        'bg_card' => 'rgba(15, 40, 30, 0.95)',
        'bg_card_hover' => 'rgba(25, 60, 45, 0.95)',
        'bg_input' => 'rgba(16, 185, 129, 0.1)',
        'text_primary' => '#ffffff',
        'text_secondary' => '#6ee7b7',
        'text_muted' => '#34d399',
        'border' => 'rgba(16, 185, 129, 0.3)',
        'border_light' => 'rgba(16, 185, 129, 0.15)',
        'gradient' => 'linear-gradient(135deg, #10b981, #059669)',
        'shadow' => 'rgba(16, 185, 129, 0.2)',
    ],
];

/**
 * ดึงธีมปัจจุบันจาก system_settings
 * @return string theme key
 */
function getSiteTheme($conn) {
    if (!$conn) {
        return 'red';
    }
    $theme = 'red';
    try {
        // สร้างตาราง system_settings ถ้ายังไม่มี
        @$conn->query("CREATE TABLE IF NOT EXISTS `system_settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `setting_key` VARCHAR(100) NOT NULL UNIQUE,
            `setting_value` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $res = @$conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'site_theme' LIMIT 1");
        if ($res) {
            $row = $res->fetch_assoc();
            if ($row && !empty($row['setting_value'])) {
                $t = trim($row['setting_value']);
                $allowed = array_keys($GLOBALS['SITE_THEMES']);
                if (in_array($t, $allowed)) {
                    $theme = $t;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Theme get error: " . $e->getMessage());
    }
    return $theme;
}

/**
 * บันทึกธีมลง system_settings
 * @return bool
 */
function saveSiteTheme($conn, $theme) {
    $allowed = array_keys($GLOBALS['SITE_THEMES']);
    if (!in_array($theme, $allowed)) {
        return false;
    }
    try {
        // สร้างตารางถ้ายังไม่มี
        @$conn->query("CREATE TABLE IF NOT EXISTS `system_settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `setting_key` VARCHAR(100) NOT NULL UNIQUE,
            `setting_value` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // ลบค่าเก่าแล้ว insert ใหม่ (ป้องกัน duplicate)
        $conn->query("DELETE FROM system_settings WHERE setting_key = 'site_theme'");
        
        $ins = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('site_theme', ?)");
        $ins->bind_param("s", $theme);
        $result = $ins->execute();
        $ins->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("Theme save error: " . $e->getMessage());
        return false;
    }
}

/**
 * สร้าง CSS :root overrides สำหรับธีมที่เลือก
 * @param string $theme
 * @return string CSS
 */
function getThemeCss($theme) {
    $themes = $GLOBALS['SITE_THEMES'];
    if (!isset($themes[$theme])) {
        $theme = 'red';
    }
    $t = $themes[$theme];

    return ":root {
    /* Primary Colors */
    --primary: {$t['primary']};
    --primary-dark: {$t['primary_dark']};
    --accent: {$t['primary']};
    --accent-color: {$t['accent']};
    
    /* Background Colors */
    --bg-body: {$t['bg_body']};
    --bg-card: {$t['bg_card']};
    --bg-card-hover: {$t['bg_card_hover']};
    --bg-input: {$t['bg_input']};
    --card-bg: {$t['bg_card']};
    
    /* Text Colors */
    --text-primary: {$t['text_primary']};
    --text-secondary: {$t['text_secondary']};
    --text-muted: {$t['text_muted']};
    
    /* Border Colors */
    --border-color: {$t['border']};
    --border-light: {$t['border_light']};
    
    /* Effects */
    --theme-gradient: {$t['gradient']};
    --theme-shadow: {$t['shadow']};
    
    /* Legacy support */
    --primary-color: {$t['primary']};
}";
}

/**
 * สร้าง Full CSS Override สำหรับทุก element
 * @param string $theme
 * @return string CSS
 */
function getFullThemeCss($theme) {
    $themes = $GLOBALS['SITE_THEMES'];
    if (!isset($themes[$theme])) {
        $theme = 'red';
    }
    $t = $themes[$theme];
    
    $css = getThemeCss($theme);
    
    $css .= "
    /* ========== BODY & BACKGROUND ========== */
    body {
        background-color: {$t['bg_body']} !important;
        color: {$t['text_primary']} !important;
    }
    
    /* ========== CARDS & BOXES ========== */
    .card, .glass-card, .glass-box, .stat-card, .menu-card,
    .server-item, .package-card, .rental-card, .notification-item,
    .dropdown-menu, .user-dropdown, .nav-submenu, .sidebar,
    [class*='card'], [class*='box'] {
        background: {$t['bg_card']} !important;
        border-color: {$t['border']} !important;
    }
    
    .card:hover, .glass-card:hover, .server-item:hover,
    .package-card:hover, .menu-card:hover {
        background: {$t['bg_card_hover']} !important;
        border-color: {$t['primary']} !important;
    }
    
    /* ========== NAVBAR & NAVIGATION ========== */
    .navbar, .admin-navbar, .bottom-nav, nav,
    header, .header {
        background: {$t['bg_card']} !important;
        border-color: {$t['border']} !important;
    }
    
    .nav-link, .nav-menu-link, .menu-link {
        color: {$t['text_secondary']} !important;
    }
    
    .nav-link:hover, .nav-menu-link:hover, .menu-link:hover,
    .nav-link.active, .nav-menu-link.active, .menu-link.active {
        color: {$t['primary']} !important;
    }
    
    /* ========== BUTTONS ========== */
    .btn-primary, .btn-danger {
        background: {$t['gradient']} !important;
        border-color: {$t['primary']} !important;
        color: #fff !important;
    }
    
    .btn-primary:hover, .btn-danger:hover {
        background: {$t['primary_dark']} !important;
        border-color: {$t['primary_dark']} !important;
        box-shadow: 0 0 20px {$t['shadow']} !important;
    }
    
    .btn-outline-primary, .btn-outline-danger {
        color: {$t['primary']} !important;
        border-color: {$t['primary']} !important;
        background: transparent !important;
    }
    
    .btn-outline-primary:hover, .btn-outline-danger:hover {
        background: {$t['primary']} !important;
        color: #fff !important;
    }
    
    /* ========== FORMS ========== */
    .form-control, .form-select, input, textarea, select {
        background: {$t['bg_input']} !important;
        border-color: {$t['border']} !important;
        color: {$t['text_primary']} !important;
    }
    
    .form-control:focus, .form-select:focus,
    input:focus, textarea:focus, select:focus {
        border-color: {$t['primary']} !important;
        box-shadow: 0 0 0 3px {$t['shadow']} !important;
    }
    
    .form-control::placeholder, input::placeholder {
        color: {$t['text_muted']} !important;
    }
    
    /* ========== TEXT COLORS ========== */
    .text-primary { color: {$t['primary']} !important; }
    .text-danger { color: {$t['primary']} !important; }
    .text-secondary { color: {$t['text_secondary']} !important; }
    .text-muted { color: {$t['text_muted']} !important; }
    .text-white { color: {$t['text_primary']} !important; }
    
    h1, h2, h3, h4, h5, h6, .h1, .h2, .h3, .h4, .h5, .h6 {
        color: {$t['text_primary']} !important;
    }
    
    p, span, div, label {
        color: inherit;
    }
    
    /* ========== BADGES ========== */
    .badge-primary, .bg-primary, .badge.bg-danger, .bg-danger {
        background: {$t['gradient']} !important;
        color: #fff !important;
    }
    
    /* ========== BORDERS ========== */
    .border, .border-top, .border-bottom, .border-start, .border-end {
        border-color: {$t['border_light']} !important;
    }
    
    .border-primary, .border-danger {
        border-color: {$t['primary']} !important;
    }
    
    hr {
        border-color: {$t['border_light']} !important;
        opacity: 0.5;
    }
    
    /* ========== TABLES ========== */
    .table, table {
        color: {$t['text_primary']} !important;
    }
    
    .table th, .table td, table th, table td {
        border-color: {$t['border_light']} !important;
    }
    
    .table-dark, .table-striped > tbody > tr:nth-of-type(odd) {
        background: {$t['bg_card']} !important;
    }
    
    /* ========== ALERTS ========== */
    .alert-danger, .alert-primary {
        background: {$t['shadow']} !important;
        border-color: {$t['border']} !important;
        color: {$t['text_primary']} !important;
    }
    
    /* ========== MODALS ========== */
    .modal-content {
        background: {$t['bg_card']} !important;
        border-color: {$t['border']} !important;
    }
    
    .modal-header, .modal-footer {
        border-color: {$t['border_light']} !important;
    }
    
    /* ========== PROGRESS BARS ========== */
    .progress {
        background: {$t['bg_input']} !important;
    }
    
    .progress-bar {
        background: {$t['gradient']} !important;
    }
    
    /* ========== SPECIAL ELEMENTS ========== */
    .avatar, .user-avatar, .friend-item .avatar,
    .notification-badge, .step-number, .commission-badge {
        background: {$t['gradient']} !important;
    }
    
    .referral-code, .server-name, .price {
        color: {$t['primary']} !important;
    }
    
    .referral-hero, .hero-section {
        background: linear-gradient(135deg, {$t['shadow']}, {$t['bg_body']}) !important;
        border-color: {$t['border']} !important;
    }
    
    /* ========== SCROLLBAR ========== */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    
    ::-webkit-scrollbar-track {
        background: {$t['bg_body']};
    }
    
    ::-webkit-scrollbar-thumb {
        background: {$t['primary']};
        border-radius: 4px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
        background: {$t['accent']};
    }
    
    /* ========== SELECTION ========== */
    ::selection {
        background: {$t['primary']} !important;
        color: #fff !important;
    }
    
    /* ========== LINKS ========== */
    a {
        color: {$t['accent']};
    }
    
    a:hover {
        color: {$t['primary']};
    }
    
    /* ========== TAB & NAV-TABS ========== */
    .nav-tabs {
        border-color: {$t['border']} !important;
    }
    
    .nav-tabs .nav-link {
        color: {$t['text_secondary']} !important;
    }
    
    .nav-tabs .nav-link.active {
        background: transparent !important;
        color: {$t['primary']} !important;
        border-bottom-color: {$t['primary']} !important;
    }
    
    /* ========== DROPDOWN ========== */
    .dropdown-item {
        color: {$t['text_secondary']} !important;
    }
    
    .dropdown-item:hover, .dropdown-item:focus {
        background: {$t['bg_card_hover']} !important;
        color: {$t['primary']} !important;
    }
    
    /* ========== LIST GROUP ========== */
    .list-group-item {
        background: {$t['bg_card']} !important;
        border-color: {$t['border_light']} !important;
        color: {$t['text_primary']} !important;
    }
    
    /* ========== ICONS ========== */
    .icon.text-danger, .fa.text-danger, i.text-danger,
    .stat-card .icon {
        color: {$t['primary']} !important;
    }
";
    
    return $css;
}
