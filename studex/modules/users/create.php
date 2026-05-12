<?php
// ============================================================
//  STUDEX — Student Index
//  modules/users/create.php — Tambah Pengguna Baru
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireSuperAdmin();

$db     = db();
$user   = currentUser();
$errors = [];
$input  = [
    'nama'      => '',
    'username'  => '',
    'email'     => '',
    'role'      => 'admin',
    'is_active' => 1,
];

// ============================================================
// PROSES FORM
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $input = [
        'nama'      => sanitize(post('nama')),
        'username'  => sanitize(post('username')),
        'email'     => sanitize(post('email')),
        'role'      => sanitize(post('role', 'admin')),
        'is_active' => sanitizeInt(post('is_active', 1)),
        'password'  => post('password'),
        'password2' => post('password2'),
    ];

    // Validasi
    if (!$input['nama'])
        $errors['nama'] = 'Nama wajib diisi.';
    if (!$input['username'])
        $errors['username'] = 'Username wajib diisi.';
    elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $input['username']))
        $errors['username'] = 'Username hanya boleh huruf, angka, underscore, min. 3 karakter.';
    if (!$input['email'])
        $errors['email'] = 'Email wajib diisi.';
    elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Format email tidak valid.';
    if (!in_array($input['role'], ['super_admin', 'admin']))
        $errors['role'] = 'Role tidak valid.';
    if (!$input['password'])
        $errors['password'] = 'Password wajib diisi.';
    elseif (strlen($input['password']) < 8)
        $errors['password'] = 'Password minimal 8 karakter.';
    if ($input['password'] !== $input['password2'])
        $errors['password2'] = 'Konfirmasi password tidak cocok.';

    // Cek username unik
    if (!isset($errors['username'])) {
        $cek = $db->prepare("SELECT id FROM users WHERE username = ?");
        $cek->execute([$input['username']]);
        if ($cek->fetch()) $errors['username'] = 'Username sudah digunakan.';
    }

    // Cek email unik
    if (!isset($errors['email'])) {
        $cek = $db->prepare("SELECT id FROM users WHERE email = ?");
        $cek->execute([$input['email']]);
        if ($cek->fetch()) $errors['email'] = 'Email sudah digunakan.';
    }

    if (empty($errors)) {
        $stmt = $db->prepare("
            INSERT INTO users
                (nama, username, email, password, role, is_active, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $input['nama'],
            $input['username'],
            $input['email'],
            Auth::hashPassword($input['password']),
            $input['role'],
            $input['is_active'] ? 1 : 0,
            $user['id'],
        ]);

        setFlash('success', 'Pengguna "' . $input['nama'] . '" berhasil ditambahkan.');
        redirect(url('modules/users/index.php'));
    }
}

// ============================================================
// LAYOUT
// ============================================================
$pageTitle   = 'Tambah Pengguna';
$activePage  = 'users';
$breadcrumbs = [
    ['label' => 'Dashboard',  'url' => url('modules/dashboard/index.php')],
    ['label' => 'Pengguna',   'url' => url('modules/users/index.php')],
    ['label' => 'Tambah Pengguna'],
];

ob_start();
?>

<div class="card" style="max-width:680px;">
    <div class="card-header">
        <div class="card-title">Form Tambah Pengguna</div>
        <div class="card-subtitle">Akses hanya untuk Super Admin</div>
    </div>

    <form method="POST" action="" novalidate>
        <?= csrfField() ?>

        <!-- Nama -->
        <div class="form-group mb-5">
            <label class="form-label">Nama Lengkap <span class="required">*</span></label>
            <input type="text" name="nama"
                   class="form-control <?= isset($errors['nama']) ? 'is-invalid' : '' ?>"
                   value="<?= e($input['nama']) ?>"
                   placeholder="Nama lengkap pengguna">
            <?php if (isset($errors['nama'])): ?>
                <div class="form-feedback invalid"><?= e($errors['nama']) ?></div>
            <?php endif; ?>
        </div>

        <!-- Username + Email -->
        <div class="form-row mb-5">
            <div class="form-group">
                <label class="form-label">Username <span class="required">*</span></label>
                <input type="text" name="username"
                       class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                       value="<?= e($input['username']) ?>"
                       placeholder="huruf, angka, underscore"
                       autocomplete="off">
                <?php if (isset($errors['username'])): ?>
                    <div class="form-feedback invalid"><?= e($errors['username']) ?></div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label class="form-label">Email <span class="required">*</span></label>
                <input type="email" name="email"
                       class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                       value="<?= e($input['email']) ?>"
                       placeholder="email@domain.com"
                       autocomplete="off">
                <?php if (isset($errors['email'])): ?>
                    <div class="form-feedback invalid"><?= e($errors['email']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Role + Status -->
        <div class="form-row mb-5">
            <div class="form-group">
                <label class="form-label">Role <span class="required">*</span></label>
                <select name="role" class="form-control <?= isset($errors['role']) ? 'is-invalid' : '' ?>">
                    <option value="admin"       <?= ($input['role'] ?? 'admin') === 'admin'       ? 'selected' : '' ?>>Admin</option>
                    <option value="super_admin" <?= ($input['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                </select>
                <div class="form-hint mt-1">
                    Super Admin dapat mengelola pengguna &amp; settings sistem.
                </div>
                <?php if (isset($errors['role'])): ?>
                    <div class="form-feedback invalid"><?= e($errors['role']) ?></div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label class="form-label">Status Akun</label>
                <select name="is_active" class="form-control">
                    <option value="1" <?= ($input['is_active'] ?? 1) == 1 ? 'selected' : '' ?>>Aktif</option>
                    <option value="0" <?= ($input['is_active'] ?? 1) == 0 ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
        </div>

        <!-- Divider -->
        <div class="section-divider mb-5">
            <span>Kata Sandi</span>
        </div>

        <!-- Password -->
        <div class="form-row mb-5">
            <div class="form-group">
                <label class="form-label">Password <span class="required">*</span></label>
                <div class="input-with-toggle">
                    <input type="password" name="password" id="password"
                           class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                           placeholder="Min. 8 karakter"
                           autocomplete="new-password">
                    <button type="button" class="toggle-pw" onclick="togglePw('password')">👁</button>
                </div>
                <?php if (isset($errors['password'])): ?>
                    <div class="form-feedback invalid"><?= e($errors['password']) ?></div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label class="form-label">Konfirmasi Password <span class="required">*</span></label>
                <div class="input-with-toggle">
                    <input type="password" name="password2" id="password2"
                           class="form-control <?= isset($errors['password2']) ? 'is-invalid' : '' ?>"
                           placeholder="Ulangi password"
                           autocomplete="new-password">
                    <button type="button" class="toggle-pw" onclick="togglePw('password2')">👁</button>
                </div>
                <?php if (isset($errors['password2'])): ?>
                    <div class="form-feedback invalid"><?= e($errors['password2']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Password strength hint -->
        <div class="password-hint mb-6">
            <div class="pw-strength-bar"><div id="pwBar" class="pw-strength-fill"></div></div>
            <div id="pwHint" class="pw-hint-text">Masukkan password untuk melihat kekuatannya.</div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                    <line x1="19" y1="8" x2="19" y2="14"/>
                    <line x1="22" y1="11" x2="16" y2="11"/>
                </svg>
                Tambah Pengguna
            </button>
            <a href="<?= url('modules/users/index.php') ?>" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>

<style>
.section-divider {
    display: flex; align-items: center; gap: 12px;
    color: var(--grey); font-size: 12px; font-weight: 600;
    text-transform: uppercase; letter-spacing: .5px;
}
.section-divider::before,
.section-divider::after {
    content: ''; flex: 1;
    height: 1px; background: var(--border);
}
.input-with-toggle { position: relative; }
.input-with-toggle .form-control { padding-right: 40px; }
.toggle-pw {
    position: absolute; right: 10px; top: 50%;
    transform: translateY(-50%);
    background: none; border: none;
    cursor: pointer; font-size: 16px; line-height: 1;
    padding: 0;
}
.password-hint { padding: 0 2px; }
.pw-strength-bar {
    height: 4px; background: var(--border);
    border-radius: 99px; overflow: hidden; margin-bottom: 6px;
}
.pw-strength-fill {
    height: 100%; width: 0;
    border-radius: 99px;
    transition: width 0.3s, background 0.3s;
}
.pw-hint-text { font-size: 12px; color: var(--grey); }
</style>

<script>
function togglePw(id) {
    const el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
}

// Password strength meter
document.getElementById('password').addEventListener('input', function () {
    const val  = this.value;
    const bar  = document.getElementById('pwBar');
    const hint = document.getElementById('pwHint');
    let score  = 0;

    if (val.length >= 8)  score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { pct: '0%',   color: '',              text: 'Masukkan password untuk melihat kekuatannya.' },
        { pct: '25%',  color: '#8B1408',       text: 'Lemah — tambahkan angka dan huruf kapital.' },
        { pct: '50%',  color: '#C97C10',       text: 'Cukup — coba tambahkan karakter spesial.' },
        { pct: '75%',  color: '#4C8C6A',       text: 'Kuat — password ini sudah cukup aman.' },
        { pct: '100%', color: 'var(--army-green)', text: 'Sangat kuat! 💪' },
    ];

    const lvl = !val ? levels[0] : levels[Math.max(1, Math.min(score, 4))];
    bar.style.width      = lvl.pct;
    bar.style.background = lvl.color;
    hint.textContent     = lvl.text;
    hint.style.color     = lvl.color || 'var(--grey)';
});
</script>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';
?>