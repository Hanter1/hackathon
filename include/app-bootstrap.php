<?php
declare(strict_types=1);

/**
 * Единая точка подключения конфигурации и БД для веб-скриптов.
 */
if (!defined('DOC_ROOT')) {
    define('DOC_ROOT', $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__));
}

require_once DOC_ROOT . '/config/config.php';
require_once DOC_ROOT . '/config/db.php';
require_once DOC_ROOT . '/include/security-log.php';
security_log_request_init($pdo);
