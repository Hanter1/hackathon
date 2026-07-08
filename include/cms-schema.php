<?php

function cms_is_mysql(PDO $pdo): bool
{
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
}

/**
 * Таблицы для журналирования и медиатеки CMS (идемпотентно).
 */
function cms_ensure_schema(PDO $pdo): void
{
    if (cms_is_mysql($pdo)) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_activity_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                login VARCHAR(64) NOT NULL DEFAULT '',
                action VARCHAR(80) NOT NULL,
                entity_type VARCHAR(48) NOT NULL DEFAULT '',
                entity_id INT NULL,
                meta TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY idx_activity_created (created_at),
                KEY idx_activity_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cms_media (
                id INT AUTO_INCREMENT PRIMARY KEY,
                path VARCHAR(500) NOT NULL,
                original_name VARCHAR(255) NOT NULL DEFAULT '',
                mime VARCHAR(128) NOT NULL DEFAULT '',
                size_bytes INT NOT NULL DEFAULT 0,
                uploaded_by INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_media_path (path(191)),
                KEY idx_media_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS security_access_log (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                request_id VARCHAR(64) NOT NULL DEFAULT '',
                visitor_id VARCHAR(64) NOT NULL DEFAULT '',
                user_id INT NULL,
                user_login VARCHAR(120) NOT NULL DEFAULT '',
                user_role VARCHAR(40) NOT NULL DEFAULT '',
                is_admin_area TINYINT(1) NOT NULL DEFAULT 0,
                method VARCHAR(12) NOT NULL DEFAULT '',
                host VARCHAR(255) NOT NULL DEFAULT '',
                uri VARCHAR(500) NOT NULL DEFAULT '',
                query_string TEXT NULL,
                ip VARCHAR(64) NOT NULL DEFAULT '',
                forwarded_for VARCHAR(255) NOT NULL DEFAULT '',
                user_agent VARCHAR(700) NOT NULL DEFAULT '',
                referer VARCHAR(700) NOT NULL DEFAULT '',
                status_code INT NOT NULL DEFAULT 0,
                response_ms INT NOT NULL DEFAULT 0,
                content_type VARCHAR(120) NOT NULL DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY idx_sec_created (created_at),
                KEY idx_sec_ip (ip),
                KEY idx_sec_user (user_id),
                KEY idx_sec_admin (is_admin_area),
                KEY idx_sec_uri (uri(191))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS course_overview_blocks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                course_id INT NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                block_type VARCHAR(16) NOT NULL DEFAULT 'text',
                title VARCHAR(255) NOT NULL DEFAULT '',
                body TEXT NULL,
                image VARCHAR(700) NOT NULL DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY idx_cob_course (course_id, sort_order),
                KEY idx_cob_type (block_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS course_overview_list_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                block_id INT NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                text VARCHAR(700) NOT NULL DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY idx_coli_block (block_id, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS course_curriculum_modules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                course_id INT NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                title VARCHAR(255) NOT NULL DEFAULT '',
                is_open TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY idx_ccm_course (course_id, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS course_curriculum_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                module_id INT NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                title VARCHAR(255) NOT NULL DEFAULT '',
                progress_percent INT NOT NULL DEFAULT 0,
                duration_label VARCHAR(32) NOT NULL DEFAULT '',
                state VARCHAR(16) NOT NULL DEFAULT 'active',
                action VARCHAR(16) NOT NULL DEFAULT 'play',
                action_url VARCHAR(700) NOT NULL DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY idx_cci_module (module_id, sort_order),
                KEY idx_cci_state (state)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS course_reviews (
                id INT AUTO_INCREMENT PRIMARY KEY,
                course_id INT NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                author_name VARCHAR(255) NOT NULL DEFAULT '',
                author_avatar VARCHAR(700) NOT NULL DEFAULT '',
                rating DECIMAL(2,1) NOT NULL DEFAULT 5.0,
                title VARCHAR(255) NOT NULL DEFAULT '',
                body TEXT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'published',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY idx_cr_course (course_id, status, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS course_comments (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                course_id INT NOT NULL,
                author_name VARCHAR(255) NOT NULL DEFAULT '',
                author_email VARCHAR(255) NOT NULL DEFAULT '',
                body TEXT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY idx_cc_course (course_id, status, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS event_content_blocks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                block_type VARCHAR(16) NOT NULL DEFAULT 'about',
                title VARCHAR(255) NOT NULL DEFAULT '',
                body TEXT NULL,
                images TEXT NULL,
                map_embed_url TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY idx_ecb_event (event_id, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } else {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_activity_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                login VARCHAR(64) NOT NULL DEFAULT '',
                action VARCHAR(80) NOT NULL,
                entity_type VARCHAR(48) NOT NULL DEFAULT '',
                entity_id INTEGER NULL,
                meta TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cms_media (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                path VARCHAR(500) NOT NULL UNIQUE,
                original_name VARCHAR(255) NOT NULL DEFAULT '',
                mime VARCHAR(128) NOT NULL DEFAULT '',
                size_bytes INTEGER NOT NULL DEFAULT 0,
                uploaded_by INTEGER NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS security_access_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                request_id VARCHAR(64) NOT NULL DEFAULT '',
                visitor_id VARCHAR(64) NOT NULL DEFAULT '',
                user_id INTEGER NULL,
                user_login VARCHAR(120) NOT NULL DEFAULT '',
                user_role VARCHAR(40) NOT NULL DEFAULT '',
                is_admin_area INTEGER NOT NULL DEFAULT 0,
                method VARCHAR(12) NOT NULL DEFAULT '',
                host VARCHAR(255) NOT NULL DEFAULT '',
                uri VARCHAR(500) NOT NULL DEFAULT '',
                query_string TEXT NULL,
                ip VARCHAR(64) NOT NULL DEFAULT '',
                forwarded_for VARCHAR(255) NOT NULL DEFAULT '',
                user_agent VARCHAR(700) NOT NULL DEFAULT '',
                referer VARCHAR(700) NOT NULL DEFAULT '',
                status_code INTEGER NOT NULL DEFAULT 0,
                response_ms INTEGER NOT NULL DEFAULT 0,
                content_type VARCHAR(120) NOT NULL DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS course_overview_blocks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                course_id INTEGER NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                block_type VARCHAR(16) NOT NULL DEFAULT 'text',
                title VARCHAR(255) NOT NULL DEFAULT '',
                body TEXT NULL,
                image VARCHAR(700) NOT NULL DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS course_overview_list_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                block_id INTEGER NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                text VARCHAR(700) NOT NULL DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS course_curriculum_modules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                course_id INTEGER NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                title VARCHAR(255) NOT NULL DEFAULT '',
                is_open INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS course_curriculum_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                module_id INTEGER NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                title VARCHAR(255) NOT NULL DEFAULT '',
                progress_percent INTEGER NOT NULL DEFAULT 0,
                duration_label VARCHAR(32) NOT NULL DEFAULT '',
                state VARCHAR(16) NOT NULL DEFAULT 'active',
                action VARCHAR(16) NOT NULL DEFAULT 'play',
                action_url VARCHAR(700) NOT NULL DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS course_reviews (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                course_id INTEGER NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                author_name VARCHAR(255) NOT NULL DEFAULT '',
                author_avatar VARCHAR(700) NOT NULL DEFAULT '',
                rating DECIMAL(2,1) NOT NULL DEFAULT 5.0,
                title VARCHAR(255) NOT NULL DEFAULT '',
                body TEXT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'published',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS course_comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                course_id INTEGER NOT NULL,
                author_name VARCHAR(255) NOT NULL DEFAULT '',
                author_email VARCHAR(255) NOT NULL DEFAULT '',
                body TEXT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS event_content_blocks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                block_type VARCHAR(16) NOT NULL DEFAULT 'about',
                title VARCHAR(255) NOT NULL DEFAULT '',
                body TEXT NULL,
                images TEXT NULL,
                map_embed_url TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    cms_ensure_content_seo_columns($pdo);
    cms_ensure_course_sidebar_columns($pdo);
    cms_ensure_event_detail_columns($pdo);
    cms_ensure_i18n_tables($pdo);

    require_once __DIR__ . '/cms-migrate.php';
    cms_run_pending_migrations($pdo);
}

/**
 * Таблицы мультиязычности (идемпотентно).
 */
function cms_ensure_i18n_tables(PDO $pdo): void
{
    if (cms_is_mysql($pdo)) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS site_translations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                translation_key VARCHAR(191) NOT NULL,
                lang VARCHAR(5) NOT NULL DEFAULT 'ru',
                translation_value TEXT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_site_tr (translation_key, lang),
                KEY idx_site_tr_lang (lang)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS home_settings_i18n (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(64) NOT NULL,
                lang VARCHAR(5) NOT NULL,
                setting_value TEXT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_home_i18n (setting_key, lang)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cms_entity_i18n (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(32) NOT NULL,
                entity_id INT NOT NULL,
                lang VARCHAR(5) NOT NULL,
                field_name VARCHAR(64) NOT NULL,
                field_value TEXT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_entity_i18n (entity_type, entity_id, lang, field_name),
                KEY idx_entity_i18n_lookup (entity_type, entity_id, lang)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } else {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS site_translations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                translation_key VARCHAR(191) NOT NULL,
                lang VARCHAR(5) NOT NULL DEFAULT 'ru',
                translation_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(translation_key, lang)
            );
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS home_settings_i18n (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(64) NOT NULL,
                lang VARCHAR(5) NOT NULL,
                setting_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(setting_key, lang)
            );
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cms_entity_i18n (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entity_type VARCHAR(32) NOT NULL,
                entity_id INTEGER NOT NULL,
                lang VARCHAR(5) NOT NULL,
                field_name VARCHAR(64) NOT NULL,
                field_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(entity_type, entity_id, lang, field_name)
            );
        ");
    }
}

/**
 * Доп. поля курса для сайдбара на детальной (идемпотентно).
 */
function cms_ensure_course_sidebar_columns(PDO $pdo): void
{
    $mysql = cms_is_mysql($pdo);
    cms_try_add_column($pdo, 'courses', 'level_label', $mysql ? "VARCHAR(50) NOT NULL DEFAULT ''" : "VARCHAR(50) DEFAULT ''");
    cms_try_add_column($pdo, 'courses', 'duration_label', $mysql ? "VARCHAR(50) NOT NULL DEFAULT ''" : "VARCHAR(50) DEFAULT ''");
    cms_try_add_column($pdo, 'courses', 'language_label', $mysql ? "VARCHAR(50) NOT NULL DEFAULT ''" : "VARCHAR(50) DEFAULT ''");
    cms_try_add_column($pdo, 'courses', 'certificate_label', $mysql ? "VARCHAR(20) NOT NULL DEFAULT ''" : "VARCHAR(20) DEFAULT ''");
    cms_try_add_column($pdo, 'courses', 'certificate_enabled', $mysql ? "TINYINT(1) NULL DEFAULT NULL" : "INTEGER DEFAULT NULL");
    cms_try_add_column($pdo, 'courses', 'quizzes_count', $mysql ? "INT NOT NULL DEFAULT 0" : "INTEGER NOT NULL DEFAULT 0");
}

/**
 * Поля детальной страницы мероприятия (идемпотентно).
 */
function cms_ensure_event_detail_columns(PDO $pdo): void
{
    $mysql = cms_is_mysql($pdo);
    $cols = [
        'organizer_name' => $mysql ? "VARCHAR(255) NOT NULL DEFAULT ''" : "VARCHAR(255) DEFAULT ''",
        'price' => $mysql ? "VARCHAR(50) NOT NULL DEFAULT ''" : "VARCHAR(50) DEFAULT ''",
        'event_time' => $mysql ? "VARCHAR(100) NOT NULL DEFAULT ''" : "VARCHAR(100) DEFAULT ''",
        'calendar_url' => $mysql ? "VARCHAR(700) NOT NULL DEFAULT ''" : "VARCHAR(700) DEFAULT ''",
        'website_url' => $mysql ? "VARCHAR(700) NOT NULL DEFAULT ''" : "VARCHAR(700) DEFAULT ''",
        'ticket_url' => $mysql ? "VARCHAR(700) NOT NULL DEFAULT ''" : "VARCHAR(700) DEFAULT ''",
        'organizer_email' => $mysql ? "VARCHAR(255) NOT NULL DEFAULT ''" : "VARCHAR(255) DEFAULT ''",
        'organizer_phone' => $mysql ? "VARCHAR(80) NOT NULL DEFAULT ''" : "VARCHAR(80) DEFAULT ''",
        'organizer_website' => $mysql ? "VARCHAR(700) NOT NULL DEFAULT ''" : "VARCHAR(700) DEFAULT ''",
        'venue_name' => $mysql ? "VARCHAR(255) NOT NULL DEFAULT ''" : "VARCHAR(255) DEFAULT ''",
        'venue_address' => $mysql ? "VARCHAR(500) NOT NULL DEFAULT ''" : "VARCHAR(500) DEFAULT ''",
        'venue_phone' => $mysql ? "VARCHAR(80) NOT NULL DEFAULT ''" : "VARCHAR(80) DEFAULT ''",
        'map_embed_url' => $mysql ? 'TEXT NULL' : 'TEXT',
    ];
    foreach ($cols as $column => $type) {
        cms_try_add_column($pdo, 'events', $column, $type);
    }
}

/**
 * SEO-поля и метка времени для оптимистической блокировки правок (идемпотентно).
 */
function cms_ensure_content_seo_columns(PDO $pdo): void
{
    $mysql = cms_is_mysql($pdo);
    $tables = ['teachers', 'courses', 'events', 'blog_posts'];
    foreach ($tables as $table) {
        cms_try_add_column($pdo, $table, 'meta_title', $mysql ? "VARCHAR(255) NOT NULL DEFAULT ''" : "VARCHAR(255) DEFAULT ''");
        cms_try_add_column($pdo, $table, 'meta_description', $mysql ? "VARCHAR(500) NOT NULL DEFAULT ''" : "VARCHAR(500) DEFAULT ''");
        cms_try_add_column($pdo, $table, 'updated_at', $mysql ? 'DATETIME NULL DEFAULT NULL' : 'DATETIME DEFAULT NULL');
    }
    foreach ($tables as $table) {
        $t = str_replace('`', '', $table);
        try {
            if ($mysql) {
                $pdo->exec("UPDATE `{$t}` SET updated_at = created_at WHERE updated_at IS NULL AND created_at IS NOT NULL");
            } else {
                $pdo->exec("UPDATE {$t} SET updated_at = created_at WHERE updated_at IS NULL AND created_at IS NOT NULL");
            }
        } catch (Throwable $e) {
            // ignore
        }
    }
}

function cms_try_add_column(PDO $pdo, string $table, string $column, string $sqlType): void
{
    $safeTable = str_replace('`', '', $table);
    try {
        if (cms_is_mysql($pdo)) {
            $pdo->exec("ALTER TABLE `{$safeTable}` ADD COLUMN `{$column}` {$sqlType}");
        } else {
            $pdo->exec("ALTER TABLE {$safeTable} ADD COLUMN {$column} {$sqlType}");
        }
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'duplicate') !== false
            || stripos($msg, 'already exists') !== false
            || stripos($msg, 'Duplicate column') !== false) {
            return;
        }
        if (stripos($msg, 'SQLSTATE[HY000]') !== false && stripos($msg, 'duplicate column') !== false) {
            return;
        }
        if (stripos($msg, '42S21') !== false || stripos($msg, '1060') !== false) {
            return;
        }
        throw $e;
    }
}
