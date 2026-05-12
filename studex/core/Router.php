<?php
// ============================================================
//  STUDEX — Student Index
//  core/Router.php — Simple PHP Router
//  Dipakai untuk API endpoints & redirect helper
//  Untuk halaman biasa, routing via direktori PHP sudah cukup
// ============================================================

defined('STUDEX') or die('Direct access not permitted');

class Router {

    private static array $routes     = [];
    private static array $middleware = [];

    // ============================================================
    // REGISTER ROUTES
    // ============================================================
    public static function get(string $path, callable $handler): void {
        self::$routes['GET'][$path] = $handler;
    }

    public static function post(string $path, callable $handler): void {
        self::$routes['POST'][$path] = $handler;
    }

    public static function any(string $path, callable $handler): void {
        self::$routes['GET'][$path]  = $handler;
        self::$routes['POST'][$path] = $handler;
    }

    // ============================================================
    // MIDDLEWARE
    // ============================================================
    public static function middleware(string $name, callable $fn): void {
        self::$middleware[$name] = $fn;
    }

    // ============================================================
    // DISPATCH — cocokkan request ke route
    // ============================================================
    public static function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');

        // Hapus BASE path dari URI
        $basePath = parse_url(BASE_URL, PHP_URL_PATH) ?: '';
        if ($basePath && strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }
        $uri = '/' . ltrim($uri, '/');

        // Cari exact match
        if (isset(self::$routes[$method][$uri])) {
            self::run(self::$routes[$method][$uri], []);
            return;
        }

        // Cari wildcard / parameter match
        foreach (self::$routes[$method] ?? [] as $pattern => $handler) {
            $params = [];
            if (self::matchPattern($pattern, $uri, $params)) {
                self::run($handler, $params);
                return;
            }
        }

        // 404
        self::notFound();
    }

    // ============================================================
    // MATCH PATTERN — support {param}
    // ============================================================
    private static function matchPattern(string $pattern, string $uri, array &$params): bool {
        $regex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            foreach ($matches as $key => $val) {
                if (is_string($key)) $params[$key] = $val;
            }
            return true;
        }
        return false;
    }

    // ============================================================
    // RUN HANDLER
    // ============================================================
    private static function run(callable $handler, array $params): void {
        try {
            call_user_func($handler, $params);
        } catch (\Exception $e) {
            error_log('STUDEX Router Error: ' . $e->getMessage());
            self::serverError($e->getMessage());
        }
    }

    // ============================================================
    // ERROR RESPONSES
    // ============================================================
    private static function notFound(): void {
        http_response_code(404);
        if (self::isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Endpoint tidak ditemukan.']);
        } else {
            echo '<!DOCTYPE html><html><head><title>404 — STUDEX</title></head><body>';
            echo '<h2>404 — Halaman tidak ditemukan</h2>';
            echo '<a href="' . BASE_URL . '">Kembali ke Dashboard</a></body></html>';
        }
        exit;
    }

    private static function serverError(string $msg = ''): void {
        http_response_code(500);
        if (self::isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Server error: ' . $msg]);
        } else {
            echo '<!DOCTYPE html><html><head><title>500 — STUDEX</title></head><body>';
            echo '<h2>500 — Server Error</h2>';
            if (defined('APP_DEBUG') && APP_DEBUG) echo '<p>' . htmlspecialchars($msg) . '</p>';
            echo '<a href="' . BASE_URL . '">Kembali ke Dashboard</a></body></html>';
        }
        exit;
    }

    // ============================================================
    // HELPERS
    // ============================================================
    public static function isAjax(): bool {
        return (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) || (
            isset($_SERVER['HTTP_ACCEPT']) &&
            strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
        );
    }

    public static function isPost(): bool {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    }

    public static function isGet(): bool {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET';
    }

    /**
     * Ambil input POST/GET dengan sanitasi
     */
    public static function input(string $key, mixed $default = ''): mixed {
        $val = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($val) ? trim(strip_tags($val)) : $val;
    }

    /**
     * JSON response helper
     */
    public static function json(array $data, int $code = 200): never {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function jsonSuccess(mixed $data = null, string $message = 'Berhasil'): never {
        self::json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    public static function jsonError(string $message = 'Terjadi kesalahan', int $code = 400): never {
        self::json(['success' => false, 'message' => $message], $code);
    }

    /**
     * Redirect
     */
    public static function redirect(string $url): never {
        header('Location: ' . $url);
        exit;
    }

    public static function back(): never {
        $ref = $_SERVER['HTTP_REFERER'] ?? BASE_URL;
        self::redirect($ref);
    }

    /**
     * Validasi request method — throw 405 kalau tidak sesuai
     */
    public static function requireMethod(string ...$methods): void {
        $current = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array(strtoupper($current), array_map('strtoupper', $methods))) {
            http_response_code(405);
            if (self::isAjax()) {
                self::jsonError('Method not allowed.', 405);
            }
            die('405 Method Not Allowed');
        }
    }

    /**
     * Validasi CSRF + method POST sekaligus
     */
    public static function requirePost(): void {
        self::requireMethod('POST');
        verifyCsrf();
    }
}