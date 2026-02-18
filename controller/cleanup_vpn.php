<?php
/**
 * VPN Cleanup Script
 * ลบ VPN accounts ที่หมดอายุเกิน 2 วัน
 * เรียกใช้จาก cron job หรือเรียกจากหน้าเว็บ
 */

require_once __DIR__ . '/config.php';

// จำนวนวันหลังหมดอายุที่จะลบ
$DAYS_AFTER_EXPIRY = 2;

/**
 * ทำความสะอาด expired VPN rentals
 * @param mysqli $conn
 * @param int $daysAfterExpiry จำนวนวันหลังหมดอายุจึงลบ (default 2)
 * @param int|null $userId ถ้าระบุ จะลบเฉพาะของ user นี้ (ใช้เมื่อเรียกจากหน้า my_vpn)
 */
function cleanupExpiredVPNRentals($conn, $daysAfterExpiry = 2, $userId = null)
{
    $deleted = 0;
    $errors = [];

    // ดึงข้อมูล VPN rentals ที่หมดอายุเกินกำหนด
    $sql = "SELECT ur.*, s.server_id, p.protocol, p.network 
            FROM user_rentals ur
            LEFT JOIN servers s ON ur.server_id = s.server_id  
            LEFT JOIN price_v2 p ON ur.price_id = p.id
            WHERE ur.expire_date < DATE_SUB(NOW(), INTERVAL ? DAY)
            AND ur.deleted_at IS NULL";
    
    if ($userId !== null) {
        $sql .= " AND ur.user_id = ?";
    }
    
    $stmt = $conn->prepare($sql);
    if ($userId !== null) {
        $stmt->bind_param("ii", $daysAfterExpiry, $userId);
    } else {
        $stmt->bind_param("i", $daysAfterExpiry);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($rental = $result->fetch_assoc()) {
        $rentalId = $rental['id'];
        $clientEmail = $rental['client_email'];
        $serverId = $rental['server_id'];
        $inboundId = $rental['inbound_id'];

        // ลบ client จาก XUI Server
        try {
            require_once __DIR__ . '/xui_api/multi_xui_api.php';
            $api = new MultiXUIApi($conn);
            
            if ($serverId && $clientEmail && $inboundId) {
                $deleteResult = $api->deleteClient($serverId, $inboundId, $clientEmail);
                
                if (!$deleteResult['success']) {
                    $errors[] = "Could not delete VPN client {$clientEmail}: " . ($deleteResult['message'] ?? 'Unknown error');
                    // Continue anyway
                }
            }
        } catch (Exception $e) {
            $errors[] = "VPN API error for {$clientEmail}: " . $e->getMessage();
        }

        // ลบจาก database (soft delete)
        $delStmt = $conn->prepare("UPDATE user_rentals SET deleted_at = NOW(), status = 'deleted' WHERE id = ?");
        $delStmt->bind_param("i", $rentalId);
        $delStmt->execute();

        if ($delStmt->affected_rows > 0) {
            $deleted++;
            error_log("[VPN Cleanup] Deleted rental #{$rentalId} - client: {$clientEmail}");
        }
    }

    return [
        'success' => true,
        'deleted' => $deleted,
        'errors' => $errors
    ];
}

// ถ้าเรียกโดยตรง (ไม่ใช่ include)
if (php_sapi_name() === 'cli' || basename($_SERVER['PHP_SELF']) === 'cleanup_vpn.php') {
    $result = cleanupExpiredVPNRentals($conn, $DAYS_AFTER_EXPIRY);

    if (php_sapi_name() === 'cli') {
        echo "VPN Cleanup Complete\n";
        echo "Deleted: {$result['deleted']} rentals\n";
        if (!empty($result['errors'])) {
            echo "Errors:\n";
            foreach ($result['errors'] as $err) {
                echo "  - {$err}\n";
            }
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode($result);
    }
}
?>