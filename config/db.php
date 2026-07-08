<?php
/**
 * Подключение к БД. Параметры задаются в .env (см. .env.example).
 */
if (!defined('DOC_ROOT')) {
    require_once __DIR__ . '/config.php';
}

$driver = strtolower((string) env('DB_DRIVER', 'sqlite'));
$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    if ($driver === 'mysql') {
        $host = (string) env('DB_HOST', 'localhost');
        $port = (string) env('DB_PORT', '3306');
        $dbname = (string) env('DB_NAME', '');
        $user = (string) env('DB_USER', '');
        $password = (string) env('DB_PASSWORD', '');

        if ($dbname === '' || $user === '') {
            throw new RuntimeException('Укажите DB_NAME и DB_USER в файле .env');
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbname);
        $pdo = new PDO($dsn, $user, $password, $pdoOptions);
    } else {
        $dbFile = (string) env('DB_PATH', DOC_ROOT . '/data/site.db');
        if ($dbFile !== '' && $dbFile[0] !== '/' && !preg_match('/^[A-Za-z]:[\\\\\\/]/', $dbFile)) {
            $dbFile = DOC_ROOT . '/' . ltrim($dbFile, '/\\');
        }

        $dataDir = dirname($dbFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $pdo = new PDO('sqlite:' . $dbFile, null, null, $pdoOptions);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
} catch (Throwable $e) {
    die('Ошибка БД: ' . $e->getMessage());
}

require_once dirname(__DIR__) . '/include/cms-schema.php';
cms_ensure_schema($pdo);

require_once dirname(__DIR__) . '/include/error-log.php';
cms_register_error_logging();
