<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/layout.php';

if (!school_is_registered()) {
    redirect('setup.php');
}
if (is_logged_in()) {
    redirect('index.php?page=profile');
}

$submitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $submitted = true;
    $email = trim((string) ($_POST['email'] ?? ''));
    $baseUrl = rtrim((string) getenv('APP_URL'), '/');
    $from = (string) getenv('MAIL_FROM');
    $url = filter_var($baseUrl, FILTER_VALIDATE_URL);
    $urlParts = $url !== false ? parse_url($url) : false;
    $mailConfigured = $urlParts !== false
        && in_array($urlParts['scheme'] ?? '', ['http', 'https'], true)
        && isset($urlParts['host'])
        && !isset($urlParts['user'])
        && !isset($urlParts['pass'])
        && !isset($urlParts['query'])
        && !isset($urlParts['fragment'])
        && filter_var($from, FILTER_VALIDATE_EMAIL);

    if (filter_var($email, FILTER_VALIDATE_EMAIL) && $mailConfigured) {
        $pdo = database();
        $pdo->exec('DELETE FROM password_resets WHERE expires_at <= NOW()');
        $find = $pdo->prepare('SELECT id, name FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $find->execute([$email]);
        $account = $find->fetch();
        if ($account) {
            $token = bin2hex(random_bytes(32));
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$account['id']]);
            $insert = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))');
            $insert->execute([$account['id'], hash('sha256', $token)]);
            $pdo->commit();

            $resetUrl = $baseUrl . '/reset_password.php?token=' . rawurlencode($token);
            $subject = t('Reset your Schoolhouse password');
            $body = t('Hello {{name}},', ['{{name}}' => $account['name']]) . "\n\n"
                . t('Use this one-time link to choose a new password. It expires in one hour.') . "\n"
                . $resetUrl . "\n\n"
                . t('If you did not request a password reset, you can ignore this message.');
            $headers = "From: " . $from . "\r\nContent-Type: text/plain; charset=UTF-8";
            if (!mail($email, $subject, $body, $headers)) {
                $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$account['id']]);
                error_log('Password reset email could not be sent for user id ' . (int) $account['id']);
            }
        }
    } elseif ($email !== '') {
        error_log('Password reset email is unavailable: configure valid APP_URL and MAIL_FROM values and PHP mail transport.');
    }
}
?>
<!doctype html>
<html lang="<?= e(current_language()) ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e(t('Forgot password')) ?> · <?= e(t('Schoolhouse')) ?></title><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="auth-body">
<div class="auth-layout">
    <?php render_language_selector('language-picker auth-language'); ?>
    <section class="auth-side"><a class="auth-brand" href="login.php"><span class="brand-mark">S<span>+</span></span><span><?= e(t('Schoolhouse')) ?></span></a><div class="auth-pitch"><span class="eyebrow light-eyebrow"><?= e(t('ACCOUNT RECOVERY')) ?></span><h1><?= e(t('Get back into your account.')) ?></h1><p><?= e(t('We’ll email a secure link if the address belongs to an active account.')) ?></p></div></section>
    <main class="auth-main"><div class="auth-card login-card">
        <div class="form-heading"><span class="step-label"><?= e(t('ACCOUNT RECOVERY')) ?></span><h2><?= e(t('Forgot password')) ?></h2><p><?= e(t('Enter your account email to request a password reset.')) ?></p></div>
        <?php if ($submitted): ?><div class="alert alert-success" role="status"><?= e(t('If this email belongs to an active account and email delivery is configured, password reset instructions will arrive shortly. Otherwise, contact your school administrator.')) ?></div><?php endif; ?>
        <form method="post" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="lang" value="<?= e(current_language()) ?>">
            <label><?= e(t('Email address')) ?><input type="email" name="email" required autocomplete="email"></label>
            <button class="button button-primary button-wide" type="submit"><?= e(t('Send reset link')) ?> <span>→</span></button>
        </form>
        <p class="form-footnote"><a class="text-link" href="login.php"><?= e(t('Back to sign in')) ?></a></p>
    </div></main>
</div>
</body>
</html>
