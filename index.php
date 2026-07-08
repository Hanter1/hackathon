<?php
/**
 * Главная страница: баннеры и блоки по вёрстке home.html, данные из БД.
 * Редактирование: Админка → Главная.
 */
require_once __DIR__ . '/include/data.php';
require_once __DIR__ . '/include/cms-helpers.php';
require_once __DIR__ . '/include/seo.php';

$home = get_home_settings();
$base = cms_public_base_url();
$cmsSeoTitle = $home['seo_title'] ?? 'Easy People';
$cmsSeoDescription = trim((string) ($home['seo_description'] ?? ''));
$cmsSeoCanonical = $base . '/';
require_once __DIR__ . '/include/header.php';
$epUi = static fn(string $k) => htmlspecialchars(ep_t($k), ENT_QUOTES, 'UTF-8');
$courses = array_slice(get_courses(), 0, 6);
$posts = array_slice(get_blog_posts(), 0, 6);
$allEvents = get_events();
$featuredEvent = null;
if (!empty($home['section_top_event_slug'])) {
    $featuredEvent = get_event_by_slug($home['section_top_event_slug']);
}
if (!$featuredEvent && !empty($allEvents)) {
    $featuredEvent = $allEvents[0];
}

$countItemSvg = '<svg width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="icons"><path id="Icon" d="M2.5 6H9M6.5 3L9.14645 5.64645C9.34171 5.84171 9.34171 6.15829 9.14645 6.35355L6.5 9" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/></g></svg>';
$starSvg = '<svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="icons" clip-path="url(#clip0_114_9488)"><path id="Vector" d="M11.9687 4.60317C11.8902 4.36018 11.6746 4.1876 11.4197 4.16462L7.95614 3.85013L6.58656 0.644511C6.48558 0.40958 6.25559 0.257507 6.00006 0.257507C5.74453 0.257507 5.51454 0.40958 5.41356 0.64506L4.04399 3.85013L0.579908 4.16462C0.325385 4.18815 0.110414 4.36018 0.0314019 4.60317C-0.0476102 4.84616 0.0253592 5.11267 0.2179 5.28068L2.83592 7.5767L2.06392 10.9773C2.00744 11.2274 2.10448 11.4858 2.31195 11.6358C2.42346 11.7164 2.55393 11.7574 2.68549 11.7574C2.79893 11.7574 2.91145 11.7268 3.01244 11.6664L6.00006 9.88077L8.98659 11.6664C9.20513 11.7978 9.48062 11.7858 9.68762 11.6358C9.89518 11.4854 9.99214 11.2268 9.93565 10.9773L9.16366 7.5767L11.7817 5.28113C11.9742 5.11267 12.0477 4.84661 11.9687 4.60317Z" fill="#F9D442"/></g><defs><clipPath id="clip0_114_9488"><rect width="12" height="12" fill="white"/></clipPath></defs></svg>';
$arrowSvg = '<svg width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="icons"><path id="Icon" d="M2.5 6H9M6.5 3L9.14645 5.64645C9.34171 5.84171 9.34171 6.15829 9.14645 6.35355L6.5 9" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/></g></svg>';
?>
        <section class="main__heading heading">
            <div class="container">
                <h1 class="heading__title">
                    <span><?= htmlspecialchars($home['hero_1']) ?></span>
                    <?= htmlspecialchars($home['hero_2']) ?>
                    <span><?= htmlspecialchars($home['hero_3']) ?></span>
                </h1>
            </div>
        </section>
        <section class="main__count count">
            <div class="container">
                <div class="count__inner">
                    <?php for ($i = 1; $i <= 4; $i++): $url = $home['count_' . $i . '_url'] ?? '#'; $tag = $url ? 'a' : 'div'; $href = $url ? ' href="' . htmlspecialchars($url) . '"' : ''; ?>
                    <<?= $tag ?> class="count__item count-item"<?= $href ?>>
                        <div class="count-item__icon"><?= $countItemSvg ?></div>
                        <p class="count-item__text">
                            <span><?= htmlspecialchars($home['count_' . $i . '_num']) ?></span>
                            <?php if ($i === 4): ?><?= $starSvg ?><?php endif; ?>
                        </p>
                        <p class="count-item__subtext"><?= htmlspecialchars($home['count_' . $i . '_text']) ?></p>
                    </<?= $tag ?>>
                    <?php endfor; ?>
                </div>
            </div>
        </section>

        <?php if (!empty($courses)): ?>
        <section class="main__groups-section groups-section">
            <div class="container">
                <div class="groups-section__top groups-section-top">
                    <h2 class="groups-section-top__title"><?= htmlspecialchars($home['groups_section_title']) ?></h2>
                    <div class="groups-section__buttons swiper-buttons">
                        <div class="groups-section__buttons-prev swiper-buttons-prev-btn"><?= $arrowSvg ?></div>
                        <div class="groups-section__buttons-next swiper-buttons-next-btn"><?= $arrowSvg ?></div>
                    </div>
                </div>
                <div class="groups-section__swiper groups-section-swiper swiper">
                    <div class="swiper-wrapper">
                        <?php foreach ($courses as $c):
                            $courseUrl = '/course.php?slug=' . rawurlencode($c['slug']);
                            $suptextClass = !empty($c['category']) && stripos($c['category'], 'online') !== false ? 'card__suptext--pink' : 'card__suptext--green';
                        ?>
                        <div class="groups-section-swiper__slide groups-section-slide swiper-slide">
                            <div class="groups-section__card card">
                                <div class="card__inner">
                                    <p class="card__suptext <?= $suptextClass ?>"><?= htmlspecialchars($c['category'] ?: ep_t('common.course')) ?></p>
                                    <div class="card__options card-options">
                                        <div class="card-options__btn"><span></span><span></span><span></span></div>
                                        <div class="card-options__inner">
                                            <a class="card-options__link" href="<?= $courseUrl ?>"><?= $epUi('common.more') ?></a>
                                        </div>
                                    </div>
                                    <a class="card__title" href="<?= $courseUrl ?>"><?= htmlspecialchars($c['title']) ?></a>
                                    <ul class="card__list card-list">
                                        <li class="card-list__item"><p class="card-list__text"><?= (int)$c['lessons_count'] ?> <?= $epUi('common.lessons') ?></p></li>
                                        <li class="card-list__item"><p class="card-list__text"><?= (int)$c['students_count'] ?> <?= $epUi('common.students') ?></p></li>
                                    </ul>
                                    <a class="card-box__poster" href="<?= $courseUrl ?>">
                                        <?php if (!empty($c['image'])): ?><img class="card-box__poster-img" src="/<?= htmlspecialchars(ltrim($c['image'], '/')) ?>" alt=""><?php else: ?><img class="card-box__poster-img" src="/images/ep/logo-img.svg" alt=""><?php endif; ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <section class="main__elevate elevate">
            <div class="container">
                <div class="elevate__inner">
                    <div class="elevate__content elevate-content">
                        <h2 class="elevate-content__title">
                            <?= nl2br(htmlspecialchars($home['elevate_title_1'])) ?> <br>
                            <span><?= nl2br(htmlspecialchars($home['elevate_title_2'])) ?></span>
                        </h2>
                        <?php $elevateUrl = $home['elevate_btn_url'] ?: '/courses.php'; ?>
                        <a class="elevate-content__link" href="<?= htmlspecialchars($elevateUrl) ?>"><?= htmlspecialchars($home['elevate_btn_text']) ?></a>
                    </div>
                    <div class="elevate__view elevate-view">
                        <div class="elevate-view__box">
                            <p class="elevate-view__box-text"><?= htmlspecialchars($home['elevate_stat_num']) ?></p>
                            <p class="elevate-view__box-subtext"><?= htmlspecialchars($home['elevate_stat_text']) ?></p>
                        </div>
                        <img class="elevate-view__img" src="/images/member-icon-2.png" alt="">
                        <img class="elevate-view__img" src="/images/member-icon-16.png" alt="">
                        <img class="elevate-view__img" src="/images/member-icon-8.png" alt="">
                        <img class="elevate-view__img" src="/images/member-icon-3.png" alt="">
                        <img class="elevate-view__img" src="/images/member-icon-25.png" alt="">
                    </div>
                </div>
            </div>
        </section>

        <?php if (!empty($courses)): ?>
        <section class="main__new-courses new-courses">
            <div class="container">
                <div class="new-courses__top new-courses-top">
                    <h2 class="new-courses-top__title"><?= htmlspecialchars($home['new_courses_title']) ?></h2>
                    <div class="new-courses__buttons swiper-buttons">
                        <div class="new-courses__buttons-prev swiper-buttons-prev-btn"><?= $arrowSvg ?></div>
                        <div class="new-courses__buttons-next swiper-buttons-next-btn"><?= $arrowSvg ?></div>
                    </div>
                </div>
                <div class="new-courses__swiper new-courses-swiper swiper">
                    <div class="swiper-wrapper">
                        <?php foreach ($courses as $c):
                            $courseUrl = '/course.php?slug=' . rawurlencode($c['slug']);
                            $teacherName = trim(($c['teacher_name'] ?? '') . ' ' . ($c['teacher_surname'] ?? ''));
                        ?>
                        <div class="new-courses-swiper__slide new-courses-slide swiper-slide">
                            <div class="courses__card card">
                                <div class="card__inner">
                                    <p class="card__suptext card__suptext--pink"><?= htmlspecialchars($c['category'] ?: ep_t('common.course')) ?></p>
                                    <a class="card__title" href="<?= $courseUrl ?>"><?= htmlspecialchars($c['title']) ?></a>
                                    <ul class="card__list card-list">
                                        <li class="card-list__item">
                                            <p class="card-list__rait"><span><?= htmlspecialchars($c['rating'] ?? '4.5') ?></span><?= $starSvg ?></p>
                                        </li>
                                        <li class="card-list__item"><p class="card-list__text"><?= (int)$c['lessons_count'] ?> <?= $epUi('common.lessons') ?></p></li>
                                        <li class="card-list__item"><p class="card-list__text"><?= (int)$c['students_count'] ?> <?= $epUi('common.students') ?></p></li>
                                        <?php if ($teacherName): ?><li class="card-list__item"><a class="card-list__link" href="/members/"><?= htmlspecialchars($teacherName) ?></a></li><?php endif; ?>
                                    </ul>
                                    <a class="card-box__poster" href="<?= $courseUrl ?>">
                                        <?php if (!empty($c['image'])): ?><img class="card-box__poster-img" src="/<?= htmlspecialchars(ltrim($c['image'], '/')) ?>" alt=""><?php else: ?><img class="card-box__poster-img" src="/images/ep/logo-img.svg" alt=""><?php endif; ?>
                                        <?php if (isset($c['price']) && trim((string)$c['price']) !== ''): ?>
                                            <p class="card-box__poster-text"><?= htmlspecialchars(ep_format_price_byn((string)$c['price'])) ?></p>
                                        <?php endif; ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($featuredEvent):
            $featTicket = trim((string) ($featuredEvent['ticket_url'] ?? ''));
            $featBtnUrl = $featTicket !== '' ? $featTicket : ('/event.php?slug=' . rawurlencode((string) $featuredEvent['slug']));
            $featBtnText = $featTicket !== '' ? ep_t('event.buy_ticket') : ep_t('common.more');
            $featBtnExternal = $featTicket !== '';
        ?>
        <div class="main__section-top section-top section-top--main">
            <div class="container">
                <div class="section-top__inner">
                    <div class="section-top__content section-top-content">
                        <?php if (!empty($featuredEvent['event_date'])): ?><p class="section-top-content__date"><?= htmlspecialchars($featuredEvent['event_date']) ?></p><?php endif; ?>
                        <h1 class="section-top-content__title title"><?= ep_event_title_html((string) $featuredEvent['title']) ?></h1>
                        <?php if (!empty($featuredEvent['location']) || trim((string) ($featuredEvent['organizer_name'] ?? '')) !== ''): ?>
                        <ul class="section-top-content__list card-list">
                            <?php if (!empty($featuredEvent['location'])): ?><li class="card-list__item"><p class="card-list__text"><?= htmlspecialchars($featuredEvent['location']) ?></p></li><?php endif; ?>
                            <?php if (trim((string) ($featuredEvent['organizer_name'] ?? '')) !== ''): ?><li class="card-list__item"><p class="card-list__text"><?= htmlspecialchars((string) $featuredEvent['organizer_name']) ?></p></li><?php endif; ?>
                        </ul>
                        <?php endif; ?>
                        <div class="section-top-content__buttons">
                            <a class="section-top-content__buttons-link section-top-content__buttons-link--green" href="<?= htmlspecialchars($featBtnUrl, ENT_QUOTES, 'UTF-8') ?>"<?= $featBtnExternal ? ' target="_blank" rel="noopener"' : '' ?>><?= htmlspecialchars($featBtnText) ?></a>
                        </div>
                    </div>
                    <?php if (!empty($featuredEvent['image'])): ?>
                    <div class="section-top__img">
                        <img class="section-top__img-image" src="<?= htmlspecialchars(ep_public_media_src((string) $featuredEvent['image']), ENT_QUOTES, 'UTF-8') ?>" alt="">
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($posts)): ?>
        <section class="main__news-section news-section">
            <div class="container">
                <div class="news-section__top news-section-top">
                    <h2 class="news-section-top__title"><?= htmlspecialchars(trim((string)($home['news_section_title'] ?? '')) ?: 'Latest News') ?></h2>
                    <div class="news-section__buttons swiper-buttons">
                        <div class="news-section__buttons-prev swiper-buttons-prev-btn"><?= $arrowSvg ?></div>
                        <div class="news-section__buttons-next swiper-buttons-next-btn"><?= $arrowSvg ?></div>
                    </div>
                </div>
                <div class="news-section__swiper news-section-swiper swiper">
                    <div class="swiper-wrapper">
                        <?php foreach ($posts as $p):
                            $postUrl = '/post.php?slug=' . rawurlencode($p['slug']);
                            $pubDate = !empty($p['published_at']) ? date('d.m.Y', strtotime($p['published_at'])) : '';
                            $postImgRaw = trim((string) ($p['image'] ?? ''));
                            $postImgSrc = '';
                            if ($postImgRaw !== '') {
                                $postImgSrc = preg_match('#^https?://#i', $postImgRaw) ? $postImgRaw : ('/' . ltrim($postImgRaw, '/'));
                            }
                        ?>
                        <div class="news-section-swiper__slide news-section-slide swiper-slide">
                            <div class="news-section-slide__card card">
                                <div class="card__inner">
                                    <a class="card-box__poster" href="<?= $postUrl ?>">
                                        <?php if ($postImgSrc !== ''): ?><img class="card-box__poster-img" src="<?= htmlspecialchars($postImgSrc) ?>" alt=""><?php else: ?><img class="card-box__poster-img" src="/images/ep/logo-img.svg" alt=""><?php endif; ?>
                                    </a>
                                    <p class="card__suptext card__suptext--purple"><?= htmlspecialchars(ep_post_tag_label($p)) ?></p>
                                    <a class="card__title" href="<?= $postUrl ?>"><?= htmlspecialchars($p['title']) ?></a>
                                    <?php if (!empty($p['excerpt'])): ?><p class="card__description"><?= htmlspecialchars($p['excerpt']) ?></p><?php endif; ?>
                                    <ul class="card__list card-list">
                                        <?php if ($pubDate): ?><li class="card-list__item"><p class="card-list__text"><?= $pubDate ?></p></li><?php endif; ?>
                                        <li class="card-list__item"><a class="card-list__link" href="<?= $postUrl ?>"><?= $epUi('common.read') ?></a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>
<?php
$homeJsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => 'Easy People',
    'url' => $base,
];
if ($cmsSeoDescription !== '') {
    $homeJsonLd['description'] = $cmsSeoDescription;
}
echo cms_seo_json_ld_tag($homeJsonLd);
require_once __DIR__ . '/include/footer.php';
