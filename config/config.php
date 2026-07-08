<?php
/**
 * Общая конфигурация сайта
 */
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}
if (!defined('DOC_ROOT')) {
    define('DOC_ROOT', $_SERVER['DOCUMENT_ROOT'] ?? PROJECT_ROOT);
}

require_once __DIR__ . '/env.php';
load_dotenv(PROJECT_ROOT);

define('SITE_URL', rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/') ?: '/');
date_default_timezone_set('Europe/Minsk');
