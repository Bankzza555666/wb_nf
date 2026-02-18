<?php
// admin/navbar.php
// V2.0 Mobile App Style (Floating Bottom Bar)

// ตรวจสอบการเชื่อมต่อ DB สำหรับ Badge แชท
if (isset($conn)) {
    $unread_q = $conn->query("SELECT COUNT(*) as c FROM chat_messages WHERE sender='user' AND is_read=0");
    $unread = $unread_q ? $unread_q->fetch_assoc()['c'] : 0;
} else {
    $unread = 0;
}

$cp = $_GET['p'] ?? 'admin_dashboard'; // Current Page
?>

<nav class="admin-header">
    <div class="container-fluid px-3 d-flex justify-content-between align-items-center h-100">
        <a href="?p=admin_dashboard" class="admin-brand">
            <img src="img/logo.png" alt="Logo" style="height: 35px; width: auto; margin-right: 10px;">
            <span class="brand-text">NF~SHOP <small class="text-muted" style="font-size: 0.7rem;">Admin</small></span>
        </a>

        <a href="controller/logout.php" class="icon-btn text-danger" title="ออกจากระบบ">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</nav>

<?php if ($cp !== 'admin_chat'): ?>
    <div class="admin-bottom-nav-wrapper">
        <div class="admin-bottom-nav">

            <a href="?p=admin_dashboard" class="nav-item <?php echo ($cp == 'admin_dashboard') ? 'active' : ''; ?>">
                <div class="nav-icon"><i class="fas fa-chart-pie"></i></div>
                <span class="nav-label">ภาพรวม</span>
            </a>

            <a href="?p=admin_users" class="nav-item <?php echo ($cp == 'admin_users') ? 'active' : ''; ?>">
                <div class="nav-icon"><i class="fas fa-users"></i></div>
                <span class="nav-label">สมาชิก</span>
            </a>

            <div class="nav-item-center">
                <a href="?p=admin_chat" class="center-btn">
                    <i class="fas fa-comments"></i>
                    <?php if ($unread > 0): ?>
                        <span class="badge-counter"><?php echo $unread > 99 ? '99+' : $unread; ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <a href="#" class="nav-item <?php echo ($cp == 'admin_servers' || $cp == 'admin_ssh_servers') ? 'active' : ''; ?>" id="serverMenuTrigger">
                <div class="nav-icon"><i class="fas fa-server"></i></div>
                <span class="nav-label">เซิร์ฟเวอร์</span>
            </a>
            
            <!-- Server Submenu Popup -->
            <div class="server-submenu" id="serverSubmenu">
                <a href="?p=admin_servers" class="server-submenu-item <?php echo $cp == 'admin_servers' ? 'active' : ''; ?>">
                    <i class="fas fa-cloud"></i>
                    <span>VPN/V2Ray Servers</span>
                </a>
                <a href="?p=admin_ssh_servers" class="server-submenu-item <?php echo $cp == 'admin_ssh_servers' ? 'active' : ''; ?>">
                    <i class="fas fa-terminal"></i>
                    <span>SSH/NPV Servers</span>
                </a>
            </div>

            <a href="#" class="nav-item" id="adminMenuTrigger">
                <div class="nav-icon"><i class="fas fa-bars"></i></div>
                <span class="nav-label">เมนู</span>
            </a>

        </div>
    </div>
<?php endif; ?>

<div class="admin-menu-overlay" id="adminMenuOverlay">
    <div class="menu-content">
        <div class="menu-header">
            <h5 class="m-0 fw-bold text-white"><i class="fas fa-th-large text-primary me-2"></i>เมนูจัดการเพิ่มเติม</h5>
            <button class="btn-close-menu" id="closeAdminMenu"><i class="fas fa-times"></i></button>
        </div>

        <div class="menu-grid">
            <a href="?p=admin_products" class="menu-card">
                <div class="mc-icon c-purple"><i class="fas fa-box-open"></i></div>
                <span>แพ็กเกจ VPN</span>
            </a>
            <a href="?p=admin_ssh_products" class="menu-card">
                <div class="mc-icon c-purple"><i class="fas fa-layer-group"></i></div>
                <span>แพ็กเกจ SSH</span>
            </a>
            <a href="?p=topup_packages" class="menu-card">
                <div class="mc-icon c-green"><i class="fas fa-coins"></i></div>
                <span>ตั้งค่าแพ็กเกจ</span>
            </a>
            <a href="?p=admin_topup" class="menu-card">
                <div class="mc-icon c-green"><i class="fas fa-money-bill-wave"></i></div>
                <span>ประวัติเติมเงิน</span>
            </a>
            <a href="?p=admin_rentals" class="menu-card">
                <div class="mc-icon c-green"><i class="fas fa-clipboard-list"></i></div>
                <span>รายการเช่า</span>
            </a>
            <a href="?p=admin_reports" class="menu-card">
                <div class="mc-icon c-green"><i class="fas fa-chart-bar"></i></div>
                <span>รายงาน</span>
            </a>
            <a href="?p=admin_notifications" class="menu-card">
                <div class="mc-icon c-red"><i class="fas fa-bell"></i></div>
                <span>แจ้งเตือน</span>
            </a>
            <a href="?p=admin_ai_training" class="menu-card">
                <div class="mc-icon c-red"><i class="fas fa-brain"></i></div>
                <span>สอน AI</span>
            </a>
            <a href="?p=admin_ai_analytics" class="menu-card">
                <div class="mc-icon c-red"><i class="fas fa-chart-line"></i></div>
                <span>AI Analytics</span>
            </a>
            <a href="?p=admin_referral" class="menu-card">
                <div class="mc-icon c-pink"><i class="fas fa-users"></i></div>
                <span>ระบบแนะนำเพื่อน</span>
            </a>
            <a href="?p=admin_settings" class="menu-card">
                <div class="mc-icon c-blue"><i class="fas fa-cog"></i></div>
                <span>ตั้งค่าระบบ</span>
            </a>
            <a href="?p=home" target="_blank" class="menu-card">
                <div class="mc-icon c-blue"><i class="fas fa-external-link-alt"></i></div>
                <span>ดูหน้าเว็บ</span>
            </a>
            <a href="controller/logout.php" class="menu-card logout-card">
                <div class="mc-icon c-red"><i class="fas fa-power-off"></i></div>
                <span>ออกจากระบบ</span>
            </a>
        </div>
    </div>
</div>
<div class="admin-backdrop" id="adminBackdrop"></div>

<style>
    :root {
        --adm-bg: rgba(10, 10, 10, 0.95);
        --adm-blur: 20px;
        --adm-border: rgba(229, 9, 20, 0.3);
        --primary: #E50914;
        --text-main: #ffffff;
        --text-sub: #aaaaaa;
    }

    /* ป้องกัน content ถูกบังโดย header และ bottom nav */
    body {
        padding-top: 70px !important;
        padding-bottom: 120px !important;
        min-height: 100vh;
        overflow-x: hidden;
    }

    .container, .container-fluid {
        padding-bottom: 20px;
    }

    /* Top Header */
    .admin-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 60px;
        background: var(--adm-bg);
        backdrop-filter: blur(var(--adm-blur));
        -webkit-backdrop-filter: blur(var(--adm-blur));
        border-bottom: 1px solid var(--adm-border);
        z-index: 1000;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
    }

    .admin-brand {
        text-decoration: none;
        display: flex;
        align-items: center;
        color: white;
    }

    .brand-text {
        font-weight: 800;
        font-size: 1.2rem;
        letter-spacing: -0.5px;
        background: linear-gradient(90deg, #E50914, #00c6ff, #0072ff, #E50914);
        background-size: 200% auto;
        color: #fff;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        animation: brandShine 3s linear infinite;
    }

    @keyframes brandShine {
        to {
            background-position: 200% center;
        }
    }

    .icon-btn {
        font-size: 1.2rem;
        background: none;
        border: none;
        cursor: pointer;
        transition: transform 0.2s;
    }

    .icon-btn:hover {
        transform: scale(1.1);
    }

    /* Floating Bottom Bar */
    .admin-bottom-nav-wrapper {
        position: fixed;
        bottom: 20px;
        left: 0;
        right: 0;
        display: flex;
        justify-content: center;
        z-index: 1000;
        pointer-events: none;
    }

    .admin-bottom-nav {
        pointer-events: auto;
        background: rgba(15, 15, 15, 0.95);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(229, 9, 20, 0.3);
        border-radius: 50px;
        padding: 10px 30px;
        display: flex;
        align-items: center;
        gap: 30px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
    }

    .nav-item {
        text-decoration: none;
        color: var(--text-sub);
        display: flex;
        flex-direction: column;
        align-items: center;
        font-size: 0.7rem;
        gap: 4px;
        transition: all 0.3s;
        min-width: 50px;
        position: relative;
    }

    .nav-icon {
        font-size: 1.3rem;
        transition: transform 0.3s, color 0.3s;
    }

    .nav-item:hover,
    .nav-item.active {
        color: white;
    }

    .nav-item:hover .nav-icon,
    .nav-item.active .nav-icon {
        color: var(--primary);
        transform: translateY(-3px);
        text-shadow: 0 0 10px rgba(229, 9, 20, 0.6);
    }

    /* Center Button (Chat) */
    .nav-item-center {
        position: relative;
        width: 60px;
    }

    .center-btn {
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        width: 65px;
        height: 65px;
        background: linear-gradient(135deg, #E50914, #99060d);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        color: white;
        box-shadow: 0 5px 20px rgba(229, 9, 20, 0.5);
        border: 5px solid #000;
        transition: transform 0.3s, box-shadow 0.3s;
        text-decoration: none;
    }

    .center-btn:hover {
        transform: translateX(-50%) scale(1.1);
        box-shadow: 0 10px 30px rgba(229, 9, 20, 0.7);
    }

    .badge-counter {
        position: absolute;
        top: -2px;
        right: -2px;
        background: white;
        color: #E50914;
        font-size: 0.75rem;
        font-weight: 800;
        padding: 2px 6px;
        border-radius: 10px;
        border: 2px solid #E50914;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }

        100% {
            transform: scale(1);
        }
    }

    /* Popup Menu Overlay */
    .admin-menu-overlay {
        position: fixed;
        inset: 0;
        z-index: 1100;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .admin-menu-overlay.show {
        pointer-events: auto;
        opacity: 1;
    }

    .admin-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        z-index: 1099;
        opacity: 0;
        visibility: hidden;
        transition: 0.3s;
    }

    .admin-backdrop.show {
        opacity: 1;
        visibility: visible;
    }

    .menu-content {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: #0f0f0f;
        background: rgba(15, 15, 15, 0.98);
        border-top-left-radius: 20px;
        border-top-right-radius: 20px;
        padding: 15px 15px 20px;
        transform: translateY(100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border-top: 1px solid var(--adm-border);
        box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.5);
        max-height: 70vh;
        display: flex;
        flex-direction: column;
    }

    .admin-menu-overlay.show .menu-content {
        transform: translateY(0);
    }

    .menu-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        flex-shrink: 0;
    }

    .menu-header h5 {
        font-size: 0.95rem;
    }

    .btn-close-menu {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .btn-close-menu:hover {
        background: rgba(229, 9, 20, 0.2);
        color: #E50914;
        border-color: #E50914;
    }

    .menu-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        padding-right: 5px;
        flex: 1;
    }

    .menu-grid::-webkit-scrollbar {
        width: 4px;
    }
    .menu-grid::-webkit-scrollbar-track {
        background: rgba(255,255,255,0.05);
        border-radius: 2px;
    }
    .menu-grid::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 2px;
    }

    .menu-card {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        padding: 12px 8px;
        text-align: center;
        text-decoration: none;
        color: var(--text-main);
        transition: all 0.2s;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 70px;
    }

    .menu-card span {
        font-size: 0.7rem;
        line-height: 1.2;
    }

    .menu-card:hover, .menu-card:active {
        background: linear-gradient(135deg, rgba(229, 9, 20, 0.15), rgba(0, 0, 0, 0));
        border-color: rgba(229, 9, 20, 0.4);
        transform: scale(0.98);
    }

    .mc-icon {
        font-size: 1.3rem;
        margin-bottom: 6px;
        color: #E50914;
        text-shadow: 0 0 10px rgba(229, 9, 20, 0.3);
    }

    /* Removed disparate colors in favor of unified theme */
    .c-purple,
    .c-green,
    .c-blue,
    .c-red,
    .c-pink {
        color: #E50914;
    }
    .c-pink { color: #ec4899 !important; }

    .logout-card {
        background: rgba(239, 68, 68, 0.05);
        border-color: rgba(239, 68, 68, 0.2);
        color: #ef4444;
    }

    .logout-card .mc-icon {
        color: #ef4444;
    }

    .logout-card:hover {
        background: rgba(239, 68, 68, 0.15);
        border-color: #ef4444;
    }

    /* Mobile Tweaks */
    @media (max-width: 576px) {
        .admin-bottom-nav {
            width: 95%;
            justify-content: space-between;
            padding: 10px 15px;
            gap: 10px;
        }

        .nav-label {
            font-size: 0.65rem;
        }

        .admin-brand .brand-text {
            font-size: 1.1rem;
        }

        .menu-content {
            max-height: 65vh;
            padding: 12px 10px 15px;
        }

        .menu-header {
            margin-bottom: 10px;
            padding-bottom: 8px;
        }

        .menu-header h5 {
            font-size: 0.85rem;
        }

        .menu-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }

        .menu-card {
            padding: 10px 5px;
            border-radius: 10px;
            min-height: 65px;
        }

        .menu-card span {
            font-size: 0.65rem;
        }

        .mc-icon {
            font-size: 1.1rem;
            margin-bottom: 4px;
        }
    }

    /* Very small screens */
    @media (max-width: 380px) {
        .menu-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .menu-card {
            min-height: 60px;
        }
    }

    /* Server Submenu */
    .server-submenu {
        position: absolute;
        bottom: 80px;
        left: 50%;
        transform: translateX(-50%) scale(0.9);
        background: rgba(15, 15, 15, 0.98);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(229, 9, 20, 0.3);
        border-radius: 16px;
        padding: 10px;
        min-width: 180px;
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s ease;
        box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.5);
        z-index: 1050;
    }

    .server-submenu.show {
        opacity: 1;
        visibility: visible;
        transform: translateX(-50%) scale(1);
    }

    .server-submenu-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 15px;
        color: #ccc;
        text-decoration: none;
        border-radius: 10px;
        transition: all 0.2s;
        font-size: 0.85rem;
    }

    .server-submenu-item:hover, .server-submenu-item.active {
        background: rgba(229, 9, 20, 0.15);
        color: #fff;
    }

    .server-submenu-item.active {
        border-left: 3px solid var(--primary);
    }

    .server-submenu-item i {
        width: 20px;
        text-align: center;
        color: var(--primary);
    }

    /* Arrow indicator */
    .server-submenu::after {
        content: '';
        position: absolute;
        bottom: -8px;
        left: 50%;
        transform: translateX(-50%);
        border-left: 8px solid transparent;
        border-right: 8px solid transparent;
        border-top: 8px solid rgba(229, 9, 20, 0.3);
    }

    /* Global SweetAlert2 Dark Theme */
    .swal2-popup {
        background: rgba(25, 25, 25, 0.95) !important;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        border-radius: 16px !important;
        color: #fff !important;
    }

    .swal2-title,
    .swal2-html-container {
        color: #fff !important;
    }

    .swal2-confirm {
        background: linear-gradient(135deg, #E50914, #99060d) !important;
        box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4) !important;
        border: none !important;
    }

    .swal2-cancel {
        background: #333 !important;
        color: #ccc !important;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const menuTrigger = document.getElementById('adminMenuTrigger');
        const menuOverlay = document.getElementById('adminMenuOverlay');
        const backdrop = document.getElementById('adminBackdrop');
        const closeMenu = document.getElementById('closeAdminMenu');

        function toggleMenu() {
            menuOverlay.classList.toggle('show');
            backdrop.classList.toggle('show');
        }

        if (menuTrigger) {
            menuTrigger.addEventListener('click', (e) => {
                e.preventDefault();
                toggleMenu();
            });
        }

        if (closeMenu) closeMenu.addEventListener('click', toggleMenu);

        if (backdrop) {
            backdrop.addEventListener('click', () => {
                menuOverlay.classList.remove('show');
                backdrop.classList.remove('show');
            });
        }

        // Server Submenu Toggle
        const serverTrigger = document.getElementById('serverMenuTrigger');
        const serverSubmenu = document.getElementById('serverSubmenu');

        if (serverTrigger && serverSubmenu) {
            serverTrigger.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                serverSubmenu.classList.toggle('show');
            });

            // Close when clicking outside
            document.addEventListener('click', (e) => {
                if (!serverTrigger.contains(e.target) && !serverSubmenu.contains(e.target)) {
                    serverSubmenu.classList.remove('show');
                }
            });
        }
    });
</script>