<?php
// ============================================================
//  STUDEX — Student Index
//  modules/auth/forgot_password.php — Reset Password
//  Catatan: Sistem ini closed (internal), jadi reset password
//  dilakukan oleh Super Admin langsung via halaman profil user.
//  Halaman ini sebagai interface request ke Super Admin.
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

if (isLoggedIn()) {
    redirect(url('modules/dashboard/index.php'));
}

$submitted = false;
$error     = '';

// ============================================================
// PROSES — simpan request reset (catat di log/DB)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $identifier = sanitize(post('identifier'));

    if (empty($identifier)) {
        $error = 'Username atau email wajib diisi.';
    } else {
        // Cek apakah user ada
        $stmt = db()->prepare("
            SELECT id, nama, email FROM users
            WHERE (username = ? OR email = ?) AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        // Selalu tampilkan success meski user tidak ditemukan (keamanan)
        // Kalau user ada, catat request di settings sebagai notifikasi ke Super Admin
        if ($user) {
            try {
                $msg = 'Reset password diminta oleh: ' . $user['nama'] . ' (' . $user['email'] . ') pada ' . date('d M Y H:i');
                $stmt2 = db()->prepare("
                    INSERT INTO settings (kunci, nilai, label, tipe)
                    VALUES (?, ?, 'Reset Password Request', 'text')
                    ON DUPLICATE KEY UPDATE nilai = ?
                ");
                $key = 'reset_request_' . $user['id'];
                $stmt2->execute([$key, $msg, $msg]);
            } catch (\Exception $e) {
                // Non-fatal
            }
        }

        $submitted = true;
    }
}

// ============================================================
// TAMPILAN
// ============================================================
$pageTitle = 'Lupa Password';
$authTitle = "Lupa\nPassword?";
$authDesc  = 'Hubungi Super Admin untuk mereset password akun Anda.';

ob_start();
?>

<?php if (!$submitted): ?>

    <!-- Back link -->
    <a href="<?= url('modules/auth/login.php') ?>" class="auth-back-link">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" width="16" height="16"
             stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"/>
            <polyline points="12 19 5 12 12 5"/>
        </svg>
        Kembali ke Login
    </a>

    <!-- Greeting -->
    <div class="auth-greeting">
        <h2 class="auth-greeting-title">Reset Password</h2>
        <p class="auth-greeting-sub">
            Masukkan username atau email Anda. Super Admin akan mendapatkan notifikasi
            dan mereset password Anda segera.
        </p>
    </div>

    <!-- Info box -->
    <div class="auth-alert info mb-5" role="note">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" width="16" height="16"
             stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        Sistem ini bersifat tertutup. Reset password hanya bisa dilakukan oleh
        <strong>Super Admin</strong>.
    </div>

    <?php if ($error): ?>
        <div class="auth-alert error mb-4" role="alert">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" width="16" height="16"
                 stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <form class="auth-form" method="POST" action="" id="forgotForm" novalidate>
        <?= csrfField() ?>

        <div class="form-group">
            <label class="form-label" for="identifier">Username atau Email</label>
            <div class="input-group">
                <span class="input-icon input-icon-left">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8" width="18" height="18"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </span>
                <input
                    type="text"
                    id="identifier"
                    name="identifier"
                    class="form-control"
                    placeholder="Masukkan username atau email"
                    value="<?= e(post('identifier')) ?>"
                    autocomplete="username"
                    autofocus
                    required>
            </div>
        </div>

        <button type="submit" class="auth-submit-btn" id="forgotBtn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" width="18" height="18"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.73a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
            </svg>
            Kirim Permintaan Reset
        </button>

    </form>

    <div class="auth-footer-text">
        Sudah ingat password?
        <a href="<?= url('modules/auth/login.php') ?>">Masuk sekarang</a>
    </div>

    <script>
    document.getElementById('forgotForm').addEventListener('submit', function () {
        var btn = document.getElementById('forgotBtn');
        btn.classList.add('loading');
        btn.disabled = true;
    });
    </script>

<?php else: ?>

    <!-- Success State -->
    <a href="<?= url('modules/auth/login.php') ?>" class="auth-back-link">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" width="16" height="16"
             stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"/>
            <polyline points="12 19 5 12 12 5"/>
        </svg>
        Kembali ke Login
    </a>

    <div class="auth-success-state">

        <div class="auth-success-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" width="32" height="32"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
        </div>

        <h3 class="auth-success-title">Permintaan Terkirim!</h3>
        <p class="auth-success-desc">
            Permintaan reset password Anda telah dicatat. Super Admin akan segera
            mereset password Anda dan menginformasikannya secara langsung.
        </p>

        <a href="<?= url('modules/auth/login.php') ?>" class="auth-submit-btn"
           style="display:inline-flex; text-decoration:none; width:auto; padding: var(--space-3) var(--space-8);">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" width="18" height="18"
                 stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
            </svg>
            Kembali ke Login
        </a>

    </div>

<?php endif; ?>

<?php
$content = ob_get_clean();
include ROOT_PATH . '/view/layouts/auth.php';
?>