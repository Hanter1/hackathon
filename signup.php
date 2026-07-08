<?php
/**
 * Обработчик формы «Записаться»: принимает POST, сохраняет в signup_requests, возвращает JSON.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/include/data.php';

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

$errors = [];
if ($name === '') {
    $errors[] = 'Укажите имя';
}
if ($email === '') {
    $errors[] = 'Укажите email';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Некорректный email';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

if (save_signup_request($name, $email, $message)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'errors' => ['Ошибка сохранения. Попробуйте позже.']]);
}
