<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
define('DOC_ROOT', __DIR__);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/include/data.php';
require_once __DIR__ . '/include/user-auth.php';
ua_ensure_users_schema($pdo);

$sessionUser = $_SESSION['user'] ?? null;
$isClientLogged = is_array($sessionUser) && !empty($sessionUser['id']);
if ($isClientLogged && !array_key_exists('notify_courses', $sessionUser)) {
    $stSync = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stSync->execute([(int) $sessionUser['id']]);
    $rowSync = $stSync->fetch();
    if ($rowSync) {
        ua_store_session_user($rowSync);
        $sessionUser = $_SESSION['user'];
    }
}
$isAdminUser = $isClientLogged && ua_is_admin_user($sessionUser);
$avatar = $isClientLogged && !empty($sessionUser['avatar']) ? (string)$sessionUser['avatar'] : '/images/member-icon-3.png';
$profileMessage = '';
$profileError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isClientLogged && (string)($_POST['action'] ?? '') === 'update_avatar') {
    if (!empty($_FILES['avatar_file']['name'] ?? '')) {
        $uploadDir = __DIR__ . '/uploads/users';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            $profileError = 'Не удалось создать папку для фото.';
        } else {
            $tmpPath = (string)($_FILES['avatar_file']['tmp_name'] ?? '');
            $origName = (string)($_FILES['avatar_file']['name'] ?? '');
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (!in_array($ext, $allowed, true)) {
                $profileError = 'Допустимые форматы: jpg, jpeg, png, webp, gif.';
            } elseif ((int)($_FILES['avatar_file']['size'] ?? 0) > 5 * 1024 * 1024) {
                $profileError = 'Файл слишком большой (до 5MB).';
            } else {
                $safeBase = slugify((string)($sessionUser['login'] ?? 'user'));
                $fileName = $safeBase . '-avatar-' . date('YmdHis') . '.' . $ext;
                $target = $uploadDir . '/' . $fileName;
                if (!@move_uploaded_file($tmpPath, $target)) {
                    $profileError = 'Не удалось загрузить файл.';
                } else {
                    $avatarPath = '/uploads/users/' . $fileName;
                    $st = $pdo->prepare('UPDATE users SET avatar = ? WHERE id = ?');
                    $st->execute([$avatarPath, (int)$sessionUser['id']]);

                    $fresh = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
                    $fresh->execute([(int)$sessionUser['id']]);
                    $freshUser = $fresh->fetch();
                    if ($freshUser) {
                        ua_store_session_user($freshUser);
                        $sessionUser = $_SESSION['user'];
                        $avatar = (string)($sessionUser['avatar'] ?? $avatarPath);
                    }
                    $profileMessage = 'Фото профиля обновлено.';
                }
            }
        }
    } else {
        $profileError = 'Выберите файл для загрузки.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isClientLogged) {
    $postAction = (string) ($_POST['action'] ?? '');
    if ($postAction === 'update_profile') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        if ($name === '') {
            $profileError = 'Укажите имя.';
        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profileError = 'Укажите корректный email.';
        } else {
            $chk = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND id != ? LIMIT 1');
            $chk->execute([$email, (int) $sessionUser['id']]);
            if ($chk->fetch()) {
                $profileError = 'Этот email уже используется другим аккаунтом.';
            } else {
                $up = $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
                $up->execute([$name, $email, (int) $sessionUser['id']]);
                $fresh = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
                $fresh->execute([(int) $sessionUser['id']]);
                $freshUser = $fresh->fetch();
                if ($freshUser) {
                    ua_store_session_user($freshUser);
                    $sessionUser = $_SESSION['user'];
                }
                $profileMessage = 'Данные профиля сохранены.';
            }
        }
    } elseif ($postAction === 'change_password') {
        $cur = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $new2 = (string) ($_POST['new_password_confirm'] ?? '');
        $rowSt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $rowSt->execute([(int) $sessionUser['id']]);
        $rowPw = $rowSt->fetch();
        $hash = (string) ($rowPw['password_hash'] ?? '');
        if ($hash === '' || !password_verify($cur, $hash)) {
            $profileError = 'Неверный текущий пароль.';
        } elseif (strlen($new) < 8) {
            $profileError = 'Новый пароль: не меньше 8 символов.';
        } elseif ($new !== $new2) {
            $profileError = 'Новый пароль и подтверждение не совпадают.';
        } elseif (password_verify($new, $hash)) {
            $profileError = 'Новый пароль должен отличаться от текущего.';
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$newHash, (int) $sessionUser['id']]);
            $profileMessage = 'Пароль успешно изменён.';
        }
    } elseif ($postAction === 'update_notifications') {
        $nc = !empty($_POST['notify_courses']) ? 1 : 0;
        $nn = !empty($_POST['notify_news']) ? 1 : 0;
        $nm = !empty($_POST['notify_marketing']) ? 1 : 0;
        $pdo->prepare('UPDATE users SET notify_courses = ?, notify_news = ?, notify_marketing = ? WHERE id = ?')->execute([$nc, $nn, $nm, (int) $sessionUser['id']]);
        $fresh = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $fresh->execute([(int) $sessionUser['id']]);
        $freshUser = $fresh->fetch();
        if ($freshUser) {
            ua_store_session_user($freshUser);
            $sessionUser = $_SESSION['user'];
        }
        $profileMessage = 'Настройки уведомлений сохранены.';
    } elseif ($postAction === 'delete_account') {
        $pwDel = (string) ($_POST['delete_password'] ?? '');
        $confirmDel = !empty($_POST['delete_confirm']);
        if (!$confirmDel) {
            $profileError = 'Отметьте согласие на удаление аккаунта.';
        } else {
            $rowDel = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $rowDel->execute([(int) $sessionUser['id']]);
            $fullDel = $rowDel->fetch();
            $hashDel = (string) ($fullDel['password_hash'] ?? '');
            if ($hashDel === '' || !password_verify($pwDel, $hashDel)) {
                $profileError = 'Неверный пароль. Аккаунт не удалён.';
            } else {
                $avDel = (string) ($fullDel['avatar'] ?? '');
                if ($avDel !== '' && strpos($avDel, '/uploads/users/') === 0) {
                    $diskAv = DOC_ROOT . $avDel;
                    if (is_file($diskAv)) {
                        @unlink($diskAv);
                    }
                }
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([(int) $sessionUser['id']]);
                $_SESSION['lk_flash'] = 'Аккаунт удалён. Вы вышли из системы.';
                unset($_SESSION['user'], $_SESSION['client_user'], $_SESSION['admin_id'], $_SESSION['admin_login']);
                header('Location: /');
                exit;
            }
        }
    }
}

$lkFormatDt = static function (?string $s): string {
    if ($s === null || trim($s) === '') {
        return '—';
    }
    $ts = strtotime($s);
    return $ts ? date('d.m.Y H:i', $ts) : htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
};

$accountMeta = ['created_at' => null, 'last_login_at' => null];
if ($isClientLogged) {
    $ms = $pdo->prepare('SELECT created_at, last_login_at FROM users WHERE id = ? LIMIT 1');
    $ms->execute([(int) $sessionUser['id']]);
    $mr = $ms->fetch();
    if ($mr) {
        $accountMeta['created_at'] = $mr['created_at'] ?? null;
        $accountMeta['last_login_at'] = $mr['last_login_at'] ?? null;
    }
}

$lkFocusAccountTab = $_SERVER['REQUEST_METHOD'] === 'POST' && $isClientLogged
    && in_array((string) ($_POST['action'] ?? ''), ['update_profile', 'change_password', 'update_notifications', 'delete_account'], true);

require_once __DIR__ . '/include/header.php';
?>
<section class="main__members-section-top members-section-top">
    <div class="container">
        <div class="members-section-top__inner">
            <?php if ($isClientLogged): ?>
            <form method="post" enctype="multipart/form-data" class="lk-avatar-col lk-avatar-col--with-form">
                <input type="hidden" name="action" value="update_avatar">
                <div class="members-section-top__img lk-avatar-wrap">
                    <img id="lk-avatar-display" class="members-section-top__img-image" src="<?= htmlspecialchars($avatar) ?>" alt="Фото профиля" data-lk-avatar-initial="<?= htmlspecialchars($avatar) ?>">
                    <label for="lk-avatar-file" class="lk-avatar-overlay">
                        <span class="lk-avatar-overlay__inner">
                            <svg class="lk-avatar-overlay__icon" width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M4 7h3l1.5-2h7L17 7h3a2 2 0 012 2v9a2 2 0 01-2 2H4a2 2 0 01-2-2V9a2 2 0 012-2z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                                <circle cx="12" cy="13" r="3.2" stroke="currentColor" stroke-width="1.6"/>
                            </svg>
                            <span class="lk-avatar-overlay__text">Сменить фото</span>
                        </span>
                    </label>
                    <input id="lk-avatar-file" class="lk-avatar-file-native" type="file" name="avatar_file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif" tabindex="-1">
                </div>
                <div class="lk-avatar-toolbar" id="lk-avatar-toolbar" hidden>
                    <p class="lk-avatar-toolbar__hint">Выбрано новое фото — сохраните или отмените</p>
                    <div class="lk-avatar-toolbar__btns">
                        <button type="submit" class="members-section-top-body-box__buttons-link members-section-top-body-box__buttons-link--green lk-avatar-toolbar__btn">Сохранить</button>
                        <button type="button" class="members-section-top-body-box__buttons-link lk-avatar-toolbar__btn lk-avatar-toolbar__btn--ghost" id="lk-avatar-cancel">Отмена</button>
                    </div>
                </div>
            </form>
            <?php else: ?>
            <div class="lk-avatar-col">
                <div class="members-section-top__img">
                    <img class="members-section-top__img-image" src="<?= htmlspecialchars($avatar) ?>" alt="">
                </div>
            </div>
            <?php endif; ?>
            <div class="members-section-top__body members-section-top-body">
                <p class="members-section-top-body__suptext">Личный кабинет</p>
                <h2 class="members-section-top-body__title">
                    <?= $isClientLogged ? htmlspecialchars((string) ($sessionUser['name'] ?: $sessionUser['login'])) : 'Гость' ?>
                </h2>
                <p class="members-section-top-body__link">
                    <?= $isClientLogged ? htmlspecialchars((string) ($sessionUser['email'] ?: $sessionUser['login'])) : 'Войдите, чтобы управлять профилем и курсами.' ?>
                </p>
                <?php if ($profileMessage): ?><p style="color:#6d9985; margin:.2rem 0 .5rem;"><?= htmlspecialchars($profileMessage) ?></p><?php endif; ?>
                <?php if ($profileError): ?><p style="color:#ff99c0; margin:.2rem 0 .5rem;"><?= htmlspecialchars($profileError) ?></p><?php endif; ?>
                <div class="members-section-top-body__box members-section-top-body-box">
                    <div class="members-section-top-body-box__buttons">
                        <?php if ($isClientLogged): ?>
                            <a class="members-section-top-body-box__buttons-link members-section-top-body-box__buttons-link--green" href="/courses.php"><span>Мои курсы</span></a>
                            <?php if ($isAdminUser): ?>
                                <a class="members-section-top-body-box__buttons-link" href="/admin/"><span>Админка</span></a>
                            <?php endif; ?>
                            <a class="members-section-top-body-box__buttons-link members-section-top-body-box__buttons-link--purple" href="/user-logout.php"><span>Выйти</span></a>
                        <?php else: ?>
                            <a class="members-section-top-body-box__buttons-link members-section-top-body-box__buttons-link--green" data-fancybox="" href="#login-popup"><span>Войти</span></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="lk-side-card">
                <p class="lk-side-card__label">Статус</p>
                <p class="lk-side-card__value"><?= $isAdminUser ? 'Администратор' : 'Пользователь' ?></p>
                <p class="lk-side-card__meta">Профиль активен и готов к обучению</p>
            </div>
        </div>
    </div>
</section>

<section class="main__members-section members-section">
    <div class="container">
        <div class="members-section__tabs tabs">
            <button class="tabs__btn tabs__btn--active" type="button" id="1">Профиль</button>
            <button class="tabs__btn" type="button" id="2">Аккаунт</button>
            <button class="tabs__btn" type="button" id="3">Курсы <span>0</span></button>
            <button class="tabs__btn" type="button" id="4">Сообщения <span>0</span></button>
        </div>

        <div class="members-section__inner">
            <div class="members-section__wrapper members-section__wrapper--active" id="1">
                <div class="members-section__profile-info profile-info">
                    <div class="profile-info__about profile-info-about">
                        <h2 class="profile-info-about__title">О кабинете</h2>
                        <p class="profile-info-about__text">
                            Это пользовательский ЛК (отдельно от админки). Здесь можно развивать профиль, хранить прогресс и управлять обучением.
                        </p>
                        <?php if ($isClientLogged): ?>
                        <p class="profile-info-about__text lk-profile-hint">
                            Во вкладке «Аккаунт» — профиль, пароль, уведомления, выгрузка данных и удаление аккаунта; юридические тексты — в подвале сайта. Фото профиля — по клику на аватар выше.
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="members-section__wrapper" id="2">
                <div class="members-section__content lk-account">
                    <?php if ($isClientLogged): ?>
                    <div class="lk-settings-grid">
                        <div class="lk-settings-card profile-info-about">
                            <h2 class="profile-info-about__title">Личные данные</h2>
                            <p class="profile-info-about__text lk-settings-muted">Логин используется для входа и не меняется.</p>
                            <form method="post" class="lk-settings-form">
                                <input type="hidden" name="action" value="update_profile">
                                <label class="lk-settings-label" for="lk-profile-login">Логин</label>
                                <input class="comment__form-input" type="text" id="lk-profile-login" value="<?= htmlspecialchars((string) ($sessionUser['login'] ?? '')) ?>" readonly tabindex="-1">
                                <label class="lk-settings-label" for="lk-profile-name">Имя</label>
                                <input class="comment__form-input" type="text" id="lk-profile-name" name="name" value="<?= htmlspecialchars((string) ($sessionUser['name'] ?? '')) ?>" required maxlength="128" autocomplete="name">
                                <label class="lk-settings-label" for="lk-profile-email">Email</label>
                                <input class="comment__form-input" type="email" id="lk-profile-email" name="email" value="<?= htmlspecialchars((string) ($sessionUser['email'] ?? '')) ?>" required maxlength="255" autocomplete="email">
                                <p class="lk-settings-meta">Дата регистрации: <?= $lkFormatDt(isset($accountMeta['created_at']) ? (string) $accountMeta['created_at'] : null) ?></p>
                                <p class="lk-settings-meta">Последний вход: <?= $lkFormatDt(isset($accountMeta['last_login_at']) ? (string) $accountMeta['last_login_at'] : null) ?></p>
                                <button class="comment__form-btn lk-settings-submit" type="submit">Сохранить данные</button>
                            </form>
                        </div>
                        <div class="lk-settings-card profile-info-about">
                            <h2 class="profile-info-about__title">Безопасность</h2>
                            <p class="profile-info-about__text lk-settings-muted">Рекомендуем уникальный пароль, который вы не используете на других сайтах.</p>
                            <form method="post" class="lk-settings-form" autocomplete="off">
                                <input type="hidden" name="action" value="change_password">
                                <label class="lk-settings-label" for="lk-pw-current">Текущий пароль</label>
                                <input class="comment__form-input" type="password" id="lk-pw-current" name="current_password" required autocomplete="current-password">
                                <label class="lk-settings-label" for="lk-pw-new">Новый пароль</label>
                                <input class="comment__form-input" type="password" id="lk-pw-new" name="new_password" required minlength="8" autocomplete="new-password">
                                <label class="lk-settings-label" for="lk-pw-new2">Повторите новый пароль</label>
                                <input class="comment__form-input" type="password" id="lk-pw-new2" name="new_password_confirm" required minlength="8" autocomplete="new-password">
                                <button class="comment__form-btn lk-settings-submit" type="submit">Сменить пароль</button>
                            </form>
                        </div>
                        <div class="lk-settings-card profile-info-about">
                            <h2 class="profile-info-about__title">Уведомления</h2>
                            <p class="profile-info-about__text lk-settings-muted">Выберите, о чём можно писать на email. Фактическая рассылка подключается на стороне сервиса писем.</p>
                            <form method="post" class="lk-settings-form lk-settings-form--checks">
                                <input type="hidden" name="action" value="update_notifications">
                                <label class="lk-settings-check">
                                    <input type="checkbox" name="notify_courses" value="1" <?= !empty($sessionUser['notify_courses']) ? 'checked' : '' ?>>
                                    <span>Курсы и обучение (напоминания, обновления материалов)</span>
                                </label>
                                <label class="lk-settings-check">
                                    <input type="checkbox" name="notify_news" value="1" <?= !empty($sessionUser['notify_news']) ? 'checked' : '' ?>>
                                    <span>Новости и анонсы платформы</span>
                                </label>
                                <label class="lk-settings-check">
                                    <input type="checkbox" name="notify_marketing" value="1" <?= !empty($sessionUser['notify_marketing']) ? 'checked' : '' ?>>
                                    <span>Акции и персональные предложения</span>
                                </label>
                                <button class="comment__form-btn lk-settings-submit" type="submit">Сохранить уведомления</button>
                            </form>
                        </div>
                        <div class="lk-settings-card profile-info-about lk-settings-card--wide lk-danger-zone">
                            <h2 class="profile-info-about__title">Данные и конфиденциальность</h2>
                            <p class="profile-info-about__text lk-settings-muted">Экспорт и политика — по запросам GDPR и для прозрачности. Удаление аккаунта необратимо: прогресс и заявки, привязанные к профилю, станут недоступны.</p>
                            <p class="lk-settings-actions">
                                <a class="comment__form-btn lk-settings-submit lk-settings-link-btn" href="/lk-export.php">Скачать мои данные (JSON)</a>
                                <a class="members-section-top-body-box__buttons-link lk-settings-outline-btn" href="/privacy.php#privacy" style="display:inline-flex;"><span>Политика конфиденциальности</span></a>
                                <a class="members-section-top-body-box__buttons-link lk-settings-outline-btn" href="/privacy.php#terms" style="display:inline-flex;"><span>Условия использования</span></a>
                            </p>
                            <?php if ($isAdminUser): ?>
                            <p class="lk-danger-note">Вы вошли как администратор: после удаления этого аккаунта доступ в админку по нему будет потерян.</p>
                            <?php endif; ?>
                            <form method="post" class="lk-settings-form lk-danger-form">
                                <input type="hidden" name="action" value="delete_account">
                                <label class="lk-settings-label" for="lk-del-password">Пароль для подтверждения</label>
                                <input class="comment__form-input" type="password" id="lk-del-password" name="delete_password" required autocomplete="current-password">
                                <label class="lk-settings-check lk-settings-check--danger">
                                    <input type="checkbox" name="delete_confirm" value="1" required>
                                    <span>Я понимаю: аккаунт и связанные данные будут удалены безвозвратно.</span>
                                </label>
                                <button class="comment__form-btn lk-settings-submit lk-danger-btn" type="submit">Удалить аккаунт навсегда</button>
                            </form>
                        </div>
                    </div>
                    <?php else: ?>
                    <p class="profile-info-about__text">Войдите, чтобы управлять данными аккаунта и паролем.</p>
                    <p style="margin-top:1rem;"><a class="members-section-top-body-box__buttons-link members-section-top-body-box__buttons-link--green" data-fancybox="" href="#login-popup" style="display:inline-flex;"><span>Войти</span></a></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="members-section__wrapper" id="3">
                <div class="members-section__content">
                    <p>Ваши курсы появятся здесь.</p>
                </div>
            </div>

            <div class="members-section__wrapper" id="4">
                <div class="members-section__content">
                    <p>Ваши сообщения появятся здесь.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    var file = document.getElementById('lk-avatar-file');
    var img = document.getElementById('lk-avatar-display');
    var toolbar = document.getElementById('lk-avatar-toolbar');
    var cancel = document.getElementById('lk-avatar-cancel');
    if (!file || !img || !toolbar) return;
    var initial = img.getAttribute('data-lk-avatar-initial') || img.src;
    var blobUrl = null;
    function revokeBlob() {
        if (blobUrl) {
            URL.revokeObjectURL(blobUrl);
            blobUrl = null;
        }
    }
    function resetPicker() {
        file.value = '';
        revokeBlob();
        img.src = initial;
        toolbar.hidden = true;
    }
    file.addEventListener('change', function () {
        var f = file.files && file.files[0];
        revokeBlob();
        if (!f) {
            toolbar.hidden = true;
            img.src = initial;
            return;
        }
        blobUrl = URL.createObjectURL(f);
        img.src = blobUrl;
        toolbar.hidden = false;
    });
    if (cancel) {
        cancel.addEventListener('click', resetPicker);
    }
})();
<?php if (!empty($lkFocusAccountTab)): ?>
document.addEventListener('DOMContentLoaded', function () {
    var tabId = '2';
    var section = document.querySelector('.main__members-section.members-section');
    if (!section) return;
    section.querySelectorAll('.tabs__btn').forEach(function (btn) {
        btn.classList.toggle('tabs__btn--active', btn.id === tabId);
    });
    section.querySelectorAll('.members-section__wrapper').forEach(function (wrap) {
        wrap.classList.toggle('members-section__wrapper--active', wrap.id === tabId);
    });
});
<?php endif; ?>
</script>
<style>
.lk-avatar-col { align-self: flex-start; }
.lk-avatar-col--with-form {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    max-width: 240px;
}
.lk-avatar-wrap {
    position: relative;
    overflow: hidden;
}
.lk-avatar-wrap:focus-within .lk-avatar-overlay {
    opacity: 1;
}
.lk-avatar-file-native {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
.lk-avatar-overlay {
    position: absolute;
    inset: 0;
    border-radius: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    background: rgba(17, 24, 39, 0.72);
    color: #f9fafb;
    opacity: 0;
    transition: opacity 0.2s ease;
    text-align: center;
}
@media (hover: hover) {
    .lk-avatar-wrap:hover .lk-avatar-overlay {
        opacity: 1;
    }
}
@media (hover: none) {
    .lk-avatar-overlay {
        opacity: 1;
        background: linear-gradient(180deg, transparent 35%, rgba(17, 24, 39, 0.88) 100%);
        align-items: flex-end;
        padding-bottom: 14px;
    }
}
.lk-avatar-overlay__inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    pointer-events: none;
}
.lk-avatar-overlay__icon {
    color: #f9d442;
}
.lk-avatar-overlay__text {
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    max-width: 140px;
    line-height: 1.2;
}
.lk-avatar-toolbar {
    width: 100%;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.14);
    background: rgba(255, 255, 255, 0.06);
    box-sizing: border-box;
}
.lk-avatar-toolbar__hint {
    margin: 0 0 10px;
    font-size: 13px;
    line-height: 1.35;
    color: #d1d5db;
}
.lk-avatar-toolbar__btns {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.lk-avatar-toolbar__btn {
    flex: 1 1 auto;
    min-width: 0;
    justify-content: center;
    min-height: 40px;
    font-size: 14px;
}
.lk-avatar-toolbar__btn--ghost {
    border: 1px solid rgba(255, 255, 255, 0.35);
    background: transparent;
}
.lk-side-card{
    min-width: 220px;
    max-width: 240px;
    padding: 16px;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,.18);
    background: rgba(255,255,255,.08);
    align-self: flex-start;
    margin-top: 6px;
}
.lk-side-card__label{ margin:0 0 6px; color:#d7dbe3; font-size:12px; font-weight:600; }
.lk-side-card__value{ margin:0 0 8px; font-size:22px; font-weight:800; color:#fff; }
.lk-side-card__meta{ margin:0; font-size:13px; color:#d6dde8; line-height:1.35; }
@media (max-width: 1200px){
    .lk-side-card{ min-width: 200px; max-width: 220px; }
}
@media (max-width: 980px){
    .lk-side-card{ display:none; }
    .lk-avatar-col--with-form { max-width: 220px; }
}
@media (max-width: 760px){
    .lk-avatar-col{ width: 100%; }
    .lk-avatar-col--with-form { max-width: 100%; align-items: stretch; }
    .members-section-top__inner .lk-avatar-col--with-form .members-section-top__img {
        margin-left: auto;
        margin-right: auto;
    }
}
.lk-profile-hint { margin-top: 0.75rem; opacity: 0.92; }
.lk-account { width: 100%; }
.lk-settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
    align-items: start;
}
.lk-settings-card {
    padding: 20px 22px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.04);
    box-sizing: border-box;
}
.lk-settings-card .profile-info-about__title { margin-top: 0; }
.lk-settings-muted {
    font-size: 14px;
    opacity: 0.88;
    margin-bottom: 1rem !important;
}
.lk-settings-form { display: flex; flex-direction: column; gap: 10px; max-width: 420px; }
.lk-settings-label {
    font-size: 13px;
    font-weight: 600;
    color: #e5e7eb;
    margin-bottom: -4px;
}
.lk-settings-form .comment__form-input[readonly] {
    opacity: 0.75;
    cursor: default;
}
.lk-settings-submit { margin-top: 6px; align-self: flex-start; }
.lk-settings-card--wide { grid-column: 1 / -1; max-width: 720px; }
.lk-settings-meta { font-size: 13px; color: #b8c0cc; margin: 0; line-height: 1.35; }
.lk-settings-form--checks { gap: 12px; }
.lk-settings-check {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 14px;
    line-height: 1.35;
    color: #e5e7eb;
    cursor: pointer;
}
.lk-settings-check input { margin-top: 3px; flex-shrink: 0; }
.lk-settings-check--danger span { color: #fecaca; }
.lk-settings-actions { display: flex; flex-wrap: wrap; gap: 10px; margin: 0 0 1rem; align-items: center; }
.lk-settings-link-btn { text-decoration: none !important; display: inline-flex !important; align-items: center; justify-content: center; box-sizing: border-box; }
.lk-settings-outline-btn { min-height: 40px; box-sizing: border-box; }
.lk-danger-zone { border-color: rgba(239, 68, 68, 0.4) !important; background: rgba(239, 68, 68, 0.07) !important; }
.lk-danger-note { font-size: 13px; color: #fca5a5; margin: 0 0 1rem; line-height: 1.35; }
.lk-danger-btn { background: #b91c1c !important; color: #fff !important; }
.lk-danger-btn:hover { filter: brightness(1.08); }
.lk-danger-form { margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid rgba(255,255,255,.1); }
</style>

<?php require_once __DIR__ . '/include/footer.php'; ?>
