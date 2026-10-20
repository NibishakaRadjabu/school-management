<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/layout.php';
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');

if (!school_is_registered()) {
    redirect('setup.php');
}
if (is_logged_in()) {
    redirect('index.php?page=profile');
}

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$validToken = is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token) === 1;
$error = '';
$complete = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    if (!$validToken) {
        $error = 'This password reset link is invalid or expired.';
    } elseif (strlen($password) < 10 || $password !== $confirmation) {
        $error = 'Choose a password with at least 10 characters and enter it the same way twice.';
    } else {
        $pdo = database();
        $pdo->beginTransaction();
        $find = $pdo->prepare(
            'SELECT pr.id, pr.user_id FROM password_resets pr JOIN users u ON u.id = pr.user_id
             WHERE pr.token_hash = ? AND pr.expires_at > NOW() AND u.is_active = 1 FOR UPDATE'
        );
        $find->execute([hash('sha256', $token)]);
        $reset = $find->fetch();
        if (!$reset) {
            $pdo->rollBack();
            $error = 'This password reset link is invalid or expired.';
        } else {
            $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $update->execute([password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$reset['user_id']]);
            $pdo->commit();
            $complete = true;
        }
    }
}
?>
<!doctype html>
<html lang="<?= e(current_language()) ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e(t('Choose a new password')) ?> · <?= e(t('Schoolhouse')) ?></title><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="auth-body">
<div class="auth-layout">
    <?php render_language_selector('language-picker auth-language'); ?>
    <section class="auth-side"><a class="auth-brand" href="login.php"><span class="brand-mark">S<span>+</span></span><span><?= e(t('Schoolhouse')) ?></span></a><div class="auth-pitch"><span class="eyebrow light-eyebrow"><?= e(t('ACCOUNT RECOVERY')) ?></span><h1><?= e(t('Choose a new password')) ?></h1><p><?= e(t('Use a strong password you have not used elsewhere.')) ?></p></div></section>
    <main class="auth-main"><div class="auth-card login-card">
        <div class="form-heading"><span class="step-label"><?= e(t('ACCOUNT RECOVERY')) ?></span><h2><?= e(t('Choose a new password')) ?></h2></div>
        <?php if ($complete): ?><div class="alert alert-success" role="status"><?= e(t('Your password has been changed. You can sign in now.')) ?></div><a class="button button-primary button-wide" href="login.php"><?= e(t('Sign in')) ?> <span>→</span></a>
        <?php else: ?>
            <?php if ($error !== ''): ?><div class="alert alert-error" role="alert"><?= e(t($error)) ?></div><?php endif; ?>
            <?php if ($validToken): ?><form method="post" class="form-stack">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="token" value="<?= e($token) ?>">
                <label><?= e(t('New password')) ?><input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
                <label><?= e(t('Confirm new password')) ?><input type="password" name="password_confirmation" minlength="10" required autocomplete="new-password"></label>
                <button class="button button-primary button-wide" type="submit"><?= e(t('Save new password')) ?> <span>→</span></button>
            </form><?php endif; ?>
            <p class="form-footnote"><a class="text-link" href="login.php"><?= e(t('Back to sign in')) ?></a></p>
        <?php endif; ?>
    </div></main>
</div>
</body>
</html>
