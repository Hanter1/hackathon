<?php

declare(strict_types=1);

/**
 * robots.txt с актуальным хостом (Sitemap).
 */
require_once __DIR__ . '/include/seo.php';

header('Content-Type: text/plain; charset=UTF-8');

$base = cms_public_base_url();

echo "User-agent: *\n";
echo "Allow: /\n\n";
echo 'Sitemap: ' . $base . "/sitemap.php\n";
