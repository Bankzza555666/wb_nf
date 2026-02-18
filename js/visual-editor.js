/**
 * Visual Editor Pro - ระบบแก้ไขเว็บแบบ Visual สำหรับ Admin
 * Version 2.0 - Full Features
 */

// ป้องกันโหลดซ้ำ
if (typeof window.VisualEditorLoaded !== 'undefined') {
    console.log('[VE] Already loaded, skipping...');
} else {
window.VisualEditorLoaded = true;

class VisualEditor {
    constructor() {
        this.isEditMode = false;
        this.selectedElement = null;
        this.hoveredElement = null;
        this.panel = null;
        this.highlighter = null;
        this.hintTooltip = null;
        this.choiceBar = null;
        this.codeModal = null;
        this.inlineEditingElement = null;
        this.currentPage = this.getNormalizedPageKey();
        
        // Undo/Redo history
        this.undoStack = [];
        this.redoStack = [];
        this.maxHistory = 50;
        
        // Copy/Paste style
        this.copiedStyles = null;
        
        this.init();
    }
    
    getNormalizedPageKey() {
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
    
    getVeUrl(path) { return path; }
    
    init() {
        this.createToggleButton();
        this.createEditorPanel();
        this.createHighlighter();
        this.createHintTooltip();
        this.createChoiceBar();
        this.createCodeModal();
        this.createResponsivePreview();
        this.createUploadModal();
        this.bindKeyboardShortcuts();
    }
    
    // ========== KEYBOARD SHORTCUTS ==========
    bindKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            if (!this.isEditMode) return;
            
            // Ctrl+Z = Undo
            if (e.ctrlKey && e.key === 'z' && !e.shiftKey) {
                e.preventDefault();
                this.undo();
            }
            // Ctrl+Y or Ctrl+Shift+Z = Redo
            if ((e.ctrlKey && e.key === 'y') || (e.ctrlKey && e.shiftKey && e.key === 'z')) {
                e.preventDefault();
                this.redo();
            }
            // Ctrl+C with element selected = Copy style
            if (e.ctrlKey && e.key === 'c' && this.selectedElement && !window.getSelection().toString()) {
                this.copyStyle();
            }
            // Ctrl+V with element selected = Paste style
            if (e.ctrlKey && e.key === 'v' && this.selectedElement && this.copiedStyles) {
                e.preventDefault();
                this.pasteStyle();
            }
            // Escape = Close panel
            if (e.key === 'Escape') {
                this.toggleEditMode();
            }
        });
    }
    
    // ========== UNDO/REDO ==========
    saveToHistory(action, element, oldValue, newValue) {
        this.undoStack.push({ action, element, oldValue, newValue, timestamp: Date.now() });
        if (this.undoStack.length > this.maxHistory) this.undoStack.shift();
        this.redoStack = [];
        this.updateUndoRedoButtons();
    }
    
    undo() {
        if (this.undoStack.length === 0) return;
        const item = this.undoStack.pop();
        this.redoStack.push(item);
        this.applyHistoryItem(item, true);
        this.updateUndoRedoButtons();
        this.showToast('Undo สำเร็จ', 'info');
    }
    
    redo() {
        if (this.redoStack.length === 0) return;
        const item = this.redoStack.pop();
        this.undoStack.push(item);
        this.applyHistoryItem(item, false);
        this.updateUndoRedoButtons();
        this.showToast('Redo สำเร็จ', 'info');
    }
    
    applyHistoryItem(item, isUndo) {
        const value = isUndo ? item.oldValue : item.newValue;
        try {
            const el = document.querySelector(item.element);
            if (!el) return;
            
            if (item.action === 'style') {
                Object.assign(el.style, value);
            } else if (item.action === 'text') {
                el.textContent = value;
            } else if (item.action === 'html') {
                el.innerHTML = value;
            } else if (item.action === 'src') {
                el.src = value;
            } else if (item.action === 'href') {
                el.href = value;
            }
        } catch (e) {}
    }
    
    updateUndoRedoButtons() {
        const undoBtn = document.getElementById('ve-undo-btn');
        const redoBtn = document.getElementById('ve-redo-btn');
        if (undoBtn) undoBtn.disabled = this.undoStack.length === 0;
        if (redoBtn) redoBtn.disabled = this.redoStack.length === 0;
    }
    
    // ========== COPY/PASTE STYLE ==========
    copyStyle() {
        if (!this.selectedElement) return;
        const computed = window.getComputedStyle(this.selectedElement);
        this.copiedStyles = {
            backgroundColor: computed.backgroundColor,
            color: computed.color,
            fontSize: computed.fontSize,
            fontFamily: computed.fontFamily,
            fontWeight: computed.fontWeight,
            textAlign: computed.textAlign,
            padding: computed.padding,
            margin: computed.margin,
            borderRadius: computed.borderRadius,
            border: computed.border,
            boxShadow: computed.boxShadow,
            opacity: computed.opacity
        };
        this.showToast('คัดลอกสไตล์แล้ว', 'success');
    }
    
    pasteStyle() {
        if (!this.selectedElement || !this.copiedStyles) return;
        
        const selector = this.generateSelector(this.selectedElement);
        const oldStyles = { ...this.selectedElement.style };
        
        Object.assign(this.selectedElement.style, this.copiedStyles);
        
        this.saveToHistory('style', selector, oldStyles, this.copiedStyles);
        this.showToast('วางสไตล์แล้ว', 'success');
    }
    
    // ========== HINT TOOLTIP ==========
    createHintTooltip() {
        this.hintTooltip = document.createElement('div');
        this.hintTooltip.className = 've-hint-tooltip';
        this.hintTooltip.innerHTML = '<strong>คลิก</strong> เลือก element | <strong>Ctrl+Z</strong> Undo | <strong>Ctrl+C/V</strong> Copy/Paste Style | <strong>ESC</strong> ปิด';
        document.body.appendChild(this.hintTooltip);
    }
    
    // ========== CHOICE BAR ==========
    createChoiceBar() {
        this.choiceBar = document.createElement('div');
        this.choiceBar.className = 've-choice-bar';
        this.choiceBar.innerHTML = `
            <span class="ve-choice-title">แก้ไข:</span>
            <button type="button" class="ve-choice-btn" data-action="text"><i class="fas fa-font"></i> ข้อความ</button>
            <button type="button" class="ve-choice-btn" data-action="style"><i class="fas fa-palette"></i> สไตล์</button>
            <button type="button" class="ve-choice-btn" data-action="code"><i class="fas fa-code"></i> HTML</button>
            <button type="button" class="ve-choice-btn ve-choice-btn-image" data-action="image" style="display:none;"><i class="fas fa-image"></i> รูป</button>
            <button type="button" class="ve-choice-btn ve-choice-btn-link" data-action="link" style="display:none;"><i class="fas fa-link"></i> Link</button>
        `;
        document.body.appendChild(this.choiceBar);
        
        this.choiceBar.querySelectorAll('.ve-choice-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const action = btn.dataset.action;
                if (action === 'text') this.openEditText();
                else if (action === 'style') this.openEditStyle();
                else if (action === 'code') this.openEditCode();
                else if (action === 'image') this.openEditImage();
                else if (action === 'link') this.openEditLink();
            });
        });
        
        this.injectChoiceBarStyles();
    }
    
    injectChoiceBarStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .ve-choice-bar {
                position: fixed; z-index: 99999999; display: none;
                flex-wrap: wrap; align-items: center; gap: 6px;
                padding: 12px 16px;
                background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                border: 2px solid rgba(102, 126, 234, 0.7);
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.6);
                font-size: 12px;
            }
            .ve-choice-bar.show { display: flex !important; animation: veChoiceIn 0.2s ease; }
            @keyframes veChoiceIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }
            .ve-choice-title { color: #aaa; margin-right: 6px; font-weight: 600; }
            .ve-choice-btn {
                display: inline-flex; align-items: center; gap: 5px;
                padding: 6px 12px;
                background: rgba(102, 126, 234, 0.3);
                border: 1px solid rgba(102, 126, 234, 0.5);
                color: #fff; border-radius: 6px; cursor: pointer;
                transition: all 0.2s; font-size: 12px;
            }
            .ve-choice-btn:hover { background: rgba(102, 126, 234, 0.6); transform: translateY(-1px); }
        `;
        document.head.appendChild(style);
    }
    
    showChoiceBar(element) {
        if (!this.choiceBar) return;
        
        const rect = element.getBoundingClientRect();
        const isMobile = window.innerWidth <= 768;
        
        this.choiceBar.classList.add('show');
        
        if (isMobile) {
            // Mobile: แสดงที่กลางด้านล่างหน้าจอ
            this.choiceBar.style.left = '50%';
            this.choiceBar.style.transform = 'translateX(-50%)';
            this.choiceBar.style.bottom = '80px';
            this.choiceBar.style.top = 'auto';
            this.choiceBar.style.position = 'fixed';
        } else {
            // Desktop: แสดงใต้ element
            this.choiceBar.style.transform = 'none';
            this.choiceBar.style.bottom = 'auto';
            this.choiceBar.style.position = 'fixed';
            
            let left = rect.left + window.scrollX;
            let top = rect.bottom + window.scrollY + 8;
            
            // ไม่ให้ล้นขอบขวา
            const barWidth = 350;
            if (left + barWidth > window.innerWidth) {
                left = window.innerWidth - barWidth - 10;
            }
            if (left < 10) left = 10;
            
            // ไม่ให้ล้นขอบล่าง
            if (top + 60 > window.innerHeight + window.scrollY) {
                top = rect.top + window.scrollY - 60;
            }
            
            this.choiceBar.style.left = left + 'px';
            this.choiceBar.style.top = top + 'px';
        }
        
        const imgBtn = this.choiceBar.querySelector('[data-action="image"]');
        const linkBtn = this.choiceBar.querySelector('[data-action="link"]');
        if (imgBtn) imgBtn.style.display = element.tagName === 'IMG' ? 'inline-flex' : 'none';
        if (linkBtn) linkBtn.style.display = element.tagName === 'A' ? 'inline-flex' : 'none';
    }
    
    hideChoiceBar() {
        if (this.choiceBar) this.choiceBar.classList.remove('show');
    }
    
    openEditText() {
        this.hideChoiceBar();
        if (!this.selectedElement) return;
        this.panel.classList.add('show');
        this.switchTab('text');
        this.updatePanelWithElement(this.selectedElement);
    }
    
    openEditStyle() {
        this.hideChoiceBar();
        if (!this.selectedElement) return;
        this.panel.classList.add('show');
        this.switchTab('style');
        this.updatePanelWithElement(this.selectedElement);
    }
    
    openEditCode() {
        this.hideChoiceBar();
        if (!this.selectedElement) return;
        this.showCodeModal(this.selectedElement);
    }
    
    openEditImage() {
        this.hideChoiceBar();
        if (!this.selectedElement) return;
        this.panel.classList.add('show');
        this.switchTab('text');
        this.updatePanelWithElement(this.selectedElement);
    }
    
    openEditLink() {
        this.hideChoiceBar();
        if (!this.selectedElement || this.selectedElement.tagName !== 'A') return;
        this.panel.classList.add('show');
        this.switchTab('text');
        this.updatePanelWithElement(this.selectedElement);
    }
    
    switchTab(tabName) {
        this.panel.querySelectorAll('.ve-tab').forEach(t => t.classList.remove('active'));
        this.panel.querySelectorAll('.ve-tab-content').forEach(c => c.classList.remove('active'));
        const tab = this.panel.querySelector(`[data-tab="${tabName}"]`);
        const content = document.getElementById(`ve-tab-${tabName}`);
        if (tab) tab.classList.add('active');
        if (content) content.classList.add('active');
    }
    
    // ========== CODE MODAL ==========
    createCodeModal() {
        const overlay = document.createElement('div');
        overlay.className = 've-code-modal-overlay';
        overlay.innerHTML = `
            <div class="ve-code-modal">
                <div class="ve-code-modal-header">
                    <h4><i class="fas fa-code"></i> แก้ไข HTML</h4>
                    <button type="button" class="ve-close-btn">&times;</button>
                </div>
                <div class="ve-code-modal-body">
                    <textarea id="ve-code-content" placeholder="<p>...</p>"></textarea>
                </div>
                <div class="ve-code-modal-footer">
                    <button type="button" class="ve-btn ve-btn-secondary ve-code-cancel">ยกเลิก</button>
                    <button type="button" class="ve-btn ve-btn-primary ve-code-save"><i class="fas fa-save"></i> บันทึก</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        
        overlay.querySelector('.ve-close-btn').addEventListener('click', () => this.hideCodeModal());
        overlay.querySelector('.ve-code-cancel').addEventListener('click', () => this.hideCodeModal());
        overlay.addEventListener('click', (e) => { if (e.target === overlay) this.hideCodeModal(); });
        overlay.querySelector('.ve-code-save').addEventListener('click', () => this.saveCodeFromModal());
        
        this.codeModal = overlay;
        this.injectCodeModalStyles();
    }
    
    injectCodeModalStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .ve-code-modal-overlay {
                position: fixed; inset: 0; background: rgba(0,0,0,0.7);
                z-index: 999999; display: none; align-items: center; justify-content: center; padding: 20px;
            }
            .ve-code-modal-overlay.show { display: flex; }
            .ve-code-modal {
                width: 100%; max-width: 700px; max-height: 85vh;
                background: #1a1a2e; border-radius: 16px; overflow: hidden;
                box-shadow: 0 20px 60px rgba(0,0,0,0.5); display: flex; flex-direction: column;
            }
            .ve-code-modal-header {
                padding: 16px 20px; background: linear-gradient(135deg, #667eea, #764ba2);
                display: flex; justify-content: space-between; align-items: center; color: #fff;
            }
            .ve-code-modal-header h4 { margin: 0; font-size: 16px; }
            .ve-code-modal-body { padding: 20px; flex: 1; overflow: hidden; display: flex; flex-direction: column; }
            .ve-code-modal-body textarea {
                width: 100%; flex: 1; min-height: 280px;
                background: #0d0d14; border: 1px solid rgba(255,255,255,0.2);
                border-radius: 8px; padding: 14px; color: #e0e0e0;
                font-family: 'Consolas', 'Monaco', monospace; font-size: 13px; resize: vertical;
            }
            .ve-code-modal-footer { padding: 16px 20px; display: flex; gap: 10px; justify-content: flex-end; }
        `;
        document.head.appendChild(style);
    }
    
    showCodeModal(element) {
        if (!this.codeModal) return;
        this.codeModal.querySelector('#ve-code-content').value = element.innerHTML || '';
        this.codeModal.classList.add('show');
    }
    
    hideCodeModal() { if (this.codeModal) this.codeModal.classList.remove('show'); }
    
    async saveCodeFromModal() {
        if (!this.selectedElement) return;
        const textarea = this.codeModal.querySelector('#ve-code-content');
        const newHtml = textarea ? textarea.value : '';
        const selector = this.generateSelector(this.selectedElement);
        const oldHtml = this.selectedElement.innerHTML;
        
        this.saveToHistory('html', selector, oldHtml, newHtml);
        this.selectedElement.innerHTML = newHtml;
        this.hideCodeModal();
        
        await this.saveToServer('save_text', { text_key: 'html:' + selector, original_text: oldHtml, custom_text: newHtml });
        this.showToast('บันทึกโค้ดเรียบร้อย', 'success');
    }
    
    // ========== UPLOAD MODAL ==========
    createUploadModal() {
        const overlay = document.createElement('div');
        overlay.className = 've-upload-modal-overlay';
        overlay.innerHTML = `
            <div class="ve-upload-modal">
                <div class="ve-code-modal-header">
                    <h4><i class="fas fa-upload"></i> อัพโหลดรูปภาพ</h4>
                    <button type="button" class="ve-close-btn">&times;</button>
                </div>
                <div class="ve-upload-modal-body">
                    <div class="ve-upload-dropzone" id="ve-dropzone">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>ลากไฟล์มาวางที่นี่ หรือคลิกเพื่อเลือกไฟล์</p>
                        <input type="file" id="ve-file-input" accept="image/*" style="display:none;">
                    </div>
                    <div class="ve-upload-preview" id="ve-upload-preview" style="display:none;">
                        <img id="ve-preview-img" src="" alt="Preview">
                    </div>
                    <div class="ve-upload-url">
                        <label>หรือใส่ URL รูปภาพ:</label>
                        <input type="text" id="ve-image-url-input" placeholder="https://...">
                    </div>
                </div>
                <div class="ve-code-modal-footer">
                    <button type="button" class="ve-btn ve-btn-secondary ve-upload-cancel">ยกเลิก</button>
                    <button type="button" class="ve-btn ve-btn-primary ve-upload-save"><i class="fas fa-save"></i> ใช้รูปนี้</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        
        const dropzone = overlay.querySelector('#ve-dropzone');
        const fileInput = overlay.querySelector('#ve-file-input');
        
        dropzone.addEventListener('click', () => fileInput.click());
        dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
        dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('dragover');
            const file = e.dataTransfer.files[0];
            if (file && file.type.startsWith('image/')) this.handleImageUpload(file);
        });
        fileInput.addEventListener('change', () => {
            if (fileInput.files[0]) this.handleImageUpload(fileInput.files[0]);
        });
        
        overlay.querySelector('.ve-close-btn').addEventListener('click', () => this.hideUploadModal());
        overlay.querySelector('.ve-upload-cancel').addEventListener('click', () => this.hideUploadModal());
        overlay.querySelector('.ve-upload-save').addEventListener('click', () => this.saveUploadedImage());
        overlay.addEventListener('click', (e) => { if (e.target === overlay) this.hideUploadModal(); });
        
        this.uploadModal = overlay;
        this.injectUploadStyles();
    }
    
    injectUploadStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .ve-upload-modal-overlay {
                position: fixed; inset: 0; background: rgba(0,0,0,0.7);
                z-index: 999999; display: none; align-items: center; justify-content: center; padding: 20px;
            }
            .ve-upload-modal-overlay.show { display: flex; }
            .ve-upload-modal { width: 100%; max-width: 500px; background: #1a1a2e; border-radius: 16px; overflow: hidden; }
            .ve-upload-modal-body { padding: 20px; }
            .ve-upload-dropzone {
                border: 2px dashed rgba(102, 126, 234, 0.5); border-radius: 12px;
                padding: 40px 20px; text-align: center; cursor: pointer;
                transition: all 0.3s; color: #aaa;
            }
            .ve-upload-dropzone:hover, .ve-upload-dropzone.dragover {
                border-color: #667eea; background: rgba(102, 126, 234, 0.1);
            }
            .ve-upload-dropzone i { font-size: 48px; color: #667eea; margin-bottom: 10px; display: block; }
            .ve-upload-preview { margin-top: 15px; text-align: center; }
            .ve-upload-preview img { max-width: 100%; max-height: 200px; border-radius: 8px; }
            .ve-upload-url { margin-top: 15px; }
            .ve-upload-url label { display: block; margin-bottom: 5px; color: #aaa; font-size: 12px; }
            .ve-upload-url input {
                width: 100%; padding: 10px; background: #0d0d14;
                border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; color: #fff;
            }
        `;
        document.head.appendChild(style);
    }
    
    showUploadModal() { if (this.uploadModal) this.uploadModal.classList.add('show'); }
    hideUploadModal() { if (this.uploadModal) this.uploadModal.classList.remove('show'); }
    
    handleImageUpload(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const preview = document.getElementById('ve-upload-preview');
            const img = document.getElementById('ve-preview-img');
            img.src = e.target.result;
            preview.style.display = 'block';
            this.uploadedImageData = e.target.result;
        };
        reader.readAsDataURL(file);
    }
    
    async saveUploadedImage() {
        const urlInput = document.getElementById('ve-image-url-input');
        const imageUrl = urlInput.value.trim() || this.uploadedImageData;
        
        if (!imageUrl) {
            this.showToast('กรุณาเลือกรูปภาพหรือใส่ URL', 'warning');
            return;
        }
        
        if (this.selectedElement && this.selectedElement.tagName === 'IMG') {
            const selector = this.generateSelector(this.selectedElement);
            const oldSrc = this.selectedElement.src;
            
            this.saveToHistory('src', selector, oldSrc, imageUrl);
            this.selectedElement.src = imageUrl;
            
            await this.saveToServer('save_text', { text_key: selector, original_text: oldSrc, custom_text: imageUrl });
            this.showToast('บันทึกรูปเรียบร้อย', 'success');
        }
        
        this.hideUploadModal();
    }
    
    // ========== RESPONSIVE PREVIEW ==========
    createResponsivePreview() {
        const preview = document.createElement('div');
        preview.className = 've-responsive-preview';
        preview.innerHTML = `
            <div class="ve-responsive-header">
                <span>Responsive Preview</span>
                <div class="ve-responsive-btns">
                    <button data-width="375" title="Mobile"><i class="fas fa-mobile-alt"></i></button>
                    <button data-width="768" title="Tablet"><i class="fas fa-tablet-alt"></i></button>
                    <button data-width="1024" title="Laptop"><i class="fas fa-laptop"></i></button>
                    <button data-width="100%" title="Desktop" class="active"><i class="fas fa-desktop"></i></button>
                </div>
                <button class="ve-close-btn ve-responsive-close">&times;</button>
            </div>
            <div class="ve-responsive-frame-container">
                <iframe id="ve-responsive-iframe" src=""></iframe>
            </div>
        `;
        document.body.appendChild(preview);
        
        preview.querySelectorAll('.ve-responsive-btns button').forEach(btn => {
            btn.addEventListener('click', () => {
                preview.querySelectorAll('.ve-responsive-btns button').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const iframe = document.getElementById('ve-responsive-iframe');
                const width = btn.dataset.width;
                iframe.style.width = width === '100%' ? '100%' : width + 'px';
            });
        });
        
        preview.querySelector('.ve-responsive-close').addEventListener('click', () => this.hideResponsivePreview());
        
        this.responsivePreview = preview;
        this.injectResponsiveStyles();
    }
    
    injectResponsiveStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .ve-responsive-preview {
                position: fixed; inset: 0; background: #0d0d14;
                z-index: 999999; display: none; flex-direction: column;
            }
            .ve-responsive-preview.show { display: flex; }
            .ve-responsive-header {
                padding: 15px 20px; background: #1a1a2e;
                display: flex; align-items: center; justify-content: space-between;
                border-bottom: 1px solid rgba(255,255,255,0.1);
            }
            .ve-responsive-header span { color: #fff; font-weight: 600; }
            .ve-responsive-btns { display: flex; gap: 8px; }
            .ve-responsive-btns button {
                width: 40px; height: 40px; border-radius: 8px;
                background: rgba(102, 126, 234, 0.2); border: 1px solid rgba(102, 126, 234, 0.3);
                color: #aaa; cursor: pointer; transition: all 0.2s;
            }
            .ve-responsive-btns button:hover, .ve-responsive-btns button.active {
                background: rgba(102, 126, 234, 0.5); color: #fff;
            }
            .ve-responsive-frame-container {
                flex: 1; display: flex; justify-content: center; padding: 20px; overflow: auto;
            }
            .ve-responsive-frame-container iframe {
                width: 100%; height: 100%; border: none; background: #fff; border-radius: 8px;
                transition: width 0.3s ease;
            }
        `;
        document.head.appendChild(style);
    }
    
    showResponsivePreview() {
        const iframe = document.getElementById('ve-responsive-iframe');
        iframe.src = window.location.href;
        this.responsivePreview.classList.add('show');
    }
    
    hideResponsivePreview() {
        this.responsivePreview.classList.remove('show');
    }
    
    // ========== TOGGLE BUTTON ==========
    createToggleButton() {
        const btn = document.createElement('div');
        btn.id = 'visual-editor-toggle';
        btn.innerHTML = `
            <button class="ve-toggle-btn" title="ปรับแต่งเว็บไซต์">
                <i class="fas fa-cog"></i>
            </button>
        `;
        btn.style.cssText = 'position: fixed; bottom: 90px; right: 24px; z-index: 999999;';
        document.body.appendChild(btn);
        
        this.injectToggleStyles();
        btn.querySelector('button').addEventListener('click', () => this.toggleEditMode());
    }
    
    injectToggleStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .ve-toggle-btn {
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white; border: none; padding: 12px 20px;
                border-radius: 50px; cursor: pointer; font-size: 14px; font-weight: 600;
                display: flex; align-items: center; gap: 8px;
                box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
                transition: all 0.3s ease;
            }
            .ve-toggle-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 25px rgba(102, 126, 234, 0.5); }
            .ve-toggle-btn.active { background: linear-gradient(135deg, #f093fb, #f5576c); }
            .ve-highlighter {
                position: fixed; pointer-events: none;
                border: 2px dashed #667eea; background: rgba(102, 126, 234, 0.1);
                z-index: 999990; transition: all 0.15s ease; display: none;
            }
            .ve-selected { outline: 3px solid #f5576c !important; outline-offset: 2px; }
            body.ve-edit-mode * { cursor: crosshair !important; }
            body.ve-edit-mode .ve-panel, body.ve-edit-mode .ve-toggle-btn,
            body.ve-edit-mode .ve-highlighter, body.ve-edit-mode .ve-hint-tooltip,
            body.ve-edit-mode .ve-choice-bar, body.ve-edit-mode .ve-code-modal-overlay { cursor: default !important; }
            .ve-hint-tooltip {
                position: fixed; bottom: 240px; right: 20px;
                background: rgba(0,0,0,0.9); color: #fff; padding: 10px 14px;
                border-radius: 10px; font-size: 11px; z-index: 999997;
                pointer-events: none; display: none; max-width: 320px;
                border: 1px solid rgba(102, 126, 234, 0.5);
            }
            body.ve-edit-mode .ve-hint-tooltip.show { display: block; }
            .ve-inline-editing { outline: 2px solid #667eea !important; outline-offset: 2px; min-height: 1em; }
        `;
        document.head.appendChild(style);
    }
    
    // ========== EDITOR PANEL ==========
    createEditorPanel() {
        this.panel = document.createElement('div');
        this.panel.className = 've-panel';
        this.panel.innerHTML = `
            <div class="ve-panel-header">
                <h4><i class="fas fa-edit"></i> Visual Editor Pro</h4>
                <button class="ve-close-btn">&times;</button>
            </div>
            <div class="ve-panel-body">
                <div class="ve-tabs">
                    <button class="ve-tab active" data-tab="style">สไตล์</button>
                    <button class="ve-tab" data-tab="text">เนื้อหา</button>
                    <button class="ve-tab" data-tab="advanced">ขั้นสูง</button>
                    <button class="ve-tab" data-tab="manage">จัดการ</button>
                </div>
                
                <!-- TAB: STYLE -->
                <div class="ve-tab-content active" id="ve-tab-style">
                    <div class="ve-selected-info">
                        <small>Element:</small>
                        <code id="ve-selected-tag">ยังไม่ได้เลือก</code>
                    </div>
                    
                    <div class="ve-section-title"><i class="fas fa-palette"></i> สี</div>
                    <div class="ve-form-row">
                        <div class="ve-form-group ve-half">
                            <label>พื้นหลัง</label>
                            <div class="ve-color-input">
                                <input type="color" id="ve-bg-color" value="#000000">
                                <input type="text" id="ve-bg-color-text" placeholder="#000000">
                            </div>
                        </div>
                        <div class="ve-form-group ve-half">
                            <label>ตัวอักษร</label>
                            <div class="ve-color-input">
                                <input type="color" id="ve-text-color" value="#ffffff">
                                <input type="text" id="ve-text-color-text" placeholder="#ffffff">
                            </div>
                        </div>
                    </div>
                    
                    <div class="ve-form-group">
                        <label>Gradient</label>
                        <div class="ve-gradient-input">
                            <input type="color" id="ve-gradient-start" value="#667eea">
                            <span>→</span>
                            <input type="color" id="ve-gradient-end" value="#764ba2">
                            <select id="ve-gradient-dir">
                                <option value="to right">→</option>
                                <option value="to left">←</option>
                                <option value="to bottom">↓</option>
                                <option value="to top">↑</option>
                                <option value="135deg">↘</option>
                            </select>
                            <button class="ve-btn-sm" id="ve-apply-gradient">ใช้</button>
                        </div>
                    </div>
                    
                    <div class="ve-section-title"><i class="fas fa-font"></i> ตัวอักษร</div>
                    <div class="ve-form-group">
                        <label>Font Family</label>
                        <select id="ve-font-family">
                            <option value="">-- ค่าเดิม --</option>
                            <option value="'Prompt', sans-serif">Prompt</option>
                            <option value="'Sarabun', sans-serif">Sarabun</option>
                            <option value="'Kanit', sans-serif">Kanit</option>
                            <option value="'Mitr', sans-serif">Mitr</option>
                            <option value="'Noto Sans Thai', sans-serif">Noto Sans Thai</option>
                            <option value="Arial, sans-serif">Arial</option>
                            <option value="'Times New Roman', serif">Times New Roman</option>
                            <option value="'Georgia', serif">Georgia</option>
                            <option value="'Courier New', monospace">Courier New</option>
                        </select>
                    </div>
                    
                    <div class="ve-form-row">
                        <div class="ve-form-group ve-half">
                            <label>ขนาด</label>
                            <div class="ve-range-input">
                                <input type="range" id="ve-font-size" min="8" max="72" value="16">
                                <span id="ve-font-size-value">16px</span>
                            </div>
                        </div>
                        <div class="ve-form-group ve-half">
                            <label>น้ำหนัก</label>
                            <select id="ve-font-weight">
                                <option value="normal">Normal</option>
                                <option value="bold">Bold</option>
                                <option value="100">100</option>
                                <option value="200">200</option>
                                <option value="300">300</option>
                                <option value="400">400</option>
                                <option value="500">500</option>
                                <option value="600">600</option>
                                <option value="700">700</option>
                                <option value="800">800</option>
                                <option value="900">900</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="ve-form-group">
                        <label>จัดตำแหน่ง</label>
                        <div class="ve-align-btns">
                            <button data-align="left" title="ชิดซ้าย"><i class="fas fa-align-left"></i></button>
                            <button data-align="center" title="กึ่งกลาง"><i class="fas fa-align-center"></i></button>
                            <button data-align="right" title="ชิดขวา"><i class="fas fa-align-right"></i></button>
                            <button data-align="justify" title="เต็มบรรทัด"><i class="fas fa-align-justify"></i></button>
                        </div>
                    </div>
                    
                    <div class="ve-section-title"><i class="fas fa-expand"></i> ขนาด/ระยะห่าง</div>
                    <div class="ve-form-group">
                        <label>Padding (บน/ขวา/ล่าง/ซ้าย)</label>
                        <div class="ve-spacing-input">
                            <input type="number" id="ve-padding-top" placeholder="0" min="0">
                            <input type="number" id="ve-padding-right" placeholder="0" min="0">
                            <input type="number" id="ve-padding-bottom" placeholder="0" min="0">
                            <input type="number" id="ve-padding-left" placeholder="0" min="0">
                        </div>
                    </div>
                    
                    <div class="ve-form-group">
                        <label>Margin (บน/ขวา/ล่าง/ซ้าย)</label>
                        <div class="ve-spacing-input">
                            <input type="number" id="ve-margin-top" placeholder="0">
                            <input type="number" id="ve-margin-right" placeholder="0">
                            <input type="number" id="ve-margin-bottom" placeholder="0">
                            <input type="number" id="ve-margin-left" placeholder="0">
                        </div>
                    </div>
                    
                    <div class="ve-form-row">
                        <div class="ve-form-group ve-half">
                            <label>Border Radius</label>
                            <div class="ve-range-input">
                                <input type="range" id="ve-border-radius" min="0" max="100" value="0">
                                <span id="ve-border-radius-value">0px</span>
                            </div>
                        </div>
                        <div class="ve-form-group ve-half">
                            <label>ความโปร่งใส</label>
                            <div class="ve-range-input">
                                <input type="range" id="ve-opacity" min="0" max="100" value="100">
                                <span id="ve-opacity-value">100%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ve-section-title"><i class="fas fa-border-all"></i> Border</div>
                    <div class="ve-form-row">
                        <div class="ve-form-group ve-third">
                            <label>ความหนา</label>
                            <input type="number" id="ve-border-width" value="0" min="0" max="20">
                        </div>
                        <div class="ve-form-group ve-third">
                            <label>สไตล์</label>
                            <select id="ve-border-style">
                                <option value="none">None</option>
                                <option value="solid">Solid</option>
                                <option value="dashed">Dashed</option>
                                <option value="dotted">Dotted</option>
                                <option value="double">Double</option>
                            </select>
                        </div>
                        <div class="ve-form-group ve-third">
                            <label>สี</label>
                            <input type="color" id="ve-border-color" value="#000000">
                        </div>
                    </div>
                    
                    <div class="ve-section-title"><i class="fas fa-clone"></i> Box Shadow</div>
                    <div class="ve-form-row">
                        <div class="ve-form-group ve-quarter">
                            <label>X</label>
                            <input type="number" id="ve-shadow-x" value="0">
                        </div>
                        <div class="ve-form-group ve-quarter">
                            <label>Y</label>
                            <input type="number" id="ve-shadow-y" value="0">
                        </div>
                        <div class="ve-form-group ve-quarter">
                            <label>Blur</label>
                            <input type="number" id="ve-shadow-blur" value="0" min="0">
                        </div>
                        <div class="ve-form-group ve-quarter">
                            <label>สี</label>
                            <input type="color" id="ve-shadow-color" value="#000000">
                        </div>
                    </div>
                    
                    <div class="ve-actions">
                        <button class="ve-btn ve-btn-icon" id="ve-undo-btn" title="Undo (Ctrl+Z)" disabled><i class="fas fa-undo"></i></button>
                        <button class="ve-btn ve-btn-icon" id="ve-redo-btn" title="Redo (Ctrl+Y)" disabled><i class="fas fa-redo"></i></button>
                        <button class="ve-btn ve-btn-icon" id="ve-copy-style-btn" title="Copy Style (Ctrl+C)"><i class="fas fa-copy"></i></button>
                        <button class="ve-btn ve-btn-icon" id="ve-paste-style-btn" title="Paste Style (Ctrl+V)"><i class="fas fa-paste"></i></button>
                        <button class="ve-btn ve-btn-primary" id="ve-save-style"><i class="fas fa-save"></i> บันทึก</button>
                        <button class="ve-btn ve-btn-secondary" id="ve-reset-style"><i class="fas fa-undo"></i> รีเซ็ต</button>
                    </div>
                </div>
                
                <!-- TAB: TEXT/CONTENT -->
                <div class="ve-tab-content" id="ve-tab-text">
                    <p class="ve-tab-hint"><small>ดับเบิลคลิกที่ข้อความบนหน้าเพื่อแก้ตรงนั้นได้เลย</small></p>
                    
                    <div class="ve-form-group" id="ve-text-group">
                        <label>แก้ไขข้อความ</label>
                        <textarea id="ve-text-content" rows="4" placeholder="คลิกเลือกองค์ประกอบก่อน"></textarea>
                    </div>
                    
                    <div class="ve-form-group" id="ve-link-group" style="display:none;">
                        <label>Link URL</label>
                        <input type="text" id="ve-link-href" placeholder="https://...">
                        <div class="ve-link-options">
                            <label><input type="checkbox" id="ve-link-blank"> เปิดในแท็บใหม่</label>
                        </div>
                    </div>
                    
                    <div class="ve-form-group" id="ve-image-group" style="display:none;">
                        <label>URL รูปภาพ</label>
                        <div class="ve-image-input">
                            <input type="text" id="ve-image-src" placeholder="https://...">
                            <button class="ve-btn-sm" id="ve-upload-btn"><i class="fas fa-upload"></i></button>
                        </div>
                        <div class="ve-image-preview" id="ve-img-preview"></div>
                    </div>
                    
                    <div class="ve-actions">
                        <button class="ve-btn ve-btn-primary" id="ve-save-text"><i class="fas fa-save"></i> บันทึกข้อความ</button>
                        <button class="ve-btn ve-btn-primary" id="ve-save-link" style="display:none;"><i class="fas fa-link"></i> บันทึก Link</button>
                        <button class="ve-btn ve-btn-primary" id="ve-save-image" style="display:none;"><i class="fas fa-image"></i> บันทึกรูป</button>
                    </div>
                </div>
                
                <!-- TAB: ADVANCED -->
                <div class="ve-tab-content" id="ve-tab-advanced">
                    <div class="ve-section-title"><i class="fas fa-magic"></i> Animation</div>
                    <div class="ve-form-group">
                        <label>เลือก Animation</label>
                        <select id="ve-animation">
                            <option value="">-- ไม่มี --</option>
                            <option value="fadeIn">Fade In</option>
                            <option value="fadeInUp">Fade In Up</option>
                            <option value="fadeInDown">Fade In Down</option>
                            <option value="slideInLeft">Slide In Left</option>
                            <option value="slideInRight">Slide In Right</option>
                            <option value="zoomIn">Zoom In</option>
                            <option value="bounce">Bounce</option>
                            <option value="pulse">Pulse</option>
                            <option value="shake">Shake</option>
                        </select>
                    </div>
                    <div class="ve-form-row">
                        <div class="ve-form-group ve-half">
                            <label>Duration</label>
                            <input type="number" id="ve-anim-duration" value="1" min="0.1" max="10" step="0.1"> <small>วินาที</small>
                        </div>
                        <div class="ve-form-group ve-half">
                            <label>Delay</label>
                            <input type="number" id="ve-anim-delay" value="0" min="0" max="10" step="0.1"> <small>วินาที</small>
                        </div>
                    </div>
                    <button class="ve-btn ve-btn-secondary" id="ve-preview-anim"><i class="fas fa-play"></i> ทดสอบ Animation</button>
                    
                    <div class="ve-section-title"><i class="fas fa-eye"></i> การแสดงผล</div>
                    <div class="ve-form-group">
                        <label>Visibility</label>
                        <div class="ve-visibility-btns">
                            <button data-vis="visible" class="active"><i class="fas fa-eye"></i> แสดง</button>
                            <button data-vis="hidden"><i class="fas fa-eye-slash"></i> ซ่อน</button>
                        </div>
                    </div>
                    
                    <div class="ve-section-title"><i class="fas fa-icons"></i> Icon (FontAwesome)</div>
                    <div class="ve-form-group">
                        <label>เลือก Icon</label>
                        <div class="ve-icon-search">
                            <input type="text" id="ve-icon-search" placeholder="ค้นหา icon...">
                        </div>
                        <div class="ve-icon-grid" id="ve-icon-grid"></div>
                    </div>
                    <button class="ve-btn ve-btn-secondary" id="ve-insert-icon"><i class="fas fa-plus"></i> แทรก Icon</button>
                    
                    <div class="ve-section-title"><i class="fas fa-mobile-alt"></i> Preview</div>
                    <button class="ve-btn ve-btn-secondary" id="ve-responsive-btn"><i class="fas fa-desktop"></i> Responsive Preview</button>
                </div>
                
                <!-- TAB: MANAGE -->
                <div class="ve-tab-content" id="ve-tab-manage">
                    <div class="ve-section-title"><i class="fas fa-history"></i> ประวัติการแก้ไข</div>
                    <div id="ve-history-list" class="ve-history-list">
                        <p class="text-muted">ยังไม่มีประวัติ</p>
                    </div>
                    
                    <div class="ve-section-title"><i class="fas fa-download"></i> Export / Import</div>
                    <div class="ve-actions">
                        <button class="ve-btn ve-btn-secondary" id="ve-export-btn"><i class="fas fa-download"></i> Export</button>
                        <button class="ve-btn ve-btn-secondary" id="ve-import-btn"><i class="fas fa-upload"></i> Import</button>
                        <input type="file" id="ve-import-file" accept=".json" style="display:none;">
                    </div>
                    
                    <div class="ve-section-title"><i class="fas fa-trash"></i> รีเซ็ต</div>
                    <div class="ve-actions">
                        <button class="ve-btn ve-btn-danger" id="ve-reset-page"><i class="fas fa-trash"></i> รีเซ็ตหน้านี้</button>
                        <button class="ve-btn ve-btn-danger" id="ve-reset-all"><i class="fas fa-bomb"></i> รีเซ็ตทั้งหมด</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(this.panel);
        this.injectPanelStyles();
        this.bindPanelEvents();
    }
    
    injectPanelStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .ve-panel {
                position: fixed; top: 80px; right: 20px; width: 340px;
                background: #1a1a2e; border-radius: 16px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.5);
                z-index: 999998; display: none;
                font-family: 'Segoe UI', sans-serif; color: #fff;
                max-height: calc(100vh - 100px); overflow: hidden;
            }
            .ve-panel.show { display: flex; flex-direction: column; animation: veSlideIn 0.3s ease; }
            @keyframes veSlideIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
            
            .ve-panel-header {
                background: linear-gradient(135deg, #667eea, #764ba2);
                padding: 12px 16px; display: flex; justify-content: space-between; align-items: center;
                flex-shrink: 0;
            }
            .ve-panel-header h4 { margin: 0; font-size: 14px; }
            .ve-close-btn { background: none; border: none; color: white; font-size: 20px; cursor: pointer; opacity: 0.8; }
            .ve-close-btn:hover { opacity: 1; }
            
            .ve-panel-body { padding: 0; flex: 1; overflow-y: auto; }
            .ve-tabs { display: flex; background: rgba(0,0,0,0.2); padding: 8px; gap: 4px; flex-shrink: 0; }
            .ve-tab {
                flex: 1; padding: 8px 4px; background: transparent; border: none;
                color: #aaa; cursor: pointer; border-radius: 6px; font-size: 11px; transition: all 0.2s;
            }
            .ve-tab:hover { background: rgba(102, 126, 234, 0.2); color: #fff; }
            .ve-tab.active { background: rgba(102, 126, 234, 0.4); color: #fff; }
            
            .ve-tab-content { display: none; padding: 12px; }
            .ve-tab-content.active { display: block; }
            
            .ve-section-title {
                font-size: 11px; font-weight: 600; color: #667eea;
                margin: 12px 0 8px; padding-bottom: 4px;
                border-bottom: 1px solid rgba(102, 126, 234, 0.3);
                display: flex; align-items: center; gap: 6px;
            }
            .ve-section-title:first-child { margin-top: 0; }
            
            .ve-selected-info { margin-bottom: 10px; padding: 8px; background: rgba(0,0,0,0.3); border-radius: 8px; }
            .ve-selected-info small { color: #888; font-size: 10px; }
            .ve-selected-info code { display: block; margin-top: 4px; color: #667eea; font-size: 11px; word-break: break-all; }
            
            .ve-form-group { margin-bottom: 10px; }
            .ve-form-group label { display: block; margin-bottom: 4px; font-size: 11px; color: #aaa; }
            .ve-form-row { display: flex; gap: 8px; }
            .ve-half { flex: 1; }
            .ve-third { flex: 1; }
            .ve-quarter { flex: 1; }
            
            .ve-form-group input[type="text"], .ve-form-group input[type="number"], .ve-form-group textarea, .ve-form-group select {
                width: 100%; padding: 8px 10px; background: rgba(0,0,0,0.3);
                border: 1px solid rgba(255,255,255,0.15); border-radius: 6px;
                color: #fff; font-size: 12px;
            }
            .ve-form-group input:focus, .ve-form-group textarea:focus, .ve-form-group select:focus {
                outline: none; border-color: #667eea;
            }
            .ve-form-group textarea { resize: vertical; min-height: 60px; }
            
            .ve-color-input { display: flex; gap: 6px; }
            .ve-color-input input[type="color"] { width: 36px; height: 32px; border: none; border-radius: 6px; cursor: pointer; }
            .ve-color-input input[type="text"] { flex: 1; }
            
            .ve-gradient-input { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
            .ve-gradient-input input[type="color"] { width: 32px; height: 28px; border: none; border-radius: 4px; }
            .ve-gradient-input span { color: #aaa; }
            .ve-gradient-input select { width: 50px; padding: 4px; }
            
            .ve-range-input { display: flex; align-items: center; gap: 8px; }
            .ve-range-input input[type="range"] { flex: 1; }
            .ve-range-input span { font-size: 11px; color: #667eea; min-width: 45px; text-align: right; }
            
            .ve-spacing-input { display: flex; gap: 4px; }
            .ve-spacing-input input { width: 25%; padding: 6px 4px; text-align: center; font-size: 11px; }
            
            .ve-align-btns, .ve-visibility-btns { display: flex; gap: 4px; }
            .ve-align-btns button, .ve-visibility-btns button {
                flex: 1; padding: 8px; background: rgba(102, 126, 234, 0.2);
                border: 1px solid rgba(102, 126, 234, 0.3); color: #aaa;
                border-radius: 6px; cursor: pointer; transition: all 0.2s;
            }
            .ve-align-btns button:hover, .ve-align-btns button.active,
            .ve-visibility-btns button:hover, .ve-visibility-btns button.active {
                background: rgba(102, 126, 234, 0.5); color: #fff;
            }
            
            .ve-image-input { display: flex; gap: 6px; }
            .ve-image-input input { flex: 1; }
            
            .ve-btn-sm {
                padding: 6px 10px; background: rgba(102, 126, 234, 0.4);
                border: none; border-radius: 6px; color: #fff; cursor: pointer;
            }
            .ve-btn-sm:hover { background: rgba(102, 126, 234, 0.6); }
            
            .ve-actions { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.1); }
            .ve-btn {
                padding: 8px 12px; border: none; border-radius: 8px;
                cursor: pointer; font-size: 12px; font-weight: 500;
                display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;
            }
            .ve-btn-icon { padding: 8px 10px; }
            .ve-btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; }
            .ve-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4); }
            .ve-btn-secondary { background: rgba(255,255,255,0.1); color: #fff; }
            .ve-btn-secondary:hover { background: rgba(255,255,255,0.2); }
            .ve-btn-danger { background: linear-gradient(135deg, #f5576c, #f093fb); color: #fff; }
            .ve-btn-danger:hover { transform: translateY(-1px); }
            .ve-btn:disabled { opacity: 0.5; cursor: not-allowed; }
            
            .ve-tab-hint { color: #888; font-size: 11px; margin: 0 0 10px; }
            
            .ve-link-options { margin-top: 6px; }
            .ve-link-options label { display: flex; align-items: center; gap: 6px; font-size: 11px; color: #aaa; }
            
            .ve-history-list { max-height: 150px; overflow-y: auto; background: rgba(0,0,0,0.2); border-radius: 8px; padding: 8px; }
            .ve-history-item { padding: 6px; border-bottom: 1px solid rgba(255,255,255,0.1); font-size: 11px; }
            .ve-history-item:last-child { border-bottom: none; }
            
            .ve-icon-search input { width: 100%; margin-bottom: 8px; }
            .ve-icon-grid {
                display: grid; grid-template-columns: repeat(6, 1fr); gap: 4px;
                max-height: 120px; overflow-y: auto; background: rgba(0,0,0,0.2);
                border-radius: 8px; padding: 8px;
            }
            .ve-icon-item {
                width: 100%; aspect-ratio: 1; display: flex; align-items: center; justify-content: center;
                background: rgba(102, 126, 234, 0.2); border-radius: 6px; cursor: pointer;
                transition: all 0.2s; font-size: 14px; color: #aaa;
            }
            .ve-icon-item:hover, .ve-icon-item.selected { background: rgba(102, 126, 234, 0.5); color: #fff; }
            
            .text-muted { color: #666; font-size: 11px; }
            
            /* Mobile Responsive */
            @media (max-width: 768px) {
                .ve-panel {
                    top: 0 !important;
                    right: 0 !important;
                    left: 0 !important;
                    width: 100% !important;
                    max-width: 100% !important;
                    height: 100vh !important;
                    max-height: 100vh !important;
                    border-radius: 0 !important;
                    z-index: 9999999 !important;
                }
                .ve-panel-body { flex: 1; overflow-y: auto; padding-bottom: 80px; }
                .ve-tabs { flex-wrap: wrap; }
                .ve-tab { padding: 10px 8px; font-size: 12px; }
                .ve-form-row { flex-direction: column; }
                .ve-half, .ve-third, .ve-quarter { width: 100%; }
                .ve-spacing-input { flex-wrap: wrap; }
                .ve-spacing-input input { width: 48%; margin-bottom: 4px; }
                .ve-actions { position: fixed; bottom: 0; left: 0; right: 0; background: #1a1a2e; padding: 12px; margin: 0; border-top: 1px solid rgba(255,255,255,0.1); }
                
                .ve-choice-bar {
                    left: 50% !important;
                    right: auto !important;
                    transform: translateX(-50%) !important;
                    bottom: 80px !important;
                    top: auto !important;
                    width: calc(100% - 20px) !important;
                    max-width: 350px !important;
                    flex-wrap: wrap;
                    justify-content: center;
                    z-index: 99999999 !important;
                }
                .ve-choice-btn { 
                    flex: 1; 
                    min-width: 70px; 
                    justify-content: center;
                    padding: 10px 8px;
                    font-size: 11px;
                }
                
                #visual-editor-toggle {
                    bottom: 140px !important;
                    right: 10px !important;
                }
                .ve-toggle-btn { padding: 10px 16px; font-size: 12px; }
                .ve-toggle-btn span { display: none; }
                
                .ve-hint-tooltip { bottom: 200px !important; }
                
                .ve-hint-tooltip { display: none !important; }
                
                .ve-code-modal { max-width: 100%; margin: 10px; max-height: calc(100vh - 20px); }
                .ve-upload-modal { max-width: 100%; margin: 10px; }
            }
            
            @media (max-width: 480px) {
                .ve-tab { font-size: 10px; padding: 8px 4px; }
                .ve-section-title { font-size: 10px; }
                .ve-form-group label { font-size: 10px; }
                .ve-btn { padding: 6px 10px; font-size: 11px; }
            }
        `;
        document.head.appendChild(style);
    }
    
    bindPanelEvents() {
        // Close button
        this.panel.querySelector('.ve-close-btn').addEventListener('click', () => this.toggleEditMode());
        
        // Tabs
        this.panel.querySelectorAll('.ve-tab').forEach(tab => {
            tab.addEventListener('click', () => this.switchTab(tab.dataset.tab));
        });
        
        // Range inputs
        this.bindRangeInput('ve-font-size', 'px');
        this.bindRangeInput('ve-border-radius', 'px');
        this.bindRangeInput('ve-opacity', '%');
        
        // Color sync
        this.bindColorSync('ve-bg-color', 've-bg-color-text');
        this.bindColorSync('ve-text-color', 've-text-color-text');
        
        // Align buttons
        this.panel.querySelectorAll('.ve-align-btns button').forEach(btn => {
            btn.addEventListener('click', () => {
                this.panel.querySelectorAll('.ve-align-btns button').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                if (this.selectedElement) this.selectedElement.style.textAlign = btn.dataset.align;
            });
        });
        
        // Visibility buttons
        this.panel.querySelectorAll('.ve-visibility-btns button').forEach(btn => {
            btn.addEventListener('click', () => {
                this.panel.querySelectorAll('.ve-visibility-btns button').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                if (this.selectedElement) {
                    this.selectedElement.style.visibility = btn.dataset.vis;
                    this.selectedElement.style.opacity = btn.dataset.vis === 'hidden' ? '0' : '1';
                }
            });
        });
        
        // Gradient
        document.getElementById('ve-apply-gradient').addEventListener('click', () => this.applyGradient());
        
        // Save buttons
        document.getElementById('ve-save-style').addEventListener('click', () => this.saveCurrentStyle());
        document.getElementById('ve-reset-style').addEventListener('click', () => this.resetCurrentStyle());
        document.getElementById('ve-save-text').addEventListener('click', () => this.saveCurrentText());
        document.getElementById('ve-save-image').addEventListener('click', () => this.saveCurrentImage());
        document.getElementById('ve-save-link').addEventListener('click', () => this.saveCurrentLink());
        
        // Undo/Redo/Copy/Paste
        document.getElementById('ve-undo-btn').addEventListener('click', () => this.undo());
        document.getElementById('ve-redo-btn').addEventListener('click', () => this.redo());
        document.getElementById('ve-copy-style-btn').addEventListener('click', () => this.copyStyle());
        document.getElementById('ve-paste-style-btn').addEventListener('click', () => this.pasteStyle());
        
        // Upload button
        document.getElementById('ve-upload-btn').addEventListener('click', () => this.showUploadModal());
        
        // Animation preview
        document.getElementById('ve-preview-anim').addEventListener('click', () => this.previewAnimation());
        
        // Responsive preview
        document.getElementById('ve-responsive-btn').addEventListener('click', () => this.showResponsivePreview());
        
        // Export/Import
        document.getElementById('ve-export-btn').addEventListener('click', () => this.exportCustomizations());
        document.getElementById('ve-import-btn').addEventListener('click', () => document.getElementById('ve-import-file').click());
        document.getElementById('ve-import-file').addEventListener('change', (e) => this.importCustomizations(e));
        
        // Reset
        document.getElementById('ve-reset-page').addEventListener('click', () => this.resetPage());
        document.getElementById('ve-reset-all').addEventListener('click', () => this.resetAll());
        
        // Icon grid
        this.populateIconGrid();
        document.getElementById('ve-icon-search').addEventListener('input', (e) => this.filterIcons(e.target.value));
        document.getElementById('ve-insert-icon').addEventListener('click', () => this.insertSelectedIcon());
    }
    
    bindRangeInput(id, unit) {
        const input = document.getElementById(id);
        const display = document.getElementById(`${id}-value`);
        if (input && display) {
            input.addEventListener('input', () => {
                display.textContent = input.value + unit;
            });
        }
    }
    
    bindColorSync(colorId, textId) {
        const colorInput = document.getElementById(colorId);
        const textInput = document.getElementById(textId);
        if (colorInput && textInput) {
            colorInput.addEventListener('input', () => textInput.value = colorInput.value);
            textInput.addEventListener('input', () => {
                if (/^#[0-9A-Fa-f]{6}$/.test(textInput.value)) {
                    colorInput.value = textInput.value;
                }
            });
        }
    }
    
    applyGradient() {
        if (!this.selectedElement) return;
        const start = document.getElementById('ve-gradient-start').value;
        const end = document.getElementById('ve-gradient-end').value;
        const dir = document.getElementById('ve-gradient-dir').value;
        this.selectedElement.style.background = `linear-gradient(${dir}, ${start}, ${end})`;
    }
    
    previewAnimation() {
        if (!this.selectedElement) return;
        const anim = document.getElementById('ve-animation').value;
        const duration = document.getElementById('ve-anim-duration').value;
        const delay = document.getElementById('ve-anim-delay').value;
        
        if (!anim) return;
        
        this.selectedElement.style.animation = `${anim} ${duration}s ease ${delay}s`;
        setTimeout(() => {
            this.selectedElement.style.animation = '';
        }, (parseFloat(duration) + parseFloat(delay) + 0.5) * 1000);
    }
    
    populateIconGrid() {
        const icons = ['home', 'user', 'cog', 'search', 'star', 'heart', 'check', 'times', 'plus', 'minus',
            'arrow-right', 'arrow-left', 'arrow-up', 'arrow-down', 'phone', 'envelope', 'map-marker-alt',
            'shopping-cart', 'credit-card', 'lock', 'unlock', 'eye', 'edit', 'trash', 'download', 'upload',
            'share', 'link', 'image', 'video', 'music', 'file', 'folder', 'calendar', 'clock', 'bell'];
        
        const grid = document.getElementById('ve-icon-grid');
        grid.innerHTML = icons.map(icon => `<div class="ve-icon-item" data-icon="${icon}"><i class="fas fa-${icon}"></i></div>`).join('');
        
        grid.querySelectorAll('.ve-icon-item').forEach(item => {
            item.addEventListener('click', () => {
                grid.querySelectorAll('.ve-icon-item').forEach(i => i.classList.remove('selected'));
                item.classList.add('selected');
                this.selectedIcon = item.dataset.icon;
            });
        });
    }
    
    filterIcons(query) {
        const items = document.querySelectorAll('.ve-icon-item');
        items.forEach(item => {
            item.style.display = item.dataset.icon.includes(query.toLowerCase()) ? 'flex' : 'none';
        });
    }
    
    insertSelectedIcon() {
        if (!this.selectedElement || !this.selectedIcon) {
            this.showToast('กรุณาเลือก element และ icon ก่อน', 'warning');
            return;
        }
        const iconHtml = `<i class="fas fa-${this.selectedIcon}"></i> `;
        this.selectedElement.innerHTML = iconHtml + this.selectedElement.innerHTML;
        this.showToast('แทรก icon แล้ว', 'success');
    }
    
    // ========== HIGHLIGHTER ==========
    createHighlighter() {
        this.highlighter = document.createElement('div');
        this.highlighter.className = 've-highlighter';
        document.body.appendChild(this.highlighter);
    }
    
    // ========== TOGGLE EDIT MODE ==========
    toggleEditMode() {
        this.isEditMode = !this.isEditMode;
        const btn = document.querySelector('.ve-toggle-btn');
        
        if (this.isEditMode) {
            btn.classList.add('active');
            btn.innerHTML = '<i class="fas fa-times"></i>';
            document.body.classList.add('ve-edit-mode');
            if (this.hintTooltip) this.hintTooltip.classList.add('show');
            this.enableElementSelection();
        } else {
            btn.classList.remove('active');
            btn.innerHTML = '<i class="fas fa-cog"></i>';
            document.body.classList.remove('ve-edit-mode');
            this.panel.classList.remove('show');
            if (this.hintTooltip) this.hintTooltip.classList.remove('show');
            if (this.highlighter) this.highlighter.style.display = 'none';
            this.hideChoiceBar();
            this.hideCodeModal();
            this.disableElementSelection();
            if (this.inlineEditingElement) {
                this.inlineEditingElement.removeAttribute('contenteditable');
                this.inlineEditingElement.classList.remove('ve-inline-editing');
                this.inlineEditingElement = null;
            }
            if (this.selectedElement) {
                this.selectedElement.classList.remove('ve-selected');
                this.selectedElement = null;
            }
        }
    }
    
    enableElementSelection() {
        this._boundMouseOver = (e) => this.handleMouseOver(e);
        this._boundMouseOut = (e) => this.handleMouseOut(e);
        this._boundClick = (e) => this.handleClick(e);
        this._boundDblClick = (e) => this.handleDoubleClick(e);
        this._boundTouchEnd = (e) => this.handleTouchEnd(e);
        
        document.addEventListener('mouseover', this._boundMouseOver);
        document.addEventListener('mouseout', this._boundMouseOut);
        document.addEventListener('click', this._boundClick);
        document.addEventListener('dblclick', this._boundDblClick);
        
        // Touch support for mobile
        document.addEventListener('touchend', this._boundTouchEnd, { passive: false });
    }
    
    disableElementSelection() {
        document.removeEventListener('mouseover', this._boundMouseOver);
        document.removeEventListener('mouseout', this._boundMouseOut);
        document.removeEventListener('click', this._boundClick);
        document.removeEventListener('touchend', this._boundTouchEnd);
        document.removeEventListener('dblclick', this._boundDblClick);
    }
    
    handleMouseOver(e) {
        const target = e.target;
        if (target.closest('.ve-panel') || target.closest('#visual-editor-toggle') || 
            target.closest('.ve-choice-bar') || target.closest('.ve-code-modal-overlay') ||
            target.closest('.ve-upload-modal-overlay') || target.closest('.ve-responsive-preview') ||
            target.classList.contains('ve-highlighter')) {
            this.highlighter.style.display = 'none';
            return;
        }
        
        this.hoveredElement = target;
        const rect = target.getBoundingClientRect();
        this.highlighter.style.display = 'block';
        this.highlighter.style.left = rect.left + window.scrollX + 'px';
        this.highlighter.style.top = rect.top + window.scrollY + 'px';
        this.highlighter.style.width = rect.width + 'px';
        this.highlighter.style.height = rect.height + 'px';
    }
    
    handleMouseOut(e) {
        if (!e.relatedTarget || e.relatedTarget === document.documentElement) {
            this.highlighter.style.display = 'none';
        }
    }
    
    handleClick(e) {
        const target = e.target;
        if (target.closest('.ve-panel') || target.closest('#visual-editor-toggle') || 
            target.closest('.ve-choice-bar') || target.closest('.ve-code-modal-overlay') ||
            target.closest('.ve-upload-modal-overlay') || target.closest('.ve-responsive-preview') ||
            target.classList.contains('ve-hint-tooltip')) {
            return;
        }
        
        e.preventDefault();
        e.stopPropagation();
        
        if (this.selectedElement) {
            this.selectedElement.classList.remove('ve-selected');
        }
        
        this.selectedElement = target;
        this.selectedElement.classList.add('ve-selected');
        this.updatePanelWithElement(target);
        this.showChoiceBar(target);
    }
    
    handleTouchEnd(e) {
        // Handle touch on mobile
        if (!e.changedTouches || e.changedTouches.length === 0) return;
        
        const touch = e.changedTouches[0];
        const target = document.elementFromPoint(touch.clientX, touch.clientY);
        
        if (!target) return;
        
        // ข้าม VE elements
        if (target.closest('.ve-panel') || target.closest('#visual-editor-toggle') || 
            target.closest('.ve-choice-bar') || target.closest('.ve-code-modal-overlay') ||
            target.closest('.ve-upload-modal-overlay') || target.closest('.ve-responsive-preview') ||
            target.classList.contains('ve-hint-tooltip')) {
            return;
        }
        
        e.preventDefault();
        
        // Select element and show choice bar
        if (this.selectedElement) {
            this.selectedElement.classList.remove('ve-selected');
        }
        
        this.selectedElement = target;
        this.selectedElement.classList.add('ve-selected');
        this.updatePanelWithElement(target);
        this.showChoiceBar(target);
    }
    
    handleDoubleClick(e) {
        const target = e.target;
        if (target.closest('.ve-panel') || target.closest('#visual-editor-toggle') || 
            target.closest('.ve-choice-bar') || target.closest('.ve-code-modal-overlay') ||
            target.closest('.ve-upload-modal-overlay') || target.closest('.ve-responsive-preview') ||
            target.classList.contains('ve-hint-tooltip')) {
            return;
        }
        
        e.preventDefault();
        e.stopPropagation();
        
        if (target.tagName === 'IMG' || target.tagName === 'VIDEO' || target.tagName === 'IFRAME') return;
        
        this.hideChoiceBar();
        if (this.inlineEditingElement) {
            this.inlineEditingElement.removeAttribute('contenteditable');
            this.inlineEditingElement.classList.remove('ve-inline-editing');
        }
        
        this.inlineEditingElement = target;
        this._inlineOriginalText = target.textContent;
        target.setAttribute('contenteditable', 'true');
        target.classList.add('ve-inline-editing');
        target.focus();
        
        const selection = window.getSelection();
        const range = document.createRange();
        range.selectNodeContents(target);
        selection.removeAllRanges();
        selection.addRange(range);
        
        const blurHandler = () => {
            this.saveInlineEdit(target);
            target.removeEventListener('blur', blurHandler);
        };
        target.addEventListener('blur', blurHandler);
    }
    
    async saveInlineEdit(element) {
        element.removeAttribute('contenteditable');
        element.classList.remove('ve-inline-editing');
        
        const newText = element.textContent.trim();
        const selector = this.generateSelector(element);
        const originalText = this._inlineOriginalText != null ? this._inlineOriginalText.trim() : newText;
        
        if (newText !== originalText) {
            this.saveToHistory('text', selector, originalText, newText);
            await this.saveToServer('save_text', { text_key: selector, original_text: originalText, custom_text: newText });
            this.showToast('บันทึกข้อความเรียบร้อย', 'success');
        }
        
        this.inlineEditingElement = null;
        this._inlineOriginalText = null;
    }
    
    // ========== UPDATE PANEL ==========
    updatePanelWithElement(element) {
        const selector = this.generateSelector(element);
        document.getElementById('ve-selected-tag').textContent = selector;
        
        const computed = window.getComputedStyle(element);
        
        // Colors
        document.getElementById('ve-bg-color').value = this.rgbToHex(computed.backgroundColor);
        document.getElementById('ve-bg-color-text').value = this.rgbToHex(computed.backgroundColor);
        document.getElementById('ve-text-color').value = this.rgbToHex(computed.color);
        document.getElementById('ve-text-color-text').value = this.rgbToHex(computed.color);
        
        // Font
        document.getElementById('ve-font-size').value = parseInt(computed.fontSize) || 16;
        document.getElementById('ve-font-size-value').textContent = parseInt(computed.fontSize) + 'px';
        document.getElementById('ve-font-weight').value = computed.fontWeight;
        
        // Spacing
        document.getElementById('ve-padding-top').value = parseInt(computed.paddingTop) || 0;
        document.getElementById('ve-padding-right').value = parseInt(computed.paddingRight) || 0;
        document.getElementById('ve-padding-bottom').value = parseInt(computed.paddingBottom) || 0;
        document.getElementById('ve-padding-left').value = parseInt(computed.paddingLeft) || 0;
        
        document.getElementById('ve-margin-top').value = parseInt(computed.marginTop) || 0;
        document.getElementById('ve-margin-right').value = parseInt(computed.marginRight) || 0;
        document.getElementById('ve-margin-bottom').value = parseInt(computed.marginBottom) || 0;
        document.getElementById('ve-margin-left').value = parseInt(computed.marginLeft) || 0;
        
        // Border
        document.getElementById('ve-border-radius').value = parseInt(computed.borderRadius) || 0;
        document.getElementById('ve-border-radius-value').textContent = parseInt(computed.borderRadius) + 'px';
        document.getElementById('ve-border-width').value = parseInt(computed.borderWidth) || 0;
        document.getElementById('ve-border-style').value = computed.borderStyle || 'none';
        document.getElementById('ve-border-color').value = this.rgbToHex(computed.borderColor);
        
        // Opacity
        document.getElementById('ve-opacity').value = parseFloat(computed.opacity) * 100 || 100;
        document.getElementById('ve-opacity-value').textContent = Math.round(parseFloat(computed.opacity) * 100) + '%';
        
        // Text align
        this.panel.querySelectorAll('.ve-align-btns button').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.align === computed.textAlign);
        });
        
        // Content tab updates
        if (element.tagName === 'IMG') {
            document.getElementById('ve-text-group').style.display = 'none';
            document.getElementById('ve-link-group').style.display = 'none';
            document.getElementById('ve-image-group').style.display = 'block';
            document.getElementById('ve-save-text').style.display = 'none';
            document.getElementById('ve-save-link').style.display = 'none';
            document.getElementById('ve-save-image').style.display = 'inline-flex';
            document.getElementById('ve-image-src').value = element.src || '';
        } else if (element.tagName === 'A') {
            document.getElementById('ve-text-group').style.display = 'block';
            document.getElementById('ve-link-group').style.display = 'block';
            document.getElementById('ve-image-group').style.display = 'none';
            document.getElementById('ve-save-text').style.display = 'inline-flex';
            document.getElementById('ve-save-link').style.display = 'inline-flex';
            document.getElementById('ve-save-image').style.display = 'none';
            document.getElementById('ve-text-content').value = element.textContent.trim();
            document.getElementById('ve-link-href').value = element.href || '';
            document.getElementById('ve-link-blank').checked = element.target === '_blank';
        } else {
            document.getElementById('ve-text-group').style.display = 'block';
            document.getElementById('ve-link-group').style.display = 'none';
            document.getElementById('ve-image-group').style.display = 'none';
            document.getElementById('ve-save-text').style.display = 'inline-flex';
            document.getElementById('ve-save-link').style.display = 'none';
            document.getElementById('ve-save-image').style.display = 'none';
            document.getElementById('ve-text-content').value = element.textContent.trim().substring(0, 1000);
        }
        
        this.updateHistoryList();
    }
    
    updateHistoryList() {
        const list = document.getElementById('ve-history-list');
        if (this.undoStack.length === 0) {
            list.innerHTML = '<p class="text-muted">ยังไม่มีประวัติ</p>';
            return;
        }
        
        list.innerHTML = this.undoStack.slice(-10).reverse().map((item, i) => `
            <div class="ve-history-item">
                <strong>${item.action}</strong>: ${item.element.substring(0, 30)}...
            </div>
        `).join('');
    }
    
    // ========== GENERATE SELECTOR ==========
    generateSelector(element) {
        // 1. ถ้ามี data-editable ใช้เลย (stable ที่สุด)
        if (element.dataset.editable) return element.dataset.editable;
        if (element.dataset.editableHtml) return 'html:' + element.dataset.editableHtml;
        if (element.dataset.editableImg) return 'img:' + element.dataset.editableImg;
        
        // 2. ถ้ามี ID ใช้ ID (ยกเว้น ID ที่ VE สร้างขึ้น)
        if (element.id && !element.id.startsWith('ve-')) return '#' + element.id;
        
        // 3. สร้าง stable ID จาก hash ของ element info
        const elementInfo = this.getElementInfo(element);
        const hash = this.hashString(elementInfo);
        const stableId = 've-' + hash;
        
        // Assign ID ให้ element
        element.id = stableId;
        
        return '#' + stableId;
    }
    
    // สร้าง info string จาก element (ใช้สร้าง hash)
    getElementInfo(element) {
        const tag = element.tagName.toLowerCase();
        const text = (element.textContent || '').trim().substring(0, 30).replace(/\s+/g, ' ');
        const classes = (element.className || '').toString().split(' ').filter(c => c && !c.startsWith('ve-')).sort().join('.');
        
        // หา position ใน parent
        let position = 0;
        if (element.parentElement) {
            const siblings = Array.from(element.parentElement.children);
            position = siblings.indexOf(element);
        }
        
        // หา parent info
        let parentInfo = '';
        if (element.parentElement) {
            const parent = element.parentElement;
            if (parent.id) parentInfo = '#' + parent.id;
            else if (parent.className) parentInfo = parent.className.toString().split(' ')[0] || '';
        }
        
        return `${tag}|${classes}|${position}|${parentInfo}|${text}`;
    }
    
    // Simple hash function
    hashString(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32bit integer
        }
        return Math.abs(hash).toString(36);
    }
    
    // ========== SAVE FUNCTIONS ==========
    async saveCurrentStyle() {
        if (!this.selectedElement) {
            this.showToast('กรุณาเลือก element ก่อน', 'warning');
            return;
        }
        
        const selector = this.generateSelector(this.selectedElement);
        const oldStyles = { ...this.selectedElement.style };
        
        const styles = {
            'background-color': document.getElementById('ve-bg-color').value,
            'color': document.getElementById('ve-text-color').value,
            'font-family': document.getElementById('ve-font-family').value,
            'font-size': document.getElementById('ve-font-size').value + 'px',
            'font-weight': document.getElementById('ve-font-weight').value,
            'padding': `${document.getElementById('ve-padding-top').value}px ${document.getElementById('ve-padding-right').value}px ${document.getElementById('ve-padding-bottom').value}px ${document.getElementById('ve-padding-left').value}px`,
            'margin': `${document.getElementById('ve-margin-top').value}px ${document.getElementById('ve-margin-right').value}px ${document.getElementById('ve-margin-bottom').value}px ${document.getElementById('ve-margin-left').value}px`,
            'border-radius': document.getElementById('ve-border-radius').value + 'px',
            'border': `${document.getElementById('ve-border-width').value}px ${document.getElementById('ve-border-style').value} ${document.getElementById('ve-border-color').value}`,
            'box-shadow': `${document.getElementById('ve-shadow-x').value}px ${document.getElementById('ve-shadow-y').value}px ${document.getElementById('ve-shadow-blur').value}px ${document.getElementById('ve-shadow-color').value}`,
            'opacity': document.getElementById('ve-opacity').value / 100
        };
        
        // Apply immediately
        Object.entries(styles).forEach(([prop, value]) => {
            if (value && value !== 'px' && value !== '0px 0px 0px 0px' && value !== '0px none #000000') {
                this.selectedElement.style.setProperty(prop, value);
            }
        });
        
        this.saveToHistory('style', selector, oldStyles, styles);
        
        // Save to server
        for (const [property, value] of Object.entries(styles)) {
            if (value && value !== 'px') {
                await this.saveToServer('save_style', { selector, property, value });
            }
        }
        
        this.showToast('บันทึกสไตล์เรียบร้อย', 'success');
        
        // ปิด panel หลังบันทึก (รอให้ toast แสดงก่อน)
        setTimeout(() => {
            this.panel.classList.remove('show');
        }, 800);
    }
    
    resetCurrentStyle() {
        if (!this.selectedElement) return;
        this.selectedElement.removeAttribute('style');
        this.updatePanelWithElement(this.selectedElement);
        this.showToast('รีเซ็ตสไตล์แล้ว', 'success');
    }
    
    async saveCurrentText() {
        if (!this.selectedElement) return;
        
        const selector = this.generateSelector(this.selectedElement);
        const oldText = this.selectedElement.textContent.trim();
        const newText = document.getElementById('ve-text-content').value;
        
        this.saveToHistory('text', selector, oldText, newText);
        this.selectedElement.textContent = newText;
        
        await this.saveToServer('save_text', { text_key: selector, original_text: oldText, custom_text: newText });
        this.showToast('บันทึกข้อความเรียบร้อย', 'success');
        setTimeout(() => this.panel.classList.remove('show'), 800);
    }
    
    async saveCurrentImage() {
        if (!this.selectedElement || this.selectedElement.tagName !== 'IMG') return;
        
        const selector = this.generateSelector(this.selectedElement);
        const oldSrc = this.selectedElement.src;
        const newSrc = document.getElementById('ve-image-src').value.trim();
        
        this.saveToHistory('src', selector, oldSrc, newSrc);
        this.selectedElement.src = newSrc;
        
        await this.saveToServer('save_text', { text_key: selector, original_text: oldSrc, custom_text: newSrc });
        this.showToast('บันทึกรูปเรียบร้อย', 'success');
        setTimeout(() => this.panel.classList.remove('show'), 800);
    }
    
    async saveCurrentLink() {
        if (!this.selectedElement || this.selectedElement.tagName !== 'A') return;
        
        const selector = this.generateSelector(this.selectedElement);
        const oldHref = this.selectedElement.href;
        const newHref = document.getElementById('ve-link-href').value.trim();
        const newTarget = document.getElementById('ve-link-blank').checked ? '_blank' : '';
        
        this.saveToHistory('href', selector, oldHref, newHref);
        this.selectedElement.href = newHref;
        this.selectedElement.target = newTarget;
        
        await this.saveToServer('save_text', { text_key: 'link:' + selector, original_text: oldHref, custom_text: newHref });
        this.showToast('บันทึก Link เรียบร้อย', 'success');
        setTimeout(() => this.panel.classList.remove('show'), 800);
    }
    
    async saveToServer(action, data) {
        try {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('page', this.currentPage);
            Object.entries(data).forEach(([key, value]) => formData.append(key, value));
            
            console.log('[VE Save] Sending:', action, data, 'page:', this.currentPage);
            
            const response = await fetch(this.getVeUrl('controller/visual_editor.php'), { method: 'POST', body: formData });
            const result = await response.json();
            
            console.log('[VE Save] Response:', result);
            
            if (!result.success) {
                console.error('[VE Save] Error:', result.message);
                this.showToast('บันทึกล้มเหลว: ' + (result.message || 'Unknown error'), 'error');
            }
            
            return result;
        } catch (e) {
            console.error('[VE Save] Error:', e);
            this.showToast('บันทึกล้มเหลว', 'error');
            return { success: false };
        }
    }
    
    // ========== EXPORT/IMPORT ==========
    async exportCustomizations() {
        try {
            const response = await fetch(this.getVeUrl('controller/visual_editor.php') + '?action=get_all_customizations');
            const data = await response.json();
            
            if (data.success) {
                const blob = new Blob([JSON.stringify(data.data, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `ve-customizations-${this.currentPage}-${Date.now()}.json`;
                a.click();
                URL.revokeObjectURL(url);
                this.showToast('Export สำเร็จ', 'success');
            }
        } catch (e) {
            this.showToast('Export ล้มเหลว', 'error');
        }
    }
    
    async importCustomizations(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        try {
            const text = await file.text();
            const data = JSON.parse(text);
            
            // Import styles
            if (data.styles) {
                for (const style of data.styles) {
                    await this.saveToServer('save_style', {
                        selector: style.selector,
                        property: style.property_name,
                        value: style.property_value
                    });
                }
            }
            
            // Import texts
            if (data.texts) {
                for (const text of data.texts) {
                    await this.saveToServer('save_text', {
                        text_key: text.text_key,
                        original_text: text.original_text,
                        custom_text: text.custom_text
                    });
                }
            }
            
            this.showToast('Import สำเร็จ - รีเฟรชหน้าเพื่อดูผล', 'success');
        } catch (e) {
            this.showToast('Import ล้มเหลว - ไฟล์ไม่ถูกต้อง', 'error');
        }
        
        e.target.value = '';
    }
    
    // ========== RESET ==========
    async resetPage() {
        if (!confirm('รีเซ็ตการปรับแต่งทั้งหมดของหน้านี้?')) return;
        
        try {
            const formData = new FormData();
            formData.append('action', 'reset_all');
            formData.append('page', this.currentPage);
            
            await fetch(this.getVeUrl('controller/visual_editor.php'), { method: 'POST', body: formData });
            this.showToast('รีเซ็ตหน้านี้เรียบร้อย', 'success');
            setTimeout(() => location.reload(), 1000);
        } catch (e) {
            this.showToast('เกิดข้อผิดพลาด', 'error');
        }
    }
    
    async resetAll() {
        if (!confirm('รีเซ็ตการปรับแต่งทั้งหมดของทุกหน้า?')) return;
        
        try {
            const formData = new FormData();
            formData.append('action', 'reset_all');
            
            await fetch(this.getVeUrl('controller/visual_editor.php'), { method: 'POST', body: formData });
            this.showToast('รีเซ็ตทั้งหมดเรียบร้อย', 'success');
            setTimeout(() => location.reload(), 1000);
        } catch (e) {
            this.showToast('เกิดข้อผิดพลาด', 'error');
        }
    }
    
    // ========== HELPERS ==========
    rgbToHex(rgb) {
        if (!rgb || rgb === 'transparent' || rgb === 'rgba(0, 0, 0, 0)') return '#000000';
        const match = rgb.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/);
        if (!match) return '#000000';
        return '#' + [match[1], match[2], match[3]].map(x => parseInt(x).toString(16).padStart(2, '0')).join('');
    }
    
    showToast(message, type = 'info') {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ toast: true, position: 'top-end', icon: type, title: message, showConfirmButton: false, timer: 2000 });
        } else {
            console.log(`[VE] ${type}: ${message}`);
        }
    }
}

// Animation CSS
const animStyle = document.createElement('style');
animStyle.textContent = `
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
@keyframes fadeInDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
@keyframes slideInLeft { from { opacity: 0; transform: translateX(-50px); } to { opacity: 1; transform: translateX(0); } }
@keyframes slideInRight { from { opacity: 0; transform: translateX(50px); } to { opacity: 1; transform: translateX(0); } }
@keyframes zoomIn { from { opacity: 0; transform: scale(0.5); } to { opacity: 1; transform: scale(1); } }
@keyframes bounce { 0%, 20%, 50%, 80%, 100% { transform: translateY(0); } 40% { transform: translateY(-20px); } 60% { transform: translateY(-10px); } }
@keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }
@keyframes shake { 0%, 100% { transform: translateX(0); } 10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); } 20%, 40%, 60%, 80% { transform: translateX(5px); } }
`;
document.head.appendChild(animStyle);

// Initialize
if (typeof window.IS_ADMIN !== 'undefined' && window.IS_ADMIN) {
    document.addEventListener('DOMContentLoaded', () => new VisualEditor());
}

} // End of VisualEditorLoaded check
