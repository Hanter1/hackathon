<?php
$epLogin = static fn(string $k) => htmlspecialchars(ep_t($k), ENT_QUOTES, 'UTF-8');
?>
<section class="main__login login" id="login-popup">
    <div class="login__inner">
        <h2 class="login__title"><?= $epLogin('login.title') ?></h2>
        <div class="login-tabs" style="display:flex; gap:0.75rem; margin-bottom:1rem;">
            <button type="button" class="login-tabs__btn login-tabs__btn--active" data-auth-tab="login"><?= $epLogin('login.tab_login') ?></button>
            <button type="button" class="login-tabs__btn" data-auth-tab="register"><?= $epLogin('login.tab_register') ?></button>
        </div>

        <form class="login__form login-form auth-pane auth-pane--active" id="client-login-form" method="post" action="/user-login.php">
            <input type="hidden" name="register" value="0">
            <div class="login-form__box login-form-box">
                <label class="login-form-box__label" for="client-login-identifier-input"><?= $epLogin('login.identifier') ?></label>
                <div class="login-form-box__row">
                    <input class="login-form-box__row-input" id="client-login-identifier-input" type="text" name="identifier" autocomplete="username" required>
                </div>
            </div>
            <div class="login-form__box login-form-box">
                <label class="login-form-box__label" for="client-login-password-input"><?= $epLogin('login.password') ?></label>
                <div class="login-form-box__row">
                    <input class="login-form-box__row-input" id="client-login-password-input" type="password" name="password" autocomplete="current-password" required>
                </div>
            </div>
            <div id="client-login-message" class="signup-form-message" style="display:none; margin-bottom: 0.5rem;"></div>
            <button class="login-form__button" type="submit"><?= $epLogin('login.submit') ?></button>
        </form>

        <form class="login__form login-form auth-pane" id="client-register-form" method="post" action="/user-login.php" style="display:none;">
            <input type="hidden" name="register" value="1">
            <div class="login-form__box login-form-box">
                <label class="login-form-box__label" for="client-register-name-input"><?= $epLogin('login.name') ?></label>
                <div class="login-form-box__row">
                    <input class="login-form-box__row-input" id="client-register-name-input" type="text" name="name" autocomplete="name" required>
                </div>
            </div>
            <div class="login-form__box login-form-box">
                <label class="login-form-box__label" for="client-register-identifier-input"><?= $epLogin('login.email') ?></label>
                <div class="login-form-box__row">
                    <input class="login-form-box__row-input" id="client-register-identifier-input" type="email" name="identifier" autocomplete="email" required>
                </div>
            </div>
            <div class="login-form__box login-form-box">
                <label class="login-form-box__label" for="client-register-password-input"><?= $epLogin('login.password') ?></label>
                <div class="login-form-box__row">
                    <input class="login-form-box__row-input" id="client-register-password-input" type="password" name="password" autocomplete="new-password" required>
                </div>
            </div>
            <div id="client-register-message" class="signup-form-message" style="display:none; margin-bottom: 0.5rem;"></div>
            <button class="login-form__button" type="submit"><?= $epLogin('login.register_submit') ?></button>
        </form>
    </div>
</section>

<script>
(function () {
    const tabButtons = Array.from(document.querySelectorAll('[data-auth-tab]'));
    const loginPane = document.getElementById('client-login-form');
    const registerPane = document.getElementById('client-register-form');

    function switchTab(tabName) {
        const isLogin = tabName === 'login';
        tabButtons.forEach(function (btn) {
            btn.classList.toggle('login-tabs__btn--active', btn.getAttribute('data-auth-tab') === tabName);
        });
        if (loginPane) {
            loginPane.style.display = isLogin ? '' : 'none';
            loginPane.classList.toggle('auth-pane--active', isLogin);
        }
        if (registerPane) {
            registerPane.style.display = isLogin ? 'none' : '';
            registerPane.classList.toggle('auth-pane--active', !isLogin);
        }
    }

    tabButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            switchTab(btn.getAttribute('data-auth-tab'));
        });
    });

    async function bindAuthForm(formId, messageId, successText) {
        const form = document.getElementById(formId);
        const msg = document.getElementById(messageId);
        if (!form || !msg) return;

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            msg.style.display = 'none';
            msg.textContent = '';
            msg.style.color = '#ff99c0';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form)
                });
                const data = await response.json();
                if (data && data.success) {
                    msg.style.display = 'block';
                    msg.style.color = '#6d9985';
                    msg.textContent = successText;
                    window.location.href = data.redirect || '/lk.php';
                    return;
                }
                msg.style.display = 'block';
                msg.textContent = (data && data.message) ? data.message : 'Ошибка авторизации.';
            } catch (err) {
                msg.style.display = 'block';
                msg.textContent = 'Сервер недоступен. Попробуйте позже.';
            }
        });
    }

    bindAuthForm('client-login-form', 'client-login-message', 'Успешный вход. Перенаправляем...');
    bindAuthForm('client-register-form', 'client-register-message', 'Аккаунт создан. Перенаправляем...');
    switchTab('login');
})();
</script>

<style>
#login-popup .login-tabs__btn{
    border:1px solid rgba(255,255,255,.2);
    background:transparent;
    color:#fff;
    border-radius:10px;
    padding:8px 14px;
    cursor:pointer;
    transition:all .2s ease;
}
#login-popup .login-tabs__btn--active{
    background:#6d9985;
    border-color:#6d9985;
}
#login-popup .auth-pane{display:none;}
#login-popup .auth-pane.auth-pane--active{display:block;}
</style>
