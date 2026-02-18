<?php
// page/reset_password.php

// 1. ตรวจสอบ Token ก่อน
$token_is_valid = false;
$user_token = $_GET['token'] ?? '';

if (!empty($user_token)) {
    // (ตรวจสอบว่า Token ถูกต้อง และยังไม่หมดอายุ)
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expiry > NOW()");
    $stmt->bind_param("s", $user_token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $token_is_valid = true;
    }
    $stmt->close();
}

// 2. โหลดส่วนประกอบ
include 'page/header.php'; 
include 'page/navbar.php';
?>

<div class="hero-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="login-card active"> 

                    <?php if ($token_is_valid): ?>
                        <h3 class="text-center mb-4"><i class="fas fa-key me-2"></i>ตั้งรหัสผ่านใหม่</h3>
                        <form id="resetPasswordForm">
                            
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($user_token); ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">รหัสผ่านใหม่</label>
                                <input type="password" class="form-control" name="new_password" placeholder="อย่างน้อย 8 ตัวอักษร" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                                <input type="password" class="form-control" name="confirm_new_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i>บันทึกรหัสผ่านใหม่
                            </button>
                        </form>
                    
                    <?php else: ?>
                        <h3 class="text-center mb-4 text-danger"><i class="fas fa-times-circle me-2"></i>ลิงก์ไม่ถูกต้อง</h3>
                        <p class="text-center text-white-50">
                            ลิงก์สำหรับรีเซ็ตรหัสผ่านนี้ไม่ถูกต้อง หรือ หมดอายุการใช้งานแล้ว
                        </p>
                        <div class="text-center mt-3">
                            <a href="?r=foget" class="btn btn-outline-light w-100">
                                <i class="fas fa-arrow-left me-2"></i>ขอลลิงก์ใหม่อีกครั้ง
                            </a>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<?php
// 4. โหลดส่วน Footer
include 'page/footer.php'; 
?>

<?php if ($token_is_valid): ?>
<script>
    document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const btn = form.querySelector('button[type="submit"]');
        
        const newPass = form.querySelector('input[name="new_password"]').value;
        const confirmPass = form.querySelector('input[name="confirm_new_password"]').value;

        if (newPass.length < 8) { Swal.fire('ผิดพลาด!', 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร', 'warning'); return; }
        if (newPass !== confirmPass) { Swal.fire('ผิดพลาด!', 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน', 'warning'); return; }

        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังบันทึก...';
        btn.disabled = true;
        
        const formData = new FormData(form);

        // (ยิงไปที่ "reset_password_conf.php")
        fetch('controller/reset_password_conf.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json()) 
        .then(data => {
            if (data.success) {
                Swal.fire('สำเร็จ!', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว! กรุณาเข้าสู่ระบบอีกครั้ง', 'success')
                .then(() => {
                    window.location.href = '?r=landing'; // เด้งไปหน้า Login
                });
            } else {
                Swal.fire('ผิดพลาด!', data.message, 'error');
                btn.innerHTML = '<i class="fas fa-save me-2"></i>บันทึกรหัสผ่านใหม่';
                btn.disabled = false;
            }
        })
        .catch(error => Swal.fire('ผิดพลาด!', 'เกิดข้อผิดพลาดในการเชื่อมต่อ (C-RSP)', 'error'));
    });
</script>
<?php endif; ?>