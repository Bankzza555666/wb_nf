<?php
// controller/auth_check.php
require_once __DIR__ . '/config.php'; // (config.php จะเริ่ม session_start() ให้เอง) — ใช้ __DIR__ เพื่อให้ path ตรงบนโฮส Linux/Production

/**
 * ตรวจสอบว่าผู้ใช้ล็อกอินหรือยัง (ผ่าน Session หรือ Cookie)
 * @return bool
 */
function isAuthenticated() {
    // 1. ตรวจสอบ Session ปกติ
    if (isset($_SESSION['user_id'])) {
        return true;
    }

    // 2. ถ้าไม่มี Session, ตรวจสอบ Cookie "Remember Me"
    if (isset($_COOKIE['remember_me'])) {
        $parts = explode(':', (string) $_COOKIE['remember_me'], 2);
        if (count($parts) !== 2) {
            return false;
        }
        $selector = $parts[0];
        $validator = $parts[1];
        if (empty($selector) || empty($validator)) {
            return false;
        }
        
        global $conn; // ดึง $conn จาก config.php
        
        $stmt = $conn->prepare("SELECT * FROM auth_tokens WHERE selector = ? AND expires >= NOW()");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $token_data = $result->fetch_assoc();

            // 3. ตรวจสอบ Validator (Token ที่แท้จริง)
            if (password_verify($validator, $token_data['hashed_validator'])) {
                
                // --- ล็อกอินสำเร็จ ---
                // ดึงข้อมูลผู้ใช้
                $stmt_user = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt_user->bind_param("i", $token_data['user_id']);
                $stmt_user->execute();
                $user_result = $stmt_user->get_result();
                $user = $user_result->fetch_assoc();
                
                // 4. สร้าง Session ขึ้นมาใหม่
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                if (!empty($user['role'])) $_SESSION['role'] = $user['role'];
                
                // (Optional but recommended: สร้าง Token ใหม่เพื่อความปลอดภัย)
                // ... (ละไว้ก่อนเพื่อความง่าย) ...
                
                $stmt_user->close();
                $stmt->close();
                return true;
            }
        }
        $stmt->close();
    }
    
    // ถ้าไม่มีทั้ง Session และ Cookie ที่ถูกต้อง
    return false;
}

?>