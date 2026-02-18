<?php
// home/userdtail.php

// --- [ 1. ส่วน PHP: ดึงข้อมูลผู้ใช้ ] ---
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, email, credit FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// ถ้าไม่พบข้อมูลผู้ใช้ ให้ logout
if (!$user) {
    header('Location: controller/logout.php');
    exit;
}

// --- [ 2. โหลดส่วนประกอบ ] ---
include 'home/header.php';
include 'home/navbar.php'; // (navbar.php จะใช้ตัวแปร $user)
?>

<!-- ========================================
   CSS STYLES (สำหรับหน้าตั้งค่าบัญชี)
   ======================================== -->
<style>
.account-container {
    /* ✅ [ปรับปรุง] ใช้ padding ที่ถูกต้อง แทน style="padding-top: 100px;" */
    padding: 30px 0 60px;
    min-height: 100vh;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
}

/* Breadcrumb (เหมือนหน้าอื่น) */
.breadcrumb-nav {
    margin-bottom: 1.5rem;
    padding: 0.75rem 1.25rem;
    background: rgba(30, 41, 59, 0.4);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.breadcrumb-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #94a3b8;
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    transition: color 0.3s ease;
}
.breadcrumb-item:hover { color: #6366f1; }
.breadcrumb-item.active { color: white; font-weight: 600; }
.breadcrumb-separator { color: #475569; font-size: 0.8rem; }

/* Page Header (เหมือนหน้าอื่น) */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1.5rem;
    padding: 2rem;
    background: rgba(30, 41, 59, 0.6);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}
.page-title-section { flex: 1; }
.page-title {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 2rem;
    font-weight: 800;
    color: white;
    margin: 0 0 0.5rem 0;
    letter-spacing: -0.5px;
}
.page-title i {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
    animation: iconFloat 3s ease-in-out infinite;
}
@keyframes iconFloat {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-5px); }
}
.page-subtitle {
    color: #94a3b8;
    font-size: 0.95rem;
    font-weight: 500;
    margin-left: 76px;
}

/* ✅ [ใหม่] Account Card (การ์ดสำหรับฟอร์ม) */
.account-card {
    background: rgba(30, 41, 59, 0.6);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 20px;
    padding: 2.5rem; /* เพิ่ม padding ให้นุ่มนวล */
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    margin-bottom: 2rem;
}

.card-title {
    color: white;
    font-weight: 700;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding-bottom: 1rem;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid rgba(99, 102, 241, 0.2);
}
.card-title i {
    color: #818cf8; /* สีไอคอนให้ซอฟต์ลง */
}

/* ✅ [ใหม่] Form Label (ป้ายกำกับฟอร์ม) */
.form-label {
    color: #cbd5e1;
    font-weight: 600;
    font-size: 0.95rem;
    margin-bottom: 0.5rem;
}

/* ✅ [ใหม่] Form Control (ช่องกรอก) - หัวใจของความ "เป็นทางการ" */
.form-control {
    background: rgba(15, 23, 42, 0.8) !important; /* พื้นหลังเทาดำ */
    border: 2px solid rgba(99, 102, 241, 0.3) !important; /* ขอบม่วงจางๆ */
    color: white !important;
    border-radius: 12px !important;
    padding: 0.75rem 1.25rem !important;
    font-weight: 500;
    transition: all 0.3s ease;
}
.form-control::placeholder {
    color: #64748b !important;
}
.form-control:focus {
    background: rgba(15, 23, 42, 0.9) !important;
    border-color: #6366f1 !important; /* ขอบม่วงชัด */
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2) !important; /* เงาเรืองแสง */
    color: white !important;
}
.form-control:disabled,
.form-control[readonly] {
    background: rgba(30, 41, 59, 0.5) !important; /* สีช่องที่ disabled */
    color: #64748b !important;
    border-color: rgba(99, 102, 241, 0.1) !important;
}

/* ✅ [ใหม่] Submit Button (ปุ่มบันทึก) */
.btn-submit-form {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border: none;
    padding: 0.85rem 1.75rem;
    border-radius: 12px;
    color: white;
    font-weight: 700;
    font-size: 1rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}
.btn-submit-form:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.5);
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
}
.btn-submit-form:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Responsive */
@media (max-width: 991px) {
    .account-container { padding: 40px 0 40px; }
    .page-header { padding: 1.5rem; }
    .page-title { font-size: 1.75rem; }
    .page-title i { width: 50px; height: 50px; font-size: 1.5rem; }
    .page-subtitle { margin-left: 66px; font-size: 0.85rem; }
}

@media (max-width: 768px) {
    .account-container { padding: 30px 0 30px; }
    .page-header { padding: 1.25rem; }
    .page-title { font-size: 1.5rem; }
    .page-title i { width: 45px; height: 45px; font-size: 1.25rem; }
    .page-subtitle { margin-left: 61px; font-size: 0.8rem; }
    .account-card { padding: 1.5rem; }
}

@media (max-width: 576px) {
    .page-header { padding: 1rem; }
    .page-title { font-size: 1.25rem; }
    .page-title i { width: 40px; height: 40px; font-size: 1.1rem; }
    .page-subtitle { margin-left: 56px; font-size: 0.75rem; }
    .account-card { padding: 1.25rem; }
}
</style>

<!-- ========================================
   HTML CONTENT
   ======================================== -->
<div class="account-container">
    <div class="container">
        
        <!-- ✅ [ใหม่] Breadcrumb -->
        <nav class="breadcrumb-nav">
            <a href="?p=home" class="breadcrumb-item">
                <i class="fas fa-home"></i>
                หน้าหลัก
            </a>
            <span class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></span>
            <span class="breadcrumb-item active">
                <i class="fas fa-user-cog"></i>
                ตั้งค่าบัญชี
            </span>
        </nav>

        <!-- ✅ [ใหม่] Page Header -->
        <div class="page-header">
            <div class="page-title-section">
                <h1 class="page-title">
                    <i class="fas fa-user-cog"></i>
                    ตั้งค่าบัญชี
                </h1>
                <p class="page-subtitle">จัดการข้อมูลส่วนตัวและความปลอดภัยของบัญชี</p>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                <!-- ✅ [ใหม่] การ์ดเปลี่ยนรหัสผ่าน -->
                <div class="account-card">
                    <h3 class="card-title"><i class="fas fa-key me-2"></i>เปลี่ยนรหัสผ่าน</h3>
                    <form id="changePasswordForm">
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-3">
                            <label class="form-label">รหัสผ่านปัจจุบัน</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">รหัสผ่านใหม่</label>
                            <input type="password" class="form-control" name="new_password" placeholder="อย่างน้อย 8 ตัวอักษร" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                            <input type="password" class="form-control" name="confirm_new_password" required>
                        </div>
                        <button type="submit" class="btn-submit-form w-100 mt-2">
                            <i class="fas fa-save me-2"></i>บันทึกรหัสผ่านใหม่
                        </button>
                    </form>
                </div>

                <!-- ✅ [ใหม่] การ์ดเปลี่ยนอีเมล -->
                <div class="account-card">
                    <h3 class="card-title"><i class="fas fa-envelope me-2"></i>เปลี่ยนอีเมล</h3>
                    <form id="changeEmailForm">
                        <input type="hidden" name="action" value="change_email">
                        <div class="mb-3">
                            <label class="form-label">อีเมลปัจจุบัน</label>
                            <!-- ใช้ class .form-control แต่เพิ่ม readonly -->
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">อีเมลใหม่</label>
                            <input type="email" class="form-control" name="new_email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ยืนยันรหัสผ่านปัจจุบัน (เพื่อความปลอดภัย)</label>
                            <input type="password" class="form-control" name="current_password_for_email" required>
                        </div>
                        <button type="submit" class="btn-submit-form w-100 mt-2">
                            <i class="fas fa-save me-2"></i>บันทึกอีเมลใหม่
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- ========================================
   JAVASCRIPT (ไม่เปลี่ยนแปลง logic)
   ======================================== -->
<script>
    // --- 1. JS สำหรับฟอร์มเปลี่ยนรหัสผ่าน ---
    document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const btn = form.querySelector('button[type="submit"]');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังตรวจสอบ...';
        btn.disabled = true;
        const formData = new FormData(form);

        fetch('controller/userdetail_conf.php', { 
            method: 'POST', 
            body: formData 
        })
        .then(response => response.text()) // อ่านเป็น text ก่อนเสมอ
        .then(text => {
            try {
                const data = JSON.parse(text); // ลอง parse JSON
                if (data.success) {
                    Swal.fire('สำเร็จ!', data.message, 'success');
                    form.reset(); 
                } else {
                    Swal.fire('ผิดพลาด!', data.message, 'error');
                }
            } catch (e) {
                // ถ้า parse JSON ล้มเหลว (แปลว่า PHP มี error)
                Swal.fire({
                    icon: 'error',
                    title: 'Server Error! (C-UDP)',
                    html: `<div style="text-align: left; background: #333; color: #ffcccc; padding: 10px; border-radius: 5px; max-height: 200px; overflow-y: auto;">${text}</div>`
                });
            }
        })
        .catch(error => { Swal.fire('ผิดพลาด!', 'เกิดข้อผิดพลาดในการเชื่อมต่อ (C-NET-Pass)', 'error'); })
        .finally(() => {
            btn.innerHTML = '<i class="fas fa-save me-2"></i>บันทึกรหัสผ่านใหม่';
            btn.disabled = false;
        });
    });

    // --- 2. JS สำหรับฟอร์มเปลี่ยนอีเมล (คง Debugger ไว้) ---
    document.getElementById('changeEmailForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const btn = form.querySelector('button[type="submit"]');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังตรวจสอบ...';
        btn.disabled = true;
        const formData = new FormData(form);

        fetch('controller/userdetail_conf.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            return response.text(); // อ่านค่าที่ Server ส่งมาเป็น "ข้อความ" (Text) ก่อน
        })
        .then(text => {
            try {
                // ถ้าสำเร็จ (Server ไม่พัง)
                const data = JSON.parse(text);
                
                if (data.success) {
                    if (data.force_logout) {
                        // หากมีการเปลี่ยนอีเมลที่ต้องยืนยันตัวตนใหม่
                        Swal.fire('สำเร็จ!', data.message, 'success').then(() => {
                            window.location.href = '?p=logout'; // สั่ง Logout
                        });
                    } else {
                        Swal.fire('สำเร็จ!', data.message, 'success');
                        // อัปเดตอีเมลที่แสดงในช่อง disabled (ถ้าจำเป็น)
                        // form.querySelector('input[type="email"][disabled]').value = data.new_email; 
                    }
                } else {
                    Swal.fire('ผิดพลาด!', data.message, 'error');
                }

            } catch (e) {
                // ถ้าแปลงเป็น JSON ล้มเหลว (แปลว่า Server พัง และ text คือ Error จริง)
                Swal.fire({
                    icon: 'error',
                    title: 'Server Error! (C-UDE)',
                    html: `<div style="text-align: left; background: #333; color: #ffcccc; padding: 10px; border-radius: 5px; max-height: 200px; overflow-y: auto;">${text}</div>`
                });
            }
        })
        .catch(error => {
            Swal.fire('ผิดพลาด!', 'เชื่อมต่อ Network ล้มเหลว (C-NET-Email)', 'error');
        })
        .finally(() => {
            btn.innerHTML = '<i class="fas fa-save me-2"></i>บันทึกอีเมลใหม่';
            btn.disabled = false;
        });
    });
</script>

<?php
// --- [ 3. โหลดส่วน Footer ] ---
include 'home/footer.php'; 
?>