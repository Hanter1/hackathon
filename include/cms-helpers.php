<?php

/**
 * Запись в журнал действий админки (ошибки игнорируются).
 */
function cms_log(PDO $pdo, string $action, string $entityType = '', ?int $entityId = null, array $meta = []): void
{
    try {
        $uid = (int) ($_SESSION['user']['id'] ?? 0);
        $login = (string) ($_SESSION['user']['login'] ?? $_SESSION['admin_login'] ?? '');
        $metaJson = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
        $st = $pdo->prepare('INSERT INTO admin_activity_log (user_id, login, action, entity_type, entity_id, meta) VALUES (?,?,?,?,?,?)');
        $st->execute([
            $uid > 0 ? $uid : null,
            $login,
            $action,
            $entityType,
            $entityId,
            $metaJson,
        ]);
    } catch (Throwable $e) {
        // ignore
    }
}

function admin_page_param(): int
{
    return max(1, (int) ($_GET['page'] ?? 1));
}

function admin_per_page_param(int $default = 20): int
{
    return min(100, max(5, (int) ($_GET['per'] ?? $default)));
}

function admin_calc_pages(int $total, int $perPage): int
{
    return max(1, (int) ceil($total / max(1, $perPage)));
}

/**
 * @param array<string,scalar|null> $baseQuery preserved keys for pager links
 */
function admin_pager_html(int $page, int $total, int $perPage, array $baseQuery, string $path = ''): string
{
    $pages = admin_calc_pages($total, $perPage);
    if ($pages <= 1) {
        return '';
    }
    $path = $path ?: basename($_SERVER['PHP_SELF'] ?? 'index.php');
    $baseQuery['per'] = $perPage;
    $out = '<nav class="admin-pager" aria-label="Страницы">';
    if ($page > 1) {
        $baseQuery['page'] = $page - 1;
        $out .= '<a class="admin-pager__link" href="' . htmlspecialchars($path) . '?' . http_build_query($baseQuery) . '">← Назад</a>';
    }
    $out .= '<span class="admin-pager__info">Стр. ' . (int) $page . ' из ' . (int) $pages . ' (' . (int) $total . ')</span>';
    if ($page < $pages) {
        $baseQuery['page'] = $page + 1;
        $out .= '<a class="admin-pager__link" href="' . htmlspecialchars($path) . '?' . http_build_query($baseQuery) . '">Вперёд →</a>';
    }
    $out .= '</nav>';

    return $out;
}

/** Бейдж статуса в таблицах админки (active, draft, …). */
function admin_status_badge_html(string $status): string
{
    $slug = preg_replace('/[^a-z0-9_-]/i', '', $status) ?: 'unknown';

    return '<span class="admin-badge admin-badge--' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>';
}

/**
 * Значение для скрытого поля оптимистической блокировки (совпадает с условием в UPDATE).
 */
function cms_row_lock_value(array $row): string
{
    $u = trim((string) ($row['updated_at'] ?? ''));
    if ($u !== '') {
        return $u;
    }

    return trim((string) ($row['created_at'] ?? ''));
}

/**
 * Форматирование цены для BYN (Br).
 * Пример: "210" -> "210 Br"
 */
function ep_format_price_byn(string $raw): string
{
    $s = trim($raw);
    if ($s === '') {
        return '';
    }
    // If already contains a currency marker, keep as-is.
    if (preg_match('/(byn|br|руб|р\\.|₽|\\$|€)/iu', $s)) {
        return $s;
    }
    // If just a number (or "210,5"), append Belarusian ruble marker.
    if (preg_match('/^\\d+(?:[\\s.,]\\d+)?$/u', $s)) {
        $s = str_replace(',', '.', $s);
        return $s . ' Br';
    }
    return $s;
}

/**
 * Гарантирует уникальность slug в указанной таблице (teachers, blog_posts, courses, events).
 * Если slug занят — добавляет суффикс "-2", "-3", ...
 */
function cms_unique_slug(PDO $pdo, string $table, string $slug, int $excludeId = 0): string
{
    $table = preg_replace('/[^a-z0-9_]/i', '', $table) ?: '';
    if ($table === '') {
        return $slug;
    }
    $base = trim($slug);
    if ($base === '') {
        $base = 'item';
    }
    $base = preg_replace('/[^a-z0-9\-]+/i', '-', $base);
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'item';
    }

    $excludeId = max(0, (int) $excludeId);

    $exists = static function (string $candidate) use ($pdo, $table, $excludeId): bool {
        $sql = "SELECT id FROM {$table} WHERE slug = ? " . ($excludeId > 0 ? "AND id <> ? " : "") . "LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute($excludeId > 0 ? [$candidate, $excludeId] : [$candidate]);
        return (bool) $st->fetchColumn();
    };

    if (!$exists($base)) {
        return $base;
    }
    for ($i = 2; $i <= 999; $i++) {
        $cand = $base . '-' . $i;
        if (!$exists($cand)) {
            return $cand;
        }
    }
    return $base . '-' . date('YmdHis');
}

/** Относительный путь публичной страницы контента или null. */
function admin_content_public_path(string $entity, string $slug): ?string
{
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }
    switch (strtolower($entity)) {
        case 'course':
            return '/course.php?slug=' . rawurlencode($slug);
        case 'event':
            return '/event.php?slug=' . rawurlencode($slug);
        case 'post':
            return '/post.php?slug=' . rawurlencode($slug);
        default:
            return null;
    }
}

/**
 * Последние строки журнала приложения (только для служебного просмотра).
 *
 * @return list<string>
 */
function admin_tail_app_log(int $maxLines = 60): array
{
    $path = (defined('DOC_ROOT') ? DOC_ROOT : dirname(__DIR__)) . '/data/logs/app.log';
    if (!is_readable($path)) {
        return [];
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) {
        return [];
    }
    $lines = array_slice($lines, -$maxLines);

    return $lines;
}

/**
 * Кнопки «Сайт», копирование URL и «Копия» в строках списков админки.
 */
function admin_entity_row_extras(?string $publicPath, string $duplicateEditUrl, string $dupConfirm = 'Создать черновик-копию этой записи?'): string
{
    $o = '';
    if ($publicPath !== null && $publicPath !== '') {
        $o .= '<a href="' . htmlspecialchars($publicPath, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener" class="btn btn-secondary btn-small">Сайт</a>';
        $o .= '<button type="button" class="btn btn-secondary btn-small js-copy-url" data-url="' . htmlspecialchars($publicPath, ENT_QUOTES, 'UTF-8') . '">URL</button>';
    }
    $o .= '<a href="' . htmlspecialchars($duplicateEditUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-secondary btn-small" data-confirm="' . htmlspecialchars($dupConfirm, ENT_QUOTES, 'UTF-8') . '">Копия</a>';

    return $o;
}

/**
 * Сколько записей в журнале админки по календарным дням (последние $days дней, включая сегодня).
 *
 * @return list<array{day_key: string, cnt: int}>
 */
function admin_activity_counts_by_day(PDO $pdo, int $days = 7): array
{
    $days = max(1, min(90, $days));
    $start = (new DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days')->format('Y-m-d 00:00:00');
    $isMysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $dateExpr = $isMysql ? 'DATE(created_at)' : 'date(created_at)';
    $sql = 'SELECT ' . $dateExpr . ' AS day_key, COUNT(*) AS cnt FROM admin_activity_log WHERE created_at >= ? GROUP BY ' . $dateExpr . ' ORDER BY day_key ASC';
    $st = $pdo->prepare($sql);
    $st->execute([$start]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static function (array $r): array {
        return ['day_key' => (string) ($r['day_key'] ?? ''), 'cnt' => (int) ($r['cnt'] ?? 0)];
    }, $rows);
}

/**
 * @return list<array<string, mixed>>
 */
function admin_activity_recent(PDO $pdo, int $limit = 15): array
{
    $limit = max(1, min(100, $limit));
    $st = $pdo->query('SELECT * FROM admin_activity_log ORDER BY id DESC LIMIT ' . (int) $limit);

    return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
}

function ep_public_media_src(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return ($path[0] ?? '') === '/' ? $path : '/' . $path;
}

function ep_event_title_html(string $title): string
{
    $title = trim($title);
    if ($title === '') {
        return '';
    }
    $parts = preg_split('/\R/u', $title, 2) ?: [$title];
    if (count($parts) === 2 && trim((string) $parts[1]) !== '') {
        return htmlspecialchars(trim((string) $parts[0]), ENT_QUOTES, 'UTF-8')
            . '<br>'
            . htmlspecialchars(trim((string) $parts[1]), ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
}

function ep_event_price_label(string $raw): string
{
    $s = trim($raw);
    if ($s === '') {
        return '';
    }
    if (preg_match('/^(free|бесплатно|0)$/iu', $s)) {
        return function_exists('ep_t') ? ep_t('event.free') : 'Free';
    }
    return $s;
}

