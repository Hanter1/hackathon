<?php
require_once __DIR__ . '/auth.php';
require_site_content_editor();
$adminNavActive = 'teachers';
require_once DOC_ROOT . '/include/data.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$dupFrom = isset($_GET['duplicate_from']) ? (int) $_GET['duplicate_from'] : 0;
$item = $id > 0 ? get_teacher_by_id($id) : null;
if (!$item && $dupFrom > 0) {
    $src = get_teacher_by_id($dupFrom);
    if ($src) {
        unset($src['id']);
        $src['name'] = ($src['name'] ?? 'Наставник') . ' (копия)';
        $baseSlug = slugify((string) ($src['slug'] ?? ''));
        if ($baseSlug === '') {
            $baseSlug = slugify(trim((string) ($src['name'] ?? '') . ' ' . (string) ($src['surname'] ?? '')));
        }
        $src['slug'] = ($baseSlug !== '' ? $baseSlug : 'mentor') . '-kopiya-' . date('YmdHis');
        $src['status'] = 'draft';
        $src['meta_title'] = '';
        $src['meta_description'] = '';
        $src['updated_at'] = null;
        $item = $src;
        cms_log($pdo, 'duplicate_from', 'teacher', $dupFrom, ['new_slug' => $src['slug']]);
    }
}
if ($id > 0 && !$item) {
    header('Location: teachers.php');
    exit;
}
$errors = [];
$uploadDir = DOC_ROOT . '/uploads/mentors';
$uploadUrlPrefix = '/uploads/mentors/';

function optimize_image_upload(string $srcPath, string $destPath, string $ext): bool {
    if (!function_exists('getimagesize')) return false;
    $info = @getimagesize($srcPath);
    if (!$info) return false;
    $width = (int)($info[0] ?? 0);
    $height = (int)($info[1] ?? 0);
    if ($width <= 0 || $height <= 0) return false;

    $maxW = 1600;
    $scale = $width > $maxW ? ($maxW / $width) : 1.0;
    $newW = max(1, (int)round($width * $scale));
    $newH = max(1, (int)round($height * $scale));
    $ext = strtolower($ext);

    $src = null;
    if (in_array($ext, ['jpg', 'jpeg'], true) && function_exists('imagecreatefromjpeg')) $src = @imagecreatefromjpeg($srcPath);
    if ($ext === 'png' && function_exists('imagecreatefrompng')) $src = @imagecreatefrompng($srcPath);
    if ($ext === 'webp' && function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($srcPath);
    if ($ext === 'gif' && function_exists('imagecreatefromgif')) $src = @imagecreatefromgif($srcPath);
    if (!$src) return false;

    $dst = imagecreatetruecolor($newW, $newH);
    if (in_array($ext, ['png', 'webp', 'gif'], true)) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);

    $ok = false;
    if (in_array($ext, ['jpg', 'jpeg'], true) && function_exists('imagejpeg')) $ok = @imagejpeg($dst, $destPath, 82);
    if ($ext === 'png' && function_exists('imagepng')) $ok = @imagepng($dst, $destPath, 7);
    if ($ext === 'webp' && function_exists('imagewebp')) $ok = @imagewebp($dst, $destPath, 82);
    if ($ext === 'gif' && function_exists('imagegif')) $ok = @imagegif($dst, $destPath);

    imagedestroy($dst);
    imagedestroy($src);
    return $ok;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'delete_media') {
    $file = basename((string)($_POST['file'] ?? ''));
    if ($file === '' || $file === '.' || $file === '..') {
        $errors[] = 'Некорректное имя файла.';
    } else {
        $path = $uploadDir . '/' . $file;
        if (is_file($path)) {
            if (!@unlink($path)) $errors[] = 'Не удалось удалить файл.';
        } else {
            $errors[] = 'Файл не найден.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') !== 'delete_media') {
    $name = trim($_POST['name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $slug = trim($_POST['slug'] ?? '') ?: slugify($name . ' ' . $surname);
    $role = trim($_POST['role'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $image = trim($_POST['image'] ?? '');
    $social_link = '';
    $joined_at = '';
    $meta_title = trim((string) ($_POST['meta_title'] ?? ''));
    $meta_description = trim((string) ($_POST['meta_description'] ?? ''));
    $lock_updated_at = trim((string) ($_POST['lock_updated_at'] ?? ''));
    $status = in_array($_POST['status'] ?? '', ['active', 'hidden', 'draft'], true) ? $_POST['status'] : 'active';
    $sort_order = (int) ($_POST['sort_order'] ?? 0);

    if (!$name || !$surname) {
        $errors[] = 'Укажите имя и фамилию.';
    }

    if ($slug === '') {
        $slug = 'item';
    }
    if (empty($errors)) {
        $slug = cms_unique_slug($pdo, 'teachers', $slug, $id);
    }

    // Фото можно указать ссылкой/путем, либо загрузить файлом.
    if (!empty($_FILES['photo_file']['name'] ?? '')) {
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            $errors[] = 'Не удалось создать папку для фото.';
        } else {
            $tmpPath = (string) ($_FILES['photo_file']['tmp_name'] ?? '');
            $origName = (string) ($_FILES['photo_file']['name'] ?? '');
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (!in_array($ext, $allowed, true)) {
                $errors[] = 'Допустимые форматы фото: jpg, jpeg, png, webp, gif.';
            } else {
                $fileName = slugify($name . '-' . $surname) . '-' . date('YmdHis') . '.' . $ext;
                $target = $uploadDir . '/' . $fileName;
                $optimized = optimize_image_upload($tmpPath, $target, $ext);
                if (!$optimized && !@move_uploaded_file($tmpPath, $target)) {
                    $errors[] = 'Не удалось загрузить файл.';
                } else {
                    $image = $uploadUrlPrefix . $fileName;
                }
            }
        }
    }

    if (empty($errors)) {
        $now = date('Y-m-d H:i:s');
        if ($id && $item) {
            $lockExpected = cms_row_lock_value($item);
            if ($lockExpected !== $lock_updated_at) {
                $errors[] = 'Эта карточка уже была изменена другим редактором. Обновите страницу и внесите правки заново.';
            } else {
                $st = $pdo->prepare('UPDATE teachers SET name=?, surname=?, slug=?, role=?, bio=?, image=?, social_link=?, joined_at=?, status=?, sort_order=?, meta_title=?, meta_description=?, updated_at=? WHERE id=? AND COALESCE(updated_at, created_at) = ?');
                $st->execute([$name, $surname, $slug, $role, $bio, $image, $social_link, $joined_at, $status, $sort_order, $meta_title, $meta_description, $now, $id, $lock_updated_at]);
                if ($st->rowCount() === 0) {
                    $errors[] = 'Не удалось сохранить: запись изменилась (конфликт правок). Обновите страницу.';
                } else {
                    cms_log($pdo, 'update', 'teacher', $id, ['name' => $name . ' ' . $surname]);
                    ep_entity_i18n_save_from_post($pdo, 'teacher', $id, $_POST);
                    header('Location: teachers.php');
                    exit;
                }
            }
        } else {
            $st = $pdo->prepare('INSERT INTO teachers (name, surname, slug, role, bio, image, social_link, joined_at, status, sort_order, meta_title, meta_description, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $st->execute([$name, $surname, $slug, $role, $bio, $image, $social_link, $joined_at, $status, $sort_order, $meta_title, $meta_description, $now]);
            $newId = (int) $pdo->lastInsertId();
            cms_log($pdo, 'create', 'teacher', $newId, ['name' => $name . ' ' . $surname]);
            ep_entity_i18n_save_from_post($pdo, 'teacher', $newId, $_POST);
            header('Location: teachers.php');
            exit;
        }
    }
    $item = array_merge(
        compact('name', 'surname', 'slug', 'role', 'bio', 'image', 'social_link', 'joined_at', 'status', 'sort_order', 'meta_title', 'meta_description'),
        $id && isset($item['id']) ? ['id' => $item['id'], 'created_at' => $item['created_at'] ?? '', 'updated_at' => $item['updated_at'] ?? ''] : []
    );
}

$mediaFiles = [];
if (is_dir($uploadDir)) {
    $files = glob($uploadDir . '/*.{jpg,jpeg,png,webp,gif,JPG,JPEG,PNG,WEBP,GIF}', GLOB_BRACE) ?: [];
    rsort($files);
    foreach ($files as $f) {
        $name = basename($f);
        $mediaFiles[] = $uploadUrlPrefix . $name;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id ? 'Редактировать' : 'Добавить' ?> наставника — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <main class="admin-main">
        <div class="page-title">
            <h1><?= $id ? 'Редактировать наставника' : ($dupFrom ? 'Новый наставник (копия)' : 'Новый наставник') ?></h1>
            <a href="teachers.php" class="btn btn-secondary">Назад</a>
        </div>
        <?php foreach ($errors as $e): ?><p class="admin-flash admin-flash--err"><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
        <form method="post" enctype="multipart/form-data" id="mentor-edit-form" class="js-admin-form-unsaved">
            <div class="admin-edit-split">
                <div class="admin-edit-split__main">
            <div class="form-group">
                <label for="mentor-in-name">Имя *</label>
                <input id="mentor-in-name" type="text" name="name" value="<?= htmlspecialchars($item['name'] ?? '') ?>" required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="mentor-in-surname">Фамилия *</label>
                <input id="mentor-in-surname" type="text" name="surname" value="<?= htmlspecialchars($item['surname'] ?? '') ?>" required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="mentor-in-slug">Slug (URL)</label>
                <input id="mentor-in-slug" type="text" name="slug" value="<?= htmlspecialchars($item['slug'] ?? '') ?>" placeholder="авто из имени" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="mentor-in-role">Профессиональные роли (множественное, через запятую или с новой строки)</label>
                <textarea id="mentor-in-role" name="role" placeholder="Наставник по коммуникациям, Коуч по карьере"><?= htmlspecialchars($item['role'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="mentor-in-bio">Ключевые компетенции и опыт, достижения</label>
                <textarea id="mentor-in-bio" name="bio"><?= htmlspecialchars($item['bio'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="mentor-image-input">Фото (URL или путь, напр. /images/member-icon-2.png)</label>
                <input type="text" id="mentor-image-input" name="image" value="<?= htmlspecialchars($item['image'] ?? '') ?>" autocomplete="off">
                <?php $media_picker_input_id = 'mentor-image-input'; include __DIR__ . '/partials/media-picker-cms.php'; ?>
                <div class="mentor-photo-preview-wrap">
                    <img id="mentor-photo-preview" class="mentor-photo-preview" src="<?= htmlspecialchars($item['image'] ?? '/images/member-icon-2.png') ?>" alt="">
                </div>
            </div>
            <div class="form-group">
                <label>Загрузить фото в хранилище</label>
                <input type="file" id="mentor-photo-file" name="photo_file" accept=".jpg,.jpeg,.png,.webp,.gif">
                <div id="mentor-file-meta" class="mentor-file-meta" style="display:none;"></div>
            </div>
            <div class="form-group">
                <label>Хранилище фото наставников</label>
                <?php if (!empty($mediaFiles)): ?>
                    <input type="text" id="media-search-input" class="media-search-input" placeholder="Поиск по имени файла...">
                    <div class="media-grid">
                        <?php foreach ($mediaFiles as $url): ?>
                            <div class="media-item" data-file-name="<?= htmlspecialchars(mb_strtolower((string)basename($url), 'UTF-8')) ?>">
                                <button type="button" class="media-item__pick js-pick-media" data-url="<?= htmlspecialchars($url) ?>">
                                    <img src="<?= htmlspecialchars($url) ?>" alt="">
                                    <span><?= htmlspecialchars(basename($url)) ?></span>
                                </button>
                                <form method="post" class="media-item__delete-form" onsubmit="return confirm('Удалить файл из хранилища?');">
                                    <input type="hidden" name="action" value="delete_media">
                                    <input type="hidden" name="file" value="<?= htmlspecialchars(basename($url)) ?>">
                                    <button type="submit" class="btn btn-secondary btn-small">Удалить</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="admin-muted">В хранилище пока нет фото. Загрузите первый файл.</p>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Статус</label>
                <select name="status">
                    <option value="active" <?= ($item['status'] ?? '') === 'active' ? 'selected' : '' ?>>Активен</option>
                    <option value="hidden" <?= ($item['status'] ?? '') === 'hidden' ? 'selected' : '' ?>>Скрыт</option>
                    <option value="draft" <?= ($item['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Черновик</option>
                </select>
            </div>
            <div class="form-group">
                <label for="mentor-in-sort">Порядок (0 = по умолчанию)</label>
                <input id="mentor-in-sort" type="number" name="sort_order" value="<?= (int) ($item['sort_order'] ?? 0) ?>">
            </div>
            <?php
            $seo_id_prefix = 'mentor';
            $seo_legend = 'SEO (поиск и соцсети)';
            $seo_hint = 'Пустые поля: для заголовка и описания страницы подставятся имя и текст из карточки.';
            include __DIR__ . '/partials/form-seo.php';
            ?>
            <?php if ($id && $item): ?>
            <input type="hidden" name="lock_updated_at" value="<?= htmlspecialchars(cms_row_lock_value($item), ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
                </div>
                <aside class="admin-edit-split__aside" aria-label="Превью карточки наставника">
                    <p class="admin-preview-aside-caption">Превью</p>
                    <p class="admin-preview-aside-hint">Как на странице профиля наставника (упрощённо).</p>
                    <div class="admin-preview-panel">
                        <div class="admin-preview-mentor">
                            <img id="mentor-live-pv-img" class="admin-preview-mentor__photo" src="<?= htmlspecialchars($item['image'] ?? '/images/member-icon-2.png') ?>" alt="">
                            <div class="admin-preview-mentor__body">
                                <p id="mentor-pv-role-primary" class="admin-preview-mentor__role"></p>
                                <h2 id="mentor-pv-name" class="admin-preview-mentor__name"></h2>
                                <p id="mentor-pv-roles2" class="admin-preview-mentor__roles2"></p>
                                <p id="mentor-pv-bio" class="admin-preview-mentor__bio"></p>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
            <?php
            $i18n_entity_type = 'teacher';
            $i18n_entity_id = (int) ($item['id'] ?? $id);
            include __DIR__ . '/partials/form-entity-i18n.php';
            ?>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Сохранить</button>
                <a href="teachers.php" class="btn btn-secondary">Отмена</a>
            </div>
        </form>
    </main>
    <?php include __DIR__ . '/_footer.php'; ?>
    <script>
    (function () {
        var input = document.getElementById('mentor-image-input');
        var preview = document.getElementById('mentor-photo-preview');
        var fileInput = document.getElementById('mentor-photo-file');

        function setPreview(src) {
            if (!src) return;
            if (preview) preview.src = src;
            var live = document.getElementById('mentor-live-pv-img');
            if (live) live.src = src;
        }

        if (input && preview) {
            input.addEventListener('input', function () {
                if (input.value.trim() !== '') setPreview(input.value.trim());
                else mentorSyncLiveCard();
            });
        }

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                var file = fileInput.files && fileInput.files[0];
                if (!file) return;
                var meta = document.getElementById('mentor-file-meta');
                var reader = new FileReader();
                reader.onload = function (e) {
                    if (e.target && e.target.result) {
                        setPreview(e.target.result);
                        mentorSyncLiveCard();
                    }
                    if (meta && e.target && e.target.result) {
                        var img = new Image();
                        img.onload = function () {
                            var sizeMb = (file.size / (1024 * 1024)).toFixed(2);
                            var isLarge = file.size > 3 * 1024 * 1024 || img.width > 2000;
                            meta.style.display = 'block';
                            meta.className = 'mentor-file-meta' + (isLarge ? ' mentor-file-meta--warn' : '');
                            meta.textContent =
                                'Файл: ' + file.name +
                                ' | Размер: ' + sizeMb + ' MB' +
                                ' | Разрешение: ' + img.width + 'x' + img.height +
                                (isLarge ? ' | Рекомендуется уменьшить изображение.' : '');
                        };
                        img.src = e.target.result;
                    }
                };
                reader.readAsDataURL(file);
            });
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.js-pick-media');
            if (!btn || !input) return;
            var url = btn.getAttribute('data-url') || '';
            if (!url) return;
            input.value = url;
            setPreview(url);
            mentorSyncLiveCard();
        });

        var search = document.getElementById('media-search-input');
        if (search) {
            search.addEventListener('input', function () {
                var q = (search.value || '').trim().toLowerCase();
                var cards = document.querySelectorAll('.media-grid .media-item');
                cards.forEach(function (card) {
                    var name = card.getAttribute('data-file-name') || '';
                    card.style.display = (!q || name.indexOf(q) !== -1) ? '' : 'none';
                });
            });
        }

        function mentorParseRoles(raw) {
            var parts = (raw || '').split(/[\r\n,]+/).map(function (s) { return s.trim(); }).filter(Boolean);
            var primary = parts[0] || '';
            var rest = parts.slice(0, 3).join(' • ');
            return { primary: primary, rest: rest };
        }

        function mentorSyncLiveCard() {
            var n = document.getElementById('mentor-in-name');
            var s = document.getElementById('mentor-in-surname');
            var r = document.getElementById('mentor-in-role');
            var b = document.getElementById('mentor-in-bio');
            var imgIn = document.getElementById('mentor-image-input');
            var pvName = document.getElementById('mentor-pv-name');
            var pvR1 = document.getElementById('mentor-pv-role-primary');
            var pvR2 = document.getElementById('mentor-pv-roles2');
            var pvBio = document.getElementById('mentor-pv-bio');
            var pvImg = document.getElementById('mentor-live-pv-img');
            var full = ((n && n.value) ? n.value : '') + ' ' + ((s && s.value) ? s.value : '');
            full = full.trim();
            if (pvName) pvName.textContent = full || '—';
            var pr = mentorParseRoles(r && r.value);
            if (pvR1) pvR1.textContent = pr.primary;
            if (pvR2) pvR2.textContent = pr.rest && pr.rest !== pr.primary ? pr.rest : '';
            var bioText = (b && b.value) ? b.value.replace(/\s+/g, ' ').trim() : '';
            if (bioText.length > 320) bioText = bioText.slice(0, 320) + '…';
            if (pvBio) pvBio.textContent = bioText || '(описание появится здесь)';
            var src = (imgIn && imgIn.value.trim()) ? imgIn.value.trim() : '/images/member-icon-2.png';
            if (pvImg) pvImg.src = src;
        }

        ['mentor-in-name', 'mentor-in-surname', 'mentor-in-role', 'mentor-in-bio', 'mentor-image-input'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('input', mentorSyncLiveCard);
        });
        mentorSyncLiveCard();
    })();
    </script>
</body>
</html>
