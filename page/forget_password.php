<?php
// page/forget_password.php

// 1. โหลดส่วนประกอบ
include 'page/header.php'; 
include 'page/navbar.php';
?>

<div class="hero-section"> 
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                
                <div class="login-card active"> 
                    <h3 class="text-center mb-4"><i class="fas fa-key me-2"></i>ลืมรหัสผ่าน</h3>
                    <p class="text-center text-white-50 mb-4">
                        กรอกอีเมลที่ผูกกับบัญชีของคุณ เราจะส่งลิงก์สำหรับรีเซ็ตรหัสผ่านไปให้
                    </p>
                    
                    <form id="forgotPasswordForm">
                        <div class="mb-3">
                            <label class="form-label">อีเมล</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-paper-plane me-2"></i>ส่งคำขอ
                        </button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <a href="?r=landing" class="text-decoration-none" style="color: var(--primary-color);">
                            <i class="fas fa-arrow-left me-2"></i>กลับไปหน้าเข้าสู่ระบบ
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// 2. โหลดส่วน Footer
include 'page/footer.php'; 
?>

<script>
    document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const btn = form.querySelector('button[type="submit"]');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังส่ง...';
        btn.disabled = true;

        const formData = new FormData(form);

        fetch('controller/forgot_password_conf.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text()) // 1. อ่านเป็น Text (เหมือนตอน Debug)
        .then(text => {
            // 2. (ใหม่) พยายาม "ค้นหา" JSON ที่ซ่อนอยู่ใน Text
            const jsonStart = text.indexOf('{');
            const jsonEnd = text.lastIndexOf('}');

            if (jsonStart === -1 || jsonEnd === -1) {
                // ถ้าหาไม่เจอเลย (แปลว่า Server Crash จริง)
                throw new Error("Server response is not JSON: " + text);
            }

            // 3. (ใหม่) "ตัด" เฉพาะส่วนที่เป็น JSON ออกมา
            const jsonString = text.substring(jsonStart, jsonEnd + 1);

            try {
                // 4. พยายามแปลง JSON ที่เรา "ตัด" ออกมา
                const data = JSON.parse(jsonString); 
                
                if (data.success) {
                    Swal.fire('ส่งสำเร็จ!', data.message, 'success');
                    form.reset();
                } else {
                    Swal.fire('ผิดพลาด!', data.message, 'error');
                }
            } catch (e) {
                // (ถ้ายังพัง) แสดงข้อความดิบๆ (เหมือนตอน Debug)
                Swal.fire({
                    icon: 'error',
                    title: 'Server Error! (C-FGP)',
                    html: `<div style="text-align: left; background: #333; color: #ffcccc; padding: 10px; border-radius: 5px; max-height: 200px; overflow-y: auto;">${text}</div>`
                });
            }
        })
        .catch(error => {
             // (อันนี้คือ Network พัง หรือ throw new Error ด้านบน)
             Swal.fire('ผิดพลาด!', 'เกิดข้อผิดพลาดในการเชื่อมต่อ (C-FGP)', 'error');
        })
        .finally(() => {
            btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>ส่งคำขอ';
            btn.disabled = false;
        });
    });
</script>