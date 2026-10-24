<?php
declare(strict_types=1);

function provision_parent_account(PDO $pdo, int $schoolId, int $guardianId): ?array
{
    $guardianQuery = $pdo->prepare(
        'SELECT id, user_id, name, email, phone
         FROM guardians WHERE id = ? AND school_id = ? FOR UPDATE'
    );
    $guardianQuery->execute([$guardianId, $schoolId]);
    $guardian = $guardianQuery->fetch();
    if (!$guardian || trim((string) ($guardian['email'] ?? '')) === '') {
        return null;
    }

    $email = strtolower(trim((string) $guardian['email']));
    if (strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Enter a valid guardian email address before creating a parent account.');
    }
    $linkedAccount = null;
    if ($guardian['user_id'] !== null) {
        $accountQuery = $pdo->prepare(
            'SELECT u.id, u.email, u.is_active, r.name AS role
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.school_id = ? FOR UPDATE'
        );
        $accountQuery->execute([$guardian['user_id'], $schoolId]);
        $linkedAccount = $accountQuery->fetch() ?: null;
        if ($linkedAccount && $linkedAccount['role'] !== 'parent') {
            throw new RuntimeException('The guardian contact is linked to a non-parent account. Update the contact email or ask an administrator for help.');
        }
    }

    if ($linkedAccount && strtolower((string) $linkedAccount['email']) === $email && (bool) $linkedAccount['is_active']) {
        return null;
    }
    if ($linkedAccount && strtolower((string) $linkedAccount['email']) === $email) {
        $pendingVerification = $pdo->prepare(
            'SELECT 1 FROM parent_email_verifications
             WHERE user_id = ? AND expires_at > NOW() LIMIT 1'
        );
        $pendingVerification->execute([$linkedAccount['id']]);
        if ($pendingVerification->fetchColumn()) {
            return null;
        }
    }

    if (!$linkedAccount) {
        $existingQuery = $pdo->prepare(
            'SELECT u.id, u.school_id, u.email, u.is_active, r.name AS role
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.email = ? FOR UPDATE'
        );
        $existingQuery->execute([$email]);
        $existing = $existingQuery->fetch();
        if ($existing) {
            if ((int) $existing['school_id'] !== $schoolId || $existing['role'] !== 'parent') {
                throw new RuntimeException('The guardian email is already used by another school account. Update the contact email or ask an administrator for help.');
            }
            $linkAccount = $pdo->prepare('UPDATE guardians SET user_id = ? WHERE id = ? AND school_id = ? AND user_id IS NULL');
            $linkAccount->execute([$existing['id'], $guardianId, $schoolId]);
            if ((bool) $existing['is_active']) {
                return null;
            }
            $linkedAccount = $existing;
        }
    }

    $roleInsert = $pdo->prepare('INSERT INTO roles (name) VALUES (?) ON DUPLICATE KEY UPDATE name = VALUES(name)');
    $roleInsert->execute(['parent']);
    $roleQuery = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
    $roleQuery->execute(['parent']);
    $roleId = (int) $roleQuery->fetchColumn();

    if (!$linkedAccount) {
        $create = $pdo->prepare(
            'INSERT INTO users (school_id, role_id, name, email, phone, password_hash, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 0)'
        );
        $create->execute([
            $schoolId,
            $roleId,
            $guardian['name'],
            $email,
            $guardian['phone'],
            password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
        ]);
        $parentUserId = (int) $pdo->lastInsertId();
        $linkAccount = $pdo->prepare('UPDATE guardians SET user_id = ? WHERE id = ? AND school_id = ? AND user_id IS NULL');
        $linkAccount->execute([$parentUserId, $guardianId, $schoolId]);
    } else {
        $parentUserId = (int) $linkedAccount['id'];
        $update = $pdo->prepare(
            'UPDATE users SET name = ?, email = ?, phone = ?, password_hash = ?, is_active = 0
             WHERE id = ? AND school_id = ? AND role_id = ?'
        );
        $update->execute([
            $guardian['name'],
            $email,
            $guardian['phone'],
            password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            $parentUserId,
            $schoolId,
            $roleId,
        ]);
    }

    $token = bin2hex(random_bytes(32));
    $pdo->prepare('DELETE FROM parent_email_verifications WHERE user_id = ?')->execute([$parentUserId]);
    $verification = $pdo->prepare(
        'INSERT INTO parent_email_verifications (user_id, token_hash, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
    );
    $verification->execute([$parentUserId, hash('sha256', $token)]);

    return [
        'email' => $email,
        'name' => (string) $guardian['name'],
        'token' => $token,
    ];
}

function send_parent_setup_invitation(array $invitation): bool
{
    if (!smtp_mail_is_configured()) {
        return false;
    }

    $baseUrl = rtrim((string) getenv('APP_URL'), '/');
    $baseUrlParts = filter_var($baseUrl, FILTER_VALIDATE_URL) !== false ? parse_url($baseUrl) : false;
    if (!is_array($baseUrlParts)
        || !in_array($baseUrlParts['scheme'] ?? '', ['http', 'https'], true)
        || !isset($baseUrlParts['host'])
        || isset($baseUrlParts['query']) || isset($baseUrlParts['fragment'])
        || isset($baseUrlParts['user']) || isset($baseUrlParts['pass'])) {
        throw new RuntimeException('Set APP_URL to the public base URL before sending parent setup emails.');
    }

    $verifyUrl = $baseUrl . '/verify_parent.php?token=' . rawurlencode((string) $invitation['token']);
    send_application_email(
        (string) $invitation['email'],
        t('Set up your parent account'),
        t('Hello {{name}},', ['{{name}}' => (string) $invitation['name']]) . "\n\n"
        . t('A parent account has been created for your school contact. Use this one-time link to verify your email and choose a password:') . "\n"
        . $verifyUrl . "\n\n"
        . t('This link expires in 24 hours. If you did not expect this message, contact the school.')
    );

    return true;
}
