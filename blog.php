<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/include/data.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/include/i18n.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/include/seo.php';

$home = get_home_settings();

$posts = get_blog_posts();

$base = cms_public_base_url();

$cmsSeoTitle = ep_t('blog.title') . ' — Easy People';

$cmsSeoDescription = trim((string) ($home['seo_description'] ?? ''));

if ($cmsSeoDescription === '') {

    $cmsSeoDescription = sprintf(ep_t('blog.seo_suffix'), count($posts));

}

$cmsSeoCanonical = $base . '/blog.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/include/header.php';

$epUi = static fn(string $k) => htmlspecialchars(ep_t($k), ENT_QUOTES, 'UTF-8');

?>

<div class="wrapper">

    <section class="main__blog blog">

        <div class="container">

            <h1 class="blog__title title"><?= $epUi('blog.title') ?></h1>

            <div class="blog__filters-panel filters-panel">

                <div class="filters-panel__tags tags">

                    <a class="tags__tag tags__tag--active" href="#"><?= $epUi('common.all_posts') ?> <span><?= count($posts) ?></span></a>

                </div>

            </div>

            <div class="blog__inner">

                <?php foreach ($posts as $p):

                    $url = '/post.php?slug=' . rawurlencode($p['slug']);

                    $img = !empty($p['image']) ? $p['image'] : '/images/group-img-5.jpg';

                    $date = !empty($p['published_at']) ? date('d.m.Y', strtotime($p['published_at'])) : '';

                ?>

                <div class="blog__card card card--blog">

                    <div class="card__inner">

                        <a class="card-box__poster" href="<?= $url ?>">

                            <img class="card-box__poster-img" src="<?= htmlspecialchars($img) ?>" alt="">

                        </a>

                        <p class="card__suptext card__suptext--purple"><?= htmlspecialchars(ep_post_tag_label($p)) ?></p>

                        <a class="card__title" href="<?= $url ?>"><?= htmlspecialchars($p['title']) ?></a>

                        <?php if (!empty($p['excerpt'])): ?><p class="card__description"><?= htmlspecialchars($p['excerpt']) ?></p><?php endif; ?>

                        <ul class="card__list card-list">

                            <?php if ($date): ?><li class="card-list__item"><p class="card-list__text"><?= $date ?></p></li><?php endif; ?>

                            <li class="card-list__item"><a class="card-list__link" href="<?= $url ?>"><?= $epUi('common.read') ?></a></li>

                        </ul>

                    </div>

                </div>

                <?php endforeach; ?>

            </div>

            <?php if (empty($posts)): ?><p><?= $epUi('common.empty_posts') ?> <a href="/admin/"><?= $epUi('common.empty_courses_admin') ?></a>.</p><?php endif; ?>

        </div>

    </section>

</div>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/include/footer.php'; ?>

