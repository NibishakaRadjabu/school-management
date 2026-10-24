<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/layout.php';

if (!school_is_registered()) {
    redirect('setup.php');
}
if (is_logged_in()) {
    $destination = match ($_SESSION['user_role'] ?? '') {
        'student' => 'index.php?page=student',
        'parent' => 'index.php?page=parent',
        default => 'index.php',
    };
    redirect($destination);
}

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $query = database()->prepare(
        'SELECT u.id, u.school_id, u.name, u.email, u.profile_picture, u.password_hash, r.name AS role, s.name AS school_name
         FROM users u JOIN roles r ON r.id = u.role_id JOIN schools s ON s.id = u.school_id
         WHERE u.email = ? AND u.is_active = 1 LIMIT 1'
    );
    $query->execute([$email]);
    $user = $query->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['school_id'] = (int) $user['school_id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['profile_picture'] = (string) ($user['profile_picture'] ?? '');
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['school_name'] = $user['school_name'];
        redirect(match ($user['role']) {
            'student' => 'index.php?page=student',
            'parent' => 'index.php?page=parent',
            default => 'index.php',
        });
    }
    $error = 'We couldn’t sign you in with those details. Please try again.';
}
?>
<!doctype html>
<html lang="<?= e(current_language()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(t('Sign in')) ?> · <?= e(t('Schoolhouse')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-body">
<div class="auth-layout">
    <?php render_language_selector('language-picker auth-language'); ?>
    <section class="auth-side">
        <a class="auth-brand" href="login.php"><span class="brand-mark">S<span>+</span></span><span><?= e(t('Schoolhouse')) ?></span></a>
        <div class="auth-pitch"><span class="eyebrow light-eyebrow"><?= e(t('YOUR SCHOOL, IN SYNC')) ?></span><h1><?= e(t('Good to have')) ?><br><em><?= e(t('you back.')) ?></em></h1><p><?= e(t('Your school community is just a sign-in away.')) ?></p><div class="pitch-points"><span>✦ &nbsp; <?= e(t('One workspace, less busywork')) ?></span><span>✦ &nbsp; <?= e(t('Made for your whole community')) ?></span></div></div>
        <span class="side-footer"><?= e(t('A little more organised. A lot more connected.')) ?></span>
    </section>
    <main class="auth-main">
        <div class="auth-card login-card">
            <div class="form-heading"><span class="step-label"><?= e(t('WELCOME BACK')) ?></span><h2><?= e(t('Sign in to your school')) ?></h2><p><?= e(t('Enter your account details to continue.')) ?></p></div>
            <?php if ($error !== ''): ?><div class="alert alert-error" role="alert"><?= e(t($error)) ?></div><?php endif; ?>
            <form method="post" class="form-stack">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="lang" value="<?= e(current_language()) ?>">
                <label><?= e(t('Email address')) ?><input type="email" name="email" value="<?= e($email) ?>" placeholder="you@yourschool.edu" required autocomplete="username"></label>
                <label><?= e(t('Password')) ?><input type="password" name="password" placeholder="<?= e(t('Enter your password')) ?>" required autocomplete="current-password"></label>
                <button class="button button-primary button-wide" type="submit"><?= e(t('Sign in')) ?> <span>→</span></button>
            </form>
            <p class="form-footnote"><a class="text-link" href="forgot_password.php"><?= e(t('Forgot password')) ?>?</a></p>
            <p class="form-footnote"><?= e(t('Need a school account? Contact your school administrator.')) ?></p>
            <p class="form-footnote"><a class="text-link" href="parent_register.php"><?= e(t('Parent? Register or link your child')) ?></a></p>
        </div>
    </main>
</div>
</body>
</html>
