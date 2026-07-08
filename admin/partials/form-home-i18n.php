<?php
declare(strict_types=1);

require_once DOC_ROOT . '/include/i18n.php';

$homeTextKeys = ep_home_text_keys();
if ($homeTextKeys === []) {
    return;
}


$keyLabels = [
    'seo_title' => 'SEO title',
    'seo_description' => 'SEO description',
    'hero_1' => 'Баннер: часть 1',
    'hero_2' => 'Баннер: часть 2',
    'hero_3' => 'Баннер: часть 3',
    'groups_section_title' => 'Заголовок «Популярные курсы»',
    'new_courses_title' => 'Заголовок «Новые курсы»',
    'news_section_title' => 'Заголовок «Новости»',
    'elevate_title_1' => 'Elevate: строка 1',
    'elevate_title_2' => 'Elevate: строка 2',
    'elevate_btn_text' => 'Elevate: кнопка',
    'elevate_stat_number' => 'Elevate: число',
    'elevate_stat_text' => 'Elevate: подпись',
];
?>
<fieldset class="admin-fieldset">
    <legend>Переводы главной (BE / EN)</legend>
    <p class="admin-lead admin-muted" style="margin-top:0;">Русские тексты — в блоках выше. Здесь переводы для белорусской и английской версии.</p>
    <?php foreach (['be' => 'Беларуская', 'en' => 'English'] as $langCode => $langLabel): ?>
    <details class="admin-i18n-lang" <?= $langCode === 'be' ? 'open' : '' ?>>
        <summary class="admin-i18n-lang__summary"><?= htmlspecialchars($langLabel, ENT_QUOTES, 'UTF-8') ?></summary>
        <div class="admin-i18n-lang__body">
            <?php foreach ($homeTextKeys as $key):
                $label = $keyLabels[$key] ?? $key;
                $val = ep_home_i18n_get($pdo, $key, $langCode);
                $inputId = 'home-i18n-' . $langCode . '-' . preg_replace('/[^a-z0-9_-]/i', '-', $key);
                $name = 'home_i18n[' . $langCode . '][' . $key . ']';
                $isLong = in_array($key, ['seo_description', 'hero_2', 'elevate_title_2', 'elevate_stat_text'], true);
            ?>
            <div class="form-group">
                <label for="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></label>
                <?php if ($isLong): ?>
                <textarea id="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" rows="2"><?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?></textarea>
                <?php else: ?>
                <input id="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>" type="text" name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </details>
    <?php endforeach; ?>
</fieldset>
