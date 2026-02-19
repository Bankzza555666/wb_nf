<?php
// page/footer.php
?>
<footer id="footer" class="landing-footer">
    <div class="container">
        <div class="footer-inner">
            <div class="footer-brand">
                <img src="img/logo.png" alt="Logo" style="height: 30px; width: auto;">
                <span>NF~SHOP</span>
            </div>
            <nav class="footer-links">
                <a href="?r=landing#home">หน้าแรก</a>
                <a href="?r=landing#features">ฟีเจอร์</a>
                <a href="?r=landing#servers">เซิร์ฟเวอร์</a>
                <a href="?r=contact">ติดต่อเรา</a>
            </nav>
            <p class="footer-copy">&copy; <?php echo date('Y'); ?> NF~SHOP. สงวนลิขสิทธิ์.</p>
        </div>
    </div>
</footer>
<style>
.landing-footer {
    background: rgba(5, 5, 8, 0.95);
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    padding: 2.5rem 0;
    margin-top: 3rem;
}
.landing-footer .footer-inner {
    text-align: center;
}
.landing-footer .footer-brand {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.25rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 1.25rem;
}
.landing-footer .footer-brand i {
    color: #E50914;
    font-size: 1.2rem;
}
.landing-footer .footer-links {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 1.5rem;
    margin-bottom: 1.25rem;
}
.landing-footer .footer-links a {
    color: rgba(255, 255, 255, 0.6);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
    transition: color 0.2s;
}
.landing-footer .footer-links a:hover {
    color: #E50914;
}
.landing-footer .footer-copy {
    color: rgba(255, 255, 255, 0.4);
    font-size: 0.85rem;
    margin: 0;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script src="js/script.js"></script>

<?php if (file_exists(__DIR__ . '/../include/visual_editor_loader.php')) include __DIR__ . '/../include/visual_editor_loader.php'; ?>
</body>

</html>