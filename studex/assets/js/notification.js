// ============================================================
//  STUDEX — Student Index
//  assets/js/notification.js — Toast Notifications
//  showToast(type, title, message, duration)
// ============================================================

(function () {
    'use strict';

    var DURATION_DEFAULT = 4000; // ms
    var container;

    // ============================================================
    // DOM READY
    // ============================================================
    document.addEventListener('DOMContentLoaded', function () {
        container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.className  = 'toast-container';
            container.id         = 'toastContainer';
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }
    });

    // ============================================================
    // ICONS per type
    // ============================================================
    var icons = {
        success: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        danger : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        warning: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info   : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
    };

    var closeIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

    // ============================================================
    // SHOW TOAST
    // Param:
    //   type     — 'success' | 'danger' | 'warning' | 'info'
    //   title    — judul toast (string) ATAU jika message kosong, ini jadi pesan
    //   message  — pesan detail (opsional)
    //   duration — ms sebelum auto-dismiss (default 4000, 0 = permanent)
    // ============================================================
    window.showToast = function (type, title, message, duration) {
        if (!container) {
            container = document.getElementById('toastContainer');
        }
        if (!container) return;

        // Support 2-arg: showToast('success', 'Pesan')
        if (typeof message === 'undefined' || message === null) {
            message = '';
        }
        if (typeof duration === 'undefined') {
            duration = DURATION_DEFAULT;
        }

        var validTypes = ['success', 'danger', 'warning', 'info'];
        if (!validTypes.includes(type)) type = 'info';

        var toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');

        toast.innerHTML = [
            '<div class="toast-icon">', icons[type] || icons.info, '</div>',
            '<div class="toast-content">',
            '  <div class="toast-title">', escHtml(title), '</div>',
            message ? '<div class="toast-message">' + escHtml(message) + '</div>' : '',
            '</div>',
            '<button class="toast-close" aria-label="Tutup notifikasi">', closeIcon, '</button>',
            duration > 0 ? '<div class="toast-progress" style="animation-duration:' + duration + 'ms;"></div>' : '',
        ].join('');

        container.appendChild(toast);

        // Close button
        toast.querySelector('.toast-close').addEventListener('click', function () {
            removeToast(toast);
        });

        // Auto dismiss
        var timer;
        if (duration > 0) {
            timer = setTimeout(function () {
                removeToast(toast);
            }, duration);
        }

        // Pause on hover
        toast.addEventListener('mouseenter', function () {
            clearTimeout(timer);
            var progress = toast.querySelector('.toast-progress');
            if (progress) progress.style.animationPlayState = 'paused';
        });

        toast.addEventListener('mouseleave', function () {
            var progress = toast.querySelector('.toast-progress');
            if (progress) progress.style.animationPlayState = 'running';
            if (duration > 0) {
                timer = setTimeout(function () { removeToast(toast); }, 1500);
            }
        });

        return toast;
    };

    // ============================================================
    // SHORTHAND HELPERS
    // ============================================================
    window.toastSuccess = function (title, message, duration) {
        return window.showToast('success', title, message, duration);
    };

    window.toastError = function (title, message, duration) {
        return window.showToast('danger', title, message, duration);
    };

    window.toastWarning = function (title, message, duration) {
        return window.showToast('warning', title, message, duration);
    };

    window.toastInfo = function (title, message, duration) {
        return window.showToast('info', title, message, duration);
    };

    // ============================================================
    // REMOVE TOAST
    // ============================================================
    function removeToast(toast) {
        if (!toast || toast.classList.contains('removing')) return;
        toast.classList.add('removing');
        setTimeout(function () {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 350);
    }

    // ============================================================
    // CLEAR ALL TOASTS
    // ============================================================
    window.clearToasts = function () {
        if (!container) return;
        Array.prototype.slice.call(container.children).forEach(function (t) {
            removeToast(t);
        });
    };

    // ============================================================
    // ESCAPE HTML
    // ============================================================
    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#039;');
    }

    // ============================================================
    // AUTO-SHOW dari PHP flash yang di-embed di HTML
    // Cara pakai di PHP: tambahkan di akhir halaman:
    //   <script>
    //     document.addEventListener('DOMContentLoaded', function() {
    //       showToast('success', 'Data berhasil disimpan!');
    //     });
    //   </script>
    // ============================================================

})();