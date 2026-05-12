<?php
define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/google_drive.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireLogin();

$db           = db();
$angkatanList = $db->query("SELECT id, nama FROM angkatan ORDER BY tahun DESC")->fetchAll();
$pageTitle    = 'Jadwal Kegiatan';
$pageSubtitle = 'Kalender semua aktivitas siswa';
$activePage   = 'jadwal';
$breadcrumbs  = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Jadwal Kegiatan'],
];

ob_start();
?>

<div class="page-header">
    <div class="page-header-left">
        <h2 class="page-title"><?= e($pageTitle) ?></h2>
        <p class="page-subtitle"><?= e($pageSubtitle) ?></p>
    </div>
    <div class="page-header-right">
        <select id="filterAngkatan" class="form-control form-control-sm" style="width:200px" onchange="filterCalendar()">
            <option value="">Semua Angkatan</option>
            <?php foreach ($angkatanList as $a): ?>
                <option value="<?= e($a['id']) ?>"><?= e($a['nama_angkatan']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- Legend -->
<div class="card mb-3">
    <div class="card-body py-3">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="text-sm text-secondary fw-medium">Keterangan:</span>
            <span class="legend-badge" style="background:#395917">Rabuan</span>
            <span class="legend-badge" style="background:#4C8C6A">Mentoring</span>
            <span class="legend-badge" style="background:#595D75">Operasional</span>
            <span class="legend-badge" style="background:#C97C10">Binjas</span>
        </div>
    </div>
</div>

<!-- Calendar Card -->
<div class="card">
    <div class="card-body p-3">
        <div id="fullCalendar"></div>
    </div>
</div>

<!-- Event Detail Modal -->
<div id="eventModal" class="modal-overlay" style="display:none">
    <div class="modal-box" style="max-width:480px">
        <div class="modal-header">
            <h4 class="modal-title" id="eventModalTitle">Detail Kegiatan</h4>
            <button class="btn-close" onclick="closeEventModal()">×</button>
        </div>
        <div class="modal-body">
            <div id="eventModalBody"></div>
        </div>
        <div class="modal-footer">
            <a id="eventModalDetailLink" href="#" class="btn btn-primary btn-sm">Lihat Detail</a>
            <button class="btn btn-outline btn-sm" onclick="closeEventModal()">Tutup</button>
        </div>
    </div>
</div>

<!-- FullCalendar v6 CDN -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

<style>
.legend-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 20px;
    color: #fff;
    font-size: 12px;
    font-weight: 500;
}
#fullCalendar { min-height: 620px; }
.fc-toolbar-title { font-size: 18px !important; font-weight: 600 !important; }
.fc-button-primary {
    background: #395917 !important;
    border-color: #395917 !important;
    border-radius: 8px !important;
    font-size: 13px !important;
}
.fc-button-primary:hover { background: #2e4712 !important; border-color: #2e4712 !important; }
.fc-button-primary:not(:disabled).fc-button-active { background: #2e4712 !important; border-color: #2e4712 !important; }
.fc-today-button { background: #4C8C6A !important; border-color: #4C8C6A !important; }
.fc-event {
    cursor: pointer;
    border: none !important;
    font-size: 12px !important;
    padding: 2px 6px !important;
    border-radius: 4px !important;
}
.fc-day-today { background: #E6EFEA !important; }
.fc-daygrid-day-number { color: #121212 !important; }
</style>

<script>
const EVENTS_URL   = '<?= url('modules/jadwal/get_events.php') ?>';
let calendarInst   = null;
let filterAngkatan = '';

document.addEventListener('DOMContentLoaded', function () {
    calendarInst = new FullCalendar.Calendar(document.getElementById('fullCalendar'), {
        initialView: 'dayGridMonth',
        locale: 'id',
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,timeGridWeek,listMonth',
        },
        buttonText: {
            today: 'Hari ini',
            month: 'Bulan',
            week:  'Minggu',
            list:  'Daftar',
        },
        height: 'auto',
        events: function (fetchInfo, successCb, failureCb) {
            const params = new URLSearchParams({
                start:       fetchInfo.startStr,
                end:         fetchInfo.endStr,
                angkatan_id: filterAngkatan,
            });
            fetch(EVENTS_URL + '?' + params.toString())
                .then(r => r.json())
                .then(data => {
                    if (data.success) successCb(data.events);
                    else failureCb(data.message || 'Gagal memuat event');
                })
                .catch(failureCb);
        },
        eventClick: function (info) {
            showEventModal(info.event);
        },
        eventTimeFormat: {
            hour:     '2-digit',
            minute:   '2-digit',
            meridiem: false,
            hour12:   false,
        },
    });
    calendarInst.render();

    document.getElementById('eventModal').addEventListener('click', function (e) {
        if (e.target === this) closeEventModal();
    });
});

function filterCalendar() {
    filterAngkatan = document.getElementById('filterAngkatan').value;
    if (calendarInst) calendarInst.refetchEvents();
}

function showEventModal(ev) {
    const props = ev.extendedProps || {};
    document.getElementById('eventModalTitle').textContent = ev.title;

    let html = '<dl class="detail-list">';
    if (props.modul)     html += `<dt>Modul</dt><dd><span class="badge" style="background:${ev.backgroundColor};color:#fff;padding:3px 10px;border-radius:20px">${props.modul}</span></dd>`;
    if (props.angkatan)  html += `<dt>Angkatan</dt><dd>${props.angkatan}</dd>`;
    if (props.tanggal)   html += `<dt>Tanggal</dt><dd>${props.tanggal}</dd>`;
    if (props.lokasi)    html += `<dt>Lokasi</dt><dd>${props.lokasi}</dd>`;
    if (props.status)    html += `<dt>Status</dt><dd>${props.status}</dd>`;
    if (props.deskripsi) html += `<dt>Keterangan</dt><dd>${props.deskripsi}</dd>`;
    html += '</dl>';

    document.getElementById('eventModalBody').innerHTML = html;

    const link = document.getElementById('eventModalDetailLink');
    if (props.detail_url) { link.href = props.detail_url; link.style.display = ''; }
    else                  { link.style.display = 'none'; }

    document.getElementById('eventModal').style.display = 'flex';
}

function closeEventModal() {
    document.getElementById('eventModal').style.display = 'none';
}
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';