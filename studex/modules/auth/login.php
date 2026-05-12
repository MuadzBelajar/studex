<?php
// ============================================================
//  STUDEX — Student Index
//  modules/auth/login.php — Halaman Login
// ============================================================

define('STUDEX', true);
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/google_drive.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Helpers.php';

// Kalau sudah login → dashboard
if (isLoggedIn()) {
    redirect(url('modules/dashboard/index.php'));
}

$error   = '';
$success = '';

// ============================================================
// PROSES LOGIN
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $username = sanitize(post('username'));
    $password = post('password');
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } else {
        $result = Auth::login($username, $password);

        if ($result['success']) {
            // Remember me — perpanjang session
            if ($remember) {
                ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60); // 30 hari
                session_set_cookie_params(30 * 24 * 60 * 60);
            }

            setFlash('success', $result['message']);
            redirect(url('modules/dashboard/index.php'));
        } else {
            $error = $result['message'];
        }
    }
}

// ============================================================
// TAMPILAN
// ============================================================
$pageTitle = 'Login';
$authTitle = "Selamat datang\nkembali!";
$authDesc  = 'Sistem monitoring aktivitas siswa yang terintegrasi, terstruktur, dan mudah digunakan.';

ob_start();
?>

<!-- Greeting -->
<div class="auth-greeting">
    <h2 class="auth-greeting-title">Masuk ke STUDEX</h2>
    <p class="auth-greeting-sub">Gunakan kredensial yang diberikan oleh Super Admin.</p>
</div>

<!-- Error alert -->
<?php if ($error): ?>
    <div class="auth-alert error mb-4" role="alert">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="15" y1="9" x2="9" y2="15"/>
            <line x1="9" y1="9" x2="15" y2="15"/>
        </svg>
        <?= e($error) ?>
    </div>
<?php endif; ?>

<!-- Form Login -->
<form class="auth-form" method="POST" action="" id="loginForm" novalidate>
    <?= csrfField() ?>

    <!-- Username -->
    <div class="form-group">
        <label class="form-label" for="username">
            Username atau Email
        </label>
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
                id="username"
                name="username"
                class="form-control <?= $error ? 'is-invalid' : '' ?>"
                placeholder="Masukkan username atau email"
                value="<?= e(post('username')) ?>"
                autocomplete="username"
                autofocus
                required>
        </div>
    </div>

    <!-- Password -->
    <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div class="password-field input-group">
            <span class="input-icon input-icon-left">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8" width="18" height="18"
                     stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </span>
            <input
                type="password"
                id="password"
                name="password"
                class="form-control <?= $error ? 'is-invalid' : '' ?>"
                placeholder="Masukkan password"
                autocomplete="current-password"
                required>
            <button type="button" class="password-toggle" aria-label="Tampilkan/sembunyikan password">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" width="18" height="18"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Remember me + Forgot password -->
    <div class="auth-form-options">
        <label class="form-check">
            <input type="checkbox" class="form-check-input" name="remember" id="remember"
                   <?= isset($_POST['remember']) ? 'checked' : '' ?>>
            <span class="form-check-label">Ingat saya</span>
        </label>
        <a href="<?= url('modules/auth/forgot_password.php') ?>" class="auth-forgot-link">
            Lupa password?
        </a>
    </div>

    <!-- Submit -->
    <button type="submit" class="auth-submit-btn" id="loginBtn">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" width="18" height="18"
             stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
            <polyline points="10 17 15 12 10 7"/>
            <line x1="15" y1="12" x2="3" y2="12"/>
        </svg>
        Masuk
    </button>

</form>

<!-- Footer -->
<div class="auth-footer-text">
    STUDEX &copy; <?= date('Y') ?> — Hanya untuk pengguna yang berwenang.
</div>

<script>
// Loading state saat submit
document.getElementById('loginForm').addEventListener('submit', function () {
    var btn = document.getElementById('loginBtn');
    btn.classList.add('loading');
    btn.disabled = true;
});
</script>

<?php
$content = ob_get_clean();
include ROOT_PATH . '/view/layouts/auth.php';
?>