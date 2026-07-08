<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/data.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/i18n.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/cms-helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/seo.php';

$epUi = static fn(string $k) => htmlspecialchars(ep_t($k), ENT_QUOTES, 'UTF-8');
$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
if ($slug === '') {
    header('Location: /events.php');
    exit;
}

$e = get_event_by_slug($slug);
if (!$e) {
    header('HTTP/1.0 404 Not Found');
    $cmsSeoTitle = ep_t('event.not_found') . ' — Easy People';
    $cmsSeoNoindex = true;
    require_once $_SERVER['DOCUMENT_ROOT'] . '/include/header.php';
    echo '<div class="wrapper"><div class="container"><h1>' . $epUi('event.not_found') . '</h1><p><a href="/events.php">' . $epUi('event.back_to_list') . '</a></p></div></div>';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/include/footer.php';
    exit;
}

$eventId = (int) ($e['id'] ?? 0);
$contentBlocks = get_event_content_blocks($eventId);
if ($contentBlocks === [] && trim((string) ($e['description'] ?? '')) !== '') {
    $contentBlocks = [[
        'block_type' => 'about',
        'title' => ep_t('event.about_title'),
        'body' => (string) $e['description'],
        'images' => '',
        'map_embed_url' => '',
    ]];
}

$base = cms_public_base_url();
$plainDesc = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($e['description'] ?? ''))));
$loc = trim((string) ($e['location'] ?? ''));
$dt = trim((string) ($e['event_date'] ?? ''));
$fallbackDesc = trim(($dt ? $dt . '. ' : '') . ($loc ? $loc . '. ' : '') . $plainDesc);
$cmsSeoTitle = cms_seo_entity_title($e, (string) $e['title']);
$cmsSeoDescription = cms_seo_entity_description($e, $fallbackDesc !== '' ? $fallbackDesc : ($cmsSeoTitle . ' — Easy People.'));
$cmsSeoCanonical = $base . '/event.php?slug=' . rawurlencode((string) $e['slug']);
$cmsSeoOgImage = cms_seo_abs_media_url($e['image'] ?? '');
$cmsSeoType = 'article';

$coverSrc = ep_public_media_src((string) ($e['image'] ?? ''));
$ticketUrl = trim((string) ($e['ticket_url'] ?? ''));
$calendarUrl = trim((string) ($e['calendar_url'] ?? ''));
$websiteUrl = trim((string) ($e['website_url'] ?? ''));
$eventUrl = '/event.php?slug=' . rawurlencode((string) $e['slug']);

require_once $_SERVER['DOCUMENT_ROOT'] . '/include/header.php';
?>
<div class="wrapper">
    <div class="main__breadcrumbs breadcrumbs">
        <div class="container">
            <ul class="breadcrumbs__list">
                <li class="breadcrumbs__list-item"><a class="breadcrumbs__list-link" href="/"><?= $epUi('nav.home') ?></a></li>
                <li class="breadcrumbs__list-item"><a class="breadcrumbs__list-link" href="/events.php"><?= $epUi('events.title') ?></a></li>
                <li class="breadcrumbs__list-item"><p class="breadcrumbs__list-text"><?= htmlspecialchars((string) $e['title']) ?></p></li>
            </ul>
        </div>
    </div>
    <div class="main__section-top section-top">
        <div class="container">
            <div class="section-top__inner">
                <div class="section-top__content section-top-content">
                    <?php if ($dt !== ''): ?><p class="section-top-content__date"><?= htmlspecialchars($dt) ?></p><?php endif; ?>
                    <h1 class="section-top-content__title title"><?= ep_event_title_html((string) $e['title']) ?></h1>
                    <?php if ($loc !== '' || trim((string) ($e['organizer_name'] ?? '')) !== ''): ?>
                    <ul class="section-top-content__list card-list">
                        <?php if ($loc !== ''): ?><li class="card-list__item"><p class="card-list__text"><?= htmlspecialchars($loc) ?></p></li><?php endif; ?>
                        <?php if (trim((string) ($e['organizer_name'] ?? '')) !== ''): ?><li class="card-list__item"><p class="card-list__text"><?= htmlspecialchars((string) $e['organizer_name']) ?></p></li><?php endif; ?>
                    </ul>
                    <?php endif; ?>
                    <?php if ($calendarUrl !== '' || $websiteUrl !== ''): ?>
                    <div class="section-top-content__buttons">
                        <?php if ($calendarUrl !== ''): ?><a class="section-top-content__buttons-link section-top-content__buttons-link--purple" href="<?= htmlspecialchars($calendarUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= $epUi('event.add_calendar') ?></a><?php endif; ?>
                        <?php if ($websiteUrl !== ''): ?><a class="section-top-content__buttons-link" href="<?= htmlspecialchars($websiteUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= $epUi('event.visit_website') ?></a><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($coverSrc !== ''): ?>
                <div class="section-top__img">
                    <img class="section-top__img-image" src="<?= htmlspecialchars($coverSrc, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) $e['title'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <section class="main__event event">
        <div class="container">
            <div class="event__inner">
                <section class="event__about about-event">
                    <?php foreach ($contentBlocks as $block):
                        $type = (string) ($block['block_type'] ?? 'about');
                        $blockTitle = trim((string) ($block['title'] ?? ''));
                        if ($blockTitle === '') {
                            $blockTitle = $type === 'map' ? ep_t('event.map_title') : ep_t('event.about_title');
                        }
                    ?>
                    <div class="about-event__box about-event-box">
                        <?php if ($blockTitle !== ''): ?><h2 class="about-event-box__title"><?= htmlspecialchars($blockTitle) ?></h2><?php endif; ?>
                        <?php if ($type === 'about'):
                            $parts = preg_split('/\n{2,}/', trim((string) ($block['body'] ?? ''))) ?: [];
                            foreach ($parts as $chunk):
                                $chunk = trim((string) $chunk);
                                if ($chunk === '') continue;
                        ?>
                            <p class="about-event-box__text"><?= nl2br(htmlspecialchars($chunk)) ?></p>
                        <?php endforeach; endif; ?>
                        <?php if ($type === 'gallery'):
                            $imgs = preg_split('/\R/u', trim((string) ($block['images'] ?? ''))) ?: [];
                            $imgs = array_values(array_filter(array_map('trim', $imgs)));
                            if ($imgs !== []):
                        ?>
                            <div class="about-event-box__images">
                                <?php foreach ($imgs as $img): $src = ep_public_media_src($img); if ($src === '') continue; ?>
                                <img class="about-event-box__images-img" src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" alt="">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; endif; ?>
                        <?php if ($type === 'map'):
                            $mapSrc = trim((string) ($block['map_embed_url'] ?? ''));
                            if ($mapSrc === '') {
                                $mapSrc = trim((string) ($e['map_embed_url'] ?? ''));
                            }
                            if ($mapSrc !== ''):
                        ?>
                            <div class="about-event-box__map">
                                <iframe src="<?= htmlspecialchars($mapSrc, ENT_QUOTES, 'UTF-8') ?>" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                            </div>
                        <?php endif; endif; ?>
                    </div>
                    <?php endforeach; ?>
                </section>
                <aside class="event__aside event-aside">
                    <?php $priceLabel = ep_event_price_label((string) ($e['price'] ?? '')); ?>
                    <?php if ($priceLabel !== ''): ?><p class="event-aside__price"><?= htmlspecialchars($priceLabel) ?></p><?php endif; ?>
                    <?php if ($dt !== '' || trim((string) ($e['event_time'] ?? '')) !== ''): ?>
                    <div class="event-aside__box event-aside-box">
                        <h4 class="event-aside-box__title"><?= $epUi('event.details') ?></h4>
                        <ul class="event-aside-box__list">
                            <?php if ($dt !== ''): ?>
                            <li class="event-aside-box__list-item">
                                <p class="event-aside-box__list-text"><?= $epUi('event.date') ?><span><?= htmlspecialchars($dt) ?></span></p>
                            </li>
                            <?php endif; ?>
                            <?php if (trim((string) ($e['event_time'] ?? '')) !== ''): ?>
                            <li class="event-aside-box__list-item">
                                <p class="event-aside-box__list-text"><?= $epUi('event.time') ?><span><?= htmlspecialchars((string) $e['event_time']) ?></span></p>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <?php
                    $orgName = trim((string) ($e['organizer_name'] ?? ''));
                    $orgEmail = trim((string) ($e['organizer_email'] ?? ''));
                    $orgPhone = trim((string) ($e['organizer_phone'] ?? ''));
                    $orgWeb = trim((string) ($e['organizer_website'] ?? ''));
                    if ($orgName !== '' || $orgEmail !== '' || $orgPhone !== '' || $orgWeb !== ''):
                    ?>
                    <div class="event-aside__box event-aside-box">
                        <h4 class="event-aside-box__title"><?= $epUi('event.organizer') ?></h4>
                        <ul class="event-aside-box__list">
                            <?php if ($orgName !== ''): ?><li class="event-aside-box__list-item"><p class="event-aside-box__list-text"><?= $epUi('event.name') ?><span><?= htmlspecialchars($orgName) ?></span></p></li><?php endif; ?>
                            <?php if ($orgEmail !== ''): ?><li class="event-aside-box__list-item"><p class="event-aside-box__list-text"><?= $epUi('event.email') ?><a href="mailto:<?= htmlspecialchars($orgEmail, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($orgEmail) ?></a></p></li><?php endif; ?>
                            <?php if ($orgPhone !== ''): ?><li class="event-aside-box__list-item"><p class="event-aside-box__list-text"><?= $epUi('event.phone') ?><a href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', $orgPhone), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($orgPhone) ?></a></p></li><?php endif; ?>
                            <?php if ($orgWeb !== ''): ?><li class="event-aside-box__list-item"><p class="event-aside-box__list-text"><?= $epUi('event.website') ?><a href="<?= htmlspecialchars($orgWeb, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($orgWeb) ?></a></p></li><?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <?php
                    $venueName = trim((string) ($e['venue_name'] ?? ''));
                    $venueAddress = trim((string) ($e['venue_address'] ?? ''));
                    $venuePhone = trim((string) ($e['venue_phone'] ?? ''));
                    if ($venueName !== '' || $venueAddress !== '' || $venuePhone !== '' || $loc !== ''):
                    ?>
                    <div class="event-aside__box event-aside-box">
                        <h4 class="event-aside-box__title"><?= $epUi('event.venue') ?></h4>
                        <ul class="event-aside-box__list">
                            <?php if ($venueName !== ''): ?><li class="event-aside-box__list-item"><p class="event-aside-box__list-text"><?= $epUi('event.place') ?><span><?= htmlspecialchars($venueName) ?></span></p></li><?php endif; ?>
                            <?php if ($venueAddress !== ''): ?><li class="event-aside-box__list-item"><p class="event-aside-box__list-text"><?= $epUi('event.location') ?><span><?= htmlspecialchars($venueAddress) ?></span></p></li><?php elseif ($loc !== ''): ?><li class="event-aside-box__list-item"><p class="event-aside-box__list-text"><?= $epUi('event.location') ?><span><?= htmlspecialchars($loc) ?></span></p></li><?php endif; ?>
                            <?php if ($venuePhone !== ''): ?><li class="event-aside-box__list-item"><p class="event-aside-box__list-text"><?= $epUi('event.phone') ?><a href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', $venuePhone), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($venuePhone) ?></a></p></li><?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <?php if ($ticketUrl !== ''): ?>
                    <a class="event-aside__link" href="<?= htmlspecialchars($ticketUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= $epUi('event.buy_ticket') ?></a>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </section>
</div>
<?php
$eventJsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Event',
    'name' => $e['title'],
    'description' => $cmsSeoDescription,
    'url' => $cmsSeoCanonical,
    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
    'eventStatus' => 'https://schema.org/EventScheduled',
];
if ($cmsSeoOgImage !== '') {
    $eventJsonLd['image'] = $cmsSeoOgImage;
}
if ($loc !== '') {
    $eventJsonLd['location'] = ['@type' => 'Place', 'name' => $loc];
}
if ($dt !== '') {
    $eventJsonLd['startDate'] = $dt;
}
echo cms_seo_json_ld_tag($eventJsonLd);
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/footer.php';
