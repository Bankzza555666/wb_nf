<?php
/**
 * Settings Controller - Admin
 * รองรับ: view_log, backup_db, cleanup_expired, get_setting, save_setting
 */

session_start();
require_once 'admin_config.php';
checkAdminAuth();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// =============================================
// ACTION: View Log File
// =============================================
if ($action === 'view_log') {
    header('Content-Type: text/plain; charset=utf-8');
    $file = basename($_GET['file'] ?? '');
    $log_path = __DIR__ . '/../../logs/' . $file;
    
    if (file_exists($log_path) && pathinfo($log_path, PATHINFO_EXTENSION) === 'log') {
        $lines = file($log_path);
        $last_lines = array_slice($lines, -500);
        echo implode('', $last_lines);
    } else {
        echo 'File not found or invalid';
    }
    exit;
}

// ทุก action ส่ง JSON ยกเว้น view_log และ download_backup ที่ override ด้านล่าง
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// =============================================
// ACTION: Get Setting Value
// =============================================
if ($action === 'get_setting') {
    $key = $_GET['key'] ?? '';
    if (empty($key)) {
        echo json_encode(['success' => false, 'message' => 'Missing key']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'value' => $row['setting_value']]);
    } else {
        echo json_encode(['success' => true, 'value' => null]);
    }
    $stmt->close();
    exit;
}

// =============================================
// ACTION: Get All Settings
// =============================================
if ($action === 'get_all_settings') {
    $result = $conn->query("SELECT setting_key, setting_value FROM system_settings");
    $settings = [];
    
    // ป้องกัน duplicate keys - เก็บค่าล่าสุด
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    echo json_encode(['success' => true, 'settings' => $settings]);
    exit;
}

// =============================================
// ACTION: Save Setting (Single)
// =============================================
if ($action === 'save_setting') {
    $key = $_POST['key'] ?? '';
    $value = $_POST['value'] ?? '';
    
    if (empty($key)) {
        echo json_encode(['success' => false, 'message' => 'Missing key']);
        exit;
    }
    
    // ลบค่าเก่าทั้งหมดก่อน (ป้องกัน duplicate)
    $conn->query("DELETE FROM system_settings WHERE setting_key = '" . $conn->real_escape_string($key) . "'");
    
    // Insert ค่าใหม่
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->bind_param("ss", $key, $value);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    $stmt->close();
    exit;
}

// =============================================
// ACTION: Save Multiple Settings
// =============================================
if ($action === 'save_settings') {
    $settings = $_POST['settings'] ?? [];
    
    if (empty($settings) || !is_array($settings)) {
        echo json_encode(['success' => false, 'message' => 'No settings provided']);
        exit;
    }
    
    $conn->begin_transaction();
    try {
        foreach ($settings as $key => $value) {
            // ลบค่าเก่า
            $conn->query("DELETE FROM system_settings WHERE setting_key = '" . $conn->real_escape_string($key) . "'");
            
            // Insert ค่าใหม่
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->bind_param("ss", $key, $value);
            $stmt->execute();
            $stmt->close();
        }
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'บันทึกการตั้งค่าเรียบร้อย']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// =============================================
// ACTION: Backup Database
// =============================================
if ($action === 'backup_db') {
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    $sql_dump = "-- Database Backup\n";
    $sql_dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql_dump .= "-- Database: " . DB_NAME . "\n\n";
    $sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    foreach ($tables as $table) {
        // CREATE TABLE
        $create = $conn->query("SHOW CREATE TABLE `$table`")->fetch_row();
        $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql_dump .= $create[1] . ";\n\n";
        
        // INSERT DATA
        $data = $conn->query("SELECT * FROM `$table`");
        while ($row = $data->fetch_assoc()) {
            $values = array_map(function($v) use ($conn) {
                if ($v === null) return 'NULL';
                return "'" . $conn->real_escape_string($v) . "'";
            }, array_values($row));
            
            $sql_dump .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
        }
        $sql_dump .= "\n";
    }
    
    $sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    // Save to file
    $backup_dir = __DIR__ . '/../../backups/';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    $filename = 'backup_' . date('Y-m-d_His') . '.sql';
    $filepath = $backup_dir . $filename;
    
    if (file_put_contents($filepath, $sql_dump)) {
        // Return download URL
        echo json_encode([
            'success' => true, 
            'message' => 'สำรองข้อมูลเรียบร้อย',
            'filename' => $filename,
            'size' => round(filesize($filepath) / 1024, 2) . ' KB'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถบันทึกไฟล์ได้']);
    }
    exit;
}

// =============================================
// ACTION: Download Backup
// =============================================
if ($action === 'download_backup') {
    $filename = basename($_GET['file'] ?? '');
    $filepath = __DIR__ . '/../../backups/' . $filename;
    
    if (file_exists($filepath) && pathinfo($filepath, PATHINFO_EXTENSION) === 'sql') {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
    } else {
        echo 'File not found';
    }
    exit;
}

// =============================================
// ACTION: List Backups
// =============================================
if ($action === 'list_backups') {
    $backup_dir = __DIR__ . '/../../backups/';
    $backups = [];
    
    if (is_dir($backup_dir)) {
        $files = glob($backup_dir . '*.sql');
        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'size' => round(filesize($file) / 1024, 2) . ' KB',
                'date' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
        // Sort by date desc
        usort($backups, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
    }
    
    echo json_encode(['success' => true, 'backups' => $backups]);
    exit;
}

// =============================================
// ACTION: Cleanup Expired Rentals
// =============================================
if ($action === 'cleanup_expired') {
    $days = max(7, min(365, intval($_POST['days'] ?? $_GET['days'] ?? 30)));
    
    $vpn_affected = 0;
    $ssh_affected = 0;
    
    // VPN Rentals - Soft delete (มี deleted_at)
    try {
        $vpn_query = "UPDATE user_rentals SET deleted_at = NOW() 
                      WHERE status = 'expired' 
                      AND expire_date < DATE_SUB(NOW(), INTERVAL ? DAY) 
                      AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
        $stmt = $conn->prepare($vpn_query);
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $vpn_affected = $stmt->affected_rows;
        $stmt->close();
    } catch (Exception $e) {
        // ตารางอาจไม่มี deleted_at
    }
    
    // SSH Rentals - ไม่มี deleted_at ใช้ status = 'deleted'
    try {
        $ssh_query = "UPDATE ssh_rentals SET status = 'deleted' 
                      WHERE status = 'expired' 
                      AND expire_date < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $conn->prepare($ssh_query);
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $ssh_affected = $stmt->affected_rows;
        $stmt->close();
    } catch (Exception $e) {
        // ไม่มี column หรือ enum
    }
    
    echo json_encode([
        'success' => true,
        'message' => "ลบรายการหมดอายุเรียบร้อย",
        'vpn_deleted' => $vpn_affected,
        'ssh_deleted' => $ssh_affected,
        'total' => $vpn_affected + $ssh_affected
    ]);
    exit;
}

// =============================================
// ACTION: Clear Logs
// =============================================
if ($action === 'clear_logs') {
    $log_files = glob(__DIR__ . '/../../logs/*.log');
    $cleared = 0;
    foreach ($log_files as $file) {
        file_put_contents($file, '');
        $cleared++;
    }
    echo json_encode(['success' => true, 'message' => "ล้าง $cleared log files เรียบร้อย"]);
    exit;
}

// =============================================
// ACTION: Clear Cache
// =============================================
if ($action === 'clear_cache') {
    $cache_dirs = [
        __DIR__ . '/../../cache/',
        __DIR__ . '/../../temp/',
        sys_get_temp_dir() . '/php_sessions/'
    ];
    
    $cleared = 0;
    $errors = [];
    
    foreach ($cache_dirs as $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '*');
            foreach ($files as $file) {
                if (is_file($file) && !in_array(basename($file), ['.htaccess', 'index.php'])) {
                    if (unlink($file)) {
                        $cleared++;
                    } else {
                        $errors[] = basename($file);
                    }
                }
            }
        }
    }
    
    // ล้าง PHP OPcache ถ้ามี
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    
    // ล้าง output buffer
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    $message = "ล้าง cache เรียบร้อย ($cleared files)";
    if (!empty($errors)) {
        $message .= " (ไม่สำเร็จ: " . count($errors) . " files)";
    }
    
    echo json_encode(['success' => true, 'message' => $message, 'cleared' => $cleared, 'errors' => $errors]);
    exit;
}

// =============================================
// ACTION: Fix Duplicate Settings
// =============================================
if ($action === 'fix_duplicates') {
    $result = $conn->query("SELECT setting_key, COUNT(*) as cnt FROM system_settings GROUP BY setting_key HAVING cnt > 1");
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Query error', 'duplicates_found' => 0, 'fixed' => 0]);
        exit;
    }
    $duplicates = [];
    while ($row = $result->fetch_assoc()) {
        $duplicates[] = $row['setting_key'];
    }
    
    $fixed = 0;
    foreach ($duplicates as $key) {
        $key_esc = $conn->real_escape_string($key);
        // ตาราง system_settings ไม่มี id - ใช้ LIMIT 1 เก็บค่าใดค่าหนึ่ง
        $res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = '$key_esc' LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            $latest_value = $row['setting_value'];
            $conn->query("DELETE FROM system_settings WHERE setting_key = '$key_esc'");
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->bind_param("ss", $key, $latest_value);
            $stmt->execute();
            $stmt->close();
            $fixed++;
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message' => "แก้ไข $fixed รายการซ้ำเรียบร้อย",
        'duplicates_found' => count($duplicates),
        'fixed' => $fixed
    ]);
    exit;
}

// =============================================
// ACTION: Toggle AI
// =============================================
if ($action === 'toggle_ai') {
    $enabled = $_POST['enabled'] ?? '0';
    
    // ลบค่าเก่าทั้งหมด
    $conn->query("DELETE FROM system_settings WHERE setting_key = 'ai_active'");
    
    // Insert ค่าใหม่
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('ai_active', ?)");
    $stmt->bind_param("s", $enabled);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode(['success' => true, 'ai_active' => $enabled]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
