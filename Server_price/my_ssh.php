<?php
// Server_price/my_ssh.php
// หน้า SSH ของฉัน - แสดง Dual Configs (SSH + NPV)
// แก้ไข: ตัดช่องว่าง (Trim) เวลา Copy Config

require_once 'controller/auth_check.php';
require_once 'controller/config.php';
require_once 'controller/ssh_api/ssh_config_generator.php';

$user_id = $_SESSION['user_id'];

// ดึงข้อมูลเครดิต
$stmt = $conn->prepare("SELECT credit FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_credit = $stmt->get_result()->fetch_assoc()['credit'];

// ลบ SSH ที่หมดอายุมาแล้ว 2 วันอัตโนมัติ (เหมือน my_vpn)
$delete_expired_query = "
    DELETE FROM ssh_rentals 
    WHERE user_id = ? 
    AND expire_date < DATE_SUB(NOW(), INTERVAL 2 DAY)
";
$stmt_delete = $conn->prepare($delete_expired_query);
$stmt_delete->bind_param("i", $user_id);
$stmt_delete->execute();
$deleted_count = $stmt_delete->affected_rows;
$stmt_delete->close();

// ดึงข้อมูล rentals พร้อม seconds_until_delete สำหรับ countdown (เหมือน my_vpn)
$sql = "SELECT r.*, p.product_name, p.price_per_day, p.ssh_config_template, p.npv_config_template, s.server_name, s.location,
        img.filename as product_image,
        TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(r.expire_date, INTERVAL 2 DAY)) as seconds_until_delete
        FROM ssh_rentals r
        LEFT JOIN ssh_products p ON r.product_id = p.id
        LEFT JOIN ssh_servers s ON r.server_id = s.server_id
        LEFT JOIN product_images img ON p.image_id = img.id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rentals_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSH ของฉัน -
        <?php echo SITE_NAME; ?>
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/alert-helper.js"></script>
    <style>
        :root {
            --primary: #E50914;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark-700: rgba(20, 20, 20, 0.9);
            --dark-600: rgba(30, 30, 30, 0.95);
            --glass-border: rgba(229, 9, 20, 0.2);
        }

        body {
            background: var(--bg-body, #0a0a0a);
            min-height: 100vh;
        }

        .page-header {
            text-align: center;
            padding: 60px 0 40px;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff, #E50914);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .ssh-card {
            background: var(--dark-600);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .ssh-card:hover {
            box-shadow: 0 15px 40px rgba(229, 9, 20, 0.15);
        }

        .ssh-card.expired {
            border-color: rgba(239, 68, 68, 0.3);
            opacity: 0.7;
        }

        .ssh-card.active {
            border-color: rgba(16, 185, 129, 0.3);
        }

        .ssh-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 1.5rem;
            background: rgba(229, 9, 20, 0.1);
            border-bottom: 1px solid var(--glass-border);
        }

        .ssh-name {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .ssh-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary), #ff3d47);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .ssh-title {
            font-weight: 700;
            color: white;
            margin: 0;
        }

        .ssh-server {
            font-size: 0.85rem;
            color: #888;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-badge.active {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .status-badge.expired {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .status-badge.suspended {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        .ssh-body {
            padding: 1.5rem;
        }

        .ssh-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .info-item {
            text-align: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
        }

        .info-label {
            font-size: 0.8rem;
            color: #888;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-weight: 600;
            color: white;
        }

        .config-section {
            margin-top: 1.5rem;
        }

        .config-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .config-tab {
            flex: 1;
            padding: 0.75rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .config-tab.ssh {
            background: rgba(59, 130, 246, 0.1);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .config-tab.ssh.active {
            background: rgba(59, 130, 246, 0.3);
        }

        .config-tab.npv {
            background: rgba(168, 85, 247, 0.1);
            color: #c084fc;
            border: 1px solid rgba(168, 85, 247, 0.3);
        }

        .config-tab.npv.active {
            background: rgba(168, 85, 247, 0.3);
        }

        .config-content {
            display: none;
        }

        .config-content.active {
            display: block;
        }

        .config-box {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 1rem;
            position: relative;
        }

        .config-url {
            word-break: break-all;
            font-family: monospace;
            font-size: 0.85rem;
            color: #aaa;
            max-height: 100px;
            overflow-y: auto;
        }

        .copy-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: var(--primary);
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            color: white;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .copy-btn:hover {
            transform: scale(1.05);
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .action-btn {
            flex: 1;
            padding: 0.75rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-extend {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }

        .btn-edit-name {
            background: rgba(99, 102, 241, 0.2);
            color: #818cf8;
            border: 1px solid rgba(99, 102, 241, 0.3);
        }

        .btn-qr {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .btn-cancel {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .btn-cancel:hover {
            background: rgba(239, 68, 68, 0.4);
        }

        .empty-state {
            text-align: center;
            padding: 60px 30px;
            background: var(--dark-600);
            border-radius: 24px;
            border: 1px solid var(--glass-border);
            animation: fadeInUp 0.6s ease-out;
        }

        .empty-state-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, rgba(229, 9, 20, 0.15), rgba(229, 9, 20, 0.05));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            border: 1px solid rgba(229, 9, 20, 0.3);
        }

        .empty-state-icon i {
            font-size: 2rem;
            color: var(--primary);
            animation: float 3s ease-in-out infinite;
        }

        .empty-state h3, .empty-state h4 {
            color: #fff;
            margin-bottom: 12px;
            font-weight: 700;
        }

        .empty-state-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: linear-gradient(135deg, var(--primary), #b20710);
            color: white;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(229, 9, 20, 0.3);
            margin-top: 16px;
        }

        .empty-state-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(229, 9, 20, 0.4);
            color: white;
        }

        .empty-state-btn i {
            font-size: 1rem;
        }

        .custom-name-display {
            padding: 0.25rem 0.75rem;
            background: rgba(99, 102, 241, 0.2);
            border-radius: 20px;
            color: #a5b4fc;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }

        /* แจ้งเตือนถูกลบอัตโนมัติ + countdown (ให้สอดคล้อง my_vpn) */
        .deleted-notification {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(239, 68, 68, 0.05));
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #fca5a5;
        }
        .deleted-notification i { font-size: 1.5rem; color: #f87171; }

        /* Auto-Renew Section - Premium Design */
        .ve-auto-renew-box {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(16, 185, 129, 0.02) 100%);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 20px;
            padding: 28px 32px;
            margin: 28px 0;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 28px;
            flex-wrap: wrap;
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .ve-auto-renew-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--success), #34d399, var(--success));
            opacity: 0.6;
        }

        .ve-auto-renew-box:hover {
            border-color: rgba(16, 185, 129, 0.4);
            box-shadow: 0 8px 32px rgba(16, 185, 129, 0.15);
        }

        .ve-auto-renew-box .ve-auto-renew-content {
            flex: 1;
            min-width: 200px;
        }

        .ve-auto-renew-box .ve-auto-renew-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #fff;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .ve-auto-renew-box .ve-auto-renew-title i {
            color: var(--success);
            font-size: 1.1rem;
            background: rgba(16, 185, 129, 0.15);
            padding: 8px;
            border-radius: 10px;
        }

        .ve-auto-renew-box .ve-auto-renew-desc {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin: 0 0 14px 0;
            line-height: 1.5;
        }

        .ve-auto-renew-box .ve-auto-renew-credit {
            font-size: 0.85rem;
            color: var(--text-muted);
            background: rgba(0, 0, 0, 0.25);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 12px;
            line-height: 1.6;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .ve-auto-renew-box .ve-auto-renew-credit strong {
            color: var(--text-primary);
        }

        .ve-auto-renew-box .ve-auto-renew-credit .credit-amount {
            color: var(--success);
            font-weight: 700;
        }

        .ve-auto-renew-box .ve-auto-renew-how {
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.6;
            padding-left: 10px;
            border-left: 2px solid rgba(16, 185, 129, 0.3);
        }

        .ve-auto-renew-box .ve-auto-renew-switch-wrap {
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
        }

        /* Premium Toggle Switch */
        .ve-toggle-switch {
            position: relative;
            width: 72px;
            height: 38px;
            cursor: pointer;
        }

        .ve-toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .ve-toggle-slider {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, var(--dark-600), var(--dark-700));
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 38px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .ve-toggle-slider::before {
            content: '';
            position: absolute;
            width: 28px;
            height: 28px;
            left: 4px;
            bottom: 3px;
            background: linear-gradient(135deg, #fff 0%, #e0e0e0 100%);
            border-radius: 50%;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.3), 0 1px 3px rgba(0, 0, 0, 0.2);
        }

        .ve-toggle-switch input:checked+.ve-toggle-slider {
            background: linear-gradient(135deg, var(--success), #059669);
            border-color: var(--success);
            box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.2), 0 0 20px rgba(16, 185, 129, 0.4);
        }

        .ve-toggle-switch input:checked+.ve-toggle-slider::before {
            transform: translateX(34px);
            background: linear-gradient(135deg, #fff 0%, #f0f0f0 100%);
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.3), 0 0 8px rgba(16, 185, 129, 0.5);
        }

        .ve-toggle-switch input:focus+.ve-toggle-slider {
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.25);
        }

        .ve-toggle-switch:hover .ve-toggle-slider {
            border-color: rgba(255, 255, 255, 0.2);
        }

        .ve-toggle-switch input:checked:hover+.ve-toggle-slider {
            border-color: rgba(16, 185, 129, 0.8);
        }

        .ve-toggle-status {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 4px 12px;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .ve-toggle-status.status-off {
            color: var(--text-muted);
            background: rgba(255, 255, 255, 0.05);
        }

        .ve-toggle-status.status-on {
            color: var(--success);
            background: rgba(16, 185, 129, 0.15);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .ve-auto-renew-box {
                padding: 18px 20px;
                gap: 16px;
            }

            .ve-auto-renew-box .ve-auto-renew-title {
                font-size: 0.95rem;
            }

            .ve-auto-renew-box .ve-auto-renew-desc {
                font-size: 0.8125rem;
            }

            .ve-auto-renew-box .ve-auto-renew-switch-wrap {
                width: 100%;
                justify-content: flex-end;
            }

            .ve-toggle-switch {
                width: 60px;
                height: 32px;
            }

            .ve-toggle-slider::before {
                width: 24px;
                height: 24px;
                left: 3px;
                bottom: 3px;
            }

            .ve-toggle-switch input:checked+.ve-toggle-slider::before {
                transform: translateX(28px);
            }

            .ve-toggle-status {
                font-size: 0.7rem;
            }
        }

        @media (max-width: 576px) {
            .ve-auto-renew-box {
                flex-direction: column;
                text-align: center;
            }

            .ve-auto-renew-box .ve-auto-renew-switch-wrap {
                justify-content: center;
                margin-top: 10px;
            }
        }

        .delete-countdown-alert {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(239, 68, 68, 0.1));
            border: 1px solid rgba(239, 68, 68, 0.4);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .delete-countdown-alert .alert-content {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #fca5a5;
        }
        .delete-countdown-alert .alert-content i {
            font-size: 1.5rem;
            color: #f87171;
            animation: ssh-pulse 1.5s infinite;
        }
        @keyframes ssh-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .delete-countdown-alert .countdown-timer { display: flex; gap: 8px; }
        .delete-countdown-alert .countdown-box {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 8px 12px;
            text-align: center;
            min-width: 55px;
        }
        .delete-countdown-alert .countdown-box .time-value {
            font-size: 1.4rem;
            font-weight: 700;
            color: #f87171;
            display: block;
            line-height: 1;
        }
        .delete-countdown-alert .countdown-box .time-label {
            font-size: 0.65rem;
            color: #fca5a5;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .alert-inline {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .alert-inline i {
            font-size: 1.2rem;
        }

        .alert-inline.alert-warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(245, 158, 11, 0.05));
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fcd34d;
        }

        @media (max-width: 576px) {
            .delete-countdown-alert { flex-direction: column; text-align: center; padding: 14px; }
            .delete-countdown-alert .alert-content { flex-direction: column; gap: 8px; }
            .delete-countdown-alert .countdown-box { min-width: 45px; padding: 6px 10px; }
            .delete-countdown-alert .countdown-box .time-value { font-size: 1.1rem; }
            .alert-inline { flex-direction: column; gap: 8px; }
            .alert-inline i { font-size: 1rem; }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes pulse-glow {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(229, 9, 20, 0.4);
            }
            50% {
                box-shadow: 0 0 20px 10px rgba(229, 9, 20, 0.1);
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        @keyframes shimmer {
            0% {
                background-position: -200% 0;
            }
            100% {
                background-position: 200% 0;
            }
        }

        /* Page entrance animation */
        .page-header {
            animation: fadeInUp 0.6s ease-out;
        }

        .ssh-card {
            animation: fadeInUp 0.5s ease-out backwards;
        }

        .ssh-card:nth-child(1) { animation-delay: 0.1s; }
        .ssh-card:nth-child(2) { animation-delay: 0.2s; }
        .ssh-card:nth-child(3) { animation-delay: 0.3s; }
        .ssh-card:nth-child(4) { animation-delay: 0.4s; }

        /* Enhanced hover effects */
        .ssh-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .ssh-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(229, 9, 20, 0.2);
        }

        .ssh-card.active:hover {
            border-color: rgba(16, 185, 129, 0.5);
            box-shadow: 0 20px 40px rgba(16, 185, 129, 0.15);
        }

        /* Icon animations */
        .ssh-icon {
            transition: all 0.3s ease;
        }

        .ssh-card:hover .ssh-icon {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 8px 25px rgba(229, 9, 20, 0.4);
        }

        /* Button hover effects */
        .action-btn {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .action-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.4s ease, height 0.4s ease;
        }

        .action-btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }

        /* Status badge animation */
        .status-badge.active {
            animation: pulse-glow 2s infinite;
        }

        /* Config tab animation */
        .config-tab {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .config-tab:hover {
            transform: translateY(-2px);
        }

        .config-content {
            animation: fadeInUp 0.3s ease-out;
        }

        /* Info item hover */
        .info-item {
            transition: all 0.3s ease;
        }

        .info-item:hover {
            background: rgba(255, 255, 255, 0.06);
            transform: translateY(-2px);
        }

        /* Empty state animation */
        .empty-state {
            animation: fadeInUp 0.6s ease-out;
        }

        .empty-icon {
            animation: float 3s ease-in-out infinite;
        }

        /* Loading animation for buttons */
        .btn-loading {
            position: relative;
            pointer-events: none;
        }

        .btn-loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Copy button success animation */
        .copy-btn.copied {
            background: var(--success);
            animation: pulse-glow 0.5s ease;
        }

        /* Auto-renew box animation */
        .ve-auto-renew-box {
            animation: fadeInUp 0.5s ease-out;
        }

        /* Delete countdown animation */
        .delete-countdown-alert {
            animation: slideInLeft 0.4s ease-out;
        }

        .countdown-box {
            transition: all 0.3s ease;
        }

        .countdown-box:hover {
            transform: scale(1.05);
            background: rgba(239, 68, 68, 0.1);
        }
    </style>
    <!-- ✅ Site Theme (Admin Selectable) -->
    <?php if (file_exists(__DIR__ . '/../include/theme_head.php')) include __DIR__ . '/../include/theme_head.php'; ?>
</head>

<body>
    <?php include 'home/navbar.php'; ?>

    <div class="container py-4">
        <div class="page-header">
            <h1 class="page-title">SSH ของฉัน</h1>
            <p class="text-secondary">จัดการ Netmod/Npv Tunnel ที่คุณเช่าอยู่</p>
        </div>

        <?php if ($deleted_count > 0): ?>
            <div class="deleted-notification">
                <i class="fas fa-trash-alt"></i>
                <strong><?php echo $deleted_count; ?> รายการถูกลบอัตโนมัติ</strong> เนื่องจากหมดอายุมาแล้วเกิน 2 วัน
            </div>
        <?php endif; ?>

        <?php if ($rentals_result->num_rows === 0): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-terminal"></i>
                </div>
                <h4 class="text-white mb-3">ยังไม่มี Netmod ที่เช่า</h4>
                <p class="text-secondary mb-4">เริ่มต้นเช่า Netmod/Npv Tunnel เพื่อใช้งานอินเทอร์เน็ตฟรี</p>
                <a href="?p=rent_ssh" class="empty-state-btn">
                    <i class="fas fa-shopping-cart me-2"></i>เช่า Netmod เลย
                </a>
            </div>
        <?php else: ?>
            <?php while ($rental = $rentals_result->fetch_assoc()):
                $is_expired = strtotime($rental['expire_date']) < time();
                $status = $is_expired ? 'expired' : $rental['status'];
                $days_left = max(0, ceil((strtotime($rental['expire_date']) - time()) / 86400));
                $seconds_until_delete = (int) ($rental['seconds_until_delete'] ?? 0);
                $show_countdown = $is_expired && $seconds_until_delete > 0;
                
                // เพิ่มการตรวจสอบสถานะเหมือน my_vpn
                if ($is_expired) {
                    $expire_status = 'expired';
                } elseif ($days_left <= 7) {
                    $expire_status = 'expiring_soon';
                } else {
                    $expire_status = 'active';
                }
                ?>
                <div class="ssh-card <?php echo $status; ?>">
                    <div class="ssh-header">
                        <div class="ssh-name">
                            <?php if (!empty($rental['product_image'])): ?>
                                <img src="img/products/<?php echo htmlspecialchars($rental['product_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($rental['product_name']); ?>" 
                                     style="width: 45px; height: 45px; border-radius: 12px; object-fit: cover; border: 2px solid var(--primary);">
                            <?php else: ?>
                                <div class="ssh-icon"><i class="fas fa-terminal"></i></div>
                            <?php endif; ?>
                            <div>
                                <h5 class="ssh-title">
                                    <?php echo htmlspecialchars($rental['product_name']); ?>
                                    <?php if ($rental['custom_name']): ?>
                                        <span class="custom-name-display">
                                            <?php echo htmlspecialchars($rental['custom_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                </h5>
                                <div class="ssh-server">
                                    <i class="fas fa-server me-1"></i>
                                    <?php echo htmlspecialchars($rental['server_name'] ?? $rental['server_id']); ?>
                                    <?php if ($rental['location']): ?>
                                        • <i class="fas fa-map-marker-alt me-1"></i>
                                        <?php echo htmlspecialchars($rental['location']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <span class="status-badge <?php echo $status; ?>">
                            <?php
                            switch ($status) {
                                case 'active':
                                    echo '<i class="fas fa-check-circle me-1"></i>ใช้งานได้';
                                    break;
                                case 'expired':
                                    echo '<i class="fas fa-times-circle me-1"></i>หมดอายุ';
                                    break;
                                case 'suspended':
                                    echo '<i class="fas fa-pause-circle me-1"></i>ถูกระงับ';
                                    break;
                                default:
                                    echo $status;
                            }
                            ?>
                        </span>
                    </div>

                    <div class="ssh-body">
                        <?php if ($expire_status === 'expired' && $seconds_until_delete > 0): ?>
                            <div class="delete-countdown-alert" data-seconds="<?php echo $seconds_until_delete; ?>"
                                data-rental-id="<?php echo (int) $rental['id']; ?>">
                                <div class="alert-content">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <div>
                                        <strong>SSH หมดอายุแล้ว!</strong><br>
                                        <small>จะถูกลบอัตโนมัติใน</small>
                                    </div>
                                </div>
                                <div class="countdown-timer">
                                    <div class="countdown-box">
                                        <span class="time-value days">00</span>
                                        <span class="time-label">วัน</span>
                                    </div>
                                    <div class="countdown-box">
                                        <span class="time-value hours">00</span>
                                        <span class="time-label">ชม.</span>
                                    </div>
                                    <div class="countdown-box">
                                        <span class="time-value minutes">00</span>
                                        <span class="time-label">นาที</span>
                                    </div>
                                    <div class="countdown-box">
                                        <span class="time-value seconds">00</span>
                                        <span class="time-label">วินาที</span>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($expire_status === 'expiring_soon'): ?>
                            <div class="alert-inline alert-warning" style="
                                background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(245, 158, 11, 0.05));
                                border: 1px solid rgba(245, 158, 11, 0.3);
                                border-radius: 12px;
                                padding: 14px 20px;
                                margin-bottom: 20px;
                                display: flex;
                                align-items: center;
                                gap: 12px;
                                color: #fcd34d;
                            ">
                                <i class="fas fa-exclamation-triangle" style="font-size: 1.2rem;"></i>
                                <strong>ใกล้หมดอายุ!</strong> เหลือ <?php echo $days_left; ?> วัน
                            </div>
                        <?php endif; ?>
                        <div class="ssh-info">
                            <div class="info-item">
                                <div class="info-label">Username</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($rental['ssh_username']); ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Password</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($rental['ssh_password']); ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">วันที่เหลือ</div>
                                <div
                                    class="info-value <?php echo $is_expired ? 'text-danger' : ($days_left <= 3 ? 'text-warning' : 'text-success'); ?>">
                                    <?php echo $is_expired ? 'หมดอายุแล้ว' : $days_left . ' วัน'; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">หมดอายุ</div>
                                <div class="info-value">
                                    <?php echo date('d/m/Y H:i', strtotime($rental['expire_date'])); ?>
                                </div>
                            </div>
                        </div>

                        <div class="config-section">
                            <?php
                            // Real-time config generation from templates
                            $customName = $rental['custom_name'] ?: $rental['ssh_username'];

                            // Generate SSH config
                            if (!empty($rental['ssh_config_template'])) {
                                $sshConfig = SSHConfigGenerator::generateSSHConfig(
                                    $rental['ssh_config_template'],
                                    $rental['ssh_username'],
                                    $rental['ssh_password'],
                                    $customName
                                );
                            } else {
                                $sshConfig = $rental['ssh_config_url'];
                            }

                            // Generate NPV config
                            if (!empty($rental['npv_config_template'])) {
                                $npvConfig = SSHConfigGenerator::generateNPVConfig(
                                    $rental['npv_config_template'],
                                    $rental['ssh_username'],
                                    $rental['ssh_password'],
                                    $customName
                                );
                            } else {
                                $npvConfig = $rental['npv_config_url'];
                            }
                            ?>
                            <div class="config-tabs">
                                <button class="config-tab ssh active" onclick="switchTab(<?php echo $rental['id']; ?>, 'ssh')">
                                    <i class="fas fa-terminal me-2"></i>NetMod Config
                                </button>
                                <button class="config-tab npv" onclick="switchTab(<?php echo $rental['id']; ?>, 'npv')">
                                    <i class="fas fa-mobile-alt me-2"></i>NPV Config
                                </button>
                            </div>

                            <div class="config-content ssh active" id="ssh_config_<?php echo $rental['id']; ?>">
                                <div class="config-box">
                                    <button class="copy-btn" onclick="copyConfig('<?php echo $rental['id']; ?>', 'ssh')">
                                        <i class="fas fa-copy me-1"></i>คัดลอก
                                    </button>
                                    <div class="config-url" id="ssh_url_<?php echo $rental['id']; ?>"><?php echo htmlspecialchars($sshConfig); ?></div>
                                </div>
                            </div>

                            <div class="config-content npv" id="npv_config_<?php echo $rental['id']; ?>">
                                <div class="config-box">
                                    <button class="copy-btn" onclick="copyConfig('<?php echo $rental['id']; ?>', 'npv')">
                                        <i class="fas fa-copy me-1"></i>คัดลอก
                                    </button>
                                    <div class="config-url" id="npv_url_<?php echo $rental['id']; ?>"><?php echo htmlspecialchars($npvConfig); ?></div>
                                </div>
                            </div>

                            <!-- Auto-Renew Section - Premium Design -->
                            <div class="ve-auto-renew-box">
                                <div class="ve-auto-renew-content">
                                    <h6 class="ve-auto-renew-title">
                                        <i class="fas fa-sync-alt"></i>
                                        ต่ออายุอัตโนมัติ
                                    </h6>
                                    <div class="ve-auto-renew-desc">
                                        ระบบจะตัดเครดิตและต่ออายุให้อัตโนมัติก่อนหมดอายุ 24 ชั่วโมง
                                    </div>
                                    <div class="ve-auto-renew-credit">
                                        <i class="fas fa-wallet me-1"></i>
                                        เครดิตคงเหลือ: <strong class="credit-amount">฿<?php echo number_format($user_credit, 2); ?></strong>
                                        <br>
                                        <small>ค่าบริการ: <strong>฿<?php echo number_format($rental['price_per_day'], 2); ?></strong>/วัน</small>
                                    </div>
                                    <div class="ve-auto-renew-how">
                                        <i class="fas fa-info-circle me-1"></i>
                                        ระบบจะต่ออายุอัตโนมัติ วันต่อวัน ถ้าเครดิตเพียงพอ
                                    </div>
                                </div>
                                <div class="ve-auto-renew-switch-wrap">
                                    <label class="ve-toggle-switch">
                                        <input type="checkbox" <?php echo (!empty($rental['auto_renew'])) ? 'checked' : ''; ?>
                                               onchange="toggleAutoRenewSSH(<?php echo $rental['id']; ?>, this.checked)">
                                        <span class="ve-toggle-slider"></span>
                                    </label>
                                    <span class="ve-toggle-status <?php echo (!empty($rental['auto_renew'])) ? 'status-on' : 'status-off'; ?>">
                                        <?php echo (!empty($rental['auto_renew'])) ? 'เปิด' : 'ปิด'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <button class="action-btn btn-edit-name"
                                onclick="editCustomName(<?php echo $rental['id']; ?>, '<?php echo htmlspecialchars($rental['custom_name'] ?? '', ENT_QUOTES); ?>')">
                                <i class="fas fa-pen me-2"></i>เปลี่ยนชื่อ
                            </button>
                            <button class="action-btn btn-extend"
                                onclick="extendRental(<?php echo $rental['id']; ?>, <?php echo $rental['price_per_day']; ?>)">
                                <i class="fas fa-plus me-2"></i>ต่ออายุ
                            </button>
                            <button class="action-btn btn-qr" onclick="showQRCode(<?php echo $rental['id']; ?>)">
                                <i class="fas fa-qrcode me-2"></i>QR Code
                            </button>
                            <button class="action-btn btn-cancel" onclick="cancelRental(<?php echo $rental['id']; ?>)">
                                <i class="fas fa-trash me-2"></i>ยกเลิก
                            </button>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="qrModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: #1a1a2e; border: 1px solid rgba(229,9,20,0.3);">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-white">QR Code Config</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="qrCodeContainer"
                        style="background: white; padding: 20px; border-radius: 10px; display: inline-block;"></div>
                    <p class="text-white mt-3" id="qrConfigType"></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        const API_BASE = '<?php
            $base = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/"));
            $base = rtrim($base, "/");
            echo ($base === "" || $base === ".") ? "" : $base;
        ?>';
        function apiUrl(path) {
            const p = path.startsWith("/") ? path : "/" + path;
            return API_BASE ? API_BASE + p : p;
        }

        function switchTab(id, type) {
            // Update tabs
            document.querySelectorAll(`[onclick*="switchTab(${id}"]`).forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            // Update content
            document.querySelectorAll(`[id^="ssh_config_${id}"], [id^="npv_config_${id}"]`).forEach(el => el.classList.remove('active'));
            document.getElementById(`${type}_config_${id}`).classList.add('active');
        }

        // FIX: Added .trim() to remove whitespace
        function copyConfig(id, type) {
            const url = document.getElementById(`${type}_url_${id}`).textContent.trim();
            navigator.clipboard.writeText(url).then(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'คัดลอกแล้ว!',
                    text: `${type.toUpperCase()} Config ถูกคัดลอกแล้ว`,
                    timer: 1500,
                    showConfirmButton: false
                });
            });
        }

        function editCustomName(id, currentName) {
            Swal.fire({
                title: 'เปลี่ยนชื่อ Config',
                input: 'text',
                inputValue: currentName,
                inputPlaceholder: 'ใส่ชื่อใหม่',
                showCancelButton: true,
                confirmButtonText: 'บันทึก',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#E50914'
            }).then(result => {
                if (result.isConfirmed && result.value) {
                    const formData = new FormData();
                    formData.append('action', 'update_custom_name');
                    formData.append('rental_id', id);
                    formData.append('custom_name', result.value);

                    fetch(apiUrl('controller/rent_ssh_controller.php'), {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('สำเร็จ!', data.message, 'success').then(() => location.reload());
                            } else {
                                Swal.fire('เกิดข้อผิดพลาด', data.message, 'error');
                            }
                        });
                }
            });
        }

        function extendRental(id, pricePerDay) {
            const userCredit = <?php echo $user_credit; ?>;
            Swal.fire({
                title: 'ต่ออายุ SSH',
                html: `
                    <p><i class="fas fa-wallet"></i> เครดิตคงเหลือ: <strong class="text-success">฿${userCredit.toFixed(2)}</strong></p>
                    <hr>
                    <p>ราคา: <strong class="text-danger">฿${pricePerDay.toFixed(2)}</strong> / วัน</p>
                    <input type="number" id="extendDays" class="swal2-input" value="7" min="1" max="30" placeholder="จำนวนวัน">
                    <p class="mt-2" id="totalPrice">รวม: ฿${(pricePerDay * 7).toFixed(2)}</p>
                `,
                showCancelButton: true,
                confirmButtonText: 'ต่ออายุ',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#10b981',
                didOpen: () => {
                    document.getElementById('extendDays').addEventListener('input', function () {
                        const days = parseInt(this.value) || 0;
                        const total = pricePerDay * days;
                        document.getElementById('totalPrice').textContent = `รวม: ฿${total.toFixed(2)}`;
                    });
                },
                preConfirm: () => {
                    const days = document.getElementById('extendDays').value;
                    const total = pricePerDay * parseInt(days);
                    if (!days || days < 1) {
                        Swal.showValidationMessage('กรุณาใส่จำนวนวัน');
                        return false;
                    }
                    if (total > userCredit) {
                        Swal.showValidationMessage('เครดิตไม่เพียงพอ');
                        return false;
                    }
                    return days;
                }
            }).then(result => {
                if (result.isConfirmed) {
                    Alert.loading('กำลังต่ออายุ SSH...');

                    const formData = new FormData();
                    formData.append('action', 'extend');
                    formData.append('rental_id', id);
                    formData.append('days', result.value);

                    fetch(apiUrl('controller/rent_ssh_controller.php'), {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Alert.success('สำเร็จ!', data.message, {
                                    timer: undefined,
                                    showConfirmButton: true
                                }).then(() => location.reload());
                            } else {
                                Alert.error('เกิดข้อผิดพลาด', data.message);
                            }
                        })
                        .catch(err => {
                            Alert.error('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
                        });
                }
            });
        }

        function showQRCode(id) {
            // Get active config (ssh or npv)
            const sshConfig = document.getElementById(`ssh_config_${id}`);
            const isSSH = sshConfig && sshConfig.classList.contains('active');
            const configType = isSSH ? 'ssh' : 'npv';
            
            // FIX: Added .trim() here as well
            const configUrl = document.getElementById(`${configType}_url_${id}`).textContent.trim();

            const container = document.getElementById('qrCodeContainer');
            container.innerHTML = '';

            // สร้าง QR Code
            new QRCode(container, {
                text: configUrl,
                width: 200,
                height: 200,
                colorDark: '#000000',
                colorLight: '#ffffff'
            });

            document.getElementById('qrConfigType').textContent = `${configType.toUpperCase()} Config`;
            new bootstrap.Modal(document.getElementById('qrModal')).show();
        }

        function cancelRental(id) {
            Swal.fire({
                title: 'ยืนยันการยกเลิก?',
                text: 'การยกเลิกจะลบ account จาก SSH Server และไม่สามารถกู้คืนได้',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ยืนยันยกเลิก',
                cancelButtonText: 'ไม่ยกเลิก',
                confirmButtonColor: '#ef4444'
            }).then(result => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'กำลังดำเนินการ...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                    const formData = new FormData();
                    formData.append('action', 'cancel');
                    formData.append('rental_id', id);

                    fetch(apiUrl('controller/rent_ssh_controller.php'), {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('สำเร็จ!', data.message, 'success').then(() => location.reload());
                            } else {
                                Swal.fire('เกิดข้อผิดพลาด', data.message, 'error');
                            }
                        })
                        .catch(err => Swal.fire('Error', err.message, 'error'));
                }
            });
        }

        function toggleAutoRenewSSH(id, isChecked) {
            const status = isChecked ? 1 : 0;
            const switchElement = document.querySelector(`input[onchange*="toggleAutoRenewSSH(${id}"]`);
            const statusElement = switchElement?.closest('.ve-auto-renew-box')?.querySelector('.ve-toggle-status');
            
            // Show loading state
            if (switchElement) switchElement.disabled = true;
            if (statusElement) {
                statusElement.textContent = '...';
                statusElement.style.opacity = '0.5';
            }

            const formData = new FormData();
            formData.append('action', 'toggle_auto_renew');
            formData.append('rental_id', id);
            formData.append('status', status);

            fetch(apiUrl('controller/rent_ssh_controller.php'), {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Update status display
                    if (statusElement) {
                        statusElement.textContent = isChecked ? 'เปิด' : 'ปิด';
                        statusElement.className = `ve-toggle-status ${isChecked ? 'status-on' : 'status-off'}`;
                        statusElement.style.opacity = '1';
                    }
                    Alert.success('สำเร็จ', data.message);
                } else {
                    // Revert state on error
                    if (switchElement) {
                        switchElement.checked = !isChecked;
                        switchElement.disabled = false;
                    }
                    if (statusElement) {
                        statusElement.textContent = !isChecked ? 'เปิด' : 'ปิด';
                        statusElement.className = `ve-toggle-status ${!isChecked ? 'status-on' : 'status-off'}`;
                        statusElement.style.opacity = '1';
                    }
                    Alert.error('ผิดพลาด', data.message);
                }
            })
            .catch(err => {
                // Revert state on error
                if (switchElement) {
                    switchElement.checked = !isChecked;
                    switchElement.disabled = false;
                }
                if (statusElement) {
                    statusElement.textContent = !isChecked ? 'เปิด' : 'ปิด';
                    statusElement.className = `ve-toggle-status ${!isChecked ? 'status-on' : 'status-off'}`;
                    statusElement.style.opacity = '1';
                }
                Alert.error('ผิดพลาด', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
            })
            .finally(() => {
                if (switchElement) switchElement.disabled = false;
            });
        }

        function initDeleteCountdowns() {
            document.querySelectorAll('.delete-countdown-alert').forEach(alert => {
                let seconds = parseInt(alert.dataset.seconds, 10) || 0;
                const updateDisplay = () => {
                    if (seconds <= 0) {
                        location.reload();
                        return;
                    }
                    const d = Math.floor(seconds / 86400);
                    const h = Math.floor((seconds % 86400) / 3600);
                    const m = Math.floor((seconds % 3600) / 60);
                    const s = seconds % 60;
                    const daysEl = alert.querySelector('.days');
                    const hoursEl = alert.querySelector('.hours');
                    const minEl = alert.querySelector('.minutes');
                    const secEl = alert.querySelector('.seconds');
                    if (daysEl) daysEl.textContent = String(d).padStart(2, '0');
                    if (hoursEl) hoursEl.textContent = String(h).padStart(2, '0');
                    if (minEl) minEl.textContent = String(m).padStart(2, '0');
                    if (secEl) secEl.textContent = String(s).padStart(2, '0');
                };
                updateDisplay();
                setInterval(() => { seconds--; updateDisplay(); }, 1000);
            });
        }

        document.addEventListener('DOMContentLoaded', initDeleteCountdowns);
    </script>
</body>

</html>