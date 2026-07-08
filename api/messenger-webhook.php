<?php
/**
 * Webhook для Facebook Messenger.
 * 1) GET: верификация (hub.verify_token, hub.challenge).
 * 2) POST: входящие события — при message от пользователя обновляем last_user_message_at.
 *
 * В настройках приложения Meta укажите:
 * - Callback URL: https://ваш-домен/api/messenger-webhook.php
 * - Verify Token: тот же, что MESSENGER_VERIFY_TOKEN в .env или config
 * - Подписаться на: messages, messaging_postbacks (по желанию)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/messenger.php';
require_once dirname(__DIR__) . '/include/messenger-window.php';

// ----- GET: верификация -----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';
    if ($mode === 'subscribe' && $token !== '' && $token === MESSENGER_VERIFY_TOKEN) {
        header('Content-Type: text/plain');
        echo $challenge;
        exit;
    }
    http_response_code(403);
    exit;
}

// ----- POST: входящие события -----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input) || !isset($input['object']) || $input['object'] !== 'page') {
    http_response_code(200);
    exit;
}

foreach ($input['entry'] ?? [] as $entry) {
    foreach ($entry['messaging'] ?? [] as $event) {
        $senderId = $event['sender']['id'] ?? null;
        if (!$senderId) {
            continue;
        }
        $psid = (string) $senderId;

        // Входящее сообщение от пользователя — сбрасываем таймер 24h
        if (isset($event['message'])) {
            $timestamp = $event['timestamp'] ?? time();
            // timestamp в webhook приходит в миллисекундах
            $ts = is_numeric($timestamp) && $timestamp > 1e12 ? (int) ($timestamp / 1000) : (int) $timestamp;
            messenger_update_last_user_message($psid, $ts);
        }

        // Ответ на быстрый ответ / postback тоже считается действием пользователя — окно открывается
        if (isset($event['postback'])) {
            messenger_update_last_user_message($psid, time());
        }
    }
}

http_response_code(200);
echo 'OK';
