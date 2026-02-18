/**
 * Apply Customizations - โหลด customizations สำหรับทุกคน (ไม่ใช่แค่ Admin)
 */

(function() {
    'use strict';
    
    // ป้องกันโหลดซ้ำ
    if (window.VE_APPLY_LOADED) return;
    window.VE_APPLY_LOADED = true;
    
    // รอให้ DOM พร้อม
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initApplyCustomizations);
    } else {
        initApplyCustomizations();
    }
    
    function initApplyCustomizations() {
        const currentPage = getPageKey();
        loadAndApply(currentPage);
    }
    
    function getPageKey() {
        if (typeof window.VE_PAGE !== 'undefined' && window.VE_PAGE) {
            return String(window.VE_PAGE);
        }
        const params = new URLSearchParams(window.location.search);
        const p = params.get('p');
        const r = params.get('r');
        if (p) return String(p).trim() || '*';
        if (r) return String(r).trim() || '*';
        return '*';
    }
    
    // Hash function (ต้องตรงกับใน visual-editor.js)
    function hashString(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        return Math.abs(hash).toString(36);
    }
    
    // สร้าง element info (ต้องตรงกับใน visual-editor.js)
    function getElementInfo(element) {
        const tag = element.tagName.toLowerCase();
        const text = (element.textContent || '').trim().substring(0, 30).replace(/\s+/g, ' ');
        const classes = (element.className || '').toString().split(' ').filter(c => c && !c.startsWith('ve-')).sort().join('.');
        
        let position = 0;
        if (element.parentElement) {
            const siblings = Array.from(element.parentElement.children);
            position = siblings.indexOf(element);
        }
        
        let parentInfo = '';
        if (element.parentElement) {
            const parent = element.parentElement;
            if (parent.id) parentInfo = '#' + parent.id;
            else if (parent.className) parentInfo = parent.className.toString().split(' ')[0] || '';
        }
        
        return `${tag}|${classes}|${position}|${parentInfo}|${text}`;
    }
    
    // Assign stable IDs ให้ทุก element ที่ยังไม่มี ID
    function assignStableIds() {
        const allElements = document.body.querySelectorAll('*');
        allElements.forEach(el => {
            // ข้าม element ที่มี ID แล้ว หรือเป็น VE element
            if (el.id || el.closest('.ve-panel') || el.closest('#visual-editor-toggle')) return;
            
            const info = getElementInfo(el);
            const hash = hashString(info);
            el.id = 've-' + hash;
        });
    }
    
    async function loadAndApply(page) {
        try {
            const response = await fetch(`controller/visual_editor.php?action=get_customizations&page=${encodeURIComponent(page)}`);
            const data = await response.json();
            
            if (data.success && data.data) {
                // Assign stable IDs ก่อน apply
                assignStableIds();
                
                const ignorePatterns = ['nav-submenu', 'nav-dropdown', 'dropdown', 'modal', 'sidebar', 'notification', 'overlay', 'main-nav', 'landing-nav', 'submenu'];
                
                // Apply styles
                if (data.data.styles && data.data.styles.length > 0) {
                    let cssRules = '';
                    let appliedCount = 0;
                    
                    data.data.styles.forEach(item => {
                        const shouldIgnore = ignorePatterns.some(p => item.selector.toLowerCase().includes(p));
                        if (!shouldIgnore) {
                            // ตรวจสอบว่า selector match กับ element จริงหรือไม่
                            try {
                                const el = document.querySelector(item.selector);
                                if (el) {
                                    cssRules += `${item.selector} { ${item.property_name}: ${item.property_value} !important; }\n`;
                                    appliedCount++;
                                }
                            } catch (e) {}
                        }
                    });
                    
                    if (cssRules) {
                        const oldStyle = document.getElementById('ve-custom-styles');
                        if (oldStyle) oldStyle.remove();
                        
                        const style = document.createElement('style');
                        style.id = 've-custom-styles';
                        style.textContent = cssRules;
                        document.head.appendChild(style);
                    }
                    
                    console.log('[VE Apply] Styles applied:', appliedCount, '/', data.data.styles.length);
                }
                
                // Apply texts, links, images, HTML
                if (data.data.texts && Object.keys(data.data.texts).length > 0) {
                    let appliedTexts = 0;
                    for (const [key, value] of Object.entries(data.data.texts)) {
                        try {
                            let selector = key;
                            let type = 'text';
                            
                            // ตรวจสอบ prefix
                            if (key.startsWith('html:')) {
                                selector = key.slice(5);
                                type = 'html';
                            } else if (key.startsWith('link:')) {
                                selector = key.slice(5);
                                type = 'link';
                            }
                            
                            const element = document.querySelector(selector);
                            if (element) {
                                if (type === 'html') {
                                    element.innerHTML = value;
                                } else if (type === 'link') {
                                    element.href = value;
                                } else if (element.tagName === 'IMG') {
                                    element.src = value;
                                } else {
                                    element.textContent = value;
                                }
                                appliedTexts++;
                            }
                        } catch (e) {}
                    }
                    console.log('[VE Apply] Texts/Links applied:', appliedTexts, '/', Object.keys(data.data.texts).length);
                }
            }
            
            showContent();
        } catch (error) {
            console.log('[VE Apply] Error:', error);
            showContent();
        }
    }
    
    function showContent() {
        document.body.classList.remove('ve-content-loading');
        document.body.classList.add('ve-content-ready');
    }
})();
