// js/script.js (ไฟล์ใหม่)

// 1. PRELOADER
const PRELOADER_DELAY_MS = 2000;

window.addEventListener('load', () => {
    const preloader = document.querySelector('.preloader');
    if (!preloader) return;

    setTimeout(() => {
        preloader.style.opacity = '0';
        preloader.style.visibility = 'hidden';
        preloader.style.pointerEvents = 'none';
    }, PRELOADER_DELAY_MS);
});

// 2. OFF-CANVAS MENU ANIMATION
const popupNavbarElement = document.getElementById('popupNavbar');
if(popupNavbarElement) {
    const bsOffcanvas = new bootstrap.Offcanvas(popupNavbarElement);

    popupNavbarElement.addEventListener('show.bs.offcanvas', event => {
        const navItems = popupNavbarElement.querySelectorAll('.offcanvas-body .nav-item');
        navItems.forEach((item) => { 
            item.style.opacity = '0'; 
            item.style.animation = 'none'; 
        });
    });
    
    popupNavbarElement.addEventListener('shown.bs.offcanvas', event => {
        const navItems = popupNavbarElement.querySelectorAll('.offcanvas-body .nav-item');
        navItems.forEach((item, index) => { 
            item.style.animation = `slideInFromRight 0.3s ease-out ${index * 0.1}s forwards`; 
        });
    });
    
    popupNavbarElement.addEventListener('hide.bs.offcanvas', event => {
        const navItems = popupNavbarElement.querySelectorAll('.offcanvas-body .nav-item');
        navItems.forEach((item) => { 
            item.style.opacity = '0'; 
            item.style.animation = 'none'; 
        });
    });

    // (ใหม่) ปิดเมนูเมื่อคลิก Smooth Scroll (สำหรับหน้า Landing)
    document.querySelectorAll('#popupNavbar a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            bsOffcanvas.hide();
        });
    });
}
