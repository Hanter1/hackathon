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
$message = '';

function cc_norm_state(string $s): string
{
    $s = trim($s);
    return in_array($s, ['checked', 'active', 'blocked'], true) ? $s : 'active';
}
function cc_norm_action(string $s): string
{
    $s = trim($s);
    return in_array($s, ['play', 'check', 'lock'], true) ? $s : 'play';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save_sort') {
            header('Content-Type: application/json; charset=utf-8');
            $modulesOrderRaw = trim((string) ($_POST['modules_order'] ?? ''));
            $itemOrdersRaw = trim((string) ($_POST['item_orders'] ?? ''));
            $modulesOrder = [];
            if ($modulesOrderRaw !== '') {
                foreach (explode(',', $modulesOrderRaw) as $p) {
                    $id = (int) trim($p);
                    if ($id > 0) $modulesOrder[] = $id;
                }
            }
            $itemOrders = [];
            if ($itemOrdersRaw !== '') {
                $decoded = json_decode($itemOrdersRaw, true);
                if (is_array($decoded)) {
                    $itemOrders = $decoded;
                }
            }

            if ($modulesOrder) {
                $st = $pdo->prepare('UPDATE course_curriculum_modules SET sort_order = ? WHERE id = ? AND course_id = ?');
                foreach ($modulesOrder as $i => $mid) {
                    $st->execute([(int) $i, (int) $mid, (int) $course_id]);
                }
            }
            if ($itemOrders) {
                $allowedModules = array_flip($modulesOrder ?: array_map('intval', array_keys($itemOrders)));
                $st = $pdo->prepare('UPDATE course_curriculum_items SET module_id = ?, sort_order = ? WHERE id = ?');
                foreach ($itemOrders as $moduleIdStr => $ids) {
                    if (!is_array($ids)) continue;
                    $moduleId = (int) $moduleIdStr;
                    if ($moduleId <= 0 || !isset($allowedModules[$moduleId])) continue;
                    foreach ($ids as $i => $iid) {
                        $id = (int) $iid;
                        if ($id <= 0) continue;
                        $st->execute([$moduleId, (int) $i, $id]);
                    }
                }
            }
            cms_log($pdo, 'sort', 'course_curriculum', $course_id, ['modules' => count($modulesOrder), 'items' => is_array($itemOrders) ? count($itemOrders) : 0]);
            echo json_encode(['ok' => true]);
            exit;
        }

        if ($action === 'add_module') {
            $st = $pdo->prepare('INSERT INTO course_curriculum_modules (course_id, sort_order, title, is_open) VALUES (?,?,?,?)');
            $st->execute([$course_id, 0, 'Module', 1]);
            cms_log($pdo, 'create', 'course_curriculum_module', (int) $pdo->lastInsertId(), ['course_id' => $course_id]);
            header('Location: course-curriculum.php?course_id=' . $course_id);
            exit;
        }

        if ($action === 'add_item') {
            $module_id = (int) ($_POST['module_id'] ?? 0);
            if ($module_id > 0) {
                $st = $pdo->prepare('INSERT INTO course_curriculum_items (module_id, sort_order, title, progress_percent, duration_label, state, action, action_url) VALUES (?,?,?,?,?,?,?,?)');
                $st->execute([$module_id, 0, 'Lesson', 0, '', 'active', 'play', '']);
                cms_log($pdo, 'create', 'course_curriculum_item', (int) $pdo->lastInsertId(), ['course_id' => $course_id, 'module_id' => $module_id]);
                $message = 'Урок добавлен.';
            } else {
                $errors[] = 'Не выбран модуль для урока.';
            }
        }

        if ($action === 'generate_from_count') {
            $count = (int) ($course['lessons_count'] ?? 0);
            if ($count <= 0) $count = 7;
            $st = $pdo->prepare('INSERT INTO course_curriculum_modules (course_id, sort_order, title, is_open) VALUES (?,?,?,?)');
            $st->execute([$course_id, 0, 'Module 1', 1]);
            $moduleId = (int) $pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO course_curriculum_items (module_id, sort_order, title, progress_percent, duration_label, state, action, action_url) VALUES (?,?,?,?,?,?,?,?)');
            for ($i = 1; $i <= $count; $i++) {
                $ins->execute([$moduleId, $i - 1, 'Lesson ' . $i, 0, '', 'active', 'play', '']);
            }
            cms_log($pdo, 'create', 'course_curriculum_generate', $course_id, ['lessons' => $count]);
            header('Location: course-curriculum.php?course_id=' . $course_id);
            exit;
        }

        $deleteModuleId = (int) ($_POST['delete_module_id'] ?? 0);
        if ($deleteModuleId > 0) {
            $pdo->prepare('DELETE FROM course_curriculum_items WHERE module_id = ?')->execute([$deleteModuleId]);
            $pdo->prepare('DELETE FROM course_curriculum_modules WHERE id = ? AND course_id = ?')->execute([$deleteModuleId, $course_id]);
            cms_log($pdo, 'delete', 'course_curriculum_module', $deleteModuleId, ['course_id' => $course_id]);
            header('Location: course-curriculum.php?course_id=' . $course_id);
            exit;
        }

        $deleteItemId = (int) ($_POST['delete_item_id'] ?? 0);
        if ($deleteItemId > 0) {
            $pdo->prepare('DELETE FROM course_curriculum_items WHERE id = ?')->execute([$deleteItemId]);
            cms_log($pdo, 'delete', 'course_curriculum_item', $deleteItemId, ['course_id' => $course_id]);
            header('Location: course-curriculum.php?course_id=' . $course_id);
            exit;
        }

        if ($action === 'save_curriculum') {
            $modules = (array) ($_POST['modules'] ?? []);
            foreach ($modules as $idStr => $m) {
                $id = (int) $idStr;
                if ($id <= 0) continue;
                $title = trim((string) ($m['title'] ?? ''));
                $sort_order = (int) ($m['sort_order'] ?? 0);
                $is_open = !empty($m['is_open']) ? 1 : 0;
                $pdo->prepare('UPDATE course_curriculum_modules SET sort_order=?, title=?, is_open=? WHERE id=? AND course_id=?')
                    ->execute([$sort_order, $title, $is_open, $id, $course_id]);
            }

            $items = (array) ($_POST['items'] ?? []);
            foreach ($items as $idStr => $it) {
                $id = (int) $idStr;
                if ($id <= 0) continue;
                $title = trim((string) ($it['title'] ?? ''));
                $sort_order = (int) ($it['sort_order'] ?? 0);
                $progress = (int) ($it['progress_percent'] ?? 0);
                if ($progress < 0) $progress = 0;
                if ($progress > 100) $progress = 100;
                $duration = trim((string) ($it['duration_label'] ?? ''));
                $state = cc_norm_state((string) ($it['state'] ?? 'active'));
                $act = cc_norm_action((string) ($it['action'] ?? 'play'));
                $url = trim((string) ($it['action_url'] ?? ''));

                $pdo->prepare('UPDATE course_curriculum_items SET sort_order=?, title=?, progress_percent=?, duration_label=?, state=?, action=?, action_url=? WHERE id=?')
                    ->execute([$sort_order, $title, $progress, $duration, $state, $act, $url, $id]);
            }

            $newItems = (array) ($_POST['new_items'] ?? []);
            foreach ($newItems as $moduleIdStr => $rows) {
                $moduleId = (int) $moduleIdStr;
                if ($moduleId <= 0 || !is_array($rows)) continue;
                foreach ($rows as $tmpKey => $it) {
                    if (!is_array($it)) continue;
                    $title = trim((string) ($it['title'] ?? ''));
                    $sort_order = (int) ($it['sort_order'] ?? 0);
                    $progress = (int) ($it['progress_percent'] ?? 0);
                    if ($progress < 0) $progress = 0;
                    if ($progress > 100) $progress = 100;
                    $duration = trim((string) ($it['duration_label'] ?? ''));
                    $state = cc_norm_state((string) ($it['state'] ?? 'active'));
                    $act = cc_norm_action((string) ($it['action'] ?? 'play'));
                    $url = trim((string) ($it['action_url'] ?? ''));
                    if ($title === '') continue;
                    $pdo->prepare('INSERT INTO course_curriculum_items (module_id, sort_order, title, progress_percent, duration_label, state, action, action_url) VALUES (?,?,?,?,?,?,?,?)')
                        ->execute([$moduleId, $sort_order, $title, $progress, $duration, $state, $act, $url]);
                }
            }

            cms_log($pdo, 'update', 'course_curriculum', $course_id, ['modules' => count($modules), 'items' => count($items)]);
            header('Location: course-curriculum.php?course_id=' . $course_id);
            exit;
        }
    } catch (Throwable $e) {
        $errors[] = 'Ошибка: ' . $e->getMessage();
    }
}

$st = $pdo->prepare('SELECT * FROM course_curriculum_modules WHERE course_id = ? ORDER BY sort_order ASC, id ASC');
$st->execute([$course_id]);
$modules = $st->fetchAll(PDO::FETCH_ASSOC);

$itemsByModule = [];
if ($modules) {
    $ids = array_map(static fn($m) => (int) $m['id'], $modules);
    $ids = array_values(array_filter($ids));
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT * FROM course_curriculum_items WHERE module_id IN ($in) ORDER BY module_id ASC, sort_order ASC, id ASC");
        $st->execute($ids);
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $mid = (int) ($r['module_id'] ?? 0);
            if (!isset($itemsByModule[$mid])) $itemsByModule[$mid] = [];
            $itemsByModule[$mid][] = $r;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Curriculum курса — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<?php include __DIR__ . '/_header.php'; ?>
<main class="admin-main">
    <div class="page-title">
        <h1>Curriculum: <?= htmlspecialchars((string) ($course['title'] ?? '')) ?></h1>
        <a href="course-edit.php?id=<?= (int) $course_id ?>" class="btn btn-secondary">Назад к курсу</a>
    </div>

    <?php if ($message): ?><p class="admin-flash admin-flash--ok"><?= htmlspecialchars($message) ?></p><?php endif; ?>
    <?php foreach ($errors as $e): ?><p class="admin-flash admin-flash--err"><?= htmlspecialchars($e) ?></p><?php endforeach; ?>

    <div class="admin-bulk-bar" style="justify-content:space-between;gap:12px;">
        <form method="post" style="display:flex;gap:8px;align-items:center;margin:0;">
            <input type="hidden" name="action" value="add_module">
            <button class="btn btn-primary" type="submit">Добавить модуль</button>
        </form>
        <?php if (!$modules): ?>
        <form method="post" style="display:flex;gap:8px;align-items:center;margin:0;">
            <input type="hidden" name="action" value="generate_from_count">
            <button class="btn btn-secondary" type="submit" data-confirm="Создать программу из количества уроков?">Сгенерировать программу</button>
        </form>
        <?php endif; ?>
        <a class="btn btn-secondary" href="/course.php?slug=<?= urlencode((string) ($course['slug'] ?? '')) ?>" target="_blank" rel="noopener">Открыть на сайте</a>
    </div>

    <form method="post" class="js-admin-form-unsaved" id="cc-curriculum-form">
        <input type="hidden" name="action" value="save_curriculum">

        <?php if (!$modules): ?>
            <p class="admin-muted">Пока нет модулей Curriculum.</p>
        <?php endif; ?>

        <div id="cc-modules" data-course-id="<?= (int) $course_id ?>">
        <?php foreach ($modules as $m): ?>
            <?php $mid = (int) ($m['id'] ?? 0); ?>
            <section class="admin-card cc-module" data-module-id="<?= (int) $mid ?>" draggable="true" style="padding:14px;margin:12px 0;border:1px solid rgba(255,255,255,.08);border-radius:12px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <span class="cc-drag-handle" title="Перетащить модуль" aria-hidden="true">⋮⋮</span>
                        <strong>Модуль #<?= $mid ?></strong>
                        <label style="display:flex;align-items:center;gap:6px;margin:0;">
                            <span style="opacity:.8;">Порядок</span>
                            <input type="number" name="modules[<?= $mid ?>][sort_order]" value="<?= (int) ($m['sort_order'] ?? 0) ?>" style="width:86px;">
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;margin:0;">
                            <span style="opacity:.8;">Открыт</span>
                            <input type="checkbox" name="modules[<?= $mid ?>][is_open]" value="1" <?= !empty($m['is_open']) ? 'checked' : '' ?>>
                        </label>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <button class="btn btn-secondary btn-small js-cc-toggle-module" type="button" data-module-id="<?= (int) $mid ?>">Свернуть</button>
                        <button class="btn btn-danger" type="submit" name="delete_module_id" value="<?= $mid ?>" data-confirm="Удалить модуль и все уроки внутри?">Удалить модуль</button>
                    </div>
                </div>

                <div class="cc-module__body">
                <div class="form-group" style="margin-top:10px;">
                    <label>Название модуля</label>
                    <input type="text" name="modules[<?= $mid ?>][title]" value="<?= htmlspecialchars((string) ($m['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin:10px 0 6px;">
                    <strong style="font-size:0.95rem;">Уроки</strong>
                </div>

                <div style="display:flex;gap:8px;flex-wrap:wrap;margin:0 0 8px;">
                    <button class="btn btn-secondary js-cc-add-item" type="button" data-module-id="<?= (int) $mid ?>">+ Добавить урок</button>
                </div>

                <?php $items = $itemsByModule[$mid] ?? []; ?>
                <?php if (!$items): ?>
                    <p class="admin-muted" style="margin:8px 0 0;">У модуля пока нет уроков.</p>
                <?php endif; ?>

                <div class="js-cc-items" data-module-id="<?= (int) $mid ?>">
                    <div class="cc-drop-empty" aria-hidden="true">Перетащите урок сюда</div>
                <?php foreach ($items as $it): ?>
                    <?php $iid = (int) ($it['id'] ?? 0); ?>
                    <div class="cc-item" data-item-id="<?= (int) $iid ?>" draggable="true" style="padding:12px;margin:10px 0 0;border:1px solid rgba(255,255,255,.06);border-radius:12px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                <span class="cc-drag-handle" title="Перетащить урок" aria-hidden="true">⋮⋮</span>
                                <strong>#<?= $iid ?></strong>
                                <label style="display:flex;align-items:center;gap:6px;margin:0;">
                                    <span style="opacity:.8;">Порядок</span>
                                    <input type="number" name="items[<?= $iid ?>][sort_order]" value="<?= (int) ($it['sort_order'] ?? 0) ?>" style="width:86px;">
                                </label>
                                <label style="display:flex;align-items:center;gap:6px;margin:0;">
                                    <span style="opacity:.8;">%</span>
                                    <input type="number" min="0" max="100" name="items[<?= $iid ?>][progress_percent]" value="<?= (int) ($it['progress_percent'] ?? 0) ?>" style="width:86px;">
                                </label>
                                <label style="display:flex;align-items:center;gap:6px;margin:0;">
                                    <span style="opacity:.8;">Время</span>
                                    <input type="text" name="items[<?= $iid ?>][duration_label]" value="<?= htmlspecialchars((string) ($it['duration_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="width:140px;">
                                </label>
                            </div>
                            <button class="btn btn-danger" type="submit" name="delete_item_id" value="<?= $iid ?>" data-confirm="Удалить урок?">Удалить</button>
                        </div>

                        <div class="form-group" style="margin-top:10px;">
                            <label>Заголовок урока</label>
                            <input type="text" name="items[<?= $iid ?>][title]" value="<?= htmlspecialchars((string) ($it['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                            <div class="form-group" style="margin:0;">
                                <label>Состояние</label>
                                <?php $state = (string) ($it['state'] ?? 'active'); ?>
                                <select name="items[<?= $iid ?>][state]">
                                    <option value="checked" <?= $state === 'checked' ? 'selected' : '' ?>>checked</option>
                                    <option value="active" <?= $state === 'active' ? 'selected' : '' ?>>active</option>
                                    <option value="blocked" <?= $state === 'blocked' ? 'selected' : '' ?>>blocked</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label>Кнопка/иконка</label>
                                <?php $act = (string) ($it['action'] ?? 'play'); ?>
                                <select name="items[<?= $iid ?>][action]">
                                    <option value="play" <?= $act === 'play' ? 'selected' : '' ?>>play</option>
                                    <option value="check" <?= $act === 'check' ? 'selected' : '' ?>>check</option>
                                    <option value="lock" <?= $act === 'lock' ? 'selected' : '' ?>>lock</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top:10px;">
                            <label>Ссылка (опционально)</label>
                            <input type="text" name="items[<?= $iid ?>][action_url]" value="<?= htmlspecialchars((string) ($it['action_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="https://... или /path" autocomplete="off">
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
                </div>
            </section>
        <?php endforeach; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Сохранить Curriculum</button>
            <a href="course-edit.php?id=<?= (int) $course_id ?>" class="btn btn-secondary">Отмена</a>
        </div>
    </form>

</main>
<script>
(function () {
    var seq = 0;
    function esc(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c] || c;
        });
    }
    function newItemHtml(moduleId, key) {
        var base = 'new_items[' + moduleId + '][' + key + ']';
        return '' +
        '<div class="js-cc-new-item" style="padding:12px;margin:10px 0 0;border:1px solid rgba(249,212,66,.25);border-radius:12px;background:rgba(249,212,66,.04);">' +
          '<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">' +
            '<strong style="color:rgba(249,212,66,.9);">Новый урок</strong>' +
            '<button class="btn btn-secondary btn-small js-cc-remove-new" type="button">Убрать</button>' +
          '</div>' +
          '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:10px;">' +
            '<label style="display:flex;align-items:center;gap:6px;margin:0;">' +
              '<span style="opacity:.8;">Порядок</span>' +
              '<input type="number" name="' + esc(base + '[sort_order]') + '" value="0" style="width:86px;">' +
            '</label>' +
            '<label style="display:flex;align-items:center;gap:6px;margin:0;">' +
              '<span style="opacity:.8;">%</span>' +
              '<input type="number" min="0" max="100" name="' + esc(base + '[progress_percent]') + '" value="0" style="width:86px;">' +
            '</label>' +
            '<label style="display:flex;align-items:center;gap:6px;margin:0;">' +
              '<span style="opacity:.8;">Время</span>' +
              '<input type="text" name="' + esc(base + '[duration_label]') + '" value="" style="width:140px;">' +
            '</label>' +
          '</div>' +
          '<div class="form-group" style="margin-top:10px;">' +
            '<label>Заголовок урока *</label>' +
            '<input type="text" name="' + esc(base + '[title]') + '" value="" autocomplete="off" required>' +
          '</div>' +
          '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">' +
            '<div class="form-group" style="margin:0;">' +
              '<label>Состояние</label>' +
              '<select name="' + esc(base + '[state]') + '">' +
                '<option value="checked">checked</option>' +
                '<option value="active" selected>active</option>' +
                '<option value="blocked">blocked</option>' +
              '</select>' +
            '</div>' +
            '<div class="form-group" style="margin:0;">' +
              '<label>Кнопка/иконка</label>' +
              '<select name="' + esc(base + '[action]') + '">' +
                '<option value="play" selected>play</option>' +
                '<option value="check">check</option>' +
                '<option value="lock">lock</option>' +
              '</select>' +
            '</div>' +
          '</div>' +
          '<div class="form-group" style="margin-top:10px;">' +
            '<label>Ссылка (опционально)</label>' +
            '<input type="text" name="' + esc(base + '[action_url]') + '" value="" placeholder="https://... или /path" autocomplete="off">' +
          '</div>' +
        '</div>';
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-cc-add-item');
        if (!btn) return;
        var mid = btn.getAttribute('data-module-id') || '';
        if (!mid) return;
        var wrap = document.querySelector('.js-cc-items[data-module-id="' + mid + '"]');
        if (!wrap) return;
        var key = 'tmp' + Date.now() + '_' + (seq++);
        wrap.insertAdjacentHTML('beforeend', newItemHtml(mid, key));
        refreshEmptyPlaceholders();
    });

    document.addEventListener('click', function (e) {
        var rm = e.target.closest('.js-cc-remove-new');
        if (!rm) return;
        var box = rm.closest('.js-cc-new-item');
        if (box) box.remove();
        refreshEmptyPlaceholders();
    });

    // Drag & drop sorting (modules and items) + autosave sort_order
    var modulesWrap = document.getElementById('cc-modules');
    var form = document.getElementById('cc-curriculum-form');
    var savingTimer = null;
    var lastSaveOkAt = 0;

    function refreshEmptyPlaceholders() {
        document.querySelectorAll('.js-cc-items[data-module-id]').forEach(function (wrap) {
            var hasItems = wrap.querySelectorAll('.cc-item[data-item-id]').length > 0 || wrap.querySelectorAll('.js-cc-new-item').length > 0;
            wrap.classList.toggle('is-empty', !hasItems);
        });
    }

    function markSortInputsFromDom() {
        if (!modulesWrap) return;
        var modules = modulesWrap.querySelectorAll('.cc-module[data-module-id]');
        modules.forEach(function (mEl, idx) {
            var mid = mEl.getAttribute('data-module-id');
            var inp = form.querySelector('input[name="modules[' + mid + '][sort_order]"]');
            if (inp) inp.value = String(idx);
            var itemsWrap = mEl.querySelector('.js-cc-items[data-module-id="' + mid + '"]');
            if (!itemsWrap) return;
            var items = itemsWrap.querySelectorAll('.cc-item[data-item-id]');
            items.forEach(function (itEl, j) {
                var iid = itEl.getAttribute('data-item-id');
                var in2 = form.querySelector('input[name="items[' + iid + '][sort_order]"]');
                if (in2) in2.value = String(j);
            });
        });
    }

    function collectSortPayload() {
        var modules = [];
        var itemOrders = {};
        modulesWrap.querySelectorAll('.cc-module[data-module-id]').forEach(function (mEl) {
            var mid = mEl.getAttribute('data-module-id');
            if (!mid) return;
            modules.push(mid);
            var ids = [];
            var itemsWrap = mEl.querySelector('.js-cc-items[data-module-id="' + mid + '"]');
            if (itemsWrap) {
                itemsWrap.querySelectorAll('.cc-item[data-item-id]').forEach(function (itEl) {
                    var iid = itEl.getAttribute('data-item-id');
                    if (iid) ids.push(iid);
                });
            }
            itemOrders[mid] = ids;
        });
        return { modules_order: modules.join(','), item_orders: JSON.stringify(itemOrders) };
    }

    function autosaveSortSoon() {
        if (savingTimer) window.clearTimeout(savingTimer);
        savingTimer = window.setTimeout(function () {
            savingTimer = null;
            if (!modulesWrap) return;
            markSortInputsFromDom();
            refreshEmptyPlaceholders();
            var payload = collectSortPayload();
            var body = new URLSearchParams();
            body.set('action', 'save_sort');
            body.set('modules_order', payload.modules_order);
            body.set('item_orders', payload.item_orders);
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString()
            }).then(function (r) { return r.json(); }).then(function (j) {
                if (j && j.ok) lastSaveOkAt = Date.now();
            }).catch(function () { /* ignore */ });
        }, 450);
    }

    function makeSortable(container, itemSelector) {
        var dragged = null;
        container.addEventListener('dragstart', function (e) {
            var el = e.target.closest(itemSelector);
            if (!el) return;
            dragged = el;
            el.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', '1'); } catch (err) {}
            if (container.classList) container.classList.add('is-dropover');
        });
        container.addEventListener('dragend', function () {
            if (dragged) dragged.classList.remove('is-dragging');
            dragged = null;
            if (container.classList) container.classList.remove('is-dropover');
        });
        container.addEventListener('dragover', function (e) {
            if (!dragged) return;
            e.preventDefault();
            if (container.classList) container.classList.add('is-dropover');
            var over = e.target.closest(itemSelector);
            if (over && over !== dragged) {
                var rect = over.getBoundingClientRect();
                var before = (e.clientY - rect.top) < rect.height / 2;
                over.parentNode.insertBefore(dragged, before ? over : over.nextSibling);
                return;
            }
            // allow dropping into empty space of container
            if (!over) {
                container.appendChild(dragged);
            }
        });
        container.addEventListener('dragleave', function (e) {
            if (!dragged) return;
            // remove highlight only when leaving container bounds
            if (e.relatedTarget && container.contains(e.relatedTarget)) return;
            if (container.classList) container.classList.remove('is-dropover');
        });
        container.addEventListener('drop', function (e) {
            if (!dragged) return;
            e.preventDefault();
            if (container.classList) container.classList.remove('is-dropover');
            autosaveSortSoon();
        });
    }

    if (modulesWrap) {
        makeSortable(modulesWrap, '.cc-module[data-module-id]');
        document.querySelectorAll('.js-cc-items[data-module-id]').forEach(function (wrap) {
            makeSortable(wrap, '.cc-item[data-item-id]');
        });
    }

    // Collapse/expand modules (persist in localStorage)
    function storageKey(mid) {
        var cid = modulesWrap ? (modulesWrap.getAttribute('data-course-id') || '') : '';
        return 'cc_curriculum_collapsed_' + cid + '_' + mid;
    }

    function setCollapsed(moduleEl, collapsed) {
        if (!moduleEl) return;
        moduleEl.classList.toggle('is-collapsed', !!collapsed);
        var mid = moduleEl.getAttribute('data-module-id') || '';
        var btn = moduleEl.querySelector('.js-cc-toggle-module');
        if (btn) btn.textContent = collapsed ? 'Развернуть' : 'Свернуть';
        if (mid) {
            try { localStorage.setItem(storageKey(mid), collapsed ? '1' : '0'); } catch (e) {}
        }
    }

    if (modulesWrap) {
        modulesWrap.querySelectorAll('.cc-module[data-module-id]').forEach(function (mEl) {
            var mid = mEl.getAttribute('data-module-id') || '';
            var collapsed = false;
            try { collapsed = localStorage.getItem(storageKey(mid)) === '1'; } catch (e) {}
            setCollapsed(mEl, collapsed);
        });
    }

    document.addEventListener('click', function (e) {
        var t = e.target.closest('.js-cc-toggle-module');
        if (!t) return;
        var mid = t.getAttribute('data-module-id') || '';
        if (!mid) return;
        var mEl = document.querySelector('.cc-module[data-module-id="' + mid + '"]');
        if (!mEl) return;
        setCollapsed(mEl, !mEl.classList.contains('is-collapsed'));
    });

    refreshEmptyPlaceholders();
})();
</script>
</body>
</html>

