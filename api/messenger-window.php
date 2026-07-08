<?php
/**
 * API: статус 24-часового окна для диалога.
 * GET ?psid=... → JSON { open, last_user_message_at, closes_at, window_hours }
 * Используется интерфейсом менеджера, чтобы блокировать ввод при закрытом окне.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/include/messenger-window.php';

$psid = isset($_GET['psid']) ? trim((string) $_GET['psid']) : '';
if ($psid === '') {
    http_response_code(400);
    echo json_encode(['error' => 'psid required']);
    exit;
}

$info = messenger_get_window_info($psid);
echo json_encode([
    'open' => $info['open'],
    'last_user_message_at' => $info['last_user_message_at'],
    'closes_at' => $info['closes_at'],
    'window_hours' => $info['window_hours'],
]);
