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

function course_overview_list_lines_to_text(array $rows): string
{
    $out = [];
    foreach ($rows as $r) {
        $t = trim((string) ($r['text'] ?? ''));
        if ($t !== '') $out[] = $t;
    }
    return implode("\n", $out);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'add_block') {
            $block_type = (string) ($_POST['block_type'] ?? 'text');
            if (!in_array($block_type, ['text', 'list', 'image'], true)) $block_type = 'text';
            $st = $pdo->prepare("INSERT INTO course_overview_blocks (course_id, sort_order, block_type, title, body, image) VALUES (?,?,?,?,?,?)");
            $st->execute([$course_id, 0, $block_type, '', '', '']);
            cms_log($pdo, 'create', 'course_overview_block', (int) $pdo->lastInsertId(), ['course_id' => $course_id, 'type' => $block_type]);
            header('Location: course-overview.php?course_id=' . $course_id);
            exit;
        }

        $deleteId = (int) ($_POST['delete_block_id'] ?? 0);
        if ($deleteId > 0) {
            $pdo->prepare('DELETE FROM course_overview_list_items WHERE block_id = ?')->execute([$deleteId]);
            $pdo->prepare('DELETE FROM course_overview_blocks WHERE id = ? AND course_id = ?')->execute([$deleteId, $course_id]);
            cms_log($pdo, 'delete', 'course_overview_block', $deleteId, ['course_id' => $course_id]);
            header('Location: course-overview.php?course_id=' . $course_id);
            exit;
        }

        if ($action === 'save_blocks') {
            $blocks = (array) ($_POST['blocks'] ?? []);
            foreach ($blocks as $idStr => $b) {
                $id = (int) $idStr;
                if ($id <= 0) continue;
                $title = trim((string) ($b['title'] ?? ''));
                $sort_order = (int) ($b['sort_order'] ?? 0);
                $block_type = (string) ($b['block_type'] ?? 'text');
                if (!in_array($block_type, ['text', 'list', 'image'], true)) $block_type = 'text';
                $body = trim((string) ($b['body'] ?? ''));
                $image = trim((string) ($b['image'] ?? ''));

                $pdo->prepare('UPDATE course_overview_blocks SET sort_order=?, block_type=?, title=?, body=?, image=? WHERE id=? AND course_id=?')
                    ->execute([$sort_order, $block_type, $title, $body, $image, $id, $course_id]);

                if ($block_type === 'list') {
                    $linesRaw = (string) ($b['list_items'] ?? '');
                    $lines = preg_split('/\R/u', $linesRaw) ?: [];
                    $pdo->prepare('DELETE FROM course_overview_list_items WHERE block_id = ?')->execute([$id]);
                    $ins = $pdo->prepare('INSERT INTO course_overview_list_items (block_id, sort_order, text) VALUES (?,?,?)');
                    $i = 0;
                    foreach ($lines as $line) {
                        $t = trim((string) $line);
                        if ($t === '') continue;
                        $ins->execute([$id, $i, $t]);
                        $i++;
                    }
                }
            }
            cms_log($pdo, 'update', 'course_overview', $course_id, ['blocks' => count($blocks)]);
            header('Location: course-overview.php?course_id=' . $course_id);
            exit;
        }
    } catch (Throwable $e) {
        $errors[] = 'Ошибка: ' . $e->getMessage();
    }
}

$st = $pdo->prepare('SELECT * FROM course_overview_blocks WHERE course_id = ? ORDER BY sort_order ASC, id ASC');
$st->execute([$course_id]);
$blocks = $st->fetchAll(PDO::FETCH_ASSOC);

$listItemsByBlock = [];
if ($blocks) {
    $ids = array_map(static fn($b) => (int) $b['id'], $blocks);
    $ids = array_values(array_filter($ids));
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT * FROM course_overview_list_items WHERE block_id IN ($in) ORDER BY block_id ASC, sort_order ASC, id ASC");
        $st->execute($ids);
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $bid = (int) ($r['block_id'] ?? 0);
            if (!isset($listItemsByBlock[$bid])) $listItemsByBlock[$bid] = [];
            $listItemsByBlock[$bid][] = $r;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overview курса — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<?php include __DIR__ . '/_header.php'; ?>
<main class="admin-main">
    <div class="page-title">
        <h1>Overview: <?= htmlspecialchars((string) ($course['title'] ?? '')) ?></h1>
        <a href="course-edit.php?id=<?= (int) $course_id ?>" class="btn btn-secondary">Назад к курсу</a>
    </div>

    <?php foreach ($errors as $e): ?><p class="admin-flash admin-flash--err"><?= htmlspecialchars($e) ?></p><?php endforeach; ?>

    <div class="admin-bulk-bar" style="justify-content:space-between;gap:12px;">
        <form method="post" style="display:flex;gap:8px;align-items:center;margin:0;">
            <input type="hidden" name="action" value="add_block">
            <label style="display:flex;gap:8px;align-items:center;margin:0;">
                <span style="font-size:0.9rem;opacity:0.9;">Добавить блок</span>
                <select name="block_type">
                    <option value="text">Текст</option>
                    <option value="list">Список</option>
                    <option value="image">Картинка</option>
                </select>
            </label>
            <button class="btn btn-primary" type="submit">Добавить</button>
        </form>
        <a class="btn btn-secondary" href="/course.php?slug=<?= urlencode((string) ($course['slug'] ?? '')) ?>" target="_blank" rel="noopener">Открыть на сайте</a>
    </div>

    <form method="post" class="js-admin-form-unsaved">
        <input type="hidden" name="action" value="save_blocks">

        <?php if (!$blocks): ?>
            <p class="admin-muted">Пока нет блоков Overview.</p>
        <?php endif; ?>

        <?php foreach ($blocks as $b): ?>
            <?php $bid = (int) ($b['id'] ?? 0); ?>
            <?php $type = (string) ($b['block_type'] ?? 'text'); ?>
            <section class="admin-card" style="padding:14px;margin:12px 0;border:1px solid rgba(255,255,255,.08);border-radius:12px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <strong>#<?= $bid ?></strong>
                        <label style="display:flex;align-items:center;gap:6px;margin:0;">
                            <span style="opacity:.8;">Порядок</span>
                            <input type="number" name="blocks[<?= $bid ?>][sort_order]" value="<?= (int) ($b['sort_order'] ?? 0) ?>" style="width:86px;">
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;margin:0;">
                            <span style="opacity:.8;">Тип</span>
                            <select name="blocks[<?= $bid ?>][block_type]">
                                <option value="text" <?= $type === 'text' ? 'selected' : '' ?>>Текст</option>
                                <option value="list" <?= $type === 'list' ? 'selected' : '' ?>>Список</option>
                                <option value="image" <?= $type === 'image' ? 'selected' : '' ?>>Картинка</option>
                            </select>
                        </label>
                    </div>
                    <button class="btn btn-danger" type="submit" name="delete_block_id" value="<?= $bid ?>" data-confirm="Удалить блок Overview?">Удалить</button>
                </div>

                <div class="form-group" style="margin-top:10px;">
                    <label>Заголовок</label>
                    <input type="text" name="blocks[<?= $bid ?>][title]" value="<?= htmlspecialchars((string) ($b['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                </div>

                <div class="form-group">
                    <label>Текст (для типа «Текст»)</label>
                    <textarea name="blocks[<?= $bid ?>][body]" rows="5"><?= htmlspecialchars((string) ($b['body'] ?? '')) ?></textarea>
                </div>

                <div class="form-group">
                    <label>Список (для типа «Список», по 1 пункту в строке)</label>
                    <textarea name="blocks[<?= $bid ?>][list_items]" rows="5"><?= htmlspecialchars(course_overview_list_lines_to_text($listItemsByBlock[$bid] ?? [])) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="cob-image-<?= $bid ?>">Картинка (для типа «Картинка»)</label>
                    <input id="cob-image-<?= $bid ?>" type="text" name="blocks[<?= $bid ?>][image]" value="<?= htmlspecialchars((string) ($b['image'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                    <?php $media_picker_input_id = 'cob-image-' . $bid; include __DIR__ . '/partials/media-picker-cms.php'; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Сохранить блоки</button>
            <a href="course-edit.php?id=<?= (int) $course_id ?>" class="btn btn-secondary">Отмена</a>
        </div>
    </form>
</main>
</body>
</html>

