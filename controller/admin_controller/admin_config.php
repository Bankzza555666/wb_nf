<?php
// controller/admin_controller/admin_config.php
// ใช้ config.php หลักเพื่อเชื่อมต่อฐานข้อมูล (ไม่ซ้ำซ้อน)

// ไม่ส่ง header เมื่อถูกเรียกจาก admin_api.php (ให้ส่ง JSON แทน)
if (!defined('ADMIN_API_REQUEST')) {
    header('Content-Type: text/html; charset=utf-8');
}

// เรียกใช้ config.php หลัก (มี DB connection, timezone, session อยู่แล้ว)
require_once __DIR__ . '/../config.php';

function checkAdminAuth()
{
    global $conn;
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../../index.php?r=landing");
        exit;
    }

    $uid = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();

    if (!$user || $user['role'] !== 'admin') {
        echo "<div style='background:#0f172a;color:#ef4444;height:100vh;display:flex;justify-content:center;align-items:center;'><h1>Access Denied</h1></div>";
        exit;
    }
}
?>