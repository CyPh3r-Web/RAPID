<?php
/**
 * PDO database connection
 */

declare(strict_types=1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'rapid');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            throw $e;
        }
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Unable to connect to the database. Please try again later.');
    }

    return $pdo;
}
