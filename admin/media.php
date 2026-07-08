<?php
require_once __DIR__ . '/auth.php';
require_editor_tools();
$adminNavActive = 'media';

$message = '';
$error = '';
$uploadDir = DOC_ROOT . '/uploads/cms';
$uploadUrl = '/uploads/cms';

if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    $error = 'Не удалось создать каталог uploads/cms.';
}

function cms_media_optimize_image_upload(string $srcPath, string $destPath, string $ext): bool
{
    if (!function_exists('getimagesize')) return false;
    $info = @getimagesize($srcPath);
    if (!$info) return false;
    $width = (int) ($info[0] ?? 0);
    $height = (int) ($info[1] ?? 0);
    if ($width <= 0 || $height <= 0) return false;

    $maxW = 2000;
    $scale = $width > $maxW ? ($maxW / $width) : 1.0;
    $newW = max(1, (int) round($width * $scale));
    $newH = max(1, (int) round($height * $scale));
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

function cms_media_store_upload(array $file, string $uploadDir, string $uploadUrl): array
{
    global $pdo;
    $tmp = (string) ($file['tmp_name'] ?? '');
    $orig = (string) ($file['name'] ?? '');
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed, true)) return ['ok' => false, 'err' => 'Допустимы: jpg, png, webp, gif.'];
    if ((int) ($file['size'] ?? 0) > 12 * 1024 * 1024) return ['ok' => false, 'err' => 'Файл больше 12 МБ.'];
    if ($tmp === '' || !is_uploaded_file($tmp)) return ['ok' => false, 'err' => 'Ошибка загрузки.'];

    $base = preg_replace('/[^a-z0-9_-]+/i', '-', pathinfo($orig, PATHINFO_FILENAME));
    $base = trim($base, '-') ?: 'file';
    $fname = $base . '-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(8)), 0, 8) . '.' . $ext;
    $dest = $uploadDir . '/' . $fname;

    $optimized = cms_media_optimize_image_upload($tmp, $dest, $ext);
    if (!$optimized && !@move_uploaded_file($tmp, $dest)) {
        return ['ok' => false, 'err' => 'Не удалось сохранить файл.'];
    }

    $rel = $uploadUrl . '/' . $fname;
    $mime = (string) (@mime_content_type($dest) ?: 'image/' . $ext);
    $size = (int) (@filesize($dest) ?: 0);
    $uid = (int) ($_SESSION['user']['id'] ?? 0);
    try {
        $pdo->prepare('INSERT INTO cms_media (path, original_name, mime, size_bytes, uploaded_by) VALUES (?,?,?,?,?)')
            ->execute([$rel, $orig, $mime, $size, $uid > 0 ? $uid : null]);
        $id = (int) $pdo->lastInsertId();
        cms_log($pdo, 'media_upload', 'media', $id, ['path' => $rel]);
        return ['ok' => true, 'id' => $id, 'path' => $rel, 'original_name' => $orig, 'mime' => $mime, 'size_bytes' => $size];
    } catch (Throwable $e) {
        @unlink($dest);
        return ['ok' => false, 'err' => 'Не удалось записать в базу (возможно, дубликат пути).'];
    }
}

function cms_media_load_gd(string $disk, string $ext)
{
    $ext = strtolower($ext);
    if (in_array($ext, ['jpg', 'jpeg'], true) && function_exists('imagecreatefromjpeg')) return @imagecreatefromjpeg($disk);
    if ($ext === 'png' && function_exists('imagecreatefrompng')) return @imagecreatefrompng($disk);
    if ($ext === 'webp' && function_exists('imagecreatefromwebp')) return @imagecreatefromwebp($disk);
    if ($ext === 'gif' && function_exists('imagecreatefromgif')) return @imagecreatefromgif($disk);
    return null;
}

function cms_media_save_webp_copy(string $srcDisk, string $destDisk): bool
{
    if (!function_exists('imagewebp')) return false;
    $ext = strtolower(pathinfo($srcDisk, PATHINFO_EXTENSION));
    $src = cms_media_load_gd($srcDisk, $ext);
    if (!$src) return false;
    $w = imagesx($src);
    $h = imagesy($src);
    $dst = imagecreatetruecolor($w, $h);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $w, $h, $transparent);
    imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
    $ok = @imagewebp($dst, $destDisk, 82);
    imagedestroy($dst);
    imagedestroy($src);
    return (bool) $ok;
}

function cms_media_rotate_copy(string $srcDisk, string $destDisk, int $deg): bool
{
    if (!function_exists('imagerotate')) return false;
    $ext = strtolower(pathinfo($srcDisk, PATHINFO_EXTENSION));
    $src = cms_media_load_gd($srcDisk, $ext);
    if (!$src) return false;
    $deg = $deg % 360;
    $rot = @imagerotate($src, $deg, 0);
    if (!$rot) {
        imagedestroy($src);
        return false;
    }
    $ok = @imagewebp($rot, $destDisk, 82);
    imagedestroy($rot);
    imagedestroy($src);
    return (bool) $ok;
}

function cms_media_crop_square_copy(string $srcDisk, string $destDisk): bool
{
    if (!function_exists('imagecrop')) return false;
    $ext = strtolower(pathinfo($srcDisk, PATHINFO_EXTENSION));
    $src = cms_media_load_gd($srcDisk, $ext);
    if (!$src) return false;
    $w = imagesx($src);
    $h = imagesy($src);
    $s = min($w, $h);
    $x = (int) floor(($w - $s) / 2);
    $y = (int) floor(($h - $s) / 2);
    $cropped = @imagecrop($src, ['x' => $x, 'y' => $y, 'width' => $s, 'height' => $s]);
    if (!$cropped) {
        imagedestroy($src);
        return false;
    }
    $ok = @imagewebp($cropped, $destDisk, 82);
    imagedestroy($cropped);
    imagedestroy($src);
    return (bool) $ok;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'upload_json') {
    header('Content-Type: application/json; charset=utf-8');
    $out = ['ok' => false, 'items' => [], 'errors' => []];
    if (!is_dir($uploadDir)) {
        $out['errors'][] = 'Каталог uploads/cms недоступен.';
        echo json_encode($out);
        exit;
    }
    $files = [];
    if (!empty($_FILES['files'])) {
        $f = $_FILES['files'];
        $names = (array) ($f['name'] ?? []);
        foreach ($names as $i => $_) {
            $files[] = [
                'name' => $f['name'][$i] ?? '',
                'type' => $f['type'][$i] ?? '',
                'tmp_name' => $f['tmp_name'][$i] ?? '',
                'error' => $f['error'][$i] ?? 0,
                'size' => $f['size'][$i] ?? 0,
            ];
        }
    } elseif (!empty($_FILES['file'])) {
        $files[] = $_FILES['file'];
    }
    if (!$files) {
        $out['errors'][] = 'Файлы не переданы.';
        echo json_encode($out);
        exit;
    }
    foreach ($files as $file) {
        if ((int) ($file['error'] ?? 0) !== 0) {
            $out['errors'][] = 'Ошибка загрузки файла.';
            continue;
        }
        $res = cms_media_store_upload($file, $uploadDir, $uploadUrl);
        if (!empty($res['ok'])) {
            $out['items'][] = $res;
        } else {
            $out['errors'][] = (string) ($res['err'] ?? 'Ошибка загрузки.');
        }
    }
    $out['ok'] = (bool) $out['items'];
    echo json_encode($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'upload' && is_dir($uploadDir)) {
    $files = [];
    if (!empty($_FILES['files'])) {
        $f = $_FILES['files'];
        $names = (array) ($f['name'] ?? []);
        foreach ($names as $i => $_) {
            $files[] = [
                'name' => $f['name'][$i] ?? '',
                'type' => $f['type'][$i] ?? '',
                'tmp_name' => $f['tmp_name'][$i] ?? '',
                'error' => $f['error'][$i] ?? 0,
                'size' => $f['size'][$i] ?? 0,
            ];
        }
    } elseif (!empty($_FILES['file'])) {
        $files[] = $_FILES['file'];
    }
    if (!$files) {
        $error = 'Выберите файл.';
    } else {
        $okCount = 0;
        foreach ($files as $file) {
            if ((int) ($file['error'] ?? 0) !== 0) {
                $error = 'Ошибка загрузки файла.';
                continue;
            }
            $res = cms_media_store_upload($file, $uploadDir, $uploadUrl);
            if (!empty($res['ok'])) {
                $okCount++;
            } else {
                $error = (string) ($res['err'] ?? 'Ошибка загрузки.');
            }
        }
        if ($okCount > 0 && $error === '') {
            $message = 'Файлы загружены. Можно копировать URL.';
        } elseif ($okCount > 0 && $error !== '') {
            $message = 'Часть файлов загружена.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'edit_quick' && ctype_digit((string) ($_POST['id'] ?? ''))) {
    $mid = (int) $_POST['id'];
    $op = (string) ($_POST['op'] ?? '');
    $st = $pdo->prepare('SELECT * FROM cms_media WHERE id = ? LIMIT 1');
    $st->execute([$mid]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $error = 'Файл не найден.';
    } else {
        $path = (string) ($row['path'] ?? '');
        if ($path === '' || strpos($path, '/uploads/cms/') !== 0) {
            $error = 'Редактор поддерживает только файлы из /uploads/cms/.';
        } else {
            $srcDisk = DOC_ROOT . str_replace('/', DIRECTORY_SEPARATOR, $path);
            if (!is_file($srcDisk)) {
                $error = 'Файл на диске не найден.';
            } else {
                $base = preg_replace('/[^a-z0-9_-]+/i', '-', pathinfo(basename($srcDisk), PATHINFO_FILENAME));
                $base = trim($base, '-') ?: 'image';
                $fname = $base . '-' . $op . '-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(8)), 0, 8) . '.webp';
                $destDisk = $uploadDir . '/' . $fname;
                $ok = false;
                if ($op === 'webp') $ok = cms_media_save_webp_copy($srcDisk, $destDisk);
                if ($op === 'rotate_l') $ok = cms_media_rotate_copy($srcDisk, $destDisk, 90);
                if ($op === 'rotate_r') $ok = cms_media_rotate_copy($srcDisk, $destDisk, 270);
                if ($op === 'square') $ok = cms_media_crop_square_copy($srcDisk, $destDisk);
                if ($op === 'optimize') $ok = cms_media_save_webp_copy($srcDisk, $destDisk);
                if (!$ok) {
                    $error = 'Не удалось обработать изображение (GD/WEBP могут быть недоступны на сервере).';
                    @unlink($destDisk);
                } else {
                    $rel = $uploadUrl . '/' . $fname;
                    $mime = (string) (@mime_content_type($destDisk) ?: 'image/webp');
                    $size = (int) (@filesize($destDisk) ?: 0);
                    $uid = (int) ($_SESSION['user']['id'] ?? 0);
                    $pdo->prepare('INSERT INTO cms_media (path, original_name, mime, size_bytes, uploaded_by) VALUES (?,?,?,?,?)')
                        ->execute([$rel, (string) ($row['original_name'] ?? basename($path)), $mime, $size, $uid > 0 ? $uid : null]);
                    $newId = (int) $pdo->lastInsertId();
                    cms_log($pdo, 'media_edit_quick', 'media', $newId, ['op' => $op, 'src_id' => $mid, 'path' => $rel]);
                    $message = 'Готово: создана новая версия файла.';
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'delete' && ctype_digit((string) ($_POST['id'] ?? ''))) {
    $mid = (int) $_POST['id'];
    $st = $pdo->prepare('SELECT * FROM cms_media WHERE id = ? LIMIT 1');
    $st->execute([$mid]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $path = (string) ($row['path'] ?? '');
        if ($path !== '' && strpos($path, '/uploads/cms/') === 0) {
            $disk = DOC_ROOT . str_replace('/', DIRECTORY_SEPARATOR, $path);
            if (is_file($disk)) {
                @unlink($disk);
            }
        }
        $pdo->prepare('DELETE FROM cms_media WHERE id = ?')->execute([$mid]);
        cms_log($pdo, 'media_delete', 'media', $mid, ['path' => $path]);
        $message = 'Файл удалён.';
    }
}

$page = admin_page_param();
$perPage = admin_per_page_param(24);
$q = trim((string) ($_GET['q'] ?? ''));
$where = '';
if ($q !== '') {
    $safe = str_replace(['%', '_'], ['\\%', '\\_'], $q);
    $like = '%' . $safe . '%';
    $where = "WHERE path LIKE " . $pdo->quote($like) . " ESCAPE '\\' OR original_name LIKE " . $pdo->quote($like) . " ESCAPE '\\'";
}
$total = (int) $pdo->query('SELECT COUNT(*) FROM cms_media ' . $where)->fetchColumn();
$off = ($page - 1) * $perPage;
$list = $pdo->query('SELECT * FROM cms_media ' . $where . ' ORDER BY id DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $off)->fetchAll(PDO::FETCH_ASSOC);
$pagerBase = ['per' => $perPage] + ($q !== '' ? ['q' => $q] : []);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Медиатека — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <main class="admin-main">
        <div class="page-title">
            <h1>Медиатека</h1>
        </div>
        <?php if ($message): ?><p class="admin-flash admin-flash--ok"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="admin-flash admin-flash--err"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <fieldset class="admin-fieldset">
            <legend>Загрузить файл</legend>
            <form method="post" enctype="multipart/form-data" class="form-row form-row--align-end admin-upload-row js-media-upload-form">
                <input type="hidden" name="action" value="upload">
                <div class="form-group form-group--flush">
                    <label>Изображения (до 12 МБ каждое)</label>
                    <input type="file" name="files[]" accept=".jpg,.jpeg,.png,.webp,.gif" multiple required class="admin-file-input js-media-file-input">
                    <div class="admin-dropzone js-media-dropzone" tabindex="0" role="button" aria-label="Перетащите файлы сюда">
                        <div class="admin-dropzone__title">Перетащите файлы сюда</div>
                        <div class="admin-dropzone__sub">или</div>
                        <div class="admin-dropzone__row">
                            <button type="button" class="btn btn-secondary js-media-pick">Выбрать файлы</button>
                            <button type="submit" class="btn btn-primary js-media-submit">Загрузить</button>
                        </div>
                        <div class="admin-dropzone__meta js-media-queue"></div>
                        <div class="admin-progress js-media-progress" style="display:none;"><span class="admin-progress__bar" style="width:0%"></span></div>
                        <div class="admin-muted js-media-upload-status" style="margin-top:0.45rem;"></div>
                    </div>
                </div>
            </form>
        </fieldset>

        <h2 class="admin-section__title admin-section__title--follow">Файлы</h2>
        <div class="admin-media-toolbar">
        <form method="get" class="admin-bulk-bar admin-media-toolbar__row" style="justify-content:space-between;">
            <div class="admin-bulk-bar__label">
                <span class="admin-muted">Поиск</span>
                <input type="text" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="имя файла или путь" style="min-width:260px;">
                <input type="hidden" name="per" value="<?= (int) $perPage ?>">
                <button class="btn btn-secondary" type="submit">Найти</button>
                <?php if ($q !== ''): ?><a class="btn btn-secondary" href="media.php?per=<?= (int) $perPage ?>">Сбросить</a><?php endif; ?>
            </div>
            <div class="admin-muted">Совет: кнопка «Копировать URL» берёт полный URL.</div>
        </form>
        <div class="admin-media-toolbar__row">
            <?= admin_pager_html($page, $total, $perPage, $pagerBase, 'media.php') ?>
        </div>
        </div>

        <div class="media-grid media-grid--wide">
            <?php foreach ($list as $m): ?>
            <?php
                $pRaw = (string) ($m['path'] ?? '');
                $p = htmlspecialchars($pRaw, ENT_QUOTES, 'UTF-8');
                $file = basename($pRaw);
                $label = (string) ($m['original_name'] ?? $file);
                $size = (int) ($m['size_bytes'] ?? 0);
                $sizeLabel = $size > 0 ? (round($size / 1024) . ' KB') : '';
            ?>
            <div class="media-item media-item--modern" data-media-id="<?= (int) $m['id'] ?>" data-media-url="<?= $p ?>" data-media-name="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
                <button type="button" class="media-item__thumb js-media-open" aria-label="Открыть изображение">
                    <img src="<?= $p ?>" alt="" loading="lazy">
                </button>
                <div class="media-item__meta">
                    <div class="media-item__name" title="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($file) ?></div>
                    <div class="media-item__sub">
                        <?php if ($sizeLabel !== ''): ?><span><?= htmlspecialchars($sizeLabel) ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="media-item__hover-actions">
                    <button type="button" class="btn btn-secondary btn-small js-media-open">Открыть</button>
                    <button type="button" class="btn btn-secondary btn-small js-copy-url" data-url="<?= $p ?>">URL</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (!$list): ?>
        <p class="admin-empty">Пока нет загрузок в медиатеку.</p>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . '/_footer.php'; ?>

    <div class="admin-modal" id="media-modal" aria-hidden="true">
        <div class="admin-modal__overlay js-modal-close" tabindex="-1"></div>
        <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-label="Просмотр изображения">
            <button type="button" class="admin-modal__close js-modal-close" aria-label="Закрыть">×</button>
            <div class="admin-modal__body">
                <div class="admin-modal__preview">
                    <img id="media-modal-img" src="" alt="">
                </div>
                <div class="admin-modal__side">
                    <div class="admin-modal__title" id="media-modal-title"></div>
                    <div class="admin-modal__code">
                        <input id="media-modal-url" type="text" readonly value="">
                    </div>
                    <div class="admin-modal__actions">
                        <button type="button" class="btn btn-secondary js-copy-url" id="media-modal-copy" data-url="">Копировать URL</button>
                        <a class="btn btn-secondary" id="media-modal-open" href="#" target="_blank" rel="noopener">Открыть в новой вкладке</a>
                        <a class="btn btn-secondary" id="media-modal-download" href="#" download>Скачать</a>
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" id="media-modal-del-id" value="">
                            <button type="submit" class="btn btn-secondary" data-confirm="Удалить файл?">Удалить</button>
                        </form>
                    </div>
                    <div class="admin-modal__actions" style="margin-top:0.75rem;">
                        <form method="post" style="display:grid;gap:8px;margin:0;">
                            <input type="hidden" name="action" value="edit_quick">
                            <input type="hidden" name="id" id="media-modal-edit-id" value="">
                            <button class="btn btn-secondary" type="submit" name="op" value="webp">Сделать копию WebP</button>
                            <button class="btn btn-secondary" type="submit" name="op" value="rotate_l">Повернуть влево (копия)</button>
                            <button class="btn btn-secondary" type="submit" name="op" value="rotate_r">Повернуть вправо (копия)</button>
                            <button class="btn btn-secondary" type="submit" name="op" value="square">Кадрировать квадрат (копия)</button>
                            <button class="btn btn-secondary" type="submit" name="op" value="optimize">Оптимизировать (копия WebP)</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
