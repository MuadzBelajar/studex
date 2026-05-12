<?php
// ============================================================
//  STUDEX — Student Index
//  core/Auth.php — Class Autentikasi
// ============================================================

defined('STUDEX') or die('Direct access not permitted');

class Auth {

    /**
     * Proses login user
     * @return array ['success' => bool, 'message' => string]
     */
    public static function login(string $username, string $password): array {
        $db = db();

        $stmt = $db->prepare("
            SELECT id, nama, username, email, password, role, avatar, is_active
            FROM users
            WHERE (username = ? OR email = ?)
            LIMIT 1
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'Username atau password salah.'];
        }

        if (!$user['is_active']) {
            return ['success' => false, 'message' => 'Akun Anda dinonaktifkan. Hubungi Super Admin.'];
        }

        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Username atau password salah.'];
        }

        // Set session
        self::setSession($user);

        // Update last login
        $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
           ->execute([$user['id']]);

        return ['success' => true, 'message' => 'Login berhasil. Selamat datang, ' . $user['nama'] . '!'];
    }

    /**
     * Set data session setelah login berhasil
     */
    private static function setSession(array $user): void {
        session_regenerate_id(true); // Cegah session fixation
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['user_nama']     = $user['nama'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_email']    = $user['email'];
        $_SESSION['user_role']     = $user['role'];
        $_SESSION['user_avatar']   = $user['avatar'];
        $_SESSION['login_time']    = time();
    }

    /**
     * Logout — hancurkan session
     */
    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Update password user
     */
    public static function updatePassword(int $userId, string $newPassword): bool {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = db()->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$hash, $userId]);
    }

    /**
     * Hash password baru
     */
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}