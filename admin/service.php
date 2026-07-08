<?php
/**
 * Служебный раздел: смена пароля и обновление схемы БД.
 */
require_once __DIR__ . '/auth.php';
require_full_admin();
$adminNavActive = 'service';

$message = '';
$error = '';
$log = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_schema') {
        $isMysql = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql');
        try {
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
        meta_title VARCHAR(255) NOT NULL DEFAULT '',
        meta_description VARCHAR(500) NOT NULL DEFAULT '',
        updated_at DATETIME NULL DEFAULT NULL,
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
        psid VARCHAR(64) NOT NULL,
        conversation_id VARCHAR(128) DEFAULT NULL,
        last_user_message_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_psid (psid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
            $log[] = 'Таблицы MySQL созданы или уже существуют.';
            try {
                $pdo->exec("UPDATE teachers SET social_link = '', joined_at = '' WHERE social_link <> '' OR joined_at <> ''");
                $log[] = 'Старые поля наставников (social_link, joined_at) очищены.';
            } catch (PDOException $e) {
                $log[] = 'Очистка старых полей наставников пропущена: ' . $e->getMessage();
            }
            foreach (['idx_teachers_status' => 'teachers(status)', 'idx_courses_status' => 'courses(status)', 'idx_events_status' => 'events(status)', 'idx_blog_status' => 'blog_posts(status)'] as $name => $table_col) {
                try {
                    $pdo->exec("CREATE INDEX $name ON $table_col");
                    $log[] = "Индекс $name создан.";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate') !== false) {
                        $log[] = "Индекс $name уже существует.";
                    } else {
                        throw $e;
                    }
                }
            }
        } else {
            $dataDir = DOC_ROOT . '/data';
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0755, true);
                $log[] = 'Создана папка data/.';
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
        meta_title VARCHAR(255) DEFAULT '',
        meta_description VARCHAR(500) DEFAULT '',
        updated_at DATETIME DEFAULT NULL,
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
            $log[] = 'Таблицы SQLite созданы или уже существуют. Индексы созданы.';
            try {
                $pdo->exec("UPDATE teachers SET social_link = '', joined_at = '' WHERE COALESCE(social_link, '') <> '' OR COALESCE(joined_at, '') <> ''");
                $log[] = 'Старые поля наставников (social_link, joined_at) очищены.';
            } catch (PDOException $e) {
                $log[] = 'Очистка старых полей наставников пропущена: ' . $e->getMessage();
            }
        }
        $message = 'Схема БД успешно обновлена.';
    } catch (PDOException $e) {
        $error = 'Ошибка обновления схемы: ' . $e->getMessage();
        $log[] = $e->getMessage();
    }
    } else {
        $current = $_POST['current'] ?? '';
        $new = $_POST['new'] ?? '';
        $confirm = $_POST['confirm'] ?? '';
        if (!$current || !$new || !$confirm) {
            $error = 'Заполните все поля пароля.';
        } elseif ($new !== $confirm) {
            $error = 'Новый пароль и подтверждение не совпадают.';
        } elseif (strlen($new) < 6) {
            $error = 'Пароль должен быть не короче 6 символов.';
        } else {
            $st = $pdo->prepare("SELECT id, password_hash FROM users WHERE id = ? LIMIT 1");
            $st->execute([$_SESSION['admin_id']]);
            $user = $st->fetch();
            if (!$user || !password_verify($current, $user['password_hash'])) {
                $error = 'Неверный текущий пароль.';
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $_SESSION['admin_id']]);
                $message = 'Пароль успешно изменён.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Служебное — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <main class="admin-main">
        <div class="page-title">
            <h1>Служебное</h1>
        </div>
        <?php if ($message): ?><p class="admin-flash admin-flash--ok"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="admin-flash admin-flash--err"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <?php if (!empty($log)): ?>
            <pre class="admin-code-block"><?= htmlspecialchars(implode("\n", $log)) ?></pre>
        <?php endif; ?>

        <section class="admin-fieldset admin-stack-top">
            <h2>Сменить пароль</h2>
            <form method="post" class="admin-form-narrow">
                <div class="form-group">
                    <label>Текущий пароль</label>
                    <input type="password" name="current" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label>Новый пароль</label>
                    <input type="password" name="new" required minlength="6" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label>Подтвердите новый пароль</label>
                    <input type="password" name="confirm" required minlength="6" autocomplete="new-password">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Сохранить пароль</button>
                </div>
            </form>
        </section>

        <section class="admin-fieldset admin-stack-top">
            <h2>Обновление схемы БД</h2>
            <p class="admin-muted">Создаёт отсутствующие таблицы и индексы (CREATE TABLE IF NOT EXISTS). Безопасно вызывать после обновления кода. Дополнительные изменения можно добавлять файлами в папке <code>database/migrations/</code> (подключаются автоматически при загрузке БД).</p>
            <form method="post" onsubmit="return confirm('Выполнить обновление схемы БД?');">
                <input type="hidden" name="action" value="update_schema">
                <button type="submit" class="btn btn-primary">Обновить схему БД</button>
            </form>
        </section>

        <section class="admin-fieldset admin-stack-top">
            <h2>Наблюдаемость и SEO (публичные URL)</h2>
            <ul class="admin-muted" style="margin:0.35rem 0 0; padding-left:1.2rem; line-height:1.55;">
                <li><strong>Проверка сервиса:</strong> <a href="/health.php" target="_blank" rel="noopener">/health.php</a> (JSON, статус БД)</li>
                <li><strong>Sitemap:</strong> <a href="/sitemap.php" target="_blank" rel="noopener">/sitemap.php</a></li>
                <li><strong>robots:</strong> <a href="/robots.php" target="_blank" rel="noopener">/robots.php</a> — при желании настройте редирект с <code>/robots.txt</code></li>
                <li><strong>Журнал ошибок PHP:</strong> <code>data/logs/app.log</code> (создаётся при первой ошибке)</li>
            </ul>
        </section>
    </main>
    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
