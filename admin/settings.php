<?php
// admin/settings.php
// System Settings - Full Featured

ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../controller/admin_controller/admin_config.php';
checkAdminAuth();
require_once __DIR__ . '/../controller/theme_helper.php';

// =============================================
// Helper: Get/Set System Settings
// =============================================
function getSetting($conn, $key, $default = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['setting_value'];
    }
    $stmt->close();
    return $default;
}

function saveSetting($conn, $key, $value) {
    // ลบค่าเก่าทั้งหมด (ป้องกัน duplicate)
    $conn->query("DELETE FROM system_settings WHERE setting_key = '" . $conn->real_escape_string($key) . "'");
    
    // Insert ค่าใหม่
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->bind_param("ss", $key, $value);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_general') {
        // บันทึกทุกค่า
        saveSetting($conn, 'site_name', $_POST['site_name'] ?? 'NF~SHOP');
        saveSetting($conn, 'contact_email', $_POST['contact_email'] ?? '');
        saveSetting($conn, 'allow_register', $_POST['allow_register'] ?? '1');
        saveSetting($conn, 'maintenance_mode', $_POST['maintenance_mode'] ?? '0');
        saveSetting($conn, 'announcement', $_POST['announcement'] ?? '');
        saveSetting($conn, 'ai_active', $_POST['ai_active'] ?? '1');
        
        // บันทึกธีม
        $theme = $_POST['site_theme'] ?? 'red';
        saveSiteTheme($conn, $theme);
        
        // บันทึกสี custom
        saveSetting($conn, 'use_custom_colors', $_POST['use_custom_colors'] ?? '0');
        saveSetting($conn, 'custom_bg_body', $_POST['custom_bg_body'] ?? '');
        saveSetting($conn, 'custom_bg_card', $_POST['custom_bg_card'] ?? '');
        saveSetting($conn, 'custom_primary_color', $_POST['custom_primary_color'] ?? '');
        
        $message = 'บันทึกการตั้งค่าเรียบร้อย';
        $message_type = 'success';
    }
    
    if ($action === 'clear_logs') {
        $log_files = glob(__DIR__ . '/../logs/*.log');
        foreach ($log_files as $file) {
            file_put_contents($file, '');
        }
        $message = 'ล้าง Log files เรียบร้อย';
        $message_type = 'success';
    }
    
    if ($action === 'clear_cache') {
        $message = 'ล้าง Cache เรียบร้อย';
        $message_type = 'success';
    }
}

// Get current settings
$site_name = getSetting($conn, 'site_name', defined('SITE_NAME') ? SITE_NAME : 'NF~SHOP');
$contact_email = getSetting($conn, 'contact_email', '');
$allow_register = getSetting($conn, 'allow_register', '1');
$maintenance_mode = getSetting($conn, 'maintenance_mode', '0');
$announcement = getSetting($conn, 'announcement', '');
$ai_active = getSetting($conn, 'ai_active', '1');

// Get current stats (ป้องกัน error ถ้าตารางหรือ column ไม่มี)
$total_users = 0;
$total_vpn = 0;
$total_ssh = 0;
$db_size = 0;

try {
    $result = $conn->query("SELECT COUNT(*) as c FROM users");
    if ($result) $total_users = $result->fetch_assoc()['c'];
} catch (Exception $e) {}

try {
    // VPN Rentals - มี deleted_at
    $result = $conn->query("SELECT COUNT(*) as c FROM user_rentals WHERE deleted_at IS NULL");
    if ($result) $total_vpn = $result->fetch_assoc()['c'];
} catch (Exception $e) {}

try {
    // SSH Rentals - อาจไม่มี deleted_at
    $result = $conn->query("SELECT COUNT(*) as c FROM ssh_rentals");
    if ($result) $total_ssh = $result->fetch_assoc()['c'];
} catch (Exception $e) {}

try {
    $result = $conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size FROM information_schema.tables WHERE table_schema = DATABASE()");
    if ($result) $db_size = $result->fetch_assoc()['size'] ?? 0;
} catch (Exception $e) {}

// Log files
$log_files = glob(__DIR__ . '/../logs/*.log');
$total_log_size = 0;
foreach ($log_files as $file) {
    $total_log_size += filesize($file);
}
$total_log_size = round($total_log_size / 1024, 2);

$current_theme = getSiteTheme($conn);
$site_themes = $GLOBALS['SITE_THEMES'];

// Custom colors
$custom_bg_body = getSetting($conn, 'custom_bg_body', '');
$custom_bg_card = getSetting($conn, 'custom_bg_card', '');
$custom_primary_color = getSetting($conn, 'custom_primary_color', '');
$use_custom_colors = getSetting($conn, 'use_custom_colors', '0');
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าระบบ - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* ===== CSS Variables ===== */
        :root {
            --bg-body: #000000;
            --card-bg: rgba(10, 10, 10, 0.8);
            --border-color: rgba(229, 9, 20, 0.2);
            --accent: #E50914;
            --accent-glow: rgba(229, 9, 20, 0.3);
        }

        body { 
            background: var(--bg-body); 
            color: #fff; 
            font-family: 'Segoe UI', sans-serif;
        }

        /* ===== Simple Fade Animation ===== */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ===== Card Styles ===== */
        .card-custom {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            animation: fadeIn 0.4s ease-out;
            transition: all 0.3s ease;
        }

        .card-custom:hover {
            border-color: rgba(229, 9, 20, 0.4);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }

        .card-title {
            color: var(--accent);
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }

        /* ===== Form Controls ===== */
        .form-control, .form-select {
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border-color);
            color: #fff;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            background: rgba(255,255,255,0.08);
            border-color: var(--accent);
            color: #fff;
            box-shadow: 0 0 0 0.2rem rgba(229, 9, 20, 0.2);
        }

        .form-select option { background: #1a1a1a; }
        .form-label { color: #aaa; font-size: 0.9rem; }

        /* ===== Info Box ===== */
        .info-box {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.2s ease;
        }

        .info-box:hover {
            border-color: rgba(59, 130, 246, 0.5);
            background: rgba(59, 130, 246, 0.15);
        }

        .info-box .label { color: #93c5fd; font-size: 0.85rem; }
        .info-box .value { 
            color: #fff; 
            font-size: 1.2rem; 
            font-weight: bold;
        }

        /* ===== Danger Zone ===== */
        .danger-zone {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* ===== Navigation ===== */
        .nav-pills .nav-link {
            color: #aaa;
            border-radius: 10px;
            margin-bottom: 5px;
            transition: all 0.2s ease;
        }

        .nav-pills .nav-link:hover { 
            color: #fff; 
            background: rgba(255,255,255,0.05);
            padding-left: 20px;
        }

        .nav-pills .nav-link.active {
            background: var(--accent);
            color: #fff;
        }

        /* ===== Theme Swatch ===== */
        .theme-swatch { 
            width: 40px; height: 40px; 
            border-radius: 50%; 
            cursor: pointer; 
            border: 3px solid transparent; 
            transition: all 0.2s ease;
        }

        .theme-swatch:hover { 
            transform: scale(1.1); 
            box-shadow: 0 4px 15px rgba(229, 9, 20, 0.3);
        }

        .theme-swatch.active { 
            border-color: #fff; 
            box-shadow: 0 0 0 2px var(--accent);
        }
        
        /* ===== Toggle Switch ===== */
        .form-switch .form-check-input {
            width: 50px;
            height: 26px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .form-switch .form-check-input:checked {
            background-color: var(--accent);
            border-color: var(--accent);
        }

        /* ===== Backup Item ===== */
        .backup-item {
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
        }

        .backup-item:hover {
            background: rgba(255,255,255,0.08);
        }

        /* ===== Button Styles ===== */
        .btn {
            transition: all 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        /* ===== Alert ===== */
        .alert {
            animation: fadeIn 0.3s ease-out;
            border: 1px solid var(--border-color);
        }

        /* ===== Modal ===== */
        .modal-content {
            animation: fadeIn 0.2s ease-out;
        }

        /* ===== Scrollbar ===== */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #ff1a25;
        }

        /* ===== Hover Glow ===== */
        .hover-glow {
            transition: all 0.2s ease;
        }

        .hover-glow:hover {
            box-shadow: 0 0 15px var(--accent-glow);
            border-color: var(--accent);
        }

        /* ===== Subtle Background ===== */
        .bg-subtle {
            background: 
                radial-gradient(circle at 20% 80%, rgba(229, 9, 20, 0.02) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(229, 9, 20, 0.02) 0%, transparent 50%);
        }
    </style>
    <?php include __DIR__ . '/../include/theme_head.php'; ?>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="bg-subtle">
        <div class="container py-4">
            <div class="mb-4">
                <h3 class="mb-0">
                    <i class="fas fa-cog me-2"></i>ตั้งค่าระบบ
                </h3>
                <small class="text-secondary">
                    <i class="fas fa-sliders-h me-1"></i>System Settings
                </small>
            </div>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-3">
                <div class="card-custom">
                    <nav class="nav nav-pills flex-column">
                        <a class="nav-link active" data-bs-toggle="pill" href="#tab-general"><i class="fas fa-home me-2"></i>ทั่วไป</a>
                        <a class="nav-link" data-bs-toggle="pill" href="#tab-ai"><i class="fas fa-robot me-2"></i>AI Settings</a>
                        <a class="nav-link" data-bs-toggle="pill" href="#tab-system"><i class="fas fa-server me-2"></i>ข้อมูลระบบ</a>
                        <a class="nav-link" data-bs-toggle="pill" href="#tab-maintenance"><i class="fas fa-tools me-2"></i>การบำรุงรักษา</a>
                        <a class="nav-link" data-bs-toggle="pill" href="#tab-backup"><i class="fas fa-database me-2"></i>Backup</a>
                        <a class="nav-link" data-bs-toggle="pill" href="#tab-logs"><i class="fas fa-file-alt me-2"></i>Log Files</a>
                    </nav>
                </div>
            </div>

            <div class="col-md-9">
                <div class="tab-content">
                    <!-- General Settings -->
                    <div class="tab-pane fade show active" id="tab-general">
                        <div class="card-custom">
                            <h5 class="card-title"><i class="fas fa-globe me-2"></i>ตั้งค่าทั่วไป</h5>
                            <form method="POST">
                                <input type="hidden" name="action" value="save_general">
                                <input type="hidden" name="ai_active" value="<?php echo $ai_active; ?>">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">ชื่อเว็บไซต์</label>
                                        <input type="text" class="form-control" name="site_name" value="<?php echo htmlspecialchars($site_name); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">อีเมลติดต่อ</label>
                                        <input type="email" class="form-control" name="contact_email" value="<?php echo htmlspecialchars($contact_email); ?>" placeholder="admin@example.com">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">เปิด/ปิดการลงทะเบียน</label>
                                        <select class="form-select" name="allow_register">
                                            <option value="1" <?php echo $allow_register == '1' ? 'selected' : ''; ?>>เปิดรับสมัคร</option>
                                            <option value="0" <?php echo $allow_register == '0' ? 'selected' : ''; ?>>ปิดรับสมัคร</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">โหมดบำรุงรักษา</label>
                                        <select class="form-select" name="maintenance_mode">
                                            <option value="0" <?php echo $maintenance_mode == '0' ? 'selected' : ''; ?>>ปิด</option>
                                            <option value="1" <?php echo $maintenance_mode == '1' ? 'selected' : ''; ?>>เปิด (ผู้ใช้จะเห็นหน้า Maintenance)</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">ประกาศหน้าเว็บ</label>
                                        <textarea class="form-control" name="announcement" rows="3" placeholder="ข้อความประกาศ (จะแสดงบนหน้าเว็บ)"><?php echo htmlspecialchars($announcement); ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label mb-3">สีธีมเว็บไซต์</label>
                                        <div class="d-flex flex-wrap gap-3 align-items-center">
                                            <?php foreach ($site_themes as $key => $theme_data): ?>
                                            <div class="text-center">
                                                <input type="radio" name="site_theme" value="<?php echo $key; ?>" id="theme_<?php echo $key; ?>" 
                                                       <?php echo $current_theme === $key ? 'checked' : ''; ?> style="display: none;">
                                                <label for="theme_<?php echo $key; ?>" class="theme-swatch <?php echo $current_theme === $key ? 'active' : ''; ?>" 
                                                       style="background: <?php echo $theme_data['gradient']; ?>;" 
                                                       title="<?php echo $theme_data['name']; ?>">
                                                </label>
                                                <div class="small text-secondary mt-1"><?php echo $theme_data['name']; ?></div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Custom Colors Section -->
                                    <div class="col-12">
                                        <hr class="border-secondary my-4">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <label class="form-label mb-0">
                                                <i class="fas fa-palette me-2 text-info"></i>กำหนดสีเอง (Custom Colors)
                                            </label>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="useCustomColors" name="use_custom_colors" value="1" <?php echo $use_custom_colors == '1' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="useCustomColors">เปิดใช้งาน</label>
                                            </div>
                                        </div>
                                        
                                        <div id="customColorsPanel" class="p-3 rounded mb-3" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); <?php echo $use_custom_colors != '1' ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label class="form-label small">สีพื้นหลังเว็บ</label>
                                                    <div class="input-group">
                                                        <input type="color" class="form-control form-control-color" id="customBgBody" name="custom_bg_body" 
                                                               value="<?php echo $custom_bg_body ?: '#000000'; ?>" style="width: 50px; height: 40px;">
                                                        <input type="text" class="form-control" id="customBgBodyText" 
                                                               value="<?php echo $custom_bg_body ?: '#000000'; ?>" placeholder="#000000">
                                                    </div>
                                                    <small class="text-muted">Background ของทั้งเว็บ</small>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small">สีกล่อง/Card</label>
                                                    <div class="input-group">
                                                        <input type="color" class="form-control form-control-color" id="customBgCard" name="custom_bg_card" 
                                                               value="<?php echo $custom_bg_card ? preg_replace('/rgba?\([^)]+\)/', '#1a1a1a', $custom_bg_card) : '#1a1a1a'; ?>" style="width: 50px; height: 40px;">
                                                        <input type="text" class="form-control" id="customBgCardText" 
                                                               value="<?php echo $custom_bg_card ?: 'rgba(20,20,20,0.95)'; ?>" placeholder="rgba(20,20,20,0.95)">
                                                    </div>
                                                    <small class="text-muted">พื้นหลัง card, box, navbar</small>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small">สีหลัก (Primary)</label>
                                                    <div class="input-group">
                                                        <input type="color" class="form-control form-control-color" id="customPrimary" name="custom_primary_color" 
                                                               value="<?php echo $custom_primary_color ?: '#E50914'; ?>" style="width: 50px; height: 40px;">
                                                        <input type="text" class="form-control" id="customPrimaryText" 
                                                               value="<?php echo $custom_primary_color ?: '#E50914'; ?>" placeholder="#E50914">
                                                    </div>
                                                    <small class="text-muted">ปุ่ม, link, accent</small>
                                                </div>
                                            </div>
                                            
                                            <!-- Preview Box -->
                                            <div class="mt-3 p-3 rounded" id="colorPreview" style="background: <?php echo $custom_bg_card ?: 'rgba(20,20,20,0.95)'; ?>; border: 1px solid <?php echo $custom_primary_color ?: '#E50914'; ?>;">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span>ตัวอย่าง Preview</span>
                                                    <button type="button" class="btn btn-sm" id="previewBtn" style="background: <?php echo $custom_primary_color ?: '#E50914'; ?>; color: #fff;">ปุ่มตัวอย่าง</button>
                                                </div>
                                            </div>
                                        </div>
                                        <small class="text-secondary">
                                            <i class="fas fa-info-circle me-1"></i>
                                            เมื่อเปิดใช้ Custom Colors จะ override สีจากธีมที่เลือกด้านบน
                                        </small>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>บันทึกการตั้งค่า
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- AI Settings -->
                    <div class="tab-pane fade" id="tab-ai">
                        <div class="card-custom">
                            <h5 class="card-title"><i class="fas fa-robot me-2"></i>AI Chat Settings</h5>
                            <div class="row g-4">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center p-3 rounded" style="background: rgba(255,255,255,0.05);">
                                        <div>
                                            <h6 class="mb-1">
                                                <i class="fas fa-comment-dots me-2 text-info"></i>
                                                AI ตอบกลับอัตโนมัติ
                                            </h6>
                                            <small class="text-secondary">เปิดให้ AI ตอบคำถามลูกค้าอัตโนมัติในแชท</small>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="aiToggle" <?php echo $ai_active == '1' ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="alert alert-info mb-0">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>AI Fallback:</strong> ถ้า Typhoon API ไม่ตอบ ระบบจะใช้ Fallback Response อัตโนมัติ<br>
                                        <small class="text-muted">ดู Log ได้ที่ <code>logs/ai_debug.log</code> และ <code>logs/chat_debug.log</code></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-box">
                                        <div class="label">API Provider</div>
                                        <div class="value">Typhoon AI</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-box">
                                        <div class="label">Model</div>
                                        <div class="value">typhoon-v2.1-12b-instruct</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Info -->
                    <div class="tab-pane fade" id="tab-system">
                        <div class="card-custom">
                            <h5 class="card-title"><i class="fas fa-info-circle me-2"></i>ข้อมูลระบบ</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="info-box">
                                        <div class="label">PHP Version</div>
                                        <div class="value"><?php echo phpversion(); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-box">
                                        <div class="label">MySQL Version</div>
                                        <div class="value"><?php echo $conn->server_info; ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-box">
                                        <div class="label">สมาชิกทั้งหมด</div>
                                        <div class="value"><?php echo number_format($total_users); ?> คน</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-box">
                                        <div class="label">รายการเช่าทั้งหมด</div>
                                        <div class="value"><?php echo number_format($total_vpn + $total_ssh); ?> รายการ</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-box">
                                        <div class="label">ขนาด Database</div>
                                        <div class="value"><?php echo $db_size; ?> MB</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-box">
                                        <div class="label">Server Time</div>
                                        <div class="value"><?php echo date('Y-m-d H:i:s'); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-box">
                                        <div class="label">cURL Extension</div>
                                        <div class="value"><?php echo function_exists('curl_init') ? '<span class="text-success">Enabled</span>' : '<span class="text-danger">Disabled</span>'; ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-box">
                                        <div class="label">Upload Max Size</div>
                                        <div class="value"><?php echo ini_get('upload_max_filesize'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Maintenance -->
                    <div class="tab-pane fade" id="tab-maintenance">
                        <div class="card-custom">
                            <h5 class="card-title"><i class="fas fa-broom me-2"></i>การบำรุงรักษา</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card bg-dark border-secondary p-3">
                                        <h6><i class="fas fa-trash me-2 text-warning"></i>ล้าง Log Files</h6>
                                        <p class="small text-secondary mb-2">ขนาด Log ปัจจุบัน: <?php echo $total_log_size; ?> KB</p>
                                        <button type="button" class="btn btn-warning btn-sm" onclick="clearLogs()">
                                            <i class="fas fa-eraser me-1"></i>ล้าง Logs
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-dark border-secondary p-3">
                                        <h6><i class="fas fa-sync me-2 text-info"></i>ล้าง Cache</h6>
                                        <p class="small text-secondary mb-2">ล้าง cache เพื่อให้ระบบทำงานเร็วขึ้น</p>
                                        <button type="button" class="btn btn-info btn-sm" onclick="clearCache()">
                                            <i class="fas fa-broom me-1"></i>ล้าง Cache
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-custom danger-zone">
                            <h5 class="card-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card bg-dark border-danger p-3">
                                        <h6 class="text-danger"><i class="fas fa-user-times me-2"></i>ลบ Expired Rentals</h6>
                                        <p class="small text-secondary mb-2">ลบรายการเช่าที่หมดอายุเกิน X วัน</p>
                                        <div class="input-group input-group-sm mb-2">
                                            <input type="number" class="form-control" id="cleanupDays" value="30" min="7" max="365">
                                            <span class="input-group-text bg-dark text-white border-secondary">วัน</span>
                                        </div>
                                        <button class="btn btn-outline-danger btn-sm" onclick="cleanupExpired()">
                                            <i class="fas fa-trash me-1"></i>Cleanup
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-dark border-danger p-3">
                                        <h6 class="text-danger"><i class="fas fa-database me-2"></i>Fix Duplicate Settings</h6>
                                        <p class="small text-secondary mb-2">ลบค่าซ้ำใน system_settings</p>
                                        <button class="btn btn-outline-danger btn-sm" onclick="fixDuplicates()">
                                            <i class="fas fa-wrench me-1"></i>Fix Now
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Backup -->
                    <div class="tab-pane fade" id="tab-backup">
                        <div class="card-custom">
                            <h5 class="card-title"><i class="fas fa-database me-2"></i>สำรองข้อมูล</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card bg-dark border-success p-3">
                                        <h6 class="text-success"><i class="fas fa-download me-2"></i>สร้าง Backup ใหม่</h6>
                                        <p class="small text-secondary mb-2">สำรองฐานข้อมูลทั้งหมดเป็นไฟล์ .sql</p>
                                        <button class="btn btn-success btn-sm" onclick="createBackup()" id="backupBtn">
                                            <i class="fas fa-download me-1"></i>สร้าง Backup
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-box">
                                        <div class="label">ขนาด Database ปัจจุบัน</div>
                                        <div class="value"><?php echo $db_size; ?> MB</div>
                                    </div>
                                </div>
                            </div>
                            
                            <h6 class="mt-4 mb-3"><i class="fas fa-history me-2"></i>Backup ล่าสุด</h6>
                            <div id="backupList">
                                <div class="text-center text-secondary py-3">
                                    <i class="fas fa-spinner fa-spin me-2"></i>กำลังโหลด...
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Logs -->
                    <div class="tab-pane fade" id="tab-logs">
                        <div class="card-custom">
                            <h5 class="card-title"><i class="fas fa-file-code me-2"></i>Log Files</h5>
                            <div class="table-responsive">
                                <table class="table table-dark table-sm">
                                    <thead>
                                        <tr>
                                            <th>ไฟล์</th>
                                            <th>ขนาด</th>
                                            <th>แก้ไขล่าสุด</th>
                                            <th>ดู</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($log_files as $file): ?>
                                        <tr>
                                            <td><i class="fas fa-file-alt me-2 text-secondary"></i><?php echo basename($file); ?></td>
                                            <td><?php echo round(filesize($file) / 1024, 2); ?> KB</td>
                                            <td><?php echo date('Y-m-d H:i:s', filemtime($file)); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info" onclick="viewLog('<?php echo basename($file); ?>')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($log_files)): ?>
                                        <tr><td colspan="4" class="text-center text-secondary">ไม่พบ Log files</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Viewer Modal -->
    <div class="modal fade" id="logModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content bg-dark text-white border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="fas fa-file-alt me-2"></i>Log Viewer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <pre id="logContent" class="bg-black p-3 rounded" style="max-height: 500px; overflow-y: auto; font-size: 0.8rem; white-space: pre-wrap;"></pre>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php
        $script_dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        if ($script_dir === '' || $script_dir === '\\') $script_dir = '';
        $settings_api = $script_dir . '/controller/admin_controller/settings_controller.php';
        ?>
        const API_BASE = '<?php echo $settings_api; ?>';

        function showError(msg) {
            if (typeof Swal !== 'undefined') Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: msg });
            else alert(msg);
        }

        // View Log
        function viewLog(filename) {
            document.getElementById('logContent').textContent = 'กำลังโหลด...';
            new bootstrap.Modal(document.getElementById('logModal')).show();
            fetch(API_BASE + '?action=view_log&file=' + encodeURIComponent(filename))
                .then(r => r.text())
                .then(data => { document.getElementById('logContent').textContent = data || '(ว่าง)'; })
                .catch(() => { document.getElementById('logContent').textContent = 'โหลดไม่สำเร็จ'; });
        }

        // Theme Swatch Selection
        document.querySelectorAll('.theme-swatch').forEach(swatch => {
            swatch.addEventListener('click', function() {
                document.querySelectorAll('.theme-swatch').forEach(s => s.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Clear Logs (AJAX)
        function clearLogs() {
            Swal.fire({
                title: 'ยืนยันล้าง Log Files?',
                text: 'เนื้อหาในไฟล์ .log ทั้งหมดจะถูกล้าง',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ล้างเลย',
                cancelButtonText: 'ยกเลิก'
            }).then(result => {
                if (result.isConfirmed) {
                    fetch(API_BASE, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=clear_logs'
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({ icon: 'success', title: 'สำเร็จ', text: data.message, timer: 2000, showConfirmButton: false });
                            setTimeout(() => location.reload(), 1500);
                        } else showError(data.message || 'ล้างไม่สำเร็จ');
                    })
                    .catch(() => showError('เชื่อมต่อ API ไม่ได้'));
                }
            });
        }

        // AI Toggle
        document.getElementById('aiToggle')?.addEventListener('change', function() {
            const enabled = this.checked ? '1' : '0';
            fetch(API_BASE, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=toggle_ai&enabled=' + enabled
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const inp = document.querySelector('input[name="ai_active"]');
                    if (inp) inp.value = enabled;
                    Swal.fire({ icon: 'success', title: enabled == '1' ? 'เปิด AI แล้ว' : 'ปิด AI แล้ว', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
                } else showError(data.message || 'บันทึกไม่สำเร็จ');
            })
            .catch(() => showError('เชื่อมต่อ API ไม่ได้'));
        });

        // Create Backup
        function createBackup() {
            const btn = document.getElementById('backupBtn');
            if (!btn) return;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>กำลังสำรอง...';
            fetch(API_BASE, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=backup_db'
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-download me-1"></i>สร้าง Backup';
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำรองข้อมูลเรียบร้อย',
                        html: `ไฟล์: <code>${data.filename}</code><br>ขนาด: ${data.size}`,
                        confirmButtonText: 'ดาวน์โหลด',
                        showCancelButton: true,
                        cancelButtonText: 'ปิด'
                    }).then(result => {
                        if (result.isConfirmed)
                            window.location.href = API_BASE + '?action=download_backup&file=' + encodeURIComponent(data.filename);
                        loadBackups();
                    });
                } else showError(data.message || 'สร้าง Backup ไม่สำเร็จ');
            })
            .catch(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-download me-1"></i>สร้าง Backup'; showError('เชื่อมต่อ API ไม่ได้'); });
        }

        // Load Backup List
        function loadBackups() {
            const container = document.getElementById('backupList');
            if (!container) return;
            fetch(API_BASE + '?action=list_backups')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.backups && data.backups.length > 0) {
                        container.innerHTML = data.backups.map(b => `
                            <div class="backup-item">
                                <div>
                                    <i class="fas fa-file-archive me-2 text-success"></i>
                                    <strong>${escapeHtml(b.filename)}</strong>
                                    <span class="text-secondary ms-2">${escapeHtml(b.size)}</span>
                                    <small class="text-secondary d-block">${escapeHtml(b.date)}</small>
                                </div>
                                <a href="${API_BASE}?action=download_backup&file=${encodeURIComponent(b.filename)}" class="btn btn-sm btn-outline-success"><i class="fas fa-download"></i></a>
                            </div>
                        `).join('');
                    } else {
                        container.innerHTML = '<div class="text-center text-secondary py-3">ยังไม่มี Backup</div>';
                    }
                })
                .catch(() => { container.innerHTML = '<div class="text-center text-danger py-3">โหลดรายการไม่สำเร็จ</div>'; });
        }
        function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

        // Cleanup Expired
        function cleanupExpired() {
            const daysEl = document.getElementById('cleanupDays');
            const days = daysEl ? Math.max(7, Math.min(365, parseInt(daysEl.value, 10) || 30)) : 30;
            Swal.fire({
                title: 'ยืนยันการลบ?',
                html: `จะลบรายการเช่าที่หมดอายุเกิน <strong>${days} วัน</strong>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'ลบเลย',
                cancelButtonText: 'ยกเลิก'
            }).then(result => {
                if (result.isConfirmed) {
                    fetch(API_BASE, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=cleanup_expired&days=' + days
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success)
                            Swal.fire('ลบเรียบร้อย', `VPN: ${data.vpn_deleted} รายการ, SSH: ${data.ssh_deleted} รายการ`, 'success');
                        else showError(data.message || 'ลบไม่สำเร็จ');
                    })
                    .catch(() => showError('เชื่อมต่อ API ไม่ได้'));
                }
            });
        }

        // Clear Cache
        function clearCache() {
            Swal.fire({
                title: 'ล้าง Cache?',
                text: 'ระบบจะล้างไฟล์ cache ทั้งหมด',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'ล้างเลย',
                cancelButtonText: 'ยกเลิก'
            }).then(result => {
                if (result.isConfirmed) {
                    fetch(API_BASE, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=clear_cache'
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'สำเร็จ',
                                text: data.message,
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    });
                }
            });
        }

        // Fix Duplicate Settings
        function fixDuplicates() {
            Swal.fire({
                title: 'Fix Duplicate Settings?',
                text: 'จะลบค่าซ้ำใน system_settings และเก็บค่าไว้เพียงรายการเดียวต่อ key',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Fix Now',
                cancelButtonText: 'ยกเลิก'
            }).then(result => {
                if (result.isConfirmed) {
                    fetch(API_BASE, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=fix_duplicates'
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success)
                            Swal.fire({ icon: 'success', title: 'สำเร็จ', html: (data.message || '') + '<br>พบ ' + (data.duplicates_found || 0) + ' รายการซ้ำ, แก้ไขแล้ว ' + (data.fixed || 0) + ' รายการ', timer: 3000, showConfirmButton: false });
                        else showError(data.message || 'แก้ไขไม่สำเร็จ');
                    })
                    .catch(() => showError('เชื่อมต่อ API ไม่ได้'));
                }
            });
        }

        // Load backups on tab show
        document.querySelector('a[href="#tab-backup"]').addEventListener('shown.bs.tab', loadBackups);
        
        // Initial load if backup tab is visible
        if (window.location.hash === '#tab-backup') {
            loadBackups();
        }
        
        // =============================================
        // Tab Transition - Simple Fade
        // =============================================
        document.querySelectorAll('[data-bs-toggle="pill"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function (e) {
                const target = document.querySelector(e.target.getAttribute('href'));
                if (target) {
                    target.style.opacity = '0';
                    target.style.transition = 'opacity 0.3s ease';
                    setTimeout(() => {
                        target.style.opacity = '1';
                    }, 10);
                }
            });
        });
        
        // =============================================
        // Simple Form Focus Effect
        // =============================================
        document.querySelectorAll('.form-control, .form-select').forEach(input => {
            input.addEventListener('focus', function() {
                this.style.transition = 'all 0.2s ease';
            });
        });
        
        // =============================================
        // Theme Swatch Selection
        // =============================================
        document.querySelectorAll('.theme-swatch').forEach(swatch => {
            swatch.addEventListener('click', function() {
                document.querySelectorAll('.theme-swatch').forEach(s => {
                    s.classList.remove('active');
                });
                this.classList.add('active');
            });
        });
        
        // =============================================
        // Custom Colors Panel
        // =============================================
        const useCustomColors = document.getElementById('useCustomColors');
        const customColorsPanel = document.getElementById('customColorsPanel');
        
        // Toggle custom colors panel
        useCustomColors?.addEventListener('change', function() {
            if (this.checked) {
                customColorsPanel.style.opacity = '1';
                customColorsPanel.style.pointerEvents = 'auto';
            } else {
                customColorsPanel.style.opacity = '0.5';
                customColorsPanel.style.pointerEvents = 'none';
            }
        });
        
        // Sync color picker with text input
        function syncColorInputs(colorId, textId) {
            const colorInput = document.getElementById(colorId);
            const textInput = document.getElementById(textId);
            
            colorInput?.addEventListener('input', function() {
                textInput.value = this.value;
                updatePreview();
            });
            
            textInput?.addEventListener('input', function() {
                // Only update color picker if valid hex
                if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                    colorInput.value = this.value;
                }
                updatePreview();
            });
        }
        
        syncColorInputs('customBgBody', 'customBgBodyText');
        syncColorInputs('customBgCard', 'customBgCardText');
        syncColorInputs('customPrimary', 'customPrimaryText');
        
        // Update preview box
        function updatePreview() {
            const bgCard = document.getElementById('customBgCardText').value || 'rgba(20,20,20,0.95)';
            const primary = document.getElementById('customPrimaryText').value || '#E50914';
            const preview = document.getElementById('colorPreview');
            const previewBtn = document.getElementById('previewBtn');
            
            if (preview) {
                preview.style.background = bgCard;
                preview.style.borderColor = primary;
            }
            if (previewBtn) {
                previewBtn.style.background = primary;
            }
        }
        
        // Initialize preview on load
        updatePreview();
        
        // =============================================
        // Simple Info Box Hover
        // =============================================
        document.querySelectorAll('.info-box').forEach(box => {
            box.addEventListener('mouseenter', function() {
                this.style.borderColor = 'var(--accent)';
            });
            box.addEventListener('mouseleave', function() {
                this.style.borderColor = '';
            });
        });
        
        // =============================================
        // Keyboard Shortcuts
        // =============================================
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                const saveBtn = document.querySelector('button[type="submit"]');
                if (saveBtn) saveBtn.click();
            }
        });
    </script>
    </div>
</body>
</html>
