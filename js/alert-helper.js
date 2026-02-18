/**
 * Unified Alert System for NF~SHOP
 * Provides consistent SweetAlert styling across all pages
 */

const Alert = {
  /**
   * Show a styled alert
   * @param {string} type - 'success', 'error', 'warning', 'info', 'loading'
   * @param {string} title - Alert title
   * @param {string} message - Alert message (optional)
   * @param {object} customOptions - Additional SweetAlert options (optional)
   */
  show(type, title, message = "", customOptions = {}) {
    const baseConfig = {
      background: "#0a0a0a",
      color: "#ffffff",
      confirmButtonColor: "#E50914",
      cancelButtonColor: "#333333",
      customClass: {
        popup: "nf-alert-popup",
        title: "nf-alert-title",
        htmlContainer: "nf-alert-content",
        confirmButton: "nf-alert-btn-confirm",
        cancelButton: "nf-alert-btn-cancel",
      },
      backdrop: "rgba(0, 0, 0, 0.8)",
      showClass: {
        popup: "animate__animated animate__fadeInDown animate__faster",
      },
      hideClass: {
        popup: "animate__animated animate__fadeOutUp animate__faster",
      },
    };

    const typeConfigs = {
      success: {
        icon: "success",
        iconColor: "#10b981",
        timer: 2000,
        showConfirmButton: false,
      },
      error: {
        icon: "error",
        iconColor: "#ef4444",
        confirmButtonText: "ตกลง",
      },
      warning: {
        icon: "warning",
        iconColor: "#f59e0b",
        confirmButtonText: "ตกลง",
      },
      info: {
        icon: "info",
        iconColor: "#3b82f6",
        confirmButtonText: "ตกลง",
      },
      loading: {
        title: title || "กำลังดำเนินการ...",
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading(),
      },
    };

    const config = {
      ...baseConfig,
      ...(typeConfigs[type] || {}),
      title,
      text: message,
      ...customOptions,
    };

    return Swal.fire(config);
  },

  // Quick access methods
  success(title, message, options) {
    return this.show("success", title, message, options);
  },

  error(title, message, options) {
    return this.show("error", title, message, options);
  },

  warning(title, message, options) {
    return this.show("warning", title, message, options);
  },

  info(title, message, options) {
    return this.show("info", title, message, options);
  },

  loading(title = "กำลังดำเนินการ...") {
    return this.show("loading", title);
  },

  /**
   * Close the currently open alert
   */
  close() {
    Swal.close();
  },

  /**
   * Show a confirmation dialog
   */
  confirm(title, message, confirmText = "ยืนยัน", cancelText = "ยกเลิก") {
    return Swal.fire({
      background: "#0a0a0a",
      color: "#ffffff",
      title,
      html: message,  // Changed from 'text' to 'html' to render HTML properly
      icon: "question",
      iconColor: "#f59e0b",
      showCancelButton: true,
      confirmButtonColor: "#E50914",
      cancelButtonColor: "#333333",
      confirmButtonText: confirmText,
      cancelButtonText: cancelText,
      customClass: {
        popup: "nf-alert-popup",
        confirmButton: "nf-alert-btn-confirm",
        cancelButton: "nf-alert-btn-cancel",
      },
      backdrop: "rgba(0, 0, 0, 0.8)",
    });
  },
};

// Inject custom styles
const style = document.createElement("style");
style.textContent = `
    .nf-alert-popup {
        border: 1px solid rgba(229, 9, 20, 0.3) !important;
        box-shadow: 0 0 30px rgba(229, 9, 20, 0.2) !important;
    }
    
    .nf-alert-title {
        font-weight: 700 !important;
        font-size: 1.5rem !important;
    }
    
    .nf-alert-content {
        font-size: 1rem !important;
    }
    
    .nf-alert-btn-confirm,
    .nf-alert-btn-cancel {
        padding: 10px 25px !important;
        border-radius: 8px !important;
        font-weight: 600 !important;
        transition: all 0.2s !important;
    }
    
    .nf-alert-btn-confirm:hover {
        background: #b20710 !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4) !important;
    }
    
    .nf-alert-btn-cancel:hover {
        background: #444444 !important;
    }
`;
document.head.appendChild(style);
