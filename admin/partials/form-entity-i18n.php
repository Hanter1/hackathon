<?php
/**
 * Поля перевода сущности CMS (BE / EN). Русский — в основных полях формы.
 *
 * @var string $i18n_entity_type  course|blog_post|teacher|event
 * @var int    $i18n_entity_id
 */
declare(strict_types=1);

require_once DOC_ROOT . '/include/i18n.php';

$entityType = (string) ($i18n_entity_type ?? '');
$entityId = (int) ($i18n_entity_id ?? 0);
$fields = ep_entity_i18n_fields($entityType);
if ($fields === []) {
    return;
}

$fieldLabels = [
    'title' => 'Название',
    'category' => 'Категория',
    'description' => 'Описание',
    'excerpt' => 'Краткое описание',
    'content' => 'Текст',
    'author_name' => 'Тег / автор (на карточке)',
    'name' => 'Имя',
    'surname' => 'Фамилия',
    'bio' => 'Биография',
    'location' => 'Место',
    'organizer_name' => 'Организатор',
    'venue_name' => 'Площадка',
    'venue_address' => 'Адрес площадки',
    'meta_title' => 'Meta title',
    'meta_description' => 'Meta description',
    'level_label' => 'Уровень',
    'duration_label' => 'Длительность',
    'language_label' => 'Язык курса',
];

$textareaFields = ['description', 'excerpt', 'content', 'bio', 'meta_description'];
$i18nBe = $entityId > 0 ? ep_entity_i18n_get($pdo, $entityType, $entityId, 'be') : [];
$i18nEn = $entityId > 0 ? ep_entity_i18n_get($pdo, $entityType, $entityId, 'en') : [];

if ($entityId <= 0): ?>
<fieldset class="admin-fieldset">
    <legend>Переводы (BE / EN)</legend>
    <p class="admin-lead admin-muted" style="margin-top:0;">Сначала сохраните запись на русском — затем откройте редактирование и добавьте переводы.</p>
</fieldset>
<?php return; endif; ?>

<fieldset class="admin-fieldset">
    <legend>Переводы (белорусский / English)</legend>
    <p class="admin-lead admin-muted" style="margin-top:0;">Русский текст задаётся в полях выше. Здесь — необязательные переводы для белорусской и английской версии сайта.</p>
    <?php foreach (['be' => 'Беларуская', 'en' => 'English'] as $langCode => $langLabel):
        $values = $langCode === 'be' ? $i18nBe : $i18nEn;
    ?>
    <details class="admin-i18n-lang" <?= $langCode === 'be' ? 'open' : '' ?>>
        <summary class="admin-i18n-lang__summary"><?= htmlspecialchars($langLabel, ENT_QUOTES, 'UTF-8') ?></summary>
        <div class="admin-i18n-lang__body">
            <?php foreach ($fields as $field):
                $label = $fieldLabels[$field] ?? $field;
                $val = (string) ($values[$field] ?? '');
                $inputId = 'i18n-' . $langCode . '-' . preg_replace('/[^a-z0-9_-]/i', '-', $field);
                $name = 'i18n[' . $langCode . '][' . $field . ']';
            ?>
            <div class="form-group">
                <label for="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></label>
                <?php if (in_array($field, $textareaFields, true)): ?>
                <textarea id="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" rows="<?= $field === 'content' ? 8 : 3 ?>"><?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?></textarea>
                <?php else: ?>
                <input id="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>" type="text" name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </details>
    <?php endforeach; ?>
</fieldset>
<style>
.admin-i18n-lang { margin: 0.75rem 0; border: 1px solid rgba(0,0,0,.08); border-radius: 8px; padding: 0.5rem 0.75rem; }
.admin-i18n-lang__summary { cursor: pointer; font-weight: 600; padding: 0.35rem 0; }
.admin-i18n-lang__body { padding-top: 0.5rem; }
</style>
