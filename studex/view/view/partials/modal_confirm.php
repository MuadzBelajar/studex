<?php
// ============================================================
//  STUDEX — Student Index
//  view/partials/modal_confirm.php
//  Modal konfirmasi global — dipakai untuk hapus & aksi kritis
//
//  Cara pakai di tombol:
//  <button
//    class="btn btn-danger-outline btn-sm"
//    data-confirm
//    data-title="Hapus Siswa"
//    data-message="Yakin ingin menghapus siswa ini? Data tidak dapat dikembalikan."
//    data-action="/modules/siswa/delete.php"
//    data-id="5"
//    data-method="POST"
//    data-type="danger">
//    Hapus
//  </button>
//
//  data-type: danger | warning | info (opsional, default: danger)
//  data-method: POST | GET (opsional, default: POST)
// ============================================================

defined('STUDEX') or die('Direct access not permitted');
?>

<!-- Global Confirm Modal -->
<div class="modal-overlay" id="confirmModalOverlay" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">
    <div class="modal modal-sm" id="confirmModal">

        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-header-icon danger" id="confirmModalIcon">
                    <!-- Icon diisi via JS sesuai type -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9"  x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div class="modal-title-group">
                    <div class="modal-title" id="confirmModalTitle">Konfirmasi</div>
                    <div class="modal-subtitle" id="confirmModalMessage">Apakah Anda yakin?</div>
                </div>
            </div>
            <button class="modal-close" id="confirmModalClose" aria-label="Tutup">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6"  y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <div class="modal-footer">
            <button class="btn btn-secondary" id="confirmModalCancel">Batal</button>
            <form id="confirmModalForm" method="POST" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="id" id="confirmModalId">
                <button type="submit" class="btn btn-danger" id="confirmModalSubmit">Ya, Lanjutkan</button>
            </form>
        </div>

    </div>
</div>

<script>
(function () {
    var overlay  = document.getElementById('confirmModalOverlay');
    var form     = document.getElementById('confirmModalForm');
    var titleEl  = document.getElementById('confirmModalTitle');
    var msgEl    = document.getElementById('confirmModalMessage');
    var idInput  = document.getElementById('confirmModalId');
    var submitBtn= document.getElementById('confirmModalSubmit');
    var iconEl   = document.getElementById('confirmModalIcon');

    var icons = {
        danger: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>',
        warning:'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info:   '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
    };

    var btnClasses = {
        danger : 'btn-danger',
        warning: 'btn-warning',
        info   : 'btn-primary',
    };

    // Buka modal saat klik tombol [data-confirm]
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-confirm]');
        if (!trigger) return;
        e.preventDefault();

        var type    = trigger.dataset.type    || 'danger';
        var title   = trigger.dataset.title   || 'Konfirmasi';
        var message = trigger.dataset.message || 'Apakah Anda yakin ingin melakukan aksi ini?';
        var action  = trigger.dataset.action  || '#';
        var id      = trigger.dataset.id      || '';
        var method  = (trigger.dataset.method || 'POST').toUpperCase();
        var label   = trigger.dataset.label   || 'Ya, Lanjutkan';

        // Isi konten modal
        titleEl.textContent  = title;
        msgEl.textContent    = message;
        idInput.value        = id;
        form.action          = action;
        form.method          = method === 'GET' ? 'GET' : 'POST';
        submitBtn.textContent= label;

        // Reset & set class submit button
        submitBtn.className = 'btn ' + (btnClasses[type] || 'btn-danger');

        // Set icon & warna header
        iconEl.className = 'modal-header-icon ' + type;
        iconEl.innerHTML = icons[type] || icons.danger;

        // Buka overlay
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    });

    // Tutup modal
    function closeModal() {
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }

    document.getElementById('confirmModalClose').addEventListener('click', closeModal);
    document.getElementById('confirmModalCancel').addEventListener('click', closeModal);

    // Klik di luar modal
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeModal();
    });

    // ESC key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('open')) closeModal();
    });
})();
</script>