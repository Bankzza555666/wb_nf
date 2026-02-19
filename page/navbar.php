<?php
$allow_register = '1';

if (!isset($conn)) {
    if (file_exists(__DIR__ . '/../controller/config.php')) {
        require_once __DIR__ . '/../controller/config.php';
    } elseif (file_exists(__DIR__ . '/../../controller/config.php')) {
        require_once __DIR__ . '/../../controller/config.php';
    }
}

if (isset($conn) && $conn) {
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'allow_register' LIMIT 1");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $allow_register = $row['setting_value'];
            }
            $stmt->close();
        }
    } catch (Exception $e) {
    }
}
?>
<nav class="landing-nav" id="navbar">
    <div class="container">
        <div class="nav-wrap">
            <!-- Logo -->
            <a class="brand" href="?r=landing">
                <img src="img/logo.png" alt="Logo" style="height: 40px; width: auto; margin-right: 10px;">
                <span class="brand-text">NF~SHOP</span>
            </a>

            <!-- Desktop Menu -->
            <div class="nav-menu">
                <a href="?r=landing#home" class="menu-item">
                    <span class="menu-text">หน้าแรก</span>
                    <span class="menu-indicator"></span>
                </a>
                <a href="?r=landing#features" class="menu-item">
                    <span class="menu-text">ฟีเจอร์</span>
                    <span class="menu-indicator"></span>
                </a>
                <a href="?r=landing#servers" class="menu-item">
                    <span class="menu-text">เซิร์ฟเวอร์</span>
                    <span class="menu-indicator"></span>
                </a>
                <a href="?r=contact" class="menu-item">
                    <span class="menu-text">ติดต่อเรา</span>
                    <span class="menu-indicator"></span>
                </a>
            </div>

            <!-- Desktop Actions -->
            <div class="nav-actions">
                <button class="btn-login" id="btnLogin">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>เข้าสู่ระบบ</span>
                </button>
                <?php if ($allow_register !== '0'): ?>
                    <button class="btn-register" id="btnRegister">
                        <i class="fas fa-user-plus"></i>
                        <span>สมัคร</span>
                    </button>
                <?php endif; ?>
            </div>

            <!-- Mobile Toggle -->
            <button class="menu-toggle" id="menuToggle" aria-label="Toggle Menu">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button>
        </div>
    </div>
</nav>

<!-- Mobile Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-head">
        <div class="sidebar-brand">
            <img src="img/logo.png" alt="Logo" style="height: 35px; width: auto; margin-right: 8px;">
            <span>NF~SHOP</span>
        </div>
        <button class="close-btn" id="closeSidebar" aria-label="Close Menu">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="sidebar-body">
        <div class="menu-section">
            <div class="section-title">เมนูหลัก</div>
            <a href="?r=landing#home" class="menu-link">
                <div class="menu-link-icon">
                    <i class="fas fa-home"></i>
                </div>
                <span>หน้าแรก</span>
            </a>
            <a href="?r=landing#features" class="menu-link">
                <div class="menu-link-icon">
                    <i class="fas fa-star"></i>
                </div>
                <span>ฟีเจอร์</span>
            </a>
            <a href="?r=landing#servers" class="menu-link">
                <div class="menu-link-icon">
                    <i class="fas fa-server"></i>
                </div>
                <span>เซิร์ฟเวอร์</span>
            </a>
            <a href="?r=contact" class="menu-link">
                <div class="menu-link-icon">
                    <i class="fas fa-headset"></i>
                </div>
                <span>ติดต่อเรา</span>
            </a>
        </div>

        <div class="action-section">
            <div class="section-title">บัญชี</div>
            <button class="action-btn login" id="mobileLogin">
                <i class="fas fa-sign-in-alt"></i>
                <span>เข้าสู่ระบบ</span>
            </button>
            <?php if ($allow_register !== '0'): ?>
                <button class="action-btn register" id="mobileRegister">
                    <i class="fas fa-user-plus"></i>
                    <span>สมัครสมาชิก</span>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Overlay -->
<div class="overlay" id="overlay"></div>

<style>
    /* ===== Compact Navbar Styles ===== */
    :root {
        --nav-height: 56px;
        --primary: #E50914;
        --primary-dark: #B20710;
        --primary-glow: rgba(229, 9, 20, 0.4);
        --glass-bg: rgba(0, 0, 0, 0.7);
        --glass-bg-scrolled: rgba(10, 10, 10, 0.95);
        --glass-border: rgba(255, 255, 255, 0.08);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        padding-top: var(--nav-height);
    }

    /* ===== Navbar Container ===== */
    .landing-nav {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: var(--nav-height);
        background: var(--glass-bg);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border-bottom: 1px solid var(--glass-border);
        z-index: 1000;
        transition: all 0.3s ease;
    }

    .landing-nav.scrolled {
        background: var(--glass-bg-scrolled);
        border-bottom-color: var(--primary-glow);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
    }

    .landing-nav.nav-hidden {
        transform: translateY(calc(-1 * var(--nav-height)));
    }

    .nav-wrap {
        display: flex;
        align-items: center;
        justify-content: space-between;
        height: var(--nav-height);
        padding: 0;
    }

    /* ===== Brand/Logo - Compact ===== */
    .brand {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        transition: transform 0.2s ease;
    }

    .brand:hover {
        transform: scale(1.02);
    }

    .brand-icon {
        width: 34px;
        height: 34px;
        background: linear-gradient(135deg, rgba(229, 9, 20, 0.15), rgba(229, 9, 20, 0.08));
        border: 1.5px solid rgba(229, 9, 20, 0.35);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        font-size: 1rem;
        transition: all 0.2s ease;
        box-shadow: 0 0 12px rgba(229, 9, 20, 0.12);
    }

    .brand:hover .brand-icon {
        background: linear-gradient(135deg, rgba(229, 9, 20, 0.25), rgba(229, 9, 20, 0.15));
        border-color: rgba(229, 9, 20, 0.5);
        box-shadow: 0 0 20px rgba(229, 9, 20, 0.25);
    }

    .brand-text {
        font-size: 1.25rem;
        font-weight: 800;
        letter-spacing: -0.5px;
        background: linear-gradient(90deg, #E50914, #ff4757, #E50914);
        background-size: 200% auto;
        color: #fff;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        animation: brandShine 4s linear infinite;
    }

    @keyframes brandShine {
        0% { background-position: 0% center; }
        100% { background-position: 200% center; }
    }

    /* ===== Desktop Menu - Compact ===== */
    .nav-menu {
        display: flex;
        gap: 1.75rem;
        align-items: center;
    }

    .menu-item {
        position: relative;
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        font-weight: 500;
        font-size: 0.9rem;
        padding: 0.35rem 0;
        transition: color 0.2s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .menu-item:hover {
        color: white;
    }

    .menu-indicator {
        position: absolute;
        bottom: -2px;
        width: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent, var(--primary), transparent);
        border-radius: 1px;
        transition: width 0.25s ease;
    }

    .menu-item:hover .menu-indicator {
        width: 100%;
    }

    /* ===== Desktop Actions - Compact ===== */
    .nav-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .btn-login,
    .btn-register {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
        overflow: hidden;
    }

    .btn-login {
        background: transparent;
        color: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(255, 255, 255, 0.15);
    }

    .btn-login:hover {
        background: rgba(255, 255, 255, 0.06);
        border-color: rgba(255, 255, 255, 0.3);
    }

    .btn-register {
        background: linear-gradient(135deg, #E50914, #B20710);
        color: white;
        box-shadow: 0 3px 12px rgba(229, 9, 20, 0.25);
    }

    .btn-register:hover {
        box-shadow: 0 4px 18px rgba(229, 9, 20, 0.4);
        transform: translateY(-1px);
    }

    /* ===== Mobile Menu Toggle - Compact ===== */
    .menu-toggle {
        display: none;
        width: 38px;
        height: 38px;
        background: rgba(229, 9, 20, 0.08);
        border: 1px solid rgba(229, 9, 20, 0.25);
        border-radius: 8px;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .menu-toggle:hover {
        background: rgba(229, 9, 20, 0.15);
        border-color: rgba(229, 9, 20, 0.4);
    }

    .hamburger-line {
        width: 18px;
        height: 2px;
        background: white;
        border-radius: 2px;
        transition: all 0.25s ease;
    }

    .menu-toggle.active .hamburger-line:nth-child(1) {
        transform: rotate(45deg) translate(4px, 4px);
    }

    .menu-toggle.active .hamburger-line:nth-child(2) {
        opacity: 0;
    }

    .menu-toggle.active .hamburger-line:nth-child(3) {
        transform: rotate(-45deg) translate(4px, -4px);
    }

    /* ===== Overlay ===== */
    .overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(8px);
        z-index: 1001;
        opacity: 0;
        visibility: hidden;
        transition: all 0.4s ease;
    }

    .overlay.show {
        opacity: 1;
        visibility: visible;
    }

    /* ===== Mobile Sidebar - Compact ===== */
    .sidebar {
        position: fixed;
        top: 0;
        right: -100%;
        width: 300px;
        height: 100vh;
        background: linear-gradient(180deg, rgba(15, 15, 15, 0.98), rgba(10, 10, 10, 0.98));
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border-left: 1px solid rgba(229, 9, 20, 0.15);
        z-index: 1002;
        transition: right 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        overflow-y: auto;
        box-shadow: -8px 0 30px rgba(0, 0, 0, 0.4);
    }

    .sidebar.show {
        right: 0;
    }

    .sidebar-head {
        padding: 1.25rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        font-size: 1.2rem;
        font-weight: 800;
        color: white;
        letter-spacing: -0.5px;
    }

    .sidebar-brand-icon {
        width: 34px;
        height: 34px;
        background: linear-gradient(135deg, rgba(229, 9, 20, 0.15), rgba(229, 9, 20, 0.08));
        border: 1.5px solid rgba(229, 9, 20, 0.35);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        font-size: 1rem;
    }

    .close-btn {
        width: 32px;
        height: 32px;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 6px;
        color: #fff;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .close-btn:hover {
        background: rgba(229, 9, 20, 0.15);
        color: var(--primary);
        border-color: var(--primary);
        transform: rotate(90deg);
    }

    .sidebar-body {
        padding: 1.25rem;
    }

    .menu-section {
        margin-bottom: 1.25rem;
    }

    .section-title {
        color: rgba(148, 163, 184, 0.7);
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        font-weight: 700;
        margin-bottom: 0.6rem;
        padding-left: 0.5rem;
    }

    .menu-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 0.875rem;
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        border-radius: 10px;
        margin-bottom: 0.4rem;
        transition: all 0.25s ease;
        font-weight: 500;
        font-size: 0.9rem;
        border: 1px solid transparent;
    }

    .menu-link:hover {
        background: rgba(229, 9, 20, 0.08);
        color: white;
        border-color: rgba(229, 9, 20, 0.15);
        transform: translateX(4px);
    }

    .menu-link-icon {
        width: 32px;
        height: 32px;
        background: rgba(229, 9, 20, 0.08);
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        font-size: 0.9rem;
        transition: all 0.2s ease;
    }

    .menu-link:hover .menu-link-icon {
        background: rgba(229, 9, 20, 0.15);
        transform: scale(1.05);
    }

    .action-section {
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
        padding-top: 0.875rem;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .action-btn {
        width: 100%;
        padding: 0.75rem 1rem;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .action-btn.login {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.12);
        color: white;
    }

    .action-btn.login:hover {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(255, 255, 255, 0.25);
        transform: translateY(-1px);
    }

    .action-btn.register {
        background: linear-gradient(135deg, #E50914, #B20710);
        color: white;
        box-shadow: 0 3px 12px rgba(229, 9, 20, 0.25);
    }

    .action-btn.register:hover {
        box-shadow: 0 4px 18px rgba(229, 9, 20, 0.4);
        transform: translateY(-1px);
    }

    /* ===== Animations ===== */
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .sidebar.show .menu-link {
        animation: slideInRight 0.4s ease backwards;
    }

    .sidebar.show .menu-link:nth-child(1) { animation-delay: 0.1s; }
    .sidebar.show .menu-link:nth-child(2) { animation-delay: 0.15s; }
    .sidebar.show .menu-link:nth-child(3) { animation-delay: 0.2s; }
    .sidebar.show .menu-link:nth-child(4) { animation-delay: 0.25s; }

    /* ===== Responsive - Compact ===== */
    @media (max-width: 991px) {
        .nav-menu {
            gap: 1.25rem;
        }

        .menu-item {
            font-size: 0.85rem;
        }

        .btn-login span,
        .btn-register span {
            display: none;
        }

        .btn-login,
        .btn-register {
            padding: 0.5rem;
            width: 36px;
            height: 36px;
            justify-content: center;
        }
    }

    @media (max-width: 768px) {
        .nav-menu {
            display: none;
        }

        .nav-actions {
            display: none;
        }

        .menu-toggle {
            display: flex;
        }
    }

    @media (max-width: 480px) {
        :root {
            --nav-height: 52px;
        }

        .brand-text {
            font-size: 1.1rem;
        }

        .brand-icon {
            width: 30px;
            height: 30px;
            font-size: 0.9rem;
        }

        .sidebar {
            width: 100%;
        }
    }

    /* ===== Scroll Progress Bar ===== */
    .scroll-progress {
        position: absolute;
        bottom: 0;
        left: 0;
        height: 2px;
        background: linear-gradient(90deg, var(--primary), #ff4757);
        width: 0%;
        transition: width 0.1s ease;
    }

    .landing-nav.scrolled .scroll-progress {
        opacity: 1;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        window.allowRegister = window.allowRegister ?? <?php echo $allow_register === '0' ? 'false' : 'true'; ?>;

        const navbar = document.getElementById('navbar');
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const closeSidebar = document.getElementById('closeSidebar');
        const menuLinks = document.querySelectorAll('.menu-link');

        // Scroll Effect
        let lastScroll = 0;
        let isTicking = false;
        const hideOffset = 120;

        function handleScroll() {
            const currentScroll = window.pageYOffset;

            if (currentScroll > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }

            if (currentScroll > hideOffset && currentScroll > lastScroll) {
                navbar.classList.add('nav-hidden');
            } else {
                navbar.classList.remove('nav-hidden');
            }

            lastScroll = currentScroll;
            isTicking = false;
        }

        window.addEventListener('scroll', function() {
            if (!isTicking) {
                window.requestAnimationFrame(handleScroll);
                isTicking = true;
            }
        }, { passive: true });

        // Menu Functions
        function openMenu() {
            sidebar.classList.add('show');
            overlay.classList.add('show');
            menuToggle.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeMenu() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            menuToggle.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Event Listeners
        if (menuToggle) menuToggle.addEventListener('click', function() {
            if (sidebar.classList.contains('show')) {
                closeMenu();
            } else {
                openMenu();
            }
        });

        if (closeSidebar) closeSidebar.addEventListener('click', closeMenu);
        if (overlay) overlay.addEventListener('click', closeMenu);

        menuLinks.forEach(link => {
            link.addEventListener('click', closeMenu);
        });

        // Close menu on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('show')) {
                closeMenu();
            }
        });

        // Button Handlers
        const btnLogin = document.getElementById('btnLogin');
        const btnRegister = document.getElementById('btnRegister');
        const mobileLogin = document.getElementById('mobileLogin');
        const mobileRegister = document.getElementById('mobileRegister');

        const isLanding = window.location.search.includes('r=landing') || window.location.pathname === '/';

        function handleLogin() {
            if (isLanding && document.getElementById('ctaLogin')) {
                document.getElementById('ctaLogin').click();
            } else {
                window.location.href = '?r=landing#login';
            }
        }

        function handleRegister() {
            if (window.allowRegister === false) {
                if (window.Alert && Alert.info) {
                    Alert.info('ปิดรับสมัครชั่วคราว', 'ระบบยังไม่เปิดให้สมัครสมาชิก');
                }
                return;
            }
            if (isLanding && document.getElementById('ctaRegister')) {
                document.getElementById('ctaRegister').click();
            } else {
                window.location.href = '?r=landing#registerCard';
            }
        }

        if (btnLogin) btnLogin.addEventListener('click', handleLogin);
        if (btnRegister) btnRegister.addEventListener('click', handleRegister);

        if (mobileLogin) {
            mobileLogin.addEventListener('click', function() {
                closeMenu();
                handleLogin();
            });
        }

        if (mobileRegister) {
            mobileRegister.addEventListener('click', function() {
                closeMenu();
                handleRegister();
            });
        }
    });
</script>
