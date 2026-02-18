<?php
// page/otp_verification.php
// V6.0 Modern UI with Segmented Inputs

// 1. (เดิม) ตรวจสอบ Session และดึงข้อมูล
if (session_status() == PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['temp_user_id'])) {
    header('Location: ?r=landing');
    exit;
}

// เชื่อมต่อ DB (ถ้ายังไม่ได้เชื่อมต่อจาก index)
if (!isset($conn)) {
    if (file_exists('controller/config.php')) require_once 'controller/config.php';
    elseif (file_exists('../controller/config.php')) require_once '../controller/config.php';
}

$user_id = $_SESSION['temp_user_id'];

// 2. (เดิม) ดึงข้อมูล email และเวลาหมดอายุ
$stmt = $conn->prepare("SELECT email, otp_expiry FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_otp_data = $result->fetch_assoc();
$stmt->close();

if (!$user_otp_data) {
    header('Location: ?r=landing&error=nouser');
    exit;
}

$email_to_display = $user_otp_data['email'];
$expiry_timestamp = strtotime($user_otp_data['otp_expiry']) * 1000;

// 3. โหลด Header/Navbar
include 'page/header.php'; 
include 'page/navbar.php';

// (เดิม) ซ่อนอีเมลบางส่วน
function mask_email($email) {
    list($user, $domain) = explode('@', $email);
    return substr($user, 0, 3) . str_repeat('*', strlen($user) - 3) . '@' . $domain;
}
?>

<style>
    /* --- Modern OTP Styles --- */
    :root {
        --bg-body: #0f172a;
        --card-bg: rgba(30, 41, 59, 0.7);
        --border: rgba(255,255,255,0.1);
        --accent: #6366f1;
        --accent-hover: #4f46e5;
        --text-main: #f8fafc;
        --text-sub: #94a3b8;
    }

    body {
        background-color: var(--bg-body);
        font-family: 'Prompt', sans-serif;
    }

    .otp-wrapper {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        position: relative;
        z-index: 1;
    }

    .nebula-bg {
        position: fixed; inset: 0; z-index: -1;
        background: radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.15) 0%, transparent 50%);
    }

    .otp-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--border);
        border-radius: 24px;
        padding: 40px 30px;
        width: 100%;
        max-width: 450px;
        text-align: center;
        box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        animation: slideUp 0.5s ease-out;
    }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .icon-box {
        width: 80px; height: 80px;
        background: linear-gradient(135deg, var(--accent), #ec4899);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 2rem; color: white;
        margin: 0 auto 20px;
        box-shadow: 0 0 20px rgba(99, 102, 241, 0.4);
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% { transform: scale(1); box-shadow: 0 0 20px rgba(99, 102, 241, 0.4); }
        50% { transform: scale(1.05); box-shadow: 0 0 30px rgba(99, 102, 241, 0.6); }
        100% { transform: scale(1); box-shadow: 0 0 20px rgba(99, 102, 241, 0.4); }
    }

    /* Input Fields (6 Boxes) */
    .otp-inputs {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin: 30px 0;
    }

    .otp-box {
        width: 50px; height: 60px;
        border-radius: 12px;
        background: rgba(15, 23, 42, 0.6);
        border: 2px solid var(--border);
        color: white;
        font-size: 1.5rem;
        font-weight: bold;
        text-align: center;
        transition: all 0.3s;
        outline: none;
    }

    .otp-box:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.2);
        background: rgba(15, 23, 42, 0.9);
        transform: translateY(-2px);
    }

    .btn-verify {
        background: linear-gradient(135deg, var(--accent), var(--accent-hover));
        border: none;
        padding: 12px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 1.1rem;
        letter-spacing: 0.5px;
        width: 100%;
        color: white;
        transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
    }

    .btn-verify:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(99, 102, 241, 0.5);
    }

    .timer-badge {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
        border: 1px solid rgba(245, 158, 11, 0.3);
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .resend-link {
        color: var(--text-sub);
        text-decoration: none;
        font-size: 0.9rem;
        cursor: pointer;
        transition: 0.3s;
    }
    .resend-link:hover { color: white; text-decoration: underline; }
    
    /* Mobile Responsiveness */
    @media (max-width: 400px) {
        .otp-inputs { gap: 5px; }
        .otp-box { width: 40px; height: 50px; font-size: 1.2rem; }
    }
</style>

<div class="nebula-bg"></div>

<div class="otp-wrapper">
    <div class="otp-card">
        <div class="icon-box">
            <i class="fas fa-shield-alt"></i>
        </div>

        <h3 class="text-white fw-bold mb-2">ยืนยันตัวตน</h3>
        <p class="text-muted small mb-4">
            กรุณากรอกรหัส 6 หลักที่เราส่งไปยังอีเมล<br>
            <span class="text-white font-monospace"><?php echo mask_email($email_to_display); ?></span>
        </p>

        <div id="timer-wrapper" class="mb-4">
            <div class="timer-badge">
                <i class="far fa-clock"></i> 
                หมดอายุใน <span id="otp-timer" class="fw-bold ms-1">00:00</span>
            </div>
        </div>

        <form id="otpForm" action="controller/validation_email_conf.php" method="POST">
            <input type="hidden" name="otp" id="actualOtpInput">
            
            <div class="otp-inputs">
                <input type="text" class="otp-box" maxlength="1" pattern="[0-9]" inputmode="numeric">
                <input type="text" class="otp-box" maxlength="1" pattern="[0-9]" inputmode="numeric">
                <input type="text" class="otp-box" maxlength="1" pattern="[0-9]" inputmode="numeric">
                <input type="text" class="otp-box" maxlength="1" pattern="[0-9]" inputmode="numeric">
                <input type="text" class="otp-box" maxlength="1" pattern="[0-9]" inputmode="numeric">
                <input type="text" class="otp-box" maxlength="1" pattern="[0-9]" inputmode="numeric">
            </div>

            <button type="submit" class="btn-verify mt-2" id="verifyBtn">
                ยืนยันรหัส
            </button>
        </form>

        <div class="mt-4">
            <p class="text-muted small mb-0">
                ไม่ได้รับรหัส? 
                <button id="resendBtn" class="btn btn-link resend-link p-0 border-0" style="display: none;">
                    ส่งรหัสอีกครั้ง
                </button>
                <span id="waitText" class="text-muted ms-1">รอสักครู่...</span>
            </p>
        </div>
    </div>
</div>

<?php include 'page/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- 1. Logic จัดการ Input 6 ช่อง ---
    const inputs = document.querySelectorAll('.otp-box');
    const hiddenInput = document.getElementById('actualOtpInput');
    const form = document.getElementById('otpForm');

    inputs.forEach((input, index) => {
        // เมื่อพิมพ์ตัวเลข
        input.addEventListener('input', (e) => {
            // รับเฉพาะตัวเลข
            e.target.value = e.target.value.replace(/[^0-9]/g, '');

            if (e.target.value.length === 1) {
                // ย้ายไปช่องถัดไป
                if (index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            }
            updateHiddenInput();
        });

        // จัดการปุ่ม Backspace และ Paste
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                inputs[index - 1].focus();
            }
        });

        // จัดการ Paste (วางรหัสทีเดียว)
        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasteData = e.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);
            pasteData.split('').forEach((char, i) => {
                if (inputs[i]) inputs[i].value = char;
            });
            updateHiddenInput();
            // Focus ช่องสุดท้ายที่มีข้อมูล
            const lastFilled = Math.min(pasteData.length, inputs.length) - 1;
            if (lastFilled >= 0) inputs[lastFilled].focus();
            
            // Auto submit ถ้าครบ
            if(pasteData.length === 6) form.dispatchEvent(new Event('submit'));
        });
    });

    function updateHiddenInput() {
        let otp = '';
        inputs.forEach(i => otp += i.value);
        hiddenInput.value = otp;
    }

    // --- 2. Timer & Logic เดิม ---
    let expiryTimestamp = <?php echo $expiry_timestamp; ?>;
    const timerElement = document.getElementById('otp-timer');
    const timerWrapper = document.getElementById('timer-wrapper');
    const resendBtn = document.getElementById('resendBtn');
    const waitText = document.getElementById('waitText');
    let countdownInterval;

    function startCountdown(expiryTime) {
        resendBtn.style.display = 'none';
        waitText.style.display = 'inline';
        timerWrapper.style.display = 'block';
        
        if (countdownInterval) clearInterval(countdownInterval);

        countdownInterval = setInterval(() => {
            const now = new Date().getTime();
            const distance = expiryTime - now;

            if (distance < 0) {
                clearInterval(countdownInterval);
                timerElement.innerText = "00:00";
                waitText.style.display = 'none';
                resendBtn.style.display = 'inline-block';
            } else {
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                timerElement.textContent = String(minutes).padStart(2, '0') + ":" + String(seconds).padStart(2, '0');
            }
        }, 1000);
    }

    // --- 3. Resend OTP ---
    resendBtn.addEventListener('click', (e) => {
        e.preventDefault();
        resendBtn.disabled = true;
        resendBtn.innerText = 'กำลังส่ง...';

        fetch('controller/resend_otp_conf.php', { method: 'POST' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'ส่งแล้ว!', text: 'รหัสชุดใหม่ถูกส่งไปที่อีเมลของคุณ', timer: 1500, showConfirmButton: false });
                startCountdown(data.new_expiry_timestamp);
                // Clear inputs
                inputs.forEach(i => i.value = '');
                inputs[0].focus();
            } else {
                Swal.fire('ผิดพลาด', data.message, 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'เชื่อมต่อล้มเหลว', 'error'))
        .finally(() => {
            resendBtn.disabled = false;
            resendBtn.innerText = 'ส่งรหัสอีกครั้ง';
        });
    });

    // --- 4. Handle Form Submit ---
    // เนื่องจาก action เป็น POST ไปไฟล์ PHP โดยตรง เราปล่อยให้มันทำงานตามปกติ
    // แต่ถ้าคุณอยากใช้ AJAX submit ก็สามารถแก้ตรงนี้ได้ แต่ตามโค้ดเดิมคือ Submit form ปกติ
    form.addEventListener('submit', (e) => {
        if(hiddenInput.value.length !== 6) {
            e.preventDefault();
            Swal.fire('แจ้งเตือน', 'กรุณากรอกรหัสให้ครบ 6 หลัก', 'warning');
        }
    });

    // --- 5. Handle URL Errors ---
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('error')) {
        const err = urlParams.get('error');
        let msg = 'เกิดข้อผิดพลาด';
        if (err === 'invalid_otp') msg = 'รหัสไม่ถูกต้อง ลองใหม่อีกครั้ง';
        if (err === 'expired_otp') msg = 'รหัสหมดอายุแล้ว กรุณาขอใหม่';
        Swal.fire('ผิดพลาด!', msg, 'error');
    }

    // Init
    startCountdown(expiryTimestamp);
    inputs[0].focus(); // Focus ช่องแรก
});
</script>