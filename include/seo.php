<?php

declare(strict_types=1);

/**
 * Публичный SEO: canonical, description, Open Graph, сниппеты, JSON-LD.
 */

function cms_public_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

    return $scheme . '://' . $host;
}

function cms_seo_entity_title(array $row, string $fallback): string
{
    $t = trim((string) ($row['meta_title'] ?? ''));

    return $t !== '' ? $t : $fallback;
}

/**
 * Обрезка текста для сниппета по границе слова (без обрыва посередине).
 */
function cms_seo_trim_snippet(string $text, int $maxLen = 320): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if ($text === '') {
        return '';
    }
    $enc = 'UTF-8';
    if (function_exists('mb_strlen') && mb_strlen($text, $enc) <= $maxLen) {
        return $text;
    }
    if (!function_exists('mb_substr')) {
        return strlen($text) > $maxLen ? rtrim(substr($text, 0, $maxLen - 1), " \t\n\r") . '…' : $text;
    }
    $slice = mb_substr($text, 0, $maxLen, $enc);
    $lastSpace = mb_strrpos($slice, ' ', 0, $enc);
    if ($lastSpace !== false && $lastSpace > (int) ($maxLen * 0.55)) {
        $slice = mb_substr($slice, 0, $lastSpace, $enc);
    }
    $slice = rtrim($slice, " \t\n\r\0\x0B,.;:");

    return $slice . '…';
}

function cms_seo_entity_description(array $row, string $fallback, int $maxLen = 320): string
{
    $d = trim((string) ($row['meta_description'] ?? ''));
    if ($d === '') {
        $d = $fallback;
    }

    return cms_seo_trim_snippet($d, $maxLen);
}

function cms_seo_abs_media_url(?string $path): string
{
    if ($path === null || trim($path) === '') {
        return '';
    }
    $path = trim($path);
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    return cms_public_base_url() . '/' . ltrim($path, '/');
}

/**
 * Безопасная вставка JSON-LD в страницу.
 */
function cms_seo_json_ld_tag(array $data): string
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($data, $flags);
    if ($json === false || $json === '') {
        return '';
    }

    return '<script type="application/ld+json">' . $json . "</script>\n";
}
