<?php
/**
 * Auto-Renewal Worker Script
 * This script should be run via Cron (e.g., every hour)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ssh_api/ssh_api.php';
require_once __DIR__ . '/xui_api/multi_xui_api.php';

// Log function
function logRenew($msg)
{
    echo "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n";
    $logFile = __DIR__ . '/../logs/auto_renew.log';
    if (!file_exists(dirname($logFile)))
        mkdir(dirname($logFile), 0755, true);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND);
}

logRenew("--- Auto-Renewal Worker Started ---");

// 1. Process SSH Rentals
logRenew("Processing SSH renewals...");
$sshQuery = "SELECT r.*, p.price_per_day, p.product_name, u.credit 
             FROM ssh_rentals r
             JOIN ssh_products p ON r.product_id = p.id
             JOIN users u ON r.user_id = u.id
             WHERE r.auto_renew = 1 
             AND r.status = 'active'
             AND r.expire_date > NOW()
             AND r.expire_date <= DATE_ADD(NOW(), INTERVAL 24 HOUR)";

$sshResults = $conn->query($sshQuery);
while ($rental = $sshResults->fetch_assoc()) {
    $rentalId = $rental['id'];
    $userId = $rental['user_id'];
    $days = 1; // ต่ออายุวันต่อวัน (ครั้งละ 1 วัน)
    $price = $rental['price_per_day'] * $days;

    logRenew("SSH Rental #$rentalId ({$rental['ssh_username']}) expiring soon. Cost: ฿$price (+{$days} วัน)");

    if ($rental['credit'] < $price) {
        logRenew("User #$userId credit too low (฿{$rental['credit']}). Skipping.");
        continue;
    }

    $conn->begin_transaction();
    try {
        $price = (float) $price;
        $conn->query("UPDATE users SET credit = credit - $price WHERE id = $userId");

        $newExpire = date('Y-m-d H:i:s', strtotime($rental['expire_date'] . " +$days days"));
        $conn->query("UPDATE ssh_rentals SET expire_date = '$newExpire', days_rented = days_rented + $days WHERE id = $rentalId");

        $stmt_server = $conn->prepare("SELECT * FROM ssh_servers WHERE server_id = ?");
        $stmt_server->bind_param("s", $rental['server_id']);
        $stmt_server->execute();
        $server = $stmt_server->get_result()->fetch_assoc();

        if ($server) {
            $sshApi = new SSHPlusManagerAPI($server, $conn);
            $sshApi->extendUser($rental['ssh_username'], $days);
        }

        $conn->commit();
        logRenew("SSH Rental #$rentalId renewed successfully to $newExpire.");

        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'success')");
        $title = "✅ ต่ออายุ SSH อัตโนมัติ (+1 วัน)";
        $msg = "ระบบได้ต่ออายุ {$rental['product_name']} +1 วัน ให้คุณแล้ว หักเครดิต ฿" . number_format($price, 2);
        $stmt->bind_param("iss", $userId, $title, $msg);
        $stmt->execute();

    } catch (Exception $e) {
        $conn->rollback();
        logRenew("ERROR: Failed to renew SSH #$rentalId: " . $e->getMessage());
    }
}

// 2. Process VPN Rentals
logRenew("Processing VPN renewals...");
$vpnQuery = "SELECT ur.*, p.price_per_day, p.min_days, u.credit 
             FROM user_rentals ur
             JOIN price_v2 p ON ur.price_id = p.id
             JOIN users u ON ur.user_id = u.id
             WHERE ur.auto_renew = 1 
             AND ur.status = 'active'
             AND ur.expire_date > NOW()
             AND ur.expire_date <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
             AND ur.deleted_at IS NULL";

$vpnResults = $conn->query($vpnQuery);
while ($rental = $vpnResults->fetch_assoc()) {
    $rentalId = $rental['id'];
    $userId = $rental['user_id'];
    $days = 1; // ต่ออายุวันต่อวัน (ครั้งละ 1 วัน)
    $price = $rental['price_per_day'] * $days;

    logRenew("VPN Rental #$rentalId ({$rental['rental_name']}) expiring soon. Cost: ฿$price (+{$days} วัน)");

    if ($rental['credit'] < $price) {
        logRenew("User #$userId credit too low (฿{$rental['credit']}). Skipping.");
        continue;
    }

    $conn->begin_transaction();
    try {
        $price = (float) $price;
        $conn->query("UPDATE users SET credit = credit - $price WHERE id = $userId");

        $newExpire = date('Y-m-d H:i:s', strtotime($rental['expire_date'] . " +$days days"));
        $conn->query("UPDATE user_rentals SET expire_date = '$newExpire', days_rented = days_rented + $days WHERE id = $rentalId");

        $api = new MultiXUIApi($conn);
        $api->extendClient($rental['server_id'], $rental['inbound_id'], $rental['client_email'], $days);

        $conn->commit();
        logRenew("VPN Rental #$rentalId renewed successfully to $newExpire.");

        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'success')");
        $title = "✅ ต่ออายุ VPN อัตโนมัติ (+1 วัน)";
        $msg = "ระบบได้ต่ออายุ VPN {$rental['rental_name']} +1 วัน ให้คุณแล้ว หักเครดิต ฿" . number_format($price, 2);
        $stmt->bind_param("iss", $userId, $title, $msg);
        $stmt->execute();

    } catch (Exception $e) {
        $conn->rollback();
        logRenew("ERROR: Failed to renew VPN #$rentalId: " . $e->getMessage());
    }
}

logRenew("--- Auto-Renewal Worker Finished ---");
$conn->close();
