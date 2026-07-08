<?php
session_start();
require_once __DIR__ . '/_assets.php';
if (!defined('DOC_ROOT')) {
    define('DOC_ROOT', $_SERVER['DOCUMENT_ROOT'] ?? dirname(dirname(__DIR__)));
}
require_once DOC_ROOT . '/include/app-bootstrap.php';
require_once DOC_ROOT . '/include/user-auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $pass = $_POST['password'] ?? '';
    if ($login && $pass) {
        ua_ensure_users_schema($pdo);
        $user = ua_find_user_by_identifier($pdo, $login);
        if ($user && password_verify($pass, $user['password_hash']) && ua_can_access_admin($user)) {
            ua_touch_last_login($pdo, (int) $user['id']);
            $stFresh = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $stFresh->execute([(int) $user['id']]);
            $user = $stFresh->fetch() ?: $user;
            ua_store_session_user($user);
            header('Location: index.php');
            exit;
        }
    }
    $error = 'Неверный логин/пароль или нет прав входа в админку.';
}

$sessionCheck = $_SESSION['user'] ?? null;
if (is_array($sessionCheck) && !empty($sessionCheck['id']) && ua_can_access_admin($sessionCheck)) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="admin-login-page">
    <div class="admin-login">
        <h1 class="admin-login__title">Вход в админку</h1>
        <p class="admin-login__lead">Администратор, редактор или модератор (заявки и Messenger). Обычные пользователи без роли входа не пускаются.</p>
        <?php if ($error): ?>
        <p class="admin-flash admin-flash--err"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="post">
            <label for="admin-login-user">Логин</label>
            <input id="admin-login-user" type="text" name="login" required autofocus autocomplete="username">
            <label for="admin-login-pass">Пароль</label>
            <input id="admin-login-pass" type="password" name="password" required autocomplete="current-password">
            <button type="submit">Войти</button>
        </form>
    </div>
</body>
</html>
