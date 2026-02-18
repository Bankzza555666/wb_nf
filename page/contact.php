<?php 
// page/contact.php
include 'page/header.php'; 
include 'page/navbar.php'; 
?>

<style>
    :root {
        --bg-dark: #0f172a;
        --card-bg: rgba(30, 41, 59, 0.6);
        --accent: #6366f1;
    }
    .nebula-bg-contact {
        position: fixed; inset: 0; z-index: -1;
        background: radial-gradient(circle at 80% 10%, rgba(99, 102, 241, 0.15) 0%, transparent 40%),
                    radial-gradient(circle at 20% 90%, rgba(16, 185, 129, 0.1) 0%, transparent 40%);
    }
    .contact-card {
        background: var(--card-bg); backdrop-filter: blur(16px);
        border: 1px solid rgba(255,255,255,0.08); border-radius: 20px;
        padding: 40px; box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    }
    .contact-info-box {
        padding: 20px; border-radius: 12px; background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.05); margin-bottom: 15px;
        display: flex; align-items: center; gap: 15px; transition: transform 0.3s;
    }
    .contact-info-box:hover { transform: translateX(5px); background: rgba(99, 102, 241, 0.1); border-color: var(--accent); }
    .c-icon {
        width: 50px; height: 50px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem; color: #fff;
    }
    .form-control-dark {
        background: rgba(0,0,0,0.3) !important; border: 1px solid rgba(255,255,255,0.1) !important;
        color: #fff !important; border-radius: 10px; padding: 12px;
    }
    .form-control-dark:focus { background: rgba(0,0,0,0.5) !important; border-color: var(--accent) !important; color: #fff !important; box-shadow: none; }
</style>

<div class="nebula-bg-contact"></div>

<section class="hero-section" style="min-height: 100vh; align-items: flex-start; padding-top: 120px;">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-5">
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 mb-3">Support Center</span>
                <h1 class="fw-bold text-white mb-4">ติดต่อเรา <br><span class="text-gradient" style="background: linear-gradient(135deg, #6366f1, #10b981); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">NF~SHOP Support</span></h1>
                <p class="text-white-50 mb-5">
                    มีปัญหาการใช้งาน เติมเงินไม่เข้า หรือต้องการสอบถามข้อมูลเพิ่มเติม? 
                    ทีมงานของเราพร้อมช่วยเหลือคุณตลอดเวลาผ่านช่องทางด้านล่างนี้
                </p>

                <a href="https://line.me/ti/p/~YOUR_LINE_ID" target="_blank" class="text-decoration-none">
                    <div class="contact-info-box">
                        <div class="c-icon" style="background: #06c755;"><i class="fab fa-line"></i></div>
                        <div>
                            <h6 class="text-white fw-bold mb-0">Line Official</h6>
                            <small class="text-white-50">@NF~SHOP_Support (ตอบไวสุด)</small>
                        </div>
                    </div>
                </a>

                <a href="https://facebook.com/YOUR_PAGE" target="_blank" class="text-decoration-none">
                    <div class="contact-info-box">
                        <div class="c-icon" style="background: #1877f2;"><i class="fab fa-facebook-f"></i></div>
                        <div>
                            <h6 class="text-white fw-bold mb-0">Facebook Page</h6>
                            <small class="text-white-50">NF~SHOP Thailand</small>
                        </div>
                    </div>
                </a>

                <div class="contact-info-box">
                    <div class="c-icon" style="background: #ea4335;"><i class="fas fa-envelope"></i></div>
                    <div>
                        <h6 class="text-white fw-bold mb-0">Email Support</h6>
                        <small class="text-white-50">support@NF~SHOP.com</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="contact-card fade-in">
                    <h4 class="text-white fw-bold mb-4"><i class="fas fa-paper-plane me-2 text-primary"></i>ส่งข้อความถึงแอดมิน</h4>
                    
                    <form id="contactForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="text-white-50 small mb-1">ชื่อของคุณ</label>
                                <input type="text" name="name" class="form-control form-control-dark" required placeholder="ระบุชื่อเล่น หรือ Username">
                            </div>
                            <div class="col-md-6">
                                <label class="text-white-50 small mb-1">อีเมลตอบกลับ</label>
                                <input type="email" name="email" class="form-control form-control-dark" required placeholder="name@example.com">
                            </div>
                            <div class="col-12">
                                <label class="text-white-50 small mb-1">หัวข้อเรื่อง</label>
                                <select name="subject" class="form-control form-control-dark">
                                    <option value="General">สอบถามทั่วไป</option>
                                    <option value="Payment">แจ้งปัญหาการชำระเงิน/เติมเงิน</option>
                                    <option value="Technical">แจ้งปัญหาการเชื่อมต่อ VPN</option>
                                    <option value="Other">อื่นๆ</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="text-white-50 small mb-1">ข้อความ</label>
                                <textarea name="message" class="form-control form-control-dark" rows="5" required placeholder="รายละเอียดปัญหา หรือสิ่งที่ต้องการสอบถาม..."></textarea>
                            </div>
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-3">
                                    <i class="fas fa-paper-plane me-2"></i>ส่งข้อความ
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'page/footer.php'; ?>

<script>
document.getElementById('contactForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const oldHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังส่ง...';
    btn.disabled = true;

    const formData = new FormData(this);

    fetch('controller/contact_db.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            Swal.fire('สำเร็จ!', data.message, 'success');
            this.reset();
        } else {
            Swal.fire('ขออภัย', data.message, 'error');
        }
    })
    .catch(err => {
        Swal.fire('Error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ (C-ERR)', 'error');
    })
    .finally(() => {
        btn.innerHTML = oldHtml;
        btn.disabled = false;
    });
});
</script>