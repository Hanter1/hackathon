<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/data.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/cms-helpers.php';
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if (!$slug) {
    header('Location: /members/');
    exit;
}
$t = get_teacher_by_slug($slug);
if (!$t) {
    header('HTTP/1.0 404 Not Found');
    require_once $_SERVER['DOCUMENT_ROOT'] . '/include/header.php';
    echo '<div class="wrapper"><div class="container"><h1>Наставник не найден</h1><p><a href="/members/">К списку</a></p></div></div>';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/include/footer.php';
    exit;
}
$img = !empty($t['image']) ? $t['image'] : '/images/member-icon-16.png';
$fullName = trim(($t['name'] ?? '') . ' ' . ($t['surname'] ?? ''));
$rolesRaw = trim((string)($t['role'] ?? ''));
$roleItems = preg_split('/[\r\n,]+/u', $rolesRaw) ?: [];
$roleItems = array_values(array_filter(array_map('trim', $roleItems), static fn($v) => $v !== ''));
$rolePrimaryRaw = trim((string)($roleItems[0] ?? ''));
if (function_exists('mb_strimwidth')) {
    $rolePrimaryRaw = mb_strimwidth($rolePrimaryRaw, 0, 64, '...');
}
$rolePrimary = $rolePrimaryRaw;
$roleList = trim((string)implode(' • ', array_slice($roleItems, 0, 3)));
$bio = trim((string)($t['bio'] ?? ''));
$bioLines = preg_split('/\r\n|\r|\n/u', $bio) ?: [];
$bioLines = array_values(array_filter(array_map('trim', $bioLines), static fn($v) => $v !== ''));
$bioParagraph = '';
$bioBullets = [];
foreach ($bioLines as $line) {
    if (preg_match('/^[\-\+\*\x{2022}]/u', $line)) {
        $bioBullets[] = trim(preg_replace('/^[\-\+\*\x{2022}\s]+/u', '', $line));
    } else {
        $bioParagraph .= ($bioParagraph === '' ? '' : ' ') . $line;
    }
}

$teacherCourses = get_courses_by_teacher_id((int) ($t['id'] ?? 0), 'active');
$teacherCoursesCount = count($teacherCourses);
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/header.php';
?>
<section class="main__members-section-top members-section-top">
    <div class="container">
        <div class="members-section-top__inner">
            <div class="members-section-top__img">
                <img class="members-section-top__img-image" src="<?= htmlspecialchars($img) ?>" alt="">
            </div>
            <div class="members-section-top__body members-section-top-body">
                <?php if ($rolePrimary !== ''): ?><p class="members-section-top-body__suptext"><?= htmlspecialchars($rolePrimary) ?></p><?php endif; ?>
                <h2 class="members-section-top-body__title"><?= htmlspecialchars($fullName) ?></h2>
                <?php if ($roleList !== ''): ?><p class="members-section-top-body__link"><?= htmlspecialchars($roleList) ?></p><?php endif; ?>
                <ul class="members-section-top-body__list card-list">
                    <li class="card-list__item"><p class="card-list__text">Наставник Easy People</p></li>
                </ul>
                <div class="members-section-top-body__box members-section-top-body-box">
                    <div class="members-section-top-body-box__buttons">
                        <a class="members-section-top-body-box__buttons-link members-section-top-body-box__buttons-link--green" data-fancybox="" href="#report-popup"><span>Как связаться</span></a>
                        <a class="members-section-top-body-box__buttons-link members-section-top-body-box__buttons-link--purple" href="/members/"><span>Все наставники</span></a>
                        <a class="members-section-top-body-box__buttons-link" data-fancybox="" href="#report-popup"><span>Записаться</span></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="main__members-section members-section">
    <div class="container">
        <div class="members-section__tabs tabs">
            <button class="tabs__btn" type="button" id="0">Лента</button>
            <button class="tabs__btn tabs__btn--active" type="button" id="1">Профиль</button>
            <button class="tabs__btn" type="button" id="2">Группы <span>0</span></button>
            <button class="tabs__btn" type="button" id="3">Друзья <span>0</span></button>
            <button class="tabs__btn" type="button" id="4">Курсы <span><?= (int) $teacherCoursesCount ?></span></button>
            <button class="tabs__btn" type="button" id="5">Ещё</button>
        </div>

        <div class="members-section__inner">
            <div class="members-section__wrapper" id="0">
                <div class="members-section__content">
                    <p>Timeline пока не заполнен.</p>
                </div>
            </div>

            <div class="members-section__wrapper members-section__wrapper--active" id="1">
                <div class="members-section__profile-info profile-info">
                    <div class="profile-info__about profile-info-about">
                        <h2 class="profile-info-about__title">Описание профиля</h2>
                        <?php if ($bio !== ''): ?>
                            <?php if ($bioParagraph !== ''): ?>
                                <p class="profile-info-about__text"><?= htmlspecialchars($bioParagraph) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($bioBullets)): ?>
                                <ul class="profile-info-about__text" style="padding-left:1.1rem; margin:0;">
                                    <?php foreach ($bioBullets as $point): ?>
                                        <li style="margin-bottom:.35rem;"><?= htmlspecialchars($point) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if ($bioParagraph === '' && empty($bioBullets)): ?>
                                <p class="profile-info-about__text"><?= htmlspecialchars($bio) ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="profile-info-about__text">Ключевые компетенции и опыт, достижения пока не заполнены.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="members-section__wrapper" id="2">
                <div class="members-section__content">
                    <p>Группы пока не добавлены.</p>
                </div>
            </div>

            <div class="members-section__wrapper" id="3">
                <div class="members-section__content">
                    <p>Друзья пока не добавлены.</p>
                </div>
            </div>

            <div class="members-section__wrapper" id="4">
                <div class="members-section__content">
                    <?php if (empty($teacherCourses)): ?>
                        <p>Курсы пока не добавлены.</p>
                    <?php else: ?>
                        <div class="courses__inner" style="margin-top: 6px;">
                            <?php foreach ($teacherCourses as $c): ?>
                                <?php
                                $url = '/course.php?slug=' . rawurlencode((string) ($c['slug'] ?? ''));
                                $img = (string) (!empty($c['image']) ? $c['image'] : '/images/new/co1.jpg');
                                $imgSrc = $img;
                                if ($imgSrc !== '' && !preg_match('~^https?://~i', $imgSrc)) {
                                    $imgSrc = ($imgSrc[0] ?? '') === '/' ? $imgSrc : '/' . $imgSrc;
                                }
                                $teacherName = trim(($c['teacher_name'] ?? '') . ' ' . ($c['teacher_surname'] ?? ''));
                                $priceLabel = ep_format_price_byn((string) ($c['price'] ?? ''));
                                ?>
                                <div class="courses__card card card--courses">
                                    <div class="card__inner">
                                        <?php if (!empty($c['category'])): ?><p class="card__suptext card__suptext--pink"><?= htmlspecialchars((string) $c['category']) ?></p><?php endif; ?>
                                        <a class="card__title" href="<?= $url ?>"><?= htmlspecialchars((string) ($c['title'] ?? '')) ?></a>
                                        <ul class="card__list card-list">
                                            <li class="card-list__item">
                                                <p class="card-list__rait">
                                                    <span><?= htmlspecialchars((string) ($c['rating'] ?? '')) ?></span>
                                                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <g clip-path="url(#clip0_114_9488)">
                                                            <path d="M11.9687 4.60317C11.8902 4.36018 11.6746 4.1876 11.4197 4.16462L7.95614 3.85013L6.58656 0.644511C6.48558 0.40958 6.25559 0.257507 6.00006 0.257507C5.74453 0.257507 5.51454 0.40958 5.41356 0.64506L4.04399 3.85013L0.579908 4.16462C0.325385 4.18815 0.110414 4.36018 0.0314019 4.60317C-0.0476102 4.84616 0.0253592 5.11267 0.2179 5.28068L2.83592 7.5767L2.06392 10.9773C2.00744 11.2274 2.10448 11.4858 2.31195 11.6358C2.42346 11.7164 2.55393 11.7574 2.68549 11.7574C2.79893 11.7574 2.91145 11.7268 3.01244 11.6664L6.00006 9.88077L8.98659 11.6664C9.20513 11.7978 9.48062 11.7858 9.68762 11.6358C9.89518 11.4854 9.99214 11.2268 9.93565 10.9773L9.16366 7.5767L11.7817 5.28113C11.9742 5.11267 12.0477 4.84661 11.9687 4.60317Z" fill="#F9D442" />
                                                        </g>
                                                        <defs><clipPath id="clip0_114_9488"><rect width="12" height="12" fill="white" /></clipPath></defs>
                                                    </svg>
                                                </p>
                                            </li>
                                            <?php if (!empty($c['lessons_count'])): ?><li class="card-list__item"><p class="card-list__text"><?= (int)$c['lessons_count'] ?> уроков</p></li><?php endif; ?>
                                            <?php if (!empty($c['students_count'])): ?><li class="card-list__item"><p class="card-list__text"><?= (int)$c['students_count'] ?> студентов</p></li><?php endif; ?>
                                            <?php if ($teacherName): ?><li class="card-list__item"><a class="card-list__link" href="<?= $url ?>">преподаватель: <?= htmlspecialchars($teacherName) ?></a></li><?php endif; ?>
                                        </ul>
                                        <a class="card-box__poster" href="<?= $url ?>">
                                            <img class="card-box__poster-img" src="<?= htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8') ?>" alt="">
                                            <?php if ($priceLabel !== ''): ?><p class="card-box__poster-text"><?= htmlspecialchars($priceLabel) ?></p><?php endif; ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="members-section__wrapper" id="5">
                <div class="members-section__content">
                    <p>Дополнительная информация появится позже.</p>
                </div>
            </div>
        </div>
    </div>
</section>
<style>
.members-section-top-body__suptext{
    max-width: 100%;
    white-space: normal;
    line-height: 1.2;
}
.members-section-top-body__link{
    line-height: 1.22;
}
.members-section-top-body-box__buttons{
    gap: 10px;
}
.members-section-top-body-box__buttons-link{
    min-width: 150px;
    justify-content: center;
}
</style>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/include/footer.php'; ?>
