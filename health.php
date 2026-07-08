<?php

declare(strict_types=1);

/**
 * Проверка готовности сервиса (БД). Для мониторинга: GET /health.php
 */
header('Content-Type: application/json; charset=utf-8');

$checks = [];
$ok = true;

try {
    if (!defined('DOC_ROOT')) {
        define('DOC_ROOT', __DIR__);
    }
    require_once DOC_ROOT . '/config/config.php';
    require_once DOC_ROOT . '/config/db.php';
    $pdo->query('SELECT 1');
    $checks['database'] = 'ok';
} catch (Throwable $e) {
    $ok = false;
    $checks['database'] = 'error';
}

$checks['php'] = PHP_VERSION;

http_response_code($ok ? 200 : 503);
echo json_encode([
    'status' => $ok ? 'ok' : 'degraded',
    'checked_at' => date('c'),
    'checks' => $checks,
], JSON_UNESCAPED_UNICODE);
