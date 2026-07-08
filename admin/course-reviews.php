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

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'add_review') {
            $st = $pdo->prepare("INSERT INTO course_reviews (course_id, sort_order, author_name, author_avatar, rating, title, body, status) VALUES (?,?,?,?,?,?,?,?)");
            $st->execute([$course_id, 0, 'Student', '', '5.0', '', '', 'published']);
            cms_log($pdo, 'create', 'course_review', (int) $pdo->lastInsertId(), ['course_id' => $course_id]);
            header('Location: course-reviews.php?course_id=' . $course_id);
            exit;
        }

        $delId = (int) ($_POST['delete_review_id'] ?? 0);
        if ($delId > 0) {
            $pdo->prepare('DELETE FROM course_reviews WHERE id = ? AND course_id = ?')->execute([$delId, $course_id]);
            cms_log($pdo, 'delete', 'course_review', $delId, ['course_id' => $course_id]);
            header('Location: course-reviews.php?course_id=' . $course_id);
            exit;
        }

        if ($action === 'save_reviews') {
            $reviews = (array) ($_POST['reviews'] ?? []);
            foreach ($reviews as $idStr => $r) {
                $id = (int) $idStr;
                if ($id <= 0) continue;
                $sort_order = (int) ($r['sort_order'] ?? 0);
                $author_name = trim((string) ($r['author_name'] ?? ''));
                $author_avatar = trim((string) ($r['author_avatar'] ?? ''));
                $rating = (string) ($r['rating'] ?? '5.0');
                $ratingNum = (float) str_replace(',', '.', $rating);
                if ($ratingNum < 0) $ratingNum = 0.0;
                if ($ratingNum > 5) $ratingNum = 5.0;
                $title = trim((string) ($r['title'] ?? ''));
                $body = trim((string) ($r['body'] ?? ''));
                $status = (string) ($r['status'] ?? 'published');
                if (!in_array($status, ['published', 'hidden'], true)) $status = 'published';

                $pdo->prepare('UPDATE course_reviews SET sort_order=?, author_name=?, author_avatar=?, rating=?, title=?, body=?, status=? WHERE id=? AND course_id=?')
                    ->execute([$sort_order, $author_name, $author_avatar, number_format($ratingNum, 1, '.', ''), $title, $body, $status, $id, $course_id]);
            }
            cms_log($pdo, 'update', 'course_reviews', $course_id, ['reviews' => count($reviews)]);
            header('Location: course-reviews.php?course_id=' . $course_id);
            exit;
        }
    } catch (Throwable $e) {
        $errors[] = 'Ошибка: ' . $e->getMessage();
    }
}

$st = $pdo->prepare('SELECT * FROM course_reviews WHERE course_id = ? ORDER BY sort_order ASC, id ASC');
$st->execute([$course_id]);
$reviews = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews курса — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<?php include __DIR__ . '/_header.php'; ?>
<main class="admin-main">
    <div class="page-title">
        <h1>Reviews: <?= htmlspecialchars((string) ($course['title'] ?? '')) ?></h1>
        <a href="course-edit.php?id=<?= (int) $course_id ?>" class="btn btn-secondary">Назад к курсу</a>
    </div>

    <?php foreach ($errors as $e): ?><p class="admin-flash admin-flash--err"><?= htmlspecialchars($e) ?></p><?php endforeach; ?>

    <div class="admin-bulk-bar" style="justify-content:space-between;gap:12px;">
        <form method="post" style="display:flex;gap:8px;align-items:center;margin:0;">
            <input type="hidden" name="action" value="add_review">
            <button class="btn btn-primary" type="submit">Добавить отзыв</button>
        </form>
        <a class="btn btn-secondary" href="/course.php?slug=<?= urlencode((string) ($course['slug'] ?? '')) ?>" target="_blank" rel="noopener">Открыть на сайте</a>
    </div>

    <form method="post" class="js-admin-form-unsaved">
        <input type="hidden" name="action" value="save_reviews">

        <?php if (!$reviews): ?>
            <p class="admin-muted">Пока нет отзывов.</p>
        <?php endif; ?>

        <?php foreach ($reviews as $r): ?>
            <?php $rid = (int) ($r['id'] ?? 0); ?>
            <section class="admin-card" style="padding:14px;margin:12px 0;border:1px solid rgba(255,255,255,.08);border-radius:12px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <strong>#<?= $rid ?></strong>
                        <label style="display:flex;align-items:center;gap:6px;margin:0;">
                            <span style="opacity:.8;">Порядок</span>
                            <input type="number" name="reviews[<?= $rid ?>][sort_order]" value="<?= (int) ($r['sort_order'] ?? 0) ?>" style="width:86px;">
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;margin:0;">
                            <span style="opacity:.8;">Рейтинг</span>
                            <input type="text" name="reviews[<?= $rid ?>][rating]" value="<?= htmlspecialchars((string) ($r['rating'] ?? '5.0'), ENT_QUOTES, 'UTF-8') ?>" style="width:86px;">
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;margin:0;">
                            <span style="opacity:.8;">Статус</span>
                            <?php $status = (string) ($r['status'] ?? 'published'); ?>
                            <select name="reviews[<?= $rid ?>][status]">
                                <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Опубликован</option>
                                <option value="hidden" <?= $status === 'hidden' ? 'selected' : '' ?>>Скрыт</option>
                            </select>
                        </label>
                    </div>
                    <button class="btn btn-danger" type="submit" name="delete_review_id" value="<?= $rid ?>" data-confirm="Удалить отзыв?">Удалить</button>
                </div>

                <div class="form-group" style="margin-top:10px;">
                    <label>Имя автора</label>
                    <input type="text" name="reviews[<?= $rid ?>][author_name]" value="<?= htmlspecialchars((string) ($r['author_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="cr-avatar-<?= $rid ?>">Аватар (URL/путь)</label>
                    <input id="cr-avatar-<?= $rid ?>" type="text" name="reviews[<?= $rid ?>][author_avatar]" value="<?= htmlspecialchars((string) ($r['author_avatar'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                    <?php $media_picker_input_id = 'cr-avatar-' . $rid; include __DIR__ . '/partials/media-picker-cms.php'; ?>
                </div>

                <div class="form-group">
                    <label>Заголовок (опционально)</label>
                    <input type="text" name="reviews[<?= $rid ?>][title]" value="<?= htmlspecialchars((string) ($r['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                </div>

                <div class="form-group">
                    <label>Текст</label>
                    <textarea name="reviews[<?= $rid ?>][body]" rows="4"><?= htmlspecialchars((string) ($r['body'] ?? '')) ?></textarea>
                </div>
            </section>
        <?php endforeach; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Сохранить отзывы</button>
            <a href="course-edit.php?id=<?= (int) $course_id ?>" class="btn btn-secondary">Отмена</a>
        </div>
    </form>
</main>
</body>
</html>

