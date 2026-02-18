<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NF~SHOP - หน้าสมาชิก</title>

    <!-- SEO & Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="icon" type="image/png" sizes="64x64" href="img/favicon.png">
    <meta name="description" content="NF~SHOP บริการ VPN และเติมเงินเกมราคาถูก รวดเร็ว ปลอดภัย ตลอด 24 ชั่วโมง">
    <meta name="keywords" content="VPN, เติมเงินเกม, อินเทอร์เน็ต, NF SHOP, ราคาถูก">
    <meta property="og:title" content="NF~SHOP - บริการ VPN และเติมเงิน">
    <meta property="og:description" content="บริการ VPN และเติมเงินเกมราคาถูก รวดเร็ว ปลอดภัย ตลอด 24 ชั่วโมง">
    <meta property="og:image" content="img/logo.png">
    <meta property="og:type" content="website">

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

<body style="background: var(--bg-body, #000) !important;">
    <div class="preloader">
        <div class="spinner"></div>
    </div>