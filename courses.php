<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/data.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/seo.php';
$home = get_home_settings();
$courses = get_courses();
$base = cms_public_base_url();
$cmsSeoTitle = ep_t('courses.title') . ' — Easy People';
$cmsSeoDescription = 'Каталог курсов Easy People. Направления, форматы и преподаватели. В каталоге: ' . count($courses) . ' курсов.';
$d = trim((string) ($home['seo_description'] ?? ''));
if ($d !== '') {
    $cmsSeoDescription = $d . ' Смотрите каталог курсов.';
}
$cmsSeoCanonical = $base . '/courses.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/header.php';

function ep_format_price_byn(string $raw): string
{
    $s = trim($raw);
    if ($s === '') return '';
    if (preg_match('/(byn|br|руб|р\\.|₽|\\$|€)/iu', $s)) {
        return $s;
    }
    if (preg_match('/^\\d+(?:[\\s.,]\\d+)?$/u', $s)) {
        $s = str_replace(',', '.', $s);
        return $s . ' Br';
    }
    return $s;
}
$epUi = static fn(string $k) => htmlspecialchars(ep_t($k), ENT_QUOTES, 'UTF-8');
?>
<div class="wrapper">
    <section class="main__courses courses">
        <div class="container">
            <h1 class="courses__title title"><?= $epUi('courses.title') ?></h1>
            <div class="courses__filters-panel filters-panel">
                <div class="filters-panel__tags tags">
                    <a class="tags__tag tags__tag--active" href="#"><?= $epUi('common.all_courses') ?> <span><?= count($courses) ?></span></a>
                </div>
            </div>
            <div class="courses__inner">
                <?php foreach ($courses as $c):
                    $url = '/course.php?slug=' . rawurlencode($c['slug']);
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
                        <?php if (!empty($c['category'])): ?><p class="card__suptext card__suptext--pink"><?= htmlspecialchars($c['category']) ?></p><?php endif; ?>
                        <a class="card__title" href="<?= $url ?>"><?= htmlspecialchars($c['title']) ?></a>
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
                            <?php if (!empty($c['lessons_count'])): ?><li class="card-list__item"><p class="card-list__text"><?= (int)$c['lessons_count'] ?> <?= $epUi('common.lessons') ?></p></li><?php endif; ?>
                            <?php if (!empty($c['students_count'])): ?><li class="card-list__item"><p class="card-list__text"><?= (int)$c['students_count'] ?> <?= $epUi('common.students') ?></p></li><?php endif; ?>
                            <?php if ($teacherName): ?><li class="card-list__item"><a class="card-list__link" href="<?= $url ?>"><?= $epUi('common.teacher') ?> <?= htmlspecialchars($teacherName) ?></a></li><?php endif; ?>
                        </ul>
                        <a class="card-box__poster" href="<?= $url ?>">
                            <img class="card-box__poster-img" src="<?= htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8') ?>" alt="">
                            <?php if ($priceLabel !== ''): ?><p class="card-box__poster-text"><?= htmlspecialchars($priceLabel) ?></p><?php endif; ?>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (empty($courses)): ?><p><?= $epUi('common.empty_courses') ?> <a href="/admin/"><?= $epUi('common.empty_courses_admin') ?></a>.</p><?php endif; ?>
        </div>
    </section>
</div>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/include/footer.php'; ?>
