<?php
/**
 * Подключение к БД (SQLite). Для MySQL раскомментировать блок внизу.
 */
if (!defined('DOC_ROOT')) {
    require_once __DIR__ . '/config.php';
}
/*
$dbFile = DOC_ROOT . '/data/site.db';
$dataDir = dirname($dbFile);
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

try {
    $pdo = new PDO(
        'sqlite:' . $dbFile,
        null,
        null,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    $pdo->exec('PRAGMA foreign_keys = ON');
} catch (PDOException $e) {
    die('Ошибка БД: ' . $e->getMessage());
}
*/

// MySQL (раскомментировать и указать свои данные):
$pdo = new PDO(
    'mysql:host=localhost;dbname=epeopleb_site;charset=utf8mb4',
    'epeopleb_user',
    'TYh@0qa09XYk!5',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

require_once dirname(__DIR__) . '/include/cms-schema.php';
cms_ensure_schema($pdo);

require_once dirname(__DIR__) . '/include/error-log.php';
cms_register_error_logging();

