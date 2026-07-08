<?php
declare(strict_types=1);

/**
 * Логирование HTTP-запросов сайта/админки для раздела "Безопасность".
 */

function security_client_ip(): string
{
    $xff = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($xff !== '') {
        $parts = explode(',', $xff);
        $ip = trim((string) ($parts[0] ?? ''));
        if ($ip !== '') {
            return mb_substr($ip, 0, 64);
        }
    }

    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($ip === '') {
        $ip = 'unknown';
    }

    return mb_substr($ip, 0, 64);
}

function security_visitor_id(): string
{
    $cookieName = 'ep_vid';
    $current = trim((string) ($_COOKIE[$cookieName] ?? ''));
    if ($current !== '' && preg_match('/^[a-zA-Z0-9_-]{16,64}$/', $current)) {
        return $current;
    }

    try {
        $newId = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $newId = sha1((string) microtime(true) . (string) mt_rand());
    }
    $newId = substr($newId, 0, 64);

    if (!headers_sent()) {
        setcookie($cookieName, $newId, [
            'expires' => time() + (3600 * 24 * 365),
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    $_COOKIE[$cookieName] = $newId;

    return $newId;
}

function security_log_request_init(PDO $pdo): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (!isset($_SERVER['REQUEST_METHOD'])) {
        return;
    }
    static $started = false;
    if ($started) {
        return;
    }
    $started = true;

    $requestId = '';
    try {
        $requestId = bin2hex(random_bytes(12));
    } catch (Throwable $e) {
        $requestId = sha1((string) microtime(true) . ':' . (string) mt_rand());
    }
    $requestId = substr($requestId, 0, 64);

    $startedAt = microtime(true);
    $visitorId = security_visitor_id();
    $method = mb_substr((string) ($_SERVER['REQUEST_METHOD'] ?? ''), 0, 12);
    $host = mb_substr((string) ($_SERVER['HTTP_HOST'] ?? ''), 0, 255);
    $uri = mb_substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 500);
    $query = mb_substr((string) ($_SERVER['QUERY_STRING'] ?? ''), 0, 2000);
    $ip = security_client_ip();
    $xff = mb_substr((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''), 0, 255);
    $ua = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 700);
    $referer = mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 700);
    $isAdmin = (strpos($uri, '/admin/') === 0 || strpos($uri, 'admin/') === 0) ? 1 : 0;

    register_shutdown_function(static function () use (
        $pdo,
        $startedAt,
        $requestId,
        $visitorId,
        $method,
        $host,
        $uri,
        $query,
        $ip,
        $xff,
        $ua,
        $referer,
        $isAdmin
    ): void {
        try {
            $status = (int) http_response_code();
            $responseMs = (int) round((microtime(true) - $startedAt) * 1000);
            $contentType = mb_substr((string) ini_get('default_mimetype'), 0, 120);

            $user = $_SESSION['user'] ?? null;
            $userId = null;
            $userLogin = '';
            $userRole = '';
            if (is_array($user)) {
                $uid = (int) ($user['id'] ?? 0);
                $userId = $uid > 0 ? $uid : null;
                $userLogin = mb_substr((string) ($user['login'] ?? $user['email'] ?? ''), 0, 120);
                $userRole = mb_substr((string) ($user['role'] ?? ''), 0, 40);
            }

            $st = $pdo->prepare(
                'INSERT INTO security_access_log
                (request_id, visitor_id, user_id, user_login, user_role, is_admin_area, method, host, uri, query_string, ip, forwarded_for, user_agent, referer, status_code, response_ms, content_type)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $st->execute([
                $requestId,
                $visitorId,
                $userId,
                $userLogin,
                $userRole,
                $isAdmin,
                $method,
                $host,
                $uri,
                $query,
                $ip,
                $xff,
                $ua,
                $referer,
                $status,
                $responseMs,
                $contentType,
            ]);
        } catch (Throwable $e) {
            // fail-safe: never break request on logging
        }
    });
}

