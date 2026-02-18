<?php
// home/faq.php - คำถามที่พบบ่อย
require_once 'controller/auth_check.php';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คำถามที่พบบ่อย - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        :root {
            --bg-body: #0a0a0a;
            --card-bg: rgba(15, 15, 15, 0.9);
            --border-color: rgba(229, 9, 20, 0.2);
            --accent: #E50914;
        }
        body { background: var(--bg-body); color: #fff; font-family: 'Segoe UI', sans-serif; }
        
        .faq-header {
            text-align: center;
            padding: 60px 0 40px;
        }
        .faq-header h1 { font-size: 2.5rem; font-weight: bold; margin-bottom: 10px; }
        .faq-header p { color: #aaa; }

        .faq-categories {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        .category-btn {
            padding: 10px 25px;
            border: 1px solid var(--border-color);
            background: transparent;
            color: #aaa;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .category-btn:hover, .category-btn.active {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }

        .accordion-item {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px !important;
            margin-bottom: 15px;
            overflow: hidden;
        }
        .accordion-button {
            background: transparent;
            color: #fff;
            font-weight: 600;
            padding: 20px 25px;
        }
        .accordion-button:not(.collapsed) {
            background: rgba(229, 9, 20, 0.1);
            color: var(--accent);
            box-shadow: none;
        }
        .accordion-button::after {
            filter: invert(1);
        }
        .accordion-button:focus {
            box-shadow: none;
            border-color: var(--border-color);
        }
        .accordion-body {
            background: rgba(0, 0, 0, 0.3);
            color: #ccc;
            padding: 20px 25px;
            line-height: 1.8;
        }
        .accordion-body ul { padding-left: 20px; margin: 10px 0; }
        .accordion-body li { margin-bottom: 8px; }
        .accordion-body a { color: var(--accent); }

        .search-box {
            max-width: 500px;
            margin: 0 auto 30px;
            position: relative;
        }
        .search-box input {
            width: 100%;
            padding: 15px 20px 15px 50px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 30px;
            color: #fff;
            font-size: 1rem;
        }
        .search-box input:focus {
            outline: none;
            border-color: var(--accent);
        }
        .search-box i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }

        .contact-card {
            background: linear-gradient(135deg, rgba(229, 9, 20, 0.1), rgba(0, 0, 0, 0.5));
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            margin-top: 40px;
        }
    </style>
    <!-- ✅ Site Theme (Admin Selectable) -->
    <?php if (file_exists(__DIR__ . '/../include/theme_head.php')) include __DIR__ . '/../include/theme_head.php'; ?>
</head>

<body>
    <?php include 'home/navbar.php'; ?>

    <div class="container py-4">
        <div class="faq-header">
            <h1><i class="fas fa-question-circle text-danger me-3"></i>คำถามที่พบบ่อย</h1>
            <p>หาคำตอบสำหรับคำถามของคุณได้ที่นี่</p>
        </div>

        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="faqSearch" placeholder="ค้นหาคำถาม...">
        </div>

        <div class="faq-categories">
            <button class="category-btn active" data-category="all">ทั้งหมด</button>
            <button class="category-btn" data-category="general">ทั่วไป</button>
            <button class="category-btn" data-category="payment">การชำระเงิน</button>
            <button class="category-btn" data-category="vpn">VPN</button>
            <button class="category-btn" data-category="ssh">SSH</button>
            <button class="category-btn" data-category="account">บัญชีผู้ใช้</button>
        </div>

        <div class="accordion" id="faqAccordion">
            <!-- General -->
            <div class="accordion-item" data-category="general">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                        <i class="fas fa-globe me-3 text-info"></i>บริการนี้คืออะไร?
                    </button>
                </h2>
                <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        เราให้บริการ VPN และ SSH Account สำหรับการเชื่อมต่ออินเทอร์เน็ตที่ปลอดภัยและเป็นส่วนตัว 
                        รองรับหลายโปรโตคอล เช่น VLESS, VMess, Trojan และ SSH พร้อม Server หลายประเทศ
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-category="general">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                        <i class="fas fa-shield-alt me-3 text-success"></i>ปลอดภัยไหม?
                    </button>
                </h2>
                <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        ใช่ครับ! เราใช้การเข้ารหัสระดับ AES-256 และไม่เก็บ log การใช้งาน 
                        ข้อมูลของคุณถูกปกป้องตลอดเวลา
                    </div>
                </div>
            </div>

            <!-- Payment -->
            <div class="accordion-item" data-category="payment">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                        <i class="fas fa-credit-card me-3 text-warning"></i>เติมเงินได้อย่างไร?
                    </button>
                </h2>
                <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        <ol>
                            <li>ไปที่เมนู "เติมเงิน"</li>
                            <li>เลือกจำนวนเงินที่ต้องการ</li>
                            <li>สแกน QR Code ด้วยแอปธนาคาร</li>
                            <li>ชำระเงินและรอระบบตรวจสอบ (1-2 นาที)</li>
                            <li>ยอดเงินจะเข้าบัญชีอัตโนมัติ</li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-category="payment">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                        <i class="fas fa-undo me-3 text-danger"></i>ขอคืนเงินได้ไหม?
                    </button>
                </h2>
                <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        เนื่องจากเป็นบริการดิจิทัล เมื่อซื้อแล้วไม่สามารถขอคืนเงินได้ 
                        แนะนำให้ทดลองใช้แพ็กเกจขนาดเล็กก่อน
                    </div>
                </div>
            </div>

            <!-- VPN -->
            <div class="accordion-item" data-category="vpn">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                        <i class="fas fa-mobile-alt me-3 text-info"></i>ใช้ VPN บนมือถือยังไง?
                    </button>
                </h2>
                <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        <strong>iOS:</strong>
                        <ul>
                            <li>ดาวน์โหลดแอป "V2Box" หรือ "Shadowrocket" จาก App Store</li>
                            <li>คัดลอก Config URL จากหน้า "VPN ของฉัน"</li>
                            <li>ในแอป กด + แล้วเลือก "Import from Clipboard"</li>
                            <li>เปิดใช้งาน VPN</li>
                        </ul>
                        <strong>Android:</strong>
                        <ul>
                            <li>ดาวน์โหลด "V2rayNG" จาก Play Store</li>
                            <li>คัดลอก Config URL จากหน้า "VPN ของฉัน"</li>
                            <li>ในแอป กด + แล้วเลือก "Import config from clipboard"</li>
                            <li>เลือก Server แล้วกดเชื่อมต่อ</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-category="vpn">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                        <i class="fas fa-tachometer-alt me-3 text-success"></i>ความเร็วเท่าไหร่?
                    </button>
                </h2>
                <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        ความเร็วขึ้นอยู่กับแพ็กเกจที่เลือก โดยทั่วไป:
                        <ul>
                            <li>แพ็กเกจ Basic: ไม่จำกัดความเร็ว (Best Effort)</li>
                            <li>แพ็กเกจ Premium: รับประกัน 100 Mbps+</li>
                            <li>แพ็กเกจ VIP: รับประกัน 500 Mbps+</li>
                        </ul>
                        ความเร็วจริงขึ้นอยู่กับ ISP และตำแหน่งของคุณ
                    </div>
                </div>
            </div>

            <!-- SSH -->
            <div class="accordion-item" data-category="ssh">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq7">
                        <i class="fas fa-terminal me-3 text-warning"></i>SSH ใช้กับแอปอะไร?
                    </button>
                </h2>
                <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        <strong>Android:</strong> HTTP Injector, HTTP Custom, KPN Tunnel<br>
                        <strong>iOS:</strong> Termius, SSH Files<br>
                        <strong>PC:</strong> Bitvise, PuTTY, OpenSSH
                    </div>
                </div>
            </div>

            <!-- Account -->
            <div class="accordion-item" data-category="account">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq8">
                        <i class="fas fa-key me-3 text-danger"></i>ลืมรหัสผ่านทำยังไง?
                    </button>
                </h2>
                <div id="faq8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        <ol>
                            <li>ไปที่หน้า Login</li>
                            <li>คลิก "ลืมรหัสผ่าน"</li>
                            <li>ใส่อีเมลที่ลงทะเบียน</li>
                            <li>ตรวจสอบอีเมลและทำตามขั้นตอน</li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-category="account">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq9">
                        <i class="fas fa-users me-3 text-info"></i>ใช้ได้กี่อุปกรณ์?
                    </button>
                </h2>
                <div id="faq9" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">
                        จำนวนอุปกรณ์ขึ้นอยู่กับแพ็กเกจ โดยทั่วไป:
                        <ul>
                            <li>แพ็กเกจ Basic: 2-3 อุปกรณ์</li>
                            <li>แพ็กเกจ Premium: 5 อุปกรณ์</li>
                            <li>แพ็กเกจ VIP: 10 อุปกรณ์</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="contact-card">
            <h4 class="mb-3"><i class="fas fa-headset me-2"></i>ยังหาคำตอบไม่เจอ?</h4>
            <p class="text-secondary mb-4">ติดต่อทีมงานของเราได้เลย เราพร้อมช่วยเหลือคุณ</p>
            <a href="?p=contact" class="btn btn-danger btn-lg">
                <i class="fas fa-comments me-2"></i>ติดต่อเรา
            </a>
        </div>
    </div>

    <?php include 'home/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Category filter
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const category = this.dataset.category;
                document.querySelectorAll('.accordion-item').forEach(item => {
                    if (category === 'all' || item.dataset.category === category) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });

        // Search
        document.getElementById('faqSearch').addEventListener('input', function() {
            const query = this.value.toLowerCase();
            document.querySelectorAll('.accordion-item').forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(query) ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>
