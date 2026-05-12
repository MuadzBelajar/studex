// ============================================================
//  STUDEX — Student Index
//  assets/js/calendar.js — Jadwal Terpadu
//  FullCalendar v6 (CDN) + fallback Mini Calendar
//  Modul: Rabuan · Mentoring · Operasional · Binjas
// ============================================================

(function () {
    'use strict';

    var MODULE_COLORS = {
        rabuan     : { bg: '#395917', border: '#2d4712', text: '#fff' },
        mentoring  : { bg: '#4C8C6A', border: '#3d7055', text: '#fff' },
        operasional: { bg: '#C9A96E', border: '#b8925a', text: '#fff' },
        binjas     : { bg: '#595D75', border: '#484b5e', text: '#fff' },
        default    : { bg: '#C1D8DA', border: '#a8c5c8', text: '#121212' },
    };

    document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('studexCalendar');
        if (!el) return;
        if (typeof FullCalendar !== 'undefined') {
            initFullCalendar(el);
        } else {
            initMiniCalendar(el);
        }
    });

    // ============================================================
    // FULLCALENDAR
    // ============================================================
    function initFullCalendar(el) {
        var activeFilter = '';

        var cal = new FullCalendar.Calendar(el, {
            initialView : 'dayGridMonth',
            locale      : 'id',
            height      : 'auto',
            headerToolbar: {
                left  : 'prev,next today',
                center: 'title',
                right : 'dayGridMonth,timeGridWeek,listWeek',
            },
            buttonText: { today: 'Hari Ini', month: 'Bulan', week: 'Minggu', list: 'Agenda' },
            events: function (info, ok, fail) {
                fetchEvents(info.startStr, info.endStr, activeFilter, ok, fail);
            },
            eventDidMount: function (info) {
                info.el.title = info.event.title
                    + (info.event.extendedProps.modul ? ' [' + info.event.extendedProps.modul.toUpperCase() + ']' : '');
            },
            eventClick   : function (info) { showEventDetail(info.event); },
            dayMaxEvents : 3,
            nowIndicator : true,
        });

        cal.render();
        window._studexCalendar = cal;

        // Filter buttons
        document.querySelectorAll('[data-calendar-filter]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                activeFilter = btn.dataset.calendarFilter || '';
                document.querySelectorAll('[data-calendar-filter]').forEach(function (b) {
                    b.classList.remove('active', 'btn-primary');
                    b.classList.add('btn-secondary');
                });
                btn.classList.remove('btn-secondary');
                btn.classList.add('active', 'btn-primary');
                cal.refetchEvents();
            });
        });
    }

    // ============================================================
    // FETCH EVENTS
    // ============================================================
    function fetchEvents(start, end, modul, ok, fail) {
        var url = (window.STUDEX_BASE_URL || '') + '/api/calendar_events.php'
            + '?start=' + encodeURIComponent(start)
            + '&end='   + encodeURIComponent(end)
            + (modul ? '&modul=' + encodeURIComponent(modul) : '');

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) { ok([]); return; }
                ok((data.events || []).map(function (ev) {
                    var c = MODULE_COLORS[ev.modul] || MODULE_COLORS.default;
                    return {
                        id: ev.id, title: ev.title,
                        start: ev.start, end: ev.end || null, allDay: ev.allDay || false,
                        backgroundColor: c.bg, borderColor: c.border, textColor: c.text,
                        extendedProps: {
                            modul: ev.modul, status: ev.status,
                            lokasi: ev.lokasi || '', deskripsi: ev.deskripsi || '',
                            url_detail: ev.url_detail || '',
                        },
                    };
                }));
            })
            .catch(fail);
    }

    // ============================================================
    // EVENT DETAIL MODAL
    // ============================================================
    function showEventDetail(event) {
        var p    = event.extendedProps || {};
        var c    = MODULE_COLORS[p.modul] || MODULE_COLORS.default;
        var modal = document.getElementById('eventDetailModal');

        if (modal) {
            setEl(modal, '#eventDetailTitle',  event.title);
            setEl(modal, '#eventDetailModul',  p.modul ? p.modul.charAt(0).toUpperCase() + p.modul.slice(1) : '-');
            setEl(modal, '#eventDetailDate',   event.start
                ? event.start.toLocaleDateString('id-ID', { weekday:'long', day:'numeric', month:'long', year:'numeric' }) : '-');
            setEl(modal, '#eventDetailTime',   event.allDay ? 'Sepanjang hari'
                : (event.start ? event.start.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' }) : '-'));
            setEl(modal, '#eventDetailLokasi', p.lokasi    || '-');
            setEl(modal, '#eventDetailDesc',   p.deskripsi || '-');

            var dot = modal.querySelector('#eventDetailDot');
            if (dot) dot.style.backgroundColor = c.bg;

            var btn = modal.querySelector('#eventDetailBtn');
            if (btn) {
                btn.href = p.url_detail || '#';
                btn.style.display = p.url_detail ? 'inline-flex' : 'none';
            }

            if (p.status) {
                var statusEl = modal.querySelector('#eventDetailStatus');
                if (statusEl) statusEl.innerHTML = '<span class="badge badge-' + getStatusBadge(p.status) + '">' + p.status + '</span>';
            }

            if (typeof openModal === 'function') openModal('eventDetailModal');
            return;
        }

        // Fallback
        var msg = event.title;
        if (event.start) msg += '\n' + event.start.toLocaleDateString('id-ID');
        if (p.lokasi)    msg += '\nLokasi: ' + p.lokasi;
        alert(msg);
    }

    // ============================================================
    // MINI CALENDAR (fallback)
    // ============================================================
    function initMiniCalendar(container) {
        var today   = new Date();
        var cur     = { year: today.getFullYear(), month: today.getMonth() };
        var evMap   = {};
        var months  = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        var dayNames = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];

        container.innerHTML = [
            '<div class="mini-calendar">',
            '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">',
            '  <button class="btn btn-ghost btn-icon btn-icon-sm" id="calPrev">&#8249;</button>',
            '  <span id="calTitle" style="font-weight:600;font-size:var(--text-base);"></span>',
            '  <button class="btn btn-ghost btn-icon btn-icon-sm" id="calNext">&#8250;</button>',
            '</div>',
            '<div id="calGrid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;"></div>',
            '<div id="calEvents" style="margin-top:16px;border-top:1px solid var(--border-color);padding-top:16px;max-height:280px;overflow-y:auto;"></div>',
            '</div>',
        ].join('');

        function pad(n) { return n < 10 ? '0' + n : String(n); }

        function render() {
            document.getElementById('calTitle').textContent = months[cur.month] + ' ' + cur.year;
            var grid   = document.getElementById('calGrid');
            var first  = new Date(cur.year, cur.month, 1).getDay();
            var last   = new Date(cur.year, cur.month + 1, 0).getDate();
            var prev   = new Date(cur.year, cur.month, 0).getDate();
            var html   = dayNames.map(function (d) {
                return '<div style="text-align:center;font-size:10px;font-weight:600;color:var(--text-muted);padding:4px 0;">' + d + '</div>';
            }).join('');

            for (var i = 0; i < first; i++) {
                html += '<div style="text-align:center;padding:6px 2px;font-size:13px;color:var(--text-muted);opacity:.4;">' + (prev - first + i + 1) + '</div>';
            }

            for (var d = 1; d <= last; d++) {
                var key    = cur.year + '-' + pad(cur.month + 1) + '-' + pad(d);
                var isToday = d === today.getDate() && cur.month === today.getMonth() && cur.year === today.getFullYear();
                var hasEv   = evMap[key] && evMap[key].length > 0;
                var style   = isToday
                    ? 'background:var(--primary);color:#fff;font-weight:600;'
                    : '';
                html += '<div data-date="' + key + '" style="text-align:center;padding:6px 2px;font-size:13px;border-radius:8px;cursor:pointer;position:relative;' + style + '">'
                    + d
                    + (hasEv ? '<span style="position:absolute;bottom:2px;left:50%;transform:translateX(-50%);width:4px;height:4px;border-radius:50%;background:' + (isToday ? '#fff' : 'var(--primary)') + ';display:block;"></span>' : '')
                    + '</div>';
            }

            var rem = 42 - first - last;
            for (var n = 1; n <= rem; n++) {
                html += '<div style="text-align:center;padding:6px 2px;font-size:13px;color:var(--text-muted);opacity:.4;">' + n + '</div>';
            }

            grid.innerHTML = html;

            grid.querySelectorAll('[data-date]').forEach(function (el) {
                el.addEventListener('mouseenter', function () {
                    if (!el.style.background.includes('var(--primary)')) el.style.background = 'var(--bg-hover)';
                });
                el.addEventListener('mouseleave', function () {
                    if (!el.style.background.includes('var(--primary)')) el.style.background = '';
                });
                el.addEventListener('click', function () {
                    grid.querySelectorAll('[data-date]').forEach(function (x) {
                        if (!x.style.background.includes('var(--primary)')) x.style.background = '';
                    });
                    if (!el.style.background.includes('var(--primary)')) el.style.background = 'var(--primary-light)';
                    renderDayEvents(el.dataset.date);
                });
            });
        }

        function renderDayEvents(key) {
            var list = evMap[key] || [];
            var el   = document.getElementById('calEvents');
            if (!list.length) {
                el.innerHTML = '<p style="color:var(--text-muted);font-size:13px;text-align:center;padding:16px;">Tidak ada kegiatan</p>';
                return;
            }
            el.innerHTML = list.map(function (ev) {
                var c = MODULE_COLORS[ev.modul] || MODULE_COLORS.default;
                return '<div style="display:flex;gap:10px;padding:10px;border-radius:8px;background:var(--neutral-050);border-left:3px solid ' + c.bg + ';margin-bottom:8px;">'
                    + '<div style="width:10px;height:10px;border-radius:50%;background:' + c.bg + ';flex-shrink:0;margin-top:3px;"></div>'
                    + '<div><div style="font-size:13px;font-weight:500;">' + escHtml(ev.title) + '</div>'
                    + '<div style="font-size:11px;color:var(--text-muted);">' + (ev.modul || '') + (ev.lokasi ? ' · ' + ev.lokasi : '') + '</div></div>'
                    + '</div>';
            }).join('');
        }

        function load() {
            var s = cur.year + '-' + pad(cur.month + 1) + '-01';
            var e = cur.year + '-' + pad(cur.month + 1) + '-' + pad(new Date(cur.year, cur.month + 1, 0).getDate());
            fetch((window.STUDEX_BASE_URL || '') + '/api/calendar_events.php?start=' + s + '&end=' + e,
                  { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    evMap = {};
                    if (data.success && data.events) {
                        data.events.forEach(function (ev) {
                            var k = ev.start.slice(0, 10);
                            if (!evMap[k]) evMap[k] = [];
                            evMap[k].push(ev);
                        });
                    }
                    render();
                })
                .catch(function () { render(); });
        }

        document.getElementById('calPrev').addEventListener('click', function () {
            cur.month--; if (cur.month < 0) { cur.month = 11; cur.year--; } load();
        });
        document.getElementById('calNext').addEventListener('click', function () {
            cur.month++; if (cur.month > 11) { cur.month = 0; cur.year++; } load();
        });

        load();
    }

    // ============================================================
    // HELPERS
    // ============================================================
    function setEl(modal, sel, text) {
        var el = modal.querySelector(sel);
        if (el) el.textContent = text;
    }

    function getStatusBadge(s) {
        return { terjadwal:'info', berlangsung:'warning', selesai:'success', dibatalkan:'danger' }[s] || 'secondary';
    }

    function escHtml(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    window.studexCalendarRefetch = function () {
        if (window._studexCalendar) window._studexCalendar.refetchEvents();
    };

})();