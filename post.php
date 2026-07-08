<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/data.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/i18n.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/seo.php';
$epUi = static fn(string $k) => htmlspecialchars(ep_t($k), ENT_QUOTES, 'UTF-8');
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if (!$slug) { header('Location: /blog.php'); exit; }
$p = get_post_by_slug($slug);
if (!$p) {
    header('HTTP/1.0 404 Not Found');
    $cmsSeoTitle = ep_t('post.not_found') . ' — Easy People';
    $cmsSeoNoindex = true;
    require_once $_SERVER['DOCUMENT_ROOT'] . '/include/header.php';
    echo '<div class="wrapper"><div class="container"><h1>' . $epUi('post.not_found') . '</h1><p><a href="/blog.php">' . $epUi('post.back_to_blog') . '</a></p></div></div>';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/include/footer.php';
    exit;
}
$date = !empty($p['published_at']) ? date('d.m.Y', strtotime($p['published_at'])) : '';
$base = cms_public_base_url();
$plainBody = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($p['content'] ?? $p['excerpt'] ?? ''))));
$ex = trim((string) ($p['excerpt'] ?? ''));
$cmsSeoTitle = cms_seo_entity_title($p, (string) $p['title']);
$cmsSeoDescription = cms_seo_entity_description($p, $ex !== '' ? $ex : ($plainBody !== '' ? $plainBody : ($cmsSeoTitle . ' — блог Easy People.')));
$cmsSeoCanonical = $base . '/post.php?slug=' . rawurlencode((string) $p['slug']);
$cmsSeoOgImage = cms_seo_abs_media_url($p['image'] ?? '');
$cmsSeoType = 'article';
$rawImage = trim((string) ($p['image'] ?? ''));
$postImageSrc = '';
if ($rawImage !== '') {
    if (preg_match('#^https?://#i', $rawImage)) {
        $postImageSrc = $rawImage;
    } else {
        $postImageSrc = '/' . ltrim($rawImage, '/');
    }
}
$bodySrc = trim((string) ($p['content'] ?: $p['excerpt']));
$bodySrc = str_replace(["\r\n", "\r"], "\n", $bodySrc);
$parts = preg_split('/\n{2,}/', $bodySrc) ?: [];
if ($parts === []) {
    $parts = [$bodySrc];
}
$parts = array_values(array_filter($parts, static function ($chunk): bool {
    $t = trim((string) $chunk);
    if ($t === '') {
        return false;
    }

    return !preg_match('/^(Источник|Крыніца|Source):\s*https?:\/\//u', $t);
}));
$blocks = [];
$toc = [];
$tocIdx = 0;
foreach ($parts as $chunk) {
    $t = trim((string) $chunk);
    if ($t === '') {
        continue;
    }
    $isHeading = false;
    if (preg_match('/^\s*(#{1,3})\s*(.+)$/u', $t, $hm)) {
        $isHeading = true;
        $t = trim((string) $hm[2]);
    } elseif (mb_strlen($t) <= 110 && !preg_match('/[.!?:;]\s*$/u', $t)) {
        // Короткая строка без завершающей пунктуации чаще всего подзаголовок.
        $isHeading = true;
    }

    if ($isHeading) {
        $tocIdx++;
        $hid = 'ep-post-h-' . $tocIdx;
        $blocks[] = ['type' => 'heading', 'text' => $t, 'id' => $hid];
        $toc[] = ['id' => $hid, 'text' => $t];
    } else {
        $blocks[] = ['type' => 'p', 'text' => $t];
    }
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/header.php';
?>
<style>
    /* Поддержка светлой темы сайта: body.body--white */
    .ep-post {
        padding: 34px 0 64px;
    }
    .ep-post__wrap {
        max-width: 1100px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: minmax(0, 1fr) 260px;
        gap: 28px;
        align-items: start;
    }
    .ep-post__main {
        min-width: 0;
        max-width: 780px;
    }
    .ep-post__side {
        position: sticky;
        top: 96px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        background: rgba(12, 12, 20, 0.72);
        backdrop-filter: blur(6px);
        padding: 14px 14px 12px;
    }
    .ep-post__side-title {
        margin: 0 0 10px;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: rgba(255, 255, 255, 0.55);
    }
    .ep-post__toc {
        margin: 0 0 12px;
        padding: 0;
        list-style: none;
        max-height: 320px;
        overflow: auto;
    }
    .ep-post__toc li + li {
        margin-top: 6px;
    }
    .ep-post__toc a {
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        font-size: 13px;
        line-height: 1.35;
    }
    .ep-post__toc a:hover {
        color: #f9d442;
    }
    .ep-post__actions {
        display: grid;
        gap: 8px;
    }
    .ep-post__action {
        border: 1px solid rgba(255,255,255,0.14);
        border-radius: 10px;
        background: rgba(255,255,255,0.03);
        color: #fff;
        font-size: 13px;
        padding: 9px 10px;
        text-align: left;
        cursor: pointer;
        text-decoration: none;
    }
    .ep-post__action:hover {
        border-color: rgba(249, 212, 66, 0.5);
        color: #f9d442;
    }
    .ep-post__back {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 18px;
        font-size: 14px;
        color: #6d9985;
        text-decoration: none;
    }
    .ep-post__back:hover {
        color: #7fb59c;
    }
    .ep-post__title {
        margin: 0 0 12px;
        font-size: clamp(32px, 5vw, 56px);
        line-height: 1.08;
        letter-spacing: -0.01em;
        color: #fff;
    }
    .ep-post__meta {
        margin: 0 0 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 8px 16px;
        font-size: 14px;
        color: rgba(255, 255, 255, 0.66);
    }
    .ep-post__cover {
        margin: 0 0 26px;
        border-radius: 20px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.04);
    }
    .ep-post__cover img {
        display: block;
        width: 100%;
        max-height: 520px;
        object-fit: cover;
    }
    .ep-post__content {
        color: rgba(255, 255, 255, 0.9);
        font-size: 18px;
        line-height: 1.76;
        word-break: break-word;
    }
    .ep-post__content p {
        margin: 0 0 1.1em;
    }
    .ep-post__content h2 {
        margin: 1.5em 0 0.45em;
        font-size: clamp(22px, 2.8vw, 30px);
        line-height: 1.25;
        color: #fff;
    }
    .ep-post__content a {
        color: #f9d442;
        text-decoration: underline;
        text-underline-offset: 3px;
    }
    .ep-post__source {
        margin-top: 26px;
        padding: 14px 16px;
        border-radius: 14px;
        border: 1px solid rgba(109, 153, 133, 0.35);
        background: rgba(109, 153, 133, 0.14);
        font-size: 14px;
        color: rgba(255, 255, 255, 0.82);
    }
    .ep-post__source a {
        color: #dff6eb;
        text-decoration: underline;
        text-underline-offset: 2px;
    }
    @media (max-width: 760px) {
        .ep-post__wrap {
            grid-template-columns: 1fr;
            gap: 18px;
        }
        .ep-post__side {
            position: static;
            order: 2;
        }
        .ep-post__main {
            max-width: 100%;
        }
        .ep-post {
            padding-top: 22px;
        }
        .ep-post__content {
            font-size: 16px;
            line-height: 1.7;
        }
    }

    /* Light theme overrides */
    body.body--white .ep-post__title { color: #0e1218; }
    body.body--white .ep-post__meta { color: rgba(14, 18, 24, 0.62); }
    body.body--white .ep-post__back { color: #2f6f58; }
    body.body--white .ep-post__back:hover { color: #225847; }
    body.body--white .ep-post__content { color: rgba(14, 18, 24, 0.88); }
    body.body--white .ep-post__content h2 { color: #0e1218; }
    body.body--white .ep-post__content a { color: #a36b00; }
    body.body--white .ep-post__cover { border-color: rgba(14, 18, 24, 0.08); background: rgba(14, 18, 24, 0.02); }
    body.body--white .ep-post__side { border-color: rgba(14, 18, 24, 0.10); background: rgba(255, 255, 255, 0.82); }
    body.body--white .ep-post__side-title { color: rgba(14, 18, 24, 0.55); }
    body.body--white .ep-post__toc a { color: rgba(14, 18, 24, 0.78); }
    body.body--white .ep-post__toc a:hover { color: #a36b00; }
    body.body--white .ep-post__action { border-color: rgba(14,18,24,0.12); background: rgba(14,18,24,0.02); color: #0e1218; }
    body.body--white .ep-post__action:hover { border-color: rgba(163, 107, 0, 0.35); color: #a36b00; }
    body.body--white .ep-post__source { border-color: rgba(47, 111, 88, 0.22); background: rgba(47, 111, 88, 0.10); color: rgba(14, 18, 24, 0.78); }
    body.body--white .ep-post__source a { color: #2f6f58; }
</style>
<div class="wrapper">
    <article class="main__blog-single ep-post">
        <div class="container">
            <div class="ep-post__wrap">
                <div class="ep-post__main">
                    <a href="/blog.php" class="ep-post__back">← <?= $epUi('post.all_posts') ?></a>
                    <h1 class="ep-post__title"><?= htmlspecialchars($p['title']) ?></h1>
                    <?php if ($date || trim((string) ($p['author_name'] ?? '')) !== ''): ?>
                        <p class="ep-post__meta">
                            <?php if ($date): ?><span><?= $date ?></span><?php endif; ?>
                            <?php if (trim((string) ($p['author_name'] ?? '')) !== ''): ?><span><?= $epUi('post.author') ?> <?= htmlspecialchars(ep_post_tag_label($p)) ?></span><?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($postImageSrc !== ''): ?>
                        <div class="ep-post__cover">
                            <img src="<?= htmlspecialchars($postImageSrc) ?>" alt="<?= htmlspecialchars((string) $p['title']) ?>">
                        </div>
                    <?php endif; ?>
                    <div class="ep-post__content">
                        <?php foreach ($blocks as $b): ?>
                            <?php if (($b['type'] ?? '') === 'heading'): ?>
                                <h2 id="<?= htmlspecialchars((string) ($b['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($b['text'] ?? '')) ?></h2>
                            <?php else: ?>
                                <p><?= nl2br(htmlspecialchars((string) ($b['text'] ?? ''))) ?></p>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php
                    $sourceUrl = '';
                    if (preg_match('/(?:Источник|Крыніца|Source):\s*(https?:\/\/\S+)/u', (string) ($p['content'] ?? ''), $m)) {
                        $sourceUrl = trim((string) ($m[1] ?? ''));
                    }
                    ?>
                    <?php if ($sourceUrl !== ''): ?>
                        <div class="ep-post__source">
                            <?= $epUi('post.source') ?> <a href="<?= htmlspecialchars($sourceUrl) ?>" target="_blank" rel="noopener nofollow"><?= htmlspecialchars($sourceUrl) ?></a>
                        </div>
                    <?php endif; ?>
                </div>

                <aside class="ep-post__side" aria-label="<?= $epUi('post.toc_nav') ?>">
                    <?php if ($toc !== []): ?>
                        <p class="ep-post__side-title"><?= $epUi('post.toc') ?></p>
                        <ul class="ep-post__toc">
                            <?php foreach ($toc as $ti): ?>
                                <li><a href="#<?= htmlspecialchars((string) $ti['id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $ti['text']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <div class="ep-post__actions">
                        <a class="ep-post__action" href="/blog.php">← <?= $epUi('post.back_to_list') ?></a>
                        <button class="ep-post__action js-copy-post-link" type="button" data-copy-label="<?= $epUi('post.copy_link') ?>" data-copied-label="<?= $epUi('post.link_copied') ?>"><?= $epUi('post.copy_link') ?></button>
                        <a class="ep-post__action" href="https://t.me/share/url?url=<?= rawurlencode($cmsSeoCanonical) ?>&text=<?= rawurlencode((string) $p['title']) ?>" target="_blank" rel="noopener"><?= $epUi('post.share_telegram') ?></a>
                        <a class="ep-post__action" href="https://vk.com/share.php?url=<?= rawurlencode($cmsSeoCanonical) ?>" target="_blank" rel="noopener"><?= $epUi('post.share_vk') ?></a>
                    </div>
                </aside>
            </div>
        </div>
    </article>
</div>
<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.js-copy-post-link');
    if (!btn) return;
    var url = window.location.href;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function () {
            var copied = btn.getAttribute('data-copied-label') || 'OK';
            var original = btn.getAttribute('data-copy-label') || btn.textContent;
            btn.textContent = copied;
            setTimeout(function () { btn.textContent = original; }, 1400);
        });
    }
});
</script>
<?php
$pubIso = !empty($p['published_at']) ? date('c', strtotime((string) $p['published_at'])) : '';
$authorJson = trim((string) ($p['author_name'] ?? ''));
$postJsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => $p['title'],
    'description' => $cmsSeoDescription,
    'url' => $cmsSeoCanonical,
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $cmsSeoCanonical],
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'Easy People',
        'url' => $base,
    ],
];
if ($cmsSeoOgImage !== '') {
    $postJsonLd['image'] = $cmsSeoOgImage;
}
if ($pubIso !== '') {
    $postJsonLd['datePublished'] = $pubIso;
}
if ($authorJson !== '') {
    $postJsonLd['author'] = ['@type' => 'Person', 'name' => $authorJson];
}
echo cms_seo_json_ld_tag($postJsonLd);
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/footer.php';
