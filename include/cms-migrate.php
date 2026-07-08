<?php
declare(strict_types=1);

/**
 * Версионируемые миграции: файлы database/migrations/*.php.
 * Каждый файл возвращает:
 *   — пустую строку (только отметка «применено»),
 *   — одну SQL-строку,
 *   — или ['mysql' => '...', 'sqlite' => '...'].
 */

function cms_ensure_migrations_table(PDO $pdo): void
{
    if (cms_is_mysql($pdo)) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cms_applied_migrations (
                name VARCHAR(190) NOT NULL PRIMARY KEY,
                applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } else {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cms_applied_migrations (
                name VARCHAR(190) NOT NULL PRIMARY KEY,
                applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }
}

function cms_migration_already_applied(PDO $pdo, string $name): bool
{
    $st = $pdo->prepare('SELECT 1 FROM cms_applied_migrations WHERE name = ? LIMIT 1');
    $st->execute([$name]);

    return (bool) $st->fetchColumn();
}

function cms_mark_migration_applied(PDO $pdo, string $name): void
{
    if (cms_is_mysql($pdo)) {
        $st = $pdo->prepare('INSERT IGNORE INTO cms_applied_migrations (name) VALUES (?)');
        $st->execute([$name]);
    } else {
        $st = $pdo->prepare('INSERT OR IGNORE INTO cms_applied_migrations (name) VALUES (?)');
        $st->execute([$name]);
    }
}

/**
 * @param mixed $payload
 */
function cms_migration_resolve_sql(PDO $pdo, $payload): string
{
    if (is_string($payload)) {
        return trim($payload);
    }
    if (!is_array($payload)) {
        return '';
    }
    if (cms_is_mysql($pdo)) {
        return trim((string) ($payload['mysql'] ?? $payload['default'] ?? ''));
    }

    return trim((string) ($payload['sqlite'] ?? $payload['default'] ?? ''));
}

function cms_run_pending_migrations(PDO $pdo): void
{
    if (!defined('DOC_ROOT')) {
        return;
    }
    cms_ensure_migrations_table($pdo);
    $dir = DOC_ROOT . '/database/migrations';
    if (!is_dir($dir)) {
        return;
    }
    $files = glob($dir . '/*.php') ?: [];
    natsort($files);
    foreach ($files as $file) {
        $name = basename($file, '.php');
        if ($name === '' || (($name[0] ?? '') === '.')) {
            continue;
        }
        if (cms_migration_already_applied($pdo, $name)) {
            continue;
        }
        /** @var mixed $raw */
        $raw = require $file;
        $sql = cms_migration_resolve_sql($pdo, $raw);
        if ($sql !== '') {
            $pdo->exec($sql);
        }
        cms_mark_migration_applied($pdo, $name);
    }
}
