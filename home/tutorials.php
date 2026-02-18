<?php
// home/tutorials.php - วิธีใช้งาน
require_once 'controller/auth_check.php';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>วิธีใช้งาน - <?php echo SITE_NAME; ?></title>
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
        
        .tutorial-header {
            text-align: center;
            padding: 60px 0 40px;
        }
        .tutorial-header h1 { font-size: 2.5rem; font-weight: bold; }

        .platform-tabs {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 40px;
        }
        .platform-tab {
            padding: 15px 30px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            color: #aaa;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            min-width: 120px;
        }
        .platform-tab:hover { border-color: var(--accent); color: #fff; }
        .platform-tab.active {
            background: linear-gradient(135deg, var(--accent), #99060d);
            border-color: transparent;
            color: #fff;
        }
        .platform-tab i { font-size: 2rem; display: block; margin-bottom: 8px; }

        .tutorial-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 30px;
        }
        .tutorial-card-header {
            background: linear-gradient(135deg, rgba(229, 9, 20, 0.2), transparent);
            padding: 25px;
            border-bottom: 1px solid var(--border-color);
        }
        .tutorial-card-header h3 { margin: 0; font-size: 1.3rem; }
        .tutorial-card-body { padding: 25px; }

        .step-list { list-style: none; padding: 0; margin: 0; counter-reset: step; }
        .step-list li {
            position: relative;
            padding: 20px 20px 20px 70px;
            margin-bottom: 15px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            border-left: 3px solid var(--accent);
        }
        .step-list li::before {
            counter-increment: step;
            content: counter(step);
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            width: 35px;
            height: 35px;
            background: var(--accent);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .step-list li strong { color: #fff; display: block; margin-bottom: 5px; }
        .step-list li span { color: #aaa; font-size: 0.9rem; }

        .download-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 25px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: #fff;
            border-radius: 10px;
            text-decoration: none;
            margin: 5px;
            transition: all 0.3s;
        }
        .download-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(59, 130, 246, 0.4); color: #fff; }
        .download-btn i { font-size: 1.5rem; }
        .download-btn.green { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .download-btn.green:hover { box-shadow: 0 5px 20px rgba(34, 197, 94, 0.4); }

        .tip-box {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 12px;
            padding: 15px 20px;
            margin-top: 20px;
        }
        .tip-box i { color: #3b82f6; }

        .warning-box {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 12px;
            padding: 15px 20px;
            margin-top: 20px;
        }
        .warning-box i { color: #f59e0b; }

        .content-section { display: none; }
        .content-section.active { display: block; }
    </style>
    <!-- ✅ Site Theme (Admin Selectable) -->
    <?php if (file_exists(__DIR__ . '/../include/theme_head.php')) include __DIR__ . '/../include/theme_head.php'; ?>
</head>

<body>
    <?php include 'home/navbar.php'; ?>

    <div class="container py-4">
        <div class="tutorial-header">
            <h1><i class="fas fa-book-open text-danger me-3"></i>วิธีใช้งาน</h1>
            <p class="text-secondary">คู่มือการตั้งค่า VPN และ SSH บนอุปกรณ์ต่างๆ</p>
        </div>

        <div class="platform-tabs">
            <div class="platform-tab active" data-platform="android">
                <i class="fab fa-android"></i>
                Android
            </div>
            <div class="platform-tab" data-platform="ios">
                <i class="fab fa-apple"></i>
                iOS
            </div>
            <div class="platform-tab" data-platform="windows">
                <i class="fab fa-windows"></i>
                Windows
            </div>
        </div>

        <!-- Android -->
        <div class="content-section active" id="android">
            <div class="row">
                <div class="col-lg-6">
                    <div class="tutorial-card">
                        <div class="tutorial-card-header">
                            <h3><i class="fas fa-shield-alt me-2 text-info"></i>ตั้งค่า VPN (V2Box / NPV Tunnel)</h3>
                        </div>
                        <div class="tutorial-card-body">
                            <div class="mb-4">
                                <a href="https://play.google.com/store/apps/details?id=dev.hexasoftware.v2box" target="_blank" class="download-btn">
                                    <i class="fab fa-google-play"></i>
                                    <span>ดาวน์โหลด V2Box</span>
                                </a>
                                <a href="https://play.google.com/store/apps/details?id=com.npv.tunnel" target="_blank" class="download-btn green">
                                    <i class="fab fa-google-play"></i>
                                    <span>ดาวน์โหลด NPV Tunnel</span>
                                </a>
                            </div>
                            <h6 class="text-info mb-3">วิธีใช้ V2Box</h6>
                            <ol class="step-list">
                                <li>
                                    <strong>เปิดแอป V2Box</strong>
                                    <span>หลังจากติดตั้งเสร็จแล้ว</span>
                                </li>
                                <li>
                                    <strong>คัดลอก Config URL</strong>
                                    <span>จากหน้า "VPN ของฉัน" ในเว็บไซต์</span>
                                </li>
                                <li>
                                    <strong>กด + ที่มุมขวาบน</strong>
                                    <span>เลือก "Import from Clipboard"</span>
                                </li>
                                <li>
                                    <strong>เลือก Server ที่นำเข้า</strong>
                                    <span>แตะที่รายการ Config ที่เพิ่มเข้ามา</span>
                                </li>
                                <li>
                                    <strong>กดปุ่มเชื่อมต่อ</strong>
                                    <span>เพื่อเริ่มเชื่อมต่อ VPN</span>
                                </li>
                            </ol>
                            <div class="tip-box">
                                <i class="fas fa-lightbulb me-2"></i>
                                <strong>NPV Tunnel:</strong> ใช้ไฟล์ Config ที่ดาวน์โหลดจากหน้า "VPN ของฉัน" แล้ว Import เข้าแอป
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="tutorial-card">
                        <div class="tutorial-card-header">
                            <h3><i class="fas fa-terminal me-2 text-warning"></i>ตั้งค่า SSH (Netmod / NPV Tunnel)</h3>
                        </div>
                        <div class="tutorial-card-body">
                            <div class="mb-4">
                                <a href="https://play.google.com/store/apps/details?id=com.developer.nicmod" target="_blank" class="download-btn">
                                    <i class="fab fa-google-play"></i>
                                    <span>ดาวน์โหลด Netmod</span>
                                </a>
                                <a href="https://play.google.com/store/apps/details?id=com.npv.tunnel" target="_blank" class="download-btn green">
                                    <i class="fab fa-google-play"></i>
                                    <span>ดาวน์โหลด NPV Tunnel</span>
                                </a>
                            </div>
                            <h6 class="text-warning mb-3">วิธีใช้ Netmod</h6>
                            <ol class="step-list">
                                <li>
                                    <strong>เปิดแอป Netmod</strong>
                                    <span>ไปที่หน้าหลัก</span>
                                </li>
                                <li>
                                    <strong>Import ไฟล์ Config</strong>
                                    <span>ดาวน์โหลดไฟล์ .npv หรือ .nm จากหน้า "SSH ของฉัน"</span>
                                </li>
                                <li>
                                    <strong>ตั้งค่า SSH (ถ้าต้องการ)</strong>
                                    <span>ใส่ Host, Port, Username, Password</span>
                                </li>
                                <li>
                                    <strong>กดปุ่ม Connect</strong>
                                    <span>รอจนกว่าจะเชื่อมต่อสำเร็จ</span>
                                </li>
                            </ol>
                            <div class="tip-box">
                                <i class="fas fa-lightbulb me-2"></i>
                                <strong>NPV Tunnel:</strong> รองรับทั้ง VPN และ SSH ในแอปเดียว สะดวกในการใช้งาน
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- iOS -->
        <div class="content-section" id="ios">
            <div class="row">
                <div class="col-lg-6">
                    <div class="tutorial-card">
                        <div class="tutorial-card-header">
                            <h3><i class="fas fa-shield-alt me-2 text-info"></i>ตั้งค่า VPN (V2Box / NPV Tunnel)</h3>
                        </div>
                        <div class="tutorial-card-body">
                            <div class="mb-4">
                                <a href="https://apps.apple.com/app/v2box-v2ray-client/id6446814690" target="_blank" class="download-btn">
                                    <i class="fab fa-app-store-ios"></i>
                                    <span>V2Box (ฟรี)</span>
                                </a>
                                <a href="https://apps.apple.com/app/npv-tunnel/id1629293763" target="_blank" class="download-btn green">
                                    <i class="fab fa-app-store-ios"></i>
                                    <span>NPV Tunnel</span>
                                </a>
                            </div>
                            <h6 class="text-info mb-3">วิธีใช้ V2Box</h6>
                            <ol class="step-list">
                                <li>
                                    <strong>ดาวน์โหลดแอปจาก App Store</strong>
                                    <span>V2Box หรือ NPV Tunnel</span>
                                </li>
                                <li>
                                    <strong>คัดลอก Config URL</strong>
                                    <span>จากหน้า "VPN ของฉัน"</span>
                                </li>
                                <li>
                                    <strong>เปิดแอปแล้วกด +</strong>
                                    <span>เลือก "Import from Clipboard"</span>
                                </li>
                                <li>
                                    <strong>อนุญาต VPN Configuration</strong>
                                    <span>เมื่อระบบถาม ให้กด Allow</span>
                                </li>
                                <li>
                                    <strong>เปิดสวิตช์เชื่อมต่อ</strong>
                                    <span>VPN จะเริ่มทำงาน</span>
                                </li>
                            </ol>
                            <div class="warning-box">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>หมายเหตุ:</strong> อาจต้องใช้ Apple ID ต่างประเทศในการดาวน์โหลดบางแอป
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="tutorial-card">
                        <div class="tutorial-card-header">
                            <h3><i class="fas fa-terminal me-2 text-warning"></i>ตั้งค่า SSH (NPV Tunnel)</h3>
                        </div>
                        <div class="tutorial-card-body">
                            <div class="mb-4">
                                <a href="https://apps.apple.com/app/npv-tunnel/id1629293763" target="_blank" class="download-btn green">
                                    <i class="fab fa-app-store-ios"></i>
                                    <span>ดาวน์โหลด NPV Tunnel</span>
                                </a>
                            </div>
                            <ol class="step-list">
                                <li>
                                    <strong>เปิด NPV Tunnel</strong>
                                    <span>หลังจากติดตั้งเสร็จ</span>
                                </li>
                                <li>
                                    <strong>Import ไฟล์ Config</strong>
                                    <span>ดาวน์โหลดไฟล์จากหน้า "SSH ของฉัน" แล้วเปิดด้วยแอป</span>
                                </li>
                                <li>
                                    <strong>อนุญาต VPN Configuration</strong>
                                    <span>กด Allow เมื่อระบบถาม</span>
                                </li>
                                <li>
                                    <strong>กดปุ่ม Connect</strong>
                                    <span>รอจนกว่าจะเชื่อมต่อสำเร็จ</span>
                                </li>
                            </ol>
                            <div class="tip-box">
                                <i class="fas fa-lightbulb me-2"></i>
                                <strong>Tip:</strong> NPV Tunnel รองรับทั้ง SSH Tunnel และ V2Ray ในแอปเดียว
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Windows -->
        <div class="content-section" id="windows">
            <div class="row">
                <div class="col-lg-6">
                    <div class="tutorial-card">
                        <div class="tutorial-card-header">
                            <h3><i class="fas fa-shield-alt me-2 text-info"></i>ตั้งค่า VPN (V2rayN)</h3>
                        </div>
                        <div class="tutorial-card-body">
                            <div class="mb-4">
                                <a href="https://github.com/2dust/v2rayN/releases" target="_blank" class="download-btn">
                                    <i class="fab fa-github"></i>
                                    <span>ดาวน์โหลด V2rayN</span>
                                </a>
                            </div>
                            <ol class="step-list">
                                <li>
                                    <strong>ดาวน์โหลดและแตกไฟล์</strong>
                                    <span>เลือก v2rayN-With-Core.zip</span>
                                </li>
                                <li>
                                    <strong>เปิดโปรแกรม v2rayN.exe</strong>
                                    <span>รันในฐานะ Administrator</span>
                                </li>
                                <li>
                                    <strong>คัดลอก Config URL</strong>
                                    <span>จากหน้า "VPN ของฉัน"</span>
                                </li>
                                <li>
                                    <strong>กด Servers > Import bulk URL from clipboard</strong>
                                    <span>Config จะถูกเพิ่มเข้ามา</span>
                                </li>
                                <li>
                                    <strong>คลิกขวาที่ Server แล้วเลือก Set as active server</strong>
                                    <span>จากนั้นเปิด System Proxy</span>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="tutorial-card">
                        <div class="tutorial-card-header">
                            <h3><i class="fas fa-terminal me-2 text-warning"></i>ตั้งค่า SSH (Netmod PC)</h3>
                        </div>
                        <div class="tutorial-card-body">
                            <div class="mb-4">
                                <a href="https://netmodapk.com/download-netmod-syna-for-pc/" target="_blank" class="download-btn">
                                    <i class="fas fa-download"></i>
                                    <span>ดาวน์โหลด Netmod PC</span>
                                </a>
                            </div>
                            <ol class="step-list">
                                <li>
                                    <strong>ดาวน์โหลดและติดตั้ง Netmod PC</strong>
                                    <span>รันไฟล์ติดตั้ง</span>
                                </li>
                                <li>
                                    <strong>เปิดโปรแกรม Netmod</strong>
                                    <span>รันในฐานะ Administrator</span>
                                </li>
                                <li>
                                    <strong>Import ไฟล์ Config</strong>
                                    <span>ดาวน์โหลดไฟล์ .nm หรือ .npv จากหน้า "SSH ของฉัน"</span>
                                </li>
                                <li>
                                    <strong>ตั้งค่า SSH (ถ้าต้องการ)</strong>
                                    <span>ใส่ Host, Port, Username, Password</span>
                                </li>
                                <li>
                                    <strong>กดปุ่ม Connect</strong>
                                    <span>รอจนกว่าจะเชื่อมต่อสำเร็จ</span>
                                </li>
                            </ol>
                            <div class="tip-box">
                                <i class="fas fa-lightbulb me-2"></i>
                                <strong>Tip:</strong> Netmod PC รองรับไฟล์ Config จากแอป Netmod บนมือถือ
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'home/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Platform tabs
        document.querySelectorAll('.platform-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.platform-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.content-section').forEach(c => c.classList.remove('active'));
                
                this.classList.add('active');
                document.getElementById(this.dataset.platform).classList.add('active');
            });
        });
    </script>
</body>
</html>
