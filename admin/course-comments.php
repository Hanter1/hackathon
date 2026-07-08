<?php
require_once __DIR__ . '/auth.php';
require_site_content_editor();
$adminNavActive = 'courses';
require_once DOC_ROOT . '/include/data.php';

$course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
if ($course_id <= 0) {
    header('Location: courses.php');
    exit;
}

$st = $pdo->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
$st->execute([$course_id]);
$course = $st->fetch(PDO::FETCH_ASSOC) ?: null;
if (!$course) {
    header('Location: courses.php');
    exit;
}

$status = (string) ($_GET['status'] ?? 'pending');
if (!in_array($status, ['pending', 'published', 'hidden', 'all'], true)) $status = 'pending';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            if ($action === 'publish') {
                $pdo->prepare("UPDATE course_comments SET status='published' WHERE id=? AND course_id=?")->execute([$id, $course_id]);
                cms_log($pdo, 'update', 'course_comment', $id, ['course_id' => $course_id, 'status' => 'published']);
            } elseif ($action === 'hide') {
                $pdo->prepare("UPDATE course_comments SET status='hidden' WHERE id=? AND course_id=?")->execute([$id, $course_id]);
                cms_log($pdo, 'update', 'course_comment', $id, ['course_id' => $course_id, 'status' => 'hidden']);
            } elseif ($action === 'delete') {
                $pdo->prepare("DELETE FROM course_comments WHERE id=? AND course_id=?")->execute([$id, $course_id]);
                cms_log($pdo, 'delete', 'course_comment', $id, ['course_id' => $course_id]);
            }
        } catch (Throwable $e) {
            $errors[] = 'Ошибка: ' . $e->getMessage();
        }
    }
    header('Location: course-comments.php?course_id=' . $course_id . '&status=' . rawurlencode($status));
    exit;
}

$where = 'course_id = ?';
$params = [$course_id];
if ($status !== 'all') {
    $where .= ' AND status = ?';
    $params[] = $status;
}
$st = $pdo->prepare("SELECT * FROM course_comments WHERE $where ORDER BY created_at DESC, id DESC LIMIT 500");
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

function cc_h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Комментарии курса — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<?php include __DIR__ . '/_header.php'; ?>
<main class="admin-main">
    <div class="page-title">
        <h1>Комментарии: <?= cc_h((string) ($course['title'] ?? '')) ?></h1>
        <a href="course-edit.php?id=<?= (int) $course_id ?>" class="btn btn-secondary">Назад к курсу</a>
    </div>

    <?php foreach ($errors as $e): ?><p class="admin-flash admin-flash--err"><?= cc_h($e) ?></p><?php endforeach; ?>

    <div class="admin-bulk-bar" style="justify-content:space-between;gap:12px;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a class="btn btn-secondary" href="course-comments.php?course_id=<?= (int) $course_id ?>&status=pending">На модерации</a>
            <a class="btn btn-secondary" href="course-comments.php?course_id=<?= (int) $course_id ?>&status=published">Опубликованные</a>
            <a class="btn btn-secondary" href="course-comments.php?course_id=<?= (int) $course_id ?>&status=hidden">Скрытые</a>
            <a class="btn btn-secondary" href="course-comments.php?course_id=<?= (int) $course_id ?>&status=all">Все</a>
        </div>
        <a class="btn btn-secondary" href="/course.php?slug=<?= urlencode((string) ($course['slug'] ?? '')) ?>#comment" target="_blank" rel="noopener">Открыть на сайте</a>
    </div>

    <?php if (!$rows): ?>
        <p class="admin-muted">Комментариев нет.</p>
    <?php endif; ?>

    <?php foreach ($rows as $r): ?>
        <?php $id = (int) ($r['id'] ?? 0); ?>
        <section class="admin-card" style="padding:14px;margin:12px 0;border:1px solid rgba(255,255,255,.08);border-radius:12px;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <strong>#<?= $id ?></strong>
                    <span style="opacity:.8;"><?= cc_h((string) ($r['status'] ?? '')) ?></span>
                    <span style="opacity:.8;"><?= cc_h((string) ($r['created_at'] ?? '')) ?></span>
                    <span><?= cc_h((string) ($r['author_name'] ?? '')) ?></span>
                    <?php if (!empty($r['author_email'])): ?><span style="opacity:.85;"><?= cc_h((string) $r['author_email']) ?></span><?php endif; ?>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="action" value="publish">
                        <button class="btn btn-primary" type="submit">Опубликовать</button>
                    </form>
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="action" value="hide">
                        <button class="btn btn-secondary" type="submit">Скрыть</button>
                    </form>
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="btn btn-danger" type="submit" data-confirm="Удалить комментарий?">Удалить</button>
                    </form>
                </div>
            </div>
            <div style="margin-top:10px;white-space:pre-wrap;line-height:1.45;"><?= cc_h((string) ($r['body'] ?? '')) ?></div>
        </section>
    <?php endforeach; ?>
</main>
</body>
</html>

