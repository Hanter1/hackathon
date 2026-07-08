<?php
declare(strict_types=1);

/** Подключение общих UX-скриптов (см. admin.js). */
if (!function_exists('admin_js_url')) {
    require_once __DIR__ . '/_assets.php';
}
?>
<script src="<?= htmlspecialchars(admin_js_url(), ENT_QUOTES, 'UTF-8') ?>" defer></script>
