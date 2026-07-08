<?php
require_once __DIR__ . '/auth.php';
require_site_content_editor();
$adminNavActive = 'blog';
require_once DOC_ROOT . '/include/data.php';

/**
 * @return array{title:string,excerpt:string,content:string,image:string,author_name:string,source_url:string}
 */
function admin_import_post_from_url(string $url): array
{
    $url = trim($url);
    if (!preg_match('#^https?://#i', $url)) {
        throw new RuntimeException('Укажите полный URL, начиная с http:// или https://');
    }
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 12,
            'user_agent' => 'EasyPeopleCMS/1.0 (+admin import)',
            'follow_location' => 1,
            'max_redirects' => 4,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $html = @file_get_contents($url, false, $ctx);
    if (!is_string($html) || trim($html) === '') {
        throw new RuntimeException('Не удалось загрузить страницу по ссылке.');
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML($html);
    libxml_clear_errors();
    $xp = new DOMXPath($dom);

    $pickMeta = static function (DOMXPath $xp, array $keys): string {
        foreach ($keys as $k) {
            $q = '//meta[@property="' . $k . '" or @name="' . $k . '"]/@content';
            $v = trim((string) $xp->evaluate('string(' . $q . ')'));
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    };

    $title = $pickMeta($xp, ['og:title', 'twitter:title']);
    if ($title === '') {
        $title = trim((string) $xp->evaluate('string(//title)'));
    }
    $title = preg_replace('/\s*[|—–-]\s*(РБК Тренды|RBC Trends|Новости.*)$/ui', '', (string) $title) ?: $title;
    $excerpt = $pickMeta($xp, ['og:description', 'description', 'twitter:description']);
    $image = $pickMeta($xp, ['og:image', 'twitter:image']);
    $author = $pickMeta($xp, ['article:author', 'author']);

    $content = '';
    $chunks = [];
    foreach (['//article//p', '//main//p', '//p'] as $pq) {
        $pars = $xp->query($pq);
        if (!($pars instanceof DOMNodeList)) {
            continue;
        }
        foreach ($pars as $p) {
            $txt = trim((string) $p->textContent);
            $txt = preg_replace('/\s+/u', ' ', $txt) ?: $txt;
            if (mb_strlen($txt) < 80) {
                continue;
            }
            if (preg_match('/^(подписка|главное меню|рубрики|обновлено|авторы|теги)\b/ui', $txt)) {
                continue;
            }
            $chunks[] = $txt;
            if (count($chunks) >= 14) {
                break 2;
            }
        }
    }
    if ($chunks !== []) {
        $content = implode("\n\n", $chunks);
    } else {
        $articleText = trim((string) $xp->evaluate('string(//article)'));
        if ($articleText !== '') {
            $articleText = preg_replace('/\s+/u', ' ', $articleText) ?: $articleText;
            $content = wordwrap($articleText, 600, "\n\n", true);
        }
    }
    if ($excerpt === '' && $content !== '') {
        $excerpt = mb_substr($content, 0, 320);
    }

    return [
        'title' => $title,
        'excerpt' => $excerpt,
        'content' => $content,
        'image' => $image,
        'author_name' => $author,
        'source_url' => $url,
    ];
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$dupFrom = isset($_GET['duplicate_from']) ? (int) $_GET['duplicate_from'] : 0;
$item = null;
if ($id > 0) {
    $st = $pdo->prepare('SELECT * FROM blog_posts WHERE id = ?');
    $st->execute([$id]);
    $item = $st->fetch() ?: null;
}
if (!$item && $dupFrom > 0) {
    $st = $pdo->prepare('SELECT * FROM blog_posts WHERE id = ?');
    $st->execute([$dupFrom]);
    $src = $st->fetch(PDO::FETCH_ASSOC);
    if ($src) {
        unset($src['id']);
        $src['title'] = ($src['title'] ?? 'Пост') . ' (копия)';
        $baseSlug = slugify((string) ($src['slug'] ?? $src['title']));
        $src['slug'] = $baseSlug . '-kopiya-' . date('YmdHis');
        $src['status'] = 'draft';
        $src['published_at'] = null;
        $src['meta_title'] = '';
        $src['meta_description'] = '';
        $src['updated_at'] = null;
        $item = $src;
        cms_log($pdo, 'duplicate_from', 'blog_post', $dupFrom, ['new_slug' => $src['slug']]);
    }
}
if ($id > 0 && !$item) {
    header('Location: blog.php');
    exit;
}

$errors = [];
$importFlash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '') ?: slugify($title);
    $excerpt = trim($_POST['excerpt'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $image = trim($_POST['image'] ?? '');
    $author_name = trim($_POST['author_name'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['published', 'draft'], true) ? $_POST['status'] : 'draft';
    $published_at = trim($_POST['published_at'] ?? '');
    $meta_title = trim((string) ($_POST['meta_title'] ?? ''));
    $meta_description = trim((string) ($_POST['meta_description'] ?? ''));
    $lock_updated_at = trim((string) ($_POST['lock_updated_at'] ?? ''));
    $source_url = trim((string) ($_POST['source_url'] ?? ''));

    if ($action === 'import_url') {
        $importUrl = trim((string) ($_POST['import_url'] ?? ''));
        try {
            $parsed = admin_import_post_from_url($importUrl);
            $title = trim((string) ($parsed['title'] ?? ''));
            $slug = slugify($title);
            $excerpt = trim((string) ($parsed['excerpt'] ?? ''));
            $content = trim((string) ($parsed['content'] ?? ''));
            $image = trim((string) ($parsed['image'] ?? ''));
            $author_name = trim((string) ($parsed['author_name'] ?? ''));
            $source_url = trim((string) ($parsed['source_url'] ?? $importUrl));
            if ($source_url !== '' && mb_stripos($content, $source_url) === false) {
                $content = trim($content . "\n\nИсточник: " . $source_url);
            }
            $status = 'draft';
            $published_at = '';
            $meta_title = $title;
            $meta_description = $excerpt;
            $importFlash = 'Материал по ссылке загружен в форму. Проверьте и сохраните запись.';
            cms_log($pdo, 'import_url', 'blog_post', $id > 0 ? $id : null, ['url' => $importUrl]);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    } else {
        if ($slug === '') {
            $slug = 'item';
        }
        if ($status === 'published' && !$published_at) {
            $published_at = date('Y-m-d H:i:s');
        }
        if (!$title) {
            $errors[] = 'Укажите заголовок.';
        }
        if (empty($errors)) {
            $slug = cms_unique_slug($pdo, 'blog_posts', $slug, $id);
            $now = date('Y-m-d H:i:s');
            if ($id && $item) {
                $lockExpected = cms_row_lock_value($item);
                if ($lockExpected !== $lock_updated_at) {
                    $errors[] = 'Запись уже изменена другим редактором. Обновите страницу.';
                } else {
                    $st = $pdo->prepare('UPDATE blog_posts SET title=?, slug=?, excerpt=?, content=?, image=?, author_name=?, status=?, published_at=?, meta_title=?, meta_description=?, updated_at=? WHERE id=? AND COALESCE(updated_at, created_at) = ?');
                    $st->execute([$title, $slug, $excerpt, $content, $image, $author_name, $status, $published_at ?: null, $meta_title, $meta_description, $now, $id, $lock_updated_at]);
                    if ($st->rowCount() === 0) {
                        $errors[] = 'Не удалось сохранить: конфликт правок. Обновите страницу.';
                    } else {
                        cms_log($pdo, 'update', 'blog_post', $id, ['title' => $title, 'status' => $status]);
                        ep_entity_i18n_save_from_post($pdo, 'blog_post', $id, $_POST);
                        header('Location: blog.php');
                        exit;
                    }
                }
            } else {
                $pdo->prepare('INSERT INTO blog_posts (title, slug, excerpt, content, image, author_name, status, published_at, meta_title, meta_description, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$title, $slug, $excerpt, $content, $image, $author_name, $status, $published_at ?: null, $meta_title, $meta_description, $now]);
                $newId = (int) $pdo->lastInsertId();
                cms_log($pdo, 'create', 'blog_post', $newId, ['title' => $title, 'status' => $status]);
                ep_entity_i18n_save_from_post($pdo, 'blog_post', $newId, $_POST);
                header('Location: blog.php');
                exit;
            }
        }
    }

    $item = array_merge(
        compact('title', 'slug', 'excerpt', 'content', 'image', 'author_name', 'status', 'published_at', 'meta_title', 'meta_description'),
        ['source_url' => $source_url],
        $id && isset($item['id']) ? ['id' => $item['id'], 'created_at' => $item['created_at'] ?? '', 'updated_at' => $item['updated_at'] ?? ''] : []
    );
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id ? 'Редактировать' : 'Добавить' ?> запись — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <main class="admin-main">
        <div class="page-title">
            <h1><?= $id ? 'Редактировать запись' : ($dupFrom ? 'Новая запись (копия)' : 'Новая запись') ?></h1>
            <a href="blog.php" class="btn btn-secondary">Назад</a>
        </div>
        <?php foreach ($errors as $e): ?><p class="admin-flash admin-flash--err"><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
        <?php if ($importFlash !== ''): ?><p class="admin-flash admin-flash--ok"><?= htmlspecialchars($importFlash) ?></p><?php endif; ?>
        <form method="post" class="admin-inline-form admin-form-narrow" style="margin-bottom:1rem;">
            <input type="hidden" name="action" value="import_url">
            <input type="url" name="import_url" placeholder="https://example.com/article" required style="min-width:420px;max-width:100%;" value="<?= htmlspecialchars((string) ($item['source_url'] ?? '')) ?>">
            <button type="submit" class="btn btn-secondary">Импорт по ссылке</button>
        </form>
        <form method="post" id="post-edit-form" class="js-admin-form-unsaved">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="source_url" value="<?= htmlspecialchars((string) ($item['source_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <div class="admin-edit-split">
                <div class="admin-edit-split__main">
            <div class="form-group">
                <label for="post-in-title">Заголовок *</label>
                <input id="post-in-title" type="text" name="title" value="<?= htmlspecialchars($item['title'] ?? '') ?>" required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="post-in-slug">Slug (URL)</label>
                <input id="post-in-slug" type="text" name="slug" value="<?= htmlspecialchars($item['slug'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="post-in-excerpt">Краткое описание (лид)</label>
                <textarea id="post-in-excerpt" name="excerpt"><?= htmlspecialchars($item['excerpt'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="post-in-content">Текст</label>
                <textarea id="post-in-content" name="content" class="admin-textarea--tall"><?= htmlspecialchars($item['content'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="post-in-image">Обложка (путь)</label>
                <input id="post-in-image" type="text" name="image" value="<?= htmlspecialchars($item['image'] ?? '') ?>" autocomplete="off">
            </div>
            <?php $media_picker_input_id = 'post-in-image'; include __DIR__ . '/partials/media-picker-cms.php'; ?>
            <div class="form-group">
                <label for="post-in-author">Автор</label>
                <input id="post-in-author" type="text" name="author_name" value="<?= htmlspecialchars($item['author_name'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label for="post-in-status">Статус</label>
                <select id="post-in-status" name="status">
                    <option value="draft" <?= ($item['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Черновик</option>
                    <option value="published" <?= ($item['status'] ?? '') === 'published' ? 'selected' : '' ?>>Опубликован</option>
                </select>
            </div>
            <div class="form-group">
                <label for="post-in-published">Дата публикации</label>
                <input id="post-in-published" type="datetime-local" name="published_at" value="<?= !empty($item['published_at']) ? date('Y-m-d\TH:i', strtotime($item['published_at'])) : '' ?>">
            </div>
            <?php
            $seo_id_prefix = 'post';
            $seo_legend = 'SEO';
            $seo_hint = '';
            include __DIR__ . '/partials/form-seo.php';
            ?>
            <?php if ($id && $item): ?>
            <input type="hidden" name="lock_updated_at" value="<?= htmlspecialchars(cms_row_lock_value($item), ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
                </div>
                <aside class="admin-edit-split__aside" aria-label="Превью записи блога">
                    <p class="admin-preview-aside-caption">Превью</p>
                    <p class="admin-preview-aside-hint">Вид страницы поста (текст без разметки).</p>
                    <div class="admin-preview-panel">
                        <article class="admin-preview-blog">
                            <h2 id="post-pv-title" class="admin-preview-blog__title"></h2>
                            <p id="post-pv-meta" class="admin-preview-blog__meta"></p>
                            <div id="post-pv-img-wrap" style="display:none;">
                                <img id="post-pv-img" class="admin-preview-blog__img" src="" alt="">
                            </div>
                            <p id="post-pv-body" class="admin-preview-blog__excerpt"></p>
                        </article>
                    </div>
                </aside>
            </div>
            <?php
            $i18n_entity_type = 'blog_post';
            $i18n_entity_id = (int) ($item['id'] ?? $id);
            include __DIR__ . '/partials/form-entity-i18n.php';
            ?>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Сохранить</button>
                <a href="blog.php" class="btn btn-secondary">Отмена</a>
            </div>
        </form>
    </main>
    <script>
    (function () {
        function fmtDateFromInput(v) {
            if (!v) return '';
            var d = new Date(v);
            if (isNaN(d.getTime())) return v;
            var dd = String(d.getDate()).padStart(2, '0');
            var mm = String(d.getMonth() + 1).padStart(2, '0');
            var yy = d.getFullYear();
            return dd + '.' + mm + '.' + yy;
        }
        function sync() {
            var title = document.getElementById('post-in-title');
            var excerpt = document.getElementById('post-in-excerpt');
            var content = document.getElementById('post-in-content');
            var image = document.getElementById('post-in-image');
            var author = document.getElementById('post-in-author');
            var status = document.getElementById('post-in-status');
            var pub = document.getElementById('post-in-published');
            document.getElementById('post-pv-title').textContent = (title && title.value.trim()) ? title.value.trim() : '—';
            var metaParts = [];
            var dlabel = fmtDateFromInput(pub && pub.value);
            if (dlabel) metaParts.push(dlabel);
            if (author && author.value.trim()) metaParts.push('Автор: ' + author.value.trim());
            if (status && status.value) metaParts.push(status.value);
            document.getElementById('post-pv-meta').textContent = metaParts.join(' · ');
            var ex = (excerpt && excerpt.value.trim()) ? excerpt.value.trim() : '';
            var body = (content && content.value.trim()) ? content.value.replace(/\s+/g, ' ').trim() : '';
            var show = ex || body;
            var text = ex || body;
            if (text.length > 520) text = text.slice(0, 520) + '…';
            document.getElementById('post-pv-body').textContent = show ? text : '(текст записи)';
            var wrap = document.getElementById('post-pv-img-wrap');
            var im = document.getElementById('post-pv-img');
            var path = (image && image.value.trim()) ? image.value.trim() : '';
            if (path && wrap && im) {
                im.src = path.indexOf('http') === 0 ? path : ('/' + path.replace(/^\//, ''));
                wrap.style.display = 'block';
            } else if (wrap) {
                wrap.style.display = 'none';
            }
        }
        ['post-in-title', 'post-in-excerpt', 'post-in-content', 'post-in-image', 'post-in-author', 'post-in-status', 'post-in-published'].forEach(function (id) {
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
