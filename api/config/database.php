<?php
// ============================================================
// KONEKT — Database Configuration
// ============================================================
// PDO connection to MySQL via Laragon
// ============================================================

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'konekt_db');
define('DB_USER', 'root');
define('DB_PASS', '');           // Laragon default: no password
define('DB_CHARSET', 'utf8mb4');

/**
 * Get a PDO database connection instance.
 *
 * Uses a static variable to reuse the connection within a single
 * request (singleton pattern per request).
 *
 * @return PDO
 * @throws PDOException if connection fails
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    return $pdo;
}
