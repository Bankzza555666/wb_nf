<?php
// page/maintenance.php
// หน้าแจ้งกำลังปรับปรุงระบบ (แสดงเมื่อแอดมินเปิดโหมดบำรุงรักษา - เฉพาะผู้ใช้ทั่วไป)

$site_name = 'NF~SHOP';
$announcement = '';
if (isset($conn)) {
    $r = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'site_name' LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) $site_name = $row['setting_value'];
    $r = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'announcement' LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) $announcement = trim($row['setting_value']);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>กำลังปรับปรุงระบบ - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if (file_exists(__DIR__ . '/../include/theme_head.php')) include __DIR__ . '/../include/theme_head.php'; ?>
    <style>
        * { box-sizing: border-box; }
        :root {
            --accent: #E50914;
            --accent-dark: #b80710;
            --gold: #ffd700;
        }
        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #0f0f0f 100%);
            color: #fff;
            font-family: 'Prompt', 'Segoe UI', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow: hidden;
        }
        /* Animated background particles */
        .bg-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.3;
            animation: float 20s infinite ease-in-out;
        }
        .orb1 {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(229,9,20,0.4) 0%, transparent 70%);
            top: -100px;
            left: -100px;
            animation-delay: 0s;
        }
        .orb2 {
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,107,107,0.3) 0%, transparent 70%);
            bottom: -50px;
            right: -50px;
            animation-delay: -5s;
        }
        .orb3 {
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255,215,0,0.2) 0%, transparent 70%);
            top: 50%;
            left: 50%;
            animation-delay: -10s;
        }
        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -30px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.95); }
        }
        
        .container {
            max-width: 560px;
            width: 100%;
            position: relative;
            z-index: 1;
        }
        .card {
            background: rgba(20, 20, 25, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.6),
                        0 0 0 1px rgba(229, 9, 20, 0.1),
                        inset 0 1px 0 rgba(255,255,255,0.05);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(229,9,20,0.5), transparent);
        }
        
        /* Logo area */
        .logo-area {
            margin-bottom: 32px;
        }
        .logo-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, rgba(229,9,20,0.2), rgba(229,9,20,0.05));
            border: 2px solid rgba(229, 9, 20, 0.4);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: #E50914;
            position: relative;
            animation: pulse-glow 3s infinite ease-in-out;
        }
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 20px rgba(229,9,20,0.3), 0 0 40px rgba(229,9,20,0.1); }
            50% { box-shadow: 0 0 30px rgba(229,9,20,0.5), 0 0 60px rgba(229,9,20,0.2); }
        }
        .gear-spin {
            animation: spin 8s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .brand-name {
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.6);
            margin-bottom: 8px;
        }
        
        h1 {
            font-size: 32px;
            font-weight: 700;
            margin: 0 0 16px;
            background: linear-gradient(135deg, #fff 0%, #aaa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .subtitle {
            color: rgba(255,255,255,0.7);
            font-size: 16px;
            font-weight: 400;
            line-height: 1.7;
            margin-bottom: 32px;
        }
        
        /* Progress bar */
        .progress-container {
            margin-bottom: 32px;
        }
        .progress-label {
            display: flex;
            justify-content: space-between;
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .progress-bar {
            height: 6px;
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
            overflow: hidden;
            position: relative;
        }
        .progress-fill {
            height: 100%;
            width: 75%;
            background: linear-gradient(90deg, #E50914, #ff4757);
            border-radius: 3px;
            animation: progress-shine 2s infinite ease-in-out;
            position: relative;
        }
        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shine 2s infinite;
        }
        @keyframes shine {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        @keyframes progress-shine {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        /* Announcement box */
        .announcement-box {
            background: linear-gradient(135deg, rgba(229,9,20,0.1), rgba(229,9,20,0.02));
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 28px;
            text-align: left;
            position: relative;
        }
        .announcement-box::before {
            content: '\f0a1';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: -12px;
            left: 20px;
            background: #0f0f0f;
            padding: 0 10px;
            color: #E50914;
            font-size: 14px;
        }
        .announcement-title {
            font-size: 13px;
            font-weight: 600;
            color: #E50914;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        .announcement-text {
            color: rgba(255,255,255,0.85);
            font-size: 14px;
            line-height: 1.7;
            margin: 0;
            white-space: pre-wrap;
        }
        
        /* Footer */
        .footer {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .apology {
            font-size: 13px;
            color: rgba(255,255,255,0.5);
            margin-bottom: 20px;
        }
        .apology i {
            color: #E50914;
            margin-right: 6px;
        }
        
        .btn-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(135deg, #E50914, #c40812);
            color: #fff !important;
            box-shadow: 0 4px 20px rgba(229, 9, 20, 0.3);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #ff1a25, #E50914);
            transform: translateY(-2px);
            box-shadow: 0 6px 30px rgba(229, 9, 20, 0.4);
        }
        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: #fff !important;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.3);
        }
        
        /* Status indicator */
        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #E50914;
            border-radius: 50%;
            margin-right: 8px;
            animation: blink 1.5s infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .card {
                padding: 36px 24px;
            }
            h1 {
                font-size: 26px;
            }
            .logo-icon {
                width: 80px;
                height: 80px;
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <div class="bg-orb orb1"></div>
    <div class="bg-orb orb2"></div>
    <div class="bg-orb orb3"></div>
    
    <div class="container">
        <div class="card">
            <div class="logo-area">
                <div class="logo-icon">
                    <i class="fas fa-cog gear-spin"></i>
                </div>
                <div class="brand-name"><?php echo htmlspecialchars($site_name); ?></div>
            </div>
            
            <h1><span class="status-dot"></span>กำลังปรับปรุงระบบ</h1>
            
            <p class="subtitle">
                ขณะนี้เรากำลังทำการอัปเกรดและปรับปรุงระบบ<br>
                เพื่อให้บริการที่ดียิ่งขึ้นแก่ท่าน
            </p>
            
            <!-- Progress Bar -->
            <div class="progress-container">
                <div class="progress-label">
                    <span>สถานะการปรับปรุง</span>
                    <span>75%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
            </div>
            
            <?php if ($announcement !== ''): ?>
            <div class="announcement-box">
                <div class="announcement-title">ประกาศจากทีมงาน</div>
                <p class="announcement-text"><?php echo nl2br(htmlspecialchars($announcement)); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="footer">
                <p class="apology">
                    <i class="fas fa-heart"></i>
                    ขออภัยในความไม่สะดวก ระบบจะกลับมาให้บริการอีกครั้งเร็วๆ นี้
                </p>
                <div class="btn-group">
                    <a href="index.php?p=logout" class="btn btn-primary">
                        <i class="fas fa-sign-out-alt"></i>
                        ออกจากระบบ
                    </a>
                    <a href="index.php?r=landing" class="btn btn-secondary">
                        <i class="fas fa-home"></i>
                        หน้าแรก
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
