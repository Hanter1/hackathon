<?php
require_once __DIR__ . '/auth.php';
require_full_admin();
$adminNavActive = 'security';

$page = admin_page_param();
$perPage = admin_per_page_param(50);
$qIp = trim((string) ($_GET['ip'] ?? ''));
$qPath = trim((string) ($_GET['path'] ?? ''));
$qMethod = strtoupper(trim((string) ($_GET['method'] ?? '')));
$qArea = trim((string) ($_GET['area'] ?? 'all'));
if (!in_array($qArea, ['all', 'site', 'admin'], true)) {
    $qArea = 'all';
}

$where = [];
$params = [];
if ($qIp !== '') {
    $where[] = 'ip LIKE ?';
    $params[] = '%' . $qIp . '%';
}
if ($qPath !== '') {
    $where[] = 'uri LIKE ?';
    $params[] = '%' . $qPath . '%';
}
if ($qMethod !== '') {
    $where[] = 'method = ?';
    $params[] = $qMethod;
}
if ($qArea === 'site') {
    $where[] = 'is_admin_area = 0';
} elseif ($qArea === 'admin') {
    $where[] = 'is_admin_area = 1';
}
$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$stc = $pdo->prepare('SELECT COUNT(*) FROM security_access_log' . $whereSql);
$stc->execute($params);
$total = (int) $stc->fetchColumn();
$off = ($page - 1) * $perPage;

$st = $pdo->prepare('SELECT * FROM security_access_log' . $whereSql . ' ORDER BY id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $off);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$kpiAll = (int) $pdo->query('SELECT COUNT(*) FROM security_access_log')->fetchColumn();
$kpi24hSt = $pdo->prepare('SELECT COUNT(*) FROM security_access_log WHERE created_at >= ?');
$kpi24hSt->execute([date('Y-m-d H:i:s', time() - 86400)]);
$kpi24h = (int) $kpi24hSt->fetchColumn();
$kpiAdmin = (int) $pdo->query('SELECT COUNT(*) FROM security_access_log WHERE is_admin_area = 1')->fetchColumn();

$topIps = $pdo->query('SELECT ip, COUNT(*) AS c FROM security_access_log GROUP BY ip ORDER BY c DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
$topPaths = $pdo->query('SELECT uri, COUNT(*) AS c FROM security_access_log GROUP BY uri ORDER BY c DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
$topUa = $pdo->query('SELECT user_agent, COUNT(*) AS c FROM security_access_log GROUP BY user_agent ORDER BY c DESC LIMIT 8')->fetchAll(PDO::FETCH_ASSOC);

$pagerBase = array_filter([
    'ip' => $qIp !== '' ? $qIp : null,
    'path' => $qPath !== '' ? $qPath : null,
    'method' => $qMethod !== '' ? $qMethod : null,
    'area' => $qArea !== 'all' ? $qArea : null,
    'per' => $perPage,
]);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Безопасность — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <main class="admin-main">
        <div class="page-title">
            <h1>Безопасность</h1>
        </div>
        <p class="admin-lead admin-lead--tight">Лог всех PHP-запросов: IP, путь, user agent, referer, visitor/user, код ответа и время ответа.</p>

        <div class="admin-analytics-kpi-grid">
            <div class="admin-analytics-kpi">
                <span class="admin-analytics-kpi__value"><?= (int) $kpiAll ?></span>
                <span class="admin-analytics-kpi__label">Всего записей</span>
            </div>
            <div class="admin-analytics-kpi">
                <span class="admin-analytics-kpi__value"><?= (int) $kpi24h ?></span>
                <span class="admin-analytics-kpi__label">За последние 24 часа</span>
            </div>
            <div class="admin-analytics-kpi">
                <span class="admin-analytics-kpi__value"><?= (int) $kpiAdmin ?></span>
                <span class="admin-analytics-kpi__label">Запросов в админку</span>
            </div>
        </div>

        <section class="admin-section">
            <h2 class="admin-section__title">Фильтры</h2>
            <form method="get" class="admin-inline-form">
                <input type="text" name="ip" value="<?= htmlspecialchars($qIp) ?>" placeholder="IP (напр. 192.168)">
                <input type="text" name="path" value="<?= htmlspecialchars($qPath) ?>" placeholder="URI содержит (напр. /admin)">
                <input type="text" name="method" value="<?= htmlspecialchars($qMethod) ?>" placeholder="GET/POST" maxlength="12">
                <select name="area" class="admin-inline-select">
                    <option value="all" <?= $qArea === 'all' ? 'selected' : '' ?>>Везде</option>
                    <option value="site" <?= $qArea === 'site' ? 'selected' : '' ?>>Только сайт</option>
                    <option value="admin" <?= $qArea === 'admin' ? 'selected' : '' ?>>Только админка</option>
                </select>
                <button type="submit" class="btn btn-secondary btn-small">Применить</button>
                <a href="security.php" class="btn btn-secondary btn-small">Сброс</a>
            </form>
        </section>

        <?= admin_pager_html($page, $total, $perPage, $pagerBase, 'security.php') ?>

        <div class="table-wrap">
            <table class="admin-table--compact">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>IP</th>
                        <th>Метод</th>
                        <th>Путь</th>
                        <th>Статус</th>
                        <th>ms</th>
                        <th>Кто</th>
                        <th>UA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($r['created_at'] ?? '')) ?></td>
                        <td><code><?= htmlspecialchars((string) ($r['ip'] ?? '')) ?></code></td>
                        <td><?= htmlspecialchars((string) ($r['method'] ?? '')) ?></td>
                        <td>
                            <span title="<?= htmlspecialchars((string) ($r['uri'] ?? '')) ?>">
                                <?= htmlspecialchars((string) mb_strimwidth((string) ($r['uri'] ?? ''), 0, 64, '…')) ?>
                            </span>
                        </td>
                        <td><?= (int) ($r['status_code'] ?? 0) ?></td>
                        <td><?= (int) ($r['response_ms'] ?? 0) ?></td>
                        <td>
                            <?= htmlspecialchars((string) ($r['user_login'] ?? '')) ?>
                            <?php if (!empty($r['user_role'])): ?>
                                <span class="admin-muted">(<?= htmlspecialchars((string) $r['user_role']) ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td title="<?= htmlspecialchars((string) ($r['user_agent'] ?? '')) ?>">
                            <?= htmlspecialchars((string) mb_strimwidth((string) ($r['user_agent'] ?? ''), 0, 60, '…')) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?>
                    <tr><td colspan="8">Пока нет данных.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <section class="admin-section">
            <h2 class="admin-section__title">Топы</h2>
            <div class="admin-analytics-charts admin-analytics-charts--dense">
                <div class="admin-chart-card">
                    <h3 class="admin-chart-card__title">Топ IP</h3>
                    <ul class="admin-recent-list">
                        <?php foreach ($topIps as $row): ?>
                        <li><span><?= htmlspecialchars((string) ($row['ip'] ?? '')) ?></span><span class="admin-recent-list__meta"><?= (int) ($row['c'] ?? 0) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="admin-chart-card">
                    <h3 class="admin-chart-card__title">Топ URI</h3>
                    <ul class="admin-recent-list">
                        <?php foreach ($topPaths as $row): ?>
                        <li><span title="<?= htmlspecialchars((string) ($row['uri'] ?? '')) ?>"><?= htmlspecialchars((string) mb_strimwidth((string) ($row['uri'] ?? ''), 0, 54, '…')) ?></span><span class="admin-recent-list__meta"><?= (int) ($row['c'] ?? 0) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="admin-chart-card">
                    <h3 class="admin-chart-card__title">Топ User-Agent</h3>
                    <ul class="admin-recent-list">
                        <?php foreach ($topUa as $row): ?>
                        <li><span title="<?= htmlspecialchars((string) ($row['user_agent'] ?? '')) ?>"><?= htmlspecialchars((string) mb_strimwidth((string) ($row['user_agent'] ?? ''), 0, 54, '…')) ?></span><span class="admin-recent-list__meta"><?= (int) ($row['c'] ?? 0) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </section>
    </main>
    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>

