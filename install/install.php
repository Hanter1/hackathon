<?php
/**
 * Однократная установка: создание таблиц и администратора.
 * После установки удалите папку install/ или переименуйте install.php.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('DOC_ROOT', $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__));
require_once DOC_ROOT . '/config/config.php';
require_once DOC_ROOT . '/config/db.php';

$isMysql = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql');

if ($isMysql) {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        login VARCHAR(64) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS teachers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        surname VARCHAR(100) NOT NULL,
        slug VARCHAR(120) NOT NULL UNIQUE,
        role VARCHAR(100) DEFAULT '',
        bio TEXT,
        image VARCHAR(255) DEFAULT '',
        social_link VARCHAR(255) DEFAULT '',
        joined_at VARCHAR(50) DEFAULT '',
        sort_order INT DEFAULT 0,
        status VARCHAR(20) DEFAULT 'active',
        meta_title VARCHAR(255) NOT NULL DEFAULT '',
        meta_description VARCHAR(500) NOT NULL DEFAULT '',
        updated_at DATETIME NULL DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(120) NOT NULL UNIQUE,
        category VARCHAR(100) DEFAULT '',
        description TEXT,
        image VARCHAR(255) DEFAULT '',
        price VARCHAR(50) DEFAULT '',
        lessons_count INT DEFAULT 0,
        students_count INT DEFAULT 0,
        level_label VARCHAR(50) NOT NULL DEFAULT '',
        duration_label VARCHAR(50) NOT NULL DEFAULT '',
        language_label VARCHAR(50) NOT NULL DEFAULT '',
        certificate_label VARCHAR(20) NOT NULL DEFAULT '',
        certificate_enabled TINYINT(1) NULL DEFAULT NULL,
        quizzes_count INT NOT NULL DEFAULT 0,
        rating VARCHAR(10) DEFAULT '4.5',
        teacher_id INT DEFAULT NULL,
        status VARCHAR(20) DEFAULT 'active',
        sort_order INT DEFAULT 0,
        meta_title VARCHAR(255) NOT NULL DEFAULT '',
        meta_description VARCHAR(500) NOT NULL DEFAULT '',
        updated_at DATETIME NULL DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(120) NOT NULL UNIQUE,
        description TEXT,
        image VARCHAR(255) DEFAULT '',
        event_date VARCHAR(50) DEFAULT '',
        location VARCHAR(255) DEFAULT '',
        status VARCHAR(20) DEFAULT 'active',
        sort_order INT DEFAULT 0,
        meta_title VARCHAR(255) NOT NULL DEFAULT '',
        meta_description VARCHAR(500) NOT NULL DEFAULT '',
        updated_at DATETIME NULL DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS blog_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(120) NOT NULL UNIQUE,
        excerpt TEXT,
        content TEXT,
        image VARCHAR(255) DEFAULT '',
        author_name VARCHAR(100) DEFAULT '',
        status VARCHAR(20) DEFAULT 'published',
        published_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS home_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(64) NOT NULL UNIQUE,
        setting_value TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS signup_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        message TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    CREATE TABLE IF NOT EXISTS messenger_conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        psid VARCHAR(64) NOT NULL COMMENT 'Page-Scoped User ID (Facebook/Instagram)',
        conversation_id VARCHAR(128) DEFAULT NULL COMMENT 'FB Graph API conversation ID for sync',
        last_user_message_at DATETIME DEFAULT NULL COMMENT 'Last message FROM user — 24h window counts from this',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_psid (psid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    foreach (['idx_teachers_status' => 'teachers(status)', 'idx_courses_status' => 'courses(status)', 'idx_events_status' => 'events(status)', 'idx_blog_status' => 'blog_posts(status)'] as $name => $table_col) {
        try {
            $pdo->exec("CREATE INDEX $name ON $table_col");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false) throw $e;
        }
    }
} else {
    $dataDir = DOC_ROOT . '/data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        login VARCHAR(64) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS teachers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100) NOT NULL,
        surname VARCHAR(100) NOT NULL,
        slug VARCHAR(120) NOT NULL UNIQUE,
        role VARCHAR(100) DEFAULT '',
        bio TEXT,
        image VARCHAR(255) DEFAULT '',
        social_link VARCHAR(255) DEFAULT '',
        joined_at VARCHAR(50) DEFAULT '',
        sort_order INTEGER DEFAULT 0,
        status VARCHAR(20) DEFAULT 'active',
        meta_title VARCHAR(255) DEFAULT '',
        meta_description VARCHAR(500) DEFAULT '',
        updated_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS courses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(120) NOT NULL UNIQUE,
        category VARCHAR(100) DEFAULT '',
        description TEXT,
        image VARCHAR(255) DEFAULT '',
        price VARCHAR(50) DEFAULT '',
        lessons_count INT DEFAULT 0,
        students_count INT DEFAULT 0,
        level_label VARCHAR(50) DEFAULT '',
        duration_label VARCHAR(50) DEFAULT '',
        language_label VARCHAR(50) DEFAULT '',
        certificate_label VARCHAR(20) DEFAULT '',
        certificate_enabled INTEGER DEFAULT NULL,
        quizzes_count INTEGER NOT NULL DEFAULT 0,
        rating VARCHAR(10) DEFAULT '4.5',
        teacher_id INT DEFAULT NULL,
        status VARCHAR(20) DEFAULT 'active',
        sort_order INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(120) NOT NULL UNIQUE,
        description TEXT,
        image VARCHAR(255) DEFAULT '',
        event_date VARCHAR(50) DEFAULT '',
        location VARCHAR(255) DEFAULT '',
        status VARCHAR(20) DEFAULT 'active',
        sort_order INTEGER DEFAULT 0,
        meta_title VARCHAR(255) DEFAULT '',
        meta_description VARCHAR(500) DEFAULT '',
        updated_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS blog_posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(120) NOT NULL UNIQUE,
        excerpt TEXT,
        content TEXT,
        image VARCHAR(255) DEFAULT '',
        author_name VARCHAR(100) DEFAULT '',
        status VARCHAR(20) DEFAULT 'published',
        published_at DATETIME DEFAULT NULL,
        meta_title VARCHAR(255) DEFAULT '',
        meta_description VARCHAR(500) DEFAULT '',
        updated_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS home_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        setting_key VARCHAR(64) NOT NULL UNIQUE,
        setting_value TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL
    );

    CREATE TABLE IF NOT EXISTS signup_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        message TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS messenger_conversations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        psid VARCHAR(64) NOT NULL UNIQUE,
        conversation_id VARCHAR(128) DEFAULT NULL,
        last_user_message_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL
    );

    CREATE INDEX IF NOT EXISTS idx_home_settings_key ON home_settings(setting_key);

    CREATE INDEX IF NOT EXISTS idx_teachers_status ON teachers(status);
    CREATE INDEX IF NOT EXISTS idx_courses_status ON courses(status);
    CREATE INDEX IF NOT EXISTS idx_events_status ON events(status);
    CREATE INDEX IF NOT EXISTS idx_blog_status ON blog_posts(status);
    ");
}

$hash = password_hash('admin123', PASSWORD_DEFAULT);
if ($isMysql) {
    $st = $pdo->prepare("INSERT IGNORE INTO users (login, password_hash) VALUES ('admin', ?)");
    $st->execute([$hash]);
} else {
    $st = $pdo->prepare("INSERT OR IGNORE INTO users (login, password_hash) VALUES (?, ?)");
    $st->execute(['admin', $hash]);
}

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Установка</title></head><body>';
echo '<h1>Установка завершена</h1>';
echo '<p>Таблицы созданы. Логин: <strong>admin</strong>, пароль: <strong>admin123</strong></p>';
echo '<p><a href="../admin/">Войти в админку</a></p>';
echo '<p style="color:red;">Удалите папку install/ или переименуйте install.php после первого входа.</p>';
echo '</body></html>';
