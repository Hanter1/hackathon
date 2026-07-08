<?php
require_once __DIR__ . '/auth.php';
require_login();

$adminNavActive = 'dashboard';
$isFullAdminDash = isset($_SESSION['user']) && is_array($_SESSION['user']) && ua_is_admin_user($_SESSION['user']);
$isModeratorDash = isset($_SESSION['user']) && is_array($_SESSION['user']) && ua_is_moderator_user($_SESSION['user']);

$teachersCount = $coursesCount = $eventsCount = $postsCount = 0;
$signupCount = $chatCount = 0;
$recentSignups = [];
$recentPosts = [];
$activityByDay = [];
$activityRecent = [];
$appLogTail = [];

try {
    require_once DOC_ROOT . '/include/data.php';
    $signupCount = count_signup_requests();
    $chatCount = count_messenger_conversations();
    $recentSignups = array_slice(get_signup_requests(), 0, 6);

    if (!$isModeratorDash) {
        $teachersCount = count(get_teachers('active'));
        $coursesCount = count(get_courses('active'));
        $eventsCount = count(get_events('active'));
        $postsCount = count_blog_posts('published');
        $recentPosts = get_blog_posts('published', 6);
    }
    if (!$isModeratorDash && isset($pdo) && $pdo instanceof PDO) {
        try {
            $activityByDay = admin_activity_counts_by_day($pdo, 7);
            $activityRecent = admin_activity_recent($pdo, 12);
        } catch (Throwable $e) {
            $activityByDay = [];
            $activityRecent = [];
        }
    }
    if ($isFullAdminDash) {
        $appLogTail = admin_tail_app_log(48);
    }
} catch (Throwable $e) {
    die('Ошибка загрузки данных: ' . htmlspecialchars($e->getMessage()) . ' в ' . $e->getFile() . ':' . $e->getLine());
}

$fmtDt = static function ($v): string {
    if ($v === null || $v === '') {
        return '—';
    }
    $ts = strtotime((string) $v);

    return $ts ? date('d.m.Y H:i', $ts) : htmlspecialchars((string) $v);
};

$denied = isset($_GET['denied']) ? (string) $_GET['denied'] : '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель — Админка Easy People</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <main class="admin-main">
        <?php if ($denied === '1'): ?>
        <p class="admin-flash admin-flash--warn">Раздел доступен только полному администратору.</p>
        <?php elseif ($denied === 'content'): ?>
        <p class="admin-flash admin-flash--warn">Редактирование контента сайта доступно ролям <strong>Редактор</strong> и <strong>Администратор</strong>. Роль <strong>Модератор</strong> работает с заявками «Записаться», Messenger и поиском.</p>
        <?php elseif ($denied === 'tools'): ?>
        <p class="admin-flash admin-flash--warn">Медиатека и журнал действий доступны редакторам и администраторам.</p>
        <?php endif; ?>

        <h1 class="admin-page-heading">Панель управления</h1>
        <?php if ($isModeratorDash): ?>
        <p class="admin-lead">Роль <strong>модератора</strong>: заявки с сайта, диалоги Messenger и поиск по материалам (без правок контента).</p>
        <?php else: ?>
        <p class="admin-lead">Обзор контента и заявок. <strong>Редакторы</strong> правят сайт и медиатеку; <strong>модераторы</strong> — только заявки и чаты; разделы «Пользователи» и «Служебное» — только у администратора.</p>
        <?php endif; ?>

        <?php if (!$isModeratorDash): ?>
        <section class="admin-section">
            <h2 class="admin-section__title">Сводка</h2>
            <div class="dashboard-cards">
                <a href="teachers.php" class="card card--stat">
                    <span class="card-num"><?= (int) $teachersCount ?></span>
                    <span class="card-label">Наставники</span>
                    <span class="card-hint">активные в каталоге</span>
                </a>
                <a href="courses.php" class="card card--stat">
                    <span class="card-num"><?= (int) $coursesCount ?></span>
                    <span class="card-label">Курсы</span>
                    <span class="card-hint">опубликованные</span>
                </a>
                <a href="events.php" class="card card--stat">
                    <span class="card-num"><?= (int) $eventsCount ?></span>
                    <span class="card-label">События</span>
                    <span class="card-hint">активные</span>
                </a>
                <a href="blog.php" class="card card--stat">
                    <span class="card-num"><?= (int) $postsCount ?></span>
                    <span class="card-label">Посты блога</span>
                    <span class="card-hint">статус «опубликован»</span>
                </a>
            </div>
        </section>
        <?php else: ?>
        <section class="admin-section">
            <h2 class="admin-section__title">Сводка</h2>
            <div class="dashboard-cards">
                <a href="signup-settings.php" class="card card--stat">
                    <span class="card-num"><?= (int) $signupCount ?></span>
                    <span class="card-label">Заявки</span>
                    <span class="card-hint">форма «Записаться»</span>
                </a>
                <a href="conversations.php" class="card card--stat">
                    <span class="card-num"><?= (int) $chatCount ?></span>
                    <span class="card-label">Диалоги</span>
                    <span class="card-hint">Messenger</span>
                </a>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!$isModeratorDash && ($activityByDay !== [] || $activityRecent !== [])): ?>
        <section class="admin-section admin-section--analytics">
            <h2 class="admin-section__title">Активность в админке</h2>
            <p class="admin-muted" style="margin-top:0;">Журнал действий за последние 7 дней и свежие события. <a href="analytics.php">Полная аналитика и графики →</a> · <a href="activity.php">Журнал</a>.</p>
            <div class="admin-analytics-grid">
                <?php if ($activityByDay !== []): ?>
                <div class="admin-analytics-card">
                    <h3 class="admin-analytics-card__title">Событий по дням</h3>
                    <div class="table-wrap">
                        <table class="admin-table--compact">
                            <thead>
                                <tr><th>Дата</th><th>Записей</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activityByDay as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($row['day_key'] ?? '')) ?></td>
                                    <td><?= (int) ($row['cnt'] ?? 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($activityRecent !== []): ?>
                <div class="admin-analytics-card">
                    <h3 class="admin-analytics-card__title">Последние действия</h3>
                    <div class="table-wrap">
                        <table class="admin-table--compact">
                            <thead>
                                <tr><th>Время</th><th>Кто</th><th>Действие</th><th>Сущность</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activityRecent as $row): ?>
                                <tr>
                                    <td><?= $fmtDt($row['created_at'] ?? '') ?></td>
                                    <td><?= htmlspecialchars((string) ($row['login'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars((string) ($row['action'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars(trim((string) ($row['entity_type'] ?? '') . ($row['entity_id'] ? ' #' . (int) $row['entity_id'] : ''))) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($isFullAdminDash && $appLogTail !== []): ?>
        <section class="admin-section">
            <h2 class="admin-section__title">Хвост журнала приложения</h2>
            <p class="admin-muted" style="margin-top:0;">Последние строки файла <code>data/logs/app.log</code> (только для администратора).</p>
            <pre class="admin-log-tail" tabindex="0"><?php foreach ($appLogTail as $line): ?><?= htmlspecialchars((string) $line) ?>

<?php endforeach; ?></pre>
        </section>
        <?php endif; ?>

        <section class="admin-section">
            <h2 class="admin-section__title"><?= $isModeratorDash ? 'Разделы' : 'Сервисы' ?></h2>
            <div class="dashboard-cards dashboard-cards--compact">
                <?php if (!$isModeratorDash): ?>
                <a href="home.php" class="card card--service">
                    <span class="card-label">Главная страница</span>
                    <span class="card-hint">баннеры, блоки, тексты</span>
                </a>
                <?php endif; ?>
                <a href="signup-settings.php" class="card card--service">
                    <span class="card-label">Записаться</span>
                    <span class="card-hint">настройки формы и <?= (int) $signupCount ?> заявок</span>
                </a>
                <a href="conversations.php" class="card card--service">
                    <span class="card-label">Messenger</span>
                    <span class="card-hint"><?= (int) $chatCount ?> диалогов</span>
                </a>
                <a href="search.php" class="card card--service">
                    <span class="card-label">Поиск</span>
                    <span class="card-hint">по контенту (read-only для модератора)</span>
                </a>
                <?php if (!$isModeratorDash): ?>
                <a href="media.php" class="card card--service">
                    <span class="card-label">Медиатека</span>
                    <span class="card-hint">загрузки и URL для вёрстки</span>
                </a>
                <a href="activity.php" class="card card--service">
                    <span class="card-label">Журнал</span>
                    <span class="card-hint">кто что менял</span>
                </a>
                <?php endif; ?>
                <?php if ($isFullAdminDash): ?>
                <a href="users.php" class="card card--service">
                    <span class="card-label">Пользователи</span>
                    <span class="card-hint">роли CMS</span>
                </a>
                <a href="service.php" class="card card--service">
                    <span class="card-label">Служебное</span>
                    <span class="card-hint">пароль, схема БД</span>
                </a>
                <?php endif; ?>
            </div>
        </section>

        <?php if (!$isModeratorDash): ?>
        <section class="admin-section">
            <h2 class="admin-section__title">Быстрое создание</h2>
            <div class="admin-quick-actions">
                <a href="teacher-edit.php" class="btn btn-secondary">+ Наставник</a>
                <a href="course-edit.php" class="btn btn-secondary">+ Курс</a>
                <a href="event-edit.php" class="btn btn-secondary">+ Событие</a>
                <a href="post-edit.php" class="btn btn-secondary">+ Пост блога</a>
            </div>
        </section>
        <?php endif; ?>

        <div class="admin-columns">
            <section class="admin-section admin-col">
                <div class="admin-section__head">
                    <h2 class="admin-section__title">Последние заявки «Записаться»</h2>
                    <a href="signup-settings.php" class="admin-section__link">Все и настройки →</a>
                </div>
                <?php if (!$recentSignups): ?>
                <p class="admin-empty">Заявок пока нет.</p>
                <?php else: ?>
                <div class="table-wrap">
                    <table class="admin-table--compact">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Имя</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSignups as $row): ?>
                            <tr>
                                <td><?= $fmtDt($row['created_at'] ?? '') ?></td>
                                <td><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string) ($row['email'] ?? '')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>

            <?php if (!$isModeratorDash): ?>
            <section class="admin-section admin-col">
                <div class="admin-section__head">
                    <h2 class="admin-section__title">Свежие публикации</h2>
                    <a href="blog.php" class="admin-section__link">Блог →</a>
                </div>
                <?php if (!$recentPosts): ?>
                <p class="admin-empty">Постов нет.</p>
                <?php else: ?>
                <ul class="admin-recent-list">
                    <?php foreach ($recentPosts as $p): ?>
                    <li>
                        <a href="post-edit.php?id=<?= (int) ($p['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($p['title'] ?? '')) ?></a>
                        <span class="admin-recent-list__meta"><?= $fmtDt($p['published_at'] ?? $p['created_at'] ?? '') ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        </div>
    </main>
    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
