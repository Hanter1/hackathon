<?php
require_once __DIR__ . '/auth.php';
require_site_content_editor();
$adminNavActive = 'teachers';
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
        $pdo->prepare("UPDATE teachers SET status = ? WHERE id IN ($in)")->execute(array_merge([$newStatus], $ids));
        cms_log($pdo, 'bulk_status', 'teacher', null, ['count' => count($ids), 'status' => $newStatus]);
    }
    $curPage = admin_page_param();
    $redir = array_filter([
        'status' => $statusFilter !== 'all' ? $statusFilter : null,
        'per' => admin_per_page_param(),
        'page' => $curPage > 1 ? $curPage : null,
    ]);
    header('Location: teachers.php?' . http_build_query($redir));
    exit;
}

if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    cms_log($pdo, 'delete', 'teacher', $id);
    $pdo->prepare('DELETE FROM teachers WHERE id = ?')->execute([$id]);
    header('Location: teachers.php?' . http_build_query(array_filter(['status' => $statusFilter !== 'all' ? $statusFilter : null])));
    exit;
}

$page = admin_page_param();
$perPage = admin_per_page_param();
$whereClause = '';
$params = [];
if ($statusFilter !== 'all') {
    $whereClause = ' WHERE status = ?';
    $params[] = $statusFilter;
}
$stc = $pdo->prepare('SELECT COUNT(*) FROM teachers' . $whereClause);
$stc->execute($params);
$total = (int) $stc->fetchColumn();
$off = ($page - 1) * $perPage;
$sql = 'SELECT * FROM teachers' . $whereClause . ' ORDER BY sort_order ASC, id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $off;
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
    <title>Наставники — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <main class="admin-main">
        <div class="page-title">
            <h1>Наставники</h1>
            <a href="teacher-edit.php" class="btn btn-primary">Добавить</a>
        </div>

        <div class="admin-filter-tabs">
            <?php
            $tabs = ['all' => 'Все', 'active' => 'Активные', 'hidden' => 'Скрытые', 'draft' => 'Черновики'];
foreach ($tabs as $key => $label):
    $q = array_filter(['status' => $key !== 'all' ? $key : null, 'page' => null]);
    $q['per'] = $perPage;
    $href = 'teachers.php?' . http_build_query(array_filter($q));
    ?>
            <a href="<?= htmlspecialchars($href) ?>" class="admin-filter-tabs__item<?= $statusFilter === $key ? ' admin-filter-tabs__item--active' : '' ?>"><?= htmlspecialchars($label) ?></a>
            <?php endforeach; ?>
        </div>

        <?= admin_pager_html($page, $total, $perPage, $pagerBase, 'teachers.php') ?>

        <form id="bulk-teachers" method="post" class="admin-bulk-bar">
            <input type="hidden" name="action" value="bulk_status">
            <label class="admin-bulk-bar__label"><span class="admin-muted">Выбранные:</span>
                <select name="bulk_status" class="admin-inline-select">
                    <option value="">— статус —</option>
                    <option value="active">Активен</option>
                    <option value="hidden">Скрыт</option>
                    <option value="draft">Черновик</option>
                </select>
            </label>
            <button type="submit" class="btn btn-secondary btn-small" data-confirm="Изменить статус у выбранных наставников?">Применить</button>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="admin-th-check"><input type="checkbox" data-check-all-for="bulk-teachers" aria-label="Выбрать все на странице"></th>
                        <th>ID</th>
                        <th>Фото</th>
                        <th>Имя</th>
                        <th>Роль</th>
                        <th>Slug</th>
                        <th>Статус</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list as $t): ?>
                    <tr>
                        <td class="admin-td-check"><input form="bulk-teachers" class="js-bulk-id" type="checkbox" name="ids[]" value="<?= (int) $t['id'] ?>" aria-label="Выбрать <?= (int) $t['id'] ?>"></td>
                        <td><?= (int) $t['id'] ?></td>
                        <td><?php if (!empty($t['image'])): ?><img src="<?= htmlspecialchars($t['image']) ?>" alt="" class="thumb"><?php else: ?>—<?php endif; ?></td>
                        <td><?= htmlspecialchars($t['name'] . ' ' . $t['surname']) ?></td>
                        <td><?= htmlspecialchars(mb_substr((string) ($t['role'] ?? ''), 0, 40)) ?></td>
                        <td><?= htmlspecialchars($t['slug']) ?></td>
                        <td><?= admin_status_badge_html((string) ($t['status'] ?? '')) ?></td>
                        <td class="admin-row-actions">
                            <a href="teacher-edit.php?id=<?= (int) $t['id'] ?>" class="btn btn-secondary btn-small">Изм.</a>
                            <?= admin_entity_row_extras(null, 'teacher-edit.php?duplicate_from=' . (int) $t['id']) ?>
                            <?php
                            $delQ = array_filter(['delete' => (string) (int) $t['id'], 'status' => $statusFilter !== 'all' ? $statusFilter : null, 'page' => $page > 1 ? $page : null]);
                            ?>
                            <a href="teachers.php?<?= htmlspecialchars(http_build_query($delQ)) ?>" class="btn btn-secondary btn-small" data-confirm="Удалить наставника без восстановления?">Удал.</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($list)): ?>
                    <tr><td colspan="8">Нет записей. <a href="teacher-edit.php">Добавить наставника</a></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>

</think>
<think>
I made a typo in teachers.php - `\n+` in the pager line and the delete URL might be wrong. Let me fix teachers.php
</think>


<｜tool▁calls▁begin｜><｜tool▁call▁begin｜>
StrReplace