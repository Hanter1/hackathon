<?php
/**
 * Настройки окна сообщений Messenger (24h по аналогии с Facebook Messenger).
 * Окно открыто = можно слать любые сообщения; закрыто = только шаблоны.
 */
if (!defined('DOC_ROOT')) {
    define('DOC_ROOT', $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__));
}

// Часы, в течение которых после последнего сообщения пользователя окно считается открытым
define('MESSENGER_WINDOW_HOURS', (int) (getenv('MESSENGER_WINDOW_HOURS') ?: 24));

// Для webhook Facebook: verify token (укажите свой в настройках приложения Meta)
define('MESSENGER_VERIFY_TOKEN', getenv('MESSENGER_VERIFY_TOKEN') ?: '');

// Page Access Token для отправки сообщений и опционально для Graph API (conversation sync)
define('MESSENGER_PAGE_ACCESS_TOKEN', getenv('MESSENGER_PAGE_ACCESS_TOKEN') ?: '');
