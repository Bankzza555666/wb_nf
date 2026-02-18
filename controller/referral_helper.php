<?php
/**
 * Referral System Helper
 * ระบบแนะนำเพื่อน - ฟังก์ชันหลักและความปลอดภัย
 * 
 * @version 2.0
 * @security Enhanced with anti-abuse measures
 */

// ป้องกันการเรียกไฟล์โดยตรง
if (!defined('REFERRAL_HELPER_LOADED')) {
    define('REFERRAL_HELPER_LOADED', true);
}

/**
 * สร้างตาราง Referral ถ้ายังไม่มี
 */
function initReferralTables($conn) {
    if (!$conn) return false;
    
    // ปิด error reporting ชั่วคราว
    $old_mode = $conn->query("SELECT @@sql_mode")->fetch_row()[0] ?? '';
    
    try {
        // 1. เพิ่ม columns ใน users table
        $columns = [
            'referral_code' => "ALTER TABLE `users` ADD COLUMN `referral_code` VARCHAR(10) DEFAULT NULL",
            'referred_by' => "ALTER TABLE `users` ADD COLUMN `referred_by` INT DEFAULT NULL",
            'total_referral_earnings' => "ALTER TABLE `users` ADD COLUMN `total_referral_earnings` DECIMAL(12,2) DEFAULT 0",
            'referral_locked' => "ALTER TABLE `users` ADD COLUMN `referral_locked` TINYINT(1) DEFAULT 0"
        ];
        
        foreach ($columns as $col => $sql) {
            try {
                $check = @$conn->query("SHOW COLUMNS FROM users LIKE '$col'");
                if ($check && $check->num_rows == 0) {
                    @$conn->query($sql);
                }
            } catch (Exception $e) {}
        }
    } catch (Exception $e) {}
    
    try {
        // 2. ตาราง referral_settings
        @$conn->query("CREATE TABLE IF NOT EXISTS `referral_settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `setting_key` VARCHAR(50) NOT NULL UNIQUE,
            `setting_value` TEXT,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
    
    try {
        // 3. ตาราง referral_earnings
        @$conn->query("CREATE TABLE IF NOT EXISTS `referral_earnings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `referrer_id` INT NOT NULL,
            `referred_id` INT NOT NULL,
            `topup_transaction_id` INT NOT NULL,
            `topup_amount` DECIMAL(12,2) NOT NULL,
            `commission_percent` DECIMAL(5,2) DEFAULT 10.00,
            `commission_amount` DECIMAL(12,2) NOT NULL,
            `status` ENUM('pending', 'credited', 'cancelled', 'refunded') DEFAULT 'credited',
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_referrer` (`referrer_id`),
            INDEX `idx_referred` (`referred_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
    
    try {
        // 4. ตาราง referral_logs (Audit Trail)
        @$conn->query("CREATE TABLE IF NOT EXISTS `referral_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `action` VARCHAR(50) NOT NULL,
            `target_user_id` INT DEFAULT NULL,
            `old_value` TEXT DEFAULT NULL,
            `new_value` TEXT DEFAULT NULL,
            `amount` DECIMAL(12,2) DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `user_agent` VARCHAR(500) DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_user` (`user_id`),
            INDEX `idx_action` (`action`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
    
    try {
        // 5. Default settings
        $defaults = [
            ['referral_enabled', '1'],
            ['commission_percent', '10'],
            ['min_topup_for_commission', '20'],
            ['max_commission_per_transaction', '500'],
            ['referrer_add_days_limit', '7'],
            ['max_referrals_per_user', '100'],
            ['same_ip_referral_limit', '3'],
            ['require_first_topup', '1'],
            ['anti_fraud_enabled', '1']
        ];
        
        foreach ($defaults as $d) {
            @$conn->query("INSERT IGNORE INTO referral_settings (setting_key, setting_value) VALUES ('{$d[0]}', '{$d[1]}')");
        }
    } catch (Exception $e) {}
    
    return true;
}

/**
 * ดึงค่า setting
 */
function getReferralSetting($conn, $key, $default = null) {
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM referral_settings WHERE setting_key = ?");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * บันทึก Log
 */
function logReferralAction($conn, $user_id, $action, $target_user_id = null, $old_value = null, $new_value = null, $amount = null, $notes = null) {
    try {
        if (!$conn) return false;
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        
        $stmt = $conn->prepare("INSERT INTO referral_logs (user_id, action, target_user_id, old_value, new_value, amount, ip_address, user_agent, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) return false;
        
        // 9 parameters: i=int, s=string, d=double
        $stmt->bind_param("isissdss" . "s", $user_id, $action, $target_user_id, $old_value, $new_value, $amount, $ip, $ua, $notes);
        $stmt->execute();
        $stmt->close();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * สร้าง Referral Code ที่ไม่ซ้ำ
 */
function generateReferralCode($conn) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // ไม่มี I, O, 0, 1 เพื่อไม่ให้สับสน
    $max_attempts = 100;
    
    for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        $stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows == 0) {
            return $code;
        }
    }
    
    // Fallback: ใช้ timestamp
    return strtoupper(substr(md5(time() . random_int(1000, 9999)), 0, 6));
}

/**
 * ตรวจสอบว่าสามารถเพิ่มผู้แนะนำได้หรือไม่
 */
function canAddReferrer($conn, $user_id) {
    try {
        // ลองดึงข้อมูล user แบบเต็ม
        $user = null;
        try {
            $stmt = $conn->prepare("SELECT referred_by, created_at, ip_address FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } catch (Exception $e) {
            // ถ้า columns ไม่มี ลองแบบ basic
            try {
                $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($user) {
                    $user['referred_by'] = null;
                    $user['created_at'] = date('Y-m-d H:i:s'); // ให้เพิ่มได้
                }
            } catch (Exception $e2) {}
        }
        
        if (!$user) {
            return ['can' => false, 'reason' => 'ไม่พบผู้ใช้'];
        }
        
        // มี referrer แล้ว
        if (!empty($user['referred_by'])) {
            return ['can' => false, 'reason' => 'คุณมีผู้แนะนำแล้ว'];
        }
        
        // เช็คระยะเวลา
        $days_limit = intval(getReferralSetting($conn, 'referrer_add_days_limit', 7));
        $days_since = 0;
        
        if (!empty($user['created_at'])) {
            try {
                $register_date = new DateTime($user['created_at']);
                $now = new DateTime();
                $days_since = $now->diff($register_date)->days;
            } catch (Exception $e) {
                $days_since = 0; // ให้เพิ่มได้ถ้าคำนวณไม่ได้
            }
        }
        
        if ($days_since > $days_limit) {
            return ['can' => false, 'reason' => "เกินระยะเวลาที่กำหนด ($days_limit วัน หลังสมัคร)"];
        }
        
        return ['can' => true, 'days_left' => max(0, $days_limit - $days_since)];
        
    } catch (Exception $e) {
        // ถ้ามี error ให้ลองเพิ่มได้
        return ['can' => true, 'days_left' => 7];
    }
}

/**
 * ตรวจสอบ Referral Code
 */
function validateReferralCode($conn, $code, $user_id) {
    $code = strtoupper(trim($code));
    
    if (empty($code)) {
        return ['valid' => false, 'error' => 'กรุณากรอกรหัสแนะนำ'];
    }
    
    if (strlen($code) !== 6) {
        return ['valid' => false, 'error' => 'รหัสแนะนำไม่ถูกต้อง'];
    }
    
    // หา referrer
    $stmt = $conn->prepare("SELECT id, username, referral_code, referred_by, referral_locked, ip_address FROM users WHERE referral_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $referrer = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$referrer) {
        return ['valid' => false, 'error' => 'ไม่พบรหัสแนะนำนี้'];
    }
    
    // ห้ามใช้รหัสตัวเอง
    if ($referrer['id'] == $user_id) {
        return ['valid' => false, 'error' => 'ไม่สามารถใช้รหัสของตัวเองได้'];
    }
    
    // ป้องกัน Circular Referral (A แนะนำ B, B แนะนำ A)
    if ($referrer['referred_by'] == $user_id) {
        return ['valid' => false, 'error' => 'ไม่สามารถแนะนำกันและกันได้'];
    }
    
    // เช็คว่า referrer ถูก lock หรือไม่
    if ($referrer['referral_locked']) {
        return ['valid' => false, 'error' => 'ไม่สามารถใช้รหัสนี้ได้'];
    }
    
    // เช็คจำนวน referrals ของ referrer
    $max_referrals = intval(getReferralSetting($conn, 'max_referrals_per_user', 100));
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM users WHERE referred_by = ?");
    $stmt->bind_param("i", $referrer['id']);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    
    if ($count >= $max_referrals) {
        return ['valid' => false, 'error' => 'ผู้แนะนำนี้มีจำนวนเพื่อนครบแล้ว'];
    }
    
    // Anti-fraud: เช็ค IP
    if (getReferralSetting($conn, 'anti_fraud_enabled', '1') == '1') {
        $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ip_limit = intval(getReferralSetting($conn, 'same_ip_referral_limit', 3));
        
        // เช็คว่า IP เดียวกันกับ referrer หรือไม่
        if ($current_ip === $referrer['ip_address']) {
            return ['valid' => false, 'error' => 'ไม่สามารถใช้รหัสนี้ได้ (ตรวจพบ IP ซ้ำ)'];
        }
        
        // เช็คจำนวน accounts จาก IP เดียวกันที่ใช้ referrer คนนี้
        $stmt = $conn->prepare("SELECT COUNT(*) as c FROM users WHERE referred_by = ? AND ip_address = ?");
        $stmt->bind_param("is", $referrer['id'], $current_ip);
        $stmt->execute();
        $ip_count = $stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();
        
        if ($ip_count >= $ip_limit) {
            return ['valid' => false, 'error' => 'มีการใช้งานรหัสนี้จาก IP นี้มากเกินไป'];
        }
    }
    
    return ['valid' => true, 'referrer' => $referrer];
}

/**
 * เพิ่มผู้แนะนำ
 */
function addReferrer($conn, $user_id, $referrer_id) {
    try {
        $conn->begin_transaction();
        
        // Update user
        $stmt = $conn->prepare("UPDATE users SET referred_by = ? WHERE id = ? AND referred_by IS NULL");
        $stmt->bind_param("ii", $referrer_id, $user_id);
        $stmt->execute();
        
        if ($stmt->affected_rows == 0) {
            $conn->rollback();
            return ['success' => false, 'error' => 'ไม่สามารถเพิ่มผู้แนะนำได้'];
        }
        $stmt->close();
        
        // Log
        logReferralAction($conn, $user_id, 'add_referrer', $referrer_id, null, $referrer_id, null, 'User added referrer manually');
        
        $conn->commit();
        return ['success' => true];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

/**
 * คำนวณและให้ Commission
 * @return array ['success' => bool, 'commission' => float, 'error' => string]
 */
function processReferralCommission($conn, $user_id, $topup_amount, $transaction_id) {
    // เช็คว่าระบบเปิดอยู่ไหม
    if (getReferralSetting($conn, 'referral_enabled', '1') != '1') {
        return ['success' => false, 'error' => 'ระบบแนะนำเพื่อนปิดอยู่'];
    }
    
    // เช็คยอดขั้นต่ำ
    $min_topup = floatval(getReferralSetting($conn, 'min_topup_for_commission', 20));
    if ($topup_amount < $min_topup) {
        return ['success' => false, 'error' => "ยอดเติมต่ำกว่าขั้นต่ำ ($min_topup บาท)"];
    }
    
    // หา referrer
    $stmt = $conn->prepare("SELECT referred_by FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$result || empty($result['referred_by'])) {
        return ['success' => false, 'error' => 'ไม่มีผู้แนะนำ'];
    }
    
    $referrer_id = $result['referred_by'];
    
    // เช็คว่า referrer ถูก lock หรือไม่
    $stmt = $conn->prepare("SELECT id, username, referral_locked FROM users WHERE id = ?");
    $stmt->bind_param("i", $referrer_id);
    $stmt->execute();
    $referrer = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$referrer || $referrer['referral_locked']) {
        return ['success' => false, 'error' => 'ผู้แนะนำถูกระงับ'];
    }
    
    // เช็คว่า transaction นี้ให้ commission ไปแล้วหรือยัง
    $stmt = $conn->prepare("SELECT id FROM referral_earnings WHERE topup_transaction_id = ?");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        return ['success' => false, 'error' => 'ให้ commission ไปแล้ว'];
    }
    $stmt->close();
    
    // คำนวณ commission
    $commission_percent = floatval(getReferralSetting($conn, 'commission_percent', 10));
    $max_commission = floatval(getReferralSetting($conn, 'max_commission_per_transaction', 500));
    
    $commission = round($topup_amount * ($commission_percent / 100), 2);
    if ($max_commission > 0 && $commission > $max_commission) {
        $commission = $max_commission;
    }
    
    if ($commission <= 0) {
        return ['success' => false, 'error' => 'Commission เป็น 0'];
    }
    
    try {
        $conn->begin_transaction();
        
        // เพิ่มเครดิตให้ผู้แนะนำ
        $stmt = $conn->prepare("UPDATE users SET credit = credit + ?, total_referral_earnings = total_referral_earnings + ? WHERE id = ?");
        $stmt->bind_param("ddi", $commission, $commission, $referrer_id);
        $stmt->execute();
        $stmt->close();
        
        // บันทึก earning
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $stmt = $conn->prepare("INSERT INTO referral_earnings (referrer_id, referred_id, topup_transaction_id, topup_amount, commission_percent, commission_amount, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiddds", $referrer_id, $user_id, $transaction_id, $topup_amount, $commission_percent, $commission, $ip);
        $stmt->execute();
        $stmt->close();
        
        // Log
        logReferralAction($conn, $referrer_id, 'earn_commission', $user_id, null, $commission, $commission, "Commission from topup #$transaction_id");
        
        // แจ้งเตือน
        $notify_title = "รายได้จากการแนะนำ!";
        $notify_msg = "เพื่อนเติมเงิน ฿" . number_format($topup_amount, 2) . " คุณได้รับ ฿" . number_format($commission, 2) . " ({$commission_percent}%)";
        $conn->query("INSERT INTO notifications (user_id, type, title, message) VALUES ($referrer_id, 'success', '$notify_title', '$notify_msg')");
        
        $conn->commit();
        
        return [
            'success' => true, 
            'commission' => $commission,
            'referrer_id' => $referrer_id,
            'referrer_name' => $referrer['username']
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

/**
 * Admin: ล็อค/ปลดล็อค referral ของ user
 */
function adminToggleReferralLock($conn, $admin_id, $user_id, $lock = true) {
    try {
        $lock_val = $lock ? 1 : 0;
        $stmt = $conn->prepare("UPDATE users SET referral_locked = ? WHERE id = ?");
        $stmt->bind_param("ii", $lock_val, $user_id);
        $stmt->execute();
        $stmt->close();
        
        $action = $lock ? 'admin_lock_referral' : 'admin_unlock_referral';
        logReferralAction($conn, $admin_id, $action, $user_id, null, $lock_val, null, "Admin action");
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Admin: เปลี่ยนผู้แนะนำ
 */
function adminChangeReferrer($conn, $admin_id, $user_id, $new_referrer_id) {
    try {
        // ดึงค่าเดิม
        $stmt = $conn->prepare("SELECT referred_by FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $old = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $old_referrer = $old['referred_by'] ?? null;
        
        // Update
        if ($new_referrer_id === null || $new_referrer_id === 0) {
            $stmt = $conn->prepare("UPDATE users SET referred_by = NULL WHERE id = ?");
            $stmt->bind_param("i", $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET referred_by = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_referrer_id, $user_id);
        }
        $stmt->execute();
        $stmt->close();
        
        logReferralAction($conn, $admin_id, 'admin_change_referrer', $user_id, $old_referrer, $new_referrer_id, null, "Admin changed referrer");
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * ดึงสถิติ Referral ของ User
 */
function getUserReferralStats($conn, $user_id) {
    $stats = [
        'referral_code' => null,
        'total_friends' => 0,
        'total_earnings' => 0,
        'this_month_earnings' => 0,
        'has_referrer' => false,
        'referrer_name' => null
    ];
    
    try {
        // ข้อมูล user
        $stmt = $conn->prepare("SELECT referral_code, total_referral_earnings, referred_by FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($user) {
            $stats['referral_code'] = $user['referral_code'];
            $stats['total_earnings'] = floatval($user['total_referral_earnings'] ?? 0);
            $stats['has_referrer'] = !empty($user['referred_by']);
            
            // หาชื่อ referrer
            if ($stats['has_referrer']) {
                $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->bind_param("i", $user['referred_by']);
                $stmt->execute();
                $ref = $stmt->get_result()->fetch_assoc();
                $stats['referrer_name'] = $ref['username'] ?? null;
                $stmt->close();
            }
        }
        
        // นับเพื่อน
        $stmt = $conn->prepare("SELECT COUNT(*) as c FROM users WHERE referred_by = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stats['total_friends'] = $stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();
        
        // รายได้เดือนนี้
        $stmt = $conn->prepare("SELECT SUM(commission_amount) as c FROM referral_earnings WHERE referrer_id = ? AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stats['this_month_earnings'] = floatval($result['c'] ?? 0);
        $stmt->close();
        
    } catch (Exception $e) {}
    
    return $stats;
}
