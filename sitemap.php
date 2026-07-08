<?php

declare(strict_types=1);

/**
 * XML sitemap для поисковиков (активные курсы, события, опубликованные посты).
 */
require_once __DIR__ . '/include/data.php';
require_once __DIR__ . '/include/seo.php';

$base = cms_public_base_url();

header('Content-Type: application/xml; charset=UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$urls = [
    ['loc' => $base . '/', 'changefreq' => 'daily', 'priority' => '1.0'],
    ['loc' => $base . '/courses.php', 'changefreq' => 'weekly', 'priority' => '0.9'],
    ['loc' => $base . '/events.php', 'changefreq' => 'weekly', 'priority' => '0.8'],
    ['loc' => $base . '/blog.php', 'changefreq' => 'weekly', 'priority' => '0.8'],
];

foreach ($urls as $u) {
    echo '  <url><loc>' . htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';
    echo '<changefreq>' . htmlspecialchars($u['changefreq'], ENT_XML1, 'UTF-8') . '</changefreq>';
    echo '<priority>' . htmlspecialchars($u['priority'], ENT_XML1, 'UTF-8') . '</priority></url>' . "\n";
}

foreach (get_courses('active') as $row) {
    $slug = (string) ($row['slug'] ?? '');
    if ($slug === '') {
        continue;
    }
    $loc = $base . '/course.php?slug=' . rawurlencode($slug);
    echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc><changefreq>weekly</changefreq><priority>0.7</priority></url>' . "\n";
}

foreach (get_events('active') as $row) {
    $slug = (string) ($row['slug'] ?? '');
    if ($slug === '') {
        continue;
    }
    $loc = $base . '/event.php?slug=' . rawurlencode($slug);
    echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc><changefreq>weekly</changefreq><priority>0.6</priority></url>' . "\n";
}

foreach (get_blog_posts('published', 500) as $row) {
    $slug = (string) ($row['slug'] ?? '');
    if ($slug === '') {
        continue;
    }
    $loc = $base . '/post.php?slug=' . rawurlencode($slug);
    echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc><changefreq>monthly</changefreq><priority>0.6</priority></url>' . "\n";
}

echo '</urlset>';
