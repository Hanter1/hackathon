<?php
/**
 * Слой данных: преподаватели, курсы, события, блог.
 */
if (!isset($pdo)) {
    require_once __DIR__ . '/app-bootstrap.php';
}

function get_teachers(string $status = 'active'): array {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM teachers WHERE status = ? ORDER BY sort_order ASC, id DESC");
    $st->execute([$status]);
    $rows = $st->fetchAll();
    return function_exists('ep_apply_entity_i18n_list') ? ep_apply_entity_i18n_list($rows, 'teacher') : $rows;
}

function get_teacher_by_slug(string $slug): ?array {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM teachers WHERE slug = ? AND status = 'active' LIMIT 1");
    $st->execute([$slug]);
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    return function_exists('ep_apply_entity_i18n') ? ep_apply_entity_i18n($row, 'teacher') : $row;
}

function get_teacher_by_id(int $id): ?array {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    return function_exists('ep_apply_entity_i18n') ? ep_apply_entity_i18n($row, 'teacher') : $row;
}

function get_courses(string $status = 'active'): array {
    global $pdo;
    $st = $pdo->prepare("SELECT c.*, t.name AS teacher_name, t.surname AS teacher_surname FROM courses c LEFT JOIN teachers t ON c.teacher_id = t.id WHERE c.status = ? ORDER BY c.sort_order ASC, c.id DESC");
    $st->execute([$status]);
    $rows = $st->fetchAll();
    return function_exists('ep_apply_entity_i18n_list') ? ep_apply_entity_i18n_list($rows, 'course') : $rows;
}

function get_course_by_slug(string $slug): ?array {
    global $pdo;
    $st = $pdo->prepare("SELECT c.*, t.name AS teacher_name, t.surname AS teacher_surname FROM courses c LEFT JOIN teachers t ON c.teacher_id = t.id WHERE c.slug = ? AND c.status = 'active' LIMIT 1");
    $st->execute([$slug]);
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    return function_exists('ep_apply_entity_i18n') ? ep_apply_entity_i18n($row, 'course') : $row;
}

function get_courses_by_teacher_id(int $teacherId, string $status = 'active', int $limit = 200): array
{
    global $pdo;
    $teacherId = max(1, $teacherId);
    $limit = max(1, min(500, $limit));
    $st = $pdo->prepare(
        "SELECT c.*, t.name AS teacher_name, t.surname AS teacher_surname
         FROM courses c
         LEFT JOIN teachers t ON c.teacher_id = t.id
         WHERE c.teacher_id = ? AND c.status = ?
         ORDER BY c.sort_order ASC, c.id DESC
         LIMIT " . (int) $limit
    );
    $st->execute([$teacherId, $status]);
    $rows = $st->fetchAll();
    return function_exists('ep_apply_entity_i18n_list') ? ep_apply_entity_i18n_list($rows, 'course') : $rows;
}

function get_events(string $status = 'active'): array {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM events WHERE status = ? ORDER BY sort_order ASC, event_date DESC, id DESC");
    $st->execute([$status]);
    $rows = $st->fetchAll();
    return function_exists('ep_apply_entity_i18n_list') ? ep_apply_entity_i18n_list($rows, 'event') : $rows;
}

function get_event_by_slug(string $slug): ?array {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM events WHERE slug = ? AND status = 'active' LIMIT 1");
    $st->execute([$slug]);
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    return function_exists('ep_apply_entity_i18n') ? ep_apply_entity_i18n($row, 'event') : $row;
}

function get_event_content_blocks(int $eventId): array
{
    global $pdo;
    if ($eventId <= 0) {
        return [];
    }
    try {
        $st = $pdo->prepare('SELECT * FROM event_content_blocks WHERE event_id = ? ORDER BY sort_order ASC, id ASC');
        $st->execute([$eventId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function get_blog_posts(string $status = 'published', int $limit = 100): array {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM blog_posts WHERE status = ? ORDER BY published_at DESC, created_at DESC LIMIT " . (int) $limit);
    $st->execute([$status]);
    $rows = $st->fetchAll();
    return function_exists('ep_apply_entity_i18n_list') ? ep_apply_entity_i18n_list($rows, 'blog_post') : $rows;
}

function count_blog_posts(string $status = 'published'): int {
    global $pdo;
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM blog_posts WHERE status = ?');
        $st->execute([$status]);

        return (int) $st->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function get_post_by_slug(string $slug): ?array {
    global $pdo;
    $st = $pdo->prepare("SELECT * FROM blog_posts WHERE slug = ? AND status = 'published' LIMIT 1");
    $st->execute([$slug]);
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    return function_exists('ep_apply_entity_i18n') ? ep_apply_entity_i18n($row, 'blog_post') : $row;
}

function slugify(string $s): string {
    $s = preg_replace('/[^a-z0-9\s\-]/ui', '', $s);
    $s = preg_replace('/[\s\-]+/', '-', trim($s));
    return mb_strtolower($s ?: 'item', 'UTF-8');
}

/** Ключи настроек главной страницы и значения по умолчанию */
const HOME_DEFAULTS = [
    'hero_1' => 'Easy',
    'hero_2' => 'обучение для увлечённых',
    'hero_3' => 'People',
    'count_1_num' => '100+',
    'count_1_text' => 'первых участников',
    'count_1_url' => '/members/',
    'count_2_num' => '10+',
    'count_2_text' => 'учебных направлений',
    'count_2_url' => '/courses.php',
    'count_3_num' => '5+',
    'count_3_text' => 'активных курсов',
    'count_3_url' => '/courses.php',
    'count_4_num' => '5.0',
    'count_4_text' => 'от первых студентов',
    'count_4_url' => '',
    'elevate_title_1' => 'Развивай свои soft skills',
    'elevate_title_2' => 'Учись и общайся с единомышленниками в Easy People',
    'elevate_btn_text' => 'Поиск курсов',
    'elevate_btn_url' => '/courses.php',
    'elevate_stat_num' => '5+',
    'elevate_stat_text' => 'Профессионалов',
    'section_top_event_slug' => '',
    'groups_section_title' => 'Популярные курсы',
    'new_courses_title' => 'Новые курсы',
    'news_section_title' => 'Последние новости',
    'signup_form_title' => 'Записаться',
    'signup_form_label_name' => 'Ваше имя',
    'signup_form_label_email' => 'Email',
    'signup_form_label_message' => 'Сообщение или комментарий',
    'signup_form_btn' => 'Отправить заявку',
    'signup_form_success' => 'Спасибо! Мы свяжемся с вами.',
];

function get_home_settings(): array {
    global $pdo;
    $out = HOME_DEFAULTS;
    try {
        $st = $pdo->query("SELECT setting_key, setting_value FROM home_settings");
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[$row['setting_key']] = $row['setting_value'];
        }
    } catch (PDOException $e) {
        // таблица может ещё не существовать
    }
    if (function_exists('ep_home_settings_localized')) {
        return ep_home_settings_localized($out);
    }
    return $out;
}

function save_home_setting(string $key, string $value): bool {
    global $pdo;
    if (!array_key_exists($key, HOME_DEFAULTS)) {
        return false;
    }
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $st = $pdo->prepare("INSERT INTO home_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP");
            $st->execute([$key, $value]);
        } else {
            $now = date('Y-m-d H:i:s');
            $st = $pdo->prepare('UPDATE home_settings SET setting_value = ?, updated_at = ? WHERE setting_key = ?');
            $st->execute([$value, $now, $key]);
            if ($st->rowCount() === 0) {
                $st = $pdo->prepare('INSERT INTO home_settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?)');
                $st->execute([$key, $value, $now]);
            }
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function get_signup_requests(): array {
    global $pdo;
    try {
        $st = $pdo->query("SELECT * FROM signup_requests ORDER BY created_at DESC");
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function count_signup_requests(): int {
    global $pdo;
    try {
        return (int) $pdo->query('SELECT COUNT(*) FROM signup_requests')->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function count_messenger_conversations(): int {
    global $pdo;
    try {
        return (int) $pdo->query('SELECT COUNT(*) FROM messenger_conversations')->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/** Отпечаток для оптимистической блокировки пакетного сохранения «Главная». */
function home_settings_lock_value(PDO $pdo): string
{
    try {
        $row = $pdo->query('SELECT MAX(COALESCE(updated_at, created_at)) AS m FROM home_settings')->fetch(PDO::FETCH_ASSOC);

        return trim((string) ($row['m'] ?? ''));
    } catch (Throwable $e) {
        return '';
    }
}

function save_signup_request(string $name, string $email, string $message = ''): bool {
    global $pdo;
    try {
        $st = $pdo->prepare("INSERT INTO signup_requests (name, email, message) VALUES (?, ?, ?)");
        $st->execute([trim($name), trim($email), trim($message)]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

require_once __DIR__ . '/i18n.php';
