<?php
// Server_price/rent_ssh.php (Redesigned UI based on custom_rental.php)
// หน้าเช่า SSH/NPV - Theme Aurora Glow
// Updated: Fix text color to White/WhiteSmoke

require_once 'controller/auth_check.php';
require_once 'controller/config.php';

$user_id = $_SESSION['user_id'];

// ดึงข้อมูล User
$stmt = $conn->prepare("SELECT username, email, credit FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ดึงข้อมูล Servers
$servers_query = "SELECT * FROM ssh_servers WHERE is_active = 1 ORDER BY server_name ASC";
$servers_result = $conn->query($servers_query);

// ดึงข้อมูล Products ทั้งหมด (จะ filter ด้วย JS ตาม server ที่เลือก)
// JOIN กับ image_id ต้อง > 0 เพื่อไม่ให้ image_id = 0 match กับรูปที่ไม่เกี่ยวข้อง
$products_query = "SELECT p.*, s.server_name, s.location, img.filename as image_filename 
                   FROM ssh_products p 
                   LEFT JOIN ssh_servers s ON p.server_id = s.server_id 
                   LEFT JOIN product_images img ON p.image_id = img.id AND p.image_id > 0
                   WHERE p.is_active = 1 
                   ORDER BY p.id ASC";
$products_result = $conn->query($products_query);
$products = [];
while ($p = $products_result->fetch_assoc()) {
    $products[] = $p;
}

// นับ active users per server
$user_counts = [];
$count_query = "SELECT server_id, COUNT(*) as active_users 
                FROM ssh_rentals 
                WHERE status = 'active' AND expire_date > NOW() 
                GROUP BY server_id";
$count_result = $conn->query($count_query);
while ($row = $count_result->fetch_assoc()) {
    $user_counts[$row['server_id']] = $row['active_users'];
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เช่า SSH/NPV - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons/css/flag-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/alert-helper.js"></script>
    <style>
        :root {
            /* --- Aurora Glow Palette (Teal Theme) --- */
            --primary-color: #009688;
            --primary-glow: #14b8a6;
            --primary-gradient: linear-gradient(135deg, #14b8a6, #0d9488);
            --glow-shadow: 0 0 25px rgba(20, 184, 166, 0.4);
            
            --success-color: #28a745;
            --success-color-glass: rgba(40, 167, 69, 0.2);

            /* --- Darker Theme Variables (Deep Midnight) --- */
            --dark-bg: #020617; /* Deep Slate */
            --dark-text: #f5f5f5; /* White Smoke - แก้ไขให้สว่างขึ้น */
            --dark-muted: #cbd5e1; /* Light Gray - แก้ไขให้สว่างขึ้นเพื่อให้อ่านง่ายบนพื้นดำ */

            /* --- Glassmorphism Variables --- */
            --glass-bg: rgba(15, 23, 42, 0.85); /* เพิ่มความทึบนิดหน่อยเพื่อให้อ่านง่าย */
            --glass-border: rgba(255, 255, 255, 0.1);
            --glass-blur: blur(16px);
            --dark-hover: rgba(30, 41, 59, 0.9);
        }

        body {
            background: var(--bg-body, var(--dark-bg));
            color: var(--dark-text);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        /* --- Global Text Overrides (Fix Black Text) --- */
        h1, h2, h3, h4, h5, h6, .h1, .h2, .h3, .h4, .h5, .h6 {
            color: #ffffff;
            font-weight: 700;
        }
        
        p, span, div, label, li {
            color: var(--dark-text);
        }

        .text-muted {
            color: var(--dark-muted) !important;
        }
        
        .text-white {
            color: #ffffff !important;
        }

        /* --- Utilities --- */
        .glass-card {
            background: var(--glass-bg);
            -webkit-backdrop-filter: var(--glass-blur);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }

        /* --- Header --- */
        .page-title {
            color: #ffffff;
            font-size: 42px;
            font-weight: bold;
            margin-bottom: 10px;
            text-shadow: 0 0 10px rgba(20, 184, 166, 0.3);
        }

        .page-subtitle {
            font-size: 18px;
            color: var(--dark-muted);
            margin-bottom: 40px;
        }

        @media (max-width: 767.98px) {
            .page-title { font-size: 32px; }
            .balance-amount { font-size: 28px; }
        }

        /* --- Balance Card --- */
        .balance-card {
            padding: 20px 25px;
            margin-bottom: 30px;
            border-left: 5px solid var(--primary-glow);
        }

        .balance-amount {
            font-size: 36px;
            font-weight: bold;
            color: var(--primary-glow);
            text-shadow: 0 0 10px rgba(20, 184, 166, 0.3);
        }

        /* --- Wizard Nav --- */
        .wizard-nav {
            padding: 10px;
            margin-bottom: 30px;
        }

        .wizard-nav .nav-link {
            padding: 15px 10px;
            border-radius: 12px;
            color: var(--dark-muted);
            font-weight: 600;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            background: transparent;
        }

        .wizard-nav .nav-link .step-number {
            width: 30px;
            height: 30px;
            background: var(--glass-border);
            color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .wizard-nav .nav-link:not(.disabled):hover { background: var(--dark-hover); cursor: pointer; color: #fff; }

        .wizard-nav .nav-link.active {
            color: white;
            background: var(--primary-gradient);
            box-shadow: 0 4px 10px rgba(20, 184, 166, 0.4);
        }
        .wizard-nav .nav-link.active .step-number { background: rgba(255, 255, 255, 0.2); color: white; }

        .wizard-nav .nav-link.completed { color: #d4edda; background: var(--success-color-glass); }
        .wizard-nav .nav-link.completed .step-number { background: var(--success-color); color: white; }
        
        .wizard-nav .nav-link.disabled { color: #64748b; cursor: not-allowed; background: rgba(10, 15, 25, 0.5); }

        /* --- Enhanced Flag Styling --- */
        .flag-container {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 70px;
            height: 70px;
            border-radius: 16px;
            background: linear-gradient(145deg, rgba(20, 184, 166, 0.1), rgba(20, 184, 166, 0.05));
            border: 2px solid rgba(20, 184, 166, 0.3);
            box-shadow: 
                0 4px 15px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .flag-container::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), transparent);
            pointer-events: none;
        }

        .server-option:hover .flag-container {
            transform: scale(1.08);
            border-color: rgba(20, 184, 166, 0.6);
            box-shadow: 
                0 6px 25px rgba(20, 184, 166, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .server-option.selected .flag-container {
            border-color: var(--primary-glow);
            background: linear-gradient(145deg, rgba(20, 184, 166, 0.2), rgba(20, 184, 166, 0.1));
            box-shadow: 
                0 0 30px rgba(20, 184, 166, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .flag-icon {
            font-size: 2.8em !important;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease;
        }

        .flag-badge {
            position: absolute;
            bottom: -4px;
            right: -4px;
            width: 20px;
            height: 20px;
            background: var(--primary-glow);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--dark-bg);
            font-size: 10px;
            color: white;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
        }

        /* --- Server Option (Step 1) - Enhanced --- */
        .server-option {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.5), rgba(15, 23, 42, 0.4));
            border: 2px solid var(--glass-border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            display: flex;
            align-items: center;
            height: 100%;
            color: var(--dark-text);
            overflow: hidden;
        }

        .server-option::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--primary-glow), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .server-option:hover {
            border-color: rgba(20, 184, 166, 0.5);
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.7), rgba(20, 184, 166, 0.1));
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3), 0 0 20px rgba(20, 184, 166, 0.15);
        }

        .server-option:hover::before {
            opacity: 1;
        }

        .server-option.selected {
            border-color: var(--primary-glow);
            background: linear-gradient(145deg, rgba(20, 184, 166, 0.15), rgba(15, 23, 42, 0.6));
            box-shadow: 
                var(--glow-shadow),
                0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .server-option.selected::before {
            opacity: 1;
            width: 5px;
            background: linear-gradient(180deg, var(--primary-glow), rgba(20, 184, 166, 0.3));
        }

        /* --- Server Info Styling --- */
        .server-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 8px;
            letter-spacing: -0.3px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .server-option:hover .server-name {
            color: var(--primary-glow);
        }

        .server-location {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #94a3b8;
            font-size: 0.9rem;
            margin-bottom: 6px;
        }

        .server-location i {
            color: var(--primary-glow);
            font-size: 0.85rem;
        }

        .server-users {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            font-size: 0.85rem;
        }

        .server-users i {
            color: #10b981;
            font-size: 0.85rem;
        }

        .user-count {
            color: var(--primary-glow);
            font-weight: 600;
        }

        .server-option input[type="radio"] { display: none; }

        .server-status {
            position: absolute;
            top: 15px;
            right: 15px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            background: rgba(40, 167, 69, 0.2);
            color: #4ade80;
            border: 1px solid #28a745;
        }
        .status-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            background: #4ade80;
            border-radius: 50%;
            margin-right: 5px;
            animation: pulse 2s infinite;
        }

        /* --- Product Option (Step 2) - Premium Redesign --- */
        .product-option {
            position: relative;
            background: linear-gradient(145deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.8));
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 0;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            height: 100%;
            color: var(--dark-text);
            overflow: hidden;
        }

        .product-option::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #14b8a6, #06b6d4, #8b5cf6);
            opacity: 0.6;
            transition: all 0.3s ease;
        }

        .product-option::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(20, 184, 166, 0.05), transparent 50%);
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }

        .product-option:hover {
            transform: translateY(-6px) scale(1.02);
            border-color: rgba(20, 184, 166, 0.4);
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.4),
                0 0 30px rgba(20, 184, 166, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        .product-option:hover::before {
            opacity: 1;
            height: 5px;
        }

        .product-option:hover::after {
            opacity: 1;
        }

        .product-option.selected {
            border-color: var(--primary-glow);
            background: linear-gradient(145deg, rgba(20, 184, 166, 0.15), rgba(15, 23, 42, 0.95));
            box-shadow: 
                0 0 0 2px rgba(20, 184, 166, 0.3),
                0 20px 40px rgba(0, 0, 0, 0.4),
                0 0 50px rgba(20, 184, 166, 0.2);
        }

        .product-option.selected::before {
            opacity: 1;
            height: 5px;
            background: linear-gradient(90deg, #14b8a6, #06b6d4, #14b8a6);
        }
        
        .product-option input[type="radio"] { display: none; }

        .product-card-inner {
            padding: 24px;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .product-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .product-icon-wrapper {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, rgba(20, 184, 166, 0.2), rgba(6, 182, 212, 0.1));
            border: 1px solid rgba(20, 184, 166, 0.3);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .product-option:hover .product-icon-wrapper {
            transform: rotate(5deg) scale(1.1);
            box-shadow: 0 0 20px rgba(20, 184, 166, 0.3);
        }

        .product-icon-wrapper i {
            font-size: 24px;
            background: linear-gradient(135deg, #14b8a6, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .product-badge-wrapper {
            display: flex;
            flex-direction: column;
            gap: 6px;
            align-items: flex-end;
        }

        .product-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-popular {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: #000;
            box-shadow: 0 4px 15px rgba(251, 191, 36, 0.3);
        }

        .badge-new {
            background: linear-gradient(135deg, #8b5cf6, #a855f7);
            color: #fff;
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);
        }

        .product-content {
            flex-grow: 1;
        }

        .product-name {
            font-size: 20px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .product-description {
            color: var(--dark-muted);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 16px;
            min-height: 2.5em;
        }

        .product-features {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
        }

        .feature-tag {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 11px;
            color: var(--dark-muted);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .feature-tag i {
            color: var(--primary-glow);
            font-size: 10px;
        }

        .product-footer {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }

        .price-section {
            display: flex;
            align-items: baseline;
            gap: 4px;
        }

        .price-currency {
            font-size: 16px;
            color: var(--primary-glow);
            font-weight: 600;
        }

        .price-amount {
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, #14b8a6, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
        }

        .price-period {
            font-size: 14px;
            color: var(--dark-muted);
        }

        .select-indicator {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .select-indicator i {
            color: rgba(255, 255, 255, 0.3);
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .product-option:hover .select-indicator {
            border-color: var(--primary-glow);
            background: rgba(20, 184, 166, 0.1);
        }

        .product-option:hover .select-indicator i {
            color: var(--primary-glow);
        }

        .product-option.selected .select-indicator {
            background: var(--primary-gradient);
            border-color: transparent;
        }

        .product-option.selected .select-indicator i {
            color: #fff;
        }

        /* Shimmer effect on hover */
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .product-option .shimmer {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.03),
                transparent
            );
            transform: translateX(-100%);
            pointer-events: none;
        }

        .product-option:hover .shimmer {
            animation: shimmer 1.5s ease-in-out;
        }

        /* --- Stepper & Slider (Step 3) --- */
        .slider-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .slider-value { font-size: 24px; color: var(--primary-glow); font-weight: bold; }

        .stepper-input {
            display: flex;
            align-items: center;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
            padding: 5px;
            border: 1px solid var(--glass-border);
            margin-bottom: 20px;
            max-width: 300px;
            margin-left: auto;
            margin-right: auto;
        }

        .stepper-btn {
            background: var(--primary-gradient);
            border: none;
            color: white;
            font-weight: bold;
            font-size: 20px;
            border-radius: 8px;
            width: 45px;
            height: 45px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .stepper-btn:hover:not(:disabled) { box-shadow: var(--glow-shadow); }
        .stepper-btn:disabled { background: #334155; color: #64748b; cursor: not-allowed; }

        .stepper-input input {
            width: 100%;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-glow);
            background: transparent;
            border: none;
        }
        
        /* Fix input arrow/controls color */
        .stepper-input input::-webkit-outer-spin-button,
        .stepper-input input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .preset-days .btn-outline-preset {
            border: 1px solid var(--glass-border);
            color: var(--dark-text);
            margin: 0 5px;
            border-radius: 20px;
            transition: 0.3s;
        }
        .preset-days .btn-outline-preset:hover {
            border-color: var(--primary-glow);
            color: white;
            background: rgba(20, 184, 166, 0.2);
        }

        /* --- Summary --- */
        .price-summary { padding: 25px; position: sticky; top: 20px; color: var(--dark-text); }
        
        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--glass-border);
            color: var(--dark-text); /* Ensure white smoke color */
        }
        .price-row span {
            color: var(--dark-text);
        }
        
        .price-row:last-child {
            border-bottom: none;
            font-size: 20px;
            font-weight: bold;
            color: var(--primary-glow);
            padding-top: 15px;
            margin-top: 10px;
            border-top: 2px solid var(--primary-glow);
        }

        .btn-rent {
            width: 100%;
            padding: 18px;
            font-size: 18px;
            font-weight: bold;
            background: var(--primary-gradient);
            border: none;
            border-radius: 12px;
            color: white;
            margin-top: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(20, 184, 166, 0.4);
        }
        .btn-rent:hover:not(:disabled) { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(20, 184, 166, 0.6); }
        .btn-rent:disabled { background: #334155; color: #64748b; cursor: not-allowed; box-shadow: none; transform: none; }

        /* --- Responsive Grid --- */
        @media (min-width: 768px) {
            #serverList, #productList { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
            .server-option, .product-option { margin-bottom: 0; }
        }
        @media (max-width: 991.98px) {
            .price-summary { position: static; }
        }

        .step-card-content { padding: 30px; }
        
        .alert-info {
            background: rgba(30, 58, 138, 0.3);
            color: #bfdbfe;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
    <!-- ✅ Site Theme (Admin Selectable) -->
    <?php if (file_exists(__DIR__ . '/../include/theme_head.php')) include __DIR__ . '/../include/theme_head.php'; ?>
</head>

<body>
    <?php include 'home/navbar.php'; ?>

    <div class="container mb-5">
        <div class="text-center mt-5">
            <h1 class="page-title"><i class="fas fa-terminal"></i> เช่า SSH / Netmod</h1>
            <p class="page-subtitle">เลือก Server, แพ็กเกจ และกำหนดจำนวนวันที่ต้องการ</p>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="balance-card glass-card">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                        <div class="mb-3 mb-md-0">
                            <div class="text-muted mb-2"><i class="fas fa-wallet"></i> ยอดเงินคงเหลือ</div>
                            <div class="balance-amount">฿<?php echo number_format($user['credit'], 2); ?></div>
                        </div>
                        <a href="?p=topup" class="btn btn-light text-dark fw-bold">
                            <i class="fas fa-plus-circle text-success"></i> เติมเงิน
                        </a>
                    </div>
                </div>

                <nav class="nav nav-pills nav-fill wizard-nav glass-card" id="wizardTabs" role="tablist">
                    <a class="nav-link active" id="nav-step1" data-bs-toggle="pill" href="#step1-server" role="tab">
                        <span class="step-number">1</span>
                        <span class="step-title">เลือก Server</span>
                    </a>
                    <a class="nav-link disabled" id="nav-step2" data-bs-toggle="pill" href="#step2-product" role="tab">
                        <span class="step-number">2</span>
                        <span class="step-title">เลือกแพ็กเกจ</span>
                    </a>
                    <a class="nav-link disabled" id="nav-step3" data-bs-toggle="pill" href="#step3-days" role="tab">
                        <span class="step-number">3</span>
                        <span class="step-title">กำหนดวัน</span>
                    </a>
                </nav>

                <div class="tab-content" id="wizardContent">
                    
                    <div class="tab-pane fade show active" id="step1-server" role="tabpanel">
                        <div class="step-card-content glass-card">
                            <div id="serverList">
                                <?php while ($server = $servers_result->fetch_assoc()):
                                    $current_users = $user_counts[$server['server_id']] ?? 0;
                                    $max_users = $server['max_clients'] ?? 100;
                                    $is_full = $current_users >= $max_users;
                                ?>
                                    <label class="server-option" data-server='<?php echo json_encode($server); ?>'>
                                        <input type="radio" name="server" value="<?php echo $server['server_id']; ?>" <?php echo $is_full ? 'disabled' : ''; ?>>
                                        
                                        <div class="server-status">
                                            <span class="status-badge" style="<?php echo $is_full ? 'color:#ef4444; border-color:#ef4444;' : ''; ?>">
                                                <?php echo $is_full ? 'FULL' : 'ONLINE'; ?>
                                            </span>
                                        </div>

                                        <div class="d-flex align-items-center w-100">
                                            <div class="me-3">
                                                <div class="flag-container">
                                                    <span class="fi fi-<?php echo $server['country_code'] ?? 'th'; ?> fis flag-icon"></span>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 pe-4">
                                                <div class="server-name"><?php echo htmlspecialchars($server['server_name']); ?></div>
                                                <div class="server-location">
                                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($server['location'] ?? 'Global'); ?>
                                                </div>
                                                <div class="server-users">
                                                    <i class="fas fa-users"></i> <span class="user-count"><?php echo $current_users; ?></span> / <?php echo $max_users; ?> users
                                                </div>
                                            </div>
                                        </div>
                                    </label>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="step2-product" role="tabpanel">
                        <div class="step-card-content glass-card">
                            <div class="alert alert-info text-center" id="productAlert">
                                <i class="fas fa-arrow-left me-2"></i> กรุณาเลือก Server ก่อน
                            </div>
                            <div id="productList">
                                </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="step3-days" role="tabpanel">
                        <div class="step-card-content glass-card text-center">
                            <h4 class="mb-4 text-white"><i class="fas fa-calendar-check me-2"></i>ระยะเวลาเช่า</h4>
                            
                            <div class="slider-label justify-content-center">
                                <span class="slider-value" id="daysValueDisplay">7 วัน</span>
                            </div>

                            <div class="stepper-input">
                                <button type="button" class="stepper-btn" id="days-minus"><i class="fas fa-minus"></i></button>
                                <input type="number" id="daysInput" value="7" min="1" max="365">
                                <button type="button" class="stepper-btn" id="days-plus"><i class="fas fa-plus"></i></button>
                            </div>

                            <div class="preset-days mt-4">
                                <button class="btn btn-sm btn-outline-preset" onclick="setDays(7)">7 วัน</button>
                                <button class="btn btn-sm btn-outline-preset" onclick="setDays(15)">15 วัน</button>
                                <button class="btn btn-sm btn-outline-preset" onclick="setDays(30)">30 วัน</button>
                                <button class="btn btn-sm btn-outline-preset" onclick="setDays(90)">90 วัน</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mt-4 mt-lg-0">
                <div class="price-summary glass-card">
                    <h5 class="mb-4 text-white"><i class="fas fa-receipt me-2"></i>สรุปรายการ</h5>

                    <div id="summaryContent">
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-shopping-basket fa-3x mb-3 opacity-50"></i>
                            <p>กรุณาเลือกรายการ</p>
                        </div>
                    </div>

                    <button id="rentBtn" class="btn btn-rent" disabled>
                        <i class="fas fa-check-circle me-2"></i> ยืนยันการเช่า
                    </button>

                    <div class="mt-3 p-3 rounded" style="background: rgba(30, 58, 138, 0.2); border-left: 3px solid #3b82f6;">
                        <small class="text-info-emphasis">
                            <i class="fas fa-info-circle me-1"></i> <strong>หมายเหตุ:</strong><br>
                            • ระบบจะสร้างบัญชีและส่ง Config ให้ทันที<br>
                            • รองรับทุกอุปกรณ์ (PC, Mobile)
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // --- SweetAlert2 Dark Theme Override ---
        Swal.mixin({
            background: '#0f172a',
            color: '#f1f5f9',
            confirmButtonColor: '#14b8a6',
            cancelButtonColor: '#64748b'
        });

        // Data & Variables
        const allProducts = <?php echo json_encode($products); ?>;
        let selectedServer = null;
        let selectedProduct = null;
        let selectedDays = 7;
        let userBalance = <?php echo $user['credit']; ?>;

        const tabStep2 = new bootstrap.Tab('#nav-step2');
        const tabStep3 = new bootstrap.Tab('#nav-step3');

        const defaultSummaryHtml = `
            <div class="text-center py-5 text-muted">
                <i class="fas fa-shopping-basket fa-3x mb-3 opacity-50"></i>
                <p>กรุณาเลือกรายการ</p>
            </div>
        `;

        // --- Event Delegation ---
        $(document).on('click', '.server-option:not(:has(input:disabled))', function() {
            $('.server-option').removeClass('selected');
            $(this).addClass('selected');
            $(this).find('input[type="radio"]').prop('checked', true);

            selectedServer = $(this).data('server');
            selectedProduct = null;

            $('#nav-step2').removeClass('disabled');
            $('#nav-step1').addClass('completed');
            
            // Reset subsequent steps
            $('#nav-step3').addClass('disabled').removeClass('completed');
            $('#summaryContent').html(defaultSummaryHtml);
            $('#rentBtn').prop('disabled', true);

            loadProducts(selectedServer.server_id);
            setTimeout(() => tabStep2.show(), 200);
        });

        $(document).on('click', '#days-minus', () => updateDays(selectedDays - 1));
        $(document).on('click', '#days-plus', () => updateDays(selectedDays + 1));
        $(document).on('input', '#daysInput', function() { updateDays(parseInt($(this).val()) || 1); });

        // Functions
        function loadProducts(serverId) {
            const serverProducts = allProducts.filter(p => p.server_id == serverId);

            if (serverProducts.length === 0) {
                $('#productAlert').show().html('<i class="fas fa-exclamation-circle"></i> ไม่มีแพ็กเกจสำหรับ Server นี้');
                $('#productList').html('');
                return;
            }

            $('#productAlert').hide();

            let html = '';
            serverProducts.forEach(product => {
                // Parse description for features if available
                const description = product.description || 'High Speed Config';

                
                let features = [];
                try {
                    if (product.features) {
                        features = JSON.parse(product.features);
                    }
                } catch(e) { }
                
                if (!Array.isArray(features) || features.length === 0) {
                     features = ['รองรับ PC/Mobile', 'ความเร็วสูง', 'Auto Setup'];
                }
                
                const iconHtml = product.image_filename 
                    ? `<img src="img/products/${product.image_filename}" style="width:100%; height:100%; object-fit:cover; border-radius:14px;">`
                    : `<i class="fas fa-bolt"></i>`;
                
                html += `
                    <label class="product-option" data-product='${JSON.stringify(product)}'>
                        <input type="radio" name="product" value="${product.id}">
                        <div class="shimmer"></div>
                        <div class="product-card-inner">
                            <div class="product-header">
                                <div class="product-icon-wrapper" ${product.image_filename ? 'style="padding:0; overflow:hidden;"' : ''}>
                                    ${iconHtml}
                                </div>
                                <div class="product-badge-wrapper">
                                    ${product.is_popular ? '<span class="product-badge badge-popular"><i class="fas fa-fire me-1"></i>แนะนำ</span>' : ''}
                                </div>
                            </div>
                            <div class="product-content">
                                <h4 class="product-name">${product.product_name}</h4>
                                <p class="product-description">${description}</p>
                                <div class="product-features">
                                    ${features.map(f => `<span class="feature-tag"><i class="fas fa-check"></i>${f}</span>`).join('')}
                                </div>
                            </div>
                            <div class="product-footer">
                                <div class="price-section">
                                    <span class="price-currency">฿</span>
                                    <span class="price-amount">${parseFloat(product.price_per_day).toFixed(2)}</span>
                                    <span class="price-period">/วัน</span>
                                </div>
                                <div class="select-indicator">
                                    <i class="fas fa-arrow-right"></i>
                                </div>
                            </div>
                        </div>
                    </label>
                `;
            });

            $('#productList').html(html);

            // Bind click for dynamically added products
            $('.product-option').click(function() {
                $('.product-option').removeClass('selected');
                $(this).addClass('selected');
                $(this).find('input[type="radio"]').prop('checked', true);

                selectedProduct = $(this).data('product');

                $('#nav-step3').removeClass('disabled');
                $('#nav-step2').addClass('completed');

                updateSummary();
                setTimeout(() => tabStep3.show(), 200);
            });
        }

        function updateDays(newDays) {
            selectedDays = Math.max(1, Math.min(365, newDays));
            $('#daysInput').val(selectedDays);
            $('#daysValueDisplay').text(selectedDays + ' วัน');
            $('#days-minus').prop('disabled', selectedDays <= 1);
            $('#days-plus').prop('disabled', selectedDays >= 365);
            updateSummary();
        }

        function setDays(days) {
            updateDays(days);
        }

        function updateSummary() {
            if (!selectedServer || !selectedProduct) {
                $('#summaryContent').html(defaultSummaryHtml);
                $('#rentBtn').prop('disabled', true);
                return;
            }

            const pricePerDay = parseFloat(selectedProduct.price_per_day);
            const totalPrice = pricePerDay * selectedDays;
            const canAfford = userBalance >= totalPrice;

            $('#summaryContent').html(`
                <div class="price-row">
                    <span>Server</span>
                    <span class="text-white">${selectedServer.server_name}</span>
                </div>
                <div class="price-row">
                    <span>แพ็กเกจ</span>
                    <span class="text-white">${selectedProduct.product_name}</span>
                </div>
                <div class="price-row">
                    <span>ระยะเวลา</span>
                    <span class="text-white">${selectedDays} วัน</span>
                </div>
                <div class="price-row">
                    <span>ยอดรวม</span>
                    <span class="text-success fs-4">฿${totalPrice.toFixed(2)}</span>
                </div>
                ${!canAfford ? `<div class="alert alert-danger mt-3 py-2 text-center small border-0 bg-danger bg-opacity-25 text-danger"><i class="fas fa-exclamation-triangle"></i> ยอดเงินไม่เพียงพอ</div>` : ''}
            `);

            $('#rentBtn').prop('disabled', !canAfford);
        }

        // Process Rental
        $('#rentBtn').click(function() {
            if (!selectedServer || !selectedProduct) return;

            Alert.confirm(
                'ยืนยันการเช่า?', 
                `เช่า ${selectedProduct.product_name} (${selectedDays} วัน)`, 
                'ยืนยันชำระเงิน', 
                'ยกเลิก'
            ).then(result => {
                if (result.isConfirmed) {
                    processRental();
                }
            });
        });

        function processRental() {
            Alert.loading('กำลังประมวลผล...');

            $.ajax({
                url: 'controller/rent_ssh_controller.php',
                method: 'POST',
                data: {
                    action: 'rent',
                    product_id: selectedProduct.id,
                    days: selectedDays,
                    custom_name: ''
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'เช่าสำเร็จ!',
                            text: 'ระบบได้สร้างบัญชีของคุณเรียบร้อยแล้ว',
                            background: '#0f172a',
                            color: '#fff',
                            confirmButtonColor: '#14b8a6',
                            confirmButtonText: 'ไปที่หน้า SSH ของฉัน'
                        }).then(() => {
                            window.location.href = '?p=my_ssh';
                        });
                    } else {
                        Alert.error('เกิดข้อผิดพลาด', response.message);
                    }
                },
                error: function() {
                    Alert.error('Connection Error', 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้');
                }
            });
        }

        // Init
        updateDays(7);
        // Prevent tab click if disabled
        $('a[data-bs-toggle="pill"]').on('show.bs.tab', function(e) { 
            if ($(e.target).hasClass('disabled')) e.preventDefault(); 
        });
    </script>
</body>
</html>