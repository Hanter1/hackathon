<?php
require_once __DIR__ . '/auth.php';
require_site_content_editor();
$adminNavActive = 'translations';

require_once DOC_ROOT . '/include/i18n.php';

$message = '';
$error = '';
$groupFilter = isset($_GET['group']) ? trim((string) $_GET['group']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = $_POST['tr'] ?? [];
    if (!is_array($posted)) {
        $posted = [];
    }
    $saved = 0;
    foreach ($posted as $key => $langs) {
        if (!is_string($key) || !is_array($langs)) {
            continue;
        }
        foreach (['ru', 'be', 'en'] as $lang) {
            if (!array_key_exists($lang, $langs)) {
                continue;
            }
            $value = trim((string) $langs[$lang]);
            if ($value === '') {
                try {
                    $st = $pdo->prepare('DELETE FROM site_translations WHERE translation_key = ? AND lang = ?');
                    $st->execute([$key, $lang]);
                } catch (Throwable $e) {
                    // ignore
                }
                continue;
            }
            if (ep_site_translation_save($pdo, $key, $lang, $value)) {
                $saved++;
            }
        }
    }
    $message = 'Переводы сохранены (' . $saved . ' значений).';
    cms_log($pdo, 'save', 'site_translations', null, ['saved' => $saved]);
}

$defaults = ep_default_translations();
$keys = ep_all_translation_keys();
sort($keys);

$dbValues = [];
try {
    $st = $pdo->query('SELECT translation_key, lang, translation_value FROM site_translations');
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $k = (string) ($row['translation_key'] ?? '');
        $l = (string) ($row['lang'] ?? '');
        if ($k !== '' && $l !== '') {
            $dbValues[$k][$l] = (string) ($row['translation_value'] ?? '');
        }
    }
} catch (Throwable $e) {
    // table may not exist yet
}

$groups = [];
foreach ($keys as $key) {
    $g = ep_translation_group($key);
    $groups[$g] = true;
}
$groupList = array_keys($groups);
sort($groupList);

function ep_tr_value(array $defaults, array $dbValues, string $key, string $lang): string
{
    if (!empty($dbValues[$key][$lang])) {
        return (string) $dbValues[$key][$lang];
    }
    return (string) ($defaults[$lang][$key] ?? '');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Переводы интерфейса — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <main class="admin-main">
        <div class="page-title">
            <h1>Переводы интерфейса</h1>
            <a href="/" target="_blank" class="btn btn-secondary">Открыть сайт</a>
        </div>
        <?php if ($message): ?><p class="admin-flash admin-flash--ok"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="admin-flash admin-flash--err"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <p class="admin-lead admin-muted">Строки меню, кнопок, подписей на публичных страницах. Русский — базовый; белорусский и английский можно переопределить. Пустое поле BE/EN использует значение по умолчанию из файла.</p>

        <form method="get" class="admin-filter-bar" style="margin-bottom:1rem;">
            <label>
                Группа
                <select name="group" onchange="this.form.submit()">
                    <option value="">Все</option>
                    <?php foreach ($groupList as $g): ?>
                    <option value="<?= htmlspecialchars($g, ENT_QUOTES, 'UTF-8') ?>" <?= $groupFilter === $g ? 'selected' : '' ?>><?= htmlspecialchars($g, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>

        <form method="post" class="js-admin-form-unsaved">
            <?php
            $currentGroup = '';
            foreach ($keys as $key):
                $g = ep_translation_group($key);
                if ($groupFilter !== '' && $g !== $groupFilter) {
                    continue;
                }
                if ($g !== $currentGroup):
                    if ($currentGroup !== '') {
                        echo '</fieldset>';
                    }
                    $currentGroup = $g;
            ?>
            <fieldset class="admin-fieldset">
                <legend><?= htmlspecialchars(ucfirst($g), ENT_QUOTES, 'UTF-8') ?></legend>
            <?php endif; ?>
                <div class="form-group admin-i18n-row">
                    <label><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?></label>
                    <div class="admin-i18n-row__grid">
                        <?php foreach (['ru' => 'RU', 'be' => 'BE', 'en' => 'EN'] as $lang => $label): ?>
                        <div class="admin-i18n-row__cell">
                            <span class="admin-i18n-row__lang"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                            <input type="text" name="tr[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>][<?= $lang ?>]" value="<?= htmlspecialchars(ep_tr_value($defaults, $dbValues, $key, $lang), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($currentGroup !== ''): ?></fieldset><?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Сохранить переводы</button>
            </div>
        </form>
    </main>
    <style>
    .admin-i18n-row__grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.5rem; margin-top: 0.35rem; }
    .admin-i18n-row__lang { display: block; font-size: 11px; opacity: 0.7; margin-bottom: 0.2rem; }
    @media (max-width: 900px) { .admin-i18n-row__grid { grid-template-columns: 1fr; } }
    </style>
    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
