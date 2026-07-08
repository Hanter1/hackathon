<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/user-auth.php';
require_once __DIR__ . '/i18n.php';
ep_i18n_bootstrap();
$epLang = ep_lang();
$epLangLabels = ep_lang_labels();
$isAdminLogged = !empty($_SESSION['admin_id']);
$sessionUser = $_SESSION['user'] ?? null;
$isClientLogged = is_array($sessionUser) && !empty($sessionUser['id']);
$clientName = $isClientLogged ? (string) ($sessionUser['name'] ?: $sessionUser['login']) : 'Кабинет';
$isAdminUser = $isClientLogged && ua_is_admin_user($sessionUser);
$clientAvatar = $isClientLogged && !empty($sessionUser['avatar']) ? (string)$sessionUser['avatar'] : '/images/member-icon-3.png';
$lkPublicFlash = '';
if (!empty($_SESSION['lk_flash'])) {
    $lkPublicFlash = (string) $_SESSION['lk_flash'];
    unset($_SESSION['lk_flash']);
}
require_once __DIR__ . '/seo.php';
$cmsSeoTitle = $cmsSeoTitle ?? 'Easy People';
$cmsSeoDescription = $cmsSeoDescription ?? '';
$cmsSeoCanonical = $cmsSeoCanonical ?? '';
$cmsSeoOgImage = $cmsSeoOgImage ?? '';
$cmsSeoNoindex = !empty($cmsSeoNoindex);
$cmsSeoType = $cmsSeoType ?? 'website';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(ep_lang_html_attr(), ENT_QUOTES, 'UTF-8') ?>">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($cmsSeoTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <?php if ($cmsSeoDescription !== ''): ?>
    <meta name="description" content="<?= htmlspecialchars($cmsSeoDescription, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($cmsSeoCanonical !== ''): ?>
    <link rel="canonical" href="<?= htmlspecialchars($cmsSeoCanonical, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($cmsSeoNoindex): ?>
    <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    <meta property="og:site_name" content="Easy People">
    <meta property="og:locale" content="<?= htmlspecialchars(ep_lang_og_locale(), ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="<?= htmlspecialchars($cmsSeoType, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($cmsSeoTitle, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($cmsSeoDescription !== ''): ?>
    <meta property="og:description" content="<?= htmlspecialchars($cmsSeoDescription, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($cmsSeoCanonical !== ''): ?>
    <meta property="og:url" content="<?= htmlspecialchars($cmsSeoCanonical, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($cmsSeoOgImage !== ''): ?>
    <meta property="og:image" content="<?= htmlspecialchars($cmsSeoOgImage, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="<?= $cmsSeoOgImage !== '' ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars($cmsSeoTitle, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($cmsSeoDescription !== ''): ?>
    <meta name="twitter:description" content="<?= htmlspecialchars($cmsSeoDescription, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($cmsSeoOgImage !== ''): ?>
    <meta name="twitter:image" content="<?= htmlspecialchars($cmsSeoOgImage, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <link rel="icon" href="/images/ep/logo-smile.svg" type="image/svg+xml">
    <link rel="alternate icon" href="/images/ep/logo-img.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/images/ep/logo-img.svg">
    <link rel="stylesheet" href="/css/swiper-bundle.min.css"/>
    <link rel="stylesheet" href="/css/fancybox.css"/>
    <link rel="stylesheet" href="/css/style.min.css">
    <style>
        .lk-public-flash {
            margin: 0;
            padding: 12px 16px;
            text-align: center;
            font-size: 14px;
            line-height: 1.4;
            background: rgba(109, 153, 133, 0.22);
            color: #d1fae5;
            border-bottom: 1px solid rgba(109, 153, 133, 0.45);
        }
        .lang-box__link--active { opacity: 1; font-weight: 600; }
        .lang-box__link { opacity: 0.75; }
    </style>
</head>

<body>
<?php if ($lkPublicFlash !== ''): ?>
<div class="lk-public-flash" role="status"><?= htmlspecialchars($lkPublicFlash) ?></div>
<?php endif; ?>


<div class="cursor"></div>
<div class="cursor-follower"></div>


<header class="header">
    <div class="container">
        <div class="header__inner">
            <a class="header__logo logo" href="/">
                <img class="logo__img logo__rotate" src="/images/ep/logo-smile.svg" alt="smile logo">
                <img class="logo__text" src="/images/ep/logo.svg" alt="img">
            </a>
            <div class="header__wrapper">
                <div class="header__lang lang">
                    <div class="lang__top">
                        <img class="lang__flag" src="<?= htmlspecialchars(ep_lang_flag_src(), ENT_QUOTES, 'UTF-8') ?>" width="22" height="16" alt="">

                        <span><?= htmlspecialchars($epLangLabels[$epLang] ?? 'Русский', ENT_QUOTES, 'UTF-8') ?></span>
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none"
                             xmlns="http://www.w3.org/2000/svg">
                            <path d="M2.99997 4.5L5.99997 7.5L8.99997 4.5" stroke="white" stroke-width="1.5"
                                  stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="lang__box lang-box">
                        <?php foreach (ep_supported_langs() as $code): ?>
                        <a class="lang-box__link<?= $code === $epLang ? ' lang-box__link--active' : '' ?>" href="<?= htmlspecialchars(ep_lang_switch_url($code), ENT_QUOTES, 'UTF-8') ?>">
                            <img class="lang-box__flag" src="<?= htmlspecialchars(ep_lang_flag_src($code), ENT_QUOTES, 'UTF-8') ?>" width="22" height="16" alt="">
                            <span><?= htmlspecialchars($epLangLabels[$code] ?? $code, ENT_QUOTES, 'UTF-8') ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <form class="header__search search" action="#">
                    <button class="search__btn" type="button">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                             xmlns="http://www.w3.org/2000/svg">
                            <path
                                d="M11 19C15.4182 19 19 15.4183 19 11C19 6.58172 15.4182 3 11 3C6.58169 3 2.99997 6.58172 2.99997 11C2.99997 15.4183 6.58169 19 11 19Z"
                                stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M21 21L16.65 16.65" stroke="white" stroke-width="1.5" stroke-linecap="round"
                                  stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <input class="search__input" type="text" placeholder="<?= htmlspecialchars(ep_t('header.search'), ENT_QUOTES, 'UTF-8') ?>">
                </form>
                <?php if ($isClientLogged): ?>
                <div class="header__user-nav user-nav">
                    <a class="user-nav__link" href="/lk.php" title="Сообщения">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M7.99997 10H16M7.99997 14H12M21 12C21 16.9706 16.9705 21 12 21C10.843 21 9.73698 20.7817 8.72092 20.384C8.33906 20.2345 7.92595 20.1704 7.52157 20.2385L3.69273 20.8833C3.35371 20.9404 3.05954 20.6463 3.11664 20.3072L3.76144 16.4784C3.82954 16.074 3.76544 15.6609 3.61598 15.279C3.21829 14.263 2.99997 13.157 2.99997 12C2.99997 7.02944 7.02941 3 12 3C16.9705 3 21 7.02944 21 12Z" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                        <span>0</span>
                    </a>
                    <a class="user-nav__link" href="/lk.php" title="Уведомления">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5.99997 8C5.99997 6.4087 6.63211 4.88258 7.75733 3.75736C8.88255 2.63214 10.4087 2 12 2C13.5913 2 15.1174 2.63214 16.2426 3.75736C17.3678 4.88258 18 6.4087 18 8C18 15 21 17 21 17H2.99997C2.99997 17 5.99997 15 5.99997 8Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M10.3 21C10.4673 21.3044 10.7134 21.5583 11.0124 21.7352C11.3115 21.912 11.6525 22.0053 12 22.0053C12.3474 22.0053 12.6884 21.912 12.9875 21.7352C13.2865 21.5583 13.5326 21.3044 13.7 21" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>0</span>
                    </a>
                    <a class="user-nav__link" href="/lk.php" title="Покупки">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8.99998 10V6C8.99998 4.34315 10.3431 3 12 3C13.6568 3 15 4.34315 15 6V10M4.99998 18H19M6.14793 22H17.852C19.0127 22 19.9296 21.0152 19.8469 19.8575L19.1327 9.8575C19.0579 8.81089 18.187 8 17.1377 8H6.86222C5.81294 8 4.94206 8.81089 4.8673 9.85751L4.15301 19.8575C4.07032 21.0152 4.98725 22 6.14793 22Z" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                        <span>0</span>
                    </a>
                </div>
                <form class="header__form header-form" action="#">
                    <div class="header-form__img">
                        <img class="header-form__img-image" src="<?= htmlspecialchars($clientAvatar) ?>" alt="img">
                    </div>
                    <div class="header-form__select custom-select">
                        <select class="js-cabinet-select">
                            <option value="/lk.php"><?= htmlspecialchars($clientName) ?></option>
                            <option value="/lk.php">Личный кабинет</option>
                            <option value="/courses.php">Мои курсы</option>
                            <?php if ($isAdminUser): ?>
                            <option value="/admin/">Перейти в админку</option>
                            <?php endif; ?>
                            <option value="/user-logout.php">Выйти</option>
                        </select>
                    </div>
                </form>
                <?php elseif ($isAdminLogged): ?>
                <div class="header__user-nav user-menu">
                    <a class="act-btn" href="/admin/" title="Админка">Админка</a>
                </div>
                <?php else: ?>
                <div class="header__user-nav user-menu">
                    <a class="act-btn" data-fancybox="" href="#login-popup"><?= htmlspecialchars(ep_t('header.login'), ENT_QUOTES, 'UTF-8') ?></a>
                </div>
                <?php endif; ?>
                <script>
                (function () {
                    function goToSelectValue(selectEl) {
                        if (!selectEl) return;
                        var url = selectEl.value || '';
                        if (url) window.location.href = url;
                    }

                    document.addEventListener('change', function (e) {
                        var selectEl = e.target.closest('.js-cabinet-select');
                        if (selectEl) goToSelectValue(selectEl);
                    });

                    document.addEventListener('click', function (e) {
                        var item = e.target.closest('.header-form .select-items div');
                        if (!item) return;
                        var form = item.closest('.header-form');
                        var selectEl = form ? form.querySelector('.js-cabinet-select') : null;
                        if (!selectEl) return;
                        var pickedText = (item.textContent || '').trim();
                        if (!pickedText) return;

                        for (var i = 0; i < selectEl.options.length; i++) {
                            var optText = (selectEl.options[i].textContent || '').trim();
                            if (optText === pickedText) {
                                selectEl.selectedIndex = i;
                                break;
                            }
                        }
                        goToSelectValue(selectEl);
                    });
                })();
                </script>
                <button class="header__burger burger" type="button">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

            </div>
        </div>
    </div>
</header>
<div class="wrapper">
    <main class="main">
        <aside class="main__aside aside">
            <div class="aside__resize">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <g clip-path="url(#clip0_114_19355)">
                        <path
                                d="M14 5.25C13.5858 5.25 13.25 5.58579 13.25 6C13.25 6.41421 13.5858 6.75 14 6.75V5.25ZM18 6H18.75C18.75 5.58579 18.4142 5.25 18 5.25V6ZM17.25 10C17.25 10.4142 17.5858 10.75 18 10.75C18.4142 10.75 18.75 10.4142 18.75 10H17.25ZM13.4697 9.46967C13.1768 9.76256 13.1768 10.2374 13.4697 10.5303C13.7626 10.8232 14.2374 10.8232 14.5303 10.5303L13.4697 9.46967ZM10 18.75C10.4142 18.75 10.75 18.4142 10.75 18C10.75 17.5858 10.4142 17.25 10 17.25V18.75ZM6 18H5.25C5.25 18.1989 5.32902 18.3897 5.46967 18.5303C5.61032 18.671 5.80109 18.75 6 18.75V18ZM6.75 14C6.75 13.5858 6.41421 13.25 6 13.25C5.58579 13.25 5.25 13.5858 5.25 14H6.75ZM10.5303 14.5303C10.8232 14.2374 10.8232 13.7626 10.5303 13.4697C10.2374 13.1768 9.76256 13.1768 9.46967 13.4697L10.5303 14.5303ZM3.73005 22.455L4.07054 21.7868L3.73005 22.455ZM1.54497 20.27L2.21322 19.9295L1.54497 20.27ZM22.455 20.27L21.7868 19.9295L22.455 20.27ZM20.27 22.455L19.9295 21.7868L20.27 22.455ZM20.27 1.54497L19.9295 2.21322L20.27 1.54497ZM22.455 3.73005L21.7868 4.07054L22.455 3.73005ZM3.73005 1.54497L4.07054 2.21322L3.73005 1.54497ZM1.54497 3.73005L2.21322 4.07054L1.54497 3.73005ZM14 6.75H18V5.25H14V6.75ZM17.25 6V10H18.75V6H17.25ZM17.4697 5.46967L13.4697 9.46967L14.5303 10.5303L18.5303 6.53033L17.4697 5.46967ZM10 17.25H6V18.75H10V17.25ZM6.75 18L6.75 14H5.25L5.25 18H6.75ZM6.53033 18.5303L10.5303 14.5303L9.46967 13.4697L5.46967 17.4697L6.53033 18.5303ZM9 1.75H15V0.25H9V1.75ZM22.25 9V15H23.75V9H22.25ZM15 22.25H9V23.75H15V22.25ZM1.75 15V9H0.25V15H1.75ZM9 22.25C7.58749 22.25 6.57322 22.2494 5.77708 22.1844C4.9897 22.12 4.48197 21.9964 4.07054 21.7868L3.38955 23.1233C4.04768 23.4586 4.77479 23.6075 5.65494 23.6794C6.52632 23.7506 7.61224 23.75 9 23.75V22.25ZM0.25 15C0.25 16.3878 0.249417 17.4737 0.320612 18.3451C0.392522 19.2252 0.541378 19.9523 0.876713 20.6104L2.21322 19.9295C2.00359 19.518 1.87996 19.0103 1.81563 18.2229C1.75058 17.4268 1.75 16.4125 1.75 15H0.25ZM4.07054 21.7868C3.27085 21.3793 2.62068 20.7291 2.21322 19.9295L0.876713 20.6104C1.42798 21.6924 2.30762 22.572 3.38955 23.1233L4.07054 21.7868ZM22.25 15C22.25 16.4125 22.2494 17.4268 22.1844 18.2229C22.12 19.0103 21.9964 19.518 21.7868 19.9295L23.1233 20.6104C23.4586 19.9523 23.6075 19.2252 23.6794 18.3451C23.7506 17.4737 23.75 16.3878 23.75 15H22.25ZM15 23.75C16.3878 23.75 17.4737 23.7506 18.3451 23.6794C19.2252 23.6075 19.9523 23.4586 20.6104 23.1233L19.9295 21.7868C19.518 21.9964 19.0103 22.12 18.2229 22.1844C17.4268 22.2494 16.4125 22.25 15 22.25V23.75ZM21.7868 19.9295C21.3793 20.7291 20.7291 21.3793 19.9295 21.7868L20.6104 23.1233C21.6924 22.572 22.572 21.6924 23.1233 20.6104L21.7868 19.9295ZM15 1.75C16.4125 1.75 17.4268 1.75058 18.2229 1.81563C19.0103 1.87996 19.518 2.00359 19.9295 2.21322L20.6104 0.876713C19.9523 0.541378 19.2252 0.392522 18.3451 0.320612C17.4737 0.249417 16.3878 0.25 15 0.25V1.75ZM23.75 9C23.75 7.61224 23.7506 6.52632 23.6794 5.65494C23.6075 4.77479 23.4586 4.04768 23.1233 3.38955L21.7868 4.07054C21.9964 4.48197 22.12 4.9897 22.1844 5.77708C22.2494 6.57322 22.25 7.58749 22.25 9H23.75ZM19.9295 2.21322C20.7291 2.62068 21.3793 3.27085 21.7868 4.07054L23.1233 3.38955C22.572 2.30762 21.6924 1.42798 20.6104 0.876713L19.9295 2.21322ZM9 0.25C7.61224 0.25 6.52632 0.249417 5.65494 0.320612C4.77479 0.392522 4.04768 0.541378 3.38955 0.876713L4.07054 2.21322C4.48197 2.00359 4.9897 1.87996 5.77708 1.81563C6.57322 1.75058 7.58749 1.75 9 1.75V0.25ZM1.75 9C1.75 7.58749 1.75058 6.57322 1.81563 5.77708C1.87996 4.9897 2.00359 4.48197 2.21322 4.07054L0.876713 3.38955C0.541378 4.04768 0.392522 4.77479 0.320612 5.65494C0.249417 6.52632 0.25 7.61224 0.25 9H1.75ZM3.38955 0.876713C2.30762 1.42798 1.42798 2.30762 0.876713 3.38955L2.21322 4.07054C2.62068 3.27085 3.27085 2.62068 4.07054 2.21322L3.38955 0.876713Z"
                                fill="#7D838C"/>
                    </g>
                    <defs>
                        <clipPath id="clip0_114_19355">
                            <rect width="24" height="24" fill="white"/>
                        </clipPath>
                    </defs>
                </svg>
            </div>
            <nav class="aside__menu menu">
<?php $epNav = static fn(string $k) => htmlspecialchars(ep_t($k), ENT_QUOTES, 'UTF-8'); ?>
                <ul class="menu__list">
                    <li class="menu__list-item">
                        <a class="menu__list-link menu__list-link--active" href="/">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                 xmlns="http://www.w3.org/2000/svg">
                                <path
                                        d="M1 10.5256L10.4993 3.49031C11.382 2.83656 12.618 2.83656 13.5007 3.49031L23 10.5256M4.66667 8.19795V18.6724C4.66667 19.9579 5.76108 21 7.11111 21H16.8889C18.2389 21 19.3333 19.9579 19.3333 18.6724V8.19795M10.7778 16.3447H13.2222C13.8972 16.3447 14.4444 15.8236 14.4444 15.1809V12.8532C14.4444 12.2105 13.8972 11.6894 13.2222 11.6894H10.7778C10.1028 11.6894 9.55556 12.2105 9.55556 12.8532V15.1809C9.55556 15.8236 10.1028 16.3447 10.7778 16.3447Z"
                                        stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <span><?= $epNav('nav.home') ?></span>
                        </a>

                        <div class="popup_menu"><?= $epNav('nav.home') ?></div>

                    </li>
                    <li class="menu__list-item">
                        <a class="menu__list-link" href="/blog.php">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                 xmlns="http://www.w3.org/2000/svg">
                                <g id="icons">
                                    <path id="Vector"
                                          d="M4 22H20C20.5304 22 21.0391 21.7893 21.4142 21.4142C21.7893 21.0391 22 20.5304 22 20V4C22 3.46957 21.7893 2.96086 21.4142 2.58579C21.0391 2.21071 20.5304 2 20 2H8C7.46957 2 6.96086 2.21071 6.58579 2.58579C6.21071 2.96086 6 3.46957 6 4V20C6 20.5304 5.78929 21.0391 5.41421 21.4142C5.03914 21.7893 4.53043 22 4 22ZM4 22C3.46957 22 2.96086 21.7893 2.58579 21.4142C2.21071 21.0391 2 20.5304 2 20V11C2 9.9 2.9 9 4 9H6"
                                          stroke="white" stroke-width="1.5" stroke-linecap="round"
                                          stroke-linejoin="round"/>
                                    <path id="Vector_2" d="M18 14H10" stroke="white" stroke-width="1.5"
                                          stroke-linecap="round" stroke-linejoin="round"/>
                                    <path id="Vector_3" d="M15 18H10" stroke="white" stroke-width="1.5"
                                          stroke-linecap="round" stroke-linejoin="round"/>
                                    <path id="Vector_4" d="M10 6H18V10H10V6Z" stroke="white" stroke-width="1.5"
                                          stroke-linecap="round" stroke-linejoin="round"/>
                                </g>
                            </svg>
                            <span><?= $epNav('nav.news') ?></span>
                        </a>
                        <div class="popup_menu"><?= $epNav('nav.news') ?></div>
                    </li>
                    <li class="menu__list-item">
                        <a class="menu__list-link" href="/members/">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                 xmlns="http://www.w3.org/2000/svg">
                                <g id="icons">
                                    <path id="Vector"
                                          d="M16 21V19C16 17.9391 15.5786 16.9217 14.8284 16.1716C14.0783 15.4214 13.0609 15 12 15H6C4.93913 15 3.92172 15.4214 3.17157 16.1716C2.42143 16.9217 2 17.9391 2 19V21"
                                          stroke="white" stroke-width="1.5" stroke-linecap="round"
                                          stroke-linejoin="round"/>
                                    <path id="Vector_2"
                                          d="M9 11C11.2091 11 13 9.20914 13 7C13 4.79086 11.2091 3 9 3C6.79086 3 5 4.79086 5 7C5 9.20914 6.79086 11 9 11Z"
                                          stroke="white" stroke-width="1.5" stroke-linecap="round"
                                          stroke-linejoin="round"/>
                                    <path id="Vector_3"
                                          d="M22 21V19C21.9993 18.1137 21.7044 17.2528 21.1614 16.5523C20.6184 15.8519 19.8581 15.3516 19 15.13"
                                          stroke="white" stroke-width="1.5" stroke-linecap="round"
                                          stroke-linejoin="round"/>
                                    <path id="Vector_4"
                                          d="M16 3.13C16.8604 3.35031 17.623 3.85071 18.1676 4.55232C18.7122 5.25392 19.0078 6.11683 19.0078 7.005C19.0078 7.89318 18.7122 8.75608 18.1676 9.45769C17.623 10.1593 16.8604 10.6597 16 10.88"
                                          stroke="white" stroke-width="1.5" stroke-linecap="round"
                                          stroke-linejoin="round"/>
                                </g>
                            </svg>
                            <span><?= $epNav('nav.teachers') ?></span>
                        </a>
                        <div class="popup_menu"><?= $epNav('nav.teachers') ?></div>
                    </li>
                    <li class="menu__list-item">
                        <a class="menu__list-link" href="/courses.php">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                 xmlns="http://www.w3.org/2000/svg">
                                <g id="icons">
                                    <path id="Vector" d="M22 10V16M22 10L12 5L2 10L12 15L22 10Z" stroke="white"
                                          stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path id="Vector_2" d="M6 12V17C9 20 15 20 18 17V12" stroke="white"
                                          stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </g>
                            </svg>
                            <span><?= $epNav('nav.courses') ?></span>
                        </a>

                        <div class="popup_menu"><?= $epNav('nav.courses') ?></div>
                    </li>
                    <li class="menu__list-item">
                        <a class="menu__list-link" href="/events.php">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                 xmlns="http://www.w3.org/2000/svg">
                                <g id="icons">
                                    <path id="Vector"
                                          d="M19 4H5C3.89543 4 3 4.89543 3 6V20C3 21.1046 3.89543 22 5 22H19C20.1046 22 21 21.1046 21 20V6C21 4.89543 20.1046 4 19 4Z"
                                          stroke="white" stroke-width="1.5" stroke-linecap="round"
                                          stroke-linejoin="round"/>
                                    <path id="Vector_2" d="M16 2V6" stroke="white" stroke-width="1.5"
                                          stroke-linecap="round" stroke-linejoin="round"/>
                                    <path id="Vector_3" d="M8 2V6" stroke="white" stroke-width="1.5"
                                          stroke-linecap="round" stroke-linejoin="round"/>
                                    <path id="Vector_4" d="M3 10H21" stroke="white" stroke-width="1.5"
                                          stroke-linecap="round" stroke-linejoin="round"/>
                                    <path id="Vector_5" d="M17 14H11" stroke="white" stroke-width="1.5"
                                          stroke-linecap="round" stroke-linejoin="round"/>
                                    <path id="Vector_6" d="M13 18H7" stroke="white" stroke-width="1.5"
                                          stroke-linecap="round" stroke-linejoin="round"/>
                                    <path id="Vector_7" d="M7 14H7.01" stroke="white" stroke-width="1.5"
                                          stroke-linecap="round" stroke-linejoin="round"/>
                                    <path id="Vector_8" d="M17 18H17.01" stroke="white" stroke-width="1.5"
                                          stroke-linecap="round" stroke-linejoin="round"/>
                                </g>
                            </svg>
                            <span><?= $epNav('nav.events') ?></span>
                        </a>

                        <div class="popup_menu"><?= $epNav('nav.events') ?></div>

                    </li>
                    <li class="menu__list-item">
                        <a class="menu__list-link" data-fancybox="" href="#faq-popup">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                 xmlns="http://www.w3.org/2000/svg">
                                <g id="icons">
                                    <path id="Icon"
                                          d="M12 18V18.01M8 10C8 7.79086 9.79086 6 12 6C14.2091 6 16 7.79086 16 10C16 11.8675 14.7202 13.4361 12.9899 13.8766C12.4547 14.0128 12 14.4477 12 15M23 12C23 18.0751 18.0751 23 12 23C5.92487 23 1 18.0751 1 12C1 5.92487 5.92487 1 12 1C18.0751 1 23 5.92487 23 12Z"
                                          stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                                </g>
                            </svg>
                            <span><?= $epNav('nav.faq') ?></span>
                        </a>
                        <div class="popup_menu"><?= $epNav('nav.faq') ?></div>
                    </li>
                    <li class="menu__list-item">
                        <a class="menu__list-link" data-fancybox="" href="#report-popup">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                 xmlns="http://www.w3.org/2000/svg">
                                <g id="icons">
                                    <path id="Icon"
                                          d="M6 13V5C6 3.89543 6.89543 3 8 3H16C17.1046 3 18 3.89543 18 5V13M10 7H14M10 11H14M5 21H19C20.1046 21 21 20.1046 21 19V13.5388C21 12.8151 20.2551 12.331 19.5939 12.625L12.8123 15.639C12.2951 15.8688 11.7049 15.8688 11.1877 15.639L4.40614 12.625C3.74485 12.331 3 12.8151 3 13.5388V19C3 20.1046 3.89543 21 5 21Z"
                                          stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                                </g>
                            </svg>
                            <span><?= $epNav('nav.signup') ?></span>
                        </a>
                        <div class="popup_menu"><?= $epNav('nav.signup') ?></div>

                    </li>
                </ul>
            </nav>

        </aside>
       