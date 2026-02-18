<?php
// home/dashboard.php - Premium Dashboard V4.0 (Modern & Professional)
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (session_status() == PHP_SESSION_NONE)
    session_start();

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

if (!isset($conn) || $conn === null) {
    require_once dirname(__DIR__) . '/controller/config.php';
}

// ดึงข้อมูลผู้ใช้
$user = null;
try {
    if (isset($conn) && $user_id > 0) {
        $stmt = $conn->prepare("SELECT username, email, credit, register_at FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }
} catch (Exception $e) {
}

if (!$user) {
    header('Location: controller/logout.php');
    exit;
}

$days_member = 0;
if (!empty($user['register_at'])) {
    try {
        $days_member = (new DateTime($user['register_at']))->diff(new DateTime())->days;
    } catch (Exception $e) {
    }
}

// SSH Stats
$active_ssh = 0;
$ssh_list = [];
try {
    $check = $conn->query("SHOW TABLES LIKE 'ssh_rentals'");
    if ($check && $check->num_rows > 0) {
        $stmt = $conn->prepare("SELECT r.*, p.product_name, s.server_name FROM ssh_rentals r LEFT JOIN ssh_products p ON r.product_id = p.id LEFT JOIN ssh_servers s ON r.server_id = s.server_id WHERE r.user_id = ? AND r.status = 'active' AND r.expire_date > NOW() ORDER BY r.expire_date ASC");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result)
                $ssh_list = $result->fetch_all(MYSQLI_ASSOC);
            $active_ssh = count($ssh_list);
            $stmt->close();
        }
    }
} catch (Exception $e) {
}

// VPN Stats
$active_vpn = 0;
$vpn_list = [];
try {
    $check = $conn->query("SHOW TABLES LIKE 'user_rentals'");
    if ($check && $check->num_rows > 0) {
        $stmt = $conn->prepare("SELECT r.*, s.server_name, p.filename as profile_name FROM user_rentals r LEFT JOIN servers s ON r.server_id = s.server_id LEFT JOIN price_v2 p ON r.price_id = p.id WHERE r.user_id = ? AND r.deleted_at IS NULL ORDER BY r.expire_date DESC");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result)
                $vpn_list = $result->fetch_all(MYSQLI_ASSOC);
            $active_vpn = count($vpn_list);
            $stmt->close();
        }
    }
} catch (Exception $e) {
}

$total_services = $active_ssh + $active_vpn;

if (file_exists('home/header.php'))
    include 'home/header.php';
if (file_exists('home/navbar.php'))
    include 'home/navbar.php';
?>

<style>
    /* ===== Dashboard V4.0 Modern Theme ===== */
    :root {
        --db-bg: #050508;
        --db-card: rgba(15, 15, 22, 0.8);
        --db-card-hover: rgba(25, 25, 35, 0.9);
        --db-border: rgba(255, 255, 255, 0.06);
        --db-border-light: rgba(255, 255, 255, 0.03);
        --db-text: #ffffff;
        --db-text-muted: rgba(255, 255, 255, 0.5);
        --db-accent: #E50914;
        --db-accent-soft: rgba(229, 9, 20, 0.15);
        --db-radius: 20px;
        --db-radius-sm: 14px;
        --db-glow: 0 0 40px rgba(229, 9, 20, 0.15);
    }

    .dashboard-v4 {
        background: var(--db-bg);
        min-height: 100vh;
        padding: 1.5rem 0 6rem;
        position: relative;
        font-family: 'Prompt', 'Segoe UI', sans-serif;
    }

    /* Animated Background */
    .dashboard-v4::before {
        content: '';
        position: fixed;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: 
            radial-gradient(circle at 20% 30%, rgba(229, 9, 20, 0.08) 0%, transparent 40%),
            radial-gradient(circle at 80% 70%, rgba(99, 102, 241, 0.06) 0%, transparent 40%);
        animation: bgFloat 20s ease-in-out infinite;
        pointer-events: none;
        z-index: 0;
    }

    @keyframes bgFloat {
        0%, 100% { transform: translate(0, 0); }
        33% { transform: translate(-2%, 2%); }
        66% { transform: translate(2%, -2%); }
    }

    .dashboard-v4 .container {
        position: relative;
        z-index: 1;
    }

    /* Glass Card Base */
    .glass-card-v4 {
        background: var(--db-card);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--db-border);
        border-radius: var(--db-radius);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .glass-card-v4:hover {
        border-color: rgba(255, 255, 255, 0.1);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
    }

    /* Hero Welcome */
    .hero-welcome-v4 {
        background: linear-gradient(135deg, rgba(229, 9, 20, 0.1), rgba(15, 15, 25, 0.95));
        border: 1px solid rgba(229, 9, 20, 0.15);
        border-radius: var(--db-radius);
        padding: 2.5rem 2rem;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }

    .hero-welcome-v4::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(229, 9, 20, 0.15), transparent 70%);
        animation: pulseGlow 4s ease-in-out infinite;
    }

    @keyframes pulseGlow {
        0%, 100% { opacity: 0.5; transform: scale(1); }
        50% { opacity: 0.8; transform: scale(1.1); }
    }

    .welcome-content {
        position: relative;
        z-index: 1;
    }

    .greeting-v4 {
        font-size: 0.95rem;
        color: var(--db-text-muted);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .greeting-v4 i {
        color: var(--db-accent);
        animation: wave 2s ease-in-out infinite;
    }

    @keyframes wave {
        0%, 100% { transform: rotate(0deg); }
        25% { transform: rotate(-10deg); }
        75% { transform: rotate(10deg); }
    }

    .username-v4 {
        font-size: 2.25rem;
        font-weight: 700;
        background: linear-gradient(135deg, #fff 0%, rgba(255,255,255,0.7) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 1rem;
        letter-spacing: -0.5px;
    }

    .member-badge-v4 {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: rgba(0, 184, 148, 0.15);
        color: #00d2a0;
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
        border: 1px solid rgba(0, 184, 148, 0.2);
    }

    /* Stats Grid */
    .stats-grid-v4 {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-card-v4 {
        background: var(--db-card);
        backdrop-filter: blur(10px);
        border: 1px solid var(--db-border);
        border-radius: var(--db-radius-sm);
        padding: 1.5rem 1rem;
        text-align: center;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .stat-card-v4::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--gradient, linear-gradient(90deg, var(--db-accent), #ff6b6b));
        opacity: 0.7;
        transition: opacity 0.3s;
    }

    .stat-card-v4:hover {
        transform: translateY(-5px);
        background: var(--db-card-hover);
        box-shadow: var(--db-glow);
    }

    .stat-card-v4:hover::before {
        opacity: 1;
    }

    .stat-card-v4.red { --gradient: linear-gradient(90deg, #E50914, #ff4757); }
    .stat-card-v4.blue { --gradient: linear-gradient(90deg, #3b82f6, #60a5fa); }
    .stat-card-v4.green { --gradient: linear-gradient(90deg, #10b981, #34d399); }
    .stat-card-v4.purple { --gradient: linear-gradient(90deg, #8b5cf6, #a78bfa); }

    .stat-icon-v4 {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.25rem;
        color: white;
        background: var(--gradient);
        box-shadow: 0 8px 20px rgba(0,0,0,0.2);
    }

    .stat-value-v4 {
        font-size: 2rem;
        font-weight: 700;
        color: var(--db-text);
        margin-bottom: 0.25rem;
        line-height: 1;
    }

    .stat-label-v4 {
        font-size: 0.8rem;
        color: var(--db-text-muted);
        font-weight: 500;
    }

    /* Quick Actions */
    .quick-actions-v4 {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 2.5rem;
    }

    .quick-card-v4 {
        background: var(--db-card);
        border: 1px solid var(--db-border);
        border-radius: var(--db-radius-sm);
        padding: 1.5rem 1rem;
        text-align: center;
        text-decoration: none !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .quick-card-v4::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, var(--card-color, var(--db-accent)), transparent);
        opacity: 0;
        transition: opacity 0.3s;
    }

    .quick-card-v4:hover {
        transform: translateY(-5px) scale(1.02);
        border-color: var(--card-color, var(--db-accent));
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }

    .quick-card-v4:hover::before {
        opacity: 0.1;
    }

    .quick-card-v4:hover .quick-icon-v4 {
        transform: scale(1.1) rotate(5deg);
    }

    .quick-icon-v4 {
        width: 55px;
        height: 55px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.4rem;
        color: white;
        position: relative;
        z-index: 1;
        transition: transform 0.3s ease;
    }

    .quick-card-v4.ssh { --card-color: #E50914; }
    .quick-card-v4.ssh .quick-icon-v4 { background: linear-gradient(135deg, #E50914, #b91c1c); }
    
    .quick-card-v4.vpn { --card-color: #3b82f6; }
    .quick-card-v4.vpn .quick-icon-v4 { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
    
    .quick-card-v4.topup { --card-color: #10b981; }
    .quick-card-v4.topup .quick-icon-v4 { background: linear-gradient(135deg, #10b981, #059669); }
    
    .quick-card-v4.history { --card-color: #8b5cf6; }
    .quick-card-v4.history .quick-icon-v4 { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }

    .quick-label-v4 {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--db-text);
        position: relative;
        z-index: 1;
    }

    .quick-desc-v4 {
        font-size: 0.75rem;
        color: var(--db-text-muted);
        margin-top: 0.25rem;
        position: relative;
        z-index: 1;
    }

    /* Section Header */
    .section-header-v4 {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.25rem;
    }

    .section-title-v4 {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--db-text);
    }

    .section-icon-v4 {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .section-icon-v4.red {
        background: rgba(229, 9, 20, 0.15);
        color: #ff4757;
    }

    .section-icon-v4.blue {
        background: rgba(59, 130, 246, 0.15);
        color: #60a5fa;
    }

    .count-badge-v4 {
        background: var(--db-accent-soft);
        color: var(--db-accent);
        padding: 0.25rem 0.75rem;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 700;
        margin-left: 0.5rem;
    }

    .view-all-v4 {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        color: var(--db-text-muted);
        text-decoration: none !important;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.2s;
    }

    .view-all-v4:hover {
        color: var(--db-accent);
        gap: 0.6rem;
    }

    /* Service Cards */
    .service-list-v4 {
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
        margin-bottom: 2.5rem;
    }

    .service-card-v4 {
        display: flex;
        align-items: center;
        gap: 1rem;
        background: var(--db-card);
        border: 1px solid var(--db-border);
        border-radius: var(--db-radius-sm);
        padding: 1.125rem 1.25rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .service-card-v4::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--status-color, transparent);
        opacity: 0;
        transition: opacity 0.3s;
    }

    .service-card-v4:hover {
        background: var(--db-card-hover);
        transform: translateX(5px);
        border-color: rgba(255,255,255,0.1);
    }

    .service-card-v4:hover::before {
        opacity: 1;
    }

    .service-card-v4.urgent {
        --status-color: #f59e0b;
        border-left-color: #f59e0b;
    }

    .service-card-v4.expired {
        --status-color: #ef4444;
        opacity: 0.7;
    }

    .service-card-v4.good {
        --status-color: #10b981;
    }

    .svc-icon-v4 {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: white;
        flex-shrink: 0;
    }

    .svc-icon-v4.ssh { background: linear-gradient(135deg, #E50914, #b91c1c); }
    .svc-icon-v4.vpn { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }

    .svc-info-v4 {
        flex: 1;
        min-width: 0;
    }

    .svc-name-v4 {
        font-weight: 600;
        color: var(--db-text);
        font-size: 0.95rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 0.35rem;
    }

    .svc-meta-v4 {
        display: flex;
        align-items: center;
        gap: 1rem;
        font-size: 0.8rem;
        color: var(--db-text-muted);
    }

    .svc-meta-v4 i {
        margin-right: 0.3rem;
        opacity: 0.7;
    }

    .svc-days-v4 {
        text-align: center;
        flex-shrink: 0;
        min-width: 60px;
    }

    .days-value-v4 {
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1;
    }

    .days-value-v4.good { color: #10b981; }
    .days-value-v4.warning { color: #f59e0b; }
    .days-value-v4.danger { color: #ef4444; }

    .days-label-v4 {
        font-size: 0.7rem;
        color: var(--db-text-muted);
        margin-top: 0.25rem;
    }

    /* Empty State */
    .empty-state-v4 {
        text-align: center;
        padding: 3rem 2rem;
        background: var(--db-card);
        border: 1px dashed var(--db-border);
        border-radius: var(--db-radius);
        margin-bottom: 2rem;
    }

    .empty-icon-v4 {
        width: 80px;
        height: 80px;
        border-radius: 20px;
        background: rgba(255,255,255,0.03);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.25rem;
        font-size: 2rem;
        color: var(--db-text-muted);
    }

    .empty-state-v4 h4 {
        color: var(--db-text);
        margin-bottom: 0.5rem;
        font-size: 1.1rem;
    }

    .empty-state-v4 p {
        color: var(--db-text-muted);
        margin-bottom: 1.5rem;
        font-size: 0.9rem;
    }

    .btn-rent-v4 {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        border-radius: var(--db-radius-sm);
        font-weight: 600;
        text-decoration: none !important;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .btn-rent-v4.ssh {
        background: linear-gradient(135deg, #E50914, #b91c1c);
        color: white;
        box-shadow: 0 8px 25px rgba(229, 9, 20, 0.3);
    }

    .btn-rent-v4.vpn {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        color: white;
        box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
    }

    .btn-rent-v4:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 35px rgba(0,0,0,0.3);
    }

    /* Sidebar */
    .sidebar-v4 {
        position: sticky;
        top: 90px;
    }

    .sidebar-card-v4 {
        background: var(--db-card);
        border: 1px solid var(--db-border);
        border-radius: var(--db-radius);
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }

    .sidebar-card-v4:hover {
        border-color: rgba(255,255,255,0.1);
    }

    .balance-header-v4 {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }

    .balance-label-v4 {
        font-size: 0.75rem;
        color: var(--db-text-muted);
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 600;
    }

    .balance-icon-v4 {
        color: #8b5cf6;
    }

    .balance-amt-v4 {
        font-size: 2.25rem;
        font-weight: 700;
        background: linear-gradient(135deg, #fff, #a78bfa);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 1.25rem;
        line-height: 1;
    }

    .balance-amt-v4 span {
        font-size: 1rem;
        -webkit-text-fill-color: var(--db-text-muted);
        margin-right: 0.25rem;
    }

    .btn-topup-v4 {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        width: 100%;
        padding: 1rem;
        background: linear-gradient(135deg, #8b5cf6, #6d28d9);
        border: none;
        border-radius: var(--db-radius-sm);
        color: white;
        font-weight: 600;
        text-decoration: none !important;
        transition: all 0.3s ease;
        box-shadow: 0 8px 25px rgba(139, 92, 246, 0.35);
    }

    .btn-topup-v4:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 35px rgba(139, 92, 246, 0.45);
        color: white;
    }

    .profile-row-v4 {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .avatar-v4 {
        width: 55px;
        height: 55px;
        background: linear-gradient(135deg, var(--db-accent), #8b5cf6);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        font-weight: 700;
        color: white;
        box-shadow: 0 8px 20px rgba(229, 9, 20, 0.3);
    }

    .profile-name-v4 {
        font-weight: 600;
        color: var(--db-text);
        font-size: 1rem;
        margin-bottom: 0.15rem;
    }

    .profile-email-v4 {
        font-size: 0.8rem;
        color: var(--db-text-muted);
    }

    .btn-settings-v4 {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        width: 100%;
        padding: 0.75rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--db-border);
        border-radius: var(--db-radius-sm);
        color: var(--db-text-muted);
        text-decoration: none !important;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.2s;
    }

    .btn-settings-v4:hover {
        background: rgba(255, 255, 255, 0.08);
        color: var(--db-text);
        border-color: rgba(255,255,255,0.15);
    }

    .support-card-v4 {
        background: linear-gradient(135deg, rgba(229, 9, 20, 0.08), rgba(139, 92, 246, 0.08));
        border: 1px solid rgba(229, 9, 20, 0.15);
    }

    .support-content {
        text-align: center;
        padding: 0.5rem 0;
    }

    .support-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        background: rgba(229, 9, 20, 0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.5rem;
        color: var(--db-accent);
    }

    .support-title {
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--db-text);
    }

    .support-desc {
        font-size: 0.85rem;
        color: var(--db-text-muted);
        margin-bottom: 1rem;
    }

    .btn-support-v4 {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1.25rem;
        background: var(--db-accent);
        color: white;
        border-radius: 10px;
        text-decoration: none !important;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .btn-support-v4:hover {
        background: #ff1a25;
        transform: translateY(-2px);
        color: white;
    }

    /* Responsive */
    @media (max-width: 992px) {
        .stats-grid-v4,
        .quick-actions-v4 {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .sidebar-v4 {
            position: relative;
            top: 0;
        }
    }

    @media (max-width: 576px) {
        .username-v4 {
            font-size: 1.75rem;
        }
        
        .stat-value-v4 {
            font-size: 1.5rem;
        }
        
        .service-card-v4 {
            padding: 1rem;
        }
        
        .svc-icon-v4 {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }
        
        .days-value-v4 {
            font-size: 1.25rem;
        }
    }
</style>

<div class="dashboard-v4">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-8">

                <!-- Hero Welcome -->
                <div class="hero-welcome-v4">
                    <div class="welcome-content">
                        <div class="greeting-v4">
                            <i class="fas fa-hand-sparkles"></i>
                            สวัสดี, ยินดีต้อนรับกลับมา
                        </div>
                        <div class="username-v4"><?php echo htmlspecialchars($user['username'] ?? 'Guest'); ?></div>
                        <div class="member-badge-v4">
                            <i class="fas fa-crown"></i>
                            สมาชิกมาแล้ว <?php echo $days_member; ?> วัน
                        </div>
                    </div>
                </div>

                <!-- Stats -->
                <div class="stats-grid-v4">
                    <div class="stat-card-v4 red">
                        <div class="stat-icon-v4"><i class="fas fa-layer-group"></i></div>
                        <div class="stat-value-v4"><?php echo $total_services; ?></div>
                        <div class="stat-label-v4">บริการทั้งหมด</div>
                    </div>
                    <div class="stat-card-v4 blue">
                        <div class="stat-icon-v4"><i class="fas fa-terminal"></i></div>
                        <div class="stat-value-v4"><?php echo $active_ssh; ?></div>
                        <div class="stat-label-v4">SSH ใช้งาน</div>
                    </div>
                    <div class="stat-card-v4 green">
                        <div class="stat-icon-v4"><i class="fas fa-shield-alt"></i></div>
                        <div class="stat-value-v4"><?php echo $active_vpn; ?></div>
                        <div class="stat-label-v4">VPN ใช้งาน</div>
                    </div>
                    <div class="stat-card-v4 purple">
                        <div class="stat-icon-v4"><i class="fas fa-calendar-check"></i></div>
                        <div class="stat-value-v4"><?php echo $days_member; ?></div>
                        <div class="stat-label-v4">วันเป็นสมาชิก</div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions-v4">
                    <a href="?p=rent_ssh" class="quick-card-v4 ssh">
                        <div class="quick-icon-v4"><i class="fas fa-terminal"></i></div>
                        <div class="quick-label-v4">เช่า SSH</div>
                        <div class="quick-desc-v4">เชื่อมต่อรวดเร็ว</div>
                    </a>
                    <a href="?p=rent_vpn" class="quick-card-v4 vpn">
                        <div class="quick-icon-v4"><i class="fas fa-shield-alt"></i></div>
                        <div class="quick-label-v4">เช่า VPN</div>
                        <div class="quick-desc-v4">ความปลอดภัยสูง</div>
                    </a>
                    <a href="?p=topup" class="quick-card-v4 topup">
                        <div class="quick-icon-v4"><i class="fas fa-coins"></i></div>
                        <div class="quick-label-v4">เติมเงิน</div>
                        <div class="quick-desc-v4">เติมเครดิต</div>
                    </a>
                    <a href="?p=topup_history" class="quick-card-v4 history">
                        <div class="quick-icon-v4"><i class="fas fa-clock-rotate-left"></i></div>
                        <div class="quick-label-v4">ประวัติ</div>
                        <div class="quick-desc-v4">ธุรกรรมทั้งหมด</div>
                    </a>
                </div>

                <!-- SSH Section -->
                <div class="section-header-v4">
                    <div class="section-title-v4">
                        <span class="section-icon-v4 red"><i class="fas fa-terminal"></i></span>
                        SSH ของฉัน
                        <span class="count-badge-v4"><?php echo $active_ssh; ?></span>
                    </div>
                    <a href="?p=my_ssh" class="view-all-v4">
                        ดูทั้งหมด <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <?php if (!empty($ssh_list)): ?>
                    <div class="service-list-v4">
                        <?php foreach ($ssh_list as $s):
                            $d = max(0, (int) ceil((strtotime($s['expire_date']) - time()) / 86400));
                            $statusClass = $d <= 1 ? 'danger' : ($d <= 3 ? 'warning' : 'good');
                            $cardClass = $d <= 3 ? ($d <= 1 ? 'expired' : 'urgent') : 'good';
                            ?>
                            <div class="service-card-v4 <?php echo $cardClass; ?>">
                                <div class="svc-icon-v4 ssh"><i class="fas fa-terminal"></i></div>
                                <div class="svc-info-v4">
                                    <div class="svc-name-v4">
                                        <?php echo htmlspecialchars($s['product_name'] ?? $s['ssh_username'] ?? 'SSH'); ?>
                                    </div>
                                    <div class="svc-meta-v4">
                                        <span><i class="fas fa-server"></i><?php echo htmlspecialchars($s['server_name'] ?? '-'); ?></span>
                                        <span><i class="fas fa-user"></i><?php echo htmlspecialchars($s['ssh_username'] ?? '-'); ?></span>
                                    </div>
                                </div>
                                <div class="svc-days-v4">
                                    <div class="days-value-v4 <?php echo $statusClass; ?>"><?php echo $d; ?></div>
                                    <div class="days-label-v4">วันเหลือ</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state-v4">
                        <div class="empty-icon-v4"><i class="fas fa-terminal"></i></div>
                        <h4>ยังไม่มี SSH ที่ใช้งาน</h4>
                        <p>เริ่มเช่า SSH เพื่อเชื่อมต่ออินเทอร์เน็ตอย่างรวดเร็วและปลอดภัย</p>
                        <a href="?p=rent_ssh" class="btn-rent-v4 ssh"><i class="fas fa-plus"></i> เช่า SSH เลย</a>
                    </div>
                <?php endif; ?>

                <!-- VPN Section -->
                <div class="section-header-v4">
                    <div class="section-title-v4">
                        <span class="section-icon-v4 blue"><i class="fas fa-shield-alt"></i></span>
                        VPN ของฉัน
                        <span class="count-badge-v4" style="background: rgba(59,130,246,0.15); color: #60a5fa;"><?php echo $active_vpn; ?></span>
                    </div>
                    <a href="?p=my_vpn" class="view-all-v4">
                        ดูทั้งหมด <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <?php if (!empty($vpn_list)): ?>
                    <div class="service-list-v4">
                        <?php foreach ($vpn_list as $v):
                            $d = max(0, (int) ceil((strtotime($v['expire_date']) - time()) / 86400));
                            $statusClass = $d <= 1 ? 'danger' : ($d <= 3 ? 'warning' : 'good');
                            $cardClass = $d <= 3 ? ($d <= 1 ? 'expired' : 'urgent') : 'good';
                            ?>
                            <div class="service-card-v4 <?php echo $cardClass; ?>">
                                <div class="svc-icon-v4 vpn"><i class="fas fa-shield-alt"></i></div>
                                <div class="svc-info-v4">
                                    <div class="svc-name-v4">
                                        <?php echo htmlspecialchars($v['rental_name'] ?? $v['profile_name'] ?? 'VPN'); ?>
                                    </div>
                                    <div class="svc-meta-v4">
                                        <span><i class="fas fa-server"></i><?php echo htmlspecialchars($v['server_name'] ?? '-'); ?></span>
                                        <span><i class="fas fa-globe"></i><?php echo htmlspecialchars($v['profile_name'] ?? '-'); ?></span>
                                    </div>
                                </div>
                                <div class="svc-days-v4">
                                    <div class="days-value-v4 <?php echo $statusClass; ?>"><?php echo $d; ?></div>
                                    <div class="days-label-v4"><?php echo $d > 0 ? 'วันเหลือ' : 'หมดอายุ'; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state-v4">
                        <div class="empty-icon-v4"><i class="fas fa-shield-alt"></i></div>
                        <h4>ยังไม่มี VPN ที่ใช้งาน</h4>
                        <p>เริ่มเช่า VPN เพื่อความเป็นส่วนตัวและปลอดภัยบนโลกออนไลน์</p>
                        <a href="?p=rent_vpn" class="btn-rent-v4 vpn"><i class="fas fa-plus"></i> เช่า VPN เลย</a>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="sidebar-v4">
                    <div class="sidebar-card-v4">
                        <div class="balance-header-v4">
                            <i class="fas fa-wallet balance-icon-v4"></i>
                            <span class="balance-label-v4">ยอดเงินคงเหลือ</span>
                        </div>
                        <div class="balance-amt-v4">
                            <span>฿</span><?php echo number_format((float) ($user['credit'] ?? 0), 2); ?>
                        </div>
                        <a href="?p=topup" class="btn-topup-v4">
                            <i class="fas fa-plus-circle"></i> เติมเงินเลย
                        </a>
                    </div>

                    <div class="sidebar-card-v4">
                        <div class="profile-row-v4">
                            <div class="avatar-v4"><?php echo strtoupper(substr($user['username'] ?? 'G', 0, 1)); ?></div>
                            <div>
                                <div class="profile-name-v4"><?php echo htmlspecialchars($user['username'] ?? '-'); ?></div>
                                <div class="profile-email-v4"><?php echo htmlspecialchars($user['email'] ?? '-'); ?></div>
                            </div>
                        </div>
                        <a href="?p=userdetail" class="btn-settings-v4">
                            <i class="fas fa-cog"></i> ตั้งค่าบัญชี
                        </a>
                    </div>

                    <div class="sidebar-card-v4 support-card-v4">
                        <div class="support-content">
                            <div class="support-icon">
                                <i class="fas fa-headset"></i>
                            </div>
                            <div class="support-title">ต้องการความช่วยเหลือ?</div>
                            <div class="support-desc">ทีมสนับสนุนพร้อมช่วยเหลือตลอด 24 ชั่วโมง</div>
                            <a href="?p=contact" class="btn-support-v4">
                                <i class="fas fa-comments"></i> ติดต่อเรา
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php if (file_exists('home/footer.php'))
    include 'home/footer.php'; ?>
