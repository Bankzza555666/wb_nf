<?php
// Server_price/my_vpn.php (Router Version)
// หน้านี้ถูกเรียกผ่าน index.php (?p=my_vpn)

require_once 'controller/auth_check.php';
require_once 'controller/config.php';

$user_id = $_SESSION['user_id'];

// ดึงข้อมูล User
$stmt = $conn->prepare("SELECT username, email, credit FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ลบ VPN ที่หมดอายุมาแล้ว 2 วันอัตโนมัติ
$delete_expired_query = "
    UPDATE user_rentals 
    SET deleted_at = NOW(), status = 'deleted'
    WHERE user_id = ? 
    AND deleted_at IS NULL 
    AND expire_date < DATE_SUB(NOW(), INTERVAL 2 DAY)
";
$stmt_delete = $conn->prepare($delete_expired_query);
$stmt_delete->bind_param("i", $user_id);
$stmt_delete->execute();
$deleted_count = $stmt_delete->affected_rows;
$stmt_delete->close();

// ดึงข้อมูล VPN ทั้งหมดของผู้ใช้ พร้อม Traffic
$query = "
    SELECT 
        ur.id as rental_real_id,
        ur.*,
        p.filename,
        p.protocol, 
        p.network,
        p.price_per_day,
        p.data_per_gb,
        p.min_days,
        p.min_data_gb,
        s.server_name,
        s.server_location,
        DATEDIFF(ur.expire_date, NOW()) as days_remaining,
        CASE 
            WHEN ur.expire_date < NOW() THEN 'expired'
            WHEN DATEDIFF(ur.expire_date, NOW()) <= 7 THEN 'expiring_soon'
            ELSE 'active'
        END as expire_status,
        ROUND((ur.data_used_bytes / ur.data_total_bytes) * 100, 2) as traffic_percentage,
        (ur.data_total_bytes - ur.data_used_bytes) as data_remaining_bytes,
        TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(ur.expire_date, INTERVAL 2 DAY)) as seconds_until_delete
    FROM user_rentals ur
    LEFT JOIN price_v2 p ON ur.price_id = p.id
    LEFT JOIN servers s ON ur.server_id = s.server_id
    WHERE ur.user_id = ? AND ur.deleted_at IS NULL
    ORDER BY ur.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$vpns_result = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VPN ของฉัน - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    <style>
        :root {
            --primary: #E50914;
            --primary-light: #ff3d47;
            --primary-dark: #b20710;
            --accent: #ff6b6b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark-900: #0a0a0a;
            --dark-800: #121212;
            --dark-700: #1a1a1a;
            --dark-600: #242424;
            --dark-500: #2d2d2d;
            --text-primary: #ffffff;
            --text-secondary: #a1a1aa;
            --text-muted: #71717a;
            --glass-bg: rgba(18, 18, 18, 0.95);
            --glass-border: rgba(255, 255, 255, 0.08);
            --glow: 0 0 40px rgba(229, 9, 20, 0.3);
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: var(--bg-body, #0a0a0a);
            background-attachment: fixed;
            color: var(--text-primary);
            font-family: 'Inter', 'Segoe UI', sans-serif;
            min-height: 100vh;
        }

        /* Animated Background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(ellipse at 20% 20%, rgba(229, 9, 20, 0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 80%, rgba(229, 9, 20, 0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }

        /* Page Header */
        .page-header {
            text-align: center;
            padding: 60px 0 40px;
            position: relative;
        }

        .page-title {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, var(--primary-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
            letter-spacing: -1px;
        }

        .page-title i {
            -webkit-text-fill-color: var(--primary);
            margin-right: 15px;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
            font-weight: 400;
        }

        /* Search Bar */
        .search-wrapper {
            position: relative;
            max-width: 600px;
            margin: 0 auto 40px;
        }

        .search-input {
            width: 100%;
            padding: 18px 25px 18px 55px;
            background: var(--dark-700);
            border: 2px solid var(--glass-border);
            border-radius: 16px;
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .search-input:focus {
            background: var(--dark-600);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(229, 9, 20, 0.15);
            outline: none;
        }

        .search-wrapper .fa-search {
            position: absolute;
            top: 50%;
            left: 20px;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        /* VPN Card - Premium Design */
        .vpn-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 0;
            margin-bottom: 30px;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .vpn-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light), var(--primary));
        }

        .vpn-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--glow), 0 25px 50px rgba(0, 0, 0, 0.5);
            border-color: rgba(229, 9, 20, 0.3);
        }

        .vpn-card.expired::before {
            background: linear-gradient(90deg, var(--danger), #ff6b6b, var(--danger));
        }

        .vpn-card.expiring_soon::before {
            background: linear-gradient(90deg, var(--warning), #fbbf24, var(--warning));
        }

        .vpn-card-body {
            padding: 30px;
        }

        /* Card Header */
        .vpn-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            gap: 15px;
        }

        .vpn-name-group h4 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .vpn-name-group h4 i {
            color: var(--primary);
            font-size: 1.3rem;
        }

        .vpn-name-group .server-info {
            display: flex;
            gap: 15px;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .vpn-name-group .server-info i {
            margin-right: 5px;
            color: var(--text-muted);
        }

        .edit-name-btn {
            color: var(--text-muted);
            font-size: 0.85rem;
            text-decoration: none;
            opacity: 0.7;
            transition: all 0.2s;
        }

        .edit-name-btn:hover {
            color: var(--primary);
            opacity: 1;
        }

        /* Status Badge */
        .status-badge {
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .status-badge.active {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(16, 185, 129, 0.1));
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-badge.expired {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(239, 68, 68, 0.1));
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .status-badge.suspended {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(245, 158, 11, 0.1));
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        /* Alert Inline */
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

        .alert-inline.alert-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(239, 68, 68, 0.05));
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert-inline.alert-warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(245, 158, 11, 0.05));
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fcd34d;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .stat-item {
            background: var(--dark-700);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px solid var(--glass-border);
            transition: all 0.3s;
        }

        .stat-item:hover {
            background: var(--dark-600);
            transform: translateY(-2px);
        }

        .stat-item .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 1.2rem;
        }

        .stat-item .stat-icon.blue {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
        }

        .stat-item .stat-icon.red {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
        }

        .stat-item .stat-icon.green {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
        }

        .stat-item .stat-icon.purple {
            background: rgba(139, 92, 246, 0.15);
            color: #a78bfa;
        }

        .stat-item .stat-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-item .stat-value {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .stat-item .stat-value.danger {
            color: var(--danger);
        }

        .stat-item .stat-value.warning {
            color: var(--warning);
        }

        .stat-item .stat-value.success {
            color: var(--success);
        }

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

        .ve-toggle-slider::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
            height: 100%;
            border-radius: 38px;
            background: transparent;
            transition: all 0.4s ease;
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

        /* Traffic Section */
        .traffic-section {
            background: var(--dark-700);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid var(--glass-border);
        }

        .traffic-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .traffic-header h6 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .traffic-header h6 i {
            color: var(--primary);
            margin-right: 10px;
        }

        .traffic-stats {
            font-size: 0.95rem;
            color: var(--text-secondary);
        }

        .traffic-stats strong {
            color: var(--text-primary);
            font-size: 1.1rem;
        }

        .progress {
            height: 12px;
            border-radius: 20px;
            background: var(--dark-500);
            overflow: hidden;
        }

        .progress-bar {
            border-radius: 20px;
            font-size: 0;
            transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .progress-bar.bg-success {
            background: linear-gradient(90deg, #10b981, #34d399);
        }

        .progress-bar.bg-info {
            background: linear-gradient(90deg, #3b82f6, #60a5fa);
        }

        .progress-bar.bg-warning {
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
        }

        .progress-bar.bg-danger {
            background: linear-gradient(90deg, #ef4444, #f87171);
        }

        .traffic-details {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--glass-border);
        }

        .traffic-details span {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .traffic-details i {
            margin-right: 6px;
        }

        .traffic-details .fa-arrow-up {
            color: var(--success);
        }

        .traffic-details .fa-arrow-down {
            color: #3b82f6;
        }

        /* Config Section */
        .config-section {
            background: var(--dark-700);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid var(--glass-border);
        }

        .config-section h6 {
            margin: 0 0 20px 0;
            font-size: 1rem;
            font-weight: 600;
        }

        .config-section h6 i {
            color: var(--primary);
            margin-right: 10px;
        }

        .config-url-wrapper {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .config-url {
            flex: 1;
            background: var(--dark-800);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 15px 18px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            color: var(--text-secondary);
            word-break: break-all;
        }

        .config-url:focus {
            border-color: var(--primary);
            outline: none;
        }

        .config-url.config-updated {
            animation: configPulse 0.5s ease;
            border-color: var(--success) !important;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.4);
        }

        @keyframes configPulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.02);
            }

            100% {
                transform: scale(1);
            }
        }

        .protocol-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: var(--dark-600);
            border-radius: 20px;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .protocol-badge i {
            color: var(--primary);
        }

        .qr-code {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .qr-code img {
            width: 150px;
            height: 150px;
            border-radius: 16px;
            padding: 10px;
            background: white;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .qr-code small {
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            padding-top: 25px;
            border-top: 1px solid var(--glass-border);
        }

        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 15px rgba(229, 9, 20, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(229, 9, 20, 0.4);
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }

        .btn-outline-danger {
            background: transparent;
            color: var(--danger);
            border: 1px solid var(--danger);
        }

        .btn-outline-danger:hover {
            background: rgba(239, 68, 68, 0.1);
        }

        .btn-link {
            background: none;
            color: var(--text-muted);
            padding: 12px;
        }

        .btn-link:hover {
            color: var(--danger);
        }

        .action-buttons .btn-link {
            margin-left: auto;
        }

        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }

            .action-buttons .btn {
                width: 100%;
                justify-content: center;
            }

            .action-buttons .btn-link {
                margin-left: 0;
            }
        }

        /* Refresh Button */
        .refresh-btn {
            position: fixed;
            bottom: 110px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            box-shadow: 0 8px 30px rgba(229, 9, 20, 0.4);
            font-size: 22px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
        }

        .refresh-btn:hover {
            transform: scale(1.1) translateY(-3px);
            box-shadow: 0 12px 40px rgba(229, 9, 20, 0.5);
        }

        .refresh-btn.syncing {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 100px 20px;
            background: var(--dark-800);
            border-radius: 24px;
            border: 2px dashed var(--glass-border);
        }

        .empty-state i {
            font-size: 80px;
            background: linear-gradient(135deg, var(--dark-600), var(--dark-700));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 25px;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--text-muted);
            margin-bottom: 30px;
        }

        /* Modal Styles */
        .c-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .c-modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .c-modal-box {
            background: var(--dark-800);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 35px;
            width: 90%;
            max-width: 480px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.6);
            transform: scale(0.9) translateY(20px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .c-modal-overlay.show .c-modal-box {
            transform: scale(1) translateY(0);
        }

        .c-modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .c-modal-content {
            color: var(--text-secondary);
            margin-bottom: 30px;
        }

        .c-modal-content .form-control {
            background: var(--dark-700) !important;
            border: 1px solid var(--glass-border) !important;
            color: white !important;
            border-radius: 12px;
            padding: 12px 16px;
        }

        .c-modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        /* Toast */
        .c-toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 3000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }

        .c-toast {
            pointer-events: auto;
            display: flex;
            align-items: center;
            background: var(--dark-800);
            color: white;
            padding: 16px 24px;
            border-radius: 16px;
            border-left: 4px solid var(--success);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
            opacity: 0;
            transform: translateX(120%);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .c-toast.show {
            opacity: 1;
            transform: translateX(0);
        }

        .c-toast i {
            font-size: 1.2rem;
            margin-right: 12px;
            color: var(--success);
        }

        /* Slider Styles */
        .slider-container {
            padding: 15px 0;
        }

        .slider-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .slider-value {
            font-size: 1.5rem;
            color: var(--primary);
            font-weight: 700;
        }

        .custom-slider {
            width: 100%;
            height: 8px;
            border-radius: 10px;
            background: var(--dark-600);
            outline: none;
            -webkit-appearance: none;
        }

        .custom-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4);
        }

        .stepper-input {
            display: flex;
            align-items: center;
            background: var(--dark-700);
            border-radius: 16px;
            padding: 8px;
            border: 1px solid var(--glass-border);
        }

        .stepper-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            border-radius: 10px;
            width: 45px;
            height: 45px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .stepper-btn:hover {
            transform: scale(1.05);
        }

        .stepper-input input {
            flex: 1;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            background: transparent;
            border: none;
        }

        .text-muted {
            color: var(--text-muted) !important;
        }

        .text-primary-glow {
            color: var(--primary) !important;
        }

        .text-warning-glow {
            color: var(--warning) !important;
        }

        .text-danger-glow {
            color: var(--danger) !important;
        }

        /* Card Footer */
        .card-footer-info {
            padding: 15px 30px;
            background: var(--dark-800);
            border-top: 1px solid var(--glass-border);
            font-size: 0.8rem;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
        }

        /* Tablet */
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }

            .vpn-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .vpn-name-group h4 {
                font-size: 1.2rem;
            }

            .stat-item .stat-value {
                font-size: 1.2rem;
            }

            .status-badge {
                margin-top: 10px;
            }

            .config-url-wrapper {
                flex-direction: column;
            }

            .config-url-wrapper .btn {
                width: 100%;
            }

            .qr-code {
                margin-top: 20px;
            }

            .qr-code img {
                width: 120px;
                height: 120px;
            }
        }

        /* Mobile */
        @media (max-width: 576px) {
            .container {
                padding-left: 12px;
                padding-right: 12px;
            }

            .page-header {
                padding: 40px 0 25px;
            }

            .page-title {
                font-size: 1.6rem;
                letter-spacing: -0.5px;
            }

            .page-title i {
                margin-right: 8px;
                font-size: 1.4rem;
            }

            .page-subtitle {
                font-size: 0.95rem;
            }

            .search-wrapper {
                margin-bottom: 25px;
            }

            .search-input {
                padding: 14px 18px 14px 45px;
                font-size: 0.95rem;
                border-radius: 12px;
            }

            .search-wrapper .fa-search {
                left: 16px;
                font-size: 1rem;
            }

            .vpn-card {
                border-radius: 18px;
                margin-bottom: 20px;
            }

            .vpn-card-body {
                padding: 20px;
            }

            .vpn-header {
                gap: 10px;
                margin-bottom: 18px;
            }

            .vpn-name-group h4 {
                font-size: 1.1rem;
                flex-wrap: wrap;
                gap: 8px;
            }

            .vpn-name-group h4 i {
                font-size: 1rem;
            }

            .vpn-name-group .server-info {
                flex-direction: column;
                gap: 5px;
                font-size: 0.8rem;
            }

            .edit-name-btn {
                font-size: 0.75rem;
            }

            .status-badge {
                padding: 6px 12px;
                font-size: 0.7rem;
            }

            .alert-inline {
                padding: 12px 15px;
                font-size: 0.85rem;
                border-radius: 10px;
            }

            .alert-inline i {
                font-size: 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
                margin-bottom: 18px;
            }

            .stat-item {
                padding: 15px 12px;
                border-radius: 12px;
            }

            .stat-item .stat-icon {
                width: 38px;
                height: 38px;
                font-size: 1rem;
                margin-bottom: 8px;
            }

            .stat-item .stat-label {
                font-size: 0.7rem;
                margin-bottom: 4px;
            }

            .stat-item .stat-value {
                font-size: 1.1rem;
            }

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

            .traffic-section {
                padding: 18px;
                border-radius: 14px;
                margin-bottom: 18px;
            }

            .traffic-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .traffic-header h6 {
                font-size: 0.9rem;
            }

            .traffic-stats {
                font-size: 0.85rem;
            }

            .traffic-stats strong {
                font-size: 1rem;
            }

            .progress {
                height: 10px;
            }

            .traffic-details {
                flex-direction: column;
                gap: 8px;
            }

            .traffic-details span {
                font-size: 0.8rem;
            }

            .config-section {
                padding: 18px;
                border-radius: 14px;
                margin-bottom: 18px;
            }

            .config-section h6 {
                font-size: 0.9rem;
                margin-bottom: 15px;
            }

            .config-url {
                padding: 12px 14px;
                font-size: 0.7rem;
                border-radius: 10px;
            }

            .config-url-wrapper {
                gap: 8px;
            }

            .config-url-wrapper .btn {
                padding: 10px 16px;
                font-size: 0.85rem;
            }

            .protocol-badge {
                padding: 5px 10px;
                font-size: 0.7rem;
            }

            .qr-code img {
                width: 100px;
                height: 100px;
                border-radius: 12px;
            }

            .qr-code small {
                font-size: 0.7rem;
            }

            .action-buttons {
                gap: 10px;
                padding-top: 18px;
            }

            .btn {
                padding: 11px 18px;
                font-size: 0.85rem;
                border-radius: 10px;
            }

            .card-footer-info {
                padding: 12px 18px;
                font-size: 0.7rem;
                flex-direction: column;
                gap: 5px;
                text-align: center;
            }

            .refresh-btn {
                width: 50px;
                height: 50px;
                font-size: 18px;
                bottom: 90px;
                right: 15px;
            }

            .empty-state {
                padding: 60px 15px;
                border-radius: 18px;
            }

            .empty-state i {
                font-size: 50px;
            }

            .empty-state h3 {
                font-size: 1.2rem;
            }

            .empty-state p {
                font-size: 0.9rem;
            }

            .empty-state .btn {
                padding: 12px 20px;
            }

            /* Modal Mobile */
            .c-modal-box {
                padding: 25px 20px;
                border-radius: 18px;
            }

            .c-modal-title {
                font-size: 1.25rem;
            }

            .c-modal-content {
                font-size: 0.9rem;
            }

            .c-modal-buttons {
                flex-direction: column;
                gap: 10px;
            }

            .c-modal-buttons .btn {
                width: 100%;
            }

            .slider-value {
                font-size: 1.3rem;
            }

            .stepper-input {
                border-radius: 12px;
                padding: 6px;
            }

            .stepper-btn {
                width: 40px;
                height: 40px;
                font-size: 1rem;
                border-radius: 8px;
            }

            .stepper-input input {
                font-size: 1.3rem;
            }

            .c-toast {
                padding: 12px 16px;
                border-radius: 12px;
                font-size: 0.9rem;
            }
        }

        /* Extra small devices */
        @media (max-width: 375px) {
            .page-title {
                font-size: 1.4rem;
            }

            .vpn-name-group h4 {
                font-size: 1rem;
            }

            .stat-item .stat-value {
                font-size: 1rem;
            }

            .stats-grid {
                gap: 8px;
            }

            .stat-item {
                padding: 12px 10px;
            }
        }

        /* Delete Countdown Alert */
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
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .delete-countdown-alert .countdown-timer {
            display: flex;
            gap: 8px;
        }

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

        @media (max-width: 576px) {
            .delete-countdown-alert {
                flex-direction: column;
                text-align: center;
                padding: 14px;
            }

            .delete-countdown-alert .alert-content {
                flex-direction: column;
                gap: 8px;
            }

            .delete-countdown-alert .countdown-box {
                min-width: 45px;
                padding: 6px 10px;
            }

            .delete-countdown-alert .countdown-box .time-value {
                font-size: 1.1rem;
            }
        }

        /* Deleted notification */
        .deleted-notification {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(239, 68, 68, 0.05));
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
            color: #fca5a5;
        }

        .deleted-notification i {
            font-size: 1.5rem;
            margin-right: 10px;
            color: #f87171;
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

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Page entrance animation */
        .page-header {
            animation: fadeInUp 0.6s ease-out;
        }

        /* VPN cards stagger animation */
        .vpn-card {
            animation: fadeInUp 0.5s ease-out backwards;
        }

        .vpn-card:nth-child(1) { animation-delay: 0.1s; }
        .vpn-card:nth-child(2) { animation-delay: 0.2s; }
        .vpn-card:nth-child(3) { animation-delay: 0.3s; }
        .vpn-card:nth-child(4) { animation-delay: 0.4s; }

        /* Enhanced card hover */
        .vpn-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .vpn-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4), 0 0 40px rgba(229, 9, 20, 0.2);
        }

        /* Status badge animations */
        .status-badge.active {
            animation: pulse-glow 2s infinite;
        }

        /* Stats item hover */
        .stat-item {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-item:hover {
            transform: translateY(-4px);
            background: var(--dark-600);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .stat-item .stat-icon {
            transition: all 0.3s ease;
        }

        .stat-item:hover .stat-icon {
            transform: scale(1.15) rotate(5deg);
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
        }

        /* Search input focus animation */
        .search-input {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .search-wrapper .fa-search {
            transition: all 0.3s ease;
        }

        .search-input:focus + .fa-search,
        .search-wrapper:focus-within .fa-search {
            color: var(--primary);
            transform: scale(1.1);
        }

        /* Empty state animation */
        .empty-state {
            animation: fadeInUp 0.6s ease-out;
            text-align: center;
            padding: 60px 30px;
            background: var(--glass-bg);
            border-radius: 24px;
            border: 1px solid var(--glass-border);
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
            color: var(--text-primary);
            margin-bottom: 12px;
            font-weight: 700;
        }

        .empty-state-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
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

        /* Refresh button */
        .refresh-btn {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .refresh-btn:hover {
            transform: scale(1.15) rotate(180deg);
        }

        /* Auto-renew box animation */
        .ve-auto-renew-box {
            animation: fadeInUp 0.5s ease-out;
        }

        /* Progress bar animation */
        .progress-fill {
            transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Alert animations */
        .alert-inline {
            animation: slideDown 0.4s ease-out;
        }

        .deleted-notification {
            animation: slideInLeft 0.4s ease-out;
        }

        /* Modal animations */
        .c-modal-overlay {
            transition: all 0.3s ease;
        }

        .c-modal-box {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Copy button animation */
        .btn-copy {
            transition: all 0.3s ease;
        }

        .btn-copy:hover {
            transform: scale(1.05);
        }

        .btn-copy.copied {
            animation: pulse-glow 0.5s ease;
        }

        /* QR button */
        .btn-qr:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
        }

        /* Delete countdown */
        .delete-countdown-alert {
            animation: slideInLeft 0.4s ease-out;
        }

        .countdown-box {
            transition: all 0.3s ease;
        }

        .countdown-box:hover {
            transform: scale(1.05);
            background: rgba(239, 68, 68, 0.15);
        }

        /* Page title gradient animation */
        .page-title {
            background-size: 200% auto;
            animation: shimmer 5s linear infinite;
        }

        /* VPN name group hover */
        .vpn-name-group h4 i {
            transition: all 0.3s ease;
        }

        .vpn-card:hover .vpn-name-group h4 i {
            transform: rotate(15deg) scale(1.1);
        }
    </style>
    <!-- ✅ Site Theme (Admin Selectable) -->
    <?php if (file_exists(__DIR__ . '/../include/theme_head.php'))
        include __DIR__ . '/../include/theme_head.php'; ?>
</head>

<body>
    <?php include 'home/navbar.php'; ?>

    <div class="container mb-5">
        <div class="text-center mt-5">
            <h1 class="page-title"><i class="fas fa-list"></i> VPN ของฉัน</h1>
            <p class="page-subtitle">จัดการและดูรายละเอียด VPN ที่คุณเช่า</p>
        </div>

        <div class="search-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" id="vpnSearchInput" class="search-input" placeholder="ค้นหาจากชื่อ, เซิร์ฟเวอร์...">
        </div>

        <?php if ($deleted_count > 0): ?>
            <div class="deleted-notification">
                <i class="fas fa-trash-alt"></i>
                <strong><?php echo $deleted_count; ?> รายการถูกลบอัตโนมัติ</strong> เนื่องจากหมดอายุมาแล้วเกิน 2 วัน
            </div>
        <?php endif; ?>

        <?php if ($vpns_result->num_rows === 0): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>คุณยังไม่มี VPN</h3>
                <p class="text-muted">เริ่มต้นใช้งานด้วยการเช่า VPN ที่เหมาะกับคุณ</p>
                <a href="?p=rent_vpn" class="empty-state-btn">
                    <i class="fas fa-plus-circle"></i> เช่า VPN
                </a>
            </div>
        <?php else: ?>
            <?php while ($vpn = $vpns_result->fetch_assoc()):
                $data_used_gb = $vpn['data_used_bytes'] / (1024 * 1024 * 1024);
                $data_total_gb = $vpn['data_total_bytes'] / (1024 * 1024 * 1024);

                $clean_config_url = $vpn['config_url'];
                if (strpos($clean_config_url, '#') !== false) {
                    $clean_config_url = substr($clean_config_url, 0, strpos($clean_config_url, '#')) . '#' . rawurlencode($vpn['rental_name']);
                }
                $clean_qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($clean_config_url);
                ?>
                <div class="vpn-card <?php echo $vpn['expire_status']; ?>"
                    data-rental-id="<?php echo $vpn['rental_real_id']; ?>">
                    <div class="vpn-card-body">
                        <div class="vpn-header">
                            <div class="vpn-name-group">
                                <h4>
                                    <i class="fas fa-shield-alt"></i>
                                    <span
                                        id="rental-name-<?php echo $vpn['rental_real_id']; ?>"><?php echo htmlspecialchars($vpn['rental_name']); ?></span>
                                    <a href="#" class="edit-name-btn"
                                        onclick="event.preventDefault(); editName(<?php echo $vpn['rental_real_id']; ?>, '<?php echo htmlspecialchars(addslashes($vpn['rental_name'])); ?>')"
                                        title="แก้ไขชื่อ"><i class="fas fa-edit"></i></a>
                                </h4>
                                <div class="server-info">
                                    <span><i class="fas fa-server"></i>
                                        <?php echo htmlspecialchars($vpn['server_name']); ?></span>
                                    <span><i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($vpn['server_location']); ?></span>
                                    <span class="d-none">ID: <?php echo $vpn['rental_real_id']; ?></span>
                                </div>
                            </div>
                            <span class="status-badge <?php echo $vpn['status']; ?>">
                                <?php
                                $status_text = ['active' => 'ใช้งานอยู่', 'expired' => 'หมดอายุ', 'suspended' => 'ระงับ', 'cancelled' => 'ยกเลิก'];
                                echo $status_text[$vpn['status']] ?? $vpn['status'];
                                ?>
                            </span>
                        </div>

                        <?php if ($vpn['expire_status'] === 'expired' && $vpn['seconds_until_delete'] > 0): ?>
                            <div class="delete-countdown-alert" data-seconds="<?php echo $vpn['seconds_until_delete']; ?>"
                                data-rental-id="<?php echo $vpn['rental_real_id']; ?>">
                                <div class="alert-content">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <div>
                                        <strong>VPN หมดอายุแล้ว!</strong><br>
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
                        <?php elseif ($vpn['expire_status'] === 'expiring_soon'): ?>
                            <div class="alert-inline alert-warning"><i class="fas fa-exclamation-triangle"></i>
                                <strong>ใกล้หมดอายุ!</strong> เหลือ <?php echo $vpn['days_remaining']; ?> วัน
                            </div>
                        <?php endif; ?>

                        <?php if ($vpn['traffic_percentage'] >= 90): ?>
                            <div class="alert-inline alert-danger"><i class="fas fa-database"></i> <strong>Data ใกล้หมด!</strong>
                                ใช้ไป <?php echo number_format($vpn['traffic_percentage'], 1); ?>%</div>
                        <?php endif; ?>

                        <?php
                        $expire_time = strtotime($vpn['expire_date']);
                        $now = time();
                        $seconds_remaining = $expire_time - $now;
                        $hours_remaining = max(0, floor($seconds_remaining / 3600));
                        $time_display = $seconds_remaining <= 0 ? '0 วัน' : ($vpn['days_remaining'] < 1 ? $hours_remaining . ' ชม.' : $vpn['days_remaining'] . ' วัน');
                        $time_class = $vpn['expire_status'] === 'expired' ? 'danger' : ($vpn['expire_status'] === 'expiring_soon' ? 'warning' : 'success');
                        ?>

                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-icon blue"><i class="fas fa-calendar-check"></i></div>
                                <div class="stat-label">วันที่เริ่ม</div>
                                <div class="stat-value"><?php echo date('d/m/Y', strtotime($vpn['start_date'])); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon red"><i class="fas fa-calendar-times"></i></div>
                                <div class="stat-label">วันหมดอายุ</div>
                                <div
                                    class="stat-value <?php echo $vpn['expire_status'] === 'expired' ? 'danger' : ($vpn['expire_status'] === 'expiring_soon' ? 'warning' : ''); ?>">
                                    <?php echo date('d/m/Y', strtotime($vpn['expire_date'])); ?>
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon green"><i class="fas fa-hourglass-half"></i></div>
                                <div class="stat-label">เหลือเวลา</div>
                                <div class="stat-value <?php echo $time_class; ?>"><?php echo $time_display; ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-icon purple"><i class="fas fa-mobile-alt"></i></div>
                                <div class="stat-label">อุปกรณ์</div>
                                <div class="stat-value"><?php echo $vpn['max_devices']; ?> เครื่อง</div>
                            </div>
                        </div>

                        <div class="traffic-section">
                            <div class="traffic-header">
                                <h6><i class="fas fa-chart-line"></i> การใช้งาน Data</h6>
                                <div class="traffic-stats">
                                    <strong class="traffic-used"><?php echo number_format($data_used_gb, 2); ?></strong> /
                                    <?php echo number_format($data_total_gb, 2); ?> GB
                                    <span
                                        class="ms-2 traffic-percent">(<?php echo number_format($vpn['traffic_percentage'], 1); ?>%)</span>
                                </div>
                            </div>
                            <?php
                            $progress_class = 'bg-success';
                            if ($vpn['traffic_percentage'] >= 50)
                                $progress_class = 'bg-info';
                            if ($vpn['traffic_percentage'] >= 75)
                                $progress_class = 'bg-warning';
                            if ($vpn['traffic_percentage'] >= 90)
                                $progress_class = 'bg-danger';
                            ?>
                            <div class="progress">
                                <div class="progress-bar traffic-bar <?php echo $progress_class; ?>" role="progressbar"
                                    style="width: <?php echo min(100, $vpn['traffic_percentage']); ?>%"></div>
                            </div>
                            <div class="traffic-details">
                                <span><i class="fas fa-arrow-up"></i> Upload: <span
                                        class="upload-bytes"><?php echo number_format($vpn['upload_bytes'] / (1024 * 1024 * 1024), 2); ?></span>
                                    GB</span>
                                <span><i class="fas fa-arrow-down"></i> Download: <span
                                        class="download-bytes"><?php echo number_format($vpn['download_bytes'] / (1024 * 1024 * 1024), 2); ?></span>
                                    GB</span>
                            </div>

                            <div class="config-section">
                                <h6><i class="fas fa-link"></i> การเชื่อมต่อ</h6>
                                <div class="row align-items-center">
                                    <div class="col-md-8 mb-3 mb-md-0">
                                        <div class="config-url-wrapper">
                                            <input type="text" class="config-url"
                                                value="<?php echo htmlspecialchars($clean_config_url); ?>" readonly
                                                id="config-<?php echo $vpn['rental_real_id']; ?>">
                                            <button class="btn btn-primary"
                                                onclick="copyConfig(<?php echo $vpn['rental_real_id']; ?>)">
                                                <i class="fas fa-copy"></i> Copy
                                            </button>
                                        </div>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <span class="protocol-badge"><i class="fas fa-shield-alt"></i>
                                                <?php echo strtoupper($vpn['protocol']); ?></span>
                                            <span class="protocol-badge"><i class="fas fa-network-wired"></i>
                                                <?php echo ucfirst($vpn['network']); ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="qr-code">
                                            <img src="<?php echo htmlspecialchars($clean_qr_code_url); ?>" alt="QR Code"
                                                id="qrcode-<?php echo $vpn['rental_real_id']; ?>">
                                            <small>สแกนเพื่อเชื่อมต่อ</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button class="btn btn-primary"
                                    onclick="extendDays(<?php echo $vpn['rental_real_id']; ?>, <?php echo $vpn['price_per_day'] ?? 0; ?>, <?php echo $vpn['min_days'] ?? 1; ?>)">
                                    <i class="fas fa-calendar-plus"></i> ต่ออายุ
                                </button>
                                <button class="btn btn-success"
                                    onclick="addData(<?php echo $vpn['rental_real_id']; ?>, <?php echo $vpn['data_per_gb'] ?? 0; ?>, <?php echo $vpn['min_data_gb'] ?? 10; ?>)">
                                    <i class="fas fa-database"></i> เพิ่ม Data
                                </button>
                                <button class="btn btn-link" onclick="deleteRental(<?php echo $vpn['rental_real_id']; ?>)">
                                    <i class="fas fa-trash"></i> ลบ
                                </button>
                            </div>

                            <!-- ✅ Auto-Renew Toggle (ย้ายมาไว้ด้านล่างปุ่ม) -->
                            <?php
                            $price_per_day = $vpn['price_per_day'] ?? 0;
                            $min_days = $vpn['min_days'] ?? 1;
                            $min_credit_auto = $price_per_day * $min_days;
                            ?>
                            <div class="ve-auto-renew-box">
                                <div class="ve-auto-renew-content">
                                    <h6 class="ve-auto-renew-title">
                                        <i class="fas fa-sync-alt"></i> ต่ออายุอัตโนมัติ
                                    </h6>
                                    <p class="ve-auto-renew-desc">ระบบจะตัดเครดิตและต่ออายุให้อัตโนมัติก่อนหมดอายุ</p>
                                    <div class="ve-auto-renew-credit">
                                        <i class="fas fa-wallet me-1"></i>
                                        ต้องมีเครดิตขั้นต่ำ <strong>฿<?php echo number_format($min_credit_auto, 2); ?></strong>
                                        (฿<?php echo number_format($price_per_day, 2); ?>/วัน × ขั้นต่ำ <?php echo $min_days; ?> วัน)
                                        — คุณมีเครดิต <strong class="credit-amount">฿<?php echo number_format($user['credit'], 2); ?></strong>
                                    </div>
                                    <p class="ve-auto-renew-how">
                                        <i class="fas fa-info-circle me-1"></i>
                                        วิธีทำงาน: เมื่อเปิดไว้ ระบบจะตรวจก่อนหมดอายุ 24 ชม. ถ้าเครดิตพอจะหักเครดิตและต่ออายุ +1 วันให้อัตโนมัติ (วันต่อวัน)
                                    </p>
                                </div>
                                <div class="ve-auto-renew-switch-wrap">
                                    <label class="ve-toggle-switch">
                                        <input type="checkbox" id="autoRenewVPN_<?php echo $vpn['rental_real_id']; ?>" 
                                            <?php echo (!empty($vpn['auto_renew'])) ? 'checked' : ''; ?>
                                            onchange="toggleAutoRenewVPN(<?php echo $vpn['rental_real_id']; ?>, this.checked)"
                                            title="เปิด/ปิด การต่ออายุอัตโนมัติ">
                                        <span class="ve-toggle-slider"></span>
                                    </label>
                                    <span class="ve-toggle-status <?php echo (!empty($vpn['auto_renew'])) ? 'status-on' : 'status-off'; ?>" 
                                        id="autoRenewStatus_<?php echo $vpn['rental_real_id']; ?>">
                                        <?php echo (!empty($vpn['auto_renew'])) ? 'เปิดใช้งาน' : 'ปิดอยู่'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer-info">
                            <span><i class="fas fa-hashtag me-1"></i>Ref: <?php echo $vpn['transaction_ref']; ?></span>
                            <span><i class="fas fa-sync-alt me-1"></i>อัพเดท:
                                <?php echo $vpn['last_traffic_sync'] ? date('H:i d/m', strtotime($vpn['last_traffic_sync'])) : '-'; ?></span>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <button class="refresh-btn" id="refreshAllBtn" onclick="refreshAllTraffic()" title="อัพเดท Traffic ทั้งหมด">
            <i class="fas fa-sync-alt"></i>
        </button>

        <?php include 'home/footer.php'; ?>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

        <script>
            const userBalance = <?php echo (float) ($user['credit'] ?? 0); ?>;
            // Base path สำหรับ API (รองรับทั้งรันที่ root และใน subfolder เช่น / หรือ /htdocs)
            const API_BASE = '<?php
            $base = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/"));
            $base = rtrim($base, "/");
            echo $base === "" || $base === "." ? "" : $base;
            ?>';
            function apiUrl(path) {
                const p = path.startsWith("/") ? path : "/" + path;
                return API_BASE ? API_BASE + p : p;
            }

            class CustomAlert {
                constructor() { this.toastContainer = null; }
                _createModal(options) {
                    const { title, content, confirmText = 'ตกลง', cancelText = 'ยกเลิก', showCancel = false, showConfirm = true } = options;
                    const overlay = document.createElement('div');
                    overlay.className = 'c-modal-overlay';
                    let buttonsHtml = '';
                    if (showCancel) buttonsHtml += `<button class="btn btn-secondary c-modal-cancel me-2">${cancelText}</button>`;
                    if (showConfirm) buttonsHtml += `<button class="btn btn-primary c-modal-confirm">${confirmText}</button>`;

                    overlay.innerHTML = `<div class="c-modal-box"><div class="c-modal-title">${title}</div><div class="c-modal-content">${content}</div><div class="c-modal-buttons">${buttonsHtml}</div></div>`;
                    document.body.appendChild(overlay);

                    return new Promise((resolve) => {
                        if (showConfirm) {
                            overlay.querySelector('.c-modal-confirm').addEventListener('click', () => { this._closeModal(overlay); resolve({ isConfirmed: true, value: this._getInputValue(overlay) }); });
                        }
                        if (showCancel) {
                            overlay.querySelector('.c-modal-cancel').addEventListener('click', () => { this._closeModal(overlay); resolve({ isConfirmed: false }); });
                        }
                        setTimeout(() => overlay.classList.add('show'), 10);
                    });
                }
                _getInputValue(overlay) { const input = overlay.querySelector('.c-modal-input'); return input ? input.value : null; }
                _closeModal(overlay) { overlay.classList.remove('show'); setTimeout(() => overlay.remove(), 300); }
                fire(options) { return this._createModal(options); }
                loading(title = 'กำลังดำเนินการ...') {
                    const overlay = document.createElement('div');
                    overlay.className = 'c-modal-overlay';
                    overlay.innerHTML = `<div class="c-modal-box text-center"><h4 class="text-white mb-3">${title}</h4><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>`;
                    document.body.appendChild(overlay);
                    setTimeout(() => overlay.classList.add('show'), 10);
                    return overlay;
                }
                closeLoading(overlay) {
                    if (overlay) this._closeModal(overlay);
                }
                toast(options) {
                    const { title, icon = 'success' } = options;
                    if (!this.toastContainer) { this.toastContainer = document.createElement('div'); this.toastContainer.className = 'c-toast-container'; document.body.appendChild(this.toastContainer); }
                    const toast = document.createElement('div'); toast.className = `c-toast ${icon}`;
                    toast.innerHTML = `<i class="fas ${icon === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'}"></i> ${title}`;
                    this.toastContainer.appendChild(toast);
                    setTimeout(() => toast.classList.add('show'), 10);
                    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 400); }, 3000);
                }
            }
            const cAlert = new CustomAlert();

            document.addEventListener('DOMContentLoaded', function () {
                const searchInput = document.getElementById('vpnSearchInput');
                refreshAllTraffic();
                initDeleteCountdowns();

                // 🔄 Auto-refresh ทุก 30 วินาที สำหรับ Config & Traffic แบบ Realtime
                setInterval(() => {
                    refreshAllTraffic(true); // true = silent mode (ไม่แสดง toast)
                }, 30000);

                if (searchInput) {
                    searchInput.addEventListener('keyup', function () {
                        const filter = searchInput.value.toLowerCase();
                        document.querySelectorAll('.vpn-card').forEach(card => {
                            card.style.display = card.textContent.toLowerCase().includes(filter) ? '' : 'none';
                        });
                    });
                }
            });

            function copyConfig(rentalId) {
                const input = document.getElementById('config-' + rentalId);
                input.select(); document.execCommand('copy');
                cAlert.toast({ title: 'คัดลอกสำเร็จ!' });
            }

            function editName(rentalId, currentName) {
                cAlert.fire({
                    title: 'แก้ไขชื่อบริการ',
                    content: `<p class="text-muted">ID: ${rentalId}</p><input type="text" class="form-control c-modal-input" id="newNameInput" value="${currentName}" placeholder="ชื่อใหม่">`,
                    showCancel: true, confirmText: 'บันทึก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const newName = document.getElementById('newNameInput').value.trim();
                        if (!newName) return;

                        const loading = cAlert.loading('กำลังบันทึก...');
                        $.post(apiUrl('Server_price/api/edit_rental_name.php'), { rental_id: rentalId, new_name: newName }, function (response) {
                            cAlert.closeLoading(loading);
                            if (response.success) {
                                cAlert.toast({ title: 'บันทึกสำเร็จ' });
                                document.getElementById(`rental-name-${rentalId}`).textContent = newName;

                                // Update Config URL & QR Code
                                const configInput = document.getElementById(`config-${rentalId}`);
                                if (configInput) {
                                    let currentUrl = configInput.value;
                                    if (currentUrl.includes('#')) {
                                        currentUrl = currentUrl.substring(0, currentUrl.indexOf('#'));
                                    }
                                    const newConfigUrl = currentUrl + '#' + encodeURIComponent(newName);
                                    configInput.value = newConfigUrl;

                                    const qrImg = document.getElementById(`qrcode-${rentalId}`);
                                    if (qrImg) {
                                        qrImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(newConfigUrl);
                                    }
                                }
                            }
                            else cAlert.fire({ title: 'Error', content: response.message });
                        }, 'json').fail(() => {
                            cAlert.closeLoading(loading);
                            cAlert.fire({ title: 'Error', content: 'Connection failed' });
                        });
                    }
                });
            }

            function refreshAllTraffic(silent = false) {
                const btn = $('#refreshAllBtn');
                btn.addClass('syncing');
                let completed = 0;
                const cards = $('.vpn-card');
                if (cards.length === 0) { btn.removeClass('syncing'); return; }

                cards.each(function () {
                    const rentalId = $(this).data('rental-id');
                    const card = $(this);
                    $.ajax({
                        url: apiUrl('Server_price/sync_traffic.php'), method: 'POST', data: { rental_id: rentalId }, dataType: 'json',
                        success: (response) => { if (response.success) updateTrafficDisplay(card, response.data); },
                        complete: () => { completed++; if (completed === cards.length) { btn.removeClass('syncing'); if (!silent) cAlert.toast({ title: 'อัพเดทแล้ว' }); } }
                    });
                });
            }

            function updateTrafficDisplay(card, data) {
                const percentage = data.percentage || 0;
                card.find('.traffic-used').text(((data.data_used_bytes || 0) / (1024 ** 3)).toFixed(2));
                card.find('.traffic-percent').text(percentage.toFixed(1) + '%');
                card.find('.traffic-bar').css('width', Math.min(100, percentage) + '%');

                let progressClass = percentage >= 90 ? 'bg-danger' : percentage >= 75 ? 'bg-warning' : percentage >= 50 ? 'bg-info' : 'bg-success';
                card.find('.traffic-bar').removeClass('bg-success bg-info bg-warning bg-danger').addClass(progressClass);

                // 🔄 Real-time Config & QR Update
                if (data.config_url) {
                    const rentalId = card.data('rental-id');
                    const configInput = card.find(`#config-${rentalId}`);
                    const qrImg = card.find(`#qrcode-${rentalId}`);

                    if (configInput.length && configInput.val() !== data.config_url) {
                        configInput.val(data.config_url);
                        configInput.addClass('config-updated');
                        setTimeout(() => configInput.removeClass('config-updated'), 2000);
                    }

                    if (qrImg.length) {
                        const newQrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(data.config_url);
                        if (qrImg.attr('src') !== newQrUrl) {
                            qrImg.attr('src', newQrUrl);
                        }
                    }
                }
            }

            function extendDays(rentalId, pricePerDay, minDays) {
                const initialDays = 30; const maxDays = 365;
                // Calculate max days affordable
                let maxAffordable = Math.floor(userBalance / pricePerDay);
                // Ensure minDays is valid
                minDays = parseInt(minDays) || 1;

                // Effective max is stricter of global max or affordable max
                let effectiveMax = Math.min(maxDays, maxAffordable);
                // If effectiveMax is less than minDays, user cannot afford even 1 unit (logic handled below) but we set slider max to at least minDays to allow UI to render, then block confirm
                let sliderMax = Math.max(minDays, effectiveMax);

                let startVal = Math.max(minDays, Math.min(initialDays, sliderMax));

                const content = `
                <div class="slider-container">
                    <div class="slider-label"><span>จำนวนวัน (฿${pricePerDay}/วัน)</span> <span class="slider-value" id="daysValue">${startVal} วัน</span></div>
                    <div class="stepper-input mb-3">
                        <button class="stepper-btn" id="days-minus">-</button>
                        <input type="number" class="c-modal-input" id="daysInput" value="${startVal}" min="${minDays}" max="${sliderMax}">
                        <button class="stepper-btn" id="days-plus">+</button>
                    </div>
                    <input type="range" class="custom-slider" id="daysSlider" min="${minDays}" max="${sliderMax}" value="${startVal}">
                    ${effectiveMax < minDays ? '<p class="text-danger small mt-2">ยอดเงินของคุณไม่เพียงพอสำหรับการต่ออายุขั้นต่ำ</p>' : ''}
                </div>
                <div class="mt-3 border-top pt-2">
                    <div class="d-flex justify-content-between"><span>ราคา:</span> <span id="extend-price">฿...</span></div>
                    <div class="d-flex justify-content-between fw-bold"><span>คงเหลือหลังหัก:</span> <span id="balance-after">฿...</span></div>
                </div>`;

                cAlert.fire({ title: 'ต่ออายุ VPN', content: content, showCancel: true, confirmText: 'ยืนยัน' }).then((r) => {
                    if (r.isConfirmed) {
                        // Try to get value from result, or fallback to DOM (safeguard)
                        let days = r.value;
                        if (!days) days = $('#daysInput').val();

                        days = parseInt(days);
                        if (!days || days <= 0) {
                            cAlert.toast({ title: 'Error: Invalid Days', icon: 'error' });
                            return;
                        }

                        // Backend double check will catch it, but frontend check:
                        if (days * pricePerDay > userBalance) {
                            cAlert.fire({ title: 'Error', content: 'ยอดเงินไม่เพียงพอ' });
                            return;
                        }

                        const loading = cAlert.loading('กำลังต่ออายุ...');
                        $.post(apiUrl('Server_price/api/extend_days.php'), { rental_id: rentalId, days: days }, (res) => {
                            cAlert.closeLoading(loading);
                            if (res.success) {
                                cAlert.fire({ title: 'สำเร็จ', content: res.message }).then(() => location.reload());
                            } else {
                                cAlert.fire({ title: 'Error', content: res.message });
                            }
                        }, 'json').fail((xhr) => {
                            cAlert.closeLoading(loading);
                            cAlert.fire({ title: 'Error', content: 'Connection failed: ' + xhr.responseText });
                        });
                    }
                });

                setTimeout(() => {
                    const update = (v) => {
                        v = Math.max(minDays, Math.min(sliderMax, parseInt(v) || minDays));
                        $('#daysInput, #daysSlider').val(v); $('#daysValue').text(v + ' วัน');
                        const cost = v * pricePerDay; const bal = userBalance - cost;
                        $('#extend-price').text('฿' + cost.toFixed(2));
                        $('#balance-after').text('฿' + bal.toFixed(2)).css('color', bal < 0 ? '#ef4444' : '#10b981');
                        $('.c-modal-confirm').prop('disabled', bal < 0);
                    };
                    $('#daysSlider').on('input', function () { update(this.value); });
                    $('#days-minus').click(() => update(parseInt($('#daysInput').val()) - 1));
                    $('#days-plus').click(() => update(parseInt($('#daysInput').val()) + 1));
                    update(startVal);
                    // Initial check to disable if startVal is unaffordable (shouldn't happen with math above unless balance < min cost)
                    if ((startVal * pricePerDay) > userBalance) $('.c-modal-confirm').prop('disabled', true);
                }, 100);
            }

            function addData(rentalId, pricePerGb, minGb) {
                const initialGb = minGb; const maxGb = 1000; const step = 10;

                // Calculate max GB affordable
                let maxAffordable = Math.floor(userBalance / pricePerGb);
                // Round down to step
                maxAffordable = Math.floor(maxAffordable / step) * step;

                let effectiveMax = Math.min(maxGb, maxAffordable);
                let sliderMax = Math.max(minGb, effectiveMax);
                sliderMax = Math.ceil(sliderMax / step) * step; // Ensure step div

                let startVal = Math.max(minGb, Math.min(initialGb, sliderMax));

                const content = `
                <div class="slider-container">
                    <div class="slider-label"><span>Data (฿${pricePerGb}/GB)</span> <span class="slider-value" id="dataValue">${startVal} GB</span></div>
                    <div class="stepper-input mb-3">
                        <button class="stepper-btn" id="data-minus">-</button>
                        <input type="number" class="c-modal-input" id="dataInput" value="${startVal}" min="${minGb}" max="${sliderMax}" step="${step}">
                        <button class="stepper-btn" id="data-plus">+</button>
                    </div>
                    <input type="range" class="custom-slider" id="dataSlider" min="${minGb}" max="${sliderMax}" value="${startVal}" step="${step}">
                     ${effectiveMax < minGb ? '<p class="text-danger small mt-2">ยอดเงินของคุณไม่เพียงพอสำหรับเพิ่ม Data ขั้นต่ำ</p>' : ''}
                </div>
                <div class="mt-3 border-top pt-2">
                    <div class="d-flex justify-content-between"><span>ราคา:</span> <span id="data-price">฿...</span></div>
                    <div class="d-flex justify-content-between fw-bold"><span>คงเหลือหลังหัก:</span> <span id="balance-after">฿...</span></div>
                </div>`;

                cAlert.fire({ title: 'เพิ่ม Data', content: content, showCancel: true, confirmText: 'ยืนยัน' }).then((r) => {
                    if (r.isConfirmed) {
                        let dataGb = r.value;
                        if (!dataGb) dataGb = $('#dataInput').val();

                        dataGb = parseInt(dataGb);
                        if (!dataGb || dataGb <= 0) {
                            cAlert.toast({ title: 'Error: Invalid Data Amount', icon: 'error' });
                            return;
                        }

                        if (dataGb * pricePerGb > userBalance) {
                            cAlert.fire({ title: 'Error', content: 'ยอดเงินไม่เพียงพอ' });
                            return;
                        }

                        const loading = cAlert.loading('กำลังเพิ่ม Data...');
                        $.post(apiUrl('Server_price/api/add_data.php'), { rental_id: rentalId, data_gb: dataGb }, (res) => {
                            cAlert.closeLoading(loading);
                            if (res.success) {
                                cAlert.fire({ title: 'สำเร็จ', content: res.message }).then(() => location.reload());
                            } else {
                                cAlert.fire({ title: 'Error', content: res.message });
                            }
                        }, 'json').fail((xhr) => {
                            cAlert.closeLoading(loading);
                            cAlert.fire({ title: 'Error', content: 'Connection failed: ' + xhr.responseText });
                        });
                    }
                });

                setTimeout(() => {
                    const update = (v) => {
                        v = Math.max(minGb, Math.min(sliderMax, parseInt(v) || minGb));
                        v = Math.round(v / step) * step;
                        $('#dataInput, #dataSlider').val(v); $('#dataValue').text(v + ' GB');
                        const cost = v * pricePerGb; const bal = userBalance - cost;
                        $('#data-price').text('฿' + cost.toFixed(2));
                        $('#balance-after').text('฿' + bal.toFixed(2)).css('color', bal < 0 ? '#ef4444' : '#10b981');
                        $('.c-modal-confirm').prop('disabled', bal < 0);
                    };
                    $('#dataSlider').on('input', function () { update(this.value); });
                    $('#data-minus').click(() => update(parseInt($('#dataInput').val()) - step));
                    $('#data-plus').click(() => update(parseInt($('#dataInput').val()) + step));
                    update(startVal);
                    if ((startVal * pricePerGb) > userBalance) $('.c-modal-confirm').prop('disabled', true);
                }, 100);
            }

            function deleteRental(rentalId) {
                cAlert.fire({ title: 'ยืนยันลบ', content: 'ลบ VPN นี้? ไม่สามารถกู้คืนได้', showCancel: true, confirmText: 'ลบเลย' }).then((r) => {
                    if (r.isConfirmed) {
                        const loading = cAlert.loading('กำลังลบ...');
                        $.post(apiUrl('Server_price/api/delete_rental.php'), { rental_id: rentalId }, (res) => {
                            cAlert.closeLoading(loading);
                            if (res.success) location.reload(); else cAlert.fire({ title: 'Error', content: res.message });
                        }, 'json');
                    }
                });
            }

            function initDeleteCountdowns() {
                document.querySelectorAll('.delete-countdown-alert').forEach(alert => {
                    let seconds = parseInt(alert.dataset.seconds);
                    const rentalId = alert.dataset.rentalId;

                    const updateDisplay = () => {
                        if (seconds <= 0) {
                            location.reload();
                            return;
                        }

                        const days = Math.floor(seconds / 86400);
                        const hours = Math.floor((seconds % 86400) / 3600);
                        const minutes = Math.floor((seconds % 3600) / 60);
                        const secs = seconds % 60;

                        alert.querySelector('.days').textContent = String(days).padStart(2, '0');
                        alert.querySelector('.hours').textContent = String(hours).padStart(2, '0');
                        alert.querySelector('.minutes').textContent = String(minutes).padStart(2, '0');
                        alert.querySelector('.seconds').textContent = String(secs).padStart(2, '0');
                    };

                    updateDisplay();

                    setInterval(() => {
                        seconds--;
                        updateDisplay();
                    }, 1000);
                });
            }

            setInterval(refreshAllTraffic, 5 * 60 * 1000);

            // ✅ Auto-Renew VPN Toggle
            function toggleAutoRenewVPN(id, isChecked) {
                const status = isChecked ? 1 : 0;
                const statusEl = $(`#autoRenewStatus_${id}`);

                // Update status label immediately for better UX
                if (isChecked) {
                    statusEl.removeClass('status-off').addClass('status-on').text('เปิดใช้งาน');
                } else {
                    statusEl.removeClass('status-on').addClass('status-off').text('ปิดอยู่');
                }

                $.ajax({
                    url: apiUrl('Server_price/api/toggle_auto_renew.php'),
                    method: 'POST',
                    data: { rental_id: id, status: status },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            cAlert.toast({ title: res.message, icon: 'success' });
                        } else {
                            const detail = (res.detail || res.message || 'เกิดข้อผิดพลาด');
                            cAlert.fire({
                                title: res.message || 'เกิดข้อผิดพลาด',
                                content: '<p class="mb-0">' + detail + '</p>',
                                icon: 'error'
                            });
                            // Revert toggle and status label on error
                            $(`#autoRenewVPN_${id}`).prop('checked', !isChecked);
                            if (!isChecked) {
                                statusEl.removeClass('status-off').addClass('status-on').text('เปิดใช้งาน');
                            } else {
                                statusEl.removeClass('status-on').addClass('status-off').text('ปิดอยู่');
                            }
                        }
                    },
                    error: function (xhr, status, err) {
                        let msg = 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้';
                        if (xhr.status === 404) msg = 'ไม่พบ API (404) กรุณาตรวจสอบ path ของเว็บไซต์';
                        else if (xhr.status === 500) msg = 'เซิร์ฟเวอร์เกิดข้อผิดพลาด (500) กรุณาลองใหม่หรือติดต่อแอดมิน';
                        else if (xhr.responseText) msg += ' (' + (xhr.responseText.substring(0, 100) || status) + ')';
                        cAlert.fire({ title: 'เกิดข้อผิดพลาด', content: msg, icon: 'error' });
                        // Revert toggle and status label on error
                        $(`#autoRenewVPN_${id}`).prop('checked', !isChecked);
                        if (!isChecked) {
                            statusEl.removeClass('status-off').addClass('status-on').text('เปิดใช้งาน');
                        } else {
                            statusEl.removeClass('status-on').addClass('status-off').text('ปิดอยู่');
                        }
                    }
                });
            }
        </script>
</body>

</html>