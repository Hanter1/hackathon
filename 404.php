<?php

/**

 * Страница «Страница не найдена»: общий шаблон header + footer.

 */

http_response_code(404);

$cmsSeoTitle = ep_t('404.title') . ' — Easy People';

$cmsSeoNoindex = true;

require_once __DIR__ . '/include/header.php';

$ep404 = static fn(string $k) => htmlspecialchars(ep_t($k), ENT_QUOTES, 'UTF-8');

?>

        <section class="main__heading heading">

            <div class="container">

                <h1 class="heading__title"><?= $ep404('404.title') ?></h1>

                <p style="margin-top: 1rem; opacity: 0.9;"><?= $ep404('404.text') ?></p>

                <p style="margin-top: 1rem;"><a href="/" class="elevate-content__link" style="display: inline-block;"><?= $ep404('404.home') ?></a></p>

            </div>

        </section>

<?php require_once __DIR__ . '/include/footer.php'; ?>

