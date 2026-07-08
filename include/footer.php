<?php
if (file_exists(__DIR__ . '/report-popup.php')) {
    require_once __DIR__ . '/report-popup.php';
}
if (file_exists(__DIR__ . '/login-popup.php')) {
    require_once __DIR__ . '/login-popup.php';
}
$epFooter = static fn(string $k) => htmlspecialchars(ep_t($k), ENT_QUOTES, 'UTF-8');
?>
</main>
<style>
    .footer__legal { display: block; margin-top: 12px; font-size: 13px; opacity: 0.88; }
    .footer__legal a { color: inherit; text-decoration: underline; text-underline-offset: 2px; }
</style>
<footer class="footer">
    <div class="container">
        <p class="footer__copy">
            Easy People © 2025. <?= $epFooter('footer.rights') ?>
            <span class="footer__legal">
                <a href="/privacy.php"><?= $epFooter('footer.privacy') ?></a>
                ·
                <a href="/privacy.php#terms"><?= $epFooter('footer.terms') ?></a>
                ·
                <a href="/privacy.php#cookies"><?= $epFooter('footer.cookies') ?></a>
            </span>
        </p>
    </div>
</footer>
</div>

</section>

<button class="switch-button"></button>


<script src="/js/jquery.min.js"></script>
<script src="/js/swiper-bundle.min.js"></script>
<script src="/js/fancybox.umd.js"></script>
<script src="/js/TweenMax.min.js"></script>
<script src="/js/main.min.js"></script>


</body>

</html>
