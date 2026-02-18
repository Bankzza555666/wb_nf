<?php
// Server_price/sync_traffic.php
// Cron Job or AJAX: Fetches traffic from X-UI and updates the local DB.

// Set the correct directory context
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/../controller/auth_check.php'; // For AJAX session
}
require_once __DIR__ . '/../controller/config.php';
require_once __DIR__ . '/../controller/xui_api/multi_xui_api.php';

// --- Helper Functions ---
$log_file = __DIR__ . '/../../logs/traffic_sync.log';
if (!file_exists(dirname($log_file))) {
    mkdir(dirname($log_file), 0755, true);
}
function writeLog($message)
{
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// --- Main Logic ---
$is_ajax_request = isset($_POST['rental_id']);

if ($is_ajax_request) {
    header('Content-Type: application/json');
    $rental_id = intval($_POST['rental_id']);
    $user_id = $_SESSION['user_id'] ?? 0;

    // For AJAX, fetch the specific rental regardless of status to give proper feedback.
    $query = "SELECT id, server_id, client_email, client_uuid, status, data_total_bytes, price_id, rental_name FROM user_rentals WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $rental_id, $user_id);
} else {
    // For Cron job, only sync active users.
    writeLog("=== Start Full Traffic Sync (Cron Job) ===");
    $query = "SELECT id, server_id, client_email, client_uuid, status, data_total_bytes FROM user_rentals WHERE status = 'active' AND expire_date > NOW() AND deleted_at IS NULL";
    $stmt = $conn->prepare($query);
}

try {
    $api = new MultiXUIApi($conn);

    $stmt->execute();
    $rentals = $stmt->get_result();
    $stmt->close();

    $success_count = 0;
    $error_count = 0;
    $updated_data = null;

    while ($rental = $rentals->fetch_assoc()) {
        try {
            // สำหรับ VPN ที่ active เท่านั้น - sync traffic
            $traffic_synced = false;
            if ($rental['status'] === 'active') {
                $traffic_result = $api->getClientTraffic($rental['server_id'], $rental['client_uuid']);

                if ($traffic_result['success'] && isset($traffic_result['data']['obj']) && is_array($traffic_result['data']['obj']) && !empty($traffic_result['data']['obj'])) {
                    $traffic_data = $traffic_result['data']['obj'][0];

                    $upload_bytes = intval($traffic_data['up'] ?? 0);
                    $download_bytes = intval($traffic_data['down'] ?? 0);
                    $total_used_bytes = $upload_bytes + $download_bytes;

                    $update_stmt = $conn->prepare("UPDATE user_rentals SET data_used_bytes = ?, upload_bytes = ?, download_bytes = ?, last_traffic_sync = NOW() WHERE id = ?");
                    $update_stmt->bind_param('iiii', $total_used_bytes, $upload_bytes, $download_bytes, $rental['id']);
                    $update_stmt->execute();
                    $update_stmt->close();

                    $traffic_synced = true;
                    $success_count++;
                }
            }

            // ✅ FIX: ใช้ config_url ที่บันทึกไว้ตอนซื้อ - ไม่ regenerate จาก template
            // เพื่อป้องกันไม่ให้ config ของผู้ใช้เปลี่ยนเมื่อแอดมินแก้ไข template
            if ($is_ajax_request) {
                $select_stmt = $conn->prepare("
                    SELECT 
                        ur.data_used_bytes, ur.upload_bytes, ur.download_bytes, 
                        ur.rental_name, ur.client_uuid, ur.client_email, ur.config_url,
                        ROUND((ur.data_used_bytes / GREATEST(ur.data_total_bytes, 1)) * 100, 2) as percentage,
                        p.config_template, p.host, p.port, p.network, p.security
                    FROM user_rentals ur
                    LEFT JOIN price_v2 p ON ur.price_id = p.id
                    WHERE ur.id = ?
                ");
                $select_stmt->bind_param('i', $rental['id']);
                $select_stmt->execute();
                $updated_data = $select_stmt->get_result()->fetch_assoc();
                $select_stmt->close();

                // ✅ Update Config URL from Template (Auto Sync)
                if (!empty($updated_data['config_template'])) {
                    $template_data = [
                        'uuid' => $updated_data['client_uuid'],
                        'email' => $updated_data['client_email'] ?? $rental['client_email'],
                        'host' => $updated_data['host'] ?? '',
                        'port' => $updated_data['port'] ?? '',
                        'network' => $updated_data['network'] ?? 'tcp',
                        'security' => $updated_data['security'] ?? 'none',
                        'sni' => $updated_data['host'] ?? '',
                        'path' => '/',
                        'public_key' => '',
                        'short_id' => '',
                        'custom_name' => $updated_data['rental_name']
                    ];
                    $new_config = $api->generateConfigFromTemplate($updated_data['config_template'], $template_data);

                    // ถ้า Config เปลี่ยนไปจากเดิม ให้ update ลงฐานข้อมูล
                    if ($new_config !== $updated_data['config_url']) {
                        $update_config = $conn->prepare("UPDATE user_rentals SET config_url = ? WHERE id = ?");
                        $update_config->bind_param("si", $new_config, $rental['id']);
                        $update_config->execute();
                        $update_config->close();
                        $updated_data['config_url'] = $new_config; // ส่งค่าใหม่กลับไป
                    }
                }

                // ลบ field ที่ไม่จำเป็น
                unset(
                    $updated_data['config_template'],
                    $updated_data['host'],
                    $updated_data['port'],
                    $updated_data['network'],
                    $updated_data['security'],
                    $updated_data['client_uuid'],
                    $updated_data['client_email']
                );
            }

            // ถ้า traffic sync ไม่สำเร็จ เพิ่ม error count (แต่ไม่ throw exception)
            if (!$traffic_synced && $rental['status'] === 'active') {
                $error_count++;
                if (!$is_ajax_request) {
                    writeLog("Traffic sync failed for rental #{$rental['id']}");
                }
            }

        } catch (Exception $e) {
            $error_count++;
            if ($is_ajax_request) {
                throw $e; // Re-throw to the final catch block for JSON response.
            } else {
                writeLog("ERROR: Exception for rental #{$rental['id']}: " . $e->getMessage());
            }
        }
    }

    if (!$is_ajax_request) {
        writeLog("=== Sync Complete: Success=$success_count, Errors=$error_count ===");
        echo json_encode(['success' => true, 'synced' => $success_count, 'errors' => $error_count]);
    } else {
        // ✅ FIX: ใช้ config_url ที่บันทึกไว้ตอนซื้อ - ไม่ regenerate จาก template
        if (!$updated_data) {
            $rentals->data_seek(0);
            $rental = $rentals->fetch_assoc();
            if ($rental) {
                $select_stmt = $conn->prepare("
                    SELECT 
                        ur.data_used_bytes, ur.upload_bytes, ur.download_bytes, 
                        ur.rental_name, ur.client_uuid, ur.client_email, ur.config_url,
                        ROUND((ur.data_used_bytes / GREATEST(ur.data_total_bytes, 1)) * 100, 2) as percentage,
                        p.config_template, p.host, p.port, p.network, p.security
                    FROM user_rentals ur
                    LEFT JOIN price_v2 p ON ur.price_id = p.id
                    WHERE ur.id = ?
                ");
                $select_stmt->bind_param('i', $rental['id']);
                $select_stmt->execute();
                $updated_data = $select_stmt->get_result()->fetch_assoc();
                $select_stmt->close();

                // ✅ Update Config URL from Template (Auto Sync)
                if (!empty($updated_data['config_template'])) {
                    $template_data = [
                        'uuid' => $updated_data['client_uuid'],
                        'email' => $updated_data['client_email'] ?? $rental['client_email'],
                        'host' => $updated_data['host'] ?? '',
                        'port' => $updated_data['port'] ?? '',
                        'network' => $updated_data['network'] ?? 'tcp',
                        'security' => $updated_data['security'] ?? 'none',
                        'sni' => $updated_data['host'] ?? '',
                        'path' => '/',
                        'public_key' => '',
                        'short_id' => '',
                        'custom_name' => $updated_data['rental_name']
                    ];
                    $new_config = $api->generateConfigFromTemplate($updated_data['config_template'], $template_data);

                    // ถ้า Config เปลี่ยนไปจากเดิม ให้ update ลงฐานข้อมูล
                    if ($new_config !== $updated_data['config_url']) {
                        $update_config = $conn->prepare("UPDATE user_rentals SET config_url = ? WHERE id = ?");
                        $update_config->bind_param("si", $new_config, $rental['id']);
                        $update_config->execute();
                        $update_config->close();
                        $updated_data['config_url'] = $new_config; // ส่งค่าใหม่กลับไป
                    }
                }

                // ลบ field ที่ไม่จำเป็น
                unset(
                    $updated_data['config_template'],
                    $updated_data['host'],
                    $updated_data['port'],
                    $updated_data['network'],
                    $updated_data['security'],
                    $updated_data['client_uuid'],
                    $updated_data['client_email']
                );
            }
        }

        if ($updated_data) {
            echo json_encode(['success' => true, 'data' => $updated_data]);
        } else {
            throw new Exception('ไม่สามารถอัปเดทข้อมูลได้');
        }
    }

} catch (Exception $e) {
    if (!$is_ajax_request) {
        writeLog("FATAL ERROR: " . $e->getMessage());
        http_response_code(500);
    }
    // For all requests (AJAX or not), send a JSON error.
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>