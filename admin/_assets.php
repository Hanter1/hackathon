<?php
declare(strict_types=1);

/** Абсолютный от корня сайта путь к admin.css с cache-bust. */
function admin_css_url(): string
{
    $dir = dirname($_SERVER['SCRIPT_NAME'] ?? '/admin');
    $base = rtrim(str_replace('\\', '/', $dir), '/');
    $fs = __DIR__ . '/admin.css';
    $v = is_readable($fs) ? filemtime($fs) : time();

    return $base . '/admin.css?v=' . rawurlencode((string) $v);
}

/** Путь к admin.js с cache-bust (для UX-скриптов админки). */
function admin_js_url(): string
{
    $dir = dirname($_SERVER['SCRIPT_NAME'] ?? '/admin');
    $base = rtrim(str_replace('\\', '/', $dir), '/');
    $fs = __DIR__ . '/admin.js';
    $v = is_readable($fs) ? filemtime($fs) : time();

    return $base . '/admin.js?v=' . rawurlencode((string) $v);
}
