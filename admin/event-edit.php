<?php
require_once __DIR__ . '/auth.php';
require_site_content_editor();
$adminNavActive = 'events';
require_once DOC_ROOT . '/include/data.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$dupFrom = isset($_GET['duplicate_from']) ? (int) $_GET['duplicate_from'] : 0;
$item = null;
if ($id > 0) {
    $st = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $st->execute([$id]);
    $item = $st->fetch() ?: null;
}
if (!$item && $dupFrom > 0) {
    $st = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $st->execute([$dupFrom]);
    $src = $st->fetch(PDO::FETCH_ASSOC);
    if ($src) {
        unset($src['id']);
        $src['title'] = ($src['title'] ?? 'Событие') . ' (копия)';
        $baseSlug = slugify((string) ($src['slug'] ?? $src['title']));
        $src['slug'] = $baseSlug . '-kopiya-' . date('YmdHis');
        $src['status'] = 'draft';
        $src['meta_title'] = '';
        $src['meta_description'] = '';
        $src['updated_at'] = null;
        $item = $src;
        cms_log($pdo, 'duplicate_from', 'event', $dupFrom, ['new_slug' => $src['slug']]);
    }
}
if ($id > 0 && !$item) {
    header('Location: events.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '') ?: slugify($title);
    $description = trim($_POST['description'] ?? '');
    $image = trim($_POST['image'] ?? '');
    $event_date = trim($_POST['event_date'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active', 'hidden', 'draft'], true) ? $_POST['status'] : 'active';
    $sort_order = (int) ($_POST['sort_order'] ?? 0);
    $meta_title = trim((string) ($_POST['meta_title'] ?? ''));
    $meta_description = trim((string) ($_POST['meta_description'] ?? ''));
    $lock_updated_at = trim((string) ($_POST['lock_updated_at'] ?? ''));
    $detailKeys = ['organizer_name', 'price', 'event_time', 'calendar_url', 'website_url', 'ticket_url', 'organizer_email', 'organizer_phone', 'organizer_website', 'venue_name', 'venue_address', 'venue_phone', 'map_embed_url'];
    $detail = [];
    foreach ($detailKeys as $dk) {
        $detail[$dk] = trim((string) ($_POST[$dk] ?? ''));
    }

    if (!$title) {
        $errors[] = 'Укажите название.';
    }
    if (empty($errors)) {
        $now = date('Y-m-d H:i:s');
        if ($id && $item) {
            $lockExpected = cms_row_lock_value($item);
            if ($lockExpected !== $lock_updated_at) {
                $errors[] = 'Событие уже изменено другим редактором. Обновите страницу.';
            } else {
                $st = $pdo->prepare('UPDATE events SET title=?, slug=?, description=?, image=?, event_date=?, location=?, status=?, sort_order=?, meta_title=?, meta_description=?, updated_at=?, organizer_name=?, price=?, event_time=?, calendar_url=?, website_url=?, ticket_url=?, organizer_email=?, organizer_phone=?, organizer_website=?, venue_name=?, venue_address=?, venue_phone=?, map_embed_url=? WHERE id=? AND COALESCE(updated_at, created_at) = ?');
                $st->execute([
                    $title, $slug, $description, $image, $event_date, $location, $status, $sort_order, $meta_title, $meta_description, $now,
                    $detail['organizer_name'], $detail['price'], $detail['event_time'], $detail['calendar_url'], $detail['website_url'], $detail['ticket_url'],
                    $detail['organizer_email'], $detail['organizer_phone'], $detail['organizer_website'], $detail['venue_name'], $detail['venue_address'], $detail['venue_phone'], $detail['map_embed_url'],
                    $id, $lock_updated_at,
                ]);
                if ($st->rowCount() === 0) {
                    $errors[] = 'Не удалось сохранить: конфликт правок. Обновите страницу.';
                } else {
                    cms_log($pdo, 'update', 'event', $id, ['title' => $title]);
                    ep_entity_i18n_save_from_post($pdo, 'event', $id, $_POST);
                    header('Location: events.php');
                    exit;
                }
            }
        } else {
            $pdo->prepare('INSERT INTO events (title, slug, description, image, event_date, location, status, sort_order, meta_title, meta_description, updated_at, organizer_name, price, event_time, calendar_url, website_url, ticket_url, organizer_email, organizer_phone, organizer_website, venue_name, venue_address, venue_phone, map_embed_url) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([
                    $title, $slug, $description, $image, $event_date, $location, $status, $sort_order, $meta_title, $meta_description, $now,
                    $detail['organizer_name'], $detail['price'], $detail['event_time'], $detail['calendar_url'], $detail['website_url'], $detail['ticket_url'],
                    $detail['organizer_email'], $detail['organizer_phone'], $detail['organizer_website'], $detail['venue_name'], $detail['venue_address'], $detail['venue_phone'], $detail['map_embed_url'],
                ]);
            $newId = (int) $pdo->lastInsertId();
            cms_log($pdo, 'create', 'event', $newId, ['title' => $title]);
            ep_entity_i18n_save_from_post($pdo, 'event', $newId, $_POST);
            header('Location: events.php');
            exit;
        }
    }
    $item = array_merge(
        compact('title', 'slug', 'description', 'image', 'event_date', 'location', 'status', 'sort_order', 'meta_title', 'meta_description'),
        $detail,
        $id && isset($item['id']) ? ['id' => $item['id'], 'created_at' => $item['created_at'] ?? '', 'updated_at' => $item['updated_at'] ?? ''] : []
    );
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id ? 'Редактировать' : 'Добавить' ?> событие — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <main class="admin-main">
        <div class="page-title">
            <h1><?= $id ? 'Редактировать событие' : ($dupFrom ? 'Новое событие (копия)' : 'Новое событие') ?></h1>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <?php if ($id): ?><a href="event-content.php?event_id=<?= (int) $id ?>" class="btn btn-secondary">Контент страницы</a><?php endif; ?>
                <a href="events.php" class="btn btn-secondary">Назад</a>
            </div>
        </div>
        <?php foreach ($errors as $e): ?><p class="admin-flash admin-flash--err"><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
        <form method="post" id="event-edit-form" class="js-admin-form-unsaved">
            <div class="admin-edit-split">
                <div class="admin-edit-split__main">
            <div class="form-group">
                <label for="event-in-title">Название *</label>
                <input id="event-in-title" type="text" name="title" value="<?= htmlspecialchars($item['title'] ?? '') ?>" required autocomplete="off">
                <p class="admin-muted" style="margin:0.35rem 0 0;font-size:0.85rem;">Для переноса строки в шапке — новая строка в названии.</p>
            </div>
            <div class="form-group">
                <label for="event-in-slug">Slug (URL)</label>
                <input id="event-in-slug" type="text" name="slug" value="<?= htmlspecialchars($item['slug'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="event-in-description">Описание</label>
                <textarea id="event-in-description" name="description"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="event-in-image">Обложка (путь)</label>
                <input id="event-in-image" type="text" name="image" value="<?= htmlspecialchars($item['image'] ?? '') ?>" autocomplete="off">
                <?php $media_picker_input_id = 'event-in-image'; include __DIR__ . '/partials/media-picker-cms.php'; ?>
            </div>
            <div class="form-group">
                <label for="event-in-date">Дата события</label>
                <input id="event-in-date" type="text" name="event_date" value="<?= htmlspecialchars($item['event_date'] ?? '') ?>" placeholder="например 15 марта 2025" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="event-in-location">Место</label>
                <input id="event-in-location" type="text" name="location" value="<?= htmlspecialchars($item['location'] ?? '') ?>" autocomplete="off">
            </div>
            <fieldset class="admin-fieldset">
                <legend>Шапка и кнопки</legend>
                <div class="form-group">
                    <label for="event-in-organizer">Организатор (вторая строка в шапке)</label>
                    <input id="event-in-organizer" type="text" name="organizer_name" value="<?= htmlspecialchars($item['organizer_name'] ?? '') ?>" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="event-in-price">Цена (для карточки / сайдбара)</label>
                    <input id="event-in-price" type="text" name="price" value="<?= htmlspecialchars($item['price'] ?? '') ?>" placeholder="100 Br или Free" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="event-in-time">Время</label>
                    <input id="event-in-time" type="text" name="event_time" value="<?= htmlspecialchars($item['event_time'] ?? '') ?>" placeholder="9:30 – 18:00" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="event-in-calendar">Ссылка «Добавить в календарь»</label>
                    <input id="event-in-calendar" type="url" name="calendar_url" value="<?= htmlspecialchars($item['calendar_url'] ?? '') ?>" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="event-in-website">Официальный сайт</label>
                    <input id="event-in-website" type="url" name="website_url" value="<?= htmlspecialchars($item['website_url'] ?? '') ?>" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="event-in-ticket">Ссылка «Купить билет»</label>
                    <input id="event-in-ticket" type="url" name="ticket_url" value="<?= htmlspecialchars($item['ticket_url'] ?? '') ?>" autocomplete="off">
                </div>
            </fieldset>
            <fieldset class="admin-fieldset">
                <legend>Организатор (сайдбар)</legend>
                <div class="form-group">
                    <label for="event-in-org-email">Email</label>
                    <input id="event-in-org-email" type="email" name="organizer_email" value="<?= htmlspecialchars($item['organizer_email'] ?? '') ?>" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="event-in-org-phone">Телефон</label>
                    <input id="event-in-org-phone" type="text" name="organizer_phone" value="<?= htmlspecialchars($item['organizer_phone'] ?? '') ?>" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="event-in-org-web">Сайт организатора</label>
                    <input id="event-in-org-web" type="url" name="organizer_website" value="<?= htmlspecialchars($item['organizer_website'] ?? '') ?>" autocomplete="off">
                </div>
            </fieldset>
            <fieldset class="admin-fieldset">
                <legend>Площадка и карта</legend>
                <div class="form-group">
                    <label for="event-in-venue-name">Название площадки</label>
                    <input id="event-in-venue-name" type="text" name="venue_name" value="<?= htmlspecialchars($item['venue_name'] ?? '') ?>" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="event-in-venue-address">Адрес</label>
                    <input id="event-in-venue-address" type="text" name="venue_address" value="<?= htmlspecialchars($item['venue_address'] ?? '') ?>" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="event-in-venue-phone">Телефон площадки</label>
                    <input id="event-in-venue-phone" type="text" name="venue_phone" value="<?= htmlspecialchars($item['venue_phone'] ?? '') ?>" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="event-in-map">URL embed Google Maps (iframe src)</label>
                    <textarea id="event-in-map" name="map_embed_url" rows="2"><?= htmlspecialchars($item['map_embed_url'] ?? '') ?></textarea>
                </div>
            </fieldset>
            <div class="form-group">
                <label for="event-in-status">Статус</label>
                <select id="event-in-status" name="status">
                    <option value="active" <?= ($item['status'] ?? '') === 'active' ? 'selected' : '' ?>>Активно</option>
                    <option value="hidden" <?= ($item['status'] ?? '') === 'hidden' ? 'selected' : '' ?>>Скрыто</option>
                    <option value="draft" <?= ($item['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Черновик</option>
                </select>
            </div>
            <div class="form-group">
                <label for="event-in-sort">Порядок</label>
                <input id="event-in-sort" type="number" name="sort_order" value="<?= (int) ($item['sort_order'] ?? 0) ?>">
            </div>
            <?php
            $seo_id_prefix = 'event';
            $seo_legend = 'SEO';
            $seo_hint = '';
            include __DIR__ . '/partials/form-seo.php';
            ?>
            <?php if ($id && $item): ?>
            <input type="hidden" name="lock_updated_at" value="<?= htmlspecialchars(cms_row_lock_value($item), ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
                </div>
                <aside class="admin-edit-split__aside" aria-label="Превью страницы события">
                    <p class="admin-preview-aside-caption">Превью</p>
                    <p class="admin-preview-aside-hint">Шапка страницы события.</p>
                    <div class="admin-preview-panel">
                        <div class="admin-preview-pagehero">
                            <p id="event-pv-date" class="admin-preview-pagehero__suptext"></p>
                            <h2 id="event-pv-title" class="admin-preview-pagehero__title"></h2>
                            <p id="event-pv-location" class="admin-preview-pagehero__meta"></p>
                            <p id="event-pv-status" class="admin-preview-pagehero__meta" style="margin-top:0.35rem;font-size:0.72rem;opacity:0.85;"></p>
                            <div id="event-pv-cover-wrap" style="display:none;margin-top:0.65rem;">
                                <img id="event-pv-cover" src="" alt="" class="admin-preview-blog__img" style="max-height:90px;margin-bottom:0;">
                            </div>
                            <div id="event-pv-desc" class="admin-preview-pagehero__text"></div>
                        </div>
                    </div>
                </aside>
            </div>
            <?php
            $i18n_entity_type = 'event';
            $i18n_entity_id = (int) ($item['id'] ?? $id);
            include __DIR__ . '/partials/form-entity-i18n.php';
            ?>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Сохранить</button>
                <a href="events.php" class="btn btn-secondary">Отмена</a>
            </div>
        </form>
    </main>
    <script>
    (function () {
        function sync() {
            var title = document.getElementById('event-in-title');
            var desc = document.getElementById('event-in-description');
            var image = document.getElementById('event-in-image');
            var dt = document.getElementById('event-in-date');
            var loc = document.getElementById('event-in-location');
            var st = document.getElementById('event-in-status');
            document.getElementById('event-pv-date').textContent = (dt && dt.value) ? dt.value : '';
            document.getElementById('event-pv-title').textContent = (title && title.value.trim()) ? title.value.trim() : '—';
            document.getElementById('event-pv-location').textContent = (loc && loc.value.trim()) ? loc.value.trim() : '';
            document.getElementById('event-pv-status').textContent = (st && st.value) ? ('Статус: ' + st.value) : '';
            var d = (desc && desc.value) ? desc.value.replace(/\s+/g, ' ').trim() : '';
            if (d.length > 400) d = d.slice(0, 400) + '…';
            document.getElementById('event-pv-desc').textContent = d || '(описание)';
            var wrap = document.getElementById('event-pv-cover-wrap');
            var im = document.getElementById('event-pv-cover');
            var path = (image && image.value.trim()) ? image.value.trim() : '';
            if (path && wrap && im) {
                im.src = path.indexOf('http') === 0 ? path : ('/' + path.replace(/^\//, ''));
                wrap.style.display = 'block';
            } else if (wrap) {
                wrap.style.display = 'none';
            }
        }
        ['event-in-title', 'event-in-description', 'event-in-image', 'event-in-date', 'event-in-location', 'event-in-status'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('input', sync);
            if (el) el.addEventListener('change', sync);
        });
        sync();
    })();
    </script>
    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
