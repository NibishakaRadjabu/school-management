<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/layout.php';

if (!school_is_registered()) {
    redirect('setup.php');
}

$token = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['token'] ?? '')
    : ($_GET['token'] ?? '');
$token = is_string($token) ? $token : '';
$validToken = preg_match('/\A[a-f0-9]{64}\z/', $token) === 1;
$verified = false;
$tokenValid = false;
$error = '';
$pdo = database();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    if (strlen($password) < 10 || $password !== $confirmation) {
        $error = 'Choose a password with at least 10 characters and enter it the same way twice.';
        $tokenValid = $validToken;
    } elseif ($validToken) {
        $pdo->beginTransaction();
        $find = $pdo->prepare(
            'SELECT v.user_id FROM parent_email_verifications v
             JOIN users u ON u.id = v.user_id JOIN roles r ON r.id = u.role_id
             WHERE v.token_hash = ? AND v.expires_at > NOW() AND r.name = "parent" AND u.is_active = 0
             FOR UPDATE'
        );
        $find->execute([hash('sha256', $token)]);
        $userId = $find->fetchColumn();
        if ($userId !== false) {
            $activate = $pdo->prepare('UPDATE users SET password_hash = ?, is_active = 1 WHERE id = ? AND is_active = 0');
            $activate->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
            if ($activate->rowCount() !== 1) {
                $pdo->rollBack();
                $error = 'The parent account could not be activated. Please try again.';
            } else {
                $pdo->prepare('DELETE FROM parent_email_verifications WHERE user_id = ?')->execute([$userId]);
                $pdo->commit();
                $verified = true;
            }
        } else {
            $pdo->rollBack();
        }
    }
} elseif ($validToken) {
    $find = $pdo->prepare(
        'SELECT 1 FROM parent_email_verifications v
         JOIN users u ON u.id = v.user_id JOIN roles r ON r.id = u.role_id
         WHERE v.token_hash = ? AND v.expires_at > NOW() AND r.name = "parent" AND u.is_active = 0
         LIMIT 1'
    );
    $find->execute([hash('sha256', $token)]);
    $tokenValid = (bool) $find->fetchColumn();
}
?>
<!doctype html>
<html lang="<?= e(current_language()) ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e(t('Set up your parent account')) ?> · <?= e(t('Schoolhouse')) ?></title><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="auth-body">
<div class="auth-layout">
    <section class="auth-side"><a class="auth-brand" href="login.php"><span class="brand-mark">S<span>+</span></span><span><?= e(t('Schoolhouse')) ?></span></a><div class="auth-pitch"><span class="eyebrow light-eyebrow"><?= e(t('PARENT PORTAL')) ?></span><h1><?= e(t('Set up your parent account')) ?></h1><p><?= e(t('A verified email helps keep your child’s information private.')) ?></p></div></section>
    <main class="auth-main"><div class="auth-card login-card">
        <?php if ($verified): ?>
            <div class="form-heading"><h2><?= e(t('Your parent account is verified.')) ?></h2></div>
            <div class="alert alert-success" role="status"><?= e(t('Your account is active. Sign in to view announcements, marks, attendance, and fee balances for your linked children.')) ?></div>
            <a class="button button-primary button-wide" href="login.php"><?= e(t('Sign in')) ?> <span>→</span></a>
        <?php elseif ($tokenValid): ?>
            <div class="form-heading"><h2><?= e(t('Choose a password')) ?></h2><p><?= e(t('Set a password to verify your email and activate your parent account.')) ?></p></div>
            <?php if ($error !== ''): ?><div class="alert alert-error" role="alert"><?= e(t($error)) ?></div><?php endif; ?>
            <form method="post" class="form-stack">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <label><?= e(t('New password')) ?><input type="password" name="password" required minlength="10" autocomplete="new-password"><small><?= e(t('At least 10 characters.')) ?></small></label>
                <label><?= e(t('Confirm password')) ?><input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label>
                <button class="button button-primary button-wide" type="submit"><?= e(t('Verify and activate account')) ?> <span>→</span></button>
            </form>
        <?php else: ?>
            <div class="form-heading"><h2><?= e(t('This verification link is invalid or has expired.')) ?></h2></div>
            <div class="alert alert-error" role="alert"><?= e(t('Contact the school office to request a new parent setup link.')) ?></div>
            <a class="button button-secondary button-wide" href="login.php"><?= e(t('Sign in')) ?></a>
        <?php endif; ?>
    </div></main>
</div>
</body>
</html>
