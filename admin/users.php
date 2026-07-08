<?php
require_once __DIR__ . '/auth.php';
require_full_admin();
$adminNavActive = 'users';

ua_ensure_users_schema($pdo);

$message = '';
$error = '';
$allowedRoles = ['user', 'editor', 'moderator', 'admin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'set_role') {
    $uid = (int) ($_POST['user_id'] ?? 0);
    $role = trim((string) ($_POST['role'] ?? ''));
    if ($uid <= 0) {
        $error = 'Некорректный пользователь.';
    } elseif ($uid === (int) ($_SESSION['user']['id'] ?? 0)) {
        $error = 'Нельзя изменить свою собственную роль в этом интерфейсе.';
    } elseif (!in_array($role, $allowedRoles, true)) {
        $error = 'Недопустимая роль.';
    } else {
        $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $uid]);
        cms_log($pdo, 'user_role', 'user', $uid, ['role' => $role]);
        $message = 'Роль сохранена.';
    }
}

$users = $pdo->query('SELECT id, login, name, email, role, created_at FROM users ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Пользователи CMS — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <main class="admin-main">
        <div class="page-title">
            <h1>Пользователи и роли</h1>
        </div>
        <p class="admin-lead">
            <strong>Редактор (editor)</strong> — весь контент сайта, медиатека и журнал.
            <strong>Модератор (moderator)</strong> — только заявки «Записаться», Messenger и поиск (без правок страниц и постов).
            <strong>Администратор (admin)</strong> — всё, включая пользователей и служебный раздел.
        </p>

        <?php if ($message): ?><p class="admin-flash admin-flash--ok"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="admin-flash admin-flash--err"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Логин</th>
                        <th>Имя</th>
                        <th>Email</th>
                        <th>Роль</th>
                        <th>Создан</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= (int) $u['id'] ?></td>
                        <td><?= htmlspecialchars((string) ($u['login'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($u['name'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($u['email'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($u['role'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($u['created_at'] ?? '')) ?></td>
                        <td>
                            <?php if ((int) $u['id'] !== (int) ($_SESSION['user']['id'] ?? 0)): ?>
                            <form method="post" class="admin-inline-form">
                                <input type="hidden" name="action" value="set_role">
                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                <select name="role" class="admin-inline-select">
                                    <?php foreach ($allowedRoles as $r): ?>
                                    <option value="<?= htmlspecialchars($r) ?>" <?= (($u['role'] ?? '') === $r) ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-secondary btn-small">OK</button>
                            </form>
                            <?php else: ?>
                            <span class="admin-empty">это вы</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
