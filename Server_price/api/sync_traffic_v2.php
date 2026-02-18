<?php
// Server_price/api/sync_traffic_v2.php
// Cron Job: ดึง Traffic จาก 3x-ui และอัพเดท (แก้ไขแล้ว)

require_once __DIR__ . '/../../controller/config.php';
require_once __DIR__ . '/../../controller/xui_api/multi_xui_api.php';

// Log file
$log_file = __DIR__ . '/../../logs/traffic_sync.log';
if (!file_exists(dirname($log_file))) {
    mkdir(dirname($log_file), 0755, true);
}

function writeLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog("=== Start Traffic Sync ===");

try {
    $api = new MultiXUIApi($conn);
    
    // ดึงรายการ VPN ที่ active
    $stmt = $conn->prepare("
        SELECT id, server_id, client_email, data_total_bytes 
        FROM user_rentals 
        WHERE status = 'active' 
        AND expire_date > NOW() 
        AND deleted_at IS NULL
    ");
    $stmt->execute();
    $rentals = $stmt->get_result();
    $stmt->close();
    
    $success_count = 0;
    $error_count = 0;
    
    while ($rental = $rentals->fetch_assoc()) {
        try {
            // ดึง Traffic จาก API
            $traffic_result = $api->getClientTraffic($rental['server_id'], $rental['client_email']);
            
            if ($traffic_result['success'] && isset($traffic_result['data'])) {
                $traffic_data = $traffic_result['data'];
                
                // ใช้ค่าจาก API
                $upload_bytes = intval($traffic_data['up'] ?? 0);
                $download_bytes = intval($traffic_data['down'] ?? 0);
                $total_used_bytes = intval($traffic_data['allTime'] ?? 0);  // ใช้ allTime
                
                // อัพเดทฐานข้อมูล
                $stmt = $conn->prepare("
                    UPDATE user_rentals 
                    SET data_used_bytes = ?,
                        upload_bytes = ?,
                        download_bytes = ?,
                        last_traffic_sync = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param("iiii", $total_used_bytes, $upload_bytes, $download_bytes, $rental['id']);
                $stmt->execute();
                $stmt->close();
                
                $success_count++;
                
                // เช็คว่าใกล้เต็มหรือยัง (90%)
                if ($rental['data_total_bytes'] > 0) {
                    $usage_percent = ($total_used_bytes / $rental['data_total_bytes']) * 100;
                    if ($usage_percent >= 90 && $usage_percent < 100) {
                        writeLog("WARNING: Rental #{$rental['id']} usage at {$usage_percent}%");
                    }
                }
                
            } else {
                $error_count++;
                writeLog("ERROR: Failed to get traffic for rental #{$rental['id']}: " . ($traffic_result['message'] ?? 'Unknown'));
            }
            
        } catch (Exception $e) {
            $error_count++;
            writeLog("ERROR: Exception for rental #{$rental['id']}: " . $e->getMessage());
        }
    }
    
    writeLog("=== Sync Complete: Success=$success_count, Errors=$error_count ===");
    
    echo json_encode([
        'success' => true,
        'synced' => $success_count,
        'errors' => $error_count
    ]);
    
} catch (Exception $e) {
    writeLog("FATAL ERROR: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>