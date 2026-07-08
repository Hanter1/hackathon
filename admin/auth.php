<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
session_start();
require_once __DIR__ . '/_assets.php';
if (!defined('DOC_ROOT')) {
    define('DOC_ROOT', $_SERVER['DOCUMENT_ROOT'] ?? dirname(dirname(__DIR__)));
}
require_once DOC_ROOT . '/include/app-bootstrap.php';
require_once DOC_ROOT . '/include/user-auth.php';
require_once DOC_ROOT . '/include/cms-helpers.php';

function require_login(): void
{
    $sessionUser = $_SESSION['user'] ?? null;
    $ok = is_array($sessionUser) && !empty($sessionUser['id']) && ua_can_access_admin($sessionUser);
    if (!$ok) {
        header('Location: login.php');
        exit;
    }
}

/** Только полный администратор (схема БД, пользователи CMS). */
function require_full_admin(): void
{
    require_login();
    $sessionUser = $_SESSION['user'] ?? null;
    if (!is_array($sessionUser) || !ua_is_admin_user($sessionUser)) {
        header('Location: index.php?denied=1');
        exit;
    }
}

/** Контент сайта: admin и editor (не moderator). */
function require_site_content_editor(): void
{
    require_login();
    $sessionUser = $_SESSION['user'] ?? null;
    if (!is_array($sessionUser) || !ua_can_edit_site_content($sessionUser)) {
        header('Location: index.php?denied=content');
        exit;
    }
}

/** Медиатека и журнал: admin и editor. */
function require_editor_tools(): void
{
    require_login();
    $sessionUser = $_SESSION['user'] ?? null;
    if (!is_array($sessionUser) || !ua_can_use_editor_tools($sessionUser)) {
        header('Location: index.php?denied=tools');
        exit;
    }
}

function admin_logout(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_login'], $_SESSION['user']);
}
