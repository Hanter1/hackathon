<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
define('DOC_ROOT', __DIR__);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/include/user-auth.php';

ua_ensure_users_schema($pdo);

$sessionUser = $_SESSION['user'] ?? null;
if (!is_array($sessionUser) || empty($sessionUser['id'])) {
    header('Location: /lk.php');
    exit;
}

$st = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$st->execute([(int) $sessionUser['id']]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    header('Location: /lk.php');
    exit;
}

unset($row['password_hash'], $row['password']);
$row['_export'] = [
    'generated_at' => date('c'),
    'site' => 'Easy People',
    'note' => 'Копия персональных данных из учётной записи (без пароля).',
];

$filename = 'easy-people-user-' . (int) $sessionUser['id'] . '-' . date('Y-m-d') . '.json';
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
