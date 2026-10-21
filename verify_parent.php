<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/layout.php';

if (!school_is_registered()) {
    redirect('setup.php');
}

$token = $_GET['token'] ?? '';
$valid = is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token) === 1;
$verified = false;
if ($valid) {
    $pdo = database();
    $pdo->beginTransaction();
    $find = $pdo->prepare(
        'SELECT v.user_id FROM parent_email_verifications v
         JOIN users u ON u.id = v.user_id JOIN roles r ON r.id = u.role_id
         WHERE v.token_hash = ? AND v.expires_at > NOW() AND r.name = "parent"
         FOR UPDATE'
    );
    $find->execute([hash('sha256', $token)]);
    $userId = $find->fetchColumn();
    if ($userId !== false) {
        $pdo->prepare('UPDATE users SET is_active = 1 WHERE id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM parent_email_verifications WHERE user_id = ?')->execute([$userId]);
        $pdo->commit();
        $verified = true;
    } else {
        $pdo->rollBack();
    }
}
?>
<!doctype html>
<html lang="<?= e(current_language()) ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e(t('Parent email verification')) ?> · <?= e(t('Schoolhouse')) ?></title><link rel="stylesheet" href="assets/css/app.css"></head>
<body class="auth-body">
<div class="auth-layout">
    <section class="auth-side"><a class="auth-brand" href="login.php"><span class="brand-mark">S<span>+</span></span><span><?= e(t('Schoolhouse')) ?></span></a><div class="auth-pitch"><span class="eyebrow light-eyebrow"><?= e(t('PARENT PORTAL')) ?></span><h1><?= e(t('Parent email verification')) ?></h1><p><?= e(t('A verified email helps keep your child’s information private.')) ?></p></div></section>
    <main class="auth-main"><div class="auth-card login-card">
        <div class="form-heading"><h2><?= e(t($verified ? 'Your parent account is verified.' : 'This verification link is invalid or has expired.')) ?></h2></div>
        <?php if ($verified): ?><div class="alert alert-success" role="status"><?= e(t('Your account is active. Sign in to view announcements, marks, attendance, and fee balances for your linked children.')) ?></div><a class="button button-primary button-wide" href="login.php"><?= e(t('Sign in')) ?> <span>→</span></a>
        <?php else: ?><div class="alert alert-error" role="alert"><?= e(t('Request a new parent verification email or contact the school office.')) ?></div><a class="button button-secondary button-wide" href="parent_register.php"><?= e(t('Register or link your child')) ?></a><?php endif; ?>
    </div></main>
</div>
</body>
</html>
