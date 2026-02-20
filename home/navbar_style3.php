<?php
// home/navbar.php
// V5.9 Final (With Chat Toggle Logic)

$user_id = $_SESSION['user_id'];

// ✅ ดึงข้อมูลผู้ใช้
if (!isset($user) || empty($user)) {
    $stmt = $conn->prepare("SELECT username, email, credit FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    // ถ้าไม่พบข้อมูลผู้ใช้ ให้ออกจากระบบ
    if (!$user) {
        header('Location: controller/logout.php');
        exit;
    }
}

// ✅ ตั้งค่า default values
$user['username'] = $user['username'] ?? 'Guest';
$user['email'] = $user['email'] ?? 'no-email@example.com';
$user['credit'] = $user['credit'] ?? 0;

$unread_count = 0;
$notifications = [];

// นับแจ้งเตือนระบบ (กระดิ่ง)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$unread_count = $row['count'];
$stmt->close();

// ดึงแจ้งเตือนระบบ
$stmt = $conn->prepare("SELECT n.*, t.transaction_ref, t.status as transaction_status, t.admin_note 
                        FROM notifications n 
                        LEFT JOIN topup_transactions t ON n.transaction_id = t.id 
                        WHERE (n.user_id = ? OR n.user_id IS NULL) 
                        ORDER BY (n.user_id IS NULL) DESC, n.created_at DESC LIMIT 20");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

// ✅ ตรวจสอบหน้าปัจจุบัน
$current_page = $_GET['p'] ?? '';
?>

<div class="nebula-bg"></div>
<nav class="main-nav">
    <div class="container">
        <div class="nav-wrap">
            <a class="brand" href="?p=home">
                <img src="img/logo.png" alt="Logo" style="height: 45px; width: auto; margin-right: 10px;">
                <span class="brand-text">NF~SHOP</span>
            </a>

            <div class="nav-menu desktop-only">
                <a href="?p=home"
                    class="nav-menu-link <?php echo ($current_page == 'home' || $current_page == '') ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>หน้าหลัก</span>
                </a>

                <div class="nav-menu-item nav-dropdown-menu" id="productsMenu">
                    <a href="#"
                        class="nav-menu-link dropdown-toggle <?php echo (strpos($current_page, 'products_') === 0 || $current_page == 'rent_vpn') ? 'active' : ''; ?>"
                        id="productsToggle">
                        <i class="fas fa-store"></i>
                        <span>สินค้า / บริการ</span>
                        <i class="fas fa-chevron-down dropdown-arrow-nav"></i>
                    </a>
                    <div class="nav-submenu" id="productsSubmenu">
                        <div class="submenu-header">หมวดหมู่สินค้า</div>
                        <a href="?p=rent_vpn" class="submenu-item">
                            <i class="fas fa-server"></i>
                            <span>เช่า VPN/V2ray</span>
                        </a>
                        <a href="?p=rent_ssh" class="submenu-item">
                            <i class="fas fa-terminal"></i>
                            <span>เช่า Netmod/Npv Tunnel</span>
                        </a>
                        <a href="?p=products_category&id=3" class="submenu-item">
                            <i class="fas fa-film"></i>
                            <span>แพ็คเกจสตรีมมิ่ง</span>
                        </a>
                        <div class="submenu-divider"></div>
                        <a href="?p=products_all" class="submenu-item all-items">
                            <i class="fas fa-boxes"></i>
                            <span>ดูสินค้าทั้งหมด</span>
                        </a>
                    </div>
                </div>

                <div class="nav-menu-item nav-dropdown-menu" id="rentalsMenu">
                    <a href="#"
                        class="nav-menu-link dropdown-toggle <?php echo ($current_page == 'my_vpn' || $current_page == 'my_ssh' || $current_page == 'netmod_config') ? 'active' : ''; ?>"
                        id="rentalsToggle">
                        <i class="fas fa-tasks"></i>
                        <span>รายการที่เช่า</span>
                        <i class="fas fa-chevron-down dropdown-arrow-nav"></i>
                    </a>
                    <div class="nav-submenu" id="rentalsSubmenu">
                        <div class="submenu-header">รายการที่เช่าของฉัน</div>
                        <a href="?p=my_vpn" class="submenu-item">
                            <i class="fas fa-server"></i>
                            <span>VPN/V2ray ของฉัน</span>
                        </a>
                        <a href="?p=my_ssh" class="submenu-item">
                            <i class="fas fa-terminal"></i>
                            <span>SSH/NPV ของฉัน</span>
                        </a>
                    </div>
                </div>

                <!-- ✅ เมนูชวนเพื่อน -->
                <div class="nav-menu-item">
                    <a href="?p=referral"
                        class="nav-menu-link <?php echo ($current_page == 'referral') ? 'active' : ''; ?>">
                        <i class="fas fa-gift"></i>
                        <span>ชวนเพื่อน</span>
                    </a>
                </div>
            </div>

            <div class="nav-right">
                <button class="notification-toggle" id="notificationToggle" title="หน้าต่างการแจ้งเตือน">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count > 99 ? '99+' : $unread_count; ?></span>
                    <?php endif; ?>
                </button>

                <div class="user-profile desktop-only" id="userProfile">
                    <div class="user-profile-trigger">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                        </div>
                        <div class="user-info-compact">
                            <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                            <div class="user-balance">฿<?php echo number_format($user['credit'], 2); ?></div>
                        </div>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </div>

                    <div class="user-dropdown" id="userDropdown" style="visibility: hidden; opacity: 0;">
                        <div class="dropdown-header">
                            <div class="dropdown-avatar">
                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                            </div>
                            <div class="dropdown-user-info">
                                <div class="dropdown-username"><?php echo htmlspecialchars($user['username']); ?></div>
                                <div class="dropdown-email"><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                        </div>

                        <div class="dropdown-balance">
                            <div class="balance-label">
                                <i class="fas fa-wallet"></i>
                                ยอดเงินคงเหลือ
                            </div>
                            <div class="balance-amount">฿<?php echo number_format($user['credit'], 2); ?></div>
                        </div>

                        <div class="dropdown-divider"></div>

                        <div class="dropdown-menu-list">
                            <a href="?p=home" class="dropdown-menu-item">
                                <i class="fas fa-home"></i>
                                <span>หน้าหลัก </span>
                            </a>
                            <a href="?p=rent_vpn" class="dropdown-menu-item">
                                <i class="fas fa-store"></i>
                                <span>เช่าสินค้า</span>
                            </a>
                            <div class="dropdown-menu-group">
                                <a href="#" class="dropdown-menu-item has-submenu" id="userRentalsToggle">
                                    <i class="fas fa-tasks"></i>
                                    <span>รายการที่เช่า</span>
                                    <i class="fas fa-chevron-right submenu-arrow"></i>
                                </a>
                                <div class="dropdown-submenu" id="userRentalsSubmenu">
                                    <a href="?p=my_vpn" class="dropdown-menu-item">
                                        <i class="fas fa-server"></i>
                                        <span>VPN/V2ray ของฉัน</span>
                                    </a>
                                    <a href="?p=my_ssh" class="dropdown-menu-item">
                                        <i class="fas fa-terminal"></i>
                                        <span>SSH/NPV ของฉัน</span>
                                    </a>
                                </div>
                            </div>
                            <a href="?p=topup" class="dropdown-menu-item">
                                <i class="fas fa-credit-card"></i>
                                <span>เติมเงิน</span>
                            </a>
                            <a href="?p=topup_history" class="dropdown-menu-item">
                                <i class="fas fa-history"></i>
                                <span>ประวัติการเติมเงิน</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="?p=tutorials" class="dropdown-menu-item">
                                <i class="fas fa-book-open"></i>
                                <span>วิธีใช้งาน</span>
                            </a>
                            <a href="?p=faq" class="dropdown-menu-item">
                                <i class="fas fa-question-circle"></i>
                                <span>คำถามที่พบบ่อย</span>
                            </a>
                            <a href="?p=userdetail" class="dropdown-menu-item">
                                <i class="fas fa-cog"></i>
                                <span>ตั้งค่าบัญชี</span>
                            </a>
                        </div>

                        <div class="dropdown-divider"></div>

                        <a href="?p=logout" class="dropdown-menu-item logout-item">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>ออกจากระบบ</span>
                        </a>
                    </div>
                </div>

                <button class="menu-toggle mobile-only" id="menuToggle">
                    <span class="menu-text">เมนู</span>
                </button>
            </div>
        </div>
    </div>
</nav>

<div class="notification-panel" id="notificationPanel" style="visibility: hidden; opacity: 0;">
    <div class="notification-header">
        <h6>
            <i class="fas fa-bell"></i>
            แจ้งเตือนระบบ
            <?php if ($unread_count > 0): ?>
                <span class="header-badge"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </h6>
        <div class="notification-actions">
            <?php if ($unread_count > 0): ?>
                <button class="mark-all-read" id="markAllRead" title="ทำเครื่องหมายอ่านทั้งหมด">
                    <i class="fas fa-check-double"></i>
                </button>
            <?php endif; ?>
            <button class="clear-all-notif" id="clearAllNotif" title="ล้างการแจ้งเตือนทั้งหมด">
                <i class="fas fa-trash-alt"></i>
            </button>
        </div>
    </div>

    <div class="notification-body">
        <?php if (empty($notifications)): ?>
            <div class="notification-empty">
                <i class="fas fa-bell-slash"></i>
                <p>ไม่มีการแจ้งเตือน</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <div class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>"
                    data-id="<?php echo $notif['id']; ?>" data-title="<?php echo htmlspecialchars($notif['title']); ?>"
                    data-message="<?php echo htmlspecialchars($notif['message']); ?>" data-type="<?php echo $notif['type']; ?>"
                    data-user-id="<?php echo $notif['user_id']; ?>"
                    data-transaction-id="<?php echo $notif['transaction_id'] ?? ''; ?>"
                    data-transaction-ref="<?php echo htmlspecialchars($notif['transaction_ref'] ?? ''); ?>"
                    data-transaction-status="<?php echo $notif['transaction_status'] ?? ''; ?>">
                    <div class="notif-icon <?php echo $notif['type']; ?>">
                        <?php
                        $icon = 'fa-info-circle';
                        if ($notif['type'] == 'success')
                            $icon = 'fa-check-circle';
                        if ($notif['type'] == 'warning')
                            $icon = 'fa-exclamation-triangle';
                        if ($notif['type'] == 'error')
                            $icon = 'fa-times-circle';
                        if ($notif['type'] == 'announcement')
                            $icon = 'fa-bullhorn';
                        ?>
                        <i class="fas <?php echo $icon; ?>"></i>
                    </div>
                    <div class="notif-content">
                        <div class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                        <div class="notif-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                        <div class="notif-time">
                            <i class="far fa-clock"></i>
                            <?php
                            $time_diff = time() - strtotime($notif['created_at']);
                            if ($time_diff < 60)
                                echo 'เมื่อสักครู่';
                            elseif ($time_diff < 3600)
                                echo floor($time_diff / 60) . ' นาทีที่แล้ว';
                            elseif ($time_diff < 86400)
                                echo floor($time_diff / 3600) . ' ชั่วโมงที่แล้ว';
                            else
                                echo floor($time_diff / 86400) . ' วันที่แล้ว';
                            ?>
                        </div>
                    </div>
                    <div class="notif-actions">
                        <?php if (!$notif['is_read']): ?>
                            <div class="unread-indicator"></div>
                        <?php endif; ?>
                        <?php if ($notif['user_id'] !== null): ?>
                            <button class="delete-notif" data-id="<?php echo $notif['id']; ?>" title="ลบ">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="notification-modal" id="notificationModal" style="visibility: hidden; opacity: 0;">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-icon" id="modalIcon"><i class="fas fa-info-circle"></i></div>
            <button class="modal-close" id="modalClose"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <h5 id="modalTitle">หัวข้อ</h5>
            <p id="modalMessage">ข้อความ</p>
        </div>
    </div>
</div>


<div class="overlay" id="overlay"></div>

<?php if (isset($_SESSION['user_id']) && $current_page !== 'contact'): ?>
    <a href="?p=contact" class="chat-toggle-btn fade-in" title="ติดต่อแอดมิน / Live Chat">
        <i class="fas fa-comment-dots"></i>
        <span class="chat-badge" id="chatBadge" style="display: none;">0</span>
    </a>
<?php endif; ?>

<style>
    /* CSS เดิมทั้งหมด */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        background: var(--bg-body, #000000);
        min-height: 100vh;
        padding-top: 80px;
    }

    .nebula-bg {
        position: fixed;
        inset: 0;
        z-index: -1;
        background: radial-gradient(circle at 10% 20%, rgba(229, 9, 20, 0.15) 0%, transparent 40%), radial-gradient(circle at 90% 80%, rgba(255, 0, 0, 0.15) 0%, transparent 40%);
        pointer-events: none;
    }

    .main-nav {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        background: #0f1115;
        border-bottom: 2px solid rgba(255, 255, 255, 0.05);
        z-index: 1000;
        transition: all 0.3s ease;
    }

    .main-nav.scrolled {
        background: rgba(15, 17, 21, 0.95);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-bottom: 2px solid #E50914;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    }

    .nav-wrap {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 0;
        gap: 1rem;
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        text-decoration: none;
        transition: transform 0.3s ease;
    }

    .brand:hover {
        transform: scale(1.02);
    }

    .brand-icon {
        width: 48px;
        height: 48px;
        background: rgba(229, 9, 20, 0.1);
        border: 1px solid rgba(229, 9, 20, 0.3);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #E50914;
        font-size: 1.4rem;
        box-shadow: 0 0 15px rgba(229, 9, 20, 0.1);
        transition: all 0.3s ease;
    }

    .brand:hover .brand-icon {
        box-shadow: 0 0 25px rgba(229, 9, 20, 0.3);
        border-color: #E50914;
    }

    .brand-text {
        font-size: 1.5rem;
        font-weight: 800;
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

    .nav-menu {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
    }

    .nav-menu-link {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 1.25rem 1rem;
        /* Taller padding for bottom border effect */
        color: rgba(255, 255, 255, 0.6);
        text-decoration: none;
        font-weight: 600;
        font-size: 0.95rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
        position: relative;
    }

    .nav-menu-link i {
        font-size: 1rem;
        transition: transform 0.3s ease;
        color: rgba(255, 255, 255, 0.4);
    }

    .nav-menu-link:hover {
        color: white;
        background: transparent;
    }

    .nav-menu-link:hover i,
    .nav-menu-link.active i {
        color: #E50914;
    }

    .nav-menu-link::after {
        content: '';
        position: absolute;
        width: 0;
        height: 3px;
        bottom: 0px;
        left: 50%;
        transform: translateX(-50%);
        background: #E50914;
        transition: width 0.3s ease;
        border-radius: 3px 3px 0 0;
    }

    .nav-menu-link:hover::after,
    .nav-menu-link.active::after {
        width: 80%;
    }

    .nav-menu-link.active {
        color: white;
        background: transparent;
        border: none;
        box-shadow: none;
        font-weight: 700;
    }

    .nav-right {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .notification-toggle {
        width: 50px;
        height: 50px;
        background: rgba(229, 9, 20, 0.15);
        border: 1px solid rgba(229, 9, 20, 0.3);
        border-radius: 12px;
        color: #E50914;
        font-size: 1.25rem;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
    }

    .notification-toggle:hover {
        background: rgba(229, 9, 20, 0.25);
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(229, 9, 20, 0.3);
    }

    .notification-toggle.active {
        background: rgba(229, 9, 20, 0.3);
        color: #ff6666;
        box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4);
    }

    .notification-badge {
        position: absolute;
        top: -6px;
        right: -6px;
        min-width: 18px;
        height: 18px;
        background: #ffffff;
        color: #E50914;
        font-size: 0.65rem;
        font-weight: 700;
        border-radius: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 5px;
        border: 2px solid #000000;
        box-shadow: 0 2px 8px rgba(229, 9, 20, 0.6);
        animation: pulse-badge 2s infinite;
    }

    @keyframes pulse-badge {

        0%,
        100% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.1);
        }
    }

    .user-profile {
        position: relative;
    }

    .user-profile-trigger {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem 1rem;
        background: rgba(229, 9, 20, 0.1);
        border: 1px solid rgba(229, 9, 20, 0.3);
        border-radius: 50px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .user-profile-trigger:hover {
        background: rgba(229, 9, 20, 0.2);
        border-color: rgba(229, 9, 20, 0.5);
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(229, 9, 20, 0.3);
    }

    .user-avatar {
        width: 45px;
        height: 45px;
        background: linear-gradient(135deg, #E50914, #B20710);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.25rem;
        font-weight: 800;
        box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4);
    }

    .user-info-compact {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
    }

    .user-name {
        color: white;
        font-weight: 700;
        font-size: 0.95rem;
        line-height: 1;
    }

    .user-balance {
        color: #fbbf24;
        font-weight: 700;
        font-size: 0.9rem;
        line-height: 1;
    }

    .dropdown-arrow {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.85rem;
        transition: transform 0.3s ease;
        margin-left: 0.25rem;
    }

    .user-profile.active .dropdown-arrow {
        transform: rotate(180deg);
    }

    /* --- User Dropdown --- */
    .user-dropdown {
        position: absolute;
        top: calc(100% + 1rem);
        right: 0;
        width: 320px;
        background: rgba(10, 10, 10, 0.95);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(229, 9, 20, 0.3);
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s ease;
        z-index: 1001;
    }

    .user-profile.active .user-dropdown {
        opacity: 1 !important;
        visibility: visible !important;
        transform: translateY(0);
    }

    .dropdown-header {
        padding: 1.5rem;
        display: flex;
        gap: 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .dropdown-avatar {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #E50914, #99060d);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.8rem;
        font-weight: 800;
        box-shadow: 0 4px 15px rgba(229, 9, 20, 0.3);
        flex-shrink: 0;
    }

    .dropdown-user-info {
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-width: 0;
    }

    .dropdown-username {
        color: white;
        font-weight: 700;
        font-size: 1.1rem;
    }

    .dropdown-email {
        color: #94a3b8;
        font-size: 0.85rem;
    }

    .dropdown-balance {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        margin: 1rem;
        background: rgba(229, 9, 20, 0.1);
        border: 1px solid rgba(229, 9, 20, 0.2);
        border-radius: 12px;
    }

    .balance-label {
        color: #ddd;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .dropdown-balance .balance-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0;
    }

    .balance-card .balance-label {
        display: block;
        text-align: center;
        font-size: 0.8rem;
    }

    .balance-amount {
        color: #fff;
        font-size: 1.25rem;
        font-weight: 700;
        text-shadow: 0 0 10px rgba(229, 9, 20, 0.5);
    }

    .balance-card {
        background: linear-gradient(135deg, rgba(229, 9, 20, 0.15), rgba(178, 7, 16, 0.15));
        border: 1px solid rgba(229, 9, 20, 0.3);
        border-radius: 12px;
        padding: 1rem;
        text-align: center;
        margin-bottom: 1.25rem;
    }

    .balance-value {
        color: white;
        font-size: 1.5rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .balance-card .balance-value i {
        color: #fbbf24;
        font-size: 1.25rem;
    }

    .dropdown-divider {
        height: 1px;
        background: rgba(255, 255, 255, 0.05);
        margin: 0.5rem 0;
    }

    .dropdown-menu-list {
        padding: 0.5rem;
    }

    .dropdown-menu-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.8rem 1rem;
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        border-radius: 10px;
        transition: all 0.2s;
        font-weight: 500;
    }

    .dropdown-menu-item i {
        width: 24px;
        font-size: 1.1rem;
        text-align: center;
        color: #E50914;
    }

    .dropdown-menu-item:hover {
        background: rgba(255, 255, 255, 0.05);
        color: white;
        padding-left: 1.25rem;
    }

    .dropdown-menu-item.logout-item {
        color: #ef4444;
        margin-top: 0.5rem;
    }

    .dropdown-menu-item.logout-item:hover {
        background: rgba(239, 68, 68, 0.1);
    }

    /* User Dropdown Submenu Styles */
    .dropdown-menu-group {
        position: relative;
    }

    .dropdown-menu-item.has-submenu {
        justify-content: space-between;
    }

    .dropdown-menu-item.has-submenu span {
        flex: 1;
    }

    .submenu-arrow {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.5);
        transition: transform 0.3s ease;
    }

    .dropdown-submenu {
        display: none;
        padding-left: 1rem;
        margin-top: 0.25rem;
        border-left: 2px solid rgba(229, 9, 20, 0.3);
        margin-left: 1.5rem;
    }

    .dropdown-submenu.show {
        display: block;
    }

    .dropdown-submenu .dropdown-menu-item {
        padding: 0.6rem 1rem;
        font-size: 0.9rem;
    }

    .menu-toggle {
        width: 70px;
        height: 50px;
        background: linear-gradient(135deg, rgba(229, 9, 20, 0.25), rgba(178, 7, 16, 0.25));
        border: 2px solid transparent;
        border-radius: 14px;
        cursor: pointer;
        transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 15px rgba(229, 9, 20, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.1);
        padding: 0 0.75rem;
    }

    .menu-toggle::before {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 14px;
        padding: 2px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6, #ec4899, #6366f1);
        background-size: 300% 300%;
        -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        -webkit-mask-composite: xor;
        mask-composite: exclude;
        animation: gradientRotate 3s ease infinite;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    @keyframes gradientRotate {

        0%,
        100% {
            background-position: 0% 50%;
        }

        50% {
            background-position: 100% 50%;
        }
    }

    .menu-toggle:hover {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.4), rgba(139, 92, 246, 0.4));
        transform: translateY(-3px) scale(1.05);
        box-shadow: 0 8px 25px rgba(99, 102, 241, 0.5), 0 0 30px rgba(139, 92, 246, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.2);
    }

    .menu-toggle:hover::before {
        opacity: 1;
    }

    .menu-text {
        font-size: 0.95rem;
        font-weight: 700;
        background: linear-gradient(135deg, #ffffff, #e0e7ff, #c7d2fe);
        background-size: 200% 200%;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        transition: all 0.3s ease;
        animation: textShimmer 3s ease-in-out infinite;
    }

    @keyframes textShimmer {

        0%,
        100% {
            background-position: 0% 50%;
        }

        50% {
            background-position: 100% 50%;
        }
    }

    .desktop-only {
        display: flex;
    }

    .mobile-only {
        display: none;
    }

    @media (min-width: 992px) {
        .menu-toggle.mobile-only {
            display: none !important;
        }
    }

    .notification-panel {
        position: fixed;
        top: 65px;
        right: -100%;
        width: 420px;
        height: calc(100vh - 65px);
        background: rgba(10, 10, 10, 0.95);
        backdrop-filter: blur(20px);
        border-left: 1px solid rgba(229, 9, 20, 0.3);
        z-index: 999;
        transition: all 0.3s ease;
        overflow: hidden;
        visibility: hidden;
        pointer-events: none;
        box-shadow: -5px 0 20px rgba(0, 0, 0, 0.3);
        display: flex;
        flex-direction: column;
    }

    .notification-panel.show {
        right: 0;
        visibility: visible !important;
        opacity: 1 !important;
        pointer-events: auto;
    }

    @media (min-width: 992px) {
        .notification-panel {
            top: 90px;
            width: 450px;
            height: auto;
            max-height: 600px;
            border-radius: 16px;
            border: 1px solid rgba(229, 9, 20, 0.3);
            right: auto;
            transform: translateX(100%);
            opacity: 0;
        }

        .notification-panel.show {
            right: 2rem;
            transform: translateX(0);
            opacity: 1 !important;
            visibility: visible !important;
            pointer-events: auto;
        }
    }

    .notification-header {
        padding: 1.25rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .notification-header h6 {
        color: white;
        font-size: 1.1rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0;
    }

    .header-badge {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        font-size: 0.8rem;
        font-weight: 700;
        padding: 0.2rem 0.6rem;
        border-radius: 50px;
        min-width: 24px;
        text-align: center;
    }

    .mark-all-read {
        width: 35px;
        height: 35px;
        background: rgba(99, 102, 241, 0.15);
        border: 1px solid rgba(99, 102, 241, 0.3);
        border-radius: 8px;
        color: #6366f1;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .mark-all-read:hover {
        background: rgba(99, 102, 241, 0.25);
        transform: scale(1.1);
    }

    .notification-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .clear-all-notif {
        width: 35px;
        height: 35px;
        background: rgba(239, 68, 68, 0.15);
        border: 1px solid rgba(239, 68, 68, 0.3);
        border-radius: 8px;
        color: #ef4444;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .clear-all-notif:hover {
        background: rgba(239, 68, 68, 0.25);
        transform: scale(1.1);
    }

    .notification-body {
        flex: 1;
        overflow-y: auto;
        padding: 0.5rem;
    }

    .notification-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        padding: 3rem 2rem;
        color: #94a3b8;
    }

    .notification-item {
        display: flex;
        gap: 1rem;
        padding: 1rem;
        margin-bottom: 0.5rem;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
    }

    .notification-item:hover {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(229, 9, 20, 0.3);
        transform: translateX(-3px);
    }

    .notification-item.unread {
        background: rgba(99, 102, 241, 0.1);
        border-color: rgba(99, 102, 241, 0.3);
    }

    .notif-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .notif-icon.info {
        background: rgba(59, 130, 246, 0.2);
        color: #3b82f6;
    }

    .notif-icon.success {
        background: rgba(16, 185, 129, 0.2);
        color: #10b981;
    }

    .notif-icon.warning {
        background: rgba(245, 158, 11, 0.2);
        color: #f59e0b;
    }

    .notif-icon.error {
        background: rgba(239, 68, 68, 0.2);
        color: #ef4444;
    }

    .notif-icon.announcement {
        background: rgba(139, 92, 246, 0.2);
        color: #8b5cf6;
    }

    .notif-content {
        flex: 1;
        min-width: 0;
    }

    .notif-title {
        color: white;
        font-weight: 700;
        font-size: 0.95rem;
        margin-bottom: 0.25rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .notif-message {
        color: #cbd5e1;
        font-size: 0.85rem;
        line-height: 1.4;
        margin-bottom: 0.5rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .notif-time {
        color: #64748b;
        font-size: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .notif-actions {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        flex-shrink: 0;
    }

    .unread-indicator {
        width: 8px;
        height: 8px;
        background: #ef4444;
        border-radius: 50%;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2);
        animation: pulse-badge 2s infinite;
    }

    .delete-notif {
        width: 30px;
        height: 30px;
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        border-radius: 8px;
        color: #ef4444;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
    }

    .delete-notif:hover {
        background: rgba(239, 68, 68, 0.2);
        transform: scale(1.1);
    }

    .notification-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(8px);
        z-index: 2000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .notification-modal.show {
        display: flex;
    }

    .modal-content {
        background: rgba(15, 23, 42, 0.98);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(99, 102, 241, 0.3);
        border-radius: 16px;
        max-width: 500px;
        width: 100%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
        from {
            transform: translateY(30px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
    }

    .modal-icon.info {
        background: rgba(59, 130, 246, 0.2);
        color: #3b82f6;
    }

    .modal-icon.success {
        background: rgba(16, 185, 129, 0.2);
        color: #10b981;
    }

    .modal-icon.warning {
        background: rgba(245, 158, 11, 0.2);
        color: #f59e0b;
    }

    .modal-icon.error {
        background: rgba(239, 68, 68, 0.2);
        color: #ef4444;
    }

    .modal-icon.announcement {
        background: rgba(139, 92, 246, 0.2);
        color: #8b5cf6;
    }

    .modal-close {
        width: 40px;
        height: 40px;
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.3);
        border-radius: 10px;
        color: #ef4444;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .modal-close:hover {
        background: rgba(239, 68, 68, 0.2);
        transform: rotate(90deg);
    }

    .modal-body {
        padding: 2rem;
    }

    .modal-body h5 {
        color: white;
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 1rem;
    }

    .modal-body p {
        color: #cbd5e1;
        font-size: 1rem;
        line-height: 1.6;
        margin: 0;
    }

    .overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        z-index: 998;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .overlay.show {
        opacity: 1;
        visibility: visible;
    }

    .sidebar {
        position: fixed;
        top: 0;
        right: -100%;
        width: 300px;
        height: 100vh;
        background: rgba(10, 10, 10, 0.95);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-left: 1px solid rgba(229, 9, 20, 0.3);
        z-index: 1002;
        transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow-y: auto;
        box-shadow: -10px 0 30px rgba(0, 0, 0, 0.5);
    }

    .sidebar.show {
        right: 0;
        visibility: visible !important;
        transform: translateX(0) !important;
    }

    .sidebar-head {
        padding: 1.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .user-info {
        display: flex;
        gap: 0.75rem;
        flex: 1;
    }

    .avatar {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #E50914, #99060d);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.25rem;
        font-weight: 800;
        flex-shrink: 0;
        box-shadow: 0 4px 15px rgba(229, 9, 20, 0.3);
    }

    .user-text {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        min-width: 0;
    }

    .username {
        color: white;
        font-weight: 700;
        font-size: 1rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .user-email {
        color: #94a3b8;
        font-size: 0.8rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .close-btn {
        width: 35px;
        height: 35px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        color: #fff;
        cursor: pointer;
        transition: all 0.3s ease;
        flex-shrink: 0;
    }

    .close-btn:hover {
        background: rgba(229, 9, 20, 0.2);
        border-color: #E50914;
        color: #E50914;
        transform: rotate(90deg);
    }

    .sidebar-body {
        padding: 1.5rem;
    }

    .menu-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-bottom: 1.25rem;
    }

    .menu-link {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        border-radius: 10px;
        transition: all 0.3s ease;
        font-weight: 500;
    }

    .menu-link:hover {
        background: rgba(229, 9, 20, 0.1);
        color: white;
        padding-left: 1.25rem;
    }

    .menu-link.active {
        background: rgba(229, 9, 20, 0.2);
        color: white;
        border-left: 3px solid #E50914;
    }

    .menu-link i {
        width: 24px;
        font-size: 1.1rem;
        color: #E50914;
    }

    .menu-link.logout {
        color: #ef4444;
        margin-top: 0.5rem;
        border-top: 1px solid rgba(255, 255, 255, 0.05);
        padding-top: 1.5rem;
    }

    .menu-link.logout:hover {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }

    @media (max-width: 991px) {
        body {
            padding-top: 70px;
        }

        .main-nav {
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.2);
        }

        .nav-wrap {
            padding: 0.75rem 0;
            gap: 1rem;
        }

        .brand-icon {
            width: 45px;
            height: 45px;
            font-size: 1.3rem;
        }

        .brand-text {
            font-size: 1.3rem;
        }

        .nav-menu {
            display: none;
        }

        .desktop-only {
            display: none !important;
        }

        .mobile-only {
            display: flex;
        }

        .notification-toggle {
            width: 45px;
            height: 45px;
            font-size: 1.15rem;
        }

        .menu-toggle {
            width: 60px;
            height: 45px;
        }

        .menu-text {
            font-size: 0.85rem;
            letter-spacing: 0.3px;
        }

        .notification-panel {
            width: 100%;
            max-width: 400px;
        }

        .nav-dropdown-menu {
            display: none !important;
        }
    }

    @media (max-width: 576px) {
        body {
            padding-top: 65px;
        }

        .nav-wrap {
            padding: 0.65rem 0;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            font-size: 1.2rem;
        }

        .brand-text {
            font-size: 1.2rem;
        }

        .nav-right {
            gap: 0.75rem;
        }

        .notification-toggle {
            width: 40px;
            height: 40px;
            font-size: 1.1rem;
        }

        .menu-toggle {
            width: 55px;
            height: 40px;
        }

        .menu-text {
            font-size: 0.8rem;
            letter-spacing: 0.2px;
        }

        .notification-panel {
            width: 100%;
        }
    }

    @media (min-width: 1400px) {
        .nav-wrap {
            padding: 1.25rem 0;
        }

        .brand-icon {
            width: 55px;
            height: 55px;
            font-size: 1.6rem;
        }

        .brand-text {
            font-size: 1.6rem;
        }

        .nav-menu {
            gap: 0.75rem;
        }

        .nav-menu-link {
            padding: 0.875rem 1.75rem;
            font-size: 1.05rem;
        }

        .notification-toggle {
            width: 55px;
            height: 55px;
            font-size: 1.3rem;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            font-size: 1.35rem;
        }

        .user-name {
            font-size: 1rem;
        }

        .user-balance {
            font-size: 0.95rem;
        }
    }

    .notification-body::-webkit-scrollbar,
    .sidebar::-webkit-scrollbar {
        width: 8px;
    }

    .notification-body::-webkit-scrollbar-track,
    .sidebar::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 10px;
    }

    .notification-body::-webkit-scrollbar-thumb,
    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(99, 102, 241, 0.5);
        border-radius: 10px;
    }

    .notification-body::-webkit-scrollbar-thumb:hover,
    .sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(99, 102, 241, 0.7);
    }

    .nav-menu-item {
        position: relative;
    }

    .nav-menu-link.dropdown-toggle {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .dropdown-arrow-nav {
        font-size: 0.8rem;
        margin-left: 0.25rem;
        transition: transform 0.3s ease;
    }

    .nav-dropdown-menu.active .dropdown-arrow-nav {
        transform: rotate(180deg);
    }

    .nav-submenu {
        position: absolute;
        top: calc(100% + 1rem);
        left: 0;
        width: 300px;
        background: rgba(15, 23, 42, 0.98);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(99, 102, 241, 0.3);
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s ease;
        z-index: 1001;
        padding: 0.5rem;
    }

    .nav-dropdown-menu.active .nav-submenu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .submenu-header {
        padding: 1rem 1rem 0.5rem;
        font-size: 0.85rem;
        color: #94a3b8;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .submenu-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.875rem 1rem;
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        border-radius: 10px;
        transition: all 0.3s ease;
        font-weight: 600;
    }

    .submenu-item i {
        width: 24px;
        font-size: 1.1rem;
        text-align: center;
        color: #818cf8;
        transition: color 0.3s ease;
    }

    .submenu-item:hover {
        background: rgba(99, 102, 241, 0.15);
        color: white;
        transform: translateX(5px);
    }

    .submenu-item:hover i {
        color: white;
    }

    .submenu-item.all-items {
        color: #a5b4fc;
    }

    .submenu-item.all-items:hover {
        color: white;
    }

    .submenu-divider {
        height: 1px;
        background: rgba(255, 255, 255, 0.1);
        margin: 0.5rem 1rem;
    }

    .menu-link.has-submenu {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .arrow-mobile {
        font-size: 0.9rem;
        transition: transform 0.3s ease;
    }

    .menu-link-group.open .arrow-mobile {
        transform: rotate(90deg);
    }

    .menu-link-group.open .menu-link.has-submenu {
        background: rgba(99, 102, 241, 0.1);
        color: white;
    }

    .submenu-list {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
        padding-left: 1.5rem;
        background: rgba(99, 102, 241, 0.05);
        border-radius: 0 0 10px 10px;
        margin-top: -5px;
        margin-bottom: 5px;
    }

    .menu-link-group.open .submenu-list {
        max-height: 500px;
    }

    .submenu-link {
        display: block;
        padding: 0.8rem 1rem 0.8rem 1rem;
        color: #cbd5e1;
        text-decoration: none;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        border-left: 3px solid rgba(99, 102, 241, 0.3);
    }

    .submenu-link span {
        padding-left: 1rem;
    }

    .submenu-link:hover {
        background: rgba(99, 102, 241, 0.1);
        color: white;
        border-left-color: #6366f1;
    }

    /* ✅ Chat Toggle Styles */
    .chat-toggle-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #6366f1, #ec4899);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        box-shadow: 0 4px 20px rgba(99, 102, 241, 0.5);
        z-index: 1050;
        transition: transform 0.3s, box-shadow 0.3s;
        text-decoration: none;
    }

    .chat-toggle-btn:hover {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 6px 25px rgba(99, 102, 241, 0.7);
        color: white;
    }

    .chat-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ef4444;
        color: white;
        font-size: 0.75rem;
        font-weight: bold;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #0f172a;
        animation: bounce 1s infinite;
    }

    @keyframes bounce {

        0%,
        100% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-3px);
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // --- 1. ตัวแปร Elements ---
        const notificationToggle = document.getElementById('notificationToggle');
        const notificationPanel = document.getElementById('notificationPanel');
        const notificationModal = document.getElementById('notificationModal');
        const modalIcon = document.getElementById('modalIcon');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const modalClose = document.getElementById('modalClose');
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const closeSidebar = document.getElementById('closeSidebar');
        const markAllRead = document.getElementById('markAllRead');
        const userProfile = document.getElementById('userProfile');
        const userDropdown = document.getElementById('userDropdown');
        const productsMenu = document.getElementById('productsMenu');
        const productsToggle = document.getElementById('productsToggle');
        const mobileProductsToggle = document.getElementById('mobileProductsToggle');
        const chatBadge = document.getElementById('chatBadge'); // ปุ่มแจ้งเตือนแชท

        // Rentals Menu Elements
        const rentalsMenu = document.getElementById('rentalsMenu');
        const rentalsToggle = document.getElementById('rentalsToggle');
        const mobileRentalsToggle = document.getElementById('mobileRentalsToggle');
        const userRentalsToggle = document.getElementById('userRentalsToggle');
        const userRentalsSubmenu = document.getElementById('userRentalsSubmenu');

        // --- 2. ฟังก์ชันจัดการ Notification Panel ---
        function toggleNotification() {
            const isOpen = notificationPanel.classList.contains('show');
            if (isOpen) {
                notificationPanel.classList.remove('show');
                notificationToggle.classList.remove('active');
                if (window.innerWidth < 992) overlay.classList.remove('show');
            } else {
                notificationPanel.classList.add('show');
                notificationToggle.classList.add('active');

                // ปิดเมนูอื่นๆ
                sidebar.classList.remove('show');
                userProfile?.classList.remove('active');
                rentalsMenu?.classList.remove('active');
                productsMenu?.classList.remove('active');

                if (window.innerWidth < 992) overlay.classList.add('show');
            }
        }

        // --- 3. ฟังก์ชันจัดการ User Profile Dropdown ---
        function toggleUserProfile() {
            if (window.innerWidth >= 992) {
                const isOpen = userProfile.classList.contains('active');
                if (isOpen) {
                    userProfile.classList.remove('active');
                } else {
                    userProfile.classList.add('active');
                    // ปิดเมนูอื่นๆ
                    notificationPanel.classList.remove('show');
                    notificationToggle.classList.remove('active');
                    productsMenu?.classList.remove('active');
                    rentalsMenu?.classList.remove('active');
                }
            }
        }

        // --- 4. ฟังก์ชันจัดการ Products Dropdown ---
        function toggleProductsMenu() {
            if (window.innerWidth >= 992) {
                const isOpen = productsMenu.classList.contains('active');
                if (isOpen) {
                    productsMenu.classList.remove('active');
                } else {
                    productsMenu.classList.add('active');
                    // ปิดเมนูอื่นๆ
                    userProfile?.classList.remove('active');
                    notificationPanel.classList.remove('show');
                    rentalsMenu?.classList.remove('active');
                    notificationToggle.classList.remove('active');
                }
            }
        }

        // --- 5. Event Listeners: การคลิกปุ่มต่างๆ ---

        // Notification
        notificationToggle.addEventListener('click', (e) => { e.stopPropagation(); toggleNotification(); });

        // User Profile
        if (userProfile) {
            userProfile.addEventListener('click', (e) => { e.stopPropagation(); toggleUserProfile(); });
        }

        // Products Menu (Desktop)
        if (productsToggle) {
            productsToggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                toggleProductsMenu();
            });
        }

        // Products Menu (Mobile Accordion)
        if (mobileProductsToggle) {
            mobileProductsToggle.addEventListener('click', function (e) {
                e.preventDefault();
                this.parentElement.classList.toggle('open');
            });
        }

        // --- Rentals Menu Functions ---
        function toggleRentalsMenu() {
            if (window.innerWidth >= 992) {
                const isOpen = rentalsMenu.classList.contains('active');
                if (isOpen) {
                    rentalsMenu.classList.remove('active');
                } else {
                    rentalsMenu.classList.add('active');
                    // ปิดเมนูอื่นๆ
                    userProfile?.classList.remove('active');
                    productsMenu?.classList.remove('active');
                    notificationPanel.classList.remove('show');
                    notificationToggle.classList.remove('active');
                }
            }
        }

        // Rentals Menu (Desktop)
        if (rentalsToggle) {
            rentalsToggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                toggleRentalsMenu();
            });
        }

        // Rentals Menu (Mobile Accordion)
        if (mobileRentalsToggle) {
            mobileRentalsToggle.addEventListener('click', function (e) {
                e.preventDefault();
                this.parentElement.classList.toggle('open');
            });
        }

        // User Rentals Submenu (in user dropdown)
        if (userRentalsToggle) {
            userRentalsToggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                userRentalsSubmenu?.classList.toggle('show');
            });
        }

        // Sidebar Toggle
        if (menuToggle) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.add('show');
                overlay.classList.add('show');
                // ปิด Panel อื่น
                notificationPanel.classList.remove('show');
                notificationToggle.classList.remove('active');
                menuToggle.classList.add('active');
            });
        }

        // Close Sidebar Btn
        if (closeSidebar) {
            closeSidebar.addEventListener('click', () => {
                sidebar.classList.remove('show');
                notificationPanel.classList.remove('show');
                notificationToggle.classList.remove('active');
                menuToggle.classList.remove('active');
                overlay.classList.remove('show');
            });
        }

        // Overlay Click (ปิดทุกอย่าง)
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            notificationPanel.classList.remove('show');
            notificationToggle.classList.remove('active');
            menuToggle.classList.remove('active');
            overlay.classList.remove('show');
            userProfile?.classList.remove('active');
            productsMenu?.classList.remove('active');
            rentalsMenu?.classList.remove('active');
        });

        // คลิกพื้นที่ว่างเพื่อปิด Dropdown
        document.addEventListener('click', (e) => {
            if (userProfile && !userProfile.contains(e.target)) userProfile.classList.remove('active');
            if (productsMenu && !productsMenu.contains(e.target)) productsMenu.classList.remove('active');
            if (rentalsMenu && !rentalsMenu.contains(e.target)) rentalsMenu.classList.remove('active');

            if (window.innerWidth >= 992) {
                if (notificationPanel && !notificationPanel.contains(e.target) && !notificationToggle.contains(e.target)) {
                    notificationPanel.classList.remove('show');
                    notificationToggle.classList.remove('active');
                }
            }
        });

        // Keyboard Esc
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                notificationPanel.classList.remove('show');
                notificationToggle.classList.remove('active');
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                userProfile?.classList.remove('active');
                productsMenu?.classList.remove('active');
                rentalsMenu?.classList.remove('active');
                notificationModal.classList.remove('show');
            }
        });

        // --- 6. Notification Modal & Logic ---

        modalClose.addEventListener('click', () => notificationModal.classList.remove('show'));
        notificationModal.addEventListener('click', (e) => { if (e.target === notificationModal) notificationModal.classList.remove('show'); });

        async function showModal(title, message, type, transactionRef = null) {
            modalTitle.textContent = title;
            modalMessage.textContent = message;
            modalIcon.className = 'modal-icon ' + type;

            let iconClass = 'fa-info-circle';
            if (type === 'success') iconClass = 'fa-check-circle';
            if (type === 'warning') iconClass = 'fa-exclamation-triangle';
            if (type === 'error') iconClass = 'fa-times-circle';
            if (type === 'announcement') iconClass = 'fa-bullhorn';

            modalIcon.innerHTML = '<i class="fas ' + iconClass + '"></i>';

            // ปุ่มชำระเงิน (ถ้ามี)
            const modalBody = document.querySelector('.modal-body');
            const existingBtn = modalBody.querySelector('.btn-continue-payment');
            if (existingBtn) existingBtn.remove();

            if (transactionRef) {
                const paymentBtn = document.createElement('button');
                paymentBtn.className = 'btn-continue-payment';
                paymentBtn.innerHTML = '<i class="fas fa-credit-card"></i> ดำเนินการชำระเงิน';
                paymentBtn.style.cssText = `
                    margin-top: 1.5rem; width: 100%; padding: 1rem; background: linear-gradient(135deg, #10b981, #059669);
                    border: none; border-radius: 12px; color: white; font-weight: 700; font-size: 1rem; cursor: pointer;
                    transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 0.5rem;
                `;
                paymentBtn.addEventListener('click', async () => {
                    try {
                        const response = await fetch('controller/continue_payment.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'transaction_ref=' + encodeURIComponent(transactionRef)
                        });
                        const data = await response.json();
                        if (data.success && data.payment_url) {
                            window.open(data.payment_url, '_blank');
                            notificationModal.classList.remove('show');
                        } else {
                            Swal.fire('เกิดข้อผิดพลาด', data.message || 'ไม่สามารถเปิดหน้าชำระเงินได้', 'error');
                        }
                    } catch (error) {
                        Swal.fire('Error', 'Server Error', 'error');
                    }
                });
                modalBody.appendChild(paymentBtn);
            }
            notificationModal.classList.add('show');
        }

        // คลิกรายการแจ้งเตือน
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', async function (e) {
                if (e.target.closest('.delete-notif')) return;

                const notifId = this.dataset.id;
                const transactionId = this.dataset.transactionId;
                const transactionRef = this.dataset.transactionRef;
                const transactionStatus = this.dataset.transactionStatus;
                const isPendingTopup = transactionId && transactionStatus === 'pending' && this.dataset.title.includes('เติมเงิน');

                showModal(this.dataset.title, this.dataset.message, this.dataset.type, isPendingTopup ? transactionRef : null);

                // Mark as read
                if (this.classList.contains('unread')) {
                    try {
                        const res = await fetch('controller/mark_notification_read.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ notification_id: notifId })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.classList.remove('unread');
                            this.querySelector('.unread-indicator')?.remove();
                            updateBadgeCount();
                        }
                    } catch (err) { console.error(err); }
                }
            });
        });

        // ลบแจ้งเตือน
        document.querySelectorAll('.delete-notif').forEach(btn => {
            btn.addEventListener('click', async function (e) {
                e.stopPropagation();
                const notifId = this.dataset.id;
                const result = await Swal.fire({
                    title: 'ยืนยัน', text: 'ลบการแจ้งเตือนนี้?', icon: 'warning',
                    showCancelButton: true, confirmButtonText: 'ลบ', cancelButtonText: 'ยกเลิก',
                    confirmButtonColor: '#ef4444', cancelButtonColor: '#64748b'
                });

                if (result.isConfirmed) {
                    try {
                        const res = await fetch('controller/delete_notification.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ notification_id: notifId })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.closest('.notification-item').remove();
                            updateBadgeCount();

                            // ถ้าหมดแล้วให้แสดง Empty State
                            if (document.querySelectorAll('.notification-item').length === 0) {
                                document.querySelector('.notification-body').innerHTML =
                                    '<div class="notification-empty"><i class="fas fa-bell-slash"></i><p>ไม่มีการแจ้งเตือน</p></div>';
                            }
                        }
                    } catch (err) { Swal.fire('Error', 'Failed to delete', 'error'); }
                }
            });
        });

        // อ่านทั้งหมด
        markAllRead?.addEventListener('click', async () => {
            try {
                const res = await fetch('controller/mark_notifications_read.php', { method: 'POST' });
                const data = await res.json();
                if (data.success) {
                    document.querySelectorAll('.notification-item.unread').forEach(i => {
                        i.classList.remove('unread');
                        i.querySelector('.unread-indicator')?.remove();
                    });
                    updateBadgeCount();
                    markAllRead.remove();
                }
            } catch (err) { }
        });

        // ล้างการแจ้งเตือนทั้งหมดของสมาชิก
        const clearAllNotif = document.getElementById('clearAllNotif');
        clearAllNotif?.addEventListener('click', async () => {
            const result = await Swal.fire({
                title: 'ยืนยันการลบ',
                text: 'ต้องการล้างการแจ้งเตือนส่วนตัวทั้งหมด? (ไม่รวมประกาศจากระบบ)',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ล้างทั้งหมด',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b'
            });

            if (result.isConfirmed) {
                try {
                    const res = await fetch('controller/clear_all_notifications.php', { method: 'POST' });
                    const data = await res.json();
                    if (data.success) {
                        // ลบเฉพาะรายการที่ไม่ใช่ประกาศ (user_id !== null)
                        document.querySelectorAll('.notification-item').forEach(item => {
                            if (item.dataset.userId && item.dataset.userId !== 'null' && item.dataset.userId !== '') {
                                item.remove();
                            }
                        });
                        updateBadgeCount();

                        // ถ้าหมดแล้วให้แสดง Empty State
                        if (document.querySelectorAll('.notification-item').length === 0) {
                            document.querySelector('.notification-body').innerHTML =
                                '<div class="notification-empty"><i class="fas fa-bell-slash"></i><p>ไม่มีการแจ้งเตือน</p></div>';
                        }

                        Swal.fire({
                            title: 'สำเร็จ!',
                            text: `ลบการแจ้งเตือนส่วนตัว ${data.deleted_count} รายการแล้ว`,
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('เกิดข้อผิดพลาด', data.message || 'ไม่สามารถลบได้', 'error');
                    }
                } catch (err) {
                    Swal.fire('Error', 'Server Error', 'error');
                }
            }
        });

        // Helper: อัปเดตตัวเลขแจ้งเตือน (กระดิ่ง)
        function updateBadgeCount() {
            const count = document.querySelectorAll('.notification-item.unread').length;
            const badge = notificationToggle.querySelector('.notification-badge');
            const headerBadge = document.querySelector('.header-badge');

            if (count === 0) {
                badge?.remove();
                headerBadge?.remove();
            } else {
                if (badge) badge.textContent = count > 99 ? '99+' : count;
                if (headerBadge) headerBadge.textContent = count;
            }
        }

        // --- 7. ✨ Chat Polling System (ระบบแชท Real-time) ---
        if (chatBadge) {
            function checkChatUnread() {
                fetch('controller/chat_api.php?action=check_notify')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.unread > 0) {
                            chatBadge.style.display = 'flex';
                            chatBadge.textContent = data.unread > 99 ? '99+' : data.unread;
                        } else {
                            chatBadge.style.display = 'none';
                        }
                    })
                    .catch(err => {
                        // Silent fail is ok for polling
                    });
            }

            // ตรวจสอบทุกๆ 5 วินาที
            setInterval(checkChatUnread, 5000);
            checkChatUnread(); // เรียกครั้งแรกทันที
        }

        // --- 8. Active Menu Highlighter ---
        const currentPage = new URLSearchParams(window.location.search).get('p') || 'home';
        document.querySelectorAll('.sidebar .menu-link').forEach(link => {
            if (link.getAttribute('href').includes(`p=${currentPage}`)) link.classList.add('active');
        });

        // Highlight Parent Menu if Submenu Active (Mobile)
        document.querySelectorAll('.sidebar .submenu-link').forEach(link => {
            if (link.getAttribute('href').includes(`p=${currentPage}`)) {
                link.closest('.menu-link-group').classList.add('open');
                link.closest('.menu-link-group').querySelector('.menu-link.has-submenu').classList.add('active');
            }
        });
    });
</script>