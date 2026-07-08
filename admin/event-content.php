<?php
require_once __DIR__ . '/auth.php';
require_site_content_editor();
$adminNavActive = 'events';
require_once DOC_ROOT . '/include/data.php';

$event_id = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
if ($event_id <= 0) {
    header('Location: events.php');
    exit;
}

$st = $pdo->prepare('SELECT * FROM events WHERE id = ? LIMIT 1');
$st->execute([$event_id]);
$event = $st->fetch(PDO::FETCH_ASSOC) ?: null;
if (!$event) {
    header('Location: events.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'add_block') {
            $block_type = (string) ($_POST['block_type'] ?? 'about');
            if (!in_array($block_type, ['about', 'gallery', 'map'], true)) {
                $block_type = 'about';
            }
            $pdo->prepare('INSERT INTO event_content_blocks (event_id, sort_order, block_type, title, body, images, map_embed_url) VALUES (?,?,?,?,?,?,?)')
                ->execute([$event_id, 0, $block_type, '', '', '', '']);
            cms_log($pdo, 'create', 'event_content_block', (int) $pdo->lastInsertId(), ['event_id' => $event_id, 'type' => $block_type]);
            header('Location: event-content.php?event_id=' . $event_id);
            exit;
        }

        $deleteId = (int) ($_POST['delete_block_id'] ?? 0);
        if ($deleteId > 0) {
            $pdo->prepare('DELETE FROM event_content_blocks WHERE id = ? AND event_id = ?')->execute([$deleteId, $event_id]);
            cms_log($pdo, 'delete', 'event_content_block', $deleteId, ['event_id' => $event_id]);
            header('Location: event-content.php?event_id=' . $event_id);
            exit;
        }

        if ($action === 'save_blocks') {
            $blocks = (array) ($_POST['blocks'] ?? []);
            foreach ($blocks as $idStr => $b) {
                $id = (int) $idStr;
                if ($id <= 0) {
                    continue;
                }
                $title = trim((string) ($b['title'] ?? ''));
                $sort_order = (int) ($b['sort_order'] ?? 0);
                $block_type = (string) ($b['block_type'] ?? 'about');
                if (!in_array($block_type, ['about', 'gallery', 'map'], true)) {
                    $block_type = 'about';
                }
                $body = trim((string) ($b['body'] ?? ''));
                $images = trim((string) ($b['images'] ?? ''));
                $map_embed_url = trim((string) ($b['map_embed_url'] ?? ''));

                $pdo->prepare('UPDATE event_content_blocks SET sort_order=?, block_type=?, title=?, body=?, images=?, map_embed_url=? WHERE id=? AND event_id=?')
                    ->execute([$sort_order, $block_type, $title, $body, $images, $map_embed_url, $id, $event_id]);
            }
            cms_log($pdo, 'update', 'event_content', $event_id, ['blocks' => count($blocks)]);
            header('Location: event-content.php?event_id=' . $event_id);
            exit;
        }
    } catch (Throwable $e) {
        $errors[] = 'Ошибка: ' . $e->getMessage();
    }
}

$blocks = get_event_content_blocks($event_id);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Контент мероприятия — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<?php include __DIR__ . '/_header.php'; ?>
<main class="admin-main">
    <div class="page-title">
        <h1>Контент: <?= htmlspecialchars((string) ($event['title'] ?? '')) ?></h1>
        <a href="event-edit.php?id=<?= (int) $event_id ?>" class="btn btn-secondary">Назад к событию</a>
    </div>

    <?php foreach ($errors as $e): ?><p class="admin-flash admin-flash--err"><?= htmlspecialchars($e) ?></p><?php endforeach; ?>

    <p class="admin-lead admin-muted">Блоки «О событии», галерея и карта на детальной странице. Если блоков нет — показывается краткое описание из основной формы.</p>

    <div class="admin-bulk-bar" style="justify-content:space-between;gap:12px;">
        <form method="post" style="display:flex;gap:8px;align-items:center;margin:0;">
            <input type="hidden" name="action" value="add_block">
            <label style="display:flex;gap:8px;align-items:center;margin:0;">
                <span style="font-size:0.9rem;opacity:0.9;">Добавить блок</span>
                <select name="block_type">
                    <option value="about">Текст (о событии)</option>
                    <option value="gallery">Галерея</option>
                    <option value="map">Карта</option>
                </select>
            </label>
            <button class="btn btn-primary" type="submit">Добавить</button>
        </form>
        <a class="btn btn-secondary" href="/event.php?slug=<?= urlencode((string) ($event['slug'] ?? '')) ?>" target="_blank" rel="noopener">Открыть на сайте</a>
    </div>

    <form method="post" class="js-admin-form-unsaved">
        <input type="hidden" name="action" value="save_blocks">

        <?php if (!$blocks): ?>
            <p class="admin-muted">Пока нет блоков контента.</p>
        <?php endif; ?>

        <?php foreach ($blocks as $b): ?>
            <?php
            $bid = (int) ($b['id'] ?? 0);
            $type = (string) ($b['block_type'] ?? 'about');
            ?>
            <section class="admin-card" style="padding:14px;margin:12px 0;border:1px solid rgba(255,255,255,.08);border-radius:12px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <strong>#<?= $bid ?></strong>
                        <label style="display:flex;align-items:center;gap:6px;margin:0;">
                            <span style="opacity:.8;">Порядок</span>
                            <input type="number" name="blocks[<?= $bid ?>][sort_order]" value="<?= (int) ($b['sort_order'] ?? 0) ?>" style="width:86px;">
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;margin:0;">
                            <span style="opacity:.8;">Тип</span>
                            <select name="blocks[<?= $bid ?>][block_type]">
                                <option value="about" <?= $type === 'about' ? 'selected' : '' ?>>Текст</option>
                                <option value="gallery" <?= $type === 'gallery' ? 'selected' : '' ?>>Галерея</option>
                                <option value="map" <?= $type === 'map' ? 'selected' : '' ?>>Карта</option>
                            </select>
                        </label>
                    </div>
                    <button class="btn btn-danger" type="submit" name="delete_block_id" value="<?= $bid ?>" data-confirm="Удалить блок?">Удалить</button>
                </div>

                <div class="form-group" style="margin-top:10px;">
                    <label>Заголовок</label>
                    <input type="text" name="blocks[<?= $bid ?>][title]" value="<?= htmlspecialchars((string) ($b['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                </div>

                <div class="form-group">
                    <label>Текст (абзацы через пустую строку)</label>
                    <textarea name="blocks[<?= $bid ?>][body]" rows="6"><?= htmlspecialchars((string) ($b['body'] ?? '')) ?></textarea>
                </div>

                <div class="form-group">
                    <label>Изображения галереи (по одному URL в строке)</label>
                    <textarea name="blocks[<?= $bid ?>][images]" rows="4"><?= htmlspecialchars((string) ($b['images'] ?? '')) ?></textarea>
                </div>

                <div class="form-group">
                    <label>URL embed карты (iframe src)</label>
                    <textarea name="blocks[<?= $bid ?>][map_embed_url]" rows="2"><?= htmlspecialchars((string) ($b['map_embed_url'] ?? '')) ?></textarea>
                </div>
            </section>
        <?php endforeach; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Сохранить блоки</button>
            <a href="event-edit.php?id=<?= (int) $event_id ?>" class="btn btn-secondary">Отмена</a>
        </div>
    </form>
</main>
</body>
</html>
