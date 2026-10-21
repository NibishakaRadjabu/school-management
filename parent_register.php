<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/mailer.php';

if (!school_is_registered()) {
    redirect('setup.php');
}
if (is_logged_in()) {
    redirect(($_SESSION['user_role'] ?? '') === 'parent' ? 'index.php?page=parent' : 'index.php');
}

$error = '';
$notice = '';
$admissionNumber = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $admissionNumber = trim((string) ($_POST['admission_number'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if ($admissionNumber === '' || strlen($admissionNumber) > 50 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid student admission number and the parent email already registered with the school.';
    } elseif (strlen($password) < 10 || $password !== $confirmation) {
        $error = 'Choose a password with at least 10 characters and enter it the same way twice.';
    } else {
        $pdo = database();
        $createdAccount = false;
        $sendVerification = false;
        $token = '';
        try {
            $pdo->beginTransaction();
            $guardianQuery = $pdo->prepare(
                'SELECT g.id, g.school_id, g.user_id, g.name, g.email, g.phone
                 FROM students s
                 JOIN student_guardians sg ON sg.student_id = s.id
                 JOIN guardians g ON g.id = sg.guardian_id
                 WHERE s.school_id = ? AND s.admission_number = ? AND g.email = ?
                 LIMIT 1 FOR UPDATE'
            );
            $schoolId = (int) $pdo->query('SELECT id FROM schools ORDER BY id LIMIT 1')->fetchColumn();
            $guardianQuery->execute([$schoolId, $admissionNumber, $email]);
            $guardian = $guardianQuery->fetch();
            if (!$guardian) {
                $pdo->rollBack();
                $error = 'We could not verify those details. Check the admission number and use the parent email recorded by the school.';
            } else {
                $roleInsert = $pdo->prepare('INSERT INTO roles (name) VALUES (?) ON DUPLICATE KEY UPDATE name = VALUES(name)');
                $roleInsert->execute(['parent']);
                $roleQuery = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
                $roleQuery->execute(['parent']);
                $parentRoleId = (int) $roleQuery->fetchColumn();

                $existingQuery = $pdo->prepare(
                    'SELECT u.id, u.password_hash, u.is_active, r.name AS role
                     FROM users u JOIN roles r ON r.id = u.role_id
                     WHERE u.email = ? FOR UPDATE'
                );
                $existingQuery->execute([$email]);
                $existing = $existingQuery->fetch();

                if ($existing && ($existing['role'] !== 'parent' || !password_verify($password, $existing['password_hash']))) {
                    $pdo->rollBack();
                    $error = 'We could not verify those details. Check the admission number and use the password for your parent account.';
                } elseif ($guardian['user_id'] !== null && (!$existing || (int) $guardian['user_id'] !== (int) $existing['id'])) {
                    $pdo->rollBack();
                    $error = 'This parent contact is already linked to another account. Contact the school office for help.';
                } else {
                    if ($existing) {
                        $parentUserId = (int) $existing['id'];
                    } else {
                        $create = $pdo->prepare(
                            'INSERT INTO users (school_id, role_id, name, email, phone, password_hash, is_active)
                             VALUES (?, ?, ?, ?, ?, ?, 0)'
                        );
                        $create->execute([
                            $guardian['school_id'],
                            $parentRoleId,
                            $guardian['name'],
                            $email,
                            $guardian['phone'],
                            password_hash($password, PASSWORD_DEFAULT),
                        ]);
                        $parentUserId = (int) $pdo->lastInsertId();
                        $createdAccount = true;
                    }

                    $link = $pdo->prepare('UPDATE guardians SET user_id = ? WHERE id = ? AND school_id = ? AND (user_id IS NULL OR user_id = ?)');
                    $link->execute([$parentUserId, $guardian['id'], $guardian['school_id'], $parentUserId]);

                    if ((bool) ($existing['is_active'] ?? false)) {
                        $pdo->commit();
                        $notice = 'Your child has been linked to your parent account. You can sign in now.';
                    } else {
                        $recentToken = $pdo->prepare(
                            'SELECT created_at FROM parent_email_verifications
                             WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)'
                        );
                        $recentToken->execute([$parentUserId]);
                        $wasSentRecently = (bool) $recentToken->fetchColumn();
                        if (!$wasSentRecently) {
                            $token = bin2hex(random_bytes(32));
                            $pdo->prepare('DELETE FROM parent_email_verifications WHERE user_id = ?')->execute([$parentUserId]);
                            $verification = $pdo->prepare(
                                'INSERT INTO parent_email_verifications (user_id, token_hash, expires_at)
                                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
                            );
                            $verification->execute([$parentUserId, hash('sha256', $token)]);
                            $sendVerification = true;
                        }
                        $pdo->commit();

                        if ($sendVerification) {
                            $baseUrl = rtrim((string) getenv('APP_URL'), '/');
                            $baseUrlParts = filter_var($baseUrl, FILTER_VALIDATE_URL) !== false ? parse_url($baseUrl) : false;
                            if (!is_array($baseUrlParts)
                                || !in_array($baseUrlParts['scheme'] ?? '', ['http', 'https'], true)
                                || !isset($baseUrlParts['host'])
                                || isset($baseUrlParts['query']) || isset($baseUrlParts['fragment'])
                                || isset($baseUrlParts['user']) || isset($baseUrlParts['pass'])) {
                                throw new RuntimeException('Set APP_URL to the public base URL before sending parent verification emails.');
                            }
                            $verifyUrl = $baseUrl . '/verify_parent.php?token=' . rawurlencode($token);
                            try {
                                send_application_email(
                                    $email,
                                    t('Verify your parent account'),
                                    t('Hello {{name}},', ['{{name}}' => $guardian['name']]) . "\n\n"
                                    . t('Your child has been linked to this parent account. Confirm your email address to activate your sign-in.') . "\n"
                                    . $verifyUrl . "\n\n"
                                    . t('This link expires in 24 hours. If you did not request this account, contact the school.')
                                );
                            } catch (Throwable $mailException) {
                                error_log('Parent account verification email failed: ' . $mailException->getMessage());
                                $pdo->beginTransaction();
                                $pdo->prepare('DELETE FROM users WHERE id = ? AND is_active = 0')->execute([$parentUserId]);
                                $pdo->commit();
                                throw new RuntimeException('We could not send the verification email. Check the mail setup or contact the school office.');
                            }
                        }
                        $notice = $wasSentRecently
                            ? 'A verification email was recently sent. Check your inbox, including spam, before requesting another.'
                            : 'Check your email for a verification link. Your parent account will be active after you verify your address.';
                    }
                }
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Parent registration failed: ' . $exception->getMessage());
            $error = 'We could not complete registration. Check your details or contact the school office.';
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($createdAccount && isset($parentUserId)) {
                $pdo->prepare('DELETE FROM users WHERE id = ? AND is_active = 0')->execute([$parentUserId]);
            }
            $error = $exception->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="<?= e(current_language()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(t('Parent registration')) ?> · <?= e(t('Schoolhouse')) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-body">
<div class="auth-layout">
    <?php render_language_selector('language-picker auth-language'); ?>
    <section class="auth-side">
        <a class="auth-brand" href="login.php"><span class="brand-mark">S<span>+</span></span><span><?= e(t('Schoolhouse')) ?></span></a>
        <div class="auth-pitch"><span class="eyebrow light-eyebrow"><?= e(t('PARENT PORTAL')) ?></span><h1><?= e(t('Stay connected to your child’s school.')) ?></h1><p><?= e(t('Use your child’s admission number and the parent email already held by the school to securely link your account.')) ?></p></div>
    </section>
    <main class="auth-main">
        <div class="auth-card login-card">
            <div class="form-heading"><span class="step-label"><?= e(t('PARENT PORTAL')) ?></span><h2><?= e(t('Register or link your child')) ?></h2><p><?= e(t('Your contact email must match the guardian details recorded for the student.')) ?></p></div>
            <?php if ($error !== ''): ?><div class="alert alert-error" role="alert"><?= e(t($error)) ?></div><?php endif; ?>
            <?php if ($notice !== ''): ?><div class="alert alert-success" role="status"><?= e(t($notice)) ?></div><?php endif; ?>
            <form method="post" class="form-stack">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label><?= e(t('Student admission number')) ?><input name="admission_number" value="<?= e($admissionNumber) ?>" required maxlength="50" autocomplete="off"></label>
                <label><?= e(t('Parent email registered with the school')) ?><input type="email" name="email" value="<?= e($email) ?>" required maxlength="190" autocomplete="email"></label>
                <label><?= e(t('Create or confirm parent account password')) ?><input type="password" name="password" required minlength="10" autocomplete="new-password"><small><?= e(t('For a new account choose at least 10 characters. If you already have a parent account, enter its current password.')) ?></small></label>
                <label><?= e(t('Confirm password')) ?><input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label>
                <button class="button button-primary button-wide" type="submit"><?= e(t('Verify and link student')) ?> <span>→</span></button>
            </form>
            <p class="form-footnote"><a class="text-link" href="login.php"><?= e(t('Already registered? Sign in')) ?></a></p>
        </div>
    </main>
</div>
</body>
</html>
