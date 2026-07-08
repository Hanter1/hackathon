<?php
declare(strict_types=1);
/**
 * Блок SEO (meta title / description).
 *
 * Ожидает: $seo_id_prefix (префикс id: course, mentor, event, post), $item (строка редактирования).
 * Опционально: $seo_legend, $seo_hint.
 */
$seo_id_prefix = preg_replace('/[^a-z0-9_-]/i', '', (string) ($seo_id_prefix ?? 'seo'));
$item = is_array($item ?? null) ? $item : [];
$seo_legend = (string) ($seo_legend ?? 'SEO');
$seo_hint = (string) ($seo_hint ?? '');
?>
            <fieldset class="admin-fieldset admin-fieldset--nested">
                <legend><?= htmlspecialchars($seo_legend, ENT_QUOTES, 'UTF-8') ?></legend>
                <?php if ($seo_hint !== ''): ?>
                <p class="admin-muted" style="margin-top:0;"><?= htmlspecialchars($seo_hint, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <div class="form-group">
                    <label for="<?= htmlspecialchars($seo_id_prefix, ENT_QUOTES, 'UTF-8') ?>-meta-title">Meta title</label>
                    <input id="<?= htmlspecialchars($seo_id_prefix, ENT_QUOTES, 'UTF-8') ?>-meta-title" type="text" name="meta_title" value="<?= htmlspecialchars((string) ($item['meta_title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" maxlength="255" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="<?= htmlspecialchars($seo_id_prefix, ENT_QUOTES, 'UTF-8') ?>-meta-desc">Meta description</label>
                    <textarea id="<?= htmlspecialchars($seo_id_prefix, ENT_QUOTES, 'UTF-8') ?>-meta-desc" name="meta_description" maxlength="500" rows="3"><?= htmlspecialchars((string) ($item['meta_description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
            </fieldset>
