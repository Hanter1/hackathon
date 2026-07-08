<?php
require_once __DIR__ . '/auth.php';
require_site_content_editor();
$adminNavActive = 'courses';
require_once DOC_ROOT . '/include/data.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$dupFrom = isset($_GET['duplicate_from']) ? (int) $_GET['duplicate_from'] : 0;
$item = null;
if ($id > 0) {
    $st = $pdo->prepare('SELECT * FROM courses WHERE id = ?');
    $st->execute([$id]);
    $item = $st->fetch() ?: null;
}
if (!$item && $dupFrom > 0) {
    $st = $pdo->prepare('SELECT * FROM courses WHERE id = ?');
    $st->execute([$dupFrom]);
    $src = $st->fetch(PDO::FETCH_ASSOC);
    if ($src) {
        unset($src['id']);
        $src['title'] = ($src['title'] ?? 'Курс') . ' (копия)';
        $baseSlug = slugify((string) ($src['slug'] ?? $src['title']));
        $src['slug'] = $baseSlug . '-kopiya-' . date('YmdHis');
        $src['status'] = 'draft';
        $src['meta_title'] = '';
        $src['meta_description'] = '';
        $src['updated_at'] = null;
        $item = $src;
        cms_log($pdo, 'duplicate_from', 'course', $dupFrom, ['new_slug' => $src['slug']]);
    }
}
$teachers = get_teachers();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '') ?: slugify($title);
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image = trim($_POST['image'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $lessons_count = (int) ($_POST['lessons_count'] ?? 0);
    $students_count = (int) ($_POST['students_count'] ?? 0);
    $level_label = trim((string) ($_POST['level_label'] ?? ''));
    $duration_label = trim((string) ($_POST['duration_label'] ?? ''));
    $language_label = trim((string) ($_POST['language_label'] ?? ''));
    $certificate_label = trim((string) ($_POST['certificate_label'] ?? ''));
    $certificate_enabled = null;
    if (array_key_exists('certificate_enabled', $_POST)) {
        $rawCert = trim((string) ($_POST['certificate_enabled'] ?? ''));
        if ($rawCert === '1' || $rawCert === '0') {
            $certificate_enabled = (int) $rawCert;
        }
    }
    $quizzes_count = (int) ($_POST['quizzes_count'] ?? 0);
    $rating = trim($_POST['rating'] ?? '4.5');
    $teacher_id = !empty($_POST['teacher_id']) ? (int) $_POST['teacher_id'] : null;
    $status = in_array($_POST['status'] ?? '', ['active', 'hidden', 'draft'], true) ? $_POST['status'] : 'active';
    $sort_order = (int) ($_POST['sort_order'] ?? 0);
    $meta_title = trim((string) ($_POST['meta_title'] ?? ''));
    $meta_description = trim((string) ($_POST['meta_description'] ?? ''));
    $lock_updated_at = trim((string) ($_POST['lock_updated_at'] ?? ''));

    if (!$title) {
        $errors[] = 'Укажите название.';
    }
    if (empty($errors)) {
        $now = date('Y-m-d H:i:s');
        if ($id && $item) {
            $lockExpected = cms_row_lock_value($item);
            if ($lockExpected !== $lock_updated_at) {
                $errors[] = 'Этот курс уже изменён другим редактором. Обновите страницу и сохраните снова.';
            } else {
                $st = $pdo->prepare('UPDATE courses SET title=?, slug=?, category=?, description=?, image=?, price=?, lessons_count=?, students_count=?, level_label=?, duration_label=?, language_label=?, certificate_label=?, certificate_enabled=?, quizzes_count=?, rating=?, teacher_id=?, status=?, sort_order=?, meta_title=?, meta_description=?, updated_at=? WHERE id=? AND COALESCE(updated_at, created_at) = ?');
                $st->execute([$title, $slug, $category, $description, $image, $price, $lessons_count, $students_count, $level_label, $duration_label, $language_label, $certificate_label, $certificate_enabled, $quizzes_count, $rating, $teacher_id, $status, $sort_order, $meta_title, $meta_description, $now, $id, $lock_updated_at]);
                if ($st->rowCount() === 0) {
                    $errors[] = 'Не удалось сохранить: запись изменилась. Обновите страницу.';
                } else {
                    cms_log($pdo, 'update', 'course', $id, ['title' => $title]);
                    ep_entity_i18n_save_from_post($pdo, 'course', $id, $_POST);
                    header('Location: courses.php');
                    exit;
                }
            }
        } else {
            $pdo->prepare('INSERT INTO courses (title, slug, category, description, image, price, lessons_count, students_count, level_label, duration_label, language_label, certificate_label, certificate_enabled, quizzes_count, rating, teacher_id, status, sort_order, meta_title, meta_description, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$title, $slug, $category, $description, $image, $price, $lessons_count, $students_count, $level_label, $duration_label, $language_label, $certificate_label, $certificate_enabled, $quizzes_count, $rating, $teacher_id, $status, $sort_order, $meta_title, $meta_description, $now]);
            $newId = (int) $pdo->lastInsertId();
            cms_log($pdo, 'create', 'course', $newId, ['title' => $title]);
            ep_entity_i18n_save_from_post($pdo, 'course', $newId, $_POST);
            header('Location: courses.php');
            exit;
        }
    }
    $item = array_merge(
        compact('title', 'slug', 'category', 'description', 'image', 'price', 'lessons_count', 'students_count', 'level_label', 'duration_label', 'language_label', 'certificate_label', 'certificate_enabled', 'quizzes_count', 'rating', 'teacher_id', 'status', 'sort_order', 'meta_title', 'meta_description'),
        $id && isset($item['id']) ? ['id' => $item['id'], 'created_at' => $item['created_at'] ?? '', 'updated_at' => $item['updated_at'] ?? ''] : []
    );
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id ? 'Редактировать' : ($dupFrom ? 'Новый курс (копия)' : 'Добавить') ?> курс — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <main class="admin-main">
        <div class="page-title">
            <h1><?= $id ? 'Редактировать курс' : ($dupFrom ? 'Новый курс (копия)' : 'Новый курс') ?></h1>
            <a href="courses.php" class="btn btn-secondary">Назад</a>
        </div>
        <?php if ($id && $item): ?>
        <div class="admin-bulk-bar" style="justify-content:flex-start;gap:10px;">
            <a class="btn btn-secondary" href="course-overview.php?course_id=<?= (int) $id ?>">Обзор</a>
            <a class="btn btn-secondary" href="course-curriculum.php?course_id=<?= (int) $id ?>">Программа</a>
            <a class="btn btn-secondary" href="course-reviews.php?course_id=<?= (int) $id ?>">Отзывы</a>
            <a class="btn btn-secondary" href="course-comments.php?course_id=<?= (int) $id ?>">Комментарии</a>
            <a class="btn btn-secondary" href="/course.php?slug=<?= urlencode((string) ($item['slug'] ?? '')) ?>" target="_blank" rel="noopener">Открыть на сайте</a>
        </div>
        <?php endif; ?>
        <?php foreach ($errors as $e): ?><p class="admin-flash admin-flash--err"><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
        <form method="post" id="course-edit-form" class="js-admin-form-unsaved">
            <div class="admin-edit-split">
                <div class="admin-edit-split__main">
            <div class="form-group">
                <label for="course-in-title">Название *</label>
                <input id="course-in-title" type="text" name="title" value="<?= htmlspecialchars($item['title'] ?? '') ?>" required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="course-in-slug">Slug (URL)</label>
                <input id="course-in-slug" type="text" name="slug" value="<?= htmlspecialchars($item['slug'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="course-in-category">Категория (напр. development, UX/UI)</label>
                <input id="course-in-category" type="text" name="category" value="<?= htmlspecialchars($item['category'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="course-in-description">Описание</label>
                <textarea id="course-in-description" name="description"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="course-in-image">Обложка (путь)</label>
                <input id="course-in-image" type="text" name="image" value="<?= htmlspecialchars($item['image'] ?? '') ?>" autocomplete="off">
            </div>
            <?php $media_picker_input_id = 'course-in-image'; include __DIR__ . '/partials/media-picker-cms.php'; ?>
            <div class="form-group">
                <label for="course-in-price">Цена (напр. $65)</label>
                <input id="course-in-price" type="text" name="price" value="<?= htmlspecialchars($item['price'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="course-in-lessons">Уроков</label>
                <input id="course-in-lessons" type="number" name="lessons_count" value="<?= (int) ($item['lessons_count'] ?? 0) ?>">
            </div>
            <div class="form-group">
                <label for="course-in-students">Студентов</label>
                <input id="course-in-students" type="number" name="students_count" value="<?= (int) ($item['students_count'] ?? 0) ?>">
            </div>
            <div class="form-group">
                <label for="course-in-level">Уровень (напр. Beginner)</label>
                <input id="course-in-level" type="text" name="level_label" value="<?= htmlspecialchars((string) ($item['level_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="course-in-duration">Длительность (напр. 10 Weeks)</label>
                <input id="course-in-duration" type="text" name="duration_label" value="<?= htmlspecialchars((string) ($item['duration_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="course-in-language">Язык (напр. English)</label>
                <input id="course-in-language" type="text" name="language_label" value="<?= htmlspecialchars((string) ($item['language_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="course-in-certificate-enabled">Сертификат</label>
                <?php $certEnabled = $item['certificate_enabled'] ?? null; ?>
                <select id="course-in-certificate-enabled" name="certificate_enabled">
                    <option value="" <?= ($certEnabled === null || $certEnabled === '') ? 'selected' : '' ?>>— не задано —</option>
                    <option value="1" <?= ((string)$certEnabled === '1') ? 'selected' : '' ?>>Да</option>
                    <option value="0" <?= ((string)$certEnabled === '0') ? 'selected' : '' ?>>Нет</option>
                </select>
                <div style="margin-top:8px;">
                    <label for="course-in-certificate" style="display:block;font-size:12px;opacity:0.85;">(необязательно) Текст для совместимости</label>
                    <input id="course-in-certificate" type="text" name="certificate_label" value="<?= htmlspecialchars((string) ($item['certificate_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                </div>
            </div>
            <div class="form-group">
                <label for="course-in-quizzes">Квизов</label>
                <input id="course-in-quizzes" type="number" name="quizzes_count" value="<?= (int) ($item['quizzes_count'] ?? 0) ?>">
            </div>
            <div class="form-group">
                <label for="course-in-rating">Рейтинг</label>
                <input id="course-in-rating" type="text" name="rating" value="<?= htmlspecialchars($item['rating'] ?? '4.5') ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="course-in-teacher">Преподаватель</label>
                <select id="course-in-teacher" name="teacher_id">
                    <option value="">— не выбран —</option>
                    <?php foreach ($teachers as $t): ?>
                    <?php $tLabel = trim(($t['name'] ?? '') . ' ' . ($t['surname'] ?? '')); ?>
                    <option value="<?= (int) $t['id'] ?>" data-teacher-label="<?= htmlspecialchars($tLabel, ENT_QUOTES, 'UTF-8') ?>" <?= (isset($item['teacher_id']) && (int) $item['teacher_id'] === (int) $t['id']) ? 'selected' : '' ?>><?= htmlspecialchars($tLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="course-in-status">Статус</label>
                <select id="course-in-status" name="status">
                    <option value="active" <?= ($item['status'] ?? '') === 'active' ? 'selected' : '' ?>>Активен</option>
                    <option value="hidden" <?= ($item['status'] ?? '') === 'hidden' ? 'selected' : '' ?>>Скрыт</option>
                    <option value="draft" <?= ($item['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Черновик</option>
                </select>
            </div>
            <div class="form-group">
                <label for="course-in-sort">Порядок</label>
                <input id="course-in-sort" type="number" name="sort_order" value="<?= (int) ($item['sort_order'] ?? 0) ?>">
            </div>
            <?php
            $seo_id_prefix = 'course';
            $seo_legend = 'SEO';
            $seo_hint = 'По умолчанию в поиск попадут название и описание курса.';
            include __DIR__ . '/partials/form-seo.php';
            ?>
            <?php if ($id && $item): ?>
            <input type="hidden" name="lock_updated_at" value="<?= htmlspecialchars(cms_row_lock_value($item), ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
                </div>
                <aside class="admin-edit-split__aside" aria-label="Превью страницы курса">
                    <p class="admin-preview-aside-caption">Превью</p>
                    <p class="admin-preview-aside-hint">Шапка страницы курса на сайте.</p>
                    <div class="admin-preview-panel">
                        <div class="admin-preview-pagehero">
                            <p id="course-pv-category" class="admin-preview-pagehero__suptext"></p>
                            <h2 id="course-pv-title" class="admin-preview-pagehero__title"></h2>
                            <p id="course-pv-teacher" class="admin-preview-pagehero__meta"></p>
                            <p id="course-pv-extra" class="admin-preview-pagehero__meta" style="margin-top:0.35rem;font-size:0.72rem;opacity:0.85;"></p>
                            <div id="course-pv-cover-wrap" style="display:none;margin-top:0.65rem;">
                                <img id="course-pv-cover" src="" alt="" class="admin-preview-blog__img" style="max-height:90px;margin-bottom:0;">
                            </div>
                            <div id="course-pv-desc" class="admin-preview-pagehero__text"></div>
                        </div>
                    </div>
                </aside>
            </div>
            <?php
            $i18n_entity_type = 'course';
            $i18n_entity_id = (int) ($item['id'] ?? $id);
            include __DIR__ . '/partials/form-entity-i18n.php';
            ?>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Сохранить</button>
                <a href="courses.php" class="btn btn-secondary">Отмена</a>
            </div>
        </form>
    </main>
    <script>
    (function () {
        function selectedTeacherLabel() {
            var sel = document.getElementById('course-in-teacher');
            if (!sel || sel.selectedIndex < 0) return '';
            var opt = sel.options[sel.selectedIndex];
            return (opt && opt.getAttribute('data-teacher-label')) || (opt ? opt.textContent : '') || '';
        }
        function sync() {
            var title = document.getElementById('course-in-title');
            var cat = document.getElementById('course-in-category');
            var desc = document.getElementById('course-in-description');
            var image = document.getElementById('course-in-image');
            var price = document.getElementById('course-in-price');
            var lessons = document.getElementById('course-in-lessons');
            var students = document.getElementById('course-in-students');
            var rating = document.getElementById('course-in-rating');
            var status = document.getElementById('course-in-status');
            document.getElementById('course-pv-category').textContent = (cat && cat.value) ? cat.value : '';
            document.getElementById('course-pv-title').textContent = (title && title.value.trim()) ? title.value.trim() : '—';
            var t = selectedTeacherLabel();
            document.getElementById('course-pv-teacher').textContent = t ? ('Преподаватель: ' + t) : '';
            var bits = [];
            if (price && price.value.trim()) bits.push(price.value.trim());
            if (lessons && lessons.value) bits.push(lessons.value + ' ур.');
            if (students && students.value) bits.push(students.value + ' ст.');
            if (rating && rating.value.trim()) bits.push('★ ' + rating.value.trim());
            if (status && status.value) bits.push(status.value);
            document.getElementById('course-pv-extra').textContent = bits.join(' · ');
            var d = (desc && desc.value) ? desc.value.replace(/\s+/g, ' ').trim() : '';
            if (d.length > 400) d = d.slice(0, 400) + '…';
            document.getElementById('course-pv-desc').textContent = d || '(описание)';
            var wrap = document.getElementById('course-pv-cover-wrap');
            var im = document.getElementById('course-pv-cover');
            var path = (image && image.value.trim()) ? image.value.trim() : '';
            if (path && wrap && im) {
                im.src = path.indexOf('http') === 0 ? path : ('/' + path.replace(/^\//, ''));
                wrap.style.display = 'block';
            } else if (wrap) {
                wrap.style.display = 'none';
            }
        }
        ['course-in-title', 'course-in-category', 'course-in-description', 'course-in-image', 'course-in-price', 'course-in-lessons', 'course-in-students', 'course-in-rating', 'course-in-teacher', 'course-in-status'].forEach(function (id) {
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
