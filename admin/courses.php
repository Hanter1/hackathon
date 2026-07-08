<?php
require_once __DIR__ . '/auth.php';
require_site_content_editor();
$adminNavActive = 'courses';
require_once DOC_ROOT . '/include/data.php';

$statusFilter = (string) ($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'active', 'hidden', 'draft'], true)) {
    $statusFilter = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'bulk_status') {
    $ids = array_values(array_filter(array_map('intval', $_POST['ids'] ?? [])));
    $newStatus = (string) ($_POST['bulk_status'] ?? '');
    if ($ids !== [] && in_array($newStatus, ['active', 'hidden', 'draft'], true)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE courses SET status = ? WHERE id IN ($in)")->execute(array_merge([$newStatus], $ids));
        cms_log($pdo, 'bulk_status', 'course', null, ['count' => count($ids), 'status' => $newStatus]);
    }
    header('Location: courses.php?' . http_build_query(array_filter(['status' => $statusFilter !== 'all' ? $statusFilter : null, 'per' => admin_per_page_param()])));
    exit;
}

if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $did = (int) $_GET['delete'];
    cms_log($pdo, 'delete', 'course', $did);
    $pdo->prepare('DELETE FROM courses WHERE id = ?')->execute([$did]);
    header('Location: courses.php?' . http_build_query(array_filter(['status' => $statusFilter !== 'all' ? $statusFilter : null])));
    exit;
}

$page = admin_page_param();
$perPage = admin_per_page_param();
$whereClause = '';
$params = [];
if ($statusFilter !== 'all') {
    $whereClause = ' WHERE c.status = ?';
    $params[] = $statusFilter;
}
$stc = $pdo->prepare('SELECT COUNT(*) FROM courses c' . $whereClause);
$stc->execute($params);
$total = (int) $stc->fetchColumn();
$off = ($page - 1) * $perPage;
$sql = 'SELECT c.*, t.name AS tname, t.surname AS tsurname FROM courses c LEFT JOIN teachers t ON c.teacher_id = t.id' . $whereClause . ' ORDER BY c.sort_order ASC, c.id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $off;
$stl = $pdo->prepare($sql);
$stl->execute($params);
$list = $stl->fetchAll(PDO::FETCH_ASSOC);
$pagerBase = array_filter(['status' => $statusFilter !== 'all' ? $statusFilter : null, 'per' => $perPage]);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Курсы — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <main class="admin-main">
        <div class="page-title">
            <h1>Курсы</h1>
            <a href="course-edit.php" class="btn btn-primary">Добавить</a>
        </div>

        <div class="admin-filter-tabs">
            <?php
            $tabs = ['all' => 'Все', 'active' => 'Активные', 'hidden' => 'Скрытые', 'draft' => 'Черновики'];
foreach ($tabs as $key => $label):
    $q = array_filter(['status' => $key !== 'all' ? $key : null]);
    $q['per'] = $perPage;
    $href = 'courses.php?' . http_build_query(array_filter($q));
    ?>
            <a href="<?= htmlspecialchars($href) ?>" class="admin-filter-tabs__item<?= $statusFilter === $key ? ' admin-filter-tabs__item--active' : '' ?>"><?= htmlspecialchars($label) ?></a>
            <?php endforeach; ?>
        </div>

        <?= admin_pager_html($page, $total, $perPage, $pagerBase, 'courses.php') ?>

        <form id="bulk-courses" method="post" class="admin-bulk-bar">
            <input type="hidden" name="action" value="bulk_status">
            <label class="admin-bulk-bar__label"><span class="admin-muted">Выбранные:</span>
                <select name="bulk_status" class="admin-inline-select">
                    <option value="">— статус —</option>
                    <option value="active">Активен</option>
                    <option value="hidden">Скрыт</option>
                    <option value="draft">Черновик</option>
                </select>
            </label>
            <button type="submit" class="btn btn-secondary btn-small" data-confirm="Изменить статус у выбранных курсов?">Применить</button>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="admin-th-check"><input type="checkbox" data-check-all-for="bulk-courses" aria-label="Выбрать все на странице"></th>
                        <th>ID</th>
                        <th>Обложка</th>
                        <th>Название</th>
                        <th>Категория</th>
                        <th>Цена</th>
                        <th>Статус</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list as $c): ?>
                    <tr>
                        <td class="admin-td-check"><input form="bulk-courses" class="js-bulk-id" type="checkbox" name="ids[]" value="<?= (int) $c['id'] ?>" aria-label="Выбрать <?= (int) $c['id'] ?>"></td>
                        <td><?= (int) $c['id'] ?></td>
                        <td><?php if (!empty($c['image'])): ?><img src="<?= htmlspecialchars($c['image']) ?>" alt="" class="thumb"><?php else: ?>—<?php endif; ?></td>
                        <td><?= htmlspecialchars($c['title']) ?></td>
                        <td><?= htmlspecialchars($c['category']) ?></td>
                        <td><?= htmlspecialchars($c['price']) ?></td>
                        <td><?= admin_status_badge_html((string) ($c['status'] ?? '')) ?></td>
                        <td class="admin-row-actions">
                            <a href="course-edit.php?id=<?= (int) $c['id'] ?>" class="btn btn-secondary btn-small">Изм.</a>
                            <?= admin_entity_row_extras(admin_content_public_path('course', (string) ($c['slug'] ?? '')), 'course-edit.php?duplicate_from=' . (int) $c['id']) ?>
                            <?php $delQ = array_filter(['delete' => (string) (int) $c['id'], 'status' => $statusFilter !== 'all' ? $statusFilter : null]); ?>
                            <a href="courses.php?<?= htmlspecialchars(http_build_query($delQ)) ?>" class="btn btn-secondary btn-small" data-confirm="Удалить курс без восстановления?">Удал.</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($list)): ?>
                    <tr><td colspan="8">Нет записей. <a href="course-edit.php">Добавить</a></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
