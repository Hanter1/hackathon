<?php
/**
 * Модалка «Записаться» (report-popup). Подключается в footer.php.
 */
if (!function_exists('get_home_settings')) {
    $base = dirname(__DIR__);
    if (file_exists($base . '/config/config.php')) require_once $base . '/config/config.php';
    if (defined('DOC_ROOT') && file_exists(DOC_ROOT . '/config/db.php')) require_once DOC_ROOT . '/config/db.php';
    if (file_exists($base . '/include/data.php')) require_once $base . '/include/data.php';
}
if (!function_exists('get_home_settings')) {
    return;
}
$home = get_home_settings();
$formTitle = $home['signup_form_title'] ?? 'Записаться';
$labelName = $home['signup_form_label_name'] ?? 'Ваше имя';
$labelEmail = $home['signup_form_label_email'] ?? 'Email';
$labelMessage = $home['signup_form_label_message'] ?? 'Сообщение';
$btnText = $home['signup_form_btn'] ?? 'Отправить заявку';
$successText = $home['signup_form_success'] ?? 'Спасибо! Мы свяжемся с вами.';
?>
<section class="main__popup" id="report-popup">
    <div class="popup__inner">
        <h2 class="popup__title"><?= htmlspecialchars($formTitle) ?></h2>
        <div class="popup-content">
            <div id="send-feedback">
                <form id="signup-form" method="post" action="/signup.php">
                    <input class="comment__form-input" type="text" name="name" placeholder="<?= htmlspecialchars($labelName) ?>" required>
                    <input class="comment__form-input" type="email" name="email" placeholder="<?= htmlspecialchars($labelEmail) ?>" required>
                    <textarea class="comment__form-textarea" name="message" placeholder="<?= htmlspecialchars($labelMessage) ?>" rows="3"></textarea>
                    <div id="signup-form-message" class="signup-form-message" style="display:none; margin-bottom: 0.5rem;"></div>
                    <button class="comment__form-btn" type="submit"><?= htmlspecialchars($btnText) ?></button>
                </form>
            </div>
        </div>
    </div>
</section>
<script>
(function() {
    var form = document.getElementById('signup-form');
    var msgEl = document.getElementById('signup-form-message');
    var successText = <?= json_encode($successText) ?>;
    if (!form) return;

    document.addEventListener('click', function (e) {
        var trigger = e.target && e.target.closest ? e.target.closest('[data-signup-message]') : null;
        if (!trigger) return;
        var v = (trigger.getAttribute('data-signup-message') || '').trim();
        if (!v) return;
        var ta = form.querySelector('textarea[name="message"]');
        if (!ta) return;
        if (String(ta.value || '').trim() === '') {
            ta.value = v;
        }
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        msgEl.style.display = 'none';
        msgEl.className = 'signup-form-message';
        var btn = form.querySelector('button[type="submit"]');
        var origText = btn.textContent;
        btn.disabled = true;
        btn.textContent = <?= json_encode(ep_t('common.sending')) ?>;
        var fd = new FormData(form);
        fetch('/signup.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    msgEl.textContent = successText;
                    msgEl.style.display = 'block';
                    msgEl.style.color = '#4ade80';
                    form.reset();
                    setTimeout(function() {
                        if (typeof Fancybox !== 'undefined') Fancybox.close();
                    }, 1500);
                } else {
                    msgEl.textContent = (data.errors && data.errors.length) ? data.errors.join(' ') : <?= json_encode(ep_t('common.error_send')) ?>;
                    msgEl.style.display = 'block';
                    msgEl.style.color = '#e94560';
                }
            })
            .catch(function() {
                msgEl.textContent = 'Ошибка сети';
                msgEl.style.display = 'block';
                msgEl.style.color = '#e94560';
            })
            .finally(function() {
                btn.disabled = false;
                btn.textContent = origText;
            });
    });
})();
</script>
