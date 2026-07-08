<?php

declare(strict_types=1);

/**
 * Агрегаты для раздела «Аналитика» в админке (графики, KPI).
 */

function admin_analytics_is_mysql(PDO $pdo): bool
{
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
}

/** SQL-выражение календарного дня для группировки (MySQL / SQLite). */
function admin_analytics_day_expr(PDO $pdo, string $column): string
{
    return admin_analytics_is_mysql($pdo) ? "DATE($column)" : "date($column)";
}

/**
 * @param array<string,int> $dayKeyToCount ключи Y-m-d
 * @return array{labels: list<string>, values: list<int>}
 */
/** Короткие подписи оси X (д.м) из Y-m-d. */
function admin_analytics_short_day_labels(array $labels): array
{
    $out = [];
    foreach ($labels as $d) {
        $ts = strtotime((string) $d);
        $out[] = $ts ? date('d.m', $ts) : (string) $d;
    }

    return $out;
}

function admin_analytics_fill_series(array $dayKeyToCount, int $days): array
{
    $days = max(1, min(366, $days));
    $labels = [];
    $values = [];
    $today = new DateTimeImmutable('today');
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = $today->modify('-' . $i . ' days')->format('Y-m-d');
        $labels[] = $d;
        $values[] = (int) ($dayKeyToCount[$d] ?? 0);
    }

    return ['labels' => $labels, 'values' => $values];
}

/**
 * @param list<array{d: string, c: int|string}> $rows
 * @return array{labels: list<string>, values: list<int>}
 */
function admin_analytics_rows_to_series(array $rows, int $days): array
{
    $map = [];
    foreach ($rows as $row) {
        $map[(string) ($row['d'] ?? '')] = (int) ($row['c'] ?? 0);
    }

    return admin_analytics_fill_series($map, $days);
}

function admin_analytics_start_datetime(int $days): string
{
    $days = max(1, min(366, $days));

    return (new DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days')->format('Y-m-d 00:00:00');
}

/** Заявки «Записаться» по дням. */
function admin_analytics_signups_daily(PDO $pdo, int $days): array
{
    $start = admin_analytics_start_datetime($days);
    $d = admin_analytics_day_expr($pdo, 'created_at');
    $sql = "SELECT {$d} AS d, COUNT(*) AS c FROM signup_requests WHERE created_at >= ? GROUP BY {$d} ORDER BY d";
    $st = $pdo->prepare($sql);
    $st->execute([$start]);

    return admin_analytics_rows_to_series($st->fetchAll(PDO::FETCH_ASSOC), $days);
}

/** Новые диалоги Messenger по дням (created_at). */
function admin_analytics_messenger_new_daily(PDO $pdo, int $days): array
{
    $start = admin_analytics_start_datetime($days);
    $d = admin_analytics_day_expr($pdo, 'created_at');
    $sql = "SELECT {$d} AS d, COUNT(*) AS c FROM messenger_conversations WHERE created_at >= ? GROUP BY {$d} ORDER BY d";
    $st = $pdo->prepare($sql);
    $st->execute([$start]);

    return admin_analytics_rows_to_series($st->fetchAll(PDO::FETCH_ASSOC), $days);
}

/** Дни, когда пользователи писали в чат (last_user_message_at). */
function admin_analytics_messenger_user_activity_daily(PDO $pdo, int $days): array
{
    $start = admin_analytics_start_datetime($days);
    $d = admin_analytics_day_expr($pdo, 'last_user_message_at');
    $sql = "SELECT {$d} AS d, COUNT(*) AS c FROM messenger_conversations WHERE last_user_message_at IS NOT NULL AND last_user_message_at >= ? GROUP BY {$d} ORDER BY d";
    $st = $pdo->prepare($sql);
    $st->execute([$start]);

    return admin_analytics_rows_to_series($st->fetchAll(PDO::FETCH_ASSOC), $days);
}

/** События журнала админки по дням. */
function admin_analytics_activity_log_daily(PDO $pdo, int $days): array
{
    $start = admin_analytics_start_datetime($days);
    $d = admin_analytics_day_expr($pdo, 'created_at');
    $sql = "SELECT {$d} AS d, COUNT(*) AS c FROM admin_activity_log WHERE created_at >= ? GROUP BY {$d} ORDER BY d";
    $st = $pdo->prepare($sql);
    $st->execute([$start]);

    return admin_analytics_rows_to_series($st->fetchAll(PDO::FETCH_ASSOC), $days);
}

function admin_analytics_blog_posts_created_daily(PDO $pdo, int $days): array
{
    $start = admin_analytics_start_datetime($days);
    $d = admin_analytics_day_expr($pdo, 'created_at');
    $sql = "SELECT {$d} AS d, COUNT(*) AS c FROM blog_posts WHERE created_at >= ? GROUP BY {$d} ORDER BY d";
    $st = $pdo->prepare($sql);
    $st->execute([$start]);

    return admin_analytics_rows_to_series($st->fetchAll(PDO::FETCH_ASSOC), $days);
}

function admin_analytics_courses_created_daily(PDO $pdo, int $days): array
{
    $start = admin_analytics_start_datetime($days);
    $d = admin_analytics_day_expr($pdo, 'created_at');
    $sql = "SELECT {$d} AS d, COUNT(*) AS c FROM courses WHERE created_at >= ? GROUP BY {$d} ORDER BY d";
    $st = $pdo->prepare($sql);
    $st->execute([$start]);

    return admin_analytics_rows_to_series($st->fetchAll(PDO::FETCH_ASSOC), $days);
}

function admin_analytics_events_created_daily(PDO $pdo, int $days): array
{
    $start = admin_analytics_start_datetime($days);
    $d = admin_analytics_day_expr($pdo, 'created_at');
    $sql = "SELECT {$d} AS d, COUNT(*) AS c FROM events WHERE created_at >= ? GROUP BY {$d} ORDER BY d";
    $st = $pdo->prepare($sql);
    $st->execute([$start]);

    return admin_analytics_rows_to_series($st->fetchAll(PDO::FETCH_ASSOC), $days);
}

function admin_analytics_teachers_created_daily(PDO $pdo, int $days): array
{
    $start = admin_analytics_start_datetime($days);
    $d = admin_analytics_day_expr($pdo, 'created_at');
    $sql = "SELECT {$d} AS d, COUNT(*) AS c FROM teachers WHERE created_at >= ? GROUP BY {$d} ORDER BY d";
    $st = $pdo->prepare($sql);
    $st->execute([$start]);

    return admin_analytics_rows_to_series($st->fetchAll(PDO::FETCH_ASSOC), $days);
}

function admin_analytics_cms_media_daily(PDO $pdo, int $days): array
{
    $start = admin_analytics_start_datetime($days);
    $d = admin_analytics_day_expr($pdo, 'created_at');
    $sql = "SELECT {$d} AS d, COUNT(*) AS c FROM cms_media WHERE created_at >= ? GROUP BY {$d} ORDER BY d";
    $st = $pdo->prepare($sql);
    $st->execute([$start]);

    return admin_analytics_rows_to_series($st->fetchAll(PDO::FETCH_ASSOC), $days);
}

/** Топ типов действий в журнале за период. */
function admin_analytics_activity_by_action(PDO $pdo, int $days, int $limit = 14): array
{
    $limit = max(3, min(40, $limit));
    $start = admin_analytics_start_datetime($days);
    $st = $pdo->prepare('SELECT action, COUNT(*) AS c FROM admin_activity_log WHERE created_at >= ? GROUP BY action ORDER BY c DESC LIMIT ' . (int) $limit);
    $st->execute([$start]);
    $labels = [];
    $values = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $labels[] = (string) ($row['action'] ?? '');
        $values[] = (int) ($row['c'] ?? 0);
    }

    return ['labels' => $labels, 'values' => $values];
}

/** Распределение по типу сущности в журнале. */
function admin_analytics_activity_by_entity(PDO $pdo, int $days, int $limit = 12): array
{
    $limit = max(3, min(30, $limit));
    $start = admin_analytics_start_datetime($days);
    $etExpr = "COALESCE(NULLIF(TRIM(entity_type), ''), '(пусто)')";
    $st = $pdo->prepare('SELECT ' . $etExpr . ' AS et, COUNT(*) AS c FROM admin_activity_log WHERE created_at >= ? GROUP BY ' . $etExpr . ' ORDER BY c DESC LIMIT ' . (int) $limit);
    $st->execute([$start]);
    $labels = [];
    $values = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $labels[] = (string) ($row['et'] ?? '');
        $values[] = (int) ($row['c'] ?? 0);
    }

    return ['labels' => $labels, 'values' => $values];
}

/** Посты блога: сколько опубликовано по дням (по published_at). */
function admin_analytics_blog_published_daily(PDO $pdo, int $days): array
{
    $start = admin_analytics_start_datetime($days);
    $d = admin_analytics_day_expr($pdo, 'published_at');
    $sql = "SELECT {$d} AS d, COUNT(*) AS c FROM blog_posts WHERE status = 'published' AND published_at IS NOT NULL AND published_at >= ? GROUP BY {$d} ORDER BY d";
    $st = $pdo->prepare($sql);
    $st->execute([$start]);

    return admin_analytics_rows_to_series($st->fetchAll(PDO::FETCH_ASSOC), $days);
}

/**
 * @return list<array{status: string, c: int}>
 */
function admin_analytics_status_counts(PDO $pdo, string $table): array
{
    $allowed = ['teachers', 'courses', 'events', 'blog_posts'];
    if (!in_array($table, $allowed, true)) {
        return [];
    }
    $st = $pdo->query('SELECT status, COUNT(*) AS c FROM ' . $table . ' GROUP BY status ORDER BY c DESC');

    return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
}

/**
 * Топ авторов блога (по числу постов).
 * @return array{labels: list<string>, values: list<int>}
 */
function admin_analytics_blog_top_authors(PDO $pdo, int $limit = 8): array
{
    $limit = max(3, min(20, $limit));
    $anExpr = "COALESCE(NULLIF(TRIM(author_name), ''), '(без имени)')";
    $st = $pdo->query('SELECT ' . $anExpr . ' AS an, COUNT(*) AS c FROM blog_posts GROUP BY ' . $anExpr . ' ORDER BY c DESC LIMIT ' . (int) $limit);
    if (!$st) {
        return ['labels' => [], 'values' => []];
    }
    $labels = [];
    $values = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $labels[] = (string) ($row['an'] ?? '');
        $values[] = (int) ($row['c'] ?? 0);
    }

    return ['labels' => $labels, 'values' => $values];
}

/** KPI: числа в одной строке карточек. */
function admin_analytics_kpis(PDO $pdo, bool $includeContent): array
{
    $out = [
        'signups_total' => (int) $pdo->query('SELECT COUNT(*) FROM signup_requests')->fetchColumn(),
        'conversations_total' => (int) $pdo->query('SELECT COUNT(*) FROM messenger_conversations')->fetchColumn(),
    ];
    if ($includeContent) {
        $out['teachers_total'] = (int) $pdo->query('SELECT COUNT(*) FROM teachers')->fetchColumn();
        $out['courses_total'] = (int) $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
        $out['events_total'] = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
        $out['posts_total'] = (int) $pdo->query('SELECT COUNT(*) FROM blog_posts')->fetchColumn();
        $out['media_total'] = (int) $pdo->query('SELECT COUNT(*) FROM cms_media')->fetchColumn();
        $out['activity_log_total'] = (int) $pdo->query('SELECT COUNT(*) FROM admin_activity_log')->fetchColumn();
        try {
            $out['users_total'] = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        } catch (Throwable $e) {
            $out['users_total'] = 0;
        }
    }

    return $out;
}
