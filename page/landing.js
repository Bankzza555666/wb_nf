document.addEventListener('DOMContentLoaded', function() {

    const loginCard = document.getElementById('login');
    const registerCard = document.getElementById('registerCard');
    const showRegisterLink = document.getElementById('showRegisterLink');
    const showLoginLink = document.getElementById('showLoginLink');
    const ctaRegister = document.getElementById('ctaRegister');
    const ctaLogin = document.getElementById('ctaLogin');

    // --- 1. ตัวสลับการ์ด ---
    if (showRegisterLink) {
        showRegisterLink.addEventListener('click', (e) => {
            e.preventDefault();
            if (window.allowRegister === false) {
                if (window.Alert && Alert.info) {
                    Alert.info('ปิดรับสมัครชั่วคราว', 'ระบบยังไม่เปิดให้สมัครสมาชิก');
                }
                return;
            }
            if (loginCard && registerCard) {
                loginCard.style.display = 'none';
                loginCard.classList.remove('active');
                registerCard.style.display = 'block';
                registerCard.classList.add('active');
            }
        });
    }
    if (showLoginLink) {
        showLoginLink.addEventListener('click', (e) => {
            e.preventDefault();
            registerCard.style.display = 'none';
            registerCard.classList.remove('active');
            loginCard.style.display = 'block';
            loginCard.classList.add('active');
        });
    }

    // --- 2. Smooth scrolling ---
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const href = this.getAttribute('href');
            const target = document.querySelector(href);

            if (!target) return;

            if (href === '#registerCard' || this.id === 'ctaRegister' || this.id === 'showRegisterLink') {
                if (window.allowRegister === false) {
                    if (window.Alert && Alert.info) {
                        Alert.info('ปิดรับสมัครชั่วคราว', 'ระบบยังไม่เปิดให้สมัครสมาชิก');
                    }
                    return;
                }
                if (loginCard && registerCard) {
                    loginCard.style.display = 'none';
                    loginCard.classList.remove('active');
                    registerCard.style.display = 'block';
                    registerCard.classList.add('active');
                }
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } 
            else if (href === '#login' || this.id === 'ctaLogin' || this.id === 'showLoginLink') {
                registerCard.style.display = 'none';
                registerCard.classList.remove('active');
                loginCard.style.display = 'block';
                loginCard.classList.add('active');
                loginCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            else if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // --- 3. Fade-in animations ---
    const observerOptions = { threshold: 0.1, rootMargin: '0px 0px -50px 0px' };
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    document.querySelectorAll('.fade-in').forEach(el => { observer.observe(el); });


    // --- 4. Form submission (Login) ---
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังเข้าสู่ระบบ...';
            btn.disabled = true;
            const formData = new FormData(this);
            
            fetch('controller/login_conf.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        if (data.verified) {
                            Alert.success('เข้าสู่ระบบสำเร็จ!', 'กำลังพาคุณเข้าสู่ระบบ...')
                            .then(() => { 
                                // ✅ แก้ตรงนี้: ส่งไป index.php เฉยๆ ให้ Router แยกแอดมิน/ยูสเซอร์เอง
                                window.location.href = 'index.php'; 
                            });
                        } else {
                            Alert.info('รอการยืนยัน!', 'กำลังพาคุณไปยังหน้ายืนยัน OTP...', { timer: 1500, showConfirmButton: false })
                                .then(() => { window.location.href = '?r=otp'; });
                        }
                    } else {
                        Alert.error('ล้มเหลว!', data.message);
                    }
                } catch (e) {
                    Alert.error('Server Error!', '', {
                        html: `<div style="text-align: left; background: #333; color: #ffcccc; padding: 10px; border-radius: 5px; max-height: 200px; overflow-y: auto;">${text}</div>`
                    });
                }
            })
            .catch(error => {
                Alert.error('ผิดพลาด!', 'เกิดข้อผิดพลาด Network');
            })
            .finally(() => {
                btn.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>เข้าสู่ระบบ';
                btn.disabled = false;
            });
        });
    }

    // --- 5. Form submission (Register) ---
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const password = this.querySelector('input[name="password_reg"]').value;
            const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
            if (password !== confirmPassword) { Alert.warning('ผิดพลาด!', 'รหัสผ่านไม่ตรงกัน!'); return; }
            if (password.length < 8) { Alert.warning('ผิดพลาด!', 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร'); return; }

            const btn = this.querySelector('button[type="submit"]');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังสมัคร...';
            btn.disabled = true;
            const formData = new FormData(this);
            
            fetch('controller/register_conf.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        Alert.success('สำเร็จ!', 'สมัครสมาชิกเรียบร้อย! กรุณาตรวจสอบอีเมลเพื่อยืนยันตัวตน');
                        if (showLoginLink) showLoginLink.click(); 
                    } else {
                        Alert.error('ล้มเหลว!', data.message);
                    }
                } catch (e) {
                    Alert.error('Server Error!', '', {
                        html: `<div style="text-align: left; background: #333; color: #ffcccc; padding: 10px; border-radius: 5px; max-height: 200px; overflow-y: auto;">${text}</div>`
                    });
                }
            })
            .catch(error => {
                Alert.error('ผิดพลาด!', 'เกิดข้อผิดพลาด Network');
            })
            .finally(() => {
                btn.innerHTML = '<i class="fas fa-check me-2"></i>ยืนยันการสมัคร';
                btn.disabled = false;
            });
        });
    }

    // --- 6. Particle effect ---
    document.addEventListener('mousemove', (e) => {
        const shapes = document.querySelectorAll('.floating-shape');
        const mouseX = e.clientX / window.innerWidth;
        const mouseY = e.clientY / window.innerHeight;
        shapes.forEach((shape, index) => {
            const speed = (index + 1) * 20;
            const x = (1 - mouseX) * speed - (speed / 2);
            const y = (1 - mouseY) * speed - (speed / 2);
            shape.style.transform = `translate(${x}px, ${y}px)`;
        });
    });

    // --- 7. Typing effect ---
    const heroTitle = document.querySelector('.hero-title');
    if (heroTitle) {
        const textContentMatch = heroTitle.textContent.match(/NF~SHOP Cloud Storage/);
        if (textContentMatch) {
            const textContent = textContentMatch[0];
            const iconHTML = heroTitle.innerHTML.split('<br>')[0] + '<br>';
            heroTitle.innerHTML = iconHTML;
            heroTitle.style.opacity = '1';
            let i = 0;
            function typeWriter() {
                if (i < textContent.length) {
                    heroTitle.innerHTML += textContent.charAt(i);
                    i++;
                    setTimeout(typeWriter, 50);
                }
            }
            setTimeout(typeWriter, 500);
        }
    }

});
