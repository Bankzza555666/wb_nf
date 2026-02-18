<?php
/**
 * Main Cron Job Worker
 * ทำงานรวมทุกอย่าง: Auto-renewal + Cleanup สำหรับ SSH และ VPN
 * เรียกผ่าน cron job ทุก 1 ชั่วโมง
 */

require_once __DIR__ . '/config.php';

// Log function
function logCron($msg)
{
    $timestamp = "[" . date('Y-m-d H:i:s') . "]";
    echo "{$timestamp} {$msg}\n";
    
    $logFile = __DIR__ . '/../logs/cron_jobs.log';
    if (!file_exists(dirname($logFile)))
        mkdir(dirname($logFile), 0755, true);
    file_put_contents($logFile, "{$timestamp} {$msg}\n", FILE_APPEND);
}

logCron("=== CRON JOB STARTED ===");

// 1. Auto-renewal (SSH + VPN)
logCron("Starting auto-renewal process...");
require_once __DIR__ . '/auto_renew_worker.php';

// 2. Cleanup expired rentals
logCron("Starting cleanup process...");

// Cleanup SSH
logCron("Cleaning up expired SSH rentals...");
require_once __DIR__ . '/cleanup_ssh.php';

// Cleanup VPN  
logCron("Cleaning up expired VPN rentals...");
require_once __DIR__ . '/cleanup_vpn.php';

logCron("=== CRON JOB COMPLETED ===");
?>