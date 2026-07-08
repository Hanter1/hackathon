<?php
/**
 * Логика 24-часового окна Messenger (как в Facebook Messenger).
 * Окно открыто = пользователь писал последним менее N часов назад → менеджер может писать что угодно.
 * Окно закрыто = прошло ≥ N часов или диалог новый → только шаблоны.
 */
if (!isset($pdo)) {
    require_once (defined('DOC_ROOT') ? DOC_ROOT : $_SERVER['DOCUMENT_ROOT']) . '/config/config.php';
    require_once (defined('DOC_ROOT') ? DOC_ROOT : $_SERVER['DOCUMENT_ROOT']) . '/config/db.php';
}
require_once (defined('DOC_ROOT') ? DOC_ROOT : $_SERVER['DOCUMENT_ROOT']) . '/config/messenger.php';

/**
 * Получить или создать запись диалога по PSID.
 *
 * @param string $psid Page-Scoped User ID (Facebook/Instagram)
 * @return array|null запись messenger_conversations или null при ошибке
 */
function messenger_get_or_create_conversation(string $psid): ?array {
    global $pdo;
    $psid = trim($psid);
    if ($psid === '') {
        return null;
    }
    $st = $pdo->prepare("SELECT * FROM messenger_conversations WHERE psid = ? LIMIT 1");
    $st->execute([$psid]);
    $row = $st->fetch();
    if ($row) {
        return $row;
    }
    $pdo->prepare("INSERT INTO messenger_conversations (psid) VALUES (?)")->execute([$psid]);
    $st->execute([$psid]);
    return $st->fetch() ?: null;
}

/**
 * Обновить время последнего сообщения от пользователя (вызывать из webhook при входящем message).
 *
 * @param string $psid
 * @param string|int $timestamp Unix timestamp (сек) или datetime (Y-m-d H:i:s)
 * @return bool
 */
function messenger_update_last_user_message(string $psid, $timestamp): bool {
    global $pdo;
    $psid = trim($psid);
    if ($psid === '') {
        return false;
    }
    if (is_numeric($timestamp)) {
        $dt = date('Y-m-d H:i:s', (int) $timestamp);
    } else {
        $dt = date('Y-m-d H:i:s', strtotime((string) $timestamp));
    }
    $now = date('Y-m-d H:i:s');
    messenger_get_or_create_conversation($psid);
    $st = $pdo->prepare("UPDATE messenger_conversations SET last_user_message_at = ?, updated_at = ? WHERE psid = ?");
    return $st->execute([$dt, $now, $psid]);
}

/**
 * Открыто ли 24-часовое окно для этого диалога?
 * Новый диалог (нет last_user_message_at) = окно закрыто.
 *
 * @param string $psid
 * @return bool true = можно слать любые сообщения, false = только шаблоны
 */
function messenger_is_window_open(string $psid): bool {
    $info = messenger_get_window_info($psid);
    return $info['open'];
}

/**
 * Информация об окне: открыто ли, когда закрывается, последнее сообщение пользователя.
 *
 * @param string $psid
 * @return array { open: bool, last_user_message_at: string|null, closes_at: string|null, window_hours: int }
 */
function messenger_get_window_info(string $psid): array {
    $conv = messenger_get_or_create_conversation($psid);
    $windowHours = defined('MESSENGER_WINDOW_HOURS') ? MESSENGER_WINDOW_HOURS : 24;
    $result = [
        'open' => false,
        'last_user_message_at' => null,
        'closes_at' => null,
        'window_hours' => $windowHours,
    ];
    if (!$conv || empty($conv['last_user_message_at'])) {
        return $result;
    }
    $last = strtotime($conv['last_user_message_at']);
    $closesAt = $last + $windowHours * 3600;
    $result['last_user_message_at'] = $conv['last_user_message_at'];
    $result['closes_at'] = date('Y-m-d H:i:s', $closesAt);
    $result['open'] = time() < $closesAt;
    return $result;
}

/**
 * Можно ли отправить произвольное сообщение (не шаблон)?
 * Если окно закрыто — только шаблоны.
 *
 * @param string $psid
 * @param bool $isTemplate true если отправляется одобренный шаблон
 * @return bool true = отправка разрешена
 */
function messenger_can_send_free_message(string $psid, bool $isTemplate = false): bool {
    if ($isTemplate) {
        return true;
    }
    return messenger_is_window_open($psid);
}
