// ============================================================
//  STUDEX — Student Index
//  assets/js/app.js — Core JavaScript
//  Sidebar · Dropdown · Logout · Search · Global helpers
// ============================================================

(function () {
    'use strict';

    // ============================================================
    // DOM READY
    // ============================================================
    document.addEventListener('DOMContentLoaded', function () {
        initSidebar();
        initDropdowns();
        initLogout();
        initSearch();
        initActiveNav();
    });

    // ============================================================
    // 1. SIDEBAR
    // ============================================================
    function initSidebar() {
        var sidebar  = document.getElementById('sidebar');
        var overlay  = document.getElementById('sidebarOverlay');
        var menuBtn  = document.getElementById('menuToggle');

        if (!sidebar) return;

        // Mobile toggle
        if (menuBtn) {
            menuBtn.addEventListener('click', function () {
                toggleMobileSidebar();
            });
        }

        // Overlay click — tutup sidebar
        if (overlay) {
            overlay.addEventListener('click', function () {
                closeMobileSidebar();
            });
        }

        // ESC — tutup sidebar di mobile
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeMobileSidebar();
                closeAllDropdowns();
            }
        });

        function toggleMobileSidebar() {
            var isOpen = sidebar.classList.contains('mobile-open');
            if (isOpen) {
                closeMobileSidebar();
            } else {
                openMobileSidebar();
            }
        }

        function openMobileSidebar() {
            sidebar.classList.add('mobile-open');
            if (overlay) {
                overlay.style.display = 'block';
                requestAnimationFrame(function () {
                    overlay.classList.add('visible');
                });
            }
            document.body.style.overflow = 'hidden';
        }

        function closeMobileSidebar() {
            sidebar.classList.remove('mobile-open');
            if (overlay) {
                overlay.classList.remove('visible');
                setTimeout(function () {
                    overlay.style.display = 'none';
                }, 250);
            }
            document.body.style.overflow = '';
        }
    }

    // ============================================================
    // 2. DROPDOWNS
    // ============================================================
    function initDropdowns() {
        // User dropdown
        var userBtn  = document.getElementById('userDropdownBtn');
        var userMenu = document.getElementById('userDropdownMenu');
        var userDrop = document.getElementById('userDropdown');

        if (userBtn && userMenu) {
            userBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                var isOpen = userDrop.classList.contains('open');
                closeAllDropdowns();
                if (!isOpen) {
                    userDrop.classList.add('open');
                    userBtn.setAttribute('aria-expanded', 'true');
                }
            });
        }

        // Semua dropdown generic [data-dropdown-toggle]
        document.querySelectorAll('[data-dropdown-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var targetId = btn.dataset.dropdownToggle;
                var target   = document.getElementById(targetId);
                var parent   = btn.closest('.dropdown');
                if (!target || !parent) return;
                var isOpen = parent.classList.contains('open');
                closeAllDropdowns();
                if (!isOpen) {
                    parent.classList.add('open');
                    btn.setAttribute('aria-expanded', 'true');
                }
            });
        });

        // Klik di luar — tutup semua dropdown
        document.addEventListener('click', function () {
            closeAllDropdowns();
        });
    }

    function closeAllDropdowns() {
        document.querySelectorAll('.dropdown.open').forEach(function (d) {
            d.classList.remove('open');
            var btn = d.querySelector('[aria-expanded]');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        });
    }

    // ============================================================
    // 3. LOGOUT CONFIRM
    // ============================================================
    function initLogout() {
        // Sidebar logout button
        var logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function (e) {
                e.preventDefault();
                confirmLogout(this.href);
            });
        }

        // Dropdown logout
        var dropLogout = document.getElementById('dropdownLogout');
        if (dropLogout) {
            dropLogout.addEventListener('click', function (e) {
                e.preventDefault();
                confirmLogout(this.href);
            });
        }

        function confirmLogout(url) {
            // Pakai modal confirm kalau ada, fallback ke native confirm
            if (typeof openConfirmModal === 'function') {
                openConfirmModal({
                    title  : 'Keluar dari STUDEX',
                    message: 'Yakin ingin keluar? Sesi Anda akan diakhiri.',
                    type   : 'warning',
                    label  : 'Ya, Keluar',
                    onConfirm: function () {
                        window.location.href = url;
                    }
                });
            } else {
                if (confirm('Yakin ingin keluar?')) {
                    window.location.href = url;
                }
            }
        }
    }

    // ============================================================
    // 4. GLOBAL SEARCH
    // ============================================================
    function initSearch() {
        var input   = document.getElementById('globalSearch');
        var btn     = document.querySelector('.search-btn');
        var timeout = null;

        if (!input) return;

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                doSearch(input.value.trim());
            }
        });

        if (btn) {
            btn.addEventListener('click', function () {
                doSearch(input.value.trim());
            });
        }

        // Keyboard shortcut: Ctrl+K / Cmd+K fokus ke search
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                input.focus();
                input.select();
            }
        });

        function doSearch(query) {
            if (!query) return;
            window.location.href = STUDEX_BASE_URL + '/modules/siswa/index.php?search=' + encodeURIComponent(query);
        }
    }

    // ============================================================
    // 5. AUTO MARK ACTIVE NAV
    // ============================================================
    function initActiveNav() {
        var path = window.location.pathname;
        document.querySelectorAll('.nav-item').forEach(function (item) {
            var href = item.getAttribute('href') || '';
            if (href && href !== '#' && path.indexOf(href) !== -1) {
                item.classList.add('active');
            }
        });
    }

    // ============================================================
    // 6. GLOBAL UTILITIES (exposed ke window)
    // ============================================================

    /**
     * Format angka ribuan: 1500 → "1.500"
     */
    window.formatNumber = function (num) {
        return Number(num).toLocaleString('id-ID');
    };

    /**
     * Format tanggal: "2025-07-14" → "14 Jul 2025"
     */
    window.formatDate = function (dateStr) {
        if (!dateStr) return '-';
        var months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
        var d = new Date(dateStr);
        return d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
    };

    /**
     * Debounce
     */
    window.debounce = function (fn, delay) {
        var t;
        return function () {
            clearTimeout(t);
            t = setTimeout(fn.bind(this, arguments), delay || 300);
        };
    };

    /**
     * Copy teks ke clipboard
     */
    window.copyToClipboard = function (text, successMsg) {
        navigator.clipboard.writeText(text).then(function () {
            if (typeof showToast === 'function') {
                showToast('success', successMsg || 'Disalin ke clipboard!');
            }
        });
    };

    /**
     * Konfirmasi via modal confirm global
     * Opsi: { title, message, type, label, action, id }
     */
    window.openConfirmModal = function (opts) {
        var overlay = document.getElementById('confirmModalOverlay');
        if (!overlay) {
            // Fallback ke native
            if (confirm(opts.message || 'Yakin?')) {
                if (typeof opts.onConfirm === 'function') opts.onConfirm();
                else if (opts.action) window.location.href = opts.action;
            }
            return;
        }

        // Isi modal
        var titleEl  = document.getElementById('confirmModalTitle');
        var msgEl    = document.getElementById('confirmModalMessage');
        var submitBtn= document.getElementById('confirmModalSubmit');
        var idInput  = document.getElementById('confirmModalId');
        var form     = document.getElementById('confirmModalForm');
        var iconEl   = document.getElementById('confirmModalIcon');

        var icons = {
            danger : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>',
            warning: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            info   : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        };

        var type = opts.type || 'danger';
        if (titleEl)   titleEl.textContent   = opts.title   || 'Konfirmasi';
        if (msgEl)     msgEl.textContent     = opts.message || 'Apakah Anda yakin?';
        if (submitBtn) submitBtn.textContent = opts.label   || 'Ya, Lanjutkan';
        if (idInput)   idInput.value         = opts.id      || '';
        if (iconEl)  { iconEl.className = 'modal-header-icon ' + type; iconEl.innerHTML = icons[type] || icons.danger; }

        var btnClasses = { danger:'btn-danger', warning:'btn-warning', info:'btn-primary' };
        if (submitBtn) submitBtn.className = 'btn ' + (btnClasses[type] || 'btn-danger');

        // Kalau ada onConfirm callback (bukan form submit)
        if (typeof opts.onConfirm === 'function') {
            if (form) form.style.display = 'none';
            var tempBtn = document.createElement('button');
            tempBtn.className = 'btn ' + (btnClasses[type] || 'btn-danger');
            tempBtn.textContent = opts.label || 'Ya, Lanjutkan';
            tempBtn.id = 'confirmModalTempBtn';
            tempBtn.addEventListener('click', function () {
                closeConfirmModal();
                opts.onConfirm();
                tempBtn.remove();
                if (form) form.style.display = 'inline';
            });
            var footer = overlay.querySelector('.modal-footer');
            if (footer) footer.appendChild(tempBtn);
        } else {
            if (form) {
                form.style.display = 'inline';
                form.action = opts.action || '#';
            }
        }

        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    function closeConfirmModal() {
        var overlay = document.getElementById('confirmModalOverlay');
        if (overlay) overlay.classList.remove('open');
        document.body.style.overflow = '';
    }

    /**
     * BASE URL — di-set dari PHP via meta tag atau inline script
     * Fallback: ambil dari window jika sudah di-set
     */
    window.STUDEX_BASE_URL = window.STUDEX_BASE_URL || '';

})();