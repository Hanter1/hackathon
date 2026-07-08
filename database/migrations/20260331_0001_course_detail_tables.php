<?php
declare(strict_types=1);

return [
    'mysql' => "
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

        CREATE TABLE IF NOT EXISTS course_overview_list_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            block_id INT NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            text VARCHAR(700) NOT NULL DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_coli_block (block_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS course_curriculum_modules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INT NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            title VARCHAR(255) NOT NULL DEFAULT '',
            is_open TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ccm_course (course_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
    ",
    'sqlite' => "
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

        CREATE TABLE IF NOT EXISTS course_overview_list_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            block_id INTEGER NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            text VARCHAR(700) NOT NULL DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS course_curriculum_modules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id INTEGER NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            title VARCHAR(255) NOT NULL DEFAULT '',
            is_open INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

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

        CREATE TABLE IF NOT EXISTS course_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id INTEGER NOT NULL,
            author_name VARCHAR(255) NOT NULL DEFAULT '',
            author_email VARCHAR(255) NOT NULL DEFAULT '',
            body TEXT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ",
];

