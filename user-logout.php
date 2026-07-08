<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['user'], $_SESSION['client_user'], $_SESSION['admin_id'], $_SESSION['admin_login']);

header('Location: /');
exit;
