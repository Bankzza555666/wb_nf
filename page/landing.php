<?php
// page/landing.php (Clean Version - No Contact Form)

$servers_data = [];
$user_counts = [];
$allow_register = '1';

// ✅ ดึงรหัสแนะนำจาก URL (ถ้ามี)
$referral_code_from_url = isset($_GET['ref']) ? strtoupper(trim($_GET['ref'])) : '';

if (file_exists('controller/config.php'))
    require_once 'controller/config.php';
elseif (file_exists('../controller/config.php'))
    require_once '../controller/config.php';
else
    $conn = null;

// โหลด Editable Content Helper (ถ้าไม่มีจะใช้ฟังก์ชัน fallback)
if (file_exists('controller/editable_content.php'))
    require_once 'controller/editable_content.php';
elseif (file_exists('../controller/editable_content.php'))
    require_once '../controller/editable_content.php';

// Fallback function ถ้าโหลด editable_content.php ไม่ได้
if (!function_exists('editableText')) {
    function editableText($key, $default = '', $page = '*') { return htmlspecialchars($default); }
}
if (!function_exists('editableHtml')) {
    function editableHtml($key, $default = '', $page = '*') { return $default; }
}
if (!function_exists('editableImage')) {
    function editableImage($key, $default = '', $page = '*') { return htmlspecialchars($default); }
}

if ($conn) {
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

    $sql = "SELECT s.*, 
            (SELECT GROUP_CONCAT(DISTINCT UPPER(protocol) SEPARATOR ' / ') 
             FROM price_v2 
             WHERE server_id = s.server_id AND is_active = 1) as protocols
            FROM servers s 
            WHERE s.is_active = 1 AND s.server_status = 'online' 
            ORDER BY s.sort_order ASC";
    $result = $conn->query($sql);
    if ($result)
        while ($row = $result->fetch_assoc())
            $servers_data[] = $row;

    $count_sql = "SELECT server_id, COUNT(*) as active_users FROM user_rentals WHERE status = 'active' AND expire_date > NOW() GROUP BY server_id";
    $count_result = $conn->query($count_sql);
    if ($count_result)
        while ($row = $count_result->fetch_assoc())
            $user_counts[$row['server_id']] = $row['active_users'];
}

function getFlagEmoji($code, $name)
{
    $flags = ['TH' => '🇹🇭', 'SG' => '🇸🇬', 'US' => '🇺🇸', 'JP' => '🇯🇵', 'UK' => '🇬🇧', 'DE' => '🇩🇪', 'FR' => '🇫🇷'];
    $c = strtoupper($code ?? '');
    if (empty($c) && !empty($name))
        $c = strtoupper(substr($name, 0, 2));
    return $flags[$c] ?? '🌐';
}

include 'page/header.php';
include 'page/navbar.php';
?>

<style>
    /* ===== Landing Professional Theme ===== */
    :root {
        --lp-bg: var(--bg-body, #050508);
        --lp-card: rgba(15, 15, 22, 0.85);
        --lp-accent: #E50914;
        --lp-accent-soft: rgba(229, 9, 20, 0.15);
        --lp-border: rgba(255, 255, 255, 0.08);
        --lp-text: #ffffff;
        --lp-muted: rgba(255, 255, 255, 0.6);
    }

    body.landing-page {
        background: var(--lp-bg);
        font-family: 'Prompt', 'Segoe UI', sans-serif;
        overflow-x: hidden;
    }

    .landing-page .nebula-bg {
        position: fixed;
        inset: 0;
        z-index: -1;
        background: 
            radial-gradient(ellipse 80% 50% at 20% 20%, rgba(229, 9, 20, 0.08) 0%, transparent 50%),
            radial-gradient(ellipse 60% 40% at 80% 80%, rgba(229, 9, 20, 0.06) 0%, transparent 50%),
            radial-gradient(ellipse 100% 100% at 50% 50%, rgba(0, 0, 0, 0) 0%, #050508 100%);
    }

    .landing-page section {
        padding: 4rem 0;
    }

    /* ----- Hero ----- */
    .landing-page .hero-section {
        min-height: 85vh;
        display: flex;
        align-items: center;
        padding-top: 5rem;
    }

    .landing-page #heroCarousel {
        border-radius: 20px;
        overflow: hidden;
        border: 1px solid var(--lp-border);
        box-shadow: 0 25px 80px rgba(0,0,0,0.5);
    }

    .landing-page #heroCarousel .carousel-item img {
        height: 400px;
        object-fit: cover;
        filter: brightness(0.5);
    }

    .landing-page #heroCarousel .carousel-indicators button {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: rgba(255,255,255,0.4);
        border: none;
        margin: 0 4px;
    }

    .landing-page #heroCarousel .carousel-indicators button.active {
        background: var(--lp-accent);
        transform: scale(1.2);
    }

    .landing-page .hero-title {
        font-size: 2.5rem;
        font-weight: 700;
        line-height: 1.2;
        text-shadow: 0 2px 20px rgba(0,0,0,0.5);
    }

    .landing-page .hero-badge {
        font-size: 0.8rem;
        font-weight: 600;
        letter-spacing: 0.5px;
        border-radius: 8px;
    }

    .landing-page .carousel-control-prev-icon,
    .landing-page .carousel-control-next-icon {
        width: 2rem;
        height: 2rem;
        background-color: rgba(0,0,0,0.4);
        border-radius: 50%;
    }

    /* ----- Auth Cards ----- */
    .landing-page .glass-box {
        background: var(--lp-card);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--lp-border);
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    }

    .landing-page .auth-box {
        padding: 2rem 2rem 2.25rem;
    }

    .landing-page .auth-box h4 {
        font-weight: 700;
        margin-bottom: 1.5rem;
        color: var(--lp-text);
    }

    .landing-page .auth-box h4 i {
        color: var(--lp-accent);
        margin-right: 0.5rem;
    }

    .landing-page .form-control {
        background: rgba(0, 0, 0, 0.4) !important;
        border: 1px solid var(--lp-border) !important;
        color: #fff !important;
        font-size: 0.95rem;
        padding: 0.85rem 1rem;
        border-radius: 12px;
    }

    .landing-page .form-control::placeholder {
        color: var(--lp-muted);
    }

    .landing-page .form-control:focus {
        border-color: var(--lp-accent) !important;
        box-shadow: 0 0 0 3px var(--lp-accent-soft) !important;
    }

    .landing-page .form-check-input:checked {
        background-color: var(--lp-accent);
        border-color: var(--lp-accent);
    }

    .landing-page .btn-primary {
        background: linear-gradient(135deg, #E50914, #b80710);
        border: none;
        padding: 0.85rem 1.5rem;
        font-weight: 600;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(229, 9, 20, 0.35);
    }

    .landing-page .btn-primary:hover {
        background: linear-gradient(135deg, #ff1a25, #E50914);
        box-shadow: 0 6px 28px rgba(229, 9, 20, 0.45);
        transform: translateY(-1px);
    }

    .landing-page .btn-outline-light {
        border-radius: 12px;
        padding: 0.85rem 1.5rem;
        font-weight: 600;
    }

    .landing-page .btn-warning {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        border: none;
        color: #000 !important;
        font-weight: 600;
        border-radius: 12px;
    }

    /* ----- Servers ----- */
    .landing-page .server-section-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--lp-text);
        margin-bottom: 1.25rem;
        padding-left: 1rem;
        border-left: 4px solid var(--lp-accent);
    }

    .landing-page .server-list-container {
        max-height: 520px;
        overflow-y: auto;
        padding-right: 8px;
    }

    .landing-page .server-list-container::-webkit-scrollbar {
        width: 6px;
    }

    .landing-page .server-list-container::-webkit-scrollbar-track {
        background: rgba(255,255,255,0.05);
        border-radius: 3px;
    }

    .landing-page .server-list-container::-webkit-scrollbar-thumb {
        background: var(--lp-accent);
        border-radius: 3px;
    }

    .landing-page .server-item {
        display: flex;
        align-items: center;
        padding: 1rem 1.25rem;
        margin-bottom: 0.75rem;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid transparent;
        border-radius: 14px;
        cursor: pointer;
        transition: all 0.25s ease;
    }

    .landing-page .server-item:hover {
        background: var(--lp-accent-soft);
        transform: translateX(6px);
        border-color: rgba(229, 9, 20, 0.3);
    }

    .landing-page .server-item.selected {
        background: var(--lp-accent-soft);
        border-color: var(--lp-accent);
        box-shadow: 0 0 0 1px rgba(229, 9, 20, 0.2), 0 8px 24px rgba(0,0,0,0.2);
    }

    .landing-page .sv-flag {
        font-size: 1.75rem;
        margin-right: 1rem;
    }

    .landing-page .sv-name {
        font-weight: 600;
        color: #fff;
        font-size: 1rem;
    }

    .landing-page .sv-loc {
        font-size: 0.8rem;
        color: var(--lp-muted);
    }

    .landing-page .load-track {
        width: 56px;
        height: 5px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 3px;
        margin-top: 6px;
        margin-left: auto;
        overflow: hidden;
    }

    .landing-page .load-fill {
        height: 100%;
        border-radius: 3px;
        transition: width 0.35s ease;
    }

    .landing-page .preview-box {
        position: sticky;
        top: 100px;
        padding: 2rem;
        text-align: center;
        border-radius: 20px;
    }

    .landing-page .preview-flag-lg {
        font-size: 4rem;
        margin-bottom: 0.75rem;
        display: block;
    }

    .landing-page .stat-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
        margin: 1.5rem 0;
    }

    .landing-page .stat-box {
        background: rgba(0, 0, 0, 0.35);
        padding: 1rem;
        border-radius: 12px;
        border: 1px solid var(--lp-border);
    }

    .landing-page .stat-lbl {
        font-size: 0.7rem;
        color: var(--lp-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .landing-page .stat-val {
        font-size: 1rem;
        font-weight: 700;
        color: #fff;
    }

    .landing-page .btn.rounded-pill {
        padding: 0.85rem 1.75rem;
        font-weight: 600;
    }

    /* ----- Features ----- */
    .landing-page #features {
        background: linear-gradient(180deg, transparent 0%, rgba(0,0,0,0.25) 50%, transparent 100%);
    }

    .landing-page .feature-item {
        transition: all 0.3s ease;
        padding: 2rem 1.5rem;
        border-radius: 18px;
        border: 1px solid transparent;
        height: 100%;
    }

    .landing-page .feature-item:hover {
        background: var(--lp-accent-soft);
        transform: translateY(-6px);
        border-color: rgba(229, 9, 20, 0.25);
        box-shadow: 0 12px 40px rgba(0,0,0,0.2);
    }

    .landing-page .feature-item i {
        display: inline-block;
        margin-bottom: 1rem;
        filter: drop-shadow(0 0 12px currentColor);
    }

    .landing-page .feature-item h6 {
        font-weight: 700;
        color: #fff;
        margin-bottom: 0.5rem;
    }

    .landing-page .feature-item p {
        color: var(--lp-muted);
        font-size: 0.9rem;
        line-height: 1.6;
    }

    #login, #registerCard {
        scroll-margin-top: 90px;
    }
</style>

<a id="ctaLogin" href="#login" class="d-none"></a>
<a id="ctaRegister" href="#registerCard" class="d-none"></a>
<div class="landing-page">
<div class="nebula-bg"></div>

<section id="home" class="hero-section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <!-- Hero Carousel -->
                <div id="heroCarousel"
                    class="carousel slide carousel-fade shadow-lg rounded-4 overflow-hidden border border-secondary border-opacity-25"
                    data-bs-ride="carousel" data-bs-interval="5000">
                    <div class="carousel-indicators">
                        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0"
                            class="active"></button>
                        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1"></button>
                        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2"></button>
                    </div>
                    <div class="carousel-inner">
                        <!-- Slide 1: Cloud -->
                        <div class="carousel-item active">
                            <img src="img/hero_cloud.png" class="d-block w-100"
                                style="height: 380px; object-fit: cover; filter: brightness(0.6);" alt="Private Cloud">
                            <div class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column justify-content-center px-5">
                                <span class="badge hero-badge bg-primary bg-opacity-50 text-white border-0 mb-3 px-3 py-2" data-editable="hero_badge_1"><?php echo editableText('hero_badge_1', 'NF~SHOP Cloud', 'landing'); ?></span>
                                <h1 class="hero-title text-white mb-2" data-editable="hero_title_1"><?php echo editableText('hero_title_1', 'Private Cloud', 'landing'); ?></h1>
                                <p class="text-white opacity-90 mb-4 fs-6" style="max-width: 420px; text-shadow: 0 1px 4px rgba(0,0,0,0.8);" data-editable="hero_desc_1"><?php echo editableText('hero_desc_1', 'เซิร์ฟเวอร์ VPN ความเร็วสูง ส่วนตัวและปลอดภัย', 'landing'); ?></p>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="#servers" class="btn btn-primary rounded-pill px-4 py-2 fw-bold text-nowrap">เลือกเซิร์ฟเวอร์</a>
                                    <a href="#features" class="btn btn-outline-light rounded-pill px-4 py-2 text-nowrap">ดูฟีเจอร์</a>
                                </div>
                            </div>
                        </div>
                        <!-- Slide 2: Secure -->
                        <div class="carousel-item">
                            <img src="img/hero_secure.png" class="d-block w-100"
                                style="height: 380px; object-fit: cover; filter: brightness(0.6);" alt="Fast & Secure">
                            <div class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column justify-content-center px-5">
                                <span class="badge hero-badge bg-danger bg-opacity-75 text-white border-0 mb-3 px-3 py-2" data-editable="hero_badge_2"><?php echo editableText('hero_badge_2', 'Security First', 'landing'); ?></span>
                                <h1 class="hero-title text-white mb-2" data-editable="hero_title_2"><?php echo editableText('hero_title_2', 'Fast & Secure', 'landing'); ?></h1>
                                <p class="text-white opacity-90 mb-4 fs-6" style="max-width: 420px; text-shadow: 0 1px 4px rgba(0,0,0,0.8);" data-editable="hero_desc_2"><?php echo editableText('hero_desc_2', 'ปกป้องข้อมูลของคุณด้วยระบบความปลอดภัยระดับสูง', 'landing'); ?></p>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="#servers" class="btn btn-danger rounded-pill px-4 py-2 fw-bold text-nowrap">เริ่มใช้งาน</a>
                                </div>
                            </div>
                        </div>
                        <!-- Slide 3: Speed -->
                        <div class="carousel-item">
                            <img src="img/hero_speed.png" class="d-block w-100"
                                style="height: 380px; object-fit: cover; filter: brightness(0.6);" alt="High Speed">
                            <div class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column justify-content-center px-5">
                                <span class="badge hero-badge bg-success bg-opacity-75 text-white border-0 mb-3 px-3 py-2" data-editable="hero_badge_3"><?php echo editableText('hero_badge_3', 'High Performance', 'landing'); ?></span>
                                <h1 class="hero-title text-white mb-2" data-editable="hero_title_3"><?php echo editableText('hero_title_3', 'Gaming & Streaming', 'landing'); ?></h1>
                                <p class="text-white opacity-90 mb-4 fs-6" style="max-width: 420px; text-shadow: 0 1px 4px rgba(0,0,0,0.8);" data-editable="hero_desc_3"><?php echo editableText('hero_desc_3', 'รองรับการเล่นเกมและสตรีมมิ่ง ลื่นไหล ไม่มีสะดุด', 'landing'); ?></p>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="#servers" class="btn btn-success rounded-pill px-4 py-2 fw-bold text-nowrap">สมัครสมาชิก</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel"
                        data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel"
                        data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
            </div>

            <div class="col-lg-5 offset-lg-1">
                <div class="glass-box auth-box" id="login">
                    <h4 class="text-white fw-bold mb-3"><i class="fas fa-sign-in-alt me-2"></i>เข้าสู่ระบบ</h4>
                    <form id="loginForm" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="mb-3"><input type="text" class="form-control" name="username"
                                placeholder="ชื่อผู้ใช้" required></div>
                        <div class="mb-3"><input type="password" class="form-control" name="password"
                                placeholder="รหัสผ่าน" required></div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="rememberMe" id="rememberMe">
                                <label class="form-check-label text-white-50 small" for="rememberMe">
                                    จดจำฉันไว้
                                </label>
                            </div>
                            <a href="?r=forget" class="small text-white-50 text-decoration-none">ลืมรหัสผ่าน?</a>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-3 fw-bold mb-2">Login</button>
                        <div class="text-center">
                            <?php if ($allow_register !== '0'): ?>
                                <small><a href="#" id="showRegisterLink" class="text-white-50">สมัครสมาชิกใหม่</a></small>
                            <?php else: ?>
                                <small class="text-white-50">ปิดรับสมัครชั่วคราว</small>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <div class="glass-box auth-box" id="registerCard" style="display: none;">
                    <?php if ($allow_register !== '0'): ?>
                        <h4 class="text-white fw-bold mb-3"><i class="fas fa-user-plus me-2"></i>สมัครสมาชิก</h4>
                        <form id="registerForm" method="POST">
                            <!-- 🛡️ Honeypot Field (Bot Protection) -->
                            <div style="opacity: 0; position: absolute; top: 0; left: 0; height: 0; width: 0; z-index: -1;">
                                <input type="text" name="website_url" autocomplete="off" tabindex="-1">
                            </div>
                            <div class="mb-2"><input type="email" class="form-control" name="email" placeholder="อีเมล"
                                    required></div>
                            <div class="mb-2"><input type="text" class="form-control" name="username_reg"
                                    placeholder="ชื่อผู้ใช้" required></div>
                            <div class="row g-2 mb-2">
                                <div class="col-6"><input type="password" class="form-control" name="password_reg"
                                        placeholder="รหัสผ่าน" required></div>
                                <div class="col-6"><input type="password" class="form-control" name="confirm_password"
                                        placeholder="ยืนยัน" required></div>
                            </div>
                            <div class="mb-3">
                                <input type="text" class="form-control" name="referral_code" id="referralCodeInput"
                                        placeholder="รหัสแนะนำ (ถ้ามี)" maxlength="10" style="text-transform: uppercase;"
                                        value="<?php echo htmlspecialchars($referral_code_from_url); ?>">
                                <small class="text-white-50">* มีรหัสแนะนำจากเพื่อน? กรอกที่นี่</small>
                            </div>
                            <button type="submit"
                                class="btn btn-warning text-dark w-100 rounded-3 fw-bold mb-2">Register</button>
                            <div class="text-center"><small><a href="#" id="showLoginLink"
                                        class="text-white-50">กลับไปเข้าสู่ระบบ</a></small></div>
                        </form>
                    <?php else: ?>
                        <h4 class="text-white fw-bold mb-3"><i class="fas fa-user-lock me-2"></i>ปิดรับสมัครชั่วคราว</h4>
                        <p class="text-white-50 mb-3">ขณะนี้ระบบปิดรับสมัครสมาชิกใหม่</p>
                        <div class="text-center"><small><a href="#" id="showLoginLink"
                                    class="text-white-50">กลับไปเข้าสู่ระบบ</a></small></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="servers" class="pt-0">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-7">
                <h5 class="server-section-title">เซิร์ฟเวอร์ที่พร้อมให้บริการ</h5>
                <div class="server-list-container">
                    <?php if (empty($servers_data)): ?>
                        <div class="text-center py-5 glass-box rounded-3" style="color: var(--lp-muted);">
                            <i class="fas fa-server fa-2x mb-3 opacity-50"></i>
                            <p class="mb-0">ไม่พบข้อมูลเซิร์ฟเวอร์ในขณะนี้</p>
                            <small>กรุณาลองใหม่อีกครั้งภายหลัง</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($servers_data as $idx => $sv):
                            $active = $user_counts[$sv['server_id']] ?? 0;
                            $max = $sv['max_clients'];
                            $pct = ($max > 0) ? ($active / $max) * 100 : 0;
                            $color = $pct > 80 ? '#ef4444' : ($pct > 50 ? '#f59e0b' : '#4ade80');
                            $flag = getFlagEmoji($sv['country_code'] ?? '', $sv['server_name']);
                            $cls = $idx === 0 ? 'selected' : '';
                            $proto = !empty($sv['protocols']) ? $sv['protocols'] : 'Standard VPN';
                            ?>
                            <div class="server-item <?php echo $cls; ?>" onclick="viewServer(this)"
                                data-name="<?php echo htmlspecialchars($sv['server_name']); ?>"
                                data-loc="<?php echo htmlspecialchars($sv['server_location']); ?>"
                                data-flag="<?php echo $flag; ?>" data-users="<?php echo $active; ?>/<?php echo $max; ?>"
                                data-ping="<?php echo rand(20, 80); ?>ms" data-pct="<?php echo $pct; ?>"
                                data-proto="<?php echo htmlspecialchars($proto); ?>">

                                <div class="sv-flag"><?php echo $flag; ?></div>
                                <div class="sv-info">
                                    <div class="sv-name"><?php echo htmlspecialchars($sv['server_name']); ?></div>
                                    <div class="sv-loc"><?php echo htmlspecialchars($sv['server_location']); ?></div>
                                </div>
                                <div class="sv-stat">
                                    <div style="font-size: 0.75rem; color: <?php echo $color; ?>;">
                                        <?php echo number_format($pct, 0); ?>% Load
                                    </div>
                                    <div class="load-track">
                                        <div class="load-fill"
                                            style="width: <?php echo $pct; ?>%; background: <?php echo $color; ?>;"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="glass-box preview-box" id="previewPanel">
                    <span id="pvFlag" class="preview-flag-lg">🌐</span>
                    <h3 id="pvName" class="text-white fw-bold mb-1">Select Server</h3>
                    <p id="pvLoc" class="text-white-50 small mb-4">Click on the list to view details</p>

                    <div class="stat-grid">
                        <div class="stat-box">
                            <div class="stat-lbl">Status</div>
                            <div class="stat-val text-success">Online</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-lbl">Protocol</div>
                            <div id="pvProto" class="stat-val">TCP/UDP</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-lbl">Users</div>
                            <div id="pvUsers" class="stat-val">-</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-lbl">Est. Ping</div>
                            <div id="pvPing" class="stat-val text-primary">-</div>
                        </div>
                    </div>

                    <?php if ($allow_register !== '0'): ?>
                        <button onclick="gotoRegister()" class="btn btn-primary w-100 rounded-pill fw-bold">
                            <i class="fas fa-rocket me-2"></i> สมัครใช้งานเซิร์ฟเวอร์นี้
                        </button>
                    <?php else: ?>
                        <button class="btn btn-secondary w-100 rounded-pill fw-bold" disabled>
                            <i class="fas fa-lock me-2"></i> ปิดรับสมัครชั่วคราว
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="features" class="py-5" style="background: rgba(0,0,0,0.2);">
    <div class="container">
        <div class="row text-center g-4">
            <div class="col-md-4">
                <div class="feature-item">
                    <i class="fas fa-chart-line text-info fa-3x mb-3"></i>
                    <h6 class="text-white fs-5 fw-bold mb-2">ใช้งานง่ายตรวจสอบสถานะได้</h6>
                    <p class="text-white-50 small mb-0">ระบบออกแบบมาให้เป็นมิตร พร้อมเช็คสถานะการเชื่อมต่อแบบ Real-time
                    </p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-item">
                    <i class="fas fa-sliders-h text-warning fa-3x mb-3"></i>
                    <h6 class="text-white fs-5 fw-bold mb-2">ปรับเปลี่ยนได้ตามใจ ตามการใช้งาน</h6>
                    <p class="text-white-50 small mb-0">รองรับหลากหลาย Protocol ปรับจูนได้เองเพื่อให้เหมาะกับเน็ตของคุณ
                    </p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-item">
                    <i class="fas fa-hand-holding-usd text-success fa-3x mb-3"></i>
                    <h6 class="text-white fs-5 fw-bold mb-2">ราคาถูก ประหยัด</h6>
                    <p class="text-white-50 small mb-0">คุ้มค่าที่สุด เริ่มต้นใช้งานได้ทันที สบายกระเป๋า</p>
                </div>
            </div>
        </div>
    </div>
</section>

</div><!-- .landing-page -->

<?php include 'page/footer.php'; ?>
<script src="page/landing.js" defer></script>

<script>
    function viewServer(el) {
        document.querySelectorAll('.server-item').forEach(i => i.classList.remove('selected'));
        el.classList.add('selected');
        document.getElementById('pvName').textContent = el.dataset.name;
        document.getElementById('pvLoc').textContent = el.dataset.loc;
        document.getElementById('pvFlag').textContent = el.dataset.flag;
        document.getElementById('pvUsers').textContent = el.dataset.users;
        document.getElementById('pvPing').textContent = el.dataset.ping;
        document.getElementById('pvProto').textContent = el.dataset.proto;
    }

    const allowRegister = <?php echo $allow_register === '0' ? 'false' : 'true'; ?>;

    window.allowRegister = <?php echo $allow_register === '0' ? 'false' : 'true'; ?>;

    function gotoRegister() {
        if (!allowRegister) {
            if (window.Alert && Alert.info) {
                Alert.info('ปิดรับสมัครชั่วคราว', 'ระบบยังไม่เปิดให้สมัครสมาชิก');
            }
            return;
        }
        const registerCard = document.getElementById('registerCard');
        const loginCard = document.getElementById('login');
        if (registerCard && loginCard) {
            registerCard.style.display = 'block';
            loginCard.style.display = 'none';
            registerCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const first = document.querySelector('.server-item');
        if (first) viewServer(first);
    });
</script>
