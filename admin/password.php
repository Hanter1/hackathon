<?php
/**
 * Редирект: пароль объединён со служебным разделом.
 */
require_once __DIR__ . '/auth.php';
require_login();
header('Location: service.php');
exit;
