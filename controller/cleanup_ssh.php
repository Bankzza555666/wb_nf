<?php
/**
 * SSH Cleanup Script
 * ลบ accounts ที่หมดอายุเกิน 2 วัน
 * เรียกใช้จาก cron job หรือเรียกจากหน้าเว็บ
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ssh_api/ssh_api.php';

// จำนวนวันหลังหมดอายุที่จะลบ
$DAYS_AFTER_EXPIRY = 2;

/**
 * ทำความสะอาด expired rentals
 * @param mysqli $conn
 * @param int $daysAfterExpiry จำนวนวันหลังหมดอายุจึงลบ (default 2)
 * @param int|null $userId ถ้าระบุ จะลบเฉพาะของ user นี้ (ใช้เมื่อเรียกจากหน้า my_ssh)
 */
function cleanupExpiredRentals($conn, $daysAfterExpiry = 2, $userId = null)
{
    $deleted = 0;
    $errors = [];

    $sql = "SELECT r.*, s.* FROM ssh_rentals r 
            LEFT JOIN ssh_servers s ON r.server_id = s.server_id 
            WHERE r.expire_date < DATE_SUB(NOW(), INTERVAL ? DAY)";
    if ($userId !== null) {
        $sql .= " AND r.user_id = ?";
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
        $username = $rental['ssh_username'];

        // ลบ user จาก SSH Server
        try {
            $sshApi = new SSHPlusManagerAPI($rental, $conn);
            $deleteResult = $sshApi->deleteUser($username);

            if (!$deleteResult['success']) {
                $errors[] = "Could not delete user {$username}: " . $deleteResult['message'];
                // Continue anyway
            }
        } catch (Exception $e) {
            $errors[] = "SSH error for {$username}: " . $e->getMessage();
        }

        // ลบจาก database
        $delStmt = $conn->prepare("DELETE FROM ssh_rentals WHERE id = ?");
        $delStmt->bind_param("i", $rentalId);
        $delStmt->execute();

        if ($delStmt->affected_rows > 0) {
            $deleted++;
            error_log("[SSH Cleanup] Deleted rental #{$rentalId} - user: {$username}");
        }
    }

    return [
        'success' => true,
        'deleted' => $deleted,
        'errors' => $errors
    ];
}

// ถ้าเรียกโดยตรง (ไม่ใช่ include)
if (php_sapi_name() === 'cli' || basename($_SERVER['PHP_SELF']) === 'cleanup_ssh.php') {
    $result = cleanupExpiredRentals($conn, $DAYS_AFTER_EXPIRY);

    if (php_sapi_name() === 'cli') {
        echo "SSH Cleanup Complete\n";
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
