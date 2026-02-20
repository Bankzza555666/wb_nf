<div class="sidebar" id="sidebar" style="visibility: hidden; transform: translateX(-100%);">
    <div class="sidebar-head">
        <div class="user-info">
            <div class="avatar">
                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
            </div>
            <div class="user-text">
                <div class="username">
                    <?php echo htmlspecialchars($user['username']); ?>
                </div>
                <div class="user-email">
                    <?php echo htmlspecialchars($user['email']); ?>
                </div>
            </div>
        </div>
        <button class="close-btn" id="closeSidebar"><i class="fas fa-times"></i></button>
    </div>

    <div class="sidebar-body">
        <div class="balance-card">
            <div class="balance-label">ยอดเงินคงเหลือ</div>
            <div class="balance-value"><i class="fas fa-wallet"></i> ฿
                <?php echo number_format($user['credit'], 2); ?>
            </div>
        </div>

        <div class="menu-list">
            <a href="?p=home" class="menu-link"><i class="fas fa-home"></i><span>หน้าหลัก </span></a>

            <div class="menu-link-group">
                <a href="#" class="menu-link has-submenu" id="mobileProductsToggle">
                    <i class="fas fa-store"></i><span>สินค้า/บริการ</span><i
                        class="fas fa-chevron-right arrow-mobile"></i>
                </a>
                <div class="submenu-list" id="mobileSubmenu">
                    <a href="?p=rent_vpn" class="submenu-link text-b"><span>เช่า VPN/V2ray</span></a>
                    <a href="?p=rent_ssh" class="submenu-link"><span>เช่า Netmod/Npv Tunnel</span></a>
                    <a href="?p=products_category&id=3" class="submenu-link"><span>สตรีมมิ่ง</span></a>
                    <a href="?p=products_all" class="submenu-link"><span>ดูทั้งหมด</span></a>
                </div>
            </div>

            <div class="menu-link-group">
                <a href="#" class="menu-link has-submenu" id="mobileRentalsToggle">
                    <i class="fas fa-tasks"></i><span>รายการที่เช่า</span><i
                        class="fas fa-chevron-right arrow-mobile"></i>
                </a>
                <div class="submenu-list" id="mobileRentalsSubmenu">
                    <a href="?p=my_vpn" class="submenu-link"><span>VPN/V2ray ของฉัน</span></a>
                    <a href="?p=my_ssh" class="submenu-link"><span>SSH/NPV ของฉัน</span></a>
                    <div style="border-top: 1px solid rgba(255,255,255,0.1); margin: 8px 0;"></div>
                    <a href="?p=tutorials" class="submenu-link"><span>วิธีใช้งาน</span></a>
                    <a href="?p=faq" class="submenu-link"><span>คำถามที่พบบ่อย</span></a>
                </div>
            </div>

            <!-- ✅ เมนูชวนเพื่อน (Mobile) -->
            <a href="?p=referral" class="menu-link <?php echo ($current_page == 'referral') ? 'active' : ''; ?>">
                <i class="fas fa-gift"></i><span>ชวนเพื่อน รับเครดิต</span>
            </a>

            <a href="?p=topup" class="menu-link"><i class="fas fa-credit-card"></i><span>เติมเงิน</span></a>
            <a href="?p=topup_history" class="menu-link"><i class="fas fa-history"></i><span>ประวัติ</span></a>
            <a href="?p=userdetail" class="menu-link"><i class="fas fa-cog"></i><span>ตั้งค่า</span></a>
        </div>

        <a href="?p=logout" class="menu-link logout"><i class="fas fa-sign-out-alt"></i><span>ออกจากระบบ</span></a>
    </div>
</div>

<style>
    /* Sidebar Style 2: Floating Right Panel */
    .sidebar {
        position: fixed;
        top: 20px !important;
        left: auto !important;
        right: -320px;
        /* Hidden off-screen right */
        bottom: 20px !important;
        width: 300px !important;
        background: rgba(15, 15, 20, 0.95) !important;
        backdrop-filter: blur(25px) !important;
        -webkit-backdrop-filter: blur(25px) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        border-radius: 24px !important;
        box-shadow: -10px 0 40px rgba(0, 0, 0, 0.5) !important;
        transform: translateX(0) !important;
        transition: right 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
    }

    .sidebar.active {
        right: 20px !important;
    }
</style>