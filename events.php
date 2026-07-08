<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/include/data.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/include/i18n.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/include/cms-helpers.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/include/seo.php';

$home = get_home_settings();

$events = get_events();

$base = cms_public_base_url();

$cmsSeoTitle = ep_t('events.title') . ' — Easy People';

$cmsSeoDescription = sprintf(ep_t('events.seo_suffix'), count($events));

$d = trim((string) ($home['seo_description'] ?? ''));

if ($d !== '') {

    $cmsSeoDescription = $d . ' ' . ep_t('events.title') . '.';

}

$cmsSeoCanonical = $base . '/events.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/include/header.php';

$epUi = static fn(string $k) => htmlspecialchars(ep_t($k), ENT_QUOTES, 'UTF-8');

?>

<div class="wrapper">

    <section class="main__events events">

        <div class="container">

            <h1 class="events__title title"><?= $epUi('events.title') ?></h1>

            <div class="events__filters-panel filters-panel">

                <div class="filters-panel__tags tags">

                    <a class="tags__tag tags__tag--active" href="#"><?= $epUi('common.all_events') ?> <span><?= count($events) ?></span></a>

                </div>

            </div>

            <div class="events__inner">

                <?php foreach ($events as $ev):

                    $url = '/event.php?slug=' . rawurlencode($ev['slug']);

                    $img = !empty($ev['image']) ? ep_public_media_src((string) $ev['image']) : '/images/new/event1.jpg';

                    $priceLabel = ep_event_price_label((string) ($ev['price'] ?? ''));

                    $organizer = trim((string) ($ev['organizer_name'] ?? ''));

                ?>

                <div class="events__card card card--events">

                    <div class="card__inner">

                        <?php if (!empty($ev['event_date'])): ?><p class="card__text"><?= htmlspecialchars($ev['event_date']) ?></p><?php endif; ?>

                        <a class="card__title card--events-title" href="<?= $url ?>"><?= htmlspecialchars($ev['title']) ?></a>

                        <ul class="card__list card-list">

                            <?php if (!empty($ev['location'])): ?><li class="card-list__item"><p class="card-list__text"><?= htmlspecialchars($ev['location']) ?></p></li><?php endif; ?>

                            <?php if ($organizer !== ''): ?><li class="card-list__item"><a class="card-list__link" href="<?= $url ?>"><?= $epUi('event.by') ?> <?= htmlspecialchars($organizer) ?></a></li><?php endif; ?>

                        </ul>

                        <a class="card-box__poster" href="<?= $url ?>">

                            <img class="card-box__poster-img" src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="">

                            <?php if ($priceLabel !== ''): ?><p class="card-box__poster-text"><?= htmlspecialchars($priceLabel) ?></p><?php endif; ?>

                        </a>

                    </div>

                </div>

                <?php endforeach; ?>

            </div>

            <?php if (empty($events)): ?><p><?= $epUi('common.empty_events') ?> <a href="/admin/"><?= $epUi('common.empty_courses_admin') ?></a>.</p><?php endif; ?>

        </div>

    </section>

</div>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/include/footer.php'; ?>

