<?php
/**
 * Редирект: заявки выведены в разделе «Форма «Записаться»».
 */
require_once __DIR__ . '/auth.php';
require_login();
header('Location: signup-settings.php');
exit;
