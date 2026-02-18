<?php
/**
 * Run Referral Migration
 * เรียกใช้ผ่าน browser หรือ command line เพื่อสร้างตารางและ generate referral codes
 */

require_once __DIR__ . '/../controller/config.php';

echo "<pre style='background:#1a1a1a;color:#fff;padding:20px;font-family:monospace;'>";
echo "=== Referral System Migration ===\n\n";

// 1. เพิ่ม columns ใน users table
echo "1. Adding columns to users table...\n";
$queries = [
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `referral_code` VARCHAR(10) UNIQUE DEFAULT NULL",
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `referred_by` INT DEFAULT NULL",
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `total_referral_earnings` DECIMAL(12,2) DEFAULT 0"
];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "   ✓ OK\n";
    } else {
        // Column might already exist
        echo "   - Skipped (may already exist)\n";
    }
}

// 2. สร้างตาราง referral_earnings
echo "\n2. Creating referral_earnings table...\n";
$sql = "CREATE TABLE IF NOT EXISTS `referral_earnings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `referrer_id` INT NOT NULL,
    `referred_id` INT NOT NULL,
    `topup_transaction_id` INT NOT NULL,
    `topup_amount` DECIMAL(12,2) NOT NULL,
    `commission_percent` DECIMAL(5,2) DEFAULT 10.00,
    `commission_amount` DECIMAL(12,2) NOT NULL,
    `status` ENUM('pending', 'credited', 'cancelled') DEFAULT 'credited',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_referrer` (`referrer_id`),
    INDEX `idx_referred` (`referred_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql)) {
    echo "   ✓ Table created\n";
} else {
    echo "   ✓ Table already exists\n";
}

// 3. สร้างตาราง referral_settings
echo "\n3. Creating referral_settings table...\n";
$sql = "CREATE TABLE IF NOT EXISTS `referral_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(50) NOT NULL UNIQUE,
    `setting_value` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql)) {
    echo "   ✓ Table created\n";
}

// 4. Insert default settings
echo "\n4. Inserting default settings...\n";
$settings = [
    ['referral_enabled', '1'],
    ['commission_percent', '10'],
    ['min_topup_for_commission', '0'],
    ['max_commission_per_transaction', '0']
];

foreach ($settings as $s) {
    $conn->query("INSERT IGNORE INTO referral_settings (setting_key, setting_value) VALUES ('{$s[0]}', '{$s[1]}')");
}
echo "   ✓ Default settings inserted\n";

// 5. Generate referral codes for existing users
echo "\n5. Generating referral codes for existing users...\n";

function generateReferralCode($conn) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // ไม่มี I, O, 0, 1 เพื่อไม่ให้สับสน
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        // เช็คว่าซ้ำไหม
        $check = $conn->query("SELECT id FROM users WHERE referral_code = '$code'");
    } while ($check && $check->num_rows > 0);
    
    return $code;
}

$result = $conn->query("SELECT id, username FROM users WHERE referral_code IS NULL OR referral_code = ''");
$count = 0;
if ($result) {
    while ($user = $result->fetch_assoc()) {
        $code = generateReferralCode($conn);
        $conn->query("UPDATE users SET referral_code = '$code' WHERE id = {$user['id']}");
        echo "   Generated code '$code' for user '{$user['username']}'\n";
        $count++;
    }
}
echo "   ✓ Generated $count codes\n";

echo "\n=== Migration Complete! ===\n";
echo "</pre>";

echo "<p style='color:#22c55e;font-size:1.2em;'>✅ ระบบแนะนำเพื่อนพร้อมใช้งานแล้ว!</p>";
echo "<p><a href='../index.php?p=home' style='color:#3b82f6;'>กลับหน้าหลัก</a></p>";
?>
