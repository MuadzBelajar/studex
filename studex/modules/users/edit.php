<?php
// ============================================================
//  STUDEX — Student Index
//  modules/users/edit.php — Edit Data User
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/google_drive.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

requireSuperAdmin();

$db = db();
$id = sanitizeInt(get('id'));

if (!$id) {
    setFlash('error', 'ID user tidak valid.');
    redirect(url('modules/users/index.php'));
}

$user = $db->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$id]);
$user = $user->fetch();

if (!$user) {
    setFlash('error', 'User tidak ditemukan.');
    redirect(url('modules/users/index.php'));
}

// Edit diri sendiri → pakai profile.php
if ((int)$user['id'] === (int)$_SESSION['user_id']) {
    setFlash('info', 'Untuk mengedit akun sendiri, gunakan halaman Profil.');
    redirect(url('modules/users/profile.php'));
}

$errors = [];
$input  = $user;

// ============================================================
// PROSES FORM
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $input = [
        'nama'      => sanitize(post('nama')),
        'username'  => sanitize(post('username')),
        'email'     => sanitize(post('email')),
        'role'      => sanitize(post('role')),
        'is_active' => post('is_active') ? 1 : 0,
        'password'  => post('password'),
    ];

    if (!$input['nama'])     $errors['nama']     = 'Nama wajib diisi.';
    if (!$input['username']) $errors['username'] = 'Username wajib diisi.';
    if (!$input['email'])    $errors['email']    = 'Email wajib diisi.';

    if ($input['email'] && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid.';
    }

    if (!in_array($input['role'], ['super_admin', 'admin'])) {
        $errors['role'] = 'Role tidak valid.';
    }

    if (!isset($errors['username'])) {
        $cek = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $cek->execute([$input['username'], $id]);
        if ($cek->fetch()) $errors['username'] = 'Username sudah digunakan user lain.';
    }

    if (!isset($errors['email'])) {
        $cek = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $cek->execute([$input['email'], $id]);
        if ($cek->fetch()) $errors['email'] = 'Email sudah digunakan user lain.';
    }

    if ($input['password'] && strlen($input['password']) < 8) {
        $errors['password'] = 'Password minimal 8 karakter.';
    }

    if (empty($errors)) {
        if ($input['password']) {
            $stmt = $db->prepare("
                UPDATE users SET nama=?, username=?, email=?, role=?, is_active=?,
                                 password=?, updated_at=NOW()
                WHERE id=?
            ");
            $stmt->execute([
                $input['nama'], $input['username'], $input['email'],
                $input['role'], $input['is_active'],
                password_hash($input['password'], PASSWORD_BCRYPT),
                $id,
            ]);
        } else {
            $stmt = $db->prepare("
                UPDATE users SET nama=?, username=?, email=?, role=?, is_active=?,
                                 updated_at=NOW()
                WHERE id=?
            ");
            $stmt->execute([
                $input['nama'], $input['username'], $input['email'],
                $input['role'], $input['is_active'],
                $id,
            ]);
        }

        setFlash('success', 'User ' . e($input['nama']) . ' berhasil diperbarui.');
        redirect(url('modules/users/index.php'));
    }
}

// ============================================================
// LAYOUT
// ============================================================
$pageTitle    = 'Edit User';
$pageSubtitle = 'Ubah data akun pengguna';
$activePage   = 'users';
$breadcrumbs  = [
    ['label' => 'Dashboard', 'url' => url('modules/dashboard/index.php')],
    ['label' => 'Users',     'url' => url('modules/users/index.php')],
    ['label' => 'Edit — ' . e($user['nama'])],
];

ob_start();
?>

<div class="card" style="max-width:640px">
    <div class="card-header">
        <div class="card-header-left">
            <div class="avatar avatar-md" style="background:var(--color-army)">
                <?= getInitials($user['nama']) ?>
            </div>
            <div>
                <div class="card-title"><?= e($user['nama']) ?></div>
                <div style="font-size:var(--text-xs);color:var(--text-muted)">
                    @<?= e($user['username']) ?>
                    &middot;
                    <span class="badge <?= $user['role'] === 'super_admin' ? 'badge-primary' : 'badge-secondary' ?>">
                        <?= $user['role'] === 'super_admin' ? 'Super Admin' : 'Admin' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="" novalidate>
        <?= csrfField() ?>

        <!-- Nama -->
        <div class="form-group mb-5">
            <label class="form-label">Nama Lengkap <span class="required">*</span></label>
            <input type="text" name="nama"
                   class="form-control <?= isset($errors['nama']) ? 'is-invalid' : '' ?>"
                   value="<?= e($input['nama'] ?? '') ?>"
                   placeholder="Nama lengkap">
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
                       value="<?= e($input['username'] ?? '') ?>"
                       placeholder="username" autocomplete="off">
                <?php if (isset($errors['username'])): ?>
                    <div class="form-feedback invalid"><?= e($errors['username']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Email <span class="required">*</span></label>
                <input type="email" name="email"
                       class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                       value="<?= e($input['email'] ?? '') ?>"
                       placeholder="email@domain.com">
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
                    <option value="admin"       <?= ($input['role'] ?? '') === 'admin'       ? 'selected' : '' ?>>Admin</option>
                    <option value="super_admin" <?= ($input['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                </select>
                <?php if (isset($errors['role'])): ?>
                    <div class="form-feedback invalid"><?= e($errors['role']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Status Akun</label>
                <div class="d-flex align-items-center gap-3" style="height:42px">
                    <label class="toggle-switch">
                        <input type="hidden"   name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1"
                               <?= ($input['is_active'] ?? 1) ? 'checked' : '' ?>
                               onchange="document.getElementById('activeLabel').textContent = this.checked ? 'Aktif' : 'Nonaktif'">
                        <span class="toggle-slider"></span>
                    </label>
                    <span class="text-sm text-secondary" id="activeLabel">
                        <?= ($input['is_active'] ?? 1) ? 'Aktif' : 'Nonaktif' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Password baru (opsional) -->
        <div class="form-group mb-6">
            <label class="form-label">
                Password Baru
                <span class="text-secondary text-sm">(kosongkan jika tidak ingin diubah)</span>
            </label>
            <div class="input-group">
                <input type="password" name="password" id="inputPassword"
                       class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                       placeholder="Minimal 8 karakter"
                       autocomplete="new-password">
                <button type="button" class="btn btn-outline"
                        onclick="const el=document.getElementById('inputPassword');el.type=el.type==='password'?'text':'password'">👁</button>
            </div>
            <?php if (isset($errors['password'])): ?>
                <div class="form-feedback invalid"><?= e($errors['password']) ?></div>
            <?php endif; ?>
            <div class="form-hint">Kosongkan jika tidak ingin mengganti password saat ini.</div>
        </div>

        <!-- Actions -->
        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" width="16" height="16"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
                Simpan Perubahan
            </button>
            <a href="<?= url('modules/users/index.php') ?>" class="btn btn-secondary">Batal</a>
        </div>

    </form>
</div>

<style>
.toggle-switch { position:relative; display:inline-block; width:44px; height:24px; flex-shrink:0; }
.toggle-switch input { opacity:0; width:0; height:0; }
.toggle-slider { position:absolute; inset:0; background:#ccc; border-radius:24px; cursor:pointer; transition:.2s; }
.toggle-slider::before { content:''; position:absolute; width:18px; height:18px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.2s; }
.toggle-switch input:checked + .toggle-slider { background:#395917; }
.toggle-switch input:checked + .toggle-slider::before { transform:translateX(20px); }
.form-hint { font-size:12px; color:var(--text-muted); margin-top:4px; }
</style>

<?php
$content = ob_get_clean();
require_once ROOT_PATH . '/view/layouts/main.php';