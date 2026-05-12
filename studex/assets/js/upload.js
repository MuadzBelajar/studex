// ============================================================
//  STUDEX — Student Index
//  assets/js/upload.js — File Upload Handler
//  Drag & Drop · Preview · Validasi · Progress
// ============================================================

(function () {
    'use strict';

    // ============================================================
    // DOM READY
    // ============================================================
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-upload-zone]').forEach(function (zone) {
            initUploadZone(zone);
        });
    });

    // ============================================================
    // INIT UPLOAD ZONE
    // Cara pakai:
    //   <div data-upload-zone
    //        data-input="fileInputId"
    //        data-accept=".pdf,.docx"
    //        data-max-mb="10"
    //        data-multiple="false"
    //        data-preview-list="previewListId">
    //     ...
    //   </div>
    //   <input type="file" id="fileInputId" name="file" style="display:none">
    //   <div id="previewListId"></div>
    // ============================================================
    function initUploadZone(zone) {
        var inputId     = zone.dataset.input;
        var input       = inputId ? document.getElementById(inputId) : zone.querySelector('input[type="file"]');
        var accept      = zone.dataset.accept     || '';
        var maxMb       = parseFloat(zone.dataset.maxMb || '10');
        var multiple    = zone.dataset.multiple   !== 'false';
        var previewId   = zone.dataset.previewList;
        var previewList = previewId ? document.getElementById(previewId) : null;

        if (!input) {
            // Buat input tersembunyi
            input = document.createElement('input');
            input.type    = 'file';
            input.style.display = 'none';
            if (accept)   input.accept   = accept;
            if (multiple) input.multiple = true;
            zone.appendChild(input);
        }

        // Klik zone → buka file picker
        zone.addEventListener('click', function (e) {
            if (e.target.closest('.file-item')) return;
            input.click();
        });

        // Drag events
        zone.addEventListener('dragover', function (e) {
            e.preventDefault();
            zone.classList.add('drag-over');
        });

        zone.addEventListener('dragleave', function (e) {
            if (!zone.contains(e.relatedTarget)) {
                zone.classList.remove('drag-over');
            }
        });

        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('drag-over');
            var files = e.dataTransfer.files;
            handleFiles(files, input, zone, previewList, maxMb, accept, multiple);
        });

        // Input change
        input.addEventListener('change', function () {
            handleFiles(input.files, input, zone, previewList, maxMb, accept, multiple);
        });
    }

    // ============================================================
    // HANDLE FILES
    // ============================================================
    function handleFiles(files, input, zone, previewList, maxMb, accept, multiple) {
        if (!files || files.length === 0) return;

        var validFiles   = [];
        var acceptedExts = accept
            ? accept.split(',').map(function (a) { return a.trim().replace('.', '').toLowerCase(); })
            : [];

        Array.prototype.forEach.call(files, function (file) {
            // Validasi ekstensi
            if (acceptedExts.length > 0) {
                var ext = file.name.split('.').pop().toLowerCase();
                if (!acceptedExts.includes(ext)) {
                    if (typeof showToast === 'function') {
                        showToast('danger', 'Format tidak didukung',
                            file.name + ' — hanya ' + accept + ' yang diizinkan.');
                    }
                    return;
                }
            }

            // Validasi ukuran
            if (file.size > maxMb * 1024 * 1024) {
                if (typeof showToast === 'function') {
                    showToast('danger', 'File terlalu besar',
                        file.name + ' melebihi batas ' + maxMb + ' MB.');
                }
                return;
            }

            validFiles.push(file);
        });

        if (validFiles.length === 0) return;

        // Kalau tidak multiple, ambil file pertama saja
        if (!multiple) validFiles = [validFiles[0]];

        // Render preview
        if (previewList) {
            if (!multiple) previewList.innerHTML = ''; // reset kalau single
            validFiles.forEach(function (file) {
                renderFileItem(file, previewList, input);
            });
        }

        // Update zone UI
        updateZoneUI(zone, validFiles, multiple);
    }

    // ============================================================
    // RENDER FILE ITEM (preview card)
    // ============================================================
    function renderFileItem(file, previewList, input) {
        var ext      = file.name.split('.').pop().toLowerCase();
        var iconClass = getFileIconClass(ext);
        var sizeStr  = formatFileSize(file.size);
        var itemId   = 'file-item-' + Date.now() + '-' + Math.random().toString(36).slice(2);

        var item = document.createElement('div');
        item.className = 'file-item fade-in';
        item.id = itemId;
        item.innerHTML = [
            '<div class="file-item-icon ' + iconClass + '">',
            '  ' + getFileIconSvg(ext),
            '</div>',
            '<div class="file-item-info">',
            '  <div class="file-item-name" title="' + escHtml(file.name) + '">' + escHtml(file.name) + '</div>',
            '  <div class="file-item-size">' + sizeStr + '</div>',
            '</div>',
            '<button type="button" class="btn btn-icon btn-icon-sm btn-ghost" ',
            '        data-remove-file="' + itemId + '" aria-label="Hapus file" ',
            '        style="color:var(--color-danger); flex-shrink:0;">',
            '  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"',
            '       stroke="currentColor" stroke-width="2" width="14" height="14"',
            '       stroke-linecap="round" stroke-linejoin="round">',
            '    <line x1="18" y1="6" x2="6" y2="18"/>',
            '    <line x1="6" y1="6" x2="18" y2="18"/>',
            '  </svg>',
            '</button>',
        ].join('');

        // Hapus file item
        item.querySelector('[data-remove-file]').addEventListener('click', function (e) {
            e.stopPropagation();
            item.remove();
            // Reset input value
            input.value = '';
            // Reset zone UI jika list kosong
            var zone = document.querySelector('[data-upload-zone]');
            if (zone && previewList && previewList.children.length === 0) {
                resetZoneUI(zone);
            }
        });

        previewList.appendChild(item);
    }

    // ============================================================
    // UPDATE ZONE UI setelah file dipilih
    // ============================================================
    function updateZoneUI(zone, files, multiple) {
        var titleEl    = zone.querySelector('.upload-zone-title');
        var subtitleEl = zone.querySelector('.upload-zone-subtitle');
        var iconEl     = zone.querySelector('.upload-zone-icon');

        if (!titleEl) return;

        if (files.length === 1) {
            titleEl.textContent    = files[0].name;
            if (subtitleEl) subtitleEl.textContent = formatFileSize(files[0].size) + ' — klik untuk ganti';
        } else {
            titleEl.textContent    = files.length + ' file dipilih';
            if (subtitleEl) subtitleEl.textContent = 'Klik untuk menambah';
        }

        zone.style.borderColor = 'var(--primary)';
        zone.style.backgroundColor = 'var(--primary-light)';

        if (iconEl) {
            iconEl.innerHTML = [
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"',
                '     stroke="var(--primary)" stroke-width="1.5" width="48" height="48"',
                '     stroke-linecap="round" stroke-linejoin="round">',
                '  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>',
                '  <polyline points="22 4 12 14.01 9 11.01"/>',
                '</svg>',
            ].join('');
        }
    }

    function resetZoneUI(zone) {
        zone.style.borderColor    = '';
        zone.style.backgroundColor = '';
        var iconEl = zone.querySelector('.upload-zone-icon');
        if (iconEl) {
            iconEl.innerHTML = [
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"',
                '     stroke="currentColor" stroke-width="1.5" width="48" height="48"',
                '     stroke-linecap="round" stroke-linejoin="round">',
                '  <polyline points="16 16 12 12 8 16"/>',
                '  <line x1="12" y1="12" x2="12" y2="21"/>',
                '  <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>',
                '</svg>',
            ].join('');
        }
        var titleEl    = zone.querySelector('.upload-zone-title');
        var subtitleEl = zone.querySelector('.upload-zone-subtitle');
        if (titleEl)    titleEl.textContent    = 'Seret & lepas file di sini';
        if (subtitleEl) subtitleEl.innerHTML   = 'atau <span>pilih file</span> dari komputer';
    }

    // ============================================================
    // AJAX UPLOAD dengan Progress Bar
    // Cara pakai:
    //   uploadFile(file, url, {
    //     fieldName : 'notulensi',
    //     extraData : { rabuan_id: 5, csrf_token: '...' },
    //     onProgress: function(pct) {},
    //     onSuccess : function(data) {},
    //     onError   : function(err) {}
    //   });
    // ============================================================
    window.uploadFile = function (file, url, opts) {
        opts = opts || {};
        var fieldName = opts.fieldName || 'file';

        var formData = new FormData();
        formData.append(fieldName, file);

        if (opts.extraData) {
            Object.keys(opts.extraData).forEach(function (key) {
                formData.append(key, opts.extraData[key]);
            });
        }

        var xhr = new XMLHttpRequest();

        // Progress
        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable && typeof opts.onProgress === 'function') {
                opts.onProgress(Math.round((e.loaded / e.total) * 100));
            }
        });

        // Success
        xhr.addEventListener('load', function () {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.success) {
                    if (typeof opts.onSuccess === 'function') opts.onSuccess(data);
                } else {
                    if (typeof opts.onError === 'function') opts.onError(data.message || 'Upload gagal.');
                }
            } catch (e) {
                if (typeof opts.onError === 'function') opts.onError('Response tidak valid.');
            }
        });

        // Error
        xhr.addEventListener('error', function () {
            if (typeof opts.onError === 'function') opts.onError('Koneksi bermasalah.');
        });

        xhr.open('POST', url);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.send(formData);

        return xhr;
    };

    // ============================================================
    // PROGRESS BAR UI
    // ============================================================
    window.createUploadProgress = function (container) {
        var wrap = document.createElement('div');
        wrap.style.cssText = 'margin-top:var(--space-3);';
        wrap.innerHTML = [
            '<div style="display:flex;justify-content:space-between;',
            '            font-size:var(--text-xs);color:var(--text-muted);margin-bottom:4px;">',
            '  <span id="uploadProgressLabel">Mengunggah...</span>',
            '  <span id="uploadProgressPct">0%</span>',
            '</div>',
            '<div style="width:100%;height:6px;background:var(--neutral-100);',
            '            border-radius:var(--border-radius-full);overflow:hidden;">',
            '  <div id="uploadProgressBar"',
            '       style="height:100%;width:0%;background:var(--primary);',
            '              border-radius:var(--border-radius-full);',
            '              transition:width 0.2s ease;">',
            '  </div>',
            '</div>',
        ].join('');

        if (container) container.appendChild(wrap);

        return {
            el : wrap,
            set: function (pct) {
                var bar   = wrap.querySelector('#uploadProgressBar');
                var label = wrap.querySelector('#uploadProgressPct');
                if (bar)   bar.style.width   = pct + '%';
                if (label) label.textContent = pct + '%';
                if (pct >= 100) {
                    var lbl = wrap.querySelector('#uploadProgressLabel');
                    if (lbl) lbl.textContent = 'Selesai!';
                    if (bar) bar.style.background = 'var(--color-success)';
                }
            },
            remove: function () {
                setTimeout(function () { if (wrap.parentNode) wrap.parentNode.removeChild(wrap); }, 1500);
            }
        };
    };

    // ============================================================
    // HELPERS
    // ============================================================
    function getFileIconClass(ext) {
        var map = {
            pdf: 'pdf', doc: 'docx', docx: 'docx',
            ppt: 'pptx', pptx: 'pptx',
            xls: 'xlsx', xlsx: 'xlsx',
            jpg: 'image', jpeg: 'image', png: 'image', gif: 'image', webp: 'image',
        };
        return map[ext] || 'pdf';
    }

    function getFileIconSvg(ext) {
        var color = {
            pdf: '#8b1408', docx: '#2563eb', doc: '#2563eb',
            pptx: '#ea580c', ppt: '#ea580c',
            xlsx: '#16a34a', xls: '#16a34a',
        };
        var c = color[ext] || '#595d75';
        return [
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"',
            '     stroke="' + c + '" stroke-width="1.8" width="18" height="18"',
            '     stroke-linecap="round" stroke-linejoin="round">',
            '  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>',
            '  <polyline points="14 2 14 8 20 8"/>',
            '</svg>',
        ].join('');
    }

    function formatFileSize(bytes) {
        if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
        if (bytes >= 1048576)    return (bytes / 1048576).toFixed(2) + ' MB';
        if (bytes >= 1024)       return (bytes / 1024).toFixed(1) + ' KB';
        return bytes + ' B';
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

})();