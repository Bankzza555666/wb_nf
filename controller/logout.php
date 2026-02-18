<?php
// controller/logout.php

require_once 'config.php'; // (config.php จะเริ่ม session_start() ให้)

// --- (ใหม่) ตรวจสอบการเชื่อมต่อ ---
// (เราจะลบ Token ก็ต่อเมื่อ 1. มี Cookie และ 2. DB เชื่อมต่อสำเร็จ)
if (isset($_COOKIE['remember_me']) && $conn && !$conn->connect_error) {
    
    list($selector, $validator) = explode(':', $_COOKIE['remember_me']);
    
    // (ใช้ try...catch ดักจับ Error เผื่อตารางพัง)
    try {
        $stmt = $conn->prepare("DELETE FROM auth_tokens WHERE selector = ?");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        // (ถ้าพังก็ไม่เป็นไร ปล่อยผ่าน)
    }
}
// --- จบส่วนแก้ไข ---

// 2. ลบ Cookie ออกจากบราวเซอร์ (ทำทุกครั้ง)
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, "/"); // ตั้งเวลาเป็นอดีต
}

// 3. ทำลาย Session (ทำทุกครั้ง)
session_unset();
session_destroy();

// 4. ส่งกลับไปหน้า Landing (ผ่าน Router)
// (Path นี้ถูกต้องแล้ว เพราะ ../ คือถอยจาก controller/ กลับไปที่ Root)
header('Location: ../?r=landing');
exit;
?>