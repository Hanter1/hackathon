<?php
$adminUserLabel = '';
if (!empty($_SESSION['user']) && is_array($_SESSION['user'])) {
    $adminUserLabel = (string) ($_SESSION['user']['name'] ?: ($_SESSION['user']['login'] ?? ''));
}
if ($adminUserLabel === '' && !empty($_SESSION['admin_login'])) {
    $adminUserLabel = (string) $_SESSION['admin_login'];
}
if ($adminUserLabel === '') {
    $adminUserLabel = 'Администратор';
}
$adminNavActive = $adminNavActive ?? '';
$isFullAdminHeader = !empty($_SESSION['user']) && is_array($_SESSION['user']) && ua_is_admin_user($_SESSION['user']);
$isModeratorNav = !empty($_SESSION['user']) && is_array($_SESSION['user']) && ua_is_moderator_user($_SESSION['user']);
$headerSearchQ = isset($_GET['q']) ? (string) $_GET['q'] : '';
if (basename($_SERVER['PHP_SELF'] ?? '') !== 'search.php') {
    $headerSearchQ = '';
}

$slink = function (string $key, string $path, string $label) use ($adminNavActive): string {
    $cls = 'admin-sidebar__link';
    if ($adminNavActive === $key) {
        $cls .= ' is-active';
    }

    return '<a href="' . htmlspecialchars($path) . '" class="' . htmlspecialchars($cls) . '">' . htmlspecialchars($label) . '</a>';
};
?>
<div class="admin-app">
    <aside class="admin-sidebar" aria-label="Меню админки">
        <div class="admin-sidebar__brand">
            <a href="index.php" class="admin-sidebar__logo">Easy People</a>
            <span class="admin-sidebar__badge">CMS</span>
        </div>

        <nav class="admin-sidebar__nav">
            <?= $slink('dashboard', 'index.php', 'Панель') ?>

            <?php if (!$isModeratorNav): ?>
            <div class="admin-sidebar__group">
                <span class="admin-sidebar__label">Контент</span>
                <div class="admin-sidebar__stack">
                    <?= $slink('home', 'home.php', 'Главная страница') ?>
                    <?= $slink('teachers', 'teachers.php', 'Наставники') ?>
                    <?= $slink('courses', 'courses.php', 'Курсы') ?>
                    <?= $slink('events', 'events.php', 'События') ?>
                    <?= $slink('blog', 'blog.php', 'Блог') ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="admin-sidebar__group">
                <span class="admin-sidebar__label">Заявки и чаты</span>
                <div class="admin-sidebar__stack">
                    <?= $slink('signup', 'signup-settings.php', 'Записаться') ?>
                    <?= $slink('conversations', 'conversations.php', 'Messenger') ?>
                </div>
            </div>

            <div class="admin-sidebar__group">
                <span class="admin-sidebar__label">Инструменты</span>
                <div class="admin-sidebar__stack">
                    <?= $slink('analytics', 'analytics.php', 'Аналитика') ?>
                    <?php if (!$isModeratorNav): ?>
                    <?= $slink('media', 'media.php', 'Медиатека') ?>
                    <?= $slink('translations', 'translations.php', 'Переводы') ?>
                    <?= $slink('activity', 'activity.php', 'Журнал') ?>
                    <?php endif; ?>
                    <?= $slink('search', 'search.php', 'Поиск') ?>
                </div>
            </div>

            <?php if ($isFullAdminHeader): ?>
            <div class="admin-sidebar__group">
                <span class="admin-sidebar__label">Система</span>
                <div class="admin-sidebar__stack">
                    <?= $slink('users', 'users.php', 'Пользователи') ?>
                    <?= $slink('security', 'security.php', 'Безопасность') ?>
                    <?= $slink('service', 'service.php', 'Служебное') ?>
                </div>
            </div>
            <?php endif; ?>
        </nav>

        <div class="admin-sidebar__footer">
            <span class="admin-sidebar__user" title="Текущий пользователь"><?= htmlspecialchars($adminUserLabel) ?></span>
            <div class="admin-sidebar__actions">
                <a href="/" class="admin-sidebar__action" target="_blank" rel="noopener">Открыть сайт</a>
                <a href="logout.php" class="admin-sidebar__action admin-sidebar__action--logout">Выход</a>
            </div>
        </div>
    </aside>

    <div class="admin-app__column">
        <header class="admin-topbar">
            <form class="admin-topbar__search" method="get" action="search.php" role="search">
                <input type="search" name="q" placeholder="Поиск по контенту…" value="<?= htmlspecialchars($headerSearchQ) ?>" maxlength="200" aria-label="Поиск в админке" autocomplete="off">
                <button type="submit" class="admin-topbar__search-btn">Найти</button>
            </form>
        </header>
