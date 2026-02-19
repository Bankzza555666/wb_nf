<?php
// page/header.php (ไฟล์ใหม่)
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยินดีต้อนรับ - NF~SHOP</title>

    <!-- SEO & Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="icon" type="image/png" sizes="64x64" href="img/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="img/favicon.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#000000">
    <meta name="description" content="NF~SHOP บริการ VPN และเติมเงินเกมราคาถูก รวดเร็ว ปลอดภัย ตลอด 24 ชั่วโมง">
    <meta name="keywords" content="VPN, เติมเงินเกม, อินเทอร์เน็ต, NF SHOP, ราคาถูก">
    <meta property="og:title" content="NF~SHOP - ยินดีต้อนรับ">
    <meta property="og:description" content="บริการ VPN และเติมเงินเกมราคาถูก รวดเร็ว ปลอดภัย ตลอด 24 ชั่วโมง">
    <meta property="og:image" content="img/logo.png">
    <meta property="og:type" content="website">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="css/premium_alert.css">
    <link rel="stylesheet" href="css/theme.css">
    <script src="js/premium_alert.js" defer></script>
    <script src="js/theme.js" defer></script>

    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">

    <!-- ✅ SweetAlert2 & Unified Alert System -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/alert-helper.js"></script>
    
    <!-- ✅ Site Theme (Admin Selectable) -->
    <?php if (file_exists(__DIR__ . '/../include/theme_head.php')) include __DIR__ . '/../include/theme_head.php'; ?>
</head>

<body>
    <div class="preloader">
        <div class="spinner"></div>
    </div>