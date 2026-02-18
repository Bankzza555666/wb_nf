<?php
// home/contact.php - Modern Chat Interface V2.0
include 'home/header.php';
include 'home/navbar.php';

if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location.href = '?r=landing';</script>";
    exit;
}

// ✅ CSRF Token for AJAX
$csrf_token = generateCsrfToken();
?>
<script>
    const CSRF_TOKEN = "<?php echo $csrf_token; ?>";
</script>

<style>
    /* ===== Modern Chat Theme V2.0 ===== */
    :root {
        --chat-bg: #050508;
        --chat-card: rgba(15, 15, 22, 0.95);
        --chat-border: rgba(255, 255, 255, 0.06);
        --chat-text: #ffffff;
        --chat-muted: rgba(255, 255, 255, 0.5);
        --chat-accent: #E50914;
        --chat-accent-soft: rgba(229, 9, 20, 0.15);
        --msg-user: linear-gradient(135deg, #E50914, #b91c1c);
        --msg-admin: rgba(30, 30, 40, 0.95);
    }

    .chat-page {
        background: var(--chat-bg);
        height: 100dvh;
        overflow: hidden;
        font-family: 'Prompt', 'Segoe UI', sans-serif;
    }

    /* Animated Background */
    .chat-page::before {
        content: '';
        position: fixed;
        inset: 0;
        background: 
            radial-gradient(ellipse 80% 50% at 20% 30%, rgba(229, 9, 20, 0.05) 0%, transparent 50%),
            radial-gradient(ellipse 60% 40% at 80% 70%, rgba(99, 102, 241, 0.04) 0%, transparent 50%);
        pointer-events: none;
        z-index: 0;
    }

    .chat-container {
        height: calc(100dvh - 70px);
        max-width: 900px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        background: var(--chat-card);
        backdrop-filter: blur(20px);
        border-left: 1px solid var(--chat-border);
        border-right: 1px solid var(--chat-border);
        position: relative;
        z-index: 1;
    }

    /* Header */
    .chat-header-v2 {
        height: 75px;
        padding: 0 24px;
        background: linear-gradient(135deg, rgba(20, 20, 30, 0.98), rgba(15, 15, 25, 0.98));
        border-bottom: 1px solid var(--chat-border);
        display: flex;
        align-items: center;
        gap: 16px;
        flex-shrink: 0;
        backdrop-filter: blur(20px);
    }

    .support-avatar {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #f59e0b, #d97706);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.4rem;
        box-shadow: 0 8px 25px rgba(245, 158, 11, 0.3);
        position: relative;
    }

    .support-avatar::after {
        content: '';
        position: absolute;
        bottom: 3px;
        right: 3px;
        width: 12px;
        height: 12px;
        background: #10b981;
        border-radius: 50%;
        border: 2px solid var(--chat-card);
        animation: pulse-dot 2s infinite;
    }

    @keyframes pulse-dot {
        0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
        50% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
    }

    .support-info h5 {
        font-weight: 700;
        color: var(--chat-text);
        margin: 0;
        font-size: 1.15rem;
    }

    .support-status {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.8rem;
        color: #10b981;
        font-weight: 500;
    }

    .status-dot-v2 {
        width: 8px;
        height: 8px;
        background: #10b981;
        border-radius: 50%;
    }

    /* Chat Body */
    .chat-body-v2 {
        flex: 1;
        padding: 24px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 16px;
        -webkit-overflow-scrolling: touch;
    }

    /* Message Rows */
    .msg-row-v2 {
        display: flex;
        width: 100%;
        animation: msgSlide 0.3s ease;
    }

    @keyframes msgSlide {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .msg-row-v2.user {
        justify-content: flex-end;
    }

    .msg-row-v2.admin {
        justify-content: flex-start;
    }

    /* Message Bubbles */
    .msg-bubble-v2 {
        max-width: 75%;
        padding: 14px 18px;
        border-radius: 20px;
        font-size: 0.95rem;
        line-height: 1.6;
        position: relative;
        word-wrap: break-word;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
    }

    .msg-row-v2.user .msg-bubble-v2 {
        background: var(--msg-user);
        color: white;
        border-bottom-right-radius: 6px;
    }

    .msg-row-v2.admin .msg-bubble-v2 {
        background: var(--msg-admin);
        color: var(--chat-text);
        border: 1px solid var(--chat-border);
        border-bottom-left-radius: 6px;
    }

    .msg-meta-v2 {
        font-size: 0.7rem;
        opacity: 0.7;
        margin-top: 6px;
        text-align: right;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 5px;
    }

    .msg-row-v2.user .msg-meta-v2 {
        color: rgba(255, 255, 255, 0.8);
    }

    .msg-row-v2.admin .msg-meta-v2 {
        color: var(--chat-muted);
    }

    /* Badges */
    .sender-badge-v2 {
        font-size: 0.7rem;
        padding: 3px 10px;
        border-radius: 20px;
        margin-bottom: 6px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .badge-ai-v2 {
        background: rgba(16, 185, 129, 0.15);
        color: #34d399;
        border: 1px solid rgba(16, 185, 129, 0.25);
    }

    .badge-admin-v2 {
        background: rgba(245, 158, 11, 0.15);
        color: #fbbf24;
        border: 1px solid rgba(245, 158, 11, 0.25);
    }

    /* Action Buttons */
    .chat-action-btn-v2 {
        display: block;
        width: 100%;
        margin-top: 12px;
        padding: 12px 18px;
        border-radius: 14px;
        border: none;
        color: white;
        font-weight: 600;
        cursor: pointer;
        text-align: center;
        text-decoration: none;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }

    .btn-pay-v2 {
        background: linear-gradient(135deg, #10b981, #059669);
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    }

    .btn-vpn-v2 {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
    }

    .btn-nav-v2 {
        background: linear-gradient(135deg, #8b5cf6, #6d28d9);
        box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);
    }

    .chat-action-btn-v2:hover {
        transform: translateY(-2px);
        filter: brightness(1.1);
    }

    /* Images */
    .chat-image-v2 {
        max-width: 100%;
        border-radius: 14px;
        margin-top: 10px;
        border: 1px solid var(--chat-border);
        cursor: pointer;
        transition: transform 0.2s;
    }

    .chat-image-v2:hover {
        transform: scale(1.02);
    }

    /* Footer / Input */
    .chat-footer-v2 {
        padding: 18px 24px;
        background: linear-gradient(180deg, rgba(15, 15, 25, 0.98), rgba(10, 10, 18, 0.98));
        border-top: 1px solid var(--chat-border);
        flex-shrink: 0;
    }

    .input-group-v2 {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--chat-border);
        border-radius: 28px;
        padding: 6px 8px 6px 18px;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all 0.3s ease;
    }

    .input-group-v2:focus-within {
        border-color: var(--chat-accent);
        background: rgba(229, 9, 20, 0.03);
        box-shadow: 0 0 0 3px var(--chat-accent-soft);
    }

    .btn-upload-v2 {
        color: var(--chat-muted);
        cursor: pointer;
        font-size: 1.2rem;
        transition: all 0.2s;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }

    .btn-upload-v2:hover {
        color: var(--chat-accent);
        background: var(--chat-accent-soft);
    }

    .chat-input-v2 {
        flex: 1;
        background: transparent;
        border: none;
        color: var(--chat-text);
        outline: none;
        padding: 12px 0;
        font-size: 16px;
        min-width: 0;
        font-family: inherit;
    }

    .chat-input-v2::placeholder {
        color: var(--chat-muted);
    }

    .btn-send-v2 {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        border: none;
        background: var(--chat-accent);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1.1rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(229, 9, 20, 0.3);
    }

    .btn-send-v2:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 20px rgba(229, 9, 20, 0.4);
    }

    .btn-send-v2:active {
        transform: scale(0.95);
    }

    /* File Preview */
    .file-preview-v2 {
        display: none;
        align-items: center;
        gap: 8px;
        margin-top: 10px;
        padding: 8px 14px;
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid rgba(16, 185, 129, 0.2);
        border-radius: 10px;
        color: #34d399;
        font-size: 0.85rem;
    }

    .file-preview-v2.show {
        display: flex;
    }

    /* Suggestions */
    .suggestions-v2 {
        display: none;
        padding: 12px 24px;
        gap: 10px;
        overflow-x: auto;
        white-space: nowrap;
        background: rgba(10, 10, 15, 0.95);
        border-top: 1px solid var(--chat-border);
        scrollbar-width: none;
    }

    .suggestions-v2::-webkit-scrollbar {
        display: none;
    }

    .chip-v2 {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--chat-border);
        color: var(--chat-text);
        padding: 8px 16px;
        border-radius: 24px;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s ease;
        font-weight: 500;
    }

    .chip-v2:hover {
        background: var(--chat-accent);
        border-color: var(--chat-accent);
        transform: translateY(-2px);
    }

    /* Loading State */
    .loading-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: var(--chat-muted);
        gap: 16px;
    }

    .loading-spinner {
        width: 50px;
        height: 50px;
        border: 3px solid rgba(229, 9, 20, 0.1);
        border-top-color: var(--chat-accent);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Empty State */
    .empty-chat-v2 {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: var(--chat-muted);
        text-align: center;
        padding: 40px;
    }

    .empty-icon-v2 {
        width: 100px;
        height: 100px;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        margin-bottom: 20px;
        color: var(--chat-muted);
    }

    .empty-chat-v2 h6 {
        color: var(--chat-text);
        font-weight: 600;
        margin-bottom: 8px;
    }

    /* Emoji Picker */
    .emoji-btn-v2 {
        color: var(--chat-muted);
        cursor: pointer;
        font-size: 1.2rem;
        transition: all 0.2s;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }

    .emoji-btn-v2:hover {
        color: #fbbf24;
        background: rgba(251, 191, 36, 0.1);
    }

    .emoji-picker-v2 {
        display: none;
        position: absolute;
        bottom: 100%;
        left: 24px;
        right: 24px;
        background: var(--chat-card);
        border: 1px solid var(--chat-border);
        border-radius: 16px;
        padding: 16px;
        margin-bottom: 10px;
        max-height: 250px;
        overflow-y: auto;
        z-index: 100;
        box-shadow: 0 -10px 40px rgba(0,0,0,0.4);
    }

    .emoji-picker-v2.show {
        display: grid;
        grid-template-columns: repeat(8, 1fr);
        gap: 8px;
    }

    .emoji-item {
        font-size: 1.5rem;
        cursor: pointer;
        text-align: center;
        padding: 8px;
        border-radius: 8px;
        transition: all 0.2s;
    }

    .emoji-item:hover {
        background: var(--chat-accent-soft);
        transform: scale(1.2);
    }

    /* Typing Indicator */
    .typing-indicator {
        display: none;
        align-items: center;
        gap: 8px;
        padding: 12px 18px;
        background: var(--msg-admin);
        border: 1px solid var(--chat-border);
        border-radius: 20px;
        border-bottom-left-radius: 6px;
        width: fit-content;
        margin-bottom: 16px;
    }

    .typing-indicator.show {
        display: flex;
    }

    .typing-dots {
        display: flex;
        gap: 4px;
    }

    .typing-dot {
        width: 8px;
        height: 8px;
        background: var(--chat-muted);
        border-radius: 50%;
        animation: typingBounce 1.4s infinite ease-in-out both;
    }

    .typing-dot:nth-child(1) { animation-delay: -0.32s; }
    .typing-dot:nth-child(2) { animation-delay: -0.16s; }

    @keyframes typingBounce {
        0%, 80%, 100% { transform: scale(0); }
        40% { transform: scale(1); }
    }

    .typing-text {
        font-size: 0.85rem;
        color: var(--chat-muted);
    }

    /* Date Divider */
    .date-divider {
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 24px 0;
        position: relative;
    }

    .date-divider::before {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        height: 1px;
        background: var(--chat-border);
    }

    .date-divider span {
        background: var(--chat-card);
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.75rem;
        color: var(--chat-muted);
        z-index: 1;
        border: 1px solid var(--chat-border);
    }

    /* Scroll to Bottom Button */
    .scroll-bottom-btn {
        display: none;
        position: absolute;
        bottom: 100px;
        right: 24px;
        width: 44px;
        height: 44px;
        background: var(--chat-accent);
        border: none;
        border-radius: 50%;
        color: white;
        font-size: 1rem;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4);
        transition: all 0.3s ease;
        z-index: 10;
    }

    .scroll-bottom-btn.show {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .scroll-bottom-btn:hover {
        transform: scale(1.1);
    }

    .scroll-bottom-btn .badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: #fff;
        color: var(--chat-accent);
        font-size: 0.65rem;
        padding: 2px 6px;
        border-radius: 10px;
        font-weight: 700;
    }

    /* Image Modal */
    .image-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.95);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .image-modal.show {
        display: flex;
    }

    .image-modal img {
        max-width: 90%;
        max-height: 90vh;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    }

    .image-modal-close {
        position: absolute;
        top: 20px;
        right: 20px;
        width: 44px;
        height: 44px;
        background: rgba(255,255,255,0.1);
        border: none;
        border-radius: 50%;
        color: white;
        font-size: 1.2rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .image-modal-close:hover {
        background: var(--chat-accent);
    }

    /* Header Actions */
    .header-actions {
        margin-left: auto;
        display: flex;
        gap: 8px;
    }

    .header-btn {
        width: 36px;
        height: 36px;
        background: rgba(255,255,255,0.05);
        border: 1px solid var(--chat-border);
        border-radius: 10px;
        color: var(--chat-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 0.9rem;
    }

    .header-btn:hover {
        background: var(--chat-accent-soft);
        color: var(--chat-accent);
        border-color: var(--chat-accent);
    }

    /* Search Modal */
    .search-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.8);
        z-index: 9998;
        align-items: flex-start;
        justify-content: center;
        padding-top: 100px;
    }

    .search-modal.show {
        display: flex;
    }

    .search-box {
        width: 90%;
        max-width: 500px;
        background: var(--chat-card);
        border: 1px solid var(--chat-border);
        border-radius: 16px;
        padding: 20px;
    }

    .search-input-v2 {
        width: 100%;
        background: rgba(255,255,255,0.05);
        border: 1px solid var(--chat-border);
        border-radius: 12px;
        padding: 12px 16px;
        color: var(--chat-text);
        font-size: 1rem;
        margin-bottom: 16px;
    }

    .search-input-v2:focus {
        outline: none;
        border-color: var(--chat-accent);
    }

    .search-results {
        max-height: 300px;
        overflow-y: auto;
    }

    .search-result-item {
        padding: 12px;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s;
        border-bottom: 1px solid var(--chat-border);
    }

    .search-result-item:hover {
        background: var(--chat-accent-soft);
    }

    .search-result-item:last-child {
        border-bottom: none;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .chat-container {
            height: calc(100dvh - 60px);
            border: none;
        }

        .chat-header-v2 {
            padding: 0 16px;
            height: 65px;
        }

        .support-avatar {
            width: 42px;
            height: 42px;
            font-size: 1.2rem;
        }

        .chat-body-v2 {
            padding: 16px;
        }

        .msg-bubble-v2 {
            max-width: 85%;
            padding: 12px 14px;
        }

        .chat-footer-v2 {
            padding: 12px 16px 16px;
            padding-bottom: max(12px, env(safe-area-inset-bottom));
        }

        .input-group-v2 {
            padding: 4px 6px 4px 14px;
        }

        .emoji-picker-v2.show {
            left: 16px;
            right: 16px;
            grid-template-columns: repeat(6, 1fr);
        }
    }
</style>

<div class="chat-page">
    <!-- Image Modal -->
    <div class="image-modal" id="imageModal">
        <button class="image-modal-close" onclick="closeImageModal()">
            <i class="fas fa-times"></i>
        </button>
        <img src="" alt="Full size" id="modalImage">
    </div>

    <!-- Search Modal -->
    <div class="search-modal" id="searchModal" onclick="closeSearchModal(event)">
        <div class="search-box" onclick="event.stopPropagation()">
            <input type="text" class="search-input-v2" id="searchInput" placeholder="ค้นหาข้อความ..." onkeyup="searchMessages()">
            <div class="search-results" id="searchResults"></div>
        </div>
    </div>

    <div class="chat-container">
        <div class="chat-header-v2">
            <div class="support-avatar">
                <i class="fas fa-headset"></i>
            </div>
            <div class="support-info">
                <h5>NF~SHOP Support</h5>
                <div class="support-status">
                    <span class="status-dot-v2"></span>
                    <span>ออนไลน์ - ตอบกลับอัตโนมัติ</span>
                </div>
            </div>
            <div class="header-actions">
                <button class="header-btn" onclick="toggleSearch()" title="ค้นหา">
                    <i class="fas fa-search"></i>
                </button>
                <button class="header-btn" onclick="clearChat()" title="ล้างแชท">
                    <i class="fas fa-broom"></i>
                </button>
            </div>
        </div>

        <div class="chat-body-v2" id="chatBody">
            <div class="loading-state">
                <div class="loading-spinner"></div>
                <span>กำลังเชื่อมต่อ...</span>
            </div>
            <!-- Typing Indicator -->
            <div class="typing-indicator" id="typingIndicator">
                <div class="typing-dots">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
                <span class="typing-text">กำลังพิมพ์...</span>
            </div>
        </div>

        <!-- Scroll to Bottom Button -->
        <button class="scroll-bottom-btn" id="scrollBottomBtn" onclick="scrollToBottom()">
            <i class="fas fa-chevron-down"></i>
            <span class="badge" id="newMessageCount">0</span>
        </button>

        <div id="suggestionChips" class="suggestions-v2"></div>

        <div class="chat-footer-v2">
            <!-- Emoji Picker -->
            <div class="emoji-picker-v2" id="emojiPicker">
                <div class="emoji-item" onclick="insertEmoji('😀')">😀</div>
                <div class="emoji-item" onclick="insertEmoji('😁')">😁</div>
                <div class="emoji-item" onclick="insertEmoji('😂')">😂</div>
                <div class="emoji-item" onclick="insertEmoji('🤣')">🤣</div>
                <div class="emoji-item" onclick="insertEmoji('😃')">😃</div>
                <div class="emoji-item" onclick="insertEmoji('😄')">😄</div>
                <div class="emoji-item" onclick="insertEmoji('😅')">😅</div>
                <div class="emoji-item" onclick="insertEmoji('😆')">😆</div>
                <div class="emoji-item" onclick="insertEmoji('😉')">😉</div>
                <div class="emoji-item" onclick="insertEmoji('😊')">😊</div>
                <div class="emoji-item" onclick="insertEmoji('😋')">😋</div>
                <div class="emoji-item" onclick="insertEmoji('😎')">😎</div>
                <div class="emoji-item" onclick="insertEmoji('😍')">😍</div>
                <div class="emoji-item" onclick="insertEmoji('😘')">😘</div>
                <div class="emoji-item" onclick="insertEmoji('🥰')">🥰</div>
                <div class="emoji-item" onclick="insertEmoji('😗')">😗</div>
                <div class="emoji-item" onclick="insertEmoji('😙')">😙</div>
                <div class="emoji-item" onclick="insertEmoji('😚')">😚</div>
                <div class="emoji-item" onclick="insertEmoji('☺️')">☺️</div>
                <div class="emoji-item" onclick="insertEmoji('🙂')">🙂</div>
                <div class="emoji-item" onclick="insertEmoji('🤗')">🤗</div>
                <div class="emoji-item" onclick="insertEmoji('🤩')">🤩</div>
                <div class="emoji-item" onclick="insertEmoji('🤔')">🤔</div>
                <div class="emoji-item" onclick="insertEmoji('🤨')">🤨</div>
                <div class="emoji-item" onclick="insertEmoji('😐')">😐</div>
                <div class="emoji-item" onclick="insertEmoji('😑')">😑</div>
                <div class="emoji-item" onclick="insertEmoji('😶')">😶</div>
                <div class="emoji-item" onclick="insertEmoji('🙄')">🙄</div>
                <div class="emoji-item" onclick="insertEmoji('😏')">😏</div>
                <div class="emoji-item" onclick="insertEmoji('😣')">😣</div>
                <div class="emoji-item" onclick="insertEmoji('😥')">😥</div>
                <div class="emoji-item" onclick="insertEmoji('😮')">😮</div>
                <div class="emoji-item" onclick="insertEmoji('🤐')">🤐</div>
                <div class="emoji-item" onclick="insertEmoji('😯')">😯</div>
                <div class="emoji-item" onclick="insertEmoji('😪')">😪</div>
                <div class="emoji-item" onclick="insertEmoji('😫')">😫</div>
                <div class="emoji-item" onclick="insertEmoji('🥱')">🥱</div>
                <div class="emoji-item" onclick="insertEmoji('😴')">😴</div>
                <div class="emoji-item" onclick="insertEmoji('😌')">😌</div>
                <div class="emoji-item" onclick="insertEmoji('😛')">😛</div>
                <div class="emoji-item" onclick="insertEmoji('😜')">😜</div>
                <div class="emoji-item" onclick="insertEmoji('😝')">😝</div>
                <div class="emoji-item" onclick="insertEmoji('🤤')">🤤</div>
                <div class="emoji-item" onclick="insertEmoji('😒')">😒</div>
                <div class="emoji-item" onclick="insertEmoji('😓')">😓</div>
                <div class="emoji-item" onclick="insertEmoji('😔')">😔</div>
                <div class="emoji-item" onclick="insertEmoji('😕')">😕</div>
                <div class="emoji-item" onclick="insertEmoji('🙃')">🙃</div>
                <div class="emoji-item" onclick="insertEmoji('🤑')">🤑</div>
                <div class="emoji-item" onclick="insertEmoji('😲')">😲</div>
                <div class="emoji-item" onclick="insertEmoji('☹️')">☹️</div>
                <div class="emoji-item" onclick="insertEmoji('🙁')">🙁</div>
                <div class="emoji-item" onclick="insertEmoji('😖')">😖</div>
                <div class="emoji-item" onclick="insertEmoji('😞')">😞</div>
                <div class="emoji-item" onclick="insertEmoji('😟')">😟</div>
                <div class="emoji-item" onclick="insertEmoji('😤')">😤</div>
                <div class="emoji-item" onclick="insertEmoji('😢')">😢</div>
                <div class="emoji-item" onclick="insertEmoji('😭')">😭</div>
                <div class="emoji-item" onclick="insertEmoji('😦')">😦</div>
                <div class="emoji-item" onclick="insertEmoji('😧')">😧</div>
                <div class="emoji-item" onclick="insertEmoji('😨')">😨</div>
                <div class="emoji-item" onclick="insertEmoji('😩')">😩</div>
                <div class="emoji-item" onclick="insertEmoji('🤯')">🤯</div>
                <div class="emoji-item" onclick="insertEmoji('😬')">😬</div>
                <div class="emoji-item" onclick="insertEmoji('😰')">😰</div>
                <div class="emoji-item" onclick="insertEmoji('😱')">😱</div>
                <div class="emoji-item" onclick="insertEmoji('🥵')">🥵</div>
                <div class="emoji-item" onclick="insertEmoji('🥶')">🥶</div>
                <div class="emoji-item" onclick="insertEmoji('😳')">😳</div>
                <div class="emoji-item" onclick="insertEmoji('🤪')">🤪</div>
                <div class="emoji-item" onclick="insertEmoji('😵')">😵</div>
                <div class="emoji-item" onclick="insertEmoji('🥴')">🥴</div>
                <div class="emoji-item" onclick="insertEmoji('😠')">😠</div>
                <div class="emoji-item" onclick="insertEmoji('😡')">😡</div>
                <div class="emoji-item" onclick="insertEmoji('🤬')">🤬</div>
                <div class="emoji-item" onclick="insertEmoji('😷')">😷</div>
                <div class="emoji-item" onclick="insertEmoji('🤒')">🤒</div>
                <div class="emoji-item" onclick="insertEmoji('🤕')">🤕</div>
                <div class="emoji-item" onclick="insertEmoji('🤢')">🤢</div>
                <div class="emoji-item" onclick="insertEmoji('🤮')">🤮</div>
                <div class="emoji-item" onclick="insertEmoji('🤧')">🤧</div>
                <div class="emoji-item" onclick="insertEmoji('😇')">😇</div>
                <div class="emoji-item" onclick="insertEmoji('🥳')">🥳</div>
                <div class="emoji-item" onclick="insertEmoji('🥴')">🥴</div>
                <div class="emoji-item" onclick="insertEmoji('🥺')">🥺</div>
                <div class="emoji-item" onclick="insertEmoji('🤠')">🤠</div>
                <div class="emoji-item" onclick="insertEmoji('🤡')">🤡</div>
                <div class="emoji-item" onclick="insertEmoji('🤥')">🤥</div>
                <div class="emoji-item" onclick="insertEmoji('🤫')">🤫</div>
                <div class="emoji-item" onclick="insertEmoji('🤭')">🤭</div>
                <div class="emoji-item" onclick="insertEmoji('🧐')">🧐</div>
                <div class="emoji-item" onclick="insertEmoji('🤓')">🤓</div>
                <div class="emoji-item" onclick="insertEmoji('👍')">👍</div>
                <div class="emoji-item" onclick="insertEmoji('👎')">👎</div>
                <div class="emoji-item" onclick="insertEmoji('👏')">👏</div>
                <div class="emoji-item" onclick="insertEmoji('🙌')">🙌</div>
                <div class="emoji-item" onclick="insertEmoji('👐')">👐</div>
                <div class="emoji-item" onclick="insertEmoji('🤲')">🤲</div>
                <div class="emoji-item" onclick="insertEmoji('🤝')">🤝</div>
                <div class="emoji-item" onclick="insertEmoji('🙏')">🙏</div>
                <div class="emoji-item" onclick="insertEmoji('💪')">💪</div>
                <div class="emoji-item" onclick="insertEmoji('🦾')">🦾</div>
                <div class="emoji-item" onclick="insertEmoji('🖕')">🖕</div>
                <div class="emoji-item" onclick="insertEmoji('✍️')">✍️</div>
                <div class="emoji-item" onclick="insertEmoji('💅')">💅</div>
                <div class="emoji-item" onclick="insertEmoji('🤳')">🤳</div>
                <div class="emoji-item" onclick="insertEmoji('💃')">💃</div>
                <div class="emoji-item" onclick="insertEmoji('🕺')">🕺</div>
                <div class="emoji-item" onclick="insertEmoji('🕴️')">🕴️</div>
                <div class="emoji-item" onclick="insertEmoji('👯')">👯</div>
                <div class="emoji-item" onclick="insertEmoji('🧖')">🧖</div>
                <div class="emoji-item" onclick="insertEmoji('🧖‍♀️')">🧖‍♀️</div>
                <div class="emoji-item" onclick="insertEmoji('🧖‍♂️')">🧖‍♂️</div>
                <div class="emoji-item" onclick="insertEmoji('👤')">👤</div>
                <div class="emoji-item" onclick="insertEmoji('👥')">👥</div>
                <div class="emoji-item" onclick="insertEmoji('🧑')">🧑</div>
                <div class="emoji-item" onclick="insertEmoji('🧑‍🚀')">🧑‍🚀</div>
                <div class="emoji-item" onclick="insertEmoji('👨‍🚀')">👨‍🚀</div>
                <div class="emoji-item" onclick="insertEmoji('👩‍🚀')">👩‍🚀</div>
                <div class="emoji-item" onclick="insertEmoji('💋')">💋</div>
                <div class="emoji-item" onclick="insertEmoji('💌')">💌</div>
                <div class="emoji-item" onclick="insertEmoji('💘')">💘</div>
                <div class="emoji-item" onclick="insertEmoji('💝')">💝</div>
                <div class="emoji-item" onclick="insertEmoji('💖')">💖</div>
                <div class="emoji-item" onclick="insertEmoji('💗')">💗</div>
                <div class="emoji-item" onclick="insertEmoji('💓')">💓</div>
                <div class="emoji-item" onclick="insertEmoji('💞')">💞</div>
                <div class="emoji-item" onclick="insertEmoji('💕')">💕</div>
                <div class="emoji-item" onclick="insertEmoji('💟')">💟</div>
                <div class="emoji-item" onclick="insertEmoji('❣️')">❣️</div>
                <div class="emoji-item" onclick="insertEmoji('💔')">💔</div>
                <div class="emoji-item" onclick="insertEmoji('❤️')">❤️</div>
                <div class="emoji-item" onclick="insertEmoji('🧡')">🧡</div>
                <div class="emoji-item" onclick="insertEmoji('💛')">💛</div>
                <div class="emoji-item" onclick="insertEmoji('💚')">💚</div>
                <div class="emoji-item" onclick="insertEmoji('💙')">💙</div>
                <div class="emoji-item" onclick="insertEmoji('💜')">💜</div>
                <div class="emoji-item" onclick="insertEmoji('🤎')">🤎</div>
                <div class="emoji-item" onclick="insertEmoji('🖤')">🖤</div>
                <div class="emoji-item" onclick="insertEmoji('🤍')">🤍</div>
                <div class="emoji-item" onclick="insertEmoji('💯')">💯</div>
                <div class="emoji-item" onclick="insertEmoji('💢')">💢</div>
                <div class="emoji-item" onclick="insertEmoji('💥')">💥</div>
                <div class="emoji-item" onclick="insertEmoji('💫')">💫</div>
                <div class="emoji-item" onclick="insertEmoji('💦')">💦</div>
                <div class="emoji-item" onclick="insertEmoji('💨')">💨</div>
                <div class="emoji-item" onclick="insertEmoji('🕳️')">🕳️</div>
                <div class="emoji-item" onclick="insertEmoji('💣')">💣</div>
                <div class="emoji-item" onclick="insertEmoji('💬')">💬</div>
                <div class="emoji-item" onclick="insertEmoji('👁️‍🗨️')">👁️‍🗨️</div>
                <div class="emoji-item" onclick="insertEmoji('🗨️')">🗨️</div>
                <div class="emoji-item" onclick="insertEmoji('🗯️')">🗯️</div>
                <div class="emoji-item" onclick="insertEmoji('💭')">💭</div>
                <div class="emoji-item" onclick="insertEmoji('💤')">💤</div>
            </div>

            <form id="chatForm" enctype="multipart/form-data">
                <div class="input-group-v2">
                    <label for="imageInput" class="btn-upload-v2" title="แนบรูป">
                        <i class="fas fa-image"></i>
                    </label>
                    <input type="file" id="imageInput" name="image" accept="image/*" style="display: none;">
                    
                    <span class="emoji-btn-v2" onclick="toggleEmojiPicker()" title="อิโมจิ">
                        <i class="far fa-smile"></i>
                    </span>
                    
                    <input type="text" id="msgInput" class="chat-input-v2" placeholder="พิมพ์ข้อความ..." autocomplete="off">
                    <button type="submit" class="btn-send-v2" title="ส่ง">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                <div id="filePreview" class="file-preview-v2">
                    <i class="fas fa-check-circle"></i>
                    <span id="fileName"></span>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'page/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // ==========================================
    // 1. Global Functions
    // ==========================================
    window.continuePayment = async function (transactionRef) {
        try {
            let secondsLeft = 10;
            let timerInterval;

            const result = await Swal.fire({
                title: 'กำลังเชื่อมต่อธนาคาร...',
                html: 'ระบบจะพาท่านไปหน้าชำระเงินในอีก <b>10</b> วินาที',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                    const b = Swal.getHtmlContainer().querySelector('b');
                    timerInterval = setInterval(() => {
                        secondsLeft--;
                        if (b) b.textContent = secondsLeft;
                        if (secondsLeft <= 0) {
                            clearInterval(timerInterval);
                            Swal.clickConfirm();
                        }
                    }, 1000);
                },
                willClose: () => {
                    clearInterval(timerInterval);
                }
            });

            if (result.isConfirmed || result.dismiss === Swal.DismissReason.timer) {
                Swal.fire({
                    title: 'กำลังเปิดหน้าชำระเงิน...',
                    text: 'กรุณารอสักครู่',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                const formData = new FormData();
                formData.append('transaction_ref', transactionRef);

                const response = await fetch('controller/continue_payment.php', { method: 'POST', body: formData });
                const textResponse = await response.text();
                let data;
                try {
                    data = JSON.parse(textResponse);
                } catch (jsonError) {
                    throw new Error("Server Error: ข้อมูลตอบกลับผิดพลาด");
                }

                if (data.success && data.payment_url) {
                    try { Swal.close(); } catch (e) { }
                    window.open(data.payment_url, '_blank');
                } else {
                    throw new Error(data.message || 'ไม่สามารถดึงลิงก์ชำระเงินได้');
                }
            }
        } catch (error) {
            Swal.fire('เกิดข้อผิดพลาด', error.message, 'error');
        }
    };

    // --- Suggestion Chips ---
    function loadSuggestions() {
        const container = document.getElementById('suggestionChips');
        if (!container) return;

        fetch('controller/chat_api.php?action=get_smart_suggestions')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.suggestions.length > 0) {
                    let html = '';
                    data.suggestions.forEach(txt => {
                        html += `<div class="chip-v2" onclick="sendSuggestion('${txt}')">${txt}</div>`;
                    });
                    container.innerHTML = html;
                    container.style.display = 'flex';
                }
            });
    }

    window.sendSuggestion = function (text) {
        const msgInput = document.getElementById('msgInput');
        const chatForm = document.getElementById('chatForm');
        msgInput.value = text;
        chatForm.dispatchEvent(new Event('submit'));
    }

    // ==========================================
    // 2. Main Chat Logic
    // ==========================================
    document.addEventListener('DOMContentLoaded', () => {
        const chatBody = document.getElementById('chatBody');
        const chatForm = document.getElementById('chatForm');
        const msgInput = document.getElementById('msgInput');
        const imageInput = document.getElementById('imageInput');
        const filePreview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        let isScrolledToBottom = true;
        let lastChatData = null;

        loadSuggestions();

        chatBody.addEventListener('scroll', () => {
            isScrolledToBottom = (chatBody.scrollHeight - chatBody.scrollTop - chatBody.clientHeight) < 150;
        });

        function scrollToBottom() {
            chatBody.scrollTop = chatBody.scrollHeight;
        }

        imageInput.addEventListener('change', () => {
            if (imageInput.files.length > 0) {
                filePreview.classList.add('show');
                fileName.textContent = imageInput.files[0].name;
            } else {
                filePreview.classList.remove('show');
            }
        });

        function loadMessages() {
            fetch('controller/chat_api.php?action=fetch')
                .then(res => res.json())
                .then(data => {
                    if (!data.success) return;

                    if (JSON.stringify(data.messages) === JSON.stringify(lastChatData)) {
                        return;
                    }

                    lastChatData = data.messages;

                    if (data.messages.length === 0) {
                        chatBody.innerHTML = `
                            <div class="empty-chat-v2">
                                <div class="empty-icon-v2">
                                    <i class="far fa-comments"></i>
                                </div>
                                <h6>เริ่มต้นการสนทนา</h6>
                                <p>ส่งข้อความเพื่อติดต่อทีมงาน หรือสอบถามข้อมูล</p>
                            </div>`;
                        return;
                    }

                    let html = '';
                    let lastDate = null;

                    data.messages.forEach((msg, index) => {
                        // Add date divider if date changed
                        const msgDate = new Date(msg.created_at).toLocaleDateString('th-TH', { 
                            weekday: 'long', 
                            year: 'numeric', 
                            month: 'long', 
                            day: 'numeric' 
                        });
                        const msgDateOnly = new Date(msg.created_at).toDateString();
                        
                        if (msgDateOnly !== lastDate) {
                            html += `<div class="date-divider"><span>${msgDate}</span></div>`;
                            lastDate = msgDateOnly;
                        }

                        const isUser = msg.sender === 'user';
                        const rowClass = isUser ? 'user' : 'admin';
                        const time = new Date(msg.created_at).toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });

                        let senderBadge = '';
                        if (!isUser) {
                            if (msg.is_ai == 1) {
                                senderBadge = `<span class="sender-badge-v2 badge-ai-v2"><i class="fas fa-robot"></i> AI Auto</span>`;
                            } else {
                                senderBadge = `<span class="sender-badge-v2 badge-admin-v2"><i class="fas fa-user-shield"></i> Admin</span>`;
                            }
                        }

                        let rawMessage = msg.message || '';
                        let actionButtonHtml = '';
                        const actionRegex = /\|\|ACTION:(.*?):(.*?)\|\|/g;

                        rawMessage = rawMessage.replace(actionRegex, (match, type, value) => {
                            if (type === 'PAY') {
                                actionButtonHtml += `
                                    <button onclick="continuePayment('${value}')" class="chat-action-btn-v2 btn-pay-v2">
                                        <i class="fas fa-credit-card me-2"></i> ดำเนินการชำระเงิน
                                    </button>`;
                            } else if (type === 'VPN') {
                                actionButtonHtml += `
                                    <a href="?p=my_vpn" class="chat-action-btn-v2 btn-vpn-v2">
                                        <i class="fas fa-server me-2"></i> จัดการ VPN ของฉัน
                                    </a>`;
                            } else if (type === 'NAV') {
                                let btnText = 'ไปหน้านี้';
                                let icon = 'fa-link';
                                if (value.includes('rent')) { btnText = 'ดูแพ็กเกจ'; icon = 'fa-shopping-cart'; }
                                else if (value.includes('topup')) { btnText = 'เติมเงิน'; icon = 'fa-wallet'; }
                                else if (value.includes('history')) { btnText = 'ดูประวัติ'; icon = 'fa-history'; }
                                else if (value.includes('userdetail')) { btnText = 'ตั้งค่าบัญชี'; icon = 'fa-cog'; }

                                actionButtonHtml += `
                                    <a href="${value}" class="chat-action-btn-v2 btn-nav-v2">
                                        <i class="fas ${icon} me-2"></i> ${btnText}
                                    </a>`;
                            }
                            return '';
                        });

                        let content = '';
                        if (msg.image_path) {
                            content += `<img src="${msg.image_path}" class="chat-image-v2" onclick="openImageModal('${msg.image_path}')">`;
                        }
                        if (rawMessage.trim()) {
                            content += `<div>${rawMessage.replace(/\n/g, '<br>')}</div>`;
                        }
                        if (actionButtonHtml) {
                            content += actionButtonHtml;
                        }

                        let readIcon = '';
                        if (isUser) {
                            readIcon = msg.is_read == 1
                                ? '<i class="fas fa-check-double text-info ms-1" title="อ่านแล้ว"></i>'
                                : '<i class="fas fa-check ms-1" title="ส่งแล้ว"></i>';
                        }

                        html += `
                            <div class="msg-row-v2 ${rowClass}">
                                <div class="msg-bubble-v2">
                                    ${!isUser ? `<div style="margin-bottom:4px;">${senderBadge}</div>` : ''}
                                    ${content}
                                    <div class="msg-meta-v2">
                                        <span>${time}</span>
                                        ${readIcon}
                                    </div>
                                </div>
                            </div>`;
                    });

                    chatBody.innerHTML = html;

                    if (isScrolledToBottom) {
                        scrollToBottom();
                    }
                })
                .catch(err => { });
        }

        chatForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const msg = msgInput.value.trim();
            const file = imageInput.files[0];

            if (!msg && !file) return;

            const formData = new FormData();
            formData.append('action', 'send');
            formData.append('message', msg);
            if (file) formData.append('image', file);
            formData.append('csrf_token', CSRF_TOKEN);

            msgInput.value = '';
            imageInput.value = '';
            filePreview.classList.remove('show');

            // Optimistic UI
            chatBody.insertAdjacentHTML('beforeend', `
                <div class="msg-row-v2 user">
                    <div class="msg-bubble-v2" style="opacity: 0.7;">
                        <i class="fas fa-spinner fa-spin me-1"></i> กำลังส่ง...
                    </div>
                </div>`);
            scrollToBottom();

            fetch('controller/chat_api.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        lastChatData = null;
                        loadMessages();
                    } else {
                        Swal.fire('Error', 'ส่งไม่สำเร็จ: ' + (data.message || 'Unknown'), 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('Error', 'การเชื่อมต่อขัดข้อง', 'error');
                });
        });

        loadMessages();
        setInterval(loadMessages, 3000);
    });

    // ==========================================
    // 3. New Features Functions
    // ==========================================
    
    // Emoji Picker
    function toggleEmojiPicker() {
        const picker = document.getElementById('emojiPicker');
        picker.classList.toggle('show');
    }

    function insertEmoji(emoji) {
        const msgInput = document.getElementById('msgInput');
        msgInput.value += emoji;
        msgInput.focus();
        document.getElementById('emojiPicker').classList.remove('show');
    }

    // Close emoji picker when clicking outside
    document.addEventListener('click', (e) => {
        const picker = document.getElementById('emojiPicker');
        const emojiBtn = document.querySelector('.emoji-btn-v2');
        if (!picker.contains(e.target) && !emojiBtn.contains(e.target)) {
            picker.classList.remove('show');
        }
    });

    // Typing Indicator
    function showTyping() {
        document.getElementById('typingIndicator').classList.add('show');
    }

    function hideTyping() {
        document.getElementById('typingIndicator').classList.remove('show');
    }

    // Scroll to Bottom Button
    let unreadCount = 0;
    const scrollBottomBtn = document.getElementById('scrollBottomBtn');
    const newMessageBadge = document.getElementById('newMessageCount');

    document.getElementById('chatBody').addEventListener('scroll', function() {
        const chatBody = this;
        const isAtBottom = (chatBody.scrollHeight - chatBody.scrollTop - chatBody.clientHeight) < 100;
        
        if (isAtBottom) {
            scrollBottomBtn.classList.remove('show');
            unreadCount = 0;
            newMessageBadge.textContent = '0';
        } else if (unreadCount > 0) {
            scrollBottomBtn.classList.add('show');
        }
    });

    function scrollToBottom() {
        const chatBody = document.getElementById('chatBody');
        chatBody.scrollTop = chatBody.scrollHeight;
        scrollBottomBtn.classList.remove('show');
        unreadCount = 0;
        newMessageBadge.textContent = '0';
    }

    // Image Modal
    function openImageModal(src) {
        document.getElementById('modalImage').src = src;
        document.getElementById('imageModal').classList.add('show');
    }

    function closeImageModal() {
        document.getElementById('imageModal').classList.remove('show');
    }

    // Close modal on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeImageModal();
            closeSearchModal();
        }
    });

    // Search Modal
    function toggleSearch() {
        const modal = document.getElementById('searchModal');
        modal.classList.toggle('show');
        if (modal.classList.contains('show')) {
            document.getElementById('searchInput').focus();
        }
    }

    function closeSearchModal(event) {
        if (!event || event.target.id === 'searchModal') {
            document.getElementById('searchModal').classList.remove('show');
            document.getElementById('searchResults').innerHTML = '';
            document.getElementById('searchInput').value = '';
        }
    }

    let allMessages = [];

    function searchMessages() {
        const query = document.getElementById('searchInput').value.toLowerCase().trim();
        const resultsDiv = document.getElementById('searchResults');
        
        if (!query) {
            resultsDiv.innerHTML = '';
            return;
        }

        const filtered = allMessages.filter(msg => 
            msg.message && msg.message.toLowerCase().includes(query)
        );

        if (filtered.length === 0) {
            resultsDiv.innerHTML = '<div style="text-align: center; color: var(--chat-muted); padding: 20px;">ไม่พบข้อความ</div>';
            return;
        }

        resultsDiv.innerHTML = filtered.map(msg => `
            <div class="search-result-item" onclick="goToMessage('${msg.created_at}')">
                <div style="font-size: 0.8rem; color: var(--chat-muted); margin-bottom: 4px;">
                    ${new Date(msg.created_at).toLocaleString('th-TH')}
                </div>
                <div style="color: var(--chat-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    ${msg.sender === 'user' ? '<i class="fas fa-user"></i> คุณ: ' : '<i class="fas fa-headset"></i> Admin: '}
                    ${msg.message}
                </div>
            </div>
        `).join('');
    }

    function goToMessage(timestamp) {
        closeSearchModal();
        // Scroll to message logic would go here
        // For now, just close the search
    }

    // Clear Chat (visual only)
    function clearChat() {
        Swal.fire({
            title: 'ล้างแชท?',
            text: 'ข้อความทั้งหมดจะถูกซ่อน (ไม่ลบจากระบบ)',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'ล้าง',
            cancelButtonText: 'ยกเลิก',
            background: 'rgba(15, 15, 22, 0.95)',
            color: '#fff',
            confirmButtonColor: '#E50914'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('chatBody').innerHTML = `
                    <div class="empty-chat-v2">
                        <div class="empty-icon-v2">
                            <i class="far fa-comments"></i>
                        </div>
                        <h6>แชทถูกล้างแล้ว</h6>
                        <p>เริ่มต้นการสนทนาใหม่</p>
                    </div>`;
                Swal.fire({
                    icon: 'success',
                    title: 'ล้างแชทสำเร็จ',
                    timer: 1500,
                    showConfirmButton: false,
                    background: 'rgba(15, 15, 22, 0.95)',
                    color: '#fff'
                });
            }
        });
    }

    // Store messages for search
    const originalLoadMessages = loadMessages;
    loadMessages = function() {
        fetch('controller/chat_api.php?action=fetch')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.messages) {
                    allMessages = data.messages;
                }
            });
        return originalLoadMessages();
    };
</script>
