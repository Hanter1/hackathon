<?php
/**
 * Список диалогов Messenger и статус 24-часового окна.
 * Пока окно закрыто — менеджер может отправлять только шаблоны.
 */
require_once __DIR__ . '/auth.php';
require_login();
$adminNavActive = 'conversations';

require_once DOC_ROOT . '/include/messenger-window.php';

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$orderBy = $driver === 'mysql'
    ? "ORDER BY last_user_message_at IS NULL, last_user_message_at DESC, updated_at DESC"
    : "ORDER BY last_user_message_at IS NULL, last_user_message_at DESC, updated_at DESC";
$st = $pdo->query("
    SELECT id, psid, conversation_id, last_user_message_at, created_at, updated_at
    FROM messenger_conversations
    $orderBy
");
$conversations = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];

$windowHours = defined('MESSENGER_WINDOW_HOURS') ? MESSENGER_WINDOW_HOURS : 24;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Диалоги Messenger — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <main class="admin-main">
        <div class="page-title">
            <h1>Диалоги Messenger</h1>
        </div>
        <p class="admin-hint">
            Окно открыто = пользователь писал последним менее <?= (int) $windowHours ?> ч назад → можно писать любые сообщения.
            Закрыто = только шаблоны. Статус обновляется по webhook при входящих сообщениях.
        </p>
        <p class="admin-hint">
            <strong>API статуса:</strong> <code>GET /api/messenger-window.php?psid=PSID</code> → JSON с полем <code>open</code>.
            В интерфейсе чата при <code>open: false</code> блокируйте произвольный текст, разрешайте только шаблоны.
        </p>

        <?php if (empty($conversations)): ?>
            <p class="admin-empty">Пока нет диалогов. Они появятся после настройки webhook и первых входящих сообщений.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>PSID</th>
                            <th>Окно</th>
                            <th>Последнее сообщение пользователя</th>
                            <th>Окно закрывается</th>
                            <th>Создан</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conversations as $c): ?>
                            <?php
                            $info = messenger_get_window_info($c['psid']);
                            ?>
                            <tr>
                                <td><code class="admin-meta-code"><?= htmlspecialchars($c['psid']) ?></code></td>
                                <td>
                                    <?php if ($info['open']): ?>
                                        <span class="admin-badge admin-badge--open">Открыто</span>
                                    <?php else: ?>
                                        <span class="admin-badge admin-badge--closed">Закрыто</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $c['last_user_message_at'] ? htmlspecialchars($c['last_user_message_at']) : '—' ?></td>
                                <td><?= $info['closes_at'] ? htmlspecialchars($info['closes_at']) : '—' ?></td>
                                <td><?= htmlspecialchars($c['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
