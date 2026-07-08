<?php
/**
 * Общая конфигурация сайта
 */
if (!defined('DOC_ROOT')) {
    define('DOC_ROOT', $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__));
}
define('SITE_URL', rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/') ?: '/');
date_default_timezone_set('Europe/Minsk');
