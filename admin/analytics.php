<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();

$adminNavActive = 'analytics';
require_once DOC_ROOT . '/include/data.php';
require_once DOC_ROOT . '/include/admin-analytics.php';

$sessionUser = $_SESSION['user'] ?? null;
$canContent = is_array($sessionUser) && ua_can_edit_site_content($sessionUser);

$days = max(7, min(90, (int) ($_GET['days'] ?? 30)));
$loadErr = '';

$kpis = ['signups_total' => 0, 'conversations_total' => 0];
$series = [
    'signups' => ['labels' => [], 'values' => []],
    'messenger_new' => ['labels' => [], 'values' => []],
    'messenger_active' => ['labels' => [], 'values' => []],
];
$contentExtras = [
    'activity_daily' => ['labels' => [], 'values' => []],
    'blog_created' => ['labels' => [], 'values' => []],
    'blog_published' => ['labels' => [], 'values' => []],
    'courses_created' => ['labels' => [], 'values' => []],
    'events_created' => ['labels' => [], 'values' => []],
    'teachers_created' => ['labels' => [], 'values' => []],
    'media_daily' => ['labels' => [], 'values' => []],
    'by_action' => ['labels' => [], 'values' => []],
    'by_entity' => ['labels' => [], 'values' => []],
    'blog_authors' => ['labels' => [], 'values' => []],
    'status_teachers' => [],
    'status_courses' => [],
    'status_events' => [],
    'status_blog' => [],
];

try {
    $kpis = admin_analytics_kpis($pdo, $canContent);
    $series['signups'] = admin_analytics_signups_daily($pdo, $days);
    $series['messenger_new'] = admin_analytics_messenger_new_daily($pdo, $days);
    $series['messenger_active'] = admin_analytics_messenger_user_activity_daily($pdo, $days);

    if ($canContent) {
        $contentExtras['activity_daily'] = admin_analytics_activity_log_daily($pdo, $days);
        $contentExtras['blog_created'] = admin_analytics_blog_posts_created_daily($pdo, $days);
        $contentExtras['blog_published'] = admin_analytics_blog_published_daily($pdo, $days);
        $contentExtras['courses_created'] = admin_analytics_courses_created_daily($pdo, $days);
        $contentExtras['events_created'] = admin_analytics_events_created_daily($pdo, $days);
        $contentExtras['teachers_created'] = admin_analytics_teachers_created_daily($pdo, $days);
        $contentExtras['media_daily'] = admin_analytics_cms_media_daily($pdo, $days);
        $contentExtras['by_action'] = admin_analytics_activity_by_action($pdo, $days, 16);
        $contentExtras['by_entity'] = admin_analytics_activity_by_entity($pdo, $days, 14);
        $contentExtras['blog_authors'] = admin_analytics_blog_top_authors($pdo, 10);
        $contentExtras['status_teachers'] = admin_analytics_status_counts($pdo, 'teachers');
        $contentExtras['status_courses'] = admin_analytics_status_counts($pdo, 'courses');
        $contentExtras['status_events'] = admin_analytics_status_counts($pdo, 'events');
        $contentExtras['status_blog'] = admin_analytics_status_counts($pdo, 'blog_posts');
    }
} catch (Throwable $e) {
    $loadErr = $e->getMessage();
}

$chartShort = static function (array $s): array {
    return [
        'labels' => admin_analytics_short_day_labels($s['labels'] ?? []),
        'fullLabels' => $s['labels'] ?? [],
        'values' => $s['values'] ?? [],
    ];
};

$enc = static function ($v): string {
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};

$rangeLink = static function (int $d) use ($days): string {
    $q = array_filter(['days' => $d]);

    return 'analytics.php?' . http_build_query($q);
};

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Аналитика — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <main class="admin-main admin-analytics-page">
        <div class="page-title">
            <h1>Аналитика</h1>
        </div>
        <p class="admin-lead admin-lead--tight">Сводка по заявкам, чатам и контенту CMS. Данные из базы; графики строятся в браузере (Chart.js).</p>

        <div class="admin-analytics-toolbar">
            <span class="admin-muted">Период (ось X):</span>
            <?php foreach ([14 => '14 дн.', 30 => '30 дн.', 90 => '90 дн.'] as $d => $lbl): ?>
            <a href="<?= htmlspecialchars($rangeLink($d)) ?>" class="btn btn-secondary btn-small<?= $days === $d ? ' is-active' : '' ?>"><?= htmlspecialchars($lbl) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($loadErr)): ?>
        <p class="admin-flash admin-flash--err">Не удалось загрузить часть метрик: <?= htmlspecialchars($loadErr) ?></p>
        <?php endif; ?>

        <section class="admin-section">
            <h2 class="admin-section__title">Ключевые показатели</h2>
            <div class="admin-analytics-kpi-grid">
                <div class="admin-analytics-kpi">
                    <span class="admin-analytics-kpi__value"><?= (int) ($kpis['signups_total'] ?? 0) ?></span>
                    <span class="admin-analytics-kpi__label">Заявок «Записаться» всего</span>
                </div>
                <div class="admin-analytics-kpi">
                    <span class="admin-analytics-kpi__value"><?= (int) ($kpis['conversations_total'] ?? 0) ?></span>
                    <span class="admin-analytics-kpi__label">Диалогов Messenger</span>
                </div>
                <?php if ($canContent): ?>
                <div class="admin-analytics-kpi">
                    <span class="admin-analytics-kpi__value"><?= (int) ($kpis['posts_total'] ?? 0) ?></span>
                    <span class="admin-analytics-kpi__label">Постов блога</span>
                </div>
                <div class="admin-analytics-kpi">
                    <span class="admin-analytics-kpi__value"><?= (int) ($kpis['courses_total'] ?? 0) ?></span>
                    <span class="admin-analytics-kpi__label">Курсов</span>
                </div>
                <div class="admin-analytics-kpi">
                    <span class="admin-analytics-kpi__value"><?= (int) ($kpis['events_total'] ?? 0) ?></span>
                    <span class="admin-analytics-kpi__label">Событий</span>
                </div>
                <div class="admin-analytics-kpi">
                    <span class="admin-analytics-kpi__value"><?= (int) ($kpis['teachers_total'] ?? 0) ?></span>
                    <span class="admin-analytics-kpi__label">Наставников</span>
                </div>
                <div class="admin-analytics-kpi">
                    <span class="admin-analytics-kpi__value"><?= (int) ($kpis['media_total'] ?? 0) ?></span>
                    <span class="admin-analytics-kpi__label">Файлов в медиатеке</span>
                </div>
                <div class="admin-analytics-kpi">
                    <span class="admin-analytics-kpi__value"><?= (int) ($kpis['activity_log_total'] ?? 0) ?></span>
                    <span class="admin-analytics-kpi__label">Записей в журнале действий</span>
                </div>
                <?php if (($kpis['users_total'] ?? 0) > 0): ?>
                <div class="admin-analytics-kpi">
                    <span class="admin-analytics-kpi__value"><?= (int) $kpis['users_total'] ?></span>
                    <span class="admin-analytics-kpi__label">Пользователей CMS</span>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="admin-section">
            <h2 class="admin-section__title">Заявки и Messenger</h2>
            <div class="admin-analytics-charts">
                <div class="admin-chart-card">
                    <h3 class="admin-chart-card__title">Новые заявки по дням</h3>
                    <div class="admin-chart-card__canvas-wrap">
                        <canvas id="chart-signups" width="400" height="220" aria-label="График заявок"></canvas>
                    </div>
                </div>
                <div class="admin-chart-card">
                    <h3 class="admin-chart-card__title">Новые диалоги (по дате создания)</h3>
                    <div class="admin-chart-card__canvas-wrap">
                        <canvas id="chart-messenger-new" width="400" height="220" aria-label="График диалогов"></canvas>
                    </div>
                </div>
                <div class="admin-chart-card">
                    <h3 class="admin-chart-card__title">Активность в чатах (сообщения пользователей по дням)</h3>
                    <div class="admin-chart-card__canvas-wrap">
                        <canvas id="chart-messenger-active" width="400" height="220" aria-label="График активности чатов"></canvas>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($canContent): ?>
        <section class="admin-section">
            <h2 class="admin-section__title">Журнал админки и контент</h2>
            <div class="admin-analytics-charts">
                <div class="admin-chart-card">
                    <h3 class="admin-chart-card__title">События журнала по дням</h3>
                    <div class="admin-chart-card__canvas-wrap">
                        <canvas id="chart-activity-daily" width="400" height="220"></canvas>
                    </div>
                </div>
                <div class="admin-chart-card">
                    <h3 class="admin-chart-card__title">Загрузки в медиатеку по дням</h3>
                    <div class="admin-chart-card__canvas-wrap">
                        <canvas id="chart-media" width="400" height="220"></canvas>
                    </div>
                </div>
                <div class="admin-chart-card">
                    <h3 class="admin-chart-card__title">Новые посты блога (created)</h3>
                    <div class="admin-chart-card__canvas-wrap">
                        <canvas id="chart-blog-created" width="400" height="220"></canvas>
                    </div>
                </div>
                <div class="admin-chart-card">
                    <h3 class="admin-chart-card__title">Публикации блога (published_at)</h3>
                    <div class="admin-chart-card__canvas-wrap">
                        <canvas id="chart-blog-published" width="400" height="220"></canvas>
                    </div>
                </div>
                <div class="admin-chart-card">
                    <h3 class="admin-chart-card__title">Новые курсы по дням</h3>
                    <div class="admin-chart-card__canvas-wrap">
                        <canvas id="chart-courses" width="400" height="220"></canvas>
                    </div>
                </div>
                <div class="admin-chart-card">
                    <h3 class="admin-chart-card__title">Новые события по дням</h3>
                    <div class="admin-chart-card__canvas-wrap">
                        <canvas id="chart-events" width="400" height="220"></canvas>
                    </div>
                </div>
                <div class="admin-chart-card">
                    <h3 class="admin-chart-card__title">Новые карточки наставников</h3>
                    <div class="admin-chart-card__canvas-wrap">
                        <canvas id="chart-teachers" width="400" height="220"></canvas>
                    </div>
                </div>
            </div>
        </section>

        <section class="admin-section">
            <h2 class="admin-section__title">Распределения и топы</h2>
            <div class="admin-analytics-charts admin-analytics-charts--dense">
                <div class="admin-chart-card">
                    <h3 class="admin-chart-card__title">Типы действий в журнале</h3>
                    <div class="admin-chart-card__canvas-wrap admin-chart-card__canvas-wrap--tall">
                        <canvas id="chart-actions-bar" width="400" height="320"></canvas>
                    </div>
                </div>
                <div class="admin-chart-card">
                    <h3 class="admin-chart-card__title">Сущности в журнале</h3>
                    <div class="admin-chart-card__canvas-wrap admin-chart-card__canvas-wrap--square">
                        <canvas id="chart-entity-doughnut" width="320" height="280"></canvas>
                    </div>
                </div>
                <div class="admin-chart-card">
                    <h3 class="admin-chart-card__title">Топ авторов блога</h3>
                    <div class="admin-chart-card__canvas-wrap admin-chart-card__canvas-wrap--tall">
                        <canvas id="chart-authors-bar" width="400" height="320"></canvas>
                    </div>
                </div>
                <div class="admin-chart-card">
                    <h3 class="admin-chart-card__title">Статусы наставников</h3>
                    <div class="admin-chart-card__canvas-wrap admin-chart-card__canvas-wrap--square">
                        <canvas id="chart-status-teachers" width="280" height="260"></canvas>
                    </div>
                </div>
                <div class="admin-chart-card">
                    <h3 class="admin-chart-card__title">Статусы курсов</h3>
                    <div class="admin-chart-card__canvas-wrap admin-chart-card__canvas-wrap--square">
                        <canvas id="chart-status-courses" width="280" height="260"></canvas>
                    </div>
                </div>
                <div class="admin-chart-card">
                    <h3 class="admin-chart-card__title">Статусы событий</h3>
                    <div class="admin-chart-card__canvas-wrap admin-chart-card__canvas-wrap--square">
                        <canvas id="chart-status-events" width="280" height="260"></canvas>
                    </div>
                </div>
                <div class="admin-chart-card">
                    <h3 class="admin-chart-card__title">Статусы постов</h3>
                    <div class="admin-chart-card__canvas-wrap admin-chart-card__canvas-wrap--square">
                        <canvas id="chart-status-blog" width="280" height="260"></canvas>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

    </main>
    <?php include __DIR__ . '/_footer.php'; ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') return;
        Chart.defaults.color = '#9494b8';
        Chart.defaults.borderColor = 'rgba(120, 110, 180, 0.22)';
        Chart.defaults.font.family = '"Segoe UI", system-ui, sans-serif';

        var C = {
            accent: '#e94560',
            gold: '#f9d442',
            info: '#93c5fd',
            success: '#4ade80',
            warn: '#fbbf24',
            purple: '#a78bfa',
            teal: '#2dd4bf',
            rose: '#fb7185'
        };

        var PALETTE = [C.accent, C.gold, C.info, C.success, C.purple, C.teal, C.rose, C.warn, '#67e8f9', '#c4b5fd', '#86efac', '#fcd34d'];

        function hexToRgba(hex, a) {
            var h = hex.replace('#', '');
            var r = parseInt(h.slice(0, 2), 16), g = parseInt(h.slice(2, 4), 16), b = parseInt(h.slice(4, 6), 16);
            return 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')';
        }

        function lineChartFixed(id, labels, values, datasetLabel, color) {
            var el = document.getElementById(id);
            if (!el || !labels.length) return;
            var bg = hexToRgba(color, 0.14);
            new Chart(el, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: datasetLabel,
                        data: values,
                        borderColor: color,
                        backgroundColor: bg,
                        fill: true,
                        tension: 0.25,
                        pointRadius: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: true } },
                    scales: {
                        x: { ticks: { maxRotation: 45 } },
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }

        function horizontalBar(id, labels, values, title) {
            var el = document.getElementById(id);
            if (!el || !labels.length) return;
            new Chart(el, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: title,
                        data: values,
                        backgroundColor: labels.map(function (_, i) { return PALETTE[i % PALETTE.length]; })
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }

        function doughnutChart(id, labels, values) {
            var el = document.getElementById(id);
            if (!el || !labels.length) return;
            new Chart(el, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: labels.map(function (_, i) { return PALETTE[i % PALETTE.length]; }),
                        borderWidth: 1,
                        borderColor: '#12121f'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } }
                    }
                }
            });
        }

        var S_SIGNUPS = <?= $enc($chartShort($series['signups'])) ?>;
        var S_MSG_NEW = <?= $enc($chartShort($series['messenger_new'])) ?>;
        var S_MSG_ACT = <?= $enc($chartShort($series['messenger_active'])) ?>;

        lineChartFixed('chart-signups', S_SIGNUPS.labels, S_SIGNUPS.values, 'Заявок', C.accent);
        lineChartFixed('chart-messenger-new', S_MSG_NEW.labels, S_MSG_NEW.values, 'Диалогов', C.info);
        lineChartFixed('chart-messenger-active', S_MSG_ACT.labels, S_MSG_ACT.values, 'Чатов с сообщениями', C.gold);

        <?php if ($canContent): ?>
        var S_ACT = <?= $enc($chartShort($contentExtras['activity_daily'])) ?>;
        var S_MEDIA = <?= $enc($chartShort($contentExtras['media_daily'])) ?>;
        var S_BLOG_C = <?= $enc($chartShort($contentExtras['blog_created'])) ?>;
        var S_BLOG_P = <?= $enc($chartShort($contentExtras['blog_published'])) ?>;
        var S_CRS = <?= $enc($chartShort($contentExtras['courses_created'])) ?>;
        var S_EVT = <?= $enc($chartShort($contentExtras['events_created'])) ?>;
        var S_TCH = <?= $enc($chartShort($contentExtras['teachers_created'])) ?>;

        lineChartFixed('chart-activity-daily', S_ACT.labels, S_ACT.values, 'Событий журнала', C.purple);
        lineChartFixed('chart-media', S_MEDIA.labels, S_MEDIA.values, 'Файлов', C.teal);
        lineChartFixed('chart-blog-created', S_BLOG_C.labels, S_BLOG_C.values, 'Постов', C.info);
        lineChartFixed('chart-blog-published', S_BLOG_P.labels, S_BLOG_P.values, 'Публикаций', C.success);
        lineChartFixed('chart-courses', S_CRS.labels, S_CRS.values, 'Курсов', C.gold);
        lineChartFixed('chart-events', S_EVT.labels, S_EVT.values, 'Событий', C.rose);
        lineChartFixed('chart-teachers', S_TCH.labels, S_TCH.values, 'Наставников', C.accent);

        var BY_ACT = <?= $enc($contentExtras['by_action']) ?>;
        var BY_ENT = <?= $enc($contentExtras['by_entity']) ?>;
        var BY_AUTH = <?= $enc($contentExtras['blog_authors']) ?>;

        horizontalBar('chart-actions-bar', BY_ACT.labels, BY_ACT.values, 'Раз');
        horizontalBar('chart-authors-bar', BY_AUTH.labels, BY_AUTH.values, 'Постов');

        doughnutChart('chart-entity-doughnut', BY_ENT.labels, BY_ENT.values);

        var ST_T = <?= $enc(array_column($contentExtras['status_teachers'], 'status')) ?>;
        var ST_TV = <?= $enc(array_map('intval', array_column($contentExtras['status_teachers'], 'c'))) ?>;
        var ST_C = <?= $enc(array_column($contentExtras['status_courses'], 'status')) ?>;
        var ST_CV = <?= $enc(array_map('intval', array_column($contentExtras['status_courses'], 'c'))) ?>;
        var ST_E = <?= $enc(array_column($contentExtras['status_events'], 'status')) ?>;
        var ST_EV = <?= $enc(array_map('intval', array_column($contentExtras['status_events'], 'c'))) ?>;
        var ST_B = <?= $enc(array_column($contentExtras['status_blog'], 'status')) ?>;
        var ST_BV = <?= $enc(array_map('intval', array_column($contentExtras['status_blog'], 'c'))) ?>;

        doughnutChart('chart-status-teachers', ST_T, ST_TV);
        doughnutChart('chart-status-courses', ST_C, ST_CV);
        doughnutChart('chart-status-events', ST_E, ST_EV);
        doughnutChart('chart-status-blog', ST_B, ST_BV);
        <?php endif; ?>
    });
    </script>
</body>
</html>
