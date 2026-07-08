<?php
require_once __DIR__ . '/auth.php';
require_editor_tools();
$adminNavActive = 'activity';

$page = admin_page_param();
$perPage = admin_per_page_param(40);
$total = (int) $pdo->query('SELECT COUNT(*) FROM admin_activity_log')->fetchColumn();
$off = ($page - 1) * $perPage;

$rows = $pdo->query('SELECT * FROM admin_activity_log ORDER BY id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $off)->fetchAll(PDO::FETCH_ASSOC);
$pagerBase = ['per' => $perPage];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Журнал действий — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <main class="admin-main">
        <div class="page-title">
            <h1>Журнал действий</h1>
        </div>
        <p class="admin-lead admin-lead--tight">Последние операции в админке (создание, сохранение, удаление).</p>

        <?= admin_pager_html($page, $total, $perPage, $pagerBase, 'activity.php') ?>

        <div class="table-wrap">
            <table class="admin-table--compact">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Пользователь</th>
                        <th>Действие</th>
                        <th>Сущность</th>
                        <th>ID</th>
                        <th>Детали</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($r['created_at'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($r['login'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($r['action'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string) ($r['entity_type'] ?? '')) ?></td>
                        <td><?= $r['entity_id'] !== null && $r['entity_id'] !== '' ? (int) $r['entity_id'] : '—' ?></td>
                        <td><?php
                            $metaStr = (string) ($r['meta'] ?? '');
                            if (function_exists('mb_substr')) {
                                $metaShort = mb_substr($metaStr, 0, 120, 'UTF-8');
                                $metaLong = mb_strlen($metaStr, 'UTF-8') > 120;
                            } else {
                                $metaShort = substr($metaStr, 0, 120);
                                $metaLong = strlen($metaStr) > 120;
                            }
                            ?>
                            <span class="admin-meta-code"><?= htmlspecialchars($metaShort) ?><?= $metaLong ? '…' : '' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?>
                    <tr><td colspan="6">Записей пока нет.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
