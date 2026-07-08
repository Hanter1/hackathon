<?php
require_once __DIR__ . '/auth.php';
require_login();
$adminNavActive = 'signup';

require_once DOC_ROOT . '/include/data.php';

$message = '';
$error = '';
$signup_keys = [
    'signup_form_title',
    'signup_form_label_name',
    'signup_form_label_email',
    'signup_form_label_message',
    'signup_form_btn',
    'signup_form_success',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $home = get_home_settings();
    foreach ($signup_keys as $key) {
        $value = isset($_POST['setting'][$key]) ? trim((string) $_POST['setting'][$key]) : ($home[$key] ?? '');
        save_home_setting($key, $value);
    }
    $message = 'Настройки формы «Записаться» сохранены.';
    cms_log($pdo, 'save', 'signup_form_settings', null, []);
}

$home = get_home_settings();
$page = admin_page_param();
$perPage = admin_per_page_param(30);
$total = count_signup_requests();
$off = ($page - 1) * $perPage;
$signup_list = [];
try {
    $stSig = $pdo->query('SELECT * FROM signup_requests ORDER BY created_at DESC LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $off);
    $signup_list = $stSig ? $stSig->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    $total = 0;
}
$pagerBase = ['per' => $perPage];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Форма «Записаться» — Админка</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>
    <main class="admin-main">
        <div class="page-title">
            <h1>Форма «Записаться»</h1>
        </div>
        <?php if ($message): ?><p class="admin-flash admin-flash--ok"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="admin-flash admin-flash--err"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <form method="post">
            <fieldset class="admin-fieldset">
                <legend>Тексты модалки «Записаться»</legend>
                <div class="form-group">
                    <label>Заголовок модалки</label>
                    <input type="text" name="setting[signup_form_title]" value="<?= htmlspecialchars($home['signup_form_title']) ?>" placeholder="Записаться">
                </div>
                <div class="form-group">
                    <label>Подпись поля «Имя»</label>
                    <input type="text" name="setting[signup_form_label_name]" value="<?= htmlspecialchars($home['signup_form_label_name']) ?>" placeholder="Ваше имя">
                </div>
                <div class="form-group">
                    <label>Подпись поля «Email»</label>
                    <input type="text" name="setting[signup_form_label_email]" value="<?= htmlspecialchars($home['signup_form_label_email']) ?>" placeholder="Email">
                </div>
                <div class="form-group">
                    <label>Подпись поля «Сообщение»</label>
                    <input type="text" name="setting[signup_form_label_message]" value="<?= htmlspecialchars($home['signup_form_label_message']) ?>" placeholder="Сообщение или комментарий">
                </div>
                <div class="form-group">
                    <label>Текст кнопки отправки</label>
                    <input type="text" name="setting[signup_form_btn]" value="<?= htmlspecialchars($home['signup_form_btn']) ?>" placeholder="Отправить заявку">
                </div>
                <div class="form-group">
                    <label>Текст после успешной отправки</label>
                    <input type="text" name="setting[signup_form_success]" value="<?= htmlspecialchars($home['signup_form_success']) ?>" placeholder="Спасибо! Мы свяжемся с вами.">
                </div>
            </fieldset>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>

        <section class="admin-fieldset admin-stack-top">
            <h2>Заявки</h2>
            <?= admin_pager_html($page, $total, $perPage, $pagerBase, 'signup-settings.php') ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Имя</th>
                            <th>Email</th>
                            <th>Сообщение</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($signup_list as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><a href="mailto:<?= htmlspecialchars($row['email']) ?>"><?= htmlspecialchars($row['email']) ?></a></td>
                            <td><?= nl2br(htmlspecialchars($row['message'] ?? '')) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($signup_list)): ?>
                        <tr><td colspan="4">Пока нет заявок.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <?php include __DIR__ . '/_footer.php'; ?>
</body>
</html>
