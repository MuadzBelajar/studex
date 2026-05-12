<?php
// ============================================================
//  STUDEX — Student Index
//  modules/users/profile.php — Profil & Edit Akun Sendiri
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/google_drive.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireLogin();

$db     = db();
$userId = (int)$_SESSION['user_id'];

// Ambil data user yang sedang login
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    // Session tidak valid — paksa logout
    redirect(url('modules/auth/logout.php'));
}

$errors      = [];
$errorsPw    = [];
$input       = $user;
$activeTab   = $_GET['tab'] ?? 'profil'; // profil | password

// ============================================================
// PROSES FORM: Update Profil
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'update_profil') {
    verifyCsrf();

    $input = [
        'nama'  => sanitize(post('nama')),
        'email' => sanitize(post('email')),
    ];

    if (!$input['nama'])  $errors['nama']  = 'Nama wajib diisi.';
    if (!$input['email']) $errors['email'] = 'Email wajib diisi.';

    if ($input['email'] && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid.';
    }

    // Email unik (exclude diri sendiri)
    if (!isset($errors['email'])) {
        $cek = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $cek->execute([$input['email'], $userId]);
        if ($cek->fetch()) $errors['email'] = 'Email sudah digunakan akun lain.';
    }

    if (empty($errors)) {
        $db->prepare("UPDATE users SET nama=?, email=?, updated_at=NOW() WHERE id=?")
           ->execute([$input['nama'], $input['email'], $userId]);

        // Update nama di session agar topbar langsung sinkron
        $_SESSION['user_nama'] = $input['nama'];

        setFlash('success', 'Profil berhasil diperbarui.');
        redirect(url('modules/users/profile.php?tab=profil'));
    }

    $activeTab = 'profil';
}

// ============================================================
// PROSES FORM: Ganti Password
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'ganti_password') {
    verifyCsrf();

    $pwLama  = post('password_lama');
    $pwBaru  = post('password_baru');
    $pwUlang = post('password_ulang');

    if (!$pwLama)  $errorsPw['password_lama']  = 'Password lama wajib diisi.';
    if (!$pwBaru)  $errorsPw['password_baru']  = 'Password baru wajib diisi.';
    if (!$pwUlang) $errorsPw['password_ulang'] = 'Konfirmasi password wajib diisi.';

    // Verifikasi password lama
    if (!isset($errorsPw['password_lama']) && !password_verify($pwLama, $user['password'])) {
        $errorsPw['password_lama'] = 'Password lama tidak sesuai.';
    }

    if (!isset($errorsPw['password_baru']) && strlen($pwBaru) < 8) {
        $errorsPw['password_baru'] = 'Password baru minimal 8 karakter.';
    }

    if (!isset($errorsPw['password_ulang']) && $pwBaru !== $pwUlang) {
        $errorsPw['password_ulang'] = 'Konfirmasi password tidak cocok.';
    }

    if (empty($errorsPw)) {
        $db->prepare("UPDATE users SET password=?, updated_at=NOW() WHERE id=?")
           ->execute([password_hash($pwBaru, PASSWORD_BCRYPT), $userId]);

        setFlash('success', 'Password berhasil diubah. Silakan login ulang.');
        redirect(url('modules/auth/logout.php'));
    }

    $activeTab = 'password';
}

// ============================================================
// Statistik singkat user
// ============================================================
$statKegiatan = (int)$db->prepare("SELECT COUNT(*) FROM rabuan WHERE created_by = ?")
    ->execute([$userId]) ? $db->query("SELECT COUNT(*) FROM rabuan WHERE created_by = {$userId}")->fetchColumn() : 0;

$lastLogin = $user['last_login']
    ? timeAgo($user['last_login'])
    : 'Belum pernah login sebelumnya';

// ============================================================
// LAYOUT
// ============================================================
$pageTitle    = 'Profil Saya';
$pageSubtitle = 'Kelola informasi akun Anda';
$activePage   = 'profile';
$breadcrumbs  = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Profil Saya'],
];

ob_start();
?>

<div class="row g-4" style="align-items:flex-start">

    <!-- ── Kolom Kiri: Info Card ───────────────────────────── -->
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body py-5">
                <!-- Avatar -->
                <div class="avatar avatar-xl mx-auto mb-3"
                     style="background:var(--color-army);font-size:32px;width:80px;height:80px">
                    <?= getInitials($user['nama']) ?>
                </div>

                <h3 style="font-size:var(--text-lg);font-weight:600;margin-bottom:4px">
                    <?= e($user['nama']) ?>
                </h3>
                <p class="text-muted text-sm mb-3">@<?= e($user['username']) ?></p>

                <?= roleLabel($user['role']) ?>

                <hr style="margin:20px 0">

                <dl class="detail-list text-left" style="text-align:left">
                    <dt>Email</dt>
                    <dd><?= e($user['email']) ?></dd>

                    <dt>Status</dt>
                    <dd>
                        <span class="badge <?= $user['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                            <?= $user['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                        </span>
                    </dd>

                    <dt>Login Terakhir</dt>
                    <dd><?= e($lastLogin) ?></dd>

                    <dt>Bergabung</dt>
                    <dd><?= formatTanggal($user['created_at']) ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <!-- ── Kolom Kanan: Tab Form ───────────────────────────── -->
    <div class="col-md-8">

        <?php flash() ?>

        <!-- Tab Nav -->
        <div class="tab-nav mb-4">
            <a href="?tab=profil"
               class="tab-item <?= $activeTab === 'profil' ? 'active' : '' ?>">
                ✏️ Edit Profil
            </a>
            <a href="?tab=password"
               class="tab-item <?= $activeTab === 'password' ? 'active' : '' ?>">
                🔒 Ganti Password
            </a>
        </div>

        <!-- ── Tab: Edit Profil ──────────────────────────── -->
        <?php if ($activeTab === 'profil'): ?>
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Informasi Akun</h4>
            </div>
            <form method="POST" action="" novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_profil">

                <!-- Nama -->
                <div class="form-group mb-5">
                    <label class="form-label">Nama Lengkap <span class="required">*</span></label>
                    <input type="text" name="nama"
                           class="form-control <?= isset($errors['nama']) ? 'is-invalid' : '' ?>"
                           value="<?= e($input['nama'] ?? '') ?>"
                           placeholder="Nama lengkap Anda">
                    <?php if (isset($errors['nama'])): ?>
                        <div class="form-feedback invalid"><?= e($errors['nama']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Username (readonly) -->
                <div class="form-group mb-5">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control"
                           value="<?= e($user['username']) ?>"
                           disabled readonly>
                    <div class="form-hint">Username tidak dapat diubah.</div>
                </div>

                <!-- Email -->
                <div class="form-group mb-6">
                    <label class="form-label">Email <span class="required">*</span></label>
                    <input type="email" name="email"
                           class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                           value="<?= e($input['email'] ?? '') ?>"
                           placeholder="email@domain.com">
                    <?php if (isset($errors['email'])): ?>
                        <div class="form-feedback invalid"><?= e($errors['email']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" width="16" height="16"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                            <polyline points="17 21 17 13 7 13 7 21"/>
                            <polyline points="7 3 7 8 15 8"/>
                        </svg>
                        Simpan Profil
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- ── Tab: Ganti Password ───────────────────────── -->
        <?php if ($activeTab === 'password'): ?>
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Ganti Password</h4>
            </div>
            <form method="POST" action="" novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="action" value="ganti_password">

                <!-- Password Lama -->
                <div class="form-group mb-5">
                    <label class="form-label">Password Lama <span class="required">*</span></label>
                    <div class="input-group">
                        <input type="password" name="password_lama" id="pwLama"
                               class="form-control <?= isset($errorsPw['password_lama']) ? 'is-invalid' : '' ?>"
                               placeholder="Password saat ini"
                               autocomplete="current-password">
                        <button type="button" class="btn btn-outline"
                                onclick="togglePw('pwLama')">👁</button>
                    </div>
                    <?php if (isset($errorsPw['password_lama'])): ?>
                        <div class="form-feedback invalid"><?= e($errorsPw['password_lama']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Password Baru -->
                <div class="form-group mb-5">
                    <label class="form-label">Password Baru <span class="required">*</span></label>
                    <div class="input-group">
                        <input type="password" name="password_baru" id="pwBaru"
                               class="form-control <?= isset($errorsPw['password_baru']) ? 'is-invalid' : '' ?>"
                               placeholder="Minimal 8 karakter"
                               autocomplete="new-password"
                               oninput="checkStrength(this.value)">
                        <button type="button" class="btn btn-outline"
                                onclick="togglePw('pwBaru')">👁</button>
                    </div>
                    <?php if (isset($errorsPw['password_baru'])): ?>
                        <div class="form-feedback invalid"><?= e($errorsPw['password_baru']) ?></div>
                    <?php endif; ?>
                    <!-- Password strength bar -->
                    <div class="pw-strength-wrap mt-2" style="display:none" id="strengthWrap">
                        <div class="pw-strength-bar">
                            <div class="pw-strength-fill" id="strengthFill"></div>
                        </div>
                        <span class="text-xs" id="strengthLabel"></span>
                    </div>
                </div>

                <!-- Konfirmasi Password -->
                <div class="form-group mb-6">
                    <label class="form-label">Konfirmasi Password Baru <span class="required">*</span></label>
                    <div class="input-group">
                        <input type="password" name="password_ulang" id="pwUlang"
                               class="form-control <?= isset($errorsPw['password_ulang']) ? 'is-invalid' : '' ?>"
                               placeholder="Ulangi password baru"
                               autocomplete="new-password">
                        <button type="button" class="btn btn-outline"
                                onclick="togglePw('pwUlang')">👁</button>
                    </div>
                    <?php if (isset($errorsPw['password_ulang'])): ?>
                        <div class="form-feedback invalid"><?= e($errorsPw['password_ulang']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="alert alert-warning mb-5" style="font-size:13px">
                    ⚠️ Setelah password berhasil diubah, Anda akan otomatis <strong>logout</strong> dan diminta login ulang.
                </div>

                <button type="submit" class="btn btn-primary">
                    🔒 Ubah Password
                </button>
            </form>
        </div>
        <?php endif; ?>

    </div><!-- .col -->
</div><!-- .row -->

<style>
/* Tab Nav */
.tab-nav {
    display: flex;
    gap: 4px;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 0;
}
.tab-item {
    padding: 8px 20px;
    border-radius: 8px 8px 0 0;
    font-size: 14px;
    font-weight: 500;
    color: var(--text-muted);
    text-decoration: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all .15s;
}
.tab-item:hover { color: var(--color-army); background: var(--primary-light); }
.tab-item.active { color: var(--color-army); border-bottom-color: var(--color-army); background: transparent; }

/* Password strength */
.pw-strength-bar {
    height: 4px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 4px;
}
.pw-strength-fill {
    height: 100%;
    border-radius: 4px;
    transition: width .3s, background .3s;
    width: 0%;
}

.form-hint { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
</style>

<script>
function togglePw(id) {
    const el = document.getElementById(id);
    el.type  = el.type === 'password' ? 'text' : 'password';
}

function checkStrength(val) {
    const wrap  = document.getElementById('strengthWrap');
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');
    if (!val) { wrap.style.display = 'none'; return; }

    wrap.style.display = '';
    let score = 0;
    if (val.length >= 8)              score++;
    if (val.length >= 12)             score++;
    if (/[A-Z]/.test(val))            score++;
    if (/[0-9]/.test(val))            score++;
    if (/[^A-Za-z0-9]/.test(val))     score++;

    const levels = [
        { pct: '20%', color: '#8B1408', text: 'Sangat Lemah' },
        { pct: '40%', color: '#C97C10', text: 'Lemah' },
        { pct: '60%', color: '#C97C10', text: 'Cukup' },
        { pct: '80%', color: '#4C8C6A', text: 'Kuat' },
        { pct: '100%', color: '#395917', text: 'Sangat Kuat' },
    ];
    const lvl       = levels[Math.min(score, 4)];
    fill.style.width      = lvl.pct;
    fill.style.background = lvl.color;
    label.textContent     = lvl.text;
    label.style.color     = lvl.color;
}
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';