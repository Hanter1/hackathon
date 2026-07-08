<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
define('DOC_ROOT', $_SERVER['DOCUMENT_ROOT'] ?? __DIR__);
require_once DOC_ROOT . '/config/config.php';
require_once DOC_ROOT . '/config/db.php';
require_once DOC_ROOT . '/include/user-auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не поддерживается.']);
    exit;
}

$identifier = trim((string) ($_POST['identifier'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$register = (string) ($_POST['register'] ?? '') === '1';
$name = trim((string) ($_POST['name'] ?? ''));

if ($identifier === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Введите логин/email и пароль.']);
    exit;
}

ua_ensure_users_schema($pdo);
$user = ua_find_user_by_identifier($pdo, $identifier);

if ($user) {
    if (!password_verify($password, (string) $user['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Неверные данные для входа.']);
        exit;
    }
    ua_touch_last_login($pdo, (int) $user['id']);
    $user = ua_find_user_by_identifier($pdo, $identifier) ?? $user;
    ua_store_session_user($user);
    echo json_encode(['success' => true, 'redirect' => '/lk.php']);
    exit;
}

if (!$register) {
    echo json_encode(['success' => false, 'message' => 'Пользователь не найден. Включите режим регистрации.']);
    exit;
}

$email = $identifier;
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Для регистрации используйте корректный email в поле логина.']);
    exit;
}
if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Введите имя для регистрации.']);
    exit;
}

$newUser = ua_create_user($pdo, $name, $email, $password);
ua_touch_last_login($pdo, (int) $newUser['id']);
$stFresh = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stFresh->execute([(int) $newUser['id']]);
$newUser = $stFresh->fetch() ?: $newUser;
ua_store_session_user($newUser);
echo json_encode(['success' => true, 'redirect' => '/lk.php']);
