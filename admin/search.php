<?php
require_once __DIR__ . '/auth.php';
require_login();
$adminNavActive = 'search';

$sessionUserSearch = $_SESSION['user'] ?? null;
$canEditContentSearch = is_array($sessionUserSearch) && ua_can_edit_site_content($sessionUserSearch);

$q = trim((string) ($_GET['q'] ?? ''));
$results = ['teachers' => [], 'courses' => [], 'events' => [], 'posts' => []];

if (mb_strlen($q) >= 2) {
    $like = '%' . $q . '%';
    foreach (['teachers' => "SELECT id, name, surname, slug, status FROM teachers WHERE name LIKE ? OR surname LIKE ? OR slug LIKE ? OR bio LIKE ? OR role LIKE ? ORDER BY id DESC LIMIT 30",
        'courses' => "SELECT id, title, slug, status FROM courses WHERE title LIKE ? OR slug LIKE ? OR description LIKE ? OR category LIKE ? ORDER BY id DESC LIMIT 30",
        'events' => "SELECT id, title, slug, status FROM events WHERE title LIKE ? OR slug LIKE ? OR description LIKE ? OR location LIKE ? ORDER BY id DESC LIMIT 30",
        'posts' => "SELECT id, title, slug, status FROM blog_posts WHERE title LIKE ? OR slug LIKE ? OR excerpt LIKE ? OR content LIKE ? ORDER BY id DESC LIMIT 30",
    ] as $key => $sql) {
        $st = $pdo->prepare($sql);
        if ($key === 'teachers') {
            $st->execute([$like, $like, $like, $like, $like]);
        } elseif ($key === 'courses' || $key === 'events') {
            $st->execute([$like, $like, $like, $like]);
        } else {
            $st->execute([$like, $like, $like, $like]);
        }
        $results[$key] = $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Поиск — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <main class="admin-main">
        <div class="page-title">
            <h1>Поиск по контенту</h1>
        </div>
        <form method="get" class="admin-search-form">
            <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Не менее 2 символов…" class="admin-search-input" autocomplete="off">
            <button type="submit" class="btn btn-primary">Найти</button>
        </form>

        <?php if (mb_strlen($q) > 0 && mb_strlen($q) < 2): ?>
        <p class="admin-flash admin-flash--warn">Введите минимум 2 символа.</p>
        <?php elseif (mb_strlen($q) >= 2): ?>

        <section class="admin-section">
            <h2 class="admin-section__title">Наставники</h2>
            <?php if (!$results['teachers']): ?><p class="admin-empty">Ничего не найдено.</p><?php else: ?>
            <ul class="admin-recent-list">
                <?php foreach ($results['teachers'] as $t): ?>
                <li>
                    <?php $tTitle = htmlspecialchars(trim(($t['name'] ?? '') . ' ' . ($t['surname'] ?? ''))); ?>
                    <?php if ($canEditContentSearch): ?>
                    <a href="teacher-edit.php?id=<?= (int) $t['id'] ?>"><?= $tTitle ?></a>
                    <?php else: ?>
                    <span class="admin-search-readonly"><?= $tTitle ?></span>
                    <?php endif; ?>
                    <span class="admin-recent-list__meta"><?= htmlspecialchars((string) ($t['status'] ?? '')) ?> · <?= htmlspecialchars((string) ($t['slug'] ?? '')) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

        <section class="admin-section">
            <h2 class="admin-section__title">Курсы</h2>
            <?php if (!$results['courses']): ?><p class="admin-empty">Ничего не найдено.</p><?php else: ?>
            <ul class="admin-recent-list">
                <?php foreach ($results['courses'] as $c): ?>
                <li>
                    <?php $cTitle = htmlspecialchars((string) ($c['title'] ?? '')); ?>
                    <?php if ($canEditContentSearch): ?>
                    <a href="course-edit.php?id=<?= (int) $c['id'] ?>"><?= $cTitle ?></a>
                    <?php else: ?>
                    <span class="admin-search-readonly"><?= $cTitle ?></span>
                    <?php endif; ?>
                    <span class="admin-recent-list__meta"><?= htmlspecialchars((string) ($c['status'] ?? '')) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

        <section class="admin-section">
            <h2 class="admin-section__title">События</h2>
            <?php if (!$results['events']): ?><p class="admin-empty">Ничего не найдено.</p><?php else: ?>
            <ul class="admin-recent-list">
                <?php foreach ($results['events'] as $e): ?>
                <li>
                    <?php $eTitle = htmlspecialchars((string) ($e['title'] ?? '')); ?>
                    <?php if ($canEditContentSearch): ?>
                    <a href="event-edit.php?id=<?= (int) $e['id'] ?>"><?= $eTitle ?></a>
                    <?php else: ?>
                    <span class="admin-search-readonly"><?= $eTitle ?></span>
                    <?php endif; ?>
                    <span class="admin-recent-list__meta"><?= htmlspecialchars((string) ($e['status'] ?? '')) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

        <section class="admin-section">
            <h2 class="admin-section__title">Блог</h2>
            <?php if (!$results['posts']): ?><p class="admin-empty">Ничего не найдено.</p><?php else: ?>
            <ul class="admin-recent-list">
                <?php foreach ($results['posts'] as $p): ?>
                <li>
                    <?php $pTitle = htmlspecialchars((string) ($p['title'] ?? '')); ?>
                    <?php if ($canEditContentSearch): ?>
                    <a href="post-edit.php?id=<?= (int) $p['id'] ?>"><?= $pTitle ?></a>
                    <?php else: ?>
                    <span class="admin-search-readonly"><?= $pTitle ?></span>
                    <?php endif; ?>
                    <span class="admin-recent-list__meta"><?= htmlspecialchars((string) ($p['status'] ?? '')) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
