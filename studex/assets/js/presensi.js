// ============================================================
//  STUDEX — Student Index
//  assets/js/presensi.js — Bulk Presensi Handler
//  Fitur: select all · bulk status · quick input · auto save
// ============================================================

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        initPresensiTable();
        initBulkActions();
        initQuickSave();
        initSummaryCounter();
    });

    // ============================================================
    // 1. PRESENSI TABLE — init per baris
    // ============================================================
    function initPresensiTable() {
        var table = document.getElementById('presensiTable');
        if (!table) return;

        // Select all checkbox
        var checkAll = document.getElementById('checkAll');
        if (checkAll) {
            checkAll.addEventListener('change', function () {
                table.querySelectorAll('.row-check').forEach(function (cb) {
                    cb.checked = checkAll.checked;
                    highlightRow(cb.closest('tr'), cb.checked);
                });
                updateBulkBar();
            });
        }

        // Per-row checkbox
        table.querySelectorAll('.row-check').forEach(function (cb) {
            cb.addEventListener('change', function () {
                highlightRow(cb.closest('tr'), cb.checked);
                updateCheckAll();
                updateBulkBar();
            });
        });

        // Status radio/select per baris
        table.querySelectorAll('.status-select, .status-radio').forEach(function (el) {
            el.addEventListener('change', function () {
                updateRowStyle(el.closest('tr'), el.value);
                updateSummary();
                markUnsaved();
            });
        });

        // Keterangan input
        table.querySelectorAll('.keterangan-input').forEach(function (input) {
            input.addEventListener('input', debounce(function () {
                markUnsaved();
            }, 500));
        });
    }

    // ============================================================
    // 2. BULK ACTIONS BAR
    // ============================================================
    function initBulkActions() {
        var bar = document.getElementById('bulkActionBar');
        if (!bar) return;

        // Bulk set status
        var bulkStatusBtns = bar.querySelectorAll('[data-bulk-status]');
        bulkStatusBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var status   = btn.dataset.bulkStatus;
                var table    = document.getElementById('presensiTable');
                var checked  = table ? table.querySelectorAll('.row-check:checked') : [];

                checked.forEach(function (cb) {
                    var row    = cb.closest('tr');
                    var select = row.querySelector('.status-select');
                    var radios = row.querySelectorAll('.status-radio');

                    if (select) {
                        select.value = status;
                        updateRowStyle(row, status);
                    }
                    radios.forEach(function (r) {
                        if (r.value === status) {
                            r.checked = true;
                            updateRowStyle(row, status);
                        }
                    });
                });

                updateSummary();
                markUnsaved();

                if (typeof showToast === 'function') {
                    showToast('info', checked.length + ' siswa diset: ' + statusLabel(status));
                }
            });
        });

        // Bulk clear
        var clearBtn = bar.querySelector('#bulkClear');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                var table = document.getElementById('presensiTable');
                if (!table) return;
                table.querySelectorAll('.row-check').forEach(function (cb) {
                    cb.checked = false;
                    highlightRow(cb.closest('tr'), false);
                });
                var checkAll = document.getElementById('checkAll');
                if (checkAll) checkAll.checked = false;
                updateBulkBar();
            });
        }
    }

    // ============================================================
    // 3. QUICK SAVE — simpan semua via AJAX
    // ============================================================
    function initQuickSave() {
        var saveBtn = document.getElementById('savePresensiBtn');
        if (!saveBtn) return;

        saveBtn.addEventListener('click', function () {
            var form = document.getElementById('presensiForm');
            if (!form) return;

            saveBtn.classList.add('loading');
            saveBtn.disabled = true;

            var formData = new FormData(form);
            formData.append('ajax', '1');

            fetch(form.action, {
                method : 'POST',
                body   : formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                saveBtn.classList.remove('loading');
                saveBtn.disabled = false;

                if (data.success) {
                    clearUnsaved();
                    if (typeof showToast === 'function') {
                        showToast('success', 'Presensi tersimpan!', data.message || '');
                    }
                    // Update last saved time
                    var lastSaved = document.getElementById('lastSavedTime');
                    if (lastSaved) {
                        lastSaved.textContent = 'Terakhir disimpan: ' + new Date().toLocaleTimeString('id-ID');
                    }
                } else {
                    if (typeof showToast === 'function') {
                        showToast('danger', 'Gagal menyimpan', data.message || 'Coba lagi.');
                    }
                }
            })
            .catch(function () {
                saveBtn.classList.remove('loading');
                saveBtn.disabled = false;
                if (typeof showToast === 'function') {
                    showToast('danger', 'Koneksi bermasalah', 'Periksa koneksi internet Anda.');
                }
            });
        });
    }

    // ============================================================
    // 4. SUMMARY COUNTER — Hadir / Izin / Sakit / Alpha
    // ============================================================
    function initSummaryCounter() {
        updateSummary();
    }

    function updateSummary() {
        var table = document.getElementById('presensiTable');
        if (!table) return;

        var counts = { hadir: 0, izin: 0, sakit: 0, alpha: 0 };

        table.querySelectorAll('tbody tr').forEach(function (row) {
            var val = getRowStatus(row);
            if (counts.hasOwnProperty(val)) counts[val]++;
        });

        Object.keys(counts).forEach(function (k) {
            var el = document.getElementById('count_' + k);
            if (el) el.textContent = counts[k];
        });

        // Persentase kehadiran
        var total   = Object.values(counts).reduce(function (a, b) { return a + b; }, 0);
        var pctEl   = document.getElementById('hadirPct');
        var barEl   = document.getElementById('hadirBar');
        var pct     = total > 0 ? Math.round((counts.hadir / total) * 100) : 0;
        if (pctEl) pctEl.textContent = pct + '%';
        if (barEl) barEl.style.width = pct + '%';
    }

    // ============================================================
    // HELPER FUNCTIONS
    // ============================================================
    function getRowStatus(row) {
        var select = row.querySelector('.status-select');
        if (select) return select.value;
        var radio  = row.querySelector('.status-radio:checked');
        if (radio)  return radio.value;
        return 'alpha';
    }

    function updateRowStyle(row, status) {
        var classes = {
            hadir: 'row-hadir',
            izin : 'row-izin',
            sakit: 'row-sakit',
            alpha: 'row-alpha',
        };
        Object.values(classes).forEach(function (cls) { row.classList.remove(cls); });
        if (classes[status]) row.classList.add(classes[status]);

        // Inject inline style per status
        var styles = {
            hadir: 'rgba(45,122,79,0.06)',
            izin : 'rgba(193,216,218,0.2)',
            sakit: 'rgba(201,124,16,0.06)',
            alpha: 'rgba(139,20,8,0.05)',
        };
        row.style.backgroundColor = styles[status] || '';
    }

    function highlightRow(row, selected) {
        if (!row) return;
        if (selected) {
            row.style.backgroundColor = 'rgba(57,89,23,0.06)';
            row.style.outline = '1px solid rgba(57,89,23,0.2)';
        } else {
            row.style.backgroundColor = '';
            row.style.outline = '';
            // Re-apply status style
            updateRowStyle(row, getRowStatus(row));
        }
    }

    function updateCheckAll() {
        var table    = document.getElementById('presensiTable');
        var checkAll = document.getElementById('checkAll');
        if (!table || !checkAll) return;
        var all     = table.querySelectorAll('.row-check');
        var checked = table.querySelectorAll('.row-check:checked');
        checkAll.indeterminate = checked.length > 0 && checked.length < all.length;
        checkAll.checked       = checked.length === all.length && all.length > 0;
    }

    function updateBulkBar() {
        var bar     = document.getElementById('bulkActionBar');
        var countEl = document.getElementById('bulkSelectedCount');
        var table   = document.getElementById('presensiTable');
        if (!bar || !table) return;

        var count = table.querySelectorAll('.row-check:checked').length;
        if (countEl) countEl.textContent = count + ' siswa dipilih';

        if (count > 0) {
            bar.style.display = 'flex';
            bar.classList.add('fade-in');
        } else {
            bar.style.display = 'none';
        }
    }

    function markUnsaved() {
        var indicator = document.getElementById('unsavedIndicator');
        if (indicator) {
            indicator.style.display = 'inline-flex';
            indicator.textContent   = '● Belum tersimpan';
        }
        var saveBtn = document.getElementById('savePresensiBtn');
        if (saveBtn) saveBtn.classList.add('btn-primary');
    }

    function clearUnsaved() {
        var indicator = document.getElementById('unsavedIndicator');
        if (indicator) indicator.style.display = 'none';
        var saveBtn = document.getElementById('savePresensiBtn');
        if (saveBtn) saveBtn.classList.remove('btn-primary');
    }

    function statusLabel(status) {
        var map = { hadir: 'Hadir', izin: 'Izin', sakit: 'Sakit', alpha: 'Alpha' };
        return map[status] || status;
    }

    function debounce(fn, delay) {
        var t;
        return function () {
            clearTimeout(t);
            t = setTimeout(fn.bind(this, arguments), delay || 300);
        };
    }

    // ============================================================
    // PUBLIC API
    // ============================================================

    // Set status semua siswa sekaligus
    window.setAllPresensi = function (status) {
        var table = document.getElementById('presensiTable');
        if (!table) return;
        table.querySelectorAll('tbody tr').forEach(function (row) {
            var select = row.querySelector('.status-select');
            var radios = row.querySelectorAll('.status-radio');
            if (select) { select.value = status; updateRowStyle(row, status); }
            radios.forEach(function (r) { if (r.value === status) { r.checked = true; updateRowStyle(row, status); } });
        });
        updateSummary();
        markUnsaved();
        if (typeof showToast === 'function') {
            showToast('info', 'Semua siswa diset: ' + statusLabel(status));
        }
    };

    // Ekspor presensi ke CSV
    window.exportPresensiCSV = function () {
        var table = document.getElementById('presensiTable');
        if (!table) return;

        var rows    = [['No', 'NIS', 'Nama Siswa', 'Status', 'Keterangan']];
        var no = 1;

        table.querySelectorAll('tbody tr').forEach(function (row) {
            var cells = row.querySelectorAll('td');
            if (!cells.length) return;
            var nis  = cells[1] ? cells[1].textContent.trim() : '';
            var nama = cells[2] ? cells[2].textContent.trim() : '';
            var stat = statusLabel(getRowStatus(row));
            var ket  = (row.querySelector('.keterangan-input') || {}).value || '';
            rows.push([no++, nis, nama, stat, ket]);
        });

        var csv  = '\uFEFF' + rows.map(function (r) {
            return r.map(function (c) { return '"' + String(c).replace(/"/g,'""') + '"'; }).join(',');
        }).join('\r\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var url  = URL.createObjectURL(blob);
        var a    = document.createElement('a');
        a.href = url;
        a.download = 'presensi-' + new Date().toISOString().slice(0,10) + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        if (typeof showToast === 'function') {
            showToast('success', 'Export berhasil!', 'File CSV presensi telah diunduh.');
        }
    };

})();