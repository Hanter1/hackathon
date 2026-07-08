<?php
declare(strict_types=1);

/** @var string|null $epCurrentLang */
$epCurrentLang = null;

/** @var array<string, string>|null $epTranslationCache */
$epTranslationCache = null;

function ep_supported_langs(): array
{
    return ['ru', 'be', 'en'];
}

function ep_lang_labels(): array
{
    return [
        'ru' => 'Русский',
        'be' => 'Беларуская',
        'en' => 'English',
    ];
}

function ep_normalize_lang(string $lang): string
{
    $lang = strtolower(trim($lang));
    if ($lang === 'by' || $lang === 'bel') {
        $lang = 'be';
    }
    return in_array($lang, ep_supported_langs(), true) ? $lang : 'ru';
}

function ep_lang(): string
{
    global $epCurrentLang;
    if ($epCurrentLang === null) {
        ep_i18n_bootstrap();
    }
    return $epCurrentLang ?? 'ru';
}

function ep_i18n_bootstrap(): void
{
    global $epCurrentLang, $pdo;

    if ($epCurrentLang !== null) {
        return;
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($pdo)) {
        require_once __DIR__ . '/app-bootstrap.php';
    }

    $lang = 'ru';
    if (isset($_GET['lang'])) {
        $lang = ep_normalize_lang((string) $_GET['lang']);
        setcookie('ep_lang', $lang, time() + 86400 * 365, '/', '', false, true);
        $_COOKIE['ep_lang'] = $lang;
    } elseif (!empty($_COOKIE['ep_lang'])) {
        $lang = ep_normalize_lang((string) $_COOKIE['ep_lang']);
    } elseif (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $accept = strtolower(substr((string) $_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2));
        if ($accept === 'be' || $accept === 'by') {
            $lang = 'be';
        } elseif ($accept === 'en') {
            $lang = 'en';
        }
    }

    $epCurrentLang = $lang;
    ep_load_translations($pdo, $lang);
}

function ep_default_translations(): array
{
    static $defaults = null;
    if ($defaults === null) {
        $defaults = require __DIR__ . '/i18n-defaults.php';
    }
    return is_array($defaults) ? $defaults : [];
}

function ep_load_translations(PDO $pdo, string $lang): void
{
    global $epTranslationCache;
    $lang = ep_normalize_lang($lang);
    $merged = ep_default_translations()[$lang] ?? [];

    try {
        $st = $pdo->prepare('SELECT translation_key, translation_value FROM site_translations WHERE lang = ?');
        $st->execute([$lang]);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $key = trim((string) ($row['translation_key'] ?? ''));
            if ($key !== '') {
                $merged[$key] = (string) ($row['translation_value'] ?? '');
            }
        }
    } catch (Throwable $e) {
        // таблица может ещё не существовать
    }

    $epTranslationCache = $merged;
}

/** Перевод UI-строки по ключу. */
function ep_t(string $key, string $fallback = ''): string
{
    global $epTranslationCache;
    if ($epTranslationCache === null) {
        ep_i18n_bootstrap();
    }
    $key = trim($key);
    if ($key === '') {
        return $fallback;
    }
    if (isset($epTranslationCache[$key]) && $epTranslationCache[$key] !== '') {
        return $epTranslationCache[$key];
    }
    if ($fallback !== '') {
        return $fallback;
    }
    $defaults = ep_default_translations()[ep_lang()] ?? [];
    return $defaults[$key] ?? $key;
}

function ep_lang_html_attr(): string
{
    $map = ['ru' => 'ru', 'be' => 'be', 'en' => 'en'];
    return $map[ep_lang()] ?? 'ru';
}

function ep_lang_og_locale(): string
{
    $map = ['ru' => 'ru_RU', 'be' => 'be_BY', 'en' => 'en_US'];
    return $map[ep_lang()] ?? 'ru_RU';
}

function ep_lang_flag_src(?string $lang = null): string
{
    $lang = ep_normalize_lang($lang ?? ep_lang());
    $map = [
        'ru' => '/images/flag-icon-ru.svg',
        'be' => '/images/flag-icon-by.svg',
        'en' => '/images/flag-icon-1.svg',
    ];
    return $map[$lang] ?? $map['ru'];
}

function ep_category_translation_key(string $category): ?string
{
    $norm = mb_strtoupper(trim($category), 'UTF-8');
    $map = [
        'ФИНАНСЫ' => 'category.FINANCES',
        'ФІНАНСЫ' => 'category.FINANCES',
        'FINANCES' => 'category.FINANCES',
        'FINANCE' => 'category.FINANCES',
        'SOFT_SKILLS' => 'category.SOFT_SKILLS',
        'SOFT SKILLS' => 'category.SOFT_SKILLS',
        'SOFT_AND_HARD' => 'category.SOFT_AND_HARD',
        'SOFT AND HARD' => 'category.SOFT_AND_HARD',
        'SOFT & HARD SKILLS' => 'category.SOFT_AND_HARD',
        'NEWS' => 'category.NEWS',
        'НОВОСТИ' => 'category.NEWS',
        'НАВІНЫ' => 'category.NEWS',
    ];
    return $map[$norm] ?? null;
}

/** Метка рубрики на карточке новости (author_name или «Новость»). */
function ep_post_tag_label(array $post): string
{
    $author = trim((string) ($post['author_name'] ?? ''));
    if ($author === '') {
        return ep_t('common.news_item');
    }
    $norm = mb_strtoupper($author, 'UTF-8');
    $generic = ['NEWS', 'НОВОСТИ', 'НОВОСТЬ', 'НАВІНА', 'НАВІНЫ', 'НОВІНА'];
    if (in_array($norm, $generic, true)) {
        return ep_t('common.news_item');
    }
    return $author;
}

/** Перевод категории курса/поста по ключу i18n, если нет записи в cms_entity_i18n. */
function ep_localize_category(string $category): string
{
    if ($category === '' || ep_lang() === 'ru') {
        return $category;
    }
    $tKey = ep_category_translation_key($category);
    if ($tKey === null) {
        return $category;
    }
    $tr = ep_t($tKey, $category);
    return ($tr !== $tKey && $tr !== '') ? $tr : $category;
}

function ep_entity_i18n_defaults(): array
{
    static $defaults = null;
    if ($defaults === null) {
        $defaults = require __DIR__ . '/entity-i18n-defaults.php';
    }
    return is_array($defaults) ? $defaults : [];
}

function ep_entity_i18n_default_pack(string $entityType, array $row, string $lang): array
{
    $lang = ep_normalize_lang($lang);
    if ($lang === 'ru') {
        return [];
    }
    $pack = ep_entity_i18n_defaults()[$entityType] ?? [];
    if (!is_array($pack)) {
        return [];
    }
    $slug = trim((string) ($row['slug'] ?? ''));
    if ($slug !== '' && !empty($pack['by_slug'][$slug][$lang]) && is_array($pack['by_slug'][$slug][$lang])) {
        return $pack['by_slug'][$slug][$lang];
    }
    $title = trim((string) ($row['title'] ?? $row['name'] ?? ''));
    if ($title !== '' && !empty($pack['by_title'][$title][$lang]) && is_array($pack['by_title'][$title][$lang])) {
        return $pack['by_title'][$title][$lang];
    }
    return [];
}

/** URL текущей страницы с другим языком. */
function ep_lang_switch_url(string $lang): string
{
    $lang = ep_normalize_lang($lang);
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($uri);
    $path = $parts['path'] ?? '/';
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $query['lang'] = $lang;
    $qs = http_build_query($query);
    return $path . ($qs !== '' ? '?' . $qs : '');
}

function ep_home_i18n_keys(): array
{
    if (!defined('HOME_DEFAULTS')) {
        require_once __DIR__ . '/data.php';
    }
    return array_keys(HOME_DEFAULTS);
}

function ep_home_text_keys(): array
{
    $skip = ['section_top_event_slug', 'count_1_url', 'count_2_url', 'count_3_url', 'count_4_url', 'elevate_btn_url'];
    return array_values(array_filter(ep_home_i18n_keys(), static fn($k) => !in_array($k, $skip, true)));
}

function ep_home_i18n_get(PDO $pdo, string $key, string $lang): string
{
    $lang = ep_normalize_lang($lang);
    if ($lang === 'ru') {
        return '';
    }
    try {
        $st = $pdo->prepare('SELECT setting_value FROM home_settings_i18n WHERE setting_key = ? AND lang = ? LIMIT 1');
        $st->execute([$key, $lang]);
        $v = $st->fetchColumn();
        if (is_string($v) && $v !== '') {
            return $v;
        }
    } catch (Throwable $e) {
        // table may not exist
    }
    return ep_home_i18n_default($key, $lang);
}

function ep_home_i18n_defaults(): array
{
    static $defaults = null;
    if ($defaults === null) {
        $defaults = require __DIR__ . '/home-i18n-defaults.php';
    }
    return is_array($defaults) ? $defaults : [];
}

function ep_home_i18n_default(string $key, string $lang): string
{
    $lang = ep_normalize_lang($lang);
    if ($lang === 'ru') {
        return '';
    }
    return (string) (ep_home_i18n_defaults()[$lang][$key] ?? '');
}

function ep_home_i18n_save(PDO $pdo, string $key, string $lang, string $value): bool
{
    $lang = ep_normalize_lang($lang);
    if ($lang === 'ru' || !array_key_exists($key, HOME_DEFAULTS)) {
        return false;
    }
    try {
        if (cms_is_mysql($pdo)) {
            $st = $pdo->prepare('INSERT INTO home_settings_i18n (setting_key, lang, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP');
            $st->execute([$key, $lang, $value]);
        } else {
            $now = date('Y-m-d H:i:s');
            $st = $pdo->prepare('UPDATE home_settings_i18n SET setting_value = ?, updated_at = ? WHERE setting_key = ? AND lang = ?');
            $st->execute([$value, $now, $key, $lang]);
            if ($st->rowCount() === 0) {
                $st = $pdo->prepare('INSERT INTO home_settings_i18n (setting_key, lang, setting_value, updated_at) VALUES (?, ?, ?, ?)');
                $st->execute([$key, $lang, $value, $now]);
            }
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function ep_home_settings_localized(array $base): array
{
    global $pdo;
    $lang = ep_lang();
    if ($lang === 'ru') {
        return $base;
    }
    $out = $base;
    foreach (ep_home_text_keys() as $key) {
        $tr = ep_home_i18n_get($pdo, $key, $lang);
        if ($tr !== '') {
            $out[$key] = $tr;
        }
    }
    return $out;
}

function ep_entity_i18n_fields(string $entityType): array
{
    $map = [
        'course' => ['title', 'category', 'description', 'meta_title', 'meta_description', 'level_label', 'duration_label', 'language_label'],
        'blog_post' => ['title', 'excerpt', 'content', 'author_name', 'meta_title', 'meta_description'],
        'teacher' => ['name', 'surname', 'bio', 'meta_title', 'meta_description'],
        'event' => ['title', 'description', 'location', 'organizer_name', 'venue_name', 'venue_address', 'meta_title', 'meta_description'],
    ];
    return $map[$entityType] ?? [];
}

function ep_entity_i18n_get(PDO $pdo, string $entityType, int $entityId, string $lang): array
{
    $lang = ep_normalize_lang($lang);
    if ($entityId <= 0 || $lang === 'ru') {
        return [];
    }
    try {
        $st = $pdo->prepare('SELECT field_name, field_value FROM cms_entity_i18n WHERE entity_type = ? AND entity_id = ? AND lang = ?');
        $st->execute([$entityType, $entityId, $lang]);
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $f = (string) ($row['field_name'] ?? '');
            if ($f !== '') {
                $out[$f] = (string) ($row['field_value'] ?? '');
            }
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

function ep_entity_i18n_save(PDO $pdo, string $entityType, int $entityId, string $lang, array $fields): void
{
    $lang = ep_normalize_lang($lang);
    if ($entityId <= 0 || $lang === 'ru') {
        return;
    }
    $allowed = ep_entity_i18n_fields($entityType);
    foreach ($allowed as $field) {
        if (!array_key_exists($field, $fields)) {
            continue;
        }
        $value = trim((string) $fields[$field]);
        try {
            if ($value === '') {
                $st = $pdo->prepare('DELETE FROM cms_entity_i18n WHERE entity_type = ? AND entity_id = ? AND lang = ? AND field_name = ?');
                $st->execute([$entityType, $entityId, $lang, $field]);
                continue;
            }
            if (cms_is_mysql($pdo)) {
                $st = $pdo->prepare('INSERT INTO cms_entity_i18n (entity_type, entity_id, lang, field_name, field_value) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE field_value = VALUES(field_value), updated_at = CURRENT_TIMESTAMP');
                $st->execute([$entityType, $entityId, $lang, $field, $value]);
            } else {
                $now = date('Y-m-d H:i:s');
                $st = $pdo->prepare('UPDATE cms_entity_i18n SET field_value = ?, updated_at = ? WHERE entity_type = ? AND entity_id = ? AND lang = ? AND field_name = ?');
                $st->execute([$value, $now, $entityType, $entityId, $lang, $field]);
                if ($st->rowCount() === 0) {
                    $st = $pdo->prepare('INSERT INTO cms_entity_i18n (entity_type, entity_id, lang, field_name, field_value, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
                    $st->execute([$entityType, $entityId, $lang, $field, $value, $now]);
                }
            }
        } catch (Throwable $e) {
            // ignore single field failure
        }
    }
}

function ep_apply_entity_i18n(?array $row, string $entityType): ?array
{
    if (!$row || empty($row['id'])) {
        return $row;
    }
    $lang = ep_lang();
    if ($lang === 'ru') {
        return $row;
    }
    global $pdo;
    $tr = array_merge(
        ep_entity_i18n_default_pack($entityType, $row, $lang),
        ep_entity_i18n_get($pdo, $entityType, (int) $row['id'], $lang)
    );
    foreach (ep_entity_i18n_fields($entityType) as $field) {
        if (!empty($tr[$field])) {
            $row[$field] = $tr[$field];
        } elseif ($field === 'category' && !empty($row['category'])) {
            $row['category'] = ep_localize_category((string) $row['category']);
        }
    }
    return $row;
}

function ep_apply_entity_i18n_list(array $rows, string $entityType): array
{
    $lang = ep_lang();
    if ($lang === 'ru' || empty($rows)) {
        return $rows;
    }
    foreach ($rows as $i => $row) {
        $applied = ep_apply_entity_i18n(is_array($row) ? $row : null, $entityType);
        if (is_array($applied)) {
            $rows[$i] = $applied;
        }
    }
    return $rows;
}

function ep_apply_entity_i18n_a(array $row, string $entityType): array
{
    $applied = ep_apply_entity_i18n($row, $entityType);
    return is_array($applied) ? $applied : $row;
}

function ep_site_translation_save(PDO $pdo, string $key, string $lang, string $value): bool
{
    $key = trim($key);
    $lang = ep_normalize_lang($lang);
    if ($key === '') {
        return false;
    }
    try {
        if (cms_is_mysql($pdo)) {
            $st = $pdo->prepare('INSERT INTO site_translations (translation_key, lang, translation_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE translation_value = VALUES(translation_value), updated_at = CURRENT_TIMESTAMP');
            $st->execute([$key, $lang, $value]);
        } else {
            $now = date('Y-m-d H:i:s');
            $st = $pdo->prepare('UPDATE site_translations SET translation_value = ?, updated_at = ? WHERE translation_key = ? AND lang = ?');
            $st->execute([$value, $now, $key, $lang]);
            if ($st->rowCount() === 0) {
                $st = $pdo->prepare('INSERT INTO site_translations (translation_key, lang, translation_value, updated_at) VALUES (?, ?, ?, ?)');
                $st->execute([$key, $lang, $value, $now]);
            }
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function ep_all_translation_keys(): array
{
    $keys = [];
    foreach (ep_default_translations() as $langPack) {
        foreach (array_keys($langPack) as $k) {
            $keys[$k] = true;
        }
    }
    return array_keys($keys);
}

function ep_entity_i18n_save_from_post(PDO $pdo, string $entityType, int $entityId, array $post): void
{
    if ($entityId <= 0) {
        return;
    }
    $data = $post['i18n'] ?? null;
    if (!is_array($data)) {
        return;
    }
    foreach (['be', 'en'] as $lang) {
        if (!isset($data[$lang]) || !is_array($data[$lang])) {
            continue;
        }
        ep_entity_i18n_save($pdo, $entityType, $entityId, $lang, $data[$lang]);
    }
}

function ep_home_i18n_save_from_post(PDO $pdo, array $post): void
{
    $data = $post['home_i18n'] ?? null;
    if (!is_array($data)) {
        return;
    }
    foreach (['be', 'en'] as $lang) {
        if (!isset($data[$lang]) || !is_array($data[$lang])) {
            continue;
        }
        foreach ($data[$lang] as $key => $value) {
            if (!is_string($key) || !array_key_exists($key, HOME_DEFAULTS)) {
                continue;
            }
            ep_home_i18n_save($pdo, $key, $lang, trim((string) $value));
        }
    }
}

function ep_translation_group(string $key): string
{
    $pos = strpos($key, '.');
    return $pos === false ? 'other' : substr($key, 0, $pos);
}
