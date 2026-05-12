<?php
// ============================================================
//  STUDEX — Student Index
//  config/database.php — Koneksi MySQL via PDO
// ============================================================

defined('STUDEX') or die('Direct access not permitted');

class Database {
    private static ?PDO $instance = null;

    // --- Konfigurasi DB ---
    private static string $host    = 'localhost';
    private static string $dbname  = 'studex';
    private static string $user    = 'root';
    private static string $pass    = '';           // Kosong untuk XAMPP default
    private static string $charset = 'utf8mb4';

    /**
     * Singleton: ambil satu instance PDO yang sama di seluruh aplikasi
     */
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                self::$host,
                self::$dbname,
                self::$charset
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];

            try {
                self::$instance = new PDO($dsn, self::$user, self::$pass, $options);
            } catch (PDOException $e) {
                // Di production, jangan tampilkan pesan error detail
                error_log('DB Connection Error: ' . $e->getMessage());
                die(json_encode([
                    'success' => false,
                    'message' => 'Koneksi database gagal. Hubungi administrator.'
                ]));
            }
        }

        return self::$instance;
    }

    // Prevent instantiation
    private function __construct() {}
    private function __clone() {}
}

/**
 * Helper function — shortcut global untuk mendapatkan PDO instance
 * Penggunaan: $db = db();
 */
function db(): PDO {
    return Database::getInstance();
}