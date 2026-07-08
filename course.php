<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/data.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/seo.php';
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if (!$slug) { header('Location: /courses.php'); exit; }
$c = get_course_by_slug($slug);
if (!$c) {
    header('HTTP/1.0 404 Not Found');
    $cmsSeoTitle = 'Курс не найден — Easy People';
    $cmsSeoNoindex = true;
    require_once $_SERVER['DOCUMENT_ROOT'] . '/include/header.php';
    echo '<div class="wrapper"><div class="container"><h1>' . htmlspecialchars(ep_t('course.not_found'), ENT_QUOTES, 'UTF-8') . '</h1><p><a href="/courses.php">' . htmlspecialchars(ep_t('course.back_to_list'), ENT_QUOTES, 'UTF-8') . '</a></p></div></div>';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/include/footer.php';
    exit;
}
$teacherName = trim(($c['teacher_name'] ?? '') . ' ' . ($c['teacher_surname'] ?? ''));
$teacher = null;
if (!empty($c['teacher_id'])) {
    $teacher = get_teacher_by_id((int) $c['teacher_id']);
}

function ep_public_img_src(string $path): string
{
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    if (($path[0] ?? '') !== '/') return '/' . $path;
    return $path;
}

$courseCover = ep_public_img_src((string) ($c['image'] ?? ''));
$teacherAvatar = ep_public_img_src((string) (($teacher['image'] ?? '') ?: '/images/member-icon-2.png'));

function ep_format_price_byn(string $raw): string
{
    $s = trim($raw);
    if ($s === '') return '';
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

function ep_ru_course_overview_title(string $title): string
{
    $t = trim($title);
    if ($t === '') return '';
    $map = [
        'About This Course' => 'О курсе',
        'Who This Course is for' => 'Для кого этот курс',
        'Skills You Get' => 'Чему вы научитесь',
        'Requirements' => 'Требования',
        'Course Introduction' => 'Введение',
    ];
    return $map[$t] ?? $t;
}

// Comments (public form -> pending moderation)
$commentFlashOk = '';
$commentFlashErr = '';
$commentName = '';
$commentEmail = '';
$commentBody = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'course_comment') {
    $commentName = trim((string) ($_POST['author_name'] ?? ''));
    $commentEmail = trim((string) ($_POST['author_email'] ?? ''));
    $commentBody = trim((string) ($_POST['body'] ?? ''));
    if ($commentName === '' || $commentBody === '') {
        $commentFlashErr = 'Заполните имя и комментарий.';
    } elseif (mb_strlen($commentBody, 'UTF-8') > 5000) {
        $commentFlashErr = 'Комментарий слишком длинный.';
    } elseif ($commentEmail !== '' && !filter_var($commentEmail, FILTER_VALIDATE_EMAIL)) {
        $commentFlashErr = 'Некорректный email.';
    } else {
        try {
            $st = $pdo->prepare("INSERT INTO course_comments (course_id, author_name, author_email, body, status, created_at) VALUES (?,?,?,?,?,?)");
            $st->execute([(int) $c['id'], $commentName, $commentEmail, $commentBody, 'pending', date('Y-m-d H:i:s')]);
            $commentFlashOk = 'Спасибо! Комментарий отправлен на модерацию.';
            $commentName = $commentEmail = $commentBody = '';
        } catch (Throwable $e) {
            $commentFlashErr = 'Не удалось отправить комментарий. Попробуйте позже.';
        }
    }
}

// Overview blocks
$overviewBlocks = [];
try {
    $st = $pdo->prepare('SELECT * FROM course_overview_blocks WHERE course_id = ? ORDER BY sort_order ASC, id ASC');
    $st->execute([(int) $c['id']]);
    $overviewBlocks = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $overviewBlocks = [];
}
$overviewListItemsByBlock = [];
if ($overviewBlocks) {
    $ids = array_values(array_filter(array_map(static fn($b) => (int) ($b['id'] ?? 0), $overviewBlocks)));
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT * FROM course_overview_list_items WHERE block_id IN ($in) ORDER BY block_id ASC, sort_order ASC, id ASC");
        $st->execute($ids);
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $bid = (int) ($r['block_id'] ?? 0);
            if (!isset($overviewListItemsByBlock[$bid])) $overviewListItemsByBlock[$bid] = [];
            $overviewListItemsByBlock[$bid][] = $r;
        }
    }
}
if (!$overviewBlocks && !empty($c['description'])) {
    $overviewBlocks = [[
        'id' => 0,
        'block_type' => 'text',
        'title' => 'О курсе',
        'body' => (string) $c['description'],
        'image' => '',
    ]];
}

// Curriculum
$modules = [];
$itemsByModule = [];
try {
    $st = $pdo->prepare('SELECT * FROM course_curriculum_modules WHERE course_id = ? ORDER BY sort_order ASC, id ASC');
    $st->execute([(int) $c['id']]);
    $modules = $st->fetchAll(PDO::FETCH_ASSOC);
    if ($modules) {
        $mids = array_values(array_filter(array_map(static fn($m) => (int) ($m['id'] ?? 0), $modules)));
        if ($mids) {
            $in = implode(',', array_fill(0, count($mids), '?'));
            $st = $pdo->prepare("SELECT * FROM course_curriculum_items WHERE module_id IN ($in) ORDER BY module_id ASC, sort_order ASC, id ASC");
            $st->execute($mids);
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $mid = (int) ($r['module_id'] ?? 0);
                if (!isset($itemsByModule[$mid])) $itemsByModule[$mid] = [];
                $itemsByModule[$mid][] = $r;
            }
        }
    }
} catch (Throwable $e) {
    $modules = [];
    $itemsByModule = [];
}

$computedLessonsCount = 0;
foreach ($itemsByModule as $rows) {
    if (is_array($rows)) $computedLessonsCount += count($rows);
}
$lessonsCount = max((int) ($c['lessons_count'] ?? 0), (int) $computedLessonsCount);
$studentsCount = (int) ($c['students_count'] ?? 0);
$priceLabel = ep_format_price_byn((string) ($c['price'] ?? ''));

// Reviews
$reviews = [];
try {
    $st = $pdo->prepare("SELECT * FROM course_reviews WHERE course_id = ? AND status = 'published' ORDER BY sort_order ASC, id ASC");
    $st->execute([(int) $c['id']]);
    $reviews = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $reviews = [];
}
$publishedComments = [];
try {
    $st = $pdo->prepare("SELECT * FROM course_comments WHERE course_id = ? AND status = 'published' ORDER BY created_at DESC, id DESC LIMIT 200");
    $st->execute([(int) $c['id']]);
    $publishedComments = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $publishedComments = [];
}
$reviewCount = count($reviews);
$avgRating = 0.0;
$starsCount = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
if ($reviewCount > 0) {
    $sum = 0.0;
    foreach ($reviews as $r) {
        $val = (float) str_replace(',', '.', (string) ($r['rating'] ?? '0'));
        if ($val < 0) $val = 0;
        if ($val > 5) $val = 5;
        $sum += $val;
        $bucket = (int) round($val);
        if ($bucket < 1) $bucket = 1;
        if ($bucket > 5) $bucket = 5;
        $starsCount[$bucket] = ($starsCount[$bucket] ?? 0) + 1;
    }
    $avgRating = $sum / $reviewCount;
}

$base = cms_public_base_url();
$plainDesc = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($c['description'] ?? ''))));
$cmsSeoTitle = cms_seo_entity_title($c, (string) $c['title']);
$cmsSeoDescription = cms_seo_entity_description($c, $plainDesc !== '' ? $plainDesc : ($cmsSeoTitle . ' — курс Easy People.'));
$cmsSeoCanonical = $base . '/course.php?slug=' . rawurlencode((string) $c['slug']);
$cmsSeoOgImage = cms_seo_abs_media_url($c['image'] ?? '');
$cmsSeoType = 'article';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/header.php';
$epUi = static fn(string $k) => htmlspecialchars(ep_t($k), ENT_QUOTES, 'UTF-8');
?>
<div class="wrapper">
    <div class="main__breadcrumbs breadcrumbs">
        <div class="container">
            <ul class="breadcrumbs__list">
                <li class="breadcrumbs__list-item">
                    <a class="breadcrumbs__list-link" href="/"><?= $epUi('nav.home') ?></a>
                </li>
                <li class="breadcrumbs__list-item">
                    <a class="breadcrumbs__list-link" href="/courses.php"><?= $epUi('courses.title') ?></a>
                </li>
                <li class="breadcrumbs__list-item">
                    <p class="breadcrumbs__list-text"><?= htmlspecialchars((string) ($c['title'] ?? '')) ?></p>
                </li>
            </ul>
        </div>
    </div>

    <div class="section-top">
        <div class="container">
            <div class="section-top__inner">
                <div class="section-top__content section-top-content">
                    <?php if (!empty($c['category'])): ?>
                        <p class="section-top-content__suptext"><?= htmlspecialchars((string) $c['category']) ?></p>
                    <?php endif; ?>
                    <h1 class="section-top-content__title title"><?= htmlspecialchars((string) ($c['title'] ?? '')) ?></h1>

                    <ul class="section-top-content__list card-list">
                        <li class="card-list__item">
                            <p class="card-list__rait">
                                <span><?= htmlspecialchars((string) ($c['rating'] ?? '')) ?></span>
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <g clip-path="url(#clip0_114_9488)">
                                        <path d="M11.9687 4.60317C11.8902 4.36018 11.6746 4.1876 11.4197 4.16462L7.95614 3.85013L6.58656 0.644511C6.48558 0.40958 6.25559 0.257507 6.00006 0.257507C5.74453 0.257507 5.51454 0.40958 5.41356 0.64506L4.04399 3.85013L0.579908 4.16462C0.325385 4.18815 0.110414 4.36018 0.0314019 4.60317C-0.0476102 4.84616 0.0253592 5.11267 0.2179 5.28068L2.83592 7.5767L2.06392 10.9773C2.00744 11.2274 2.10448 11.4858 2.31195 11.6358C2.42346 11.7164 2.55393 11.7574 2.68549 11.7574C2.79893 11.7574 2.91145 11.7268 3.01244 11.6664L6.00006 9.88077L8.98659 11.6664C9.20513 11.7978 9.48062 11.7858 9.68762 11.6358C9.89518 11.4854 9.99214 11.2268 9.93565 10.9773L9.16366 7.5767L11.7817 5.28113C11.9742 5.11267 12.0477 4.84661 11.9687 4.60317Z" fill="#F9D442" />
                                    </g>
                                    <defs>
                                        <clipPath id="clip0_114_9488"><rect width="12" height="12" fill="white" /></clipPath>
                                    </defs>
                                </svg>
                            </p>
                        </li>
                        <li class="card-list__item">
                            <p class="card-list__text"><?= (int) $lessonsCount ?> <?= $epUi('common.lessons') ?></p>
                        </li>
                        <li class="card-list__item">
                            <p class="card-list__text"><?= (int) $studentsCount ?> <?= $epUi('common.students') ?></p>
                        </li>
                        <?php if ($teacherName !== ''): ?>
                        <li class="card-list__item">
                            <a class="card-list__link" href="/teachers.php">
                                <?= $epUi('common.teacher') ?> <?= htmlspecialchars($teacherName) ?>
                                <img class="card-list__link-img" src="<?= htmlspecialchars($teacherAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="img">
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>

                    <div class="section-top-content__buttons">
                        <a
                            class="section-top-content__buttons-link"
                            data-fancybox=""
                            href="#report-popup"
                            data-signup-message="<?= htmlspecialchars(ep_t('course.question_msg') . ' ' . (string)($c['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        ><?= $epUi('course.ask') ?></a>
                    </div>
                </div>
                <div class="section-top__img">
                    <?php if ($courseCover !== ''): ?>
                        <img class="section-top__img-image" src="<?= htmlspecialchars($courseCover, ENT_QUOTES, 'UTF-8') ?>" alt="img">
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <section class="main__courses-section courses-section">
        <div class="container">
            <div class="courses-section__tabs tabs">
                <button class="tabs__btn tabs__btn--active" type="button" id="0"><?= $epUi('course.tab_overview') ?></button>
                <button class="tabs__btn" type="button" id="1"><?= $epUi('course.tab_curriculum') ?></button>
                <button class="tabs__btn" type="button" id="2"><?= $epUi('course.tab_instructor') ?></button>
                <button class="tabs__btn" type="button" id="3">
                    <?= $epUi('course.tab_reviews') ?>
                    <span><?= (int) $reviewCount ?></span>
                </button>
            </div>

            <div class="courses-section__inner">
                <div class="courses-section__wrapper courses-section__wrapper--active" id="0">
                    <section class="courses-section__overview courses-section-overview">
                        <?php foreach ($overviewBlocks as $b): ?>
                            <?php $type = (string) ($b['block_type'] ?? 'text'); ?>
                            <?php $bid = (int) ($b['id'] ?? 0); ?>
                            <div class="courses-section-overview__box courses-section-overview-box">
                                <?php if (!empty($b['title'])): ?>
                                    <h2 class="courses-section-overview-box__title"><?= htmlspecialchars(ep_ru_course_overview_title((string) $b['title'])) ?></h2>
                                <?php endif; ?>

                                <?php if ($type === 'list'): ?>
                                    <ul class="courses-section-overview-box__list">
                                        <?php foreach (($overviewListItemsByBlock[$bid] ?? []) as $li): ?>
                                            <?php $t = trim((string) ($li['text'] ?? '')); if ($t === '') continue; ?>
                                            <li class="courses-section-overview-box__list-item"><?= htmlspecialchars($t) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php elseif ($type === 'image'): ?>
                                    <?php $img = ep_public_img_src((string) ($b['image'] ?? '')); ?>
                                    <?php if ($img !== ''): ?>
                                        <div class="courses-section-overview-box__img">
                                            <img class="courses-section-overview-box__img-image" src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="img">
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php
                                    $txt = trim((string) ($b['body'] ?? ''));
                                    $parts = preg_split('/\n{2,}/u', $txt) ?: [];
                                    foreach ($parts as $p) {
                                        $p = trim($p);
                                        if ($p === '') continue;
                                        echo '<p class="courses-section-overview-box__text">' . nl2br(htmlspecialchars($p)) . '</p>';
                                    }
                                    ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </section>

                    <section class="courses-section__comment comment">
                        <div class="comment__inner">
                            <h2 class="comment__title">Оставить комментарий</h2>
                            <?php if ($commentFlashOk !== ''): ?>
                                <div class="ep-comment-flash ep-comment-flash--ok" role="status"><?= htmlspecialchars($commentFlashOk) ?></div>
                            <?php endif; ?>
                            <?php if ($commentFlashErr !== ''): ?>
                                <div class="ep-comment-flash ep-comment-flash--err" role="alert"><?= htmlspecialchars($commentFlashErr) ?></div>
                            <?php endif; ?>
                            <form id="course-comment-form" class="comment__form" method="post" action="/course.php?slug=<?= rawurlencode((string) $c['slug']) ?>#comment">
                                <input type="hidden" name="action" value="course_comment">
                                <div id="comment"></div>
                                <input
                                    class="comment__form-input"
                                    type="text"
                                    name="author_name"
                                    placeholder="Ваше имя"
                                    value="<?= htmlspecialchars($commentName, ENT_QUOTES, 'UTF-8') ?>"
                                    autocomplete="name"
                                    required
                                >
                                <input
                                    class="comment__form-input"
                                    type="email"
                                    name="author_email"
                                    placeholder="Email (необязательно)"
                                    value="<?= htmlspecialchars($commentEmail, ENT_QUOTES, 'UTF-8') ?>"
                                    autocomplete="email"
                                    inputmode="email"
                                >
                                <textarea class="comment__form-textarea" name="body" placeholder="Комментарий" rows="4" required><?= htmlspecialchars($commentBody) ?></textarea>
                                <button class="comment__form-btn" type="submit">Отправить</button>
                            </form>
                        </div>
                    </section>
                    <style>
                        .ep-comment-flash{
                            margin: 10px 0 14px;
                            padding: 10px 12px;
                            border-radius: 12px;
                            font-size: 14px;
                            line-height: 1.35;
                            border: 1px solid rgba(255,255,255,0.12);
                            background: rgba(255,255,255,0.04);
                        }
                        .ep-comment-flash--ok{
                            border-color: rgba(109,153,133,0.55);
                            background: rgba(109,153,133,0.16);
                            color: #d1fae5;
                        }
                        .ep-comment-flash--err{
                            border-color: rgba(233,69,96,0.55);
                            background: rgba(233,69,96,0.14);
                            color: #ffd1d8;
                        }
                        .comment__form-input{
                            display:block;
                            width:100%;
                            box-sizing:border-box;
                            margin-bottom:12px;
                        }
                        .comment__form-btn[disabled]{
                            opacity:0.7;
                            cursor:not-allowed;
                        }
                    </style>
                    <script>
                        (function () {
                            var form = document.getElementById('course-comment-form');
                            if (!form) return;
                            form.addEventListener('submit', function () {
                                var btn = form.querySelector('button[type="submit"]');
                                if (!btn) return;
                                btn.disabled = true;
                                btn.setAttribute('aria-busy', 'true');
                                btn.textContent = 'Отправка...';
                            });
                        })();
                    </script>
                </div>

                <div class="courses-section__wrapper" id="1">
                    <section class="courses-section__curriculum courses-section-curriculum">
                        <?php if (!$modules): ?>
                            <p class="courses-section-overview-box__text">Пока нет программы курса.</p>
                        <?php endif; ?>

                        <?php foreach ($modules as $m): ?>
                            <?php $mid = (int) ($m['id'] ?? 0); ?>
                            <?php $isOpen = !empty($m['is_open']); ?>
                            <div class="courses-section-curriculum__item courses-section-curriculum-item<?= $isOpen ? ' courses-section-curriculum-item--acitve' : '' ?>">
                                <div class="courses-section-curriculum-item__top">
                                    <h3 class="courses-section-curriculum-item__top-title"><?= htmlspecialchars((string) ($m['title'] ?? '')) ?></h3>
                                </div>
                                <div class="courses-section-curriculum-item__inner">
                                    <?php foreach (($itemsByModule[$mid] ?? []) as $it): ?>
                                        <?php
                                        $state = (string) ($it['state'] ?? 'active');
                                        $mod = $state === 'checked' ? '--checked' : ($state === 'blocked' ? '--blocked' : '--active');
                                        $progress = (int) ($it['progress_percent'] ?? 0);
                                        if ($progress < 0) $progress = 0;
                                        if ($progress > 100) $progress = 100;
                                        $dur = (string) ($it['duration_label'] ?? '');
                                        $act = (string) ($it['action'] ?? 'play');
                                        $url = trim((string) ($it['action_url'] ?? '#'));
                                        if ($url === '') $url = '#';
                                        ?>
                                        <div class="courses-section-curriculum-item__box courses-section-curriculum-item-box courses-section-curriculum-item-box<?= $mod ?>">
                                            <h4 class="courses-section-curriculum-item-box__title">
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M10 2V10L13 7L16 10V2" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                                    <path d="M4 19.5C4 18.837 4.26339 18.2011 4.73223 17.7322C5.20107 17.2634 5.83696 17 6.5 17H20" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                                    <path d="M6.5 2H20V22H6.5C5.83696 22 5.20107 21.7366 4.73223 21.2678C4.26339 20.7989 4 20.163 4 19.5V4.5C4 3.83696 4.26339 3.20107 4.73223 2.73223C5.20107 2.26339 5.83696 2 6.5 2Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                                <span><?= htmlspecialchars((string) ($it['title'] ?? '')) ?></span>
                                            </h4>
                                            <div class="courses-section-curriculum-item__box-wrapper">
                                                <p class="courses-section-curriculum-item-box__procents"><?= (int) $progress ?>%</p>
                                                <p class="courses-section-curriculum-item-box__time"><?= htmlspecialchars($dur) ?></p>
                                                <a class="courses-section-curriculum-item-box__link" href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">
                                                    <?php if ($act === 'lock'): ?>
                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M12.6667 7.33337H3.33333C2.59695 7.33337 2 7.93033 2 8.66671V13.3334C2 14.0698 2.59695 14.6667 3.33333 14.6667H12.6667C13.403 14.6667 14 14.0698 14 13.3334V8.66671C14 7.93033 13.403 7.33337 12.6667 7.33337Z" stroke="#7D838C" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                                            <path d="M4.66699 7.33337V4.66671C4.66699 3.78265 5.01818 2.93481 5.6433 2.30968C6.26842 1.68456 7.11627 1.33337 8.00033 1.33337C8.88438 1.33337 9.73223 1.68456 10.3573 2.30968C10.9825 2.93481 11.3337 3.78265 11.3337 4.66671V7.33337" stroke="#7D838C" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                                        </svg>
                                                    <?php elseif ($act === 'check'): ?>
                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M11.9997 4L4.66634 11.3333L1.33301 8" stroke="#6D9985" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                                            <path d="M14.667 6.66669L9.66699 11.6667L8.66699 10.6667" stroke="#6D9985" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                                        </svg>
                                                    <?php else: ?>
                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M3.33301 2L12.6663 8L3.33301 14V2Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                                        </svg>
                                                    <?php endif; ?>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </section>
                </div>

                <div class="courses-section__wrapper" id="2">
                    <section class="courses-section__instructor courses-section-instructor">
                        <div class="courses-section-instructor__inner">
                            <div class="courses-section-instructor__img">
                                <img class="courses-section-instructor__img-image" src="<?= htmlspecialchars($teacherAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="img">
                            </div>
                            <div class="courses-section-instructor__content courses-section-instructor-content">
                                <p class="courses-section-instructor-content__suptext"><?= htmlspecialchars((string) (($teacher['role'] ?? '') ?: 'instructor')) ?></p>
                                <p class="courses-section-instructor-content__name"><?= htmlspecialchars($teacherName !== '' ? $teacherName : ((string) ($teacher['name'] ?? ''))) ?></p>
                                <p class="courses-section-instructor-content__text"><?= nl2br(htmlspecialchars((string) (($teacher['bio'] ?? '') ?: ''))) ?></p>
                                <?php if (!empty($teacher['social_link'])): ?>
                                <ul class="courses-section-instructor-content__socials socials">
                                    <li class="socials__item">
                                        <a class="socials__link" href="<?= htmlspecialchars((string) $teacher['social_link'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd" clip-rule="evenodd" d="M9.855 7.57844C9.69281 7.20344 9.52375 6.82906 9.34344 6.45875C10.8831 5.79187 12.1206 4.88625 13.0425 3.73781C13.9419 4.80062 14.5066 6.15031 14.5897 7.6275C12.8731 7.34656 11.2944 7.33094 9.855 7.57844ZM11.6681 13.4931C11.3763 11.925 10.9478 10.3916 10.3884 8.89563C11.6388 8.71875 13.0153 8.76469 14.5206 9.02656C14.2294 10.8837 13.1694 12.4881 11.6681 13.4931ZM3.94438 13.2069C5.25688 11.1478 6.93281 9.8075 9.00937 9.18875C9.62719 10.8025 10.0822 12.4619 10.3716 14.1619C8.20844 14.9978 5.77781 14.6388 3.94438 13.2069ZM1.40031 7.82969C3.96562 7.82594 6.17875 7.53125 8.03312 6.94781C8.18844 7.26438 8.33719 7.5825 8.47937 7.90281C6.22719 8.60656 4.37031 10.05 2.92219 12.2228C1.87969 10.9716 1.36063 9.45406 1.40031 7.82969ZM5.01844 2.11C5.90625 3.27156 6.69219 4.46906 7.37625 5.69781C5.75594 6.17469 3.8225 6.41969 1.58656 6.435C2.04844 4.545 3.32094 2.97312 5.01844 2.11ZM12.0288 2.77312C11.2256 3.80875 10.1169 4.62781 8.70406 5.22594C8.02719 3.98562 7.25344 2.77563 6.37937 1.60125C8.35812 1.09656 10.405 1.51906 12.0288 2.77312ZM7.99969 0C3.58156 0 0 3.58187 0 8C0 12.4181 3.58156 16 7.99969 16C12.4184 16 16 12.4181 16 8C16 3.58187 12.4181 0 7.99969 0Z" fill="white" />
                                            </svg>
                                        </a>
                                    </li>
                                </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="courses-section__wrapper" id="3">
                    <section class="courses-section__reviews reviews">
                        <div class="reviews__top reviews-top">
                            <div class="reviews-top__content reviews-top-content">
                                <p class="reviews-top-content__rait"><?= number_format($avgRating, 1, '.', '') ?></p>
                                <div class="reviews-top-content__stars stars">
                                    <?php
                                    $filled = (int) round($avgRating);
                                    for ($i = 1; $i <= 5; $i++) {
                                        $disabled = $i > $filled ? ' stars-star--disabled' : '';
                                        echo '<div class="stars__star stars-star' . $disabled . '"><svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_114_9488)"><path d="M11.9687 4.60317C11.8902 4.36018 11.6746 4.1876 11.4197 4.16462L7.95614 3.85013L6.58656 0.644511C6.48558 0.40958 6.25559 0.257507 6.00006 0.257507C5.74453 0.257507 5.51454 0.40958 5.41356 0.64506L4.04399 3.85013L0.579908 4.16462C0.325385 4.18815 0.110414 4.36018 0.0314019 4.60317C-0.0476102 4.84616 0.0253592 5.11267 0.2179 5.28068L2.83592 7.5767L2.06392 10.9773C2.00744 11.2274 2.10448 11.4858 2.31195 11.6358C2.42346 11.7164 2.55393 11.7574 2.68549 11.7574C2.79893 11.7574 2.91145 11.7268 3.01244 11.6664L6.00006 9.88077L8.98659 11.6664C9.20513 11.7978 9.48062 11.7858 9.68762 11.6358C9.89518 11.4854 9.99214 11.2268 9.93565 10.9773L9.16366 7.5767L11.7817 5.28113C11.9742 5.11267 12.0477 4.84661 11.9687 4.60317Z" fill="#F9D442"/></g><defs><clipPath id="clip0_114_9488"><rect width="12" height="12" fill="white"/></clipPath></defs></svg></div>';
                                    }
                                    ?>
                                </div>
                                <p class="reviews-top-content__subtext"><?= (int) $reviewCount ?> rating<?= $reviewCount === 1 ? '' : 's' ?></p>
                            </div>
                            <div class="reviews-top__body reviews-top-body">
                                <?php for ($s = 5; $s >= 1; $s--): ?>
                                    <?php
                                    $cnt = (int) ($starsCount[$s] ?? 0);
                                    $pct = $reviewCount > 0 ? (int) round(($cnt / $reviewCount) * 100) : 0;
                                    ?>
                                    <div class="reviews-top-body__item reviews-top-body-item">
                                        <div class="reviews-top-body-item__stars stars">
                                            <?php for ($i = 1; $i <= $s; $i++): ?>
                                                <div class="stars__star stars-star">
                                                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <g clip-path="url(#clip0_114_9488)">
                                                            <path d="M11.9687 4.60317C11.8902 4.36018 11.6746 4.1876 11.4197 4.16462L7.95614 3.85013L6.58656 0.644511C6.48558 0.40958 6.25559 0.257507 6.00006 0.257507C5.74453 0.257507 5.51454 0.40958 5.41356 0.64506L4.04399 3.85013L0.579908 4.16462C0.325385 4.18815 0.110414 4.36018 0.0314019 4.60317C-0.0476102 4.84616 0.0253592 5.11267 0.2179 5.28068L2.83592 7.5767L2.06392 10.9773C2.00744 11.2274 2.10448 11.4858 2.31195 11.6358C2.42346 11.7164 2.55393 11.7574 2.68549 11.7574C2.79893 11.7574 2.91145 11.7268 3.01244 11.6664L6.00006 9.88077L8.98659 11.6664C9.20513 11.7978 9.48062 11.7858 9.68762 11.6358C9.89518 11.4854 9.99214 11.2268 9.93565 10.9773L9.16366 7.5767L11.7817 5.28113C11.9742 5.11267 12.0477 4.84661 11.9687 4.60317Z" fill="#F9D442" />
                                                        </g>
                                                        <defs><clipPath id="clip0_114_9488"><rect width="12" height="12" fill="white"/></clipPath></defs>
                                                    </svg>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="reviews-top-body-item__line"><span style="width: <?= (int) $pct ?>%"></span></div>
                                        <p class="reviews-top-body-item__procents"><?= (int) $pct ?>%</p>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="reviews__inner">
                            <?php if (!$reviews): ?>
                                <p class="reviews-comment-body__text">Пока нет отзывов.</p>
                            <?php endif; ?>
                            <?php foreach ($reviews as $r): ?>
                                <?php
                                $rAvatar = ep_public_img_src((string) ($r['author_avatar'] ?? ''));
                                if ($rAvatar === '') $rAvatar = '/images/member-icon-21.png';
                                $rName = trim((string) ($r['author_name'] ?? ''));
                                if ($rName === '') $rName = 'Student';
                                $rBody = trim((string) ($r['body'] ?? ''));
                                $rRating = (float) str_replace(',', '.', (string) ($r['rating'] ?? '5.0'));
                                if ($rRating < 0) $rRating = 0;
                                if ($rRating > 5) $rRating = 5;
                                $rFilled = (int) round($rRating);
                                ?>
                                <div class="reviews__comment reviews-comment">
                                    <div class="reviews-comment__img">
                                        <img class="reviews-comment__img-image" src="<?= htmlspecialchars($rAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="img">
                                    </div>
                                    <div class="reviews-comment__body reviews-comment-body">
                                        <div class="reviews-comment-body__top reviews-comment-body-top">
                                            <div class="reviews-comment-body-top__box">
                                                <p class="reviews-comment-body-top__box-text"><?= htmlspecialchars($rName) ?></p>
                                                <p class="reviews-comment-body-top__box-subtext"></p>
                                            </div>
                                            <div class="reviews-comment-body-top__stars stars">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <?php $disabled = $i > $rFilled ? ' stars-star--disabled' : ''; ?>
                                                    <div class="stars__star stars-star<?= $disabled ?>">
                                                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <g clip-path="url(#clip0_114_9488)">
                                                                <path d="M11.9687 4.60317C11.8902 4.36018 11.6746 4.1876 11.4197 4.16462L7.95614 3.85013L6.58656 0.644511C6.48558 0.40958 6.25559 0.257507 6.00006 0.257507C5.74453 0.257507 5.51454 0.40958 5.41356 0.64506L4.04399 3.85013L0.579908 4.16462C0.325385 4.18815 0.110414 4.36018 0.0314019 4.60317C-0.0476102 4.84616 0.0253592 5.11267 0.2179 5.28068L2.83592 7.5767L2.06392 10.9773C2.00744 11.2274 2.10448 11.4858 2.31195 11.6358C2.42346 11.7164 2.55393 11.7574 2.68549 11.7574C2.79893 11.7574 2.91145 11.7268 3.01244 11.6664L6.00006 9.88077L8.98659 11.6664C9.20513 11.7978 9.48062 11.7858 9.68762 11.6358C9.89518 11.4854 9.99214 11.2268 9.93565 10.9773L9.16366 7.5767L11.7817 5.28113C11.9742 5.11267 12.0477 4.84661 11.9687 4.60317Z" fill="#F9D442" />
                                                            </g>
                                                            <defs><clipPath id="clip0_114_9488"><rect width="12" height="12" fill="white"/></clipPath></defs>
                                                        </svg>
                                                    </div>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <p class="reviews-comment-body__text"><?= nl2br(htmlspecialchars($rBody)) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($publishedComments): ?>
                        <div class="reviews__inner" style="margin-top:18px;">
                            <?php foreach ($publishedComments as $cm): ?>
                                <?php
                                $cmName = trim((string) ($cm['author_name'] ?? ''));
                                if ($cmName === '') $cmName = 'Student';
                                $cmBody = trim((string) ($cm['body'] ?? ''));
                                $cmDate = trim((string) ($cm['created_at'] ?? ''));
                                ?>
                                <div class="reviews__comment reviews-comment">
                                    <div class="reviews-comment__img">
                                        <img class="reviews-comment__img-image" src="/images/member-icon-21.png" alt="img">
                                    </div>
                                    <div class="reviews-comment__body reviews-comment-body">
                                        <div class="reviews-comment-body__top reviews-comment-body-top">
                                            <div class="reviews-comment-body-top__box">
                                                <p class="reviews-comment-body-top__box-text"><?= htmlspecialchars($cmName) ?></p>
                                                <p class="reviews-comment-body-top__box-subtext"><?= htmlspecialchars($cmDate) ?></p>
                                            </div>
                                        </div>
                                        <p class="reviews-comment-body__text"><?= nl2br(htmlspecialchars($cmBody)) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </section>
                </div>

                <aside class="courses-section__aside courses-section-aside">
                    <div class="courses-section-aside__aside-cost-info aside-cost-info">
                        <?php if ($priceLabel !== ''): ?>
                            <p class="aside-cost-info__price"><?= htmlspecialchars($priceLabel) ?></p>
                        <?php endif; ?>
                        <?php
                        $levelLabel = trim((string) ($c['level_label'] ?? ''));
                        $durationLabel = trim((string) ($c['duration_label'] ?? ''));
                        $languageLabel = trim((string) ($c['language_label'] ?? ''));
                        $certificateLabel = trim((string) ($c['certificate_label'] ?? ''));
                        $certificateEnabled = $c['certificate_enabled'] ?? null;
                        $certificateText = '—';
                        if ($certificateEnabled === 1 || $certificateEnabled === '1') {
                            $certificateText = 'Да';
                        } elseif ($certificateEnabled === 0 || $certificateEnabled === '0') {
                            $certificateText = 'Нет';
                        } elseif ($certificateLabel !== '') {
                            $low = mb_strtolower($certificateLabel, 'UTF-8');
                            if (in_array($low, ['yes', 'true', '1', 'да', 'есть'], true)) {
                                $certificateText = 'Да';
                            } elseif (in_array($low, ['no', 'false', '0', 'нет'], true)) {
                                $certificateText = 'Нет';
                            } else {
                                $certificateText = $certificateLabel;
                            }
                        }
                        $quizzesCount = (int) ($c['quizzes_count'] ?? 0);
                        ?>
                        <ul class="aside-cost-info__list">
                            <li class="aside-cost-info__list-item">
                                <p class="aside-cost-info__list-text">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M10 2V10L13 7L16 10V2" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M4 19.5C4 18.837 4.26339 18.2011 4.73223 17.7322C5.20107 17.2634 5.83696 17 6.5 17H20" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M6.5 2H20V22H6.5C5.83696 22 5.20107 21.7366 4.73223 21.2678C4.26339 20.7989 4 20.163 4 19.5V4.5C4 3.83696 4.26339 3.20107 4.73223 2.73223C5.20107 2.26339 5.83696 2 6.5 2Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span><?= $epUi('course.lessons') ?></span>
                                </p>
                                <p class="aside-cost-info__list-text"><?= (int) $lessonsCount ?></p>
                            </li>
                            <li class="aside-cost-info__list-item">
                                <p class="aside-cost-info__list-text">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M18 20V10" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M12 20V4" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M6 20V14" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span><?= $epUi('course.level') ?></span>
                                </p>
                                <p class="aside-cost-info__list-text"><?= htmlspecialchars($levelLabel !== '' ? $levelLabel : '—') ?></p>
                            </li>
                            <li class="aside-cost-info__list-item">
                                <p class="aside-cost-info__list-text">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M21 7.5V6C21 5.46957 20.7893 4.96086 20.4142 4.58579C20.0391 4.21071 19.5304 4 19 4H5C4.46957 4 3.96086 4.21071 3.58579 4.58579C3.21071 4.96086 3 5.46957 3 6V20C3 20.5304 3.21071 21.0391 3.58579 21.4142C3.96086 21.7893 4.46957 22 5 22H8.5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M16 2V6" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M8 2V6" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M3 10H8" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M17.5 17.5L16 16.25V14" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M22 16C22 17.5913 21.3679 19.1174 20.2426 20.2426C19.1174 21.3679 17.5913 22 16 22C14.4087 22 12.8826 21.3679 11.7574 20.2426C10.6321 19.1174 10 17.5913 10 16C10 14.4087 10.6321 12.8826 11.7574 11.7574C12.8826 10.6321 14.4087 10 16 10C17.5913 10 19.1174 10.6321 20.2426 11.7574C21.3679 12.8826 22 14.4087 22 16Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span><?= $epUi('course.duration') ?></span>
                                </p>
                                <p class="aside-cost-info__list-text"><?= htmlspecialchars($durationLabel !== '' ? $durationLabel : '—') ?></p>
                            </li>
                            <li class="aside-cost-info__list-item">
                                <p class="aside-cost-info__list-text">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M1.5 10C4.33333 9.33335 9.2 8.9 8 12.5C6.5 17 7 16 8 18C8.8 19.6 8.33333 21.3333 8 22M5 4C6.5 3.66667 10 3.4 12 5C14.5 7 14 10 21 6.00001M23 12C23 18.0751 18.0751 23 12 23C5.92487 23 1 18.0751 1 12C1 5.92487 5.92487 1 12 1C18.0751 1 23 5.92487 23 12ZM14.3272 12.299C17.8272 11.799 19 11.5 18 14C17.0715 16.3212 14.3272 20.9657 12.3272 16.299C11.8272 15.1324 11.5272 12.699 14.3272 12.299Z" stroke="white" stroke-width="1.5" stroke-linecap="round" />
                                    </svg>
                                    <span><?= $epUi('course.language') ?></span>
                                </p>
                                <p class="aside-cost-info__list-text"><?= htmlspecialchars($languageLabel !== '' ? $languageLabel : '—') ?></p>
                            </li>
                            <li class="aside-cost-info__list-item">
                                <p class="aside-cost-info__list-text">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 14C15.3137 14 18 11.3137 18 8C18 4.68629 15.3137 2 12 2C8.68629 2 6 4.68629 6 8C6 11.3137 8.68629 14 12 14Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M15.477 12.89L17 22L12 19L7 22L8.523 12.89" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span><?= $epUi('course.certificate') ?></span>
                                </p>
                                <p class="aside-cost-info__list-text"><?= htmlspecialchars($certificateText) ?></p>
                            </li>
                            <li class="aside-cost-info__list-item">
                                <p class="aside-cost-info__list-text">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M12 11C14.2091 11 16 9.20914 16 7C16 4.79086 14.2091 3 12 3C9.79086 3 8 4.79086 8 7C8 9.20914 9.79086 11 12 11Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span><?= $epUi('common.students') ?></span>
                                </p>
                                <p class="aside-cost-info__list-text"><?= (int) $studentsCount ?></p>
                            </li>
                            <li class="aside-cost-info__list-item">
                                <p class="aside-cost-info__list-text">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M19.439 7.85005C19.39 8.17205 19.4981 8.49805 19.7281 8.72805L21.296 10.296C21.766 10.766 22.0021 11.383 22.0021 12C22.0021 12.617 21.767 13.233 21.296 13.704L19.685 15.315C19.5768 15.4232 19.4447 15.5045 19.2993 15.5524C19.154 15.6004 18.9994 15.6136 18.848 15.591C18.378 15.521 18.046 15.111 17.88 14.666C17.7344 14.2728 17.4922 13.9226 17.1758 13.6474C16.8593 13.3723 16.4788 13.1811 16.0692 13.0915C15.6595 13.0019 15.2339 13.0168 14.8315 13.1347C14.4291 13.2526 14.0628 13.4698 13.7663 13.7663C13.4698 14.0628 13.2526 14.4291 13.1347 14.8315C13.0168 15.2339 13.0019 15.6595 13.0915 16.0692C13.1811 16.4788 13.3723 16.8593 13.6474 17.1758C13.9226 17.4922 14.2728 17.7344 14.666 17.88C15.112 18.046 15.521 18.377 15.591 18.848C15.6137 18.9994 15.6005 19.154 15.5526 19.2994C15.5046 19.4447 15.4233 19.5768 15.315 19.685L13.705 21.295C13.4814 21.5194 13.2157 21.6974 12.9231 21.8187C12.6305 21.94 12.3168 22.0023 12 22.0021C11.6835 22.0025 11.37 21.9403 11.0776 21.8191C10.7852 21.698 10.5195 21.5202 10.296 21.296L8.72805 19.7281C8.61472 19.6145 8.47634 19.5291 8.32403 19.4788C8.17172 19.4284 8.00972 19.4145 7.85105 19.438C7.35805 19.512 7.01105 19.942 6.83105 20.406C6.68017 20.7933 6.43513 21.1368 6.11809 21.4056C5.80105 21.6743 5.42202 21.8598 5.01528 21.9452C4.60853 22.0306 4.18692 22.0132 3.78857 21.8947C3.39022 21.7761 3.02771 21.5602 2.73383 21.2663C2.43995 20.9724 2.22397 20.6099 2.10543 20.2115C1.98689 19.8132 1.96954 19.3916 2.05494 18.9848C2.14033 18.5781 2.32579 18.199 2.59452 17.882C2.86325 17.565 3.20679 17.3199 3.59405 17.169C4.05805 16.989 4.48805 16.642 4.56105 16.149C4.58476 15.9905 4.57097 15.8285 4.52079 15.6762C4.4706 15.5239 4.38541 15.3855 4.27205 15.272L2.70405 13.704C2.4799 13.4805 2.30213 13.2149 2.18097 12.9225C2.05981 12.6301 1.99765 12.3166 1.99805 12C1.99805 11.383 2.23405 10.766 2.70405 10.296L4.23005 8.77005C4.47005 8.53005 4.81105 8.41705 5.14705 8.46705C5.66205 8.54405 6.02405 8.99505 6.22005 9.47705C6.37575 9.8588 6.62344 10.1961 6.94106 10.459C7.25869 10.7218 7.63639 10.902 8.04052 10.9835C8.44466 11.0651 8.86269 11.0454 9.25737 10.9263C9.65206 10.8071 10.0112 10.5922 10.3027 10.3007C10.5942 10.0092 10.8091 9.65006 10.9283 9.25537C11.0474 8.86069 11.0671 8.44266 10.9855 8.03852C10.904 7.63439 10.7238 7.25669 10.461 6.93906C10.1981 6.62144 9.8608 6.37375 9.47905 6.21805C8.99705 6.02205 8.54605 5.66005 8.46905 5.14505C8.41905 4.80905 8.53105 4.46905 8.77205 4.22805L10.297 2.70305C10.5205 2.4792 10.786 2.30168 11.0782 2.1807C11.3705 2.05972 11.6837 1.99765 12 1.99805C12.617 1.99805 13.234 2.23405 13.704 2.70405L15.272 4.27205C15.502 4.50205 15.828 4.61005 16.149 4.56205C16.642 4.48805 16.989 4.05805 17.169 3.59405C17.3199 3.20679 17.565 2.86325 17.882 2.59452C18.199 2.32579 18.5781 2.14033 18.9848 2.05494C19.3916 1.96954 19.8132 1.98689 20.2115 2.10543C20.6099 2.22397 20.9724 2.43995 21.2663 2.73383C21.5602 3.02771 21.7761 3.39022 21.8947 3.78857C22.0132 4.18692 22.0306 4.60853 21.9452 5.01528C21.8598 5.42202 21.6743 5.80105 21.4056 6.11809C21.1368 6.43513 20.7933 6.68017 20.406 6.83105C19.942 7.01105 19.512 7.35705 19.439 7.85005Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span><?= $epUi('course.quizzes') ?></span>
                                </p>
                                <p class="aside-cost-info__list-text"><?= (int) $quizzesCount ?></p>
                            </li>
                            <?php if ($teacherName !== ''): ?>
                            <li class="aside-cost-info__list-item">
                                <p class="aside-cost-info__list-text">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M12 11C14.2091 11 16 9.20914 16 7C16 4.79086 14.2091 3 12 3C9.79086 3 8 4.79086 8 7C8 9.20914 9.79086 11 12 11Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span><?= $epUi('course.tab_instructor') ?></span>
                                </p>
                                <p class="aside-cost-info__list-text"><?= htmlspecialchars($teacherName) ?></p>
                            </li>
                            <?php endif; ?>
                        </ul>
                        <a
                            class="aside-cost-info__link"
                            data-fancybox=""
                            href="#report-popup"
                            data-signup-message="<?= htmlspecialchars(ep_t('course.signup_msg') . ' ' . (string)($c['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        ><?= $epUi('course.enroll') ?></a>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</div>
<?php
$courseJsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Course',
    'name' => $c['title'],
    'description' => $cmsSeoDescription,
    'url' => $cmsSeoCanonical,
    'provider' => [
        '@type' => 'Organization',
        'name' => 'Easy People',
        'url' => $base,
    ],
];
if ($cmsSeoOgImage !== '') {
    $courseJsonLd['image'] = $cmsSeoOgImage;
}
if ($teacherName !== '') {
    $courseJsonLd['hasCourseInstance'] = [
        '@type' => 'CourseInstance',
        'courseMode' => 'mixed',
        'instructor' => ['@type' => 'Person', 'name' => $teacherName],
    ];
}
echo cms_seo_json_ld_tag($courseJsonLd);
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/footer.php';
