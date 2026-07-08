<?php
declare(strict_types=1);
/**
 * Сетка последних файлов из cms_media — вставка URL в поле обложки.
 *
 * Ожидает в области видимости: $pdo, переменная $media_picker_input_id — id поля обложки.
 */
$targetId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($media_picker_input_id ?? ''));
if ($targetId === '' || !isset($pdo)) {
    return;
}
$rows = [];
try {
    $rows = $pdo->query('SELECT path, original_name FROM cms_media ORDER BY id DESC LIMIT 40')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $rows = [];
}
if (!$rows) {
    return;
}
?>
            <div class="admin-media-picker">
                <p class="admin-muted" style="margin:0 0 0.5rem;">Медиатека — клик по превью подставит URL в поле обложки. <a href="media.php" target="_blank" rel="noopener">Открыть медиатеку →</a></p>
                <div class="admin-media-picker__grid">
                    <?php foreach ($rows as $m):
                        $u = (string) ($m['path'] ?? '');
                        if ($u === '') {
                            continue;
                        }
                        $label = (string) ($m['original_name'] ?? basename($u));
                        ?>
                    <button type="button" class="admin-media-picker__cell js-pick-cms-media" data-target-input="<?= htmlspecialchars($targetId, ENT_QUOTES, 'UTF-8') ?>" data-url="<?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
                        <img src="<?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
