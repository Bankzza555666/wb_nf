<?php
// home/topup.php
// V6.4 Final: Wording Update (Best Seller -> Popular) & TrueMoney Image Fixed

// --- [ส่วน PHP: ดึงข้อมูลผู้ใช้และแพ็คเกจ] ---

if (!isset($_SESSION['user_id'])) {
    header('Location: ?p=login');
    exit;
}

$user_id = $_SESSION['user_id'];

// ดึงข้อมูลผู้ใช้
$stmt = $conn->prepare("SELECT username, email, credit FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: controller/logout.php');
    exit;
}

// ดึงแพ็คเกจ
$packages = [];
$stmt = $conn->prepare("SELECT * FROM topup_packages WHERE is_active = 1 ORDER BY sort_order ASC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $packages[] = $row;
}
$stmt->close();

// --- [ส่วน HTML] ---
include 'home/header.php';
include 'home/navbar.php';
?>

<div class="topup-container">
    <div class="container">

        <nav class="breadcrumb-nav fade-in">
            <a href="?p=home" class="breadcrumb-item">
                <i class="fas fa-home"></i> หน้าหลัก
            </a>
            <span class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></span>
            <span class="breadcrumb-item active">เติมเงิน</span>
        </nav>

        <div class="row justify-content-center">
            <div class="col-lg-9">

                <div class="text-center mb-5 fade-in">
                    <h1 class="fw-bold text-white mb-2 display-6">เติมเงินเข้าระบบ</h1>
                    <p class="text-white-50">เลือกจำนวนเงินและช่องทางที่สะดวกที่สุด</p>

                    <div class="d-flex justify-content-center gap-3 mt-3">
                        <button class="btn btn-outline-info rounded-pill px-4 btn-sm" data-bs-toggle="modal"
                            data-bs-target="#tutorialModal">
                            <i class="fas fa-book-open me-2"></i>คู่มือการเติมเงิน
                        </button>
                        <a href="?p=topup_history" class="btn btn-outline-secondary rounded-pill px-4 btn-sm">
                            <i class="fas fa-history me-2"></i>ประวัติรายการ
                        </a>
                    </div>
                </div>

                <div class="credit-card-box fade-in mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-white-50 small mb-1">ยอดเงินคงเหลือ</div>
                            <div class="balance-amount">฿<?php echo number_format($user['credit'], 2); ?></div>
                        </div>
                        <div class="balance-icon"><i class="fas fa-wallet"></i></div>
                    </div>
                    <div class="mt-3 pt-3 border-top border-white border-opacity-10">
                        <small class="text-white-50"><i class="fas fa-user-circle me-1"></i>
                            <?php echo htmlspecialchars($user['username']); ?></small>
                    </div>
                </div>

                <form id="topupForm" method="POST" class="fade-in">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

                    <div class="section-card mb-4">
                        <h5 class="section-title"><i class="fas fa-coins me-2 text-warning"></i>เลือกจำนวนเงิน</h5>

                        <div class="row g-3">
                            <?php foreach ($packages as $pkg): ?>
                                <div class="col-6 col-md-4">
                                    <label class="pkg-radio">
                                        <input type="radio" name="package_id" value="<?php echo $pkg['id']; ?>"
                                            data-amount="<?php echo $pkg['amount']; ?>"
                                            data-bonus="<?php echo $pkg['bonus']; ?>" required>
                                        <div class="pkg-content">
                                            <?php if ($pkg['is_popular']): ?>
                                                <div class="pkg-badge"><i class="fas fa-fire me-1"></i>ยอดนิยม</div>
                                            <?php endif; ?>
                                            <div class="pkg-price">฿<?php echo number_format($pkg['amount'], 0); ?></div>
                                            <?php if ($pkg['bonus'] > 0): ?>
                                                <div class="pkg-bonus text-success">+โบนัส
                                                    ฿<?php echo number_format($pkg['bonus'], 0); ?></div>
                                            <?php else: ?>
                                                <div class="pkg-bonus text-muted">-</div>
                                            <?php endif; ?>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-3">
                            <label class="pkg-radio custom-mode">
                                <input type="radio" name="package_id" id="customAmount" value="custom">
                                <div class="pkg-content d-flex align-items-center justify-content-between px-4 py-3">
                                    <div class="text-start">
                                        <div class="fw-bold text-white"><i class="fas fa-pen me-2"></i>ระบุจำนวนเงินเอง
                                        </div>
                                        <small class="text-white-50">ขั้นต่ำ 10 บาท</small>
                                    </div>
                                    <i class="fas fa-chevron-right text-white-50"></i>
                                </div>
                            </label>

                            <div id="customAmountInput" class="mt-3 p-3 rounded-3"
                                style="display: none; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1);">
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-secondary text-white">฿</span>
                                    <input type="number" class="form-control bg-dark text-white border-secondary"
                                        name="custom_amount" min="10" step="1" placeholder="ระบุจำนวนเงิน">
                                </div>
                            </div>
                        </div>

                        <div class="summary-box mt-4" id="amountSummary" style="display: none;">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-white-50">ยอดเติม</span>
                                <span class="text-white fw-bold" id="displayAmount">฿0</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2" id="bonusRow" style="display: none;">
                                <span class="text-success"><i class="fas fa-gift me-1"></i>โบนัส</span>
                                <span class="text-success fw-bold" id="displayBonus">+฿0</span>
                            </div>
                            <div
                                class="border-top border-secondary my-2 pt-2 d-flex justify-content-between align-items-center">
                                <span class="text-white">รวมชำระ</span>
                                <span class="fs-4 fw-bold text-primary" id="totalAmount">฿0</span>
                            </div>
                        </div>
                    </div>

                    <div class="section-card mb-4">
                        <h5 class="section-title"><i class="fas fa-credit-card me-2 text-info"></i>ช่องทางการชำระเงิน
                        </h5>

                        <div class="row g-3">
                            <div class="col-6">
                                <label class="payment-radio">
                                    <input type="radio" name="payment_method" value="promptpay" required>
                                    <div class="pay-content">
                                        <img src="https://upload.wikimedia.org/wikipedia/commons/c/c5/PromptPay-logo.png"
                                            alt="PromptPay"
                                            style="height: 30px; object-fit: contain; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));"
                                            class="mb-2">
                                        <div class="pay-name">สแกน QR Code</div>
                                        <div class="pay-desc">รองรับทุกธนาคาร</div>
                                    </div>
                                </label>
                            </div>
                            <div class="col-6">
                                <label class="payment-radio">
                                    <input type="radio" name="payment_method" value="truemoney" required>
                                    <div class="pay-content">
                                        <img src="https://f.ptcdn.info/035/054/000/oxg367902Xyvy63Fxjv-o.png"
                                            alt="TrueMoney"
                                            style="height: 30px; object-fit: contain; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));"
                                            class="mb-2">
                                        <div class="pay-name">TrueMoney</div>
                                        <div class="pay-desc">ทรูมันนี่วอเล็ต</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-main w-100 py-3 fs-5 fw-bold shadow-lg" id="submitBtn"
                        disabled>
                        <i class="fas fa-lock me-2"></i>ยืนยันการชำระเงิน
                    </button>

                </form>

                <div class="text-center mt-4 text-white-50 small">
                    <i class="fas fa-shield-alt me-1"></i> ปลอดภัยด้วยมาตรฐาน SSL และระบบอัตโนมัติ
                </div>

            </div>
        </div>
    </div>

    <div class="modal fade" id="tutorialModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content bg-dark border-secondary text-white">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="fas fa-book me-2 text-primary"></i>วิธีการเติมเงิน</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">

                    <div class="step-box mb-4">
                        <div class="step-header mb-3">
                            <div class="step-num">1</div>
                            <h5 class="mb-0">เลือกยอดและช่องทาง</h5>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <div class="img-wrapper"><img src="./img/t0.png" class="img-fluid"></div>
                            </div>
                            <div class="col-6">
                                <div class="img-wrapper"><img src="./img/t1.png" class="img-fluid"></div>
                            </div>
                        </div>
                        <p class="text-white-50 small ms-5">เลือกจำนวนเงินที่ต้องการ และเลือกช่องทางชำระเงิน (PromptPay
                            หรือ True Wallet) และกด "ยืนยันการชำระเงิน"</p>
                    </div>
                    <hr class="border-secondary opacity-25 my-4">

                    <div class="step-box mb-4">
                        <div class="step-header mb-3">
                            <div class="step-num bg-warning text-dark">2</div>
                            <h5 class="mb-0">สแกนจ่าย / จ่ายด้วยวอเล็ต</h5>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <div class="img-wrapper"><img src="./img/t22.png" class="img-fluid"></div>
                            </div>
                            <div class="col-6">
                                <div class="img-wrapper"><img src="./img/t3.png" class="img-fluid"></div>
                            </div>
                        </div>
                        <p class="text-white-50 small ms-5">- พร้อมเพย์ ระบบจะแสดง QR Code
                            ให้ทำการแสกนจ่ายตามยอดเงินที่เลือกไว้</p>
                        <p class="text-white-50 small ms-5">- True Wallet ระบบจะแสดงพาไปหน้าชำระเงิน<br>
                            กรอกเบอร์ทรูวอร์วอเล็ต กดปุ่ม GET OTP นำเลข OTP มากกรอกและกดปุ่มยืนยัน</p>
                    </div>
                    <hr class="border-secondary opacity-25 my-4">

                    <div class="step-box">
                        <div class="step-header mb-3">
                            <div class="step-num bg-success">3</div>
                            <h5 class="mb-0">รับเครดิตอัตโนมัติ</h5>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <div class="img-wrapper"><img src="./img/t4.png" class="img-fluid"></div>
                            </div>
                            <div class="col-6">
                                <div class="img-wrapper"><img src="./img/t55.png" class="img-fluid"></div>
                            </div>
                        </div>
                        <p class="text-white-50 small ms-5">เมื่อโอนเงินสำเร็จ ระบบจะตรวจสอบและเติมเครดิตให้ทันที</p>
                        <p class="text-white-50 small ms-5">หากดำเนินการสำเร็จแล้วแต่เครดิตยังไม่เข้า </p>
                        <p class="text-white-50 small ms-5">- กดที่ปุ่มการแจ้งเตือนเลือกรายการของท่าน และกดดำเนินการต่อ
                        </p>
                        <p class="text-white-50 small ms-5">- หรือ กดที่ปุ่มแชทเพื่อแจ้งผู้ดูแลระบบ</p>
                    </div>

                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">ตกลง</button>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include 'home/footer.php'; ?>

<style>
    :root {
        --card-bg: rgba(10, 10, 10, 0.8);
        --border: rgba(229, 9, 20, 0.2);
        --primary: #E50914;
        --primary-hover: #b20710;
    }

    .topup-container {
        padding: 30px 0 100px;
        min-height: 100vh;
        background: var(--bg-body, #000000);
    }

    .breadcrumb-nav {
        background: var(--card-bg);
        backdrop-filter: blur(10px);
        padding: 10px 20px;
        border-radius: 50px;
        border: 1px solid var(--border);
        display: inline-flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 30px;
    }

    .breadcrumb-item {
        color: #aaaaaa;
        text-decoration: none;
        font-size: 0.9rem;
    }

    .breadcrumb-item.active {
        color: #fff;
        font-weight: 600;
    }

    .breadcrumb-separator {
        color: #475569;
        font-size: 0.8rem;
    }

    .credit-card-box {
        background: linear-gradient(135deg, #E50914, #B20710);
        border-radius: 20px;
        padding: 25px;
        color: white;
        box-shadow: 0 10px 30px rgba(229, 9, 20, 0.3);
        position: relative;
        overflow: hidden;
    }

    .credit-card-box::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 60%);
        pointer-events: none;
    }

    .balance-amount {
        font-size: 2.5rem;
        font-weight: 800;
        line-height: 1;
    }

    .balance-icon {
        font-size: 3rem;
        opacity: 0.2;
    }

    .section-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 25px;
    }

    .section-title {
        font-weight: 700;
        color: white;
        margin-bottom: 20px;
        font-size: 1.1rem;
    }

    .pkg-radio,
    .payment-radio {
        display: block;
        cursor: pointer;
        position: relative;
        height: 100%;
    }

    .pkg-radio input,
    .payment-radio input {
        position: absolute;
        opacity: 0;
    }

    .pkg-content,
    .pay-content {
        background: rgba(20, 20, 20, 0.6);
        border: 2px solid var(--border);
        border-radius: 16px;
        padding: 20px 10px;
        text-align: center;
        transition: all 0.2s;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }

    .pkg-radio input:checked+.pkg-content,
    .payment-radio input:checked+.pay-content {
        border-color: var(--primary);
        background: rgba(229, 9, 20, 0.1);
        box-shadow: 0 4px 20px rgba(229, 9, 20, 0.2);
        transform: translateY(-3px);
    }

    .pkg-radio:hover .pkg-content,
    .payment-radio:hover .pay-content {
        border-color: rgba(229, 9, 20, 0.5);
    }

    .pkg-price {
        font-size: 1.5rem;
        font-weight: 800;
        color: white;
    }

    .pkg-bonus {
        font-size: 0.85rem;
        font-weight: 500;
    }

    .pkg-badge {
        position: absolute;
        top: -10px;
        right: 10px;
        background: linear-gradient(45deg, #E50914, #ff4d4d);
        color: #fff;
        font-size: 0.7rem;
        font-weight: 800;
        padding: 3px 10px;
        border-radius: 20px;
        box-shadow: 0 2px 10px rgba(229, 9, 20, 0.4);
    }

    .pay-name {
        font-weight: 700;
        color: white;
        margin-bottom: 2px;
    }

    .pay-desc {
        font-size: 0.8rem;
        color: #aaaaaa;
    }

    .btn-main {
        background: linear-gradient(135deg, var(--primary), var(--primary-hover));
        border: none;
        border-radius: 16px;
        color: white;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .btn-main:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(229, 9, 20, 0.4);
    }

    .btn-main:disabled {
        background: #333;
        color: #666;
        cursor: not-allowed;
    }

    .summary-box {
        background: rgba(229, 9, 20, 0.1);
        border: 1px solid rgba(229, 9, 20, 0.3);
        border-radius: 12px;
        padding: 15px;
    }

    .step-box {
        position: relative;
    }

    .step-header {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .step-num {
        width: 35px;
        height: 35px;
        background: var(--primary);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .img-wrapper {
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid var(--border);
        background: #0f172a;
        aspect-ratio: 16/9;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .img-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s;
    }

    .fade-in {
        animation: fadeIn 0.5s ease forwards;
        opacity: 0;
        transform: translateY(20px);
    }

    @keyframes fadeIn {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('topupForm');
        const radios = document.querySelectorAll('input[name="package_id"]');
        const customRadio = document.getElementById('customAmount');
        const customInputDiv = document.getElementById('customAmountInput');
        const customInputField = document.querySelector('input[name="custom_amount"]');
        const payRadios = document.querySelectorAll('input[name="payment_method"]');
        const submitBtn = document.getElementById('submitBtn');
        const summaryBox = document.getElementById('amountSummary');
        const dispAmount = document.getElementById('displayAmount');
        const dispBonus = document.getElementById('displayBonus');
        const bonusRow = document.getElementById('bonusRow');
        const dispTotal = document.getElementById('totalAmount');

        let amount = 0, bonus = 0, method = '';

        function updateUI() {
            if (customRadio.checked) customInputDiv.style.display = 'block';
            else customInputDiv.style.display = 'none';

            if (amount > 0) {
                summaryBox.style.display = 'block';
                dispAmount.textContent = `฿${amount.toLocaleString()}`;
                dispTotal.textContent = `฿${amount.toLocaleString()}`;
                if (bonus > 0) {
                    bonusRow.style.display = 'flex';
                    dispBonus.textContent = `+฿${bonus.toLocaleString()}`;
                } else { bonusRow.style.display = 'none'; }
            } else { summaryBox.style.display = 'none'; }

            submitBtn.disabled = !(amount >= 10 && method !== '');
        }

        radios.forEach(r => {
            r.addEventListener('change', () => {
                if (r.value === 'custom') {
                    amount = parseFloat(customInputField.value) || 0;
                    bonus = 0;
                } else {
                    amount = parseFloat(r.dataset.amount);
                    bonus = parseFloat(r.dataset.bonus) || 0;
                }
                updateUI();
            });
        });

        customInputField.addEventListener('input', (e) => {
            if (customRadio.checked) {
                amount = parseFloat(e.target.value) || 0;
                updateUI();
            }
        });

        payRadios.forEach(p => {
            p.addEventListener('change', () => {
                method = p.value;
                updateUI();
            });
        });

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังสร้างรายการ...';

            const fd = new FormData(form);
            fetch('controller/topup_conf.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success && d.payment_url) {
                        Swal.fire({ icon: 'success', title: 'สำเร็จ', text: 'กำลังพาท่านไปชำระเงิน...', timer: 1500, showConfirmButton: false })
                            .then(() => window.location.href = d.payment_url);
                    } else {
                        Swal.fire('ผิดพลาด', d.message, 'error');
                        submitBtn.innerHTML = '<i class="fas fa-lock me-2"></i>ยืนยันการชำระเงิน';
                        submitBtn.disabled = false;
                    }
                })
                .catch(err => {
                    Swal.fire('Error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
                    submitBtn.disabled = false;
                });
        });
    });
</script>