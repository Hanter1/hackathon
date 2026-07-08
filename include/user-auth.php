<?php

function ua_is_mysql(PDO $pdo): bool
{
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
}

function ua_users_columns(PDO $pdo): array
{
    if (ua_is_mysql($pdo)) {
        $rows = $pdo->query('SHOW COLUMNS FROM users')->fetchAll();
        return array_map(static fn($r) => (string) $r['Field'], $rows);
    }

    $rows = $pdo->query("PRAGMA table_info('users')")->fetchAll();
    return array_map(static fn($r) => (string) $r['name'], $rows);
}

function ua_ensure_users_schema(PDO $pdo): void
{
    $columns = ua_users_columns($pdo);
    if (!in_array('name', $columns, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN name VARCHAR(128) NULL");
    }
    if (!in_array('email', $columns, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL");
    }
    if (!in_array('role', $columns, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(32) NOT NULL DEFAULT 'user'");
    }
    if (!in_array('avatar', $columns, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL");
    }
    $columns = ua_users_columns($pdo);
    if (!in_array('notify_courses', $columns, true)) {
        if (ua_is_mysql($pdo)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN notify_courses TINYINT(1) NOT NULL DEFAULT 1');
        } else {
            $pdo->exec('ALTER TABLE users ADD COLUMN notify_courses INTEGER NOT NULL DEFAULT 1');
        }
    }
    $columns = ua_users_columns($pdo);
    if (!in_array('notify_news', $columns, true)) {
        if (ua_is_mysql($pdo)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN notify_news TINYINT(1) NOT NULL DEFAULT 1');
        } else {
            $pdo->exec('ALTER TABLE users ADD COLUMN notify_news INTEGER NOT NULL DEFAULT 1');
        }
    }
    $columns = ua_users_columns($pdo);
    if (!in_array('notify_marketing', $columns, true)) {
        if (ua_is_mysql($pdo)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN notify_marketing TINYINT(1) NOT NULL DEFAULT 0');
        } else {
            $pdo->exec('ALTER TABLE users ADD COLUMN notify_marketing INTEGER NOT NULL DEFAULT 0');
        }
    }
    $columns = ua_users_columns($pdo);
    if (!in_array('last_login_at', $columns, true)) {
        if (ua_is_mysql($pdo)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL');
        } else {
            $pdo->exec('ALTER TABLE users ADD COLUMN last_login_at TEXT NULL');
        }
    }
}

function ua_touch_last_login(PDO $pdo, int $userId): void
{
    if ($userId <= 0) {
        return;
    }
    if (ua_is_mysql($pdo)) {
        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$userId]);
    } else {
        $pdo->prepare("UPDATE users SET last_login_at = datetime('now') WHERE id = ?")->execute([$userId]);
    }
}

function ua_is_admin_user(array $user): bool
{
    $role = (string) ($user['role'] ?? '');
    $login = (string) ($user['login'] ?? '');
    return $role === 'admin' || strtolower($login) === 'admin';
}

/** Доступ в админку: полные администраторы и роли editor / moderator. */
function ua_can_access_admin(array $user): bool
{
    if (ua_is_admin_user($user)) {
        return true;
    }
    $role = strtolower(trim((string) ($user['role'] ?? '')));

    return in_array($role, ['editor', 'moderator'], true);
}

function ua_is_moderator_user(array $user): bool
{
    return strtolower(trim((string) ($user['role'] ?? ''))) === 'moderator';
}

function ua_is_editor_user(array $user): bool
{
    return strtolower(trim((string) ($user['role'] ?? ''))) === 'editor';
}

/** Главная, наставники, курсы, события, блог — не для модераторов. */
function ua_can_edit_site_content(array $user): bool
{
    return ua_is_admin_user($user) || ua_is_editor_user($user);
}

/** Медиатека и журнал — только админ и редактор. */
function ua_can_use_editor_tools(array $user): bool
{
    return ua_is_admin_user($user) || ua_is_editor_user($user);
}

function ua_store_session_user(array $user): void
{
    $_SESSION['user'] = [
        'id' => (int) ($user['id'] ?? 0),
        'login' => (string) ($user['login'] ?? ''),
        'name' => (string) ($user['name'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'role' => (string) ($user['role'] ?? 'user'),
        'avatar' => (string) ($user['avatar'] ?? ''),
        'notify_courses' => (int) ($user['notify_courses'] ?? 1) ? 1 : 0,
        'notify_news' => (int) ($user['notify_news'] ?? 1) ? 1 : 0,
        'notify_marketing' => (int) ($user['notify_marketing'] ?? 0) ? 1 : 0,
    ];

    if (ua_is_admin_user($user)) {
        $_SESSION['admin_id'] = (int) ($user['id'] ?? 0);
        $_SESSION['admin_login'] = (string) ($user['login'] ?? '');
    } else {
        unset($_SESSION['admin_id'], $_SESSION['admin_login']);
    }
}

function ua_find_user_by_identifier(PDO $pdo, string $identifier): ?array
{
    $st = $pdo->prepare('SELECT * FROM users WHERE login = ? OR email = ? LIMIT 1');
    $st->execute([$identifier, $identifier]);
    $row = $st->fetch();
    return $row ?: null;
}

function ua_create_user(PDO $pdo, string $name, string $email, string $password): array
{
    $base = preg_replace('/[^a-z0-9_]/i', '', strstr($email, '@', true) ?: $email);
    $base = strtolower($base ?: 'user');
    $login = $base;
    $i = 1;
    while (ua_find_user_by_identifier($pdo, $login)) {
        $login = $base . $i;
        $i++;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $st = $pdo->prepare("INSERT INTO users (login, password_hash, name, email, role) VALUES (?, ?, ?, ?, 'user')");
    $st->execute([$login, $hash, $name, $email]);

    $id = (int) $pdo->lastInsertId();
    $st2 = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $st2->execute([$id]);
    return (array) $st2->fetch();
}

