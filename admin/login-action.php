<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!defined('DOC_ROOT')) {
    define('DOC_ROOT', $_SERVER['DOCUMENT_ROOT'] ?? dirname(dirname(__DIR__)));
}
require_once DOC_ROOT . '/include/app-bootstrap.php';
require_once DOC_ROOT . '/include/user-auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не поддерживается.']);
    exit;
}

$login = trim((string) ($_POST['login'] ?? ''));
$pass = (string) ($_POST['password'] ?? '');

if ($login === '' || $pass === '') {
    echo json_encode(['success' => false, 'message' => 'Введите логин и пароль.']);
    exit;
}

ua_ensure_users_schema($pdo);
$user = ua_find_user_by_identifier($pdo, $login);

if ($user && password_verify($pass, (string) $user['password_hash']) && ua_can_access_admin($user)) {
    ua_touch_last_login($pdo, (int) $user['id']);
    $stFresh = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stFresh->execute([(int) $user['id']]);
    $user = $stFresh->fetch() ?: $user;
    ua_store_session_user($user);
    echo json_encode(['success' => true, 'redirect' => '/admin/']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Неверный логин/пароль или нет прав входа в админку.']);
