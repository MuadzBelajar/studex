// ============================================================
//  STUDEX — Student Index
//  assets/js/table.js — DataTable Helper
//  Client-side search · Sort · Per-page · Export CSV
// ============================================================

(function () {
    'use strict';

    // ============================================================
    // DOM READY
    // ============================================================
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-table]').forEach(function (wrapper) {
            initTable(wrapper);
        });
    });

    // ============================================================
    // INIT TABLE
    // Cara pakai:
    //   <div data-table
    //        data-search="true"
    //        data-sortable="true"
    //        data-per-page="15"
    //        data-export="true">
    //     <table id="myTable">...</table>
    //   </div>
    // ============================================================
    function initTable(wrapper) {
        var table      = wrapper.querySelector('table');
        if (!table) return;

        var opts = {
            search   : wrapper.dataset.search    !== 'false',
            sortable : wrapper.dataset.sortable  !== 'false',
            perPage  : parseInt(wrapper.dataset.perPage  || '0'),   // 0 = tampilkan semua
            exportCsv: wrapper.dataset.export    === 'true',
        };

        var state = {
            query     : '',
            sortCol   : -1,
            sortDir   : 'asc',
            page      : 1,
            perPage   : opts.perPage || 9999,
            allRows   : [],
            filtered  : [],
        };

        // Ambil semua baris body
        var tbody = table.querySelector('tbody');
        if (!tbody) return;
        state.allRows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));

        // Buat toolbar di atas tabel
        var toolbar = buildToolbar(wrapper, opts, state, table);
        wrapper.insertBefore(toolbar, wrapper.firstChild);

        // Pagination wrapper di bawah tabel
        var paginationEl = document.createElement('div');
        paginationEl.className = 'table-pagination-wrapper';
        wrapper.appendChild(paginationEl);

        // Init sortable header
        if (opts.sortable) {
            initSort(table, state, function () { render(state, tbody, paginationEl, opts); });
        }

        // Render awal
        render(state, tbody, paginationEl, opts);

        // Expose refresh
        wrapper._tableRefresh = function () {
            state.allRows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
            state.page = 1;
            render(state, tbody, paginationEl, opts);
        };
    }

    // ============================================================
    // BUILD TOOLBAR
    // ============================================================
    function buildToolbar(wrapper, opts, state, table) {
        var toolbar = document.createElement('div');
        toolbar.className = 'flex items-center justify-between flex-wrap gap-3 mb-4';

        var leftEl  = document.createElement('div');
        leftEl.className = 'flex items-center gap-3 flex-wrap';

        var rightEl = document.createElement('div');
        rightEl.className = 'flex items-center gap-3';

        // Search input
        if (opts.search) {
            var searchWrap = document.createElement('div');
            searchWrap.className = 'input-group';
            searchWrap.style.cssText = 'min-width:220px; max-width:320px;';
            searchWrap.innerHTML = [
                '<span class="input-icon input-icon-left" style="pointer-events:none;">',
                '  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"',
                '       stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"',
                '       width="16" height="16"><circle cx="11" cy="11" r="8"/>',
                '       <line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
                '</span>',
                '<input type="text" class="form-control form-control-sm"',
                '       placeholder="Cari data..." autocomplete="off"',
                '       style="padding-left:36px;" data-table-search>',
            ].join('');

            var input = searchWrap.querySelector('[data-table-search]');
            input.addEventListener('input', debounce(function () {
                state.query = input.value.toLowerCase().trim();
                state.page  = 1;
                render(state, table.querySelector('tbody'),
                       wrapper.querySelector('.table-pagination-wrapper'), opts);
            }, 250));

            leftEl.appendChild(searchWrap);
        }

        // Per page select
        if (opts.perPage > 0) {
            var perPageWrap = document.createElement('div');
            perPageWrap.className = 'flex items-center gap-2';
            perPageWrap.style.fontSize = 'var(--text-sm)';
            perPageWrap.style.color    = 'var(--text-muted)';

            var selOptions = [10, 15, 25, 50, 100]
                .filter(function (v) { return v >= opts.perPage || v === opts.perPage; })
                .map(function (v) {
                    return '<option value="' + v + '"' + (v === opts.perPage ? ' selected' : '') + '>' + v + '</option>';
                }).join('');

            perPageWrap.innerHTML = [
                '<span>Tampilkan</span>',
                '<select class="form-control form-control-sm" style="width:70px;" data-table-perpage>',
                selOptions,
                '</select>',
                '<span>data</span>',
            ].join('');

            var sel = perPageWrap.querySelector('[data-table-perpage]');
            sel.addEventListener('change', function () {
                state.perPage = parseInt(this.value);
                state.page    = 1;
                render(state, table.querySelector('tbody'),
                       wrapper.querySelector('.table-pagination-wrapper'), opts);
            });

            leftEl.appendChild(perPageWrap);
        }

        // Export CSV button
        if (opts.exportCsv) {
            var exportBtn = document.createElement('button');
            exportBtn.className = 'btn btn-secondary btn-sm';
            exportBtn.innerHTML = [
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"',
                '     stroke="currentColor" stroke-width="2" width="14" height="14"',
                '     stroke-linecap="round" stroke-linejoin="round">',
                '  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>',
                '  <polyline points="7 10 12 15 17 10"/>',
                '  <line x1="12" y1="15" x2="12" y2="3"/>',
                '</svg>',
                'Export CSV',
            ].join('');
            exportBtn.addEventListener('click', function () {
                exportCSV(table, state.filtered.length ? state.filtered : state.allRows);
            });
            rightEl.appendChild(exportBtn);
        }

        toolbar.appendChild(leftEl);
        toolbar.appendChild(rightEl);
        return toolbar;
    }

    // ============================================================
    // RENDER — filter + sort + paginate + show/hide rows
    // ============================================================
    function render(state, tbody, paginationEl, opts) {
        // 1. Filter
        state.filtered = state.allRows.filter(function (row) {
            if (!state.query) return true;
            return row.textContent.toLowerCase().indexOf(state.query) !== -1;
        });

        // 2. Sort
        if (state.sortCol >= 0) {
            state.filtered.sort(function (a, b) {
                var aVal = getCellText(a, state.sortCol);
                var bVal = getCellText(b, state.sortCol);
                var aNum = parseFloat(aVal.replace(/[^0-9.-]/g, ''));
                var bNum = parseFloat(bVal.replace(/[^0-9.-]/g, ''));
                var cmp;
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    cmp = aNum - bNum;
                } else {
                    cmp = aVal.localeCompare(bVal, 'id');
                }
                return state.sortDir === 'asc' ? cmp : -cmp;
            });
        }

        // 3. Paginate
        var total      = state.filtered.length;
        var totalPages = Math.max(1, Math.ceil(total / state.perPage));
        state.page     = Math.min(state.page, totalPages);
        var start      = (state.page - 1) * state.perPage;
        var end        = start + state.perPage;
        var pageRows   = state.filtered.slice(start, end);

        // 4. Show/hide
        state.allRows.forEach(function (row) { row.style.display = 'none'; });
        pageRows.forEach(function (row) { row.style.display = ''; });

        // 5. Empty state
        var existing = tbody.querySelector('.table-empty-row');
        if (existing) existing.remove();
        if (state.filtered.length === 0) {
            var colCount = (tbody.closest('table').querySelector('thead tr') || { cells: { length: 5 } }).cells.length;
            var emptyRow = document.createElement('tr');
            emptyRow.className = 'table-empty-row';
            emptyRow.innerHTML = [
                '<td colspan="' + colCount + '">',
                '  <div class="table-empty">',
                '    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"',
                '         stroke="currentColor" stroke-width="1.5">',
                '      <circle cx="11" cy="11" r="8"/>',
                '      <line x1="21" y1="21" x2="16.65" y2="16.65"/>',
                '    </svg>',
                '    <p>Tidak ada data' + (state.query ? ' untuk "<strong>' + escHtml(state.query) + '</strong>"' : '') + '</p>',
                '  </div>',
                '</td>',
            ].join('');
            tbody.appendChild(emptyRow);
        }

        // 6. Render pagination
        if (paginationEl) renderPagination(paginationEl, state, total, totalPages, tbody, opts);
    }

    // ============================================================
    // PAGINATION
    // ============================================================
    function renderPagination(el, state, total, totalPages, tbody, opts) {
        el.innerHTML = '';
        if (totalPages <= 1 && total <= state.perPage) return;

        var from = (state.page - 1) * state.perPage + 1;
        var to   = Math.min(state.page * state.perPage, total);

        var wrap = document.createElement('div');
        wrap.className = 'flex items-center justify-between flex-wrap gap-3 mt-4';
        wrap.style.borderTop = '1px solid var(--border-color)';
        wrap.style.paddingTop = 'var(--space-4)';

        // Info
        var info = document.createElement('div');
        info.className = 'pagination-info';
        info.style.fontSize = 'var(--text-sm)';
        info.style.color    = 'var(--text-muted)';
        info.innerHTML = 'Menampilkan <strong>' + from + '–' + to + '</strong> dari <strong>' + total + '</strong> data';

        // Buttons
        var nav = document.createElement('nav');
        nav.className = 'pagination';

        function makeBtn(label, page, disabled, active) {
            var btn = document.createElement('button');
            btn.className = 'page-link' + (active ? ' active' : '') + (disabled ? ' disabled' : '');
            btn.innerHTML = label;
            btn.disabled  = disabled;
            if (!disabled && !active) {
                btn.addEventListener('click', function () {
                    state.page = page;
                    render(state, tbody, el, opts);
                });
            }
            var item = document.createElement('div');
            item.className = 'page-item';
            item.appendChild(btn);
            return item;
        }

        var chevL = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>';
        var chevR = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>';

        nav.appendChild(makeBtn(chevL, state.page - 1, state.page <= 1, false));

        var range = 2;
        var start = Math.max(1, state.page - range);
        var end   = Math.min(totalPages, state.page + range);

        if (start > 1) {
            nav.appendChild(makeBtn('1', 1, false, false));
            if (start > 2) {
                var dots = document.createElement('div');
                dots.className = 'page-item';
                dots.innerHTML = '<span class="page-link disabled" style="letter-spacing:2px;font-size:10px;">···</span>';
                nav.appendChild(dots);
            }
        }

        for (var i = start; i <= end; i++) {
            nav.appendChild(makeBtn(String(i), i, false, i === state.page));
        }

        if (end < totalPages) {
            if (end < totalPages - 1) {
                var dots2 = document.createElement('div');
                dots2.className = 'page-item';
                dots2.innerHTML = '<span class="page-link disabled" style="letter-spacing:2px;font-size:10px;">···</span>';
                nav.appendChild(dots2);
            }
            nav.appendChild(makeBtn(String(totalPages), totalPages, false, false));
        }

        nav.appendChild(makeBtn(chevR, state.page + 1, state.page >= totalPages, false));

        wrap.appendChild(info);
        wrap.appendChild(nav);
        el.appendChild(wrap);
    }

    // ============================================================
    // SORT
    // ============================================================
    function initSort(table, state, renderFn) {
        var headers = table.querySelectorAll('thead th');
        headers.forEach(function (th, idx) {
            if (th.dataset.noSort) return;

            th.style.cursor = 'pointer';
            th.style.userSelect = 'none';

            // Tambah icon sort
            var icon = document.createElement('span');
            icon.className = 'sort-icon';
            icon.style.cssText = 'margin-left:4px; opacity:0.35; font-size:10px; display:inline-block;';
            icon.textContent = '↕';
            th.appendChild(icon);

            th.addEventListener('click', function () {
                if (state.sortCol === idx) {
                    state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    state.sortCol = idx;
                    state.sortDir = 'asc';
                }
                // Reset semua icon
                headers.forEach(function (h) {
                    var ic = h.querySelector('.sort-icon');
                    if (ic) { ic.textContent = '↕'; ic.style.opacity = '0.35'; }
                });
                // Set icon aktif
                var activeIcon = th.querySelector('.sort-icon');
                if (activeIcon) {
                    activeIcon.textContent = state.sortDir === 'asc' ? '↑' : '↓';
                    activeIcon.style.opacity = '1';
                }
                state.page = 1;
                renderFn();
            });
        });
    }

    // ============================================================
    // EXPORT CSV
    // ============================================================
    function exportCSV(table, rows) {
        var headers = [];
        table.querySelectorAll('thead th').forEach(function (th) {
            // Skip kolom aksi
            if (!th.classList.contains('col-action') && th.dataset.noExport !== 'true') {
                headers.push('"' + th.textContent.trim().replace(/"/g, '""') + '"');
            }
        });

        var skipCols = [];
        table.querySelectorAll('thead th').forEach(function (th, i) {
            if (th.classList.contains('col-action') || th.dataset.noExport === 'true') {
                skipCols.push(i);
            }
        });

        var lines = [headers.join(',')];

        rows.forEach(function (row) {
            var cells = [];
            row.querySelectorAll('td').forEach(function (td, i) {
                if (skipCols.indexOf(i) !== -1) return;
                var text = td.textContent.trim().replace(/\s+/g, ' ').replace(/"/g, '""');
                cells.push('"' + text + '"');
            });
            lines.push(cells.join(','));
        });

        var csv  = '\uFEFF' + lines.join('\r\n'); // BOM untuk Excel
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var url  = URL.createObjectURL(blob);
        var a    = document.createElement('a');
        a.href   = url;
        a.download = 'studex-export-' + new Date().toISOString().slice(0,10) + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        if (typeof showToast === 'function') {
            showToast('success', 'Export berhasil!', 'File CSV telah diunduh.');
        }
    }

    // ============================================================
    // HELPERS
    // ============================================================
    function getCellText(row, colIdx) {
        var cells = row.querySelectorAll('td');
        return cells[colIdx] ? cells[colIdx].textContent.trim().toLowerCase() : '';
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function debounce(fn, delay) {
        var t;
        return function () {
            clearTimeout(t);
            var args = arguments;
            var ctx  = this;
            t = setTimeout(function () { fn.apply(ctx, args); }, delay || 300);
        };
    }

})();