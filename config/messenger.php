<?php
/**
 * Настройки окна сообщений Messenger (24h по аналогии с Facebook Messenger).
 * Окно открыто = можно слать любые сообщения; закрыто = только шаблоны.
 */
if (!defined('DOC_ROOT')) {
    define('DOC_ROOT', $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__));
}

if (!function_exists('env')) {
    require_once __DIR__ . '/env.php';
    load_dotenv(dirname(__DIR__));
}

define('MESSENGER_WINDOW_HOURS', (int) env('MESSENGER_WINDOW_HOURS', 24));
define('MESSENGER_VERIFY_TOKEN', (string) env('MESSENGER_VERIFY_TOKEN', ''));
define('MESSENGER_PAGE_ACCESS_TOKEN', (string) env('MESSENGER_PAGE_ACCESS_TOKEN', ''));
