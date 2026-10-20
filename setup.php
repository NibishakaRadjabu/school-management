<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/layout.php';

if (school_is_registered()) {
    redirect(is_logged_in() ? 'index.php' : 'login.php');
}

$errors = [];
$values = ['school_name' => '', 'school_email' => '', 'school_phone' => '', 'school_address' => '', 'admin_name' => '', 'admin_email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($values as $key => $_) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $password = (string) ($_POST['admin_password'] ?? '');

    if ($values['school_name'] === '' || $values['admin_name'] === '') {
        $errors[] = 'School name and administrator name are required.';
    }
    if (!filter_var($values['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid administrator email address.';
    }
    if ($values['school_email'] !== '' && !filter_var($values['school_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid school email address.';
    }
    if (strlen($password) < 10) {
        $errors[] = 'Choose an administrator password with at least 10 characters.';
    }

    if ($errors === []) {
        $pdo = database();
        try {
            $pdo->beginTransaction();
            if (school_is_registered()) {
                throw new RuntimeException('A school has already been registered.');
            }
            $pdo->exec("INSERT INTO roles (name) VALUES ('admin'), ('teacher'), ('parent'), ('student')");
            $roleId = (int) $pdo->query("SELECT id FROM roles WHERE name = 'admin'")->fetchColumn();
            $insertSchool = $pdo->prepare(
                'INSERT INTO schools (name, email, phone, address) VALUES (:name, :email, :phone, :address)'
            );
            $insertSchool->execute([
                'name' => $values['school_name'],
                'email' => $values['school_email'] ?: null,
                'phone' => $values['school_phone'] ?: null,
                'address' => $values['school_address'] ?: null,
            ]);
            $schoolId = (int) $pdo->lastInsertId();
            $insertSections = $pdo->prepare('INSERT INTO sections (school_id, name) VALUES (?, ?), (?, ?), (?, ?)');
            $insertSections->execute([$schoolId, 'Nursery', $schoolId, 'Primary', $schoolId, 'Secondary']);
            $insertAdmin = $pdo->prepare(
                'INSERT INTO users (school_id, role_id, name, email, password_hash) VALUES (?, ?, ?, ?, ?)'
            );
            $insertAdmin->execute([$schoolId, $roleId, $values['admin_name'], $values['admin_email'], password_hash($password, PASSWORD_DEFAULT)]);
            $adminId = (int) $pdo->lastInsertId();
            $pdo->commit();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $adminId;
            $_SESSION['school_id'] = $schoolId;
            $_SESSION['user_name'] = $values['admin_name'];
            $_SESSION['profile_picture'] = '';
            $_SESSION['user_role'] = 'admin';
            $_SESSION['school_name'] = $values['school_name'];
            flash('Your school workspace is ready. Welcome aboard!');
            redirect('index.php');
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash($exception->getMessage(), 'error');
            redirect('login.php');
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception->getCode() === '23000') {
                $errors[] = 'A matching school or administrator record already exists. Check your email or sign in if setup has already completed.';
            } else {
                error_log('School setup failed: ' . $exception->getMessage());
                $errors[] = 'Setup could not be completed. Check the database connection and try again.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="<?= e(current_language()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(t('Register your school')) ?> · <?= e(t('Schoolhouse')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-body">
<div class="auth-layout setup-layout">
    <?php render_language_selector('language-picker auth-language'); ?>
    <section class="auth-side">
        <a class="auth-brand" href="setup.php"><span class="brand-mark">S<span>+</span></span><span><?= e(t('Schoolhouse')) ?></span></a>
        <div class="auth-pitch">
            <span class="eyebrow light-eyebrow"><?= e(t('A better school day starts here')) ?></span>
            <h1><?= e(t('Everything your school needs.')) ?><br><em><?= e(t('All in one place.')) ?></em></h1>
            <p><?= e(t('Bring your people, academics, and school community together in one simple workspace.')) ?></p>
            <div class="pitch-points"><span>✦ &nbsp; <?= e(t('Nursery to secondary')) ?></span><span>✦ &nbsp; <?= e(t('Made for your whole community')) ?></span></div>
        </div>
        <span class="side-footer"><?= e(t('A little more organised. A lot more connected.')) ?></span>
    </section>
    <main class="auth-main setup-main">
        <div class="auth-card setup-card">
            <div class="form-heading"><span class="step-label"><?= e(t('GETTING STARTED')) ?> &nbsp;·&nbsp; 1 / 1</span><h2><?= e(t('Register your school')) ?></h2><p><?= e(t('There’s no school registered yet. Let’s set up your workspace.')) ?></p></div>
            <?php if ($errors !== []): ?><div class="alert alert-error"><ul class="error-list"><?php foreach ($errors as $error): ?><li><?= e(t($error)) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <form method="post" class="form-stack">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <h3 class="form-section-title"><?= e(t('School details')) ?></h3>
                <label><?= e(t('School name')) ?> <span class="required">*</span><input name="school_name" value="<?= e($values['school_name']) ?>" placeholder="<?= e(t('e.g. Bright Future Academy')) ?>" required maxlength="150"></label>
                <div class="form-grid">
                    <label><?= e(t('School email')) ?><input type="email" name="school_email" value="<?= e($values['school_email']) ?>" placeholder="hello@yourschool.edu" maxlength="190"></label>
                    <label><?= e(t('Phone number')) ?><input name="school_phone" value="<?= e($values['school_phone']) ?>" placeholder="+250 700 000 000" maxlength="40"></label>
                </div>
                <label><?= e(t('School address')) ?><input name="school_address" value="<?= e($values['school_address']) ?>" placeholder="<?= e(t('Town, district, or full address')) ?>" maxlength="255"></label>
                <h3 class="form-section-title admin-section"><?= e(t('Administrator account')) ?></h3>
                <label><?= e(t('Your full name')) ?> <span class="required">*</span><input name="admin_name" value="<?= e($values['admin_name']) ?>" placeholder="<?= e(t('e.g. Alex Morgan')) ?>" required maxlength="150"></label>
                <label><?= e(t('Email address')) ?> <span class="required">*</span><input type="email" name="admin_email" value="<?= e($values['admin_email']) ?>" placeholder="you@yourschool.edu" required maxlength="190"></label>
                <label><?= e(t('Password')) ?> <span class="required">*</span><input type="password" name="admin_password" placeholder="<?= e(t('At least 10 characters')) ?>" required minlength="10" autocomplete="new-password"></label>
                <button class="button button-primary button-wide" type="submit"><?= e(t('Create school workspace')) ?> <span>→</span></button>
            </form>
            <p class="form-footnote"><?= e(t('You can configure staff, classes, and notifications once your workspace is ready.')) ?></p>
        </div>
    </main>
</div>
</body>
</html>
