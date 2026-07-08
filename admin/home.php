<?php
require_once __DIR__ . '/auth.php';
require_site_content_editor();
$adminNavActive = 'home';

require_once DOC_ROOT . '/include/data.php';

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedLock = trim((string) ($_POST['home_lock_at'] ?? ''));
    $currentLock = home_settings_lock_value($pdo);
    if ($postedLock !== '' && $currentLock !== '' && $postedLock !== $currentLock) {
        $error = 'Настройки уже изменены в другой вкладке или другим редактором. Обновите страницу, чтобы не затереть чужие правки.';
    }
    if ($error === '') {
        $saved = 0;
        $signup_keys = ['signup_form_title', 'signup_form_label_name', 'signup_form_label_email', 'signup_form_label_message', 'signup_form_btn', 'signup_form_success'];
        foreach (HOME_DEFAULTS as $key => $default) {
            if (in_array($key, $signup_keys, true)) {
                continue;
            }
            $value = isset($_POST['setting'][$key]) ? trim((string) $_POST['setting'][$key]) : $default;
            if (save_home_setting($key, $value)) {
                $saved++;
            }
        }
        ep_home_i18n_save_from_post($pdo, $_POST);
        $message = 'Настройки главной страницы сохранены.';
        cms_log($pdo, 'save', 'home_settings', null, ['saved_keys' => $saved]);
    }
}

$home = get_home_settings();
$homeLockAt = home_settings_lock_value($pdo);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Главная страница — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <main class="admin-main">
        <div class="page-title">
            <h1>Главная страница</h1>
            <a href="/" target="_blank" class="btn btn-secondary">Открыть сайт</a>
        </div>
        <?php if ($message): ?><p class="admin-flash admin-flash--ok"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="admin-flash admin-flash--err"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <form method="post" id="home-settings-form" class="js-admin-form-unsaved">
            <input type="hidden" name="home_lock_at" value="<?= htmlspecialchars($homeLockAt, ENT_QUOTES, 'UTF-8') ?>">
            <fieldset class="admin-fieldset">
                <legend>SEO главной страницы</legend>
                <p class="admin-lead admin-muted" style="margin-top:0;">Title и description для тега &lt;title&gt;, сниппета в поиске и карточек в соцсетях.</p>
                <div class="form-group">
                    <label for="home-seo-title">Meta title</label>
                    <input id="home-seo-title" type="text" name="setting[seo_title]" value="<?= htmlspecialchars($home['seo_title'] ?? '') ?>" maxlength="255" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="home-seo-desc">Meta description</label>
                    <textarea id="home-seo-desc" name="setting[seo_description]" maxlength="500" rows="3"><?= htmlspecialchars($home['seo_description'] ?? '') ?></textarea>
                </div>
            </fieldset>
            <fieldset class="admin-fieldset">
                <legend>Баннер (заголовок)</legend>
                <div class="admin-fieldset-split">
                    <div class="admin-fieldset-split__form">
                        <div class="form-group">
                            <label for="home-in-hero-1">Первая часть (слева)</label>
                            <input id="home-in-hero-1" type="text" name="setting[hero_1]" value="<?= htmlspecialchars($home['hero_1']) ?>" placeholder="Easy" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="home-in-hero-2">Середина (текст)</label>
                            <input id="home-in-hero-2" type="text" name="setting[hero_2]" value="<?= htmlspecialchars($home['hero_2']) ?>" placeholder="обучение для увлечённых" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="home-in-hero-3">Третья часть (справа)</label>
                            <input id="home-in-hero-3" type="text" name="setting[hero_3]" value="<?= htmlspecialchars($home['hero_3']) ?>" placeholder="People" autocomplete="off">
                        </div>
                    </div>
                    <div class="admin-fieldset-split__preview">
                        <p class="admin-preview-caption">Превью баннера</p>
                        <p class="admin-preview-hint">Стиль как на главной сайта (упрощённо).</p>
                        <div class="admin-preview-canvas admin-preview-canvas--hero">
                            <h1 class="admin-preview-hero__title">
                                <span class="admin-preview-hero__pill" id="homePvHero1"></span><span class="admin-preview-hero__mid" id="homePvHero2"></span><span class="admin-preview-hero__outline" id="homePvHero3"></span>
                            </h1>
                        </div>
                    </div>
                </div>
            </fieldset>

            <fieldset class="admin-fieldset">
                <legend>Блок «Счётчики» (4 карточки)</legend>
                <div class="admin-fieldset-split">
                    <div class="admin-fieldset-split__form">
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div class="form-row admin-home-count-row">
                            <div class="form-group">
                                <label for="home-in-count-<?= $i ?>-num">Карточка <?= $i ?> — число</label>
                                <input id="home-in-count-<?= $i ?>-num" type="text" name="setting[count_<?= $i ?>_num]" value="<?= htmlspecialchars($home['count_' . $i . '_num']) ?>" placeholder="100+" autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label for="home-in-count-<?= $i ?>-text">Карточка <?= $i ?> — подпись</label>
                                <input id="home-in-count-<?= $i ?>-text" type="text" name="setting[count_<?= $i ?>_text]" value="<?= htmlspecialchars($home['count_' . $i . '_text']) ?>" autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label for="home-in-count-<?= $i ?>-url">Карточка <?= $i ?> — ссылка (пусто = без ссылки)</label>
                                <input id="home-in-count-<?= $i ?>-url" type="text" name="setting[count_<?= $i ?>_url]" value="<?= htmlspecialchars($home['count_' . $i . '_url']) ?>" placeholder="/members/" autocomplete="off">
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <div class="admin-fieldset-split__preview">
                        <p class="admin-preview-caption">Превью счётчиков</p>
                        <p class="admin-preview-hint">Цвета карточек как на сайте.</p>
                        <div class="admin-preview-canvas admin-preview-canvas--counts">
                            <div class="admin-preview-counts" id="homePvCounts">
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                <div class="admin-preview-counts__item admin-preview-counts__item--<?= $i ?>">
                                    <div class="admin-preview-counts__icon" aria-hidden="true">
                                        <svg width="14" height="14" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.5 6H9M6.5 3L9.146 5.646a.5.5 0 010 .708L6.5 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                    </div>
                                    <p class="admin-preview-counts__num">
                                        <span id="homePvNum<?= $i ?>"></span>
                                        <?php if ((int) $i === 4): ?>
                                        <span class="admin-preview-counts__star" aria-hidden="true">
                                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M11.97 4.603a.409.409 0 00-.55-.438L7.956 3.85 6.587.645a.51.51 0 00-.922 0L4.044 3.85.58 4.165a.409.409 0 00-.227.712l2.618 2.296-.772 3.4a.409.409 0 00.605.448L6 9.881l2.987 1.785a.409.409 0 00.62-.447l-.772-3.4 2.618-2.297a.408.408 0 00.117-.32z" fill="#F9D442"/></svg>
                                        </span>
                                        <?php endif; ?>
                                    </p>
                                    <p class="admin-preview-counts__sub" id="homePvText<?= $i ?>"></p>
                                    <p class="admin-preview-counts__url" id="homePvUrl<?= $i ?>"></p>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </fieldset>

            <fieldset class="admin-fieldset">
                <legend>Блок «Elevate» (призыв к действию)</legend>
                <div class="admin-fieldset-split">
                    <div class="admin-fieldset-split__form">
                        <div class="form-group">
                            <label for="home-in-elevate-1">Заголовок, первая строка</label>
                            <input id="home-in-elevate-1" type="text" name="setting[elevate_title_1]" value="<?= htmlspecialchars($home['elevate_title_1']) ?>" placeholder="Развивай свои soft skills" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="home-in-elevate-2">Заголовок, вторая часть (можно с переносами строк)</label>
                            <textarea id="home-in-elevate-2" name="setting[elevate_title_2]" rows="2" placeholder="Учись и общайся с единомышленниками в Easy People"><?= htmlspecialchars($home['elevate_title_2']) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="home-in-elevate-btn">Текст кнопки</label>
                            <input id="home-in-elevate-btn" type="text" name="setting[elevate_btn_text]" value="<?= htmlspecialchars($home['elevate_btn_text']) ?>" placeholder="Поиск курсов" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="home-in-elevate-url">Ссылка кнопки</label>
                            <input id="home-in-elevate-url" type="text" name="setting[elevate_btn_url]" value="<?= htmlspecialchars($home['elevate_btn_url']) ?>" placeholder="/courses.php" autocomplete="off">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="home-in-elevate-stat-num">Число в блоке справа</label>
                                <input id="home-in-elevate-stat-num" type="text" name="setting[elevate_stat_num]" value="<?= htmlspecialchars($home['elevate_stat_num']) ?>" placeholder="5+" autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label for="home-in-elevate-stat-text">Подпись под числом</label>
                                <input id="home-in-elevate-stat-text" type="text" name="setting[elevate_stat_text]" value="<?= htmlspecialchars($home['elevate_stat_text']) ?>" placeholder="Профессионалов" autocomplete="off">
                            </div>
                        </div>
                    </div>
                    <div class="admin-fieldset-split__preview">
                        <p class="admin-preview-caption">Превью Elevate</p>
                        <p class="admin-preview-hint">Блок с кнопкой и цифрой справа.</p>
                        <div class="admin-preview-canvas admin-preview-canvas--counts" style="padding:0;">
                            <div class="admin-preview-elevate">
                                <div class="admin-preview-elevate__content">
                                    <div id="homePvElevateTitle" class="admin-preview-elevate__title"></div>
                                    <span id="homePvElevateBtn" class="admin-preview-elevate__btn"></span>
                                    <p id="homePvElevateUrl" style="margin:0.4rem 0 0;font-size:0.62rem;color:rgba(255,255,255,0.45);word-break:break-all;"></p>
                                </div>
                                <div class="admin-preview-elevate__stat">
                                    <p id="homePvElevateStatNum" class="admin-preview-elevate__stat-num"></p>
                                    <p id="homePvElevateStatText" class="admin-preview-elevate__stat-text"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </fieldset>

            <fieldset class="admin-fieldset">
                <legend>Заголовки секций и промо-событие</legend>
                <div class="admin-fieldset-split">
                    <div class="admin-fieldset-split__form">
                        <div class="form-group">
                            <label for="home-in-sec-groups">Заголовок блока «Популярные курсы»</label>
                            <input id="home-in-sec-groups" type="text" name="setting[groups_section_title]" value="<?= htmlspecialchars($home['groups_section_title']) ?>" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="home-in-sec-new">Заголовок блока «Новые курсы»</label>
                            <input id="home-in-sec-new" type="text" name="setting[new_courses_title]" value="<?= htmlspecialchars($home['new_courses_title']) ?>" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="home-in-sec-news">Заголовок блока «Новости»</label>
                            <input id="home-in-sec-news" type="text" name="setting[news_section_title]" value="<?= htmlspecialchars($home['news_section_title']) ?>" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="home-in-sec-slug">Промо-событие на главной (slug события, пусто = первое по списку)</label>
                            <input id="home-in-sec-slug" type="text" name="setting[section_top_event_slug]" value="<?= htmlspecialchars($home['section_top_event_slug']) ?>" placeholder="например: creative-forum-25" autocomplete="off">
                        </div>
                    </div>
                    <div class="admin-fieldset-split__preview">
                        <p class="admin-preview-caption">Превью подписей</p>
                        <p class="admin-preview-hint">Как будут показаны заголовки секций.</p>
                        <div class="admin-preview-panel">
                            <div id="homePvSections" class="admin-preview-sections"></div>
                        </div>
                    </div>
                </div>
            </fieldset>

            <?php include __DIR__ . '/partials/form-home-i18n.php'; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </main>
    <script>
    (function () {
        var h1 = document.getElementById('homePvHero1');
        var h2 = document.getElementById('homePvHero2');
        var h3 = document.getElementById('homePvHero3');
        var in1 = document.getElementById('home-in-hero-1');
        var in2 = document.getElementById('home-in-hero-2');
        var in3 = document.getElementById('home-in-hero-3');

        function syncHero() {
            if (h1 && in1) h1.textContent = in1.value;
            if (h2 && in2) h2.textContent = in2.value;
            if (h3 && in3) h3.textContent = in3.value;
        }

        function syncCounts() {
            for (var i = 1; i <= 4; i++) {
                var num = document.getElementById('home-in-count-' + i + '-num');
                var text = document.getElementById('home-in-count-' + i + '-text');
                var url = document.getElementById('home-in-count-' + i + '-url');
                var elN = document.getElementById('homePvNum' + i);
                var elT = document.getElementById('homePvText' + i);
                var elU = document.getElementById('homePvUrl' + i);
                if (elN && num) elN.textContent = num.value;
                if (elT && text) elT.textContent = text.value;
                if (elU && url) {
                    var u = (url.value || '').trim();
                    if (u) elU.textContent = '→ ' + u;
                    else elU.textContent = '';
                }
            }
        }

        function syncAll() {
            syncHero();
            syncCounts();
        }

        [in1, in2, in3].forEach(function (el) {
            if (el) el.addEventListener('input', syncHero);
        });
        for (var j = 1; j <= 4; j++) {
            ['num', 'text', 'url'].forEach(function (f) {
                var inp = document.getElementById('home-in-count-' + j + '-' + f);
                if (inp) inp.addEventListener('input', syncCounts);
            });
        }

        function syncElevate() {
            var l1 = document.getElementById('home-in-elevate-1');
            var l2 = document.getElementById('home-in-elevate-2');
            var bt = document.getElementById('home-in-elevate-btn');
            var ur = document.getElementById('home-in-elevate-url');
            var sn = document.getElementById('home-in-elevate-stat-num');
            var st = document.getElementById('home-in-elevate-stat-text');
            var box = document.getElementById('homePvElevateTitle');
            if (box) {
                var a = (l1 && l1.value) ? l1.value : '';
                var b = (l2 && l2.value) ? l2.value : '';
                box.innerHTML = '';
                if (a) {
                    var t1 = document.createElement('span');
                    t1.textContent = a;
                    box.appendChild(t1);
                }
                if (b) {
                    if (a) box.appendChild(document.createElement('br'));
                    var t2 = document.createElement('span');
                    t2.textContent = b;
                    box.appendChild(t2);
                }
                if (!a && !b) box.textContent = '—';
            }
            var bEl = document.getElementById('homePvElevateBtn');
            if (bEl) bEl.textContent = (bt && bt.value.trim()) ? bt.value.trim() : 'Кнопка';
            var uEl = document.getElementById('homePvElevateUrl');
            if (uEl) {
                var u = (ur && ur.value.trim()) ? ur.value.trim() : '';
                uEl.textContent = u ? ('Ссылка: ' + u) : '';
            }
            document.getElementById('homePvElevateStatNum').textContent = (sn && sn.value.trim()) ? sn.value.trim() : '—';
            document.getElementById('homePvElevateStatText').textContent = (st && st.value.trim()) ? st.value.trim() : '';
        }

        function syncSections() {
            var g = document.getElementById('home-in-sec-groups');
            var n = document.getElementById('home-in-sec-new');
            var w = document.getElementById('home-in-sec-news');
            var s = document.getElementById('home-in-sec-slug');
            var out = document.getElementById('homePvSections');
            if (!out) return;
            function row(label, val) {
                var p = document.createElement('p');
                p.className = 'admin-preview-sections__row';
                var lb = document.createElement('span');
                lb.className = 'admin-preview-sections__label';
                lb.textContent = label;
                p.appendChild(lb);
                p.appendChild(document.createTextNode(val || '—'));
                return p;
            }
            out.innerHTML = '';
            out.appendChild(row('Курсы:', g && g.value));
            out.appendChild(row('Новые:', n && n.value));
            out.appendChild(row('Новости:', w && w.value));
            var slug = document.createElement('p');
            slug.className = 'admin-preview-sections__slug';
            slug.textContent = (s && s.value.trim()) ? ('Промо-событие (slug): ' + s.value.trim()) : 'Промо-событие: автоматически первое в списке';
            out.appendChild(slug);
        }

        ['home-in-elevate-1', 'home-in-elevate-2', 'home-in-elevate-btn', 'home-in-elevate-url', 'home-in-elevate-stat-num', 'home-in-elevate-stat-text'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('input', syncElevate);
        });
        ['home-in-sec-groups', 'home-in-sec-new', 'home-in-sec-news', 'home-in-sec-slug'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('input', syncSections);
        });

        syncAll();
        syncElevate();
        syncSections();
    })();
    </script>
    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
