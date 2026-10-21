<?php
declare(strict_types=1);

function smtp_mail_configuration(): ?array
{
    $keys = ['MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION'];
    $configured = false;
    foreach ($keys as $key) {
        if (getenv($key) !== false && getenv($key) !== '') {
            $configured = true;
            break;
        }
    }
    if (!$configured) {
        return null;
    }

    $host = trim((string) getenv('MAIL_HOST'));
    $username = trim((string) getenv('MAIL_USERNAME'));
    $password = (string) getenv('MAIL_PASSWORD');
    $from = trim((string) getenv('MAIL_FROM'));
    $encryptionValue = getenv('MAIL_ENCRYPTION');
    $encryption = strtolower(trim($encryptionValue === false || $encryptionValue === '' ? 'tls' : $encryptionValue));
    $portValue = getenv('MAIL_PORT');
    $port = (string) ($portValue === false || $portValue === ''
        ? ($encryption === 'ssl' ? '465' : '587')
        : $portValue);

    if ($host === '' || $username === '' || $password === '' || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('SMTP email is partially configured. Set MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD, and a valid MAIL_FROM.');
    }
    if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
        throw new RuntimeException('MAIL_PORT must be a valid TCP port between 1 and 65535.');
    }
    if (!in_array($encryption, ['tls', 'ssl'], true)) {
        throw new RuntimeException('MAIL_ENCRYPTION must be either tls or ssl.');
    }

    return [
        'host' => $host,
        'port' => (int) $port,
        'username' => $username,
        'password' => $password,
        'from' => $from,
        'from_name' => trim((string) (getenv('MAIL_FROM_NAME') ?: 'Schoolhouse')),
        'encryption' => $encryption,
    ];
}

function smtp_mail_is_configured(): bool
{
    try {
        return smtp_mail_configuration() !== null && is_file(__DIR__ . '/../vendor/autoload.php');
    } catch (RuntimeException) {
        return false;
    }
}

function ensure_smtp_mail_ready(): array
{
    $configuration = smtp_mail_configuration();
    if ($configuration === null) {
        throw new RuntimeException('SMTP email is not configured. Set the MAIL_* environment variables before sending email.');
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('PHPMailer is not installed. Run composer install from the project directory.');
    }
    require_once $autoload;

    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        throw new RuntimeException('PHPMailer could not be loaded. Run composer install from the project directory.');
    }

    return $configuration;
}

function send_application_email(string $recipient, string $subject, string $body): void
{
    $configuration = smtp_mail_configuration();
    if ($configuration === null) {
        $from = trim((string) getenv('MAIL_FROM'));
        if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('MAIL_FROM must be a valid email address.');
        }
        $headers = "From: {$from}\r\nContent-Type: text/plain; charset=UTF-8";
        if (!mail($recipient, $subject, $body, $headers)) {
            throw new RuntimeException('PHP mail() could not send the email.');
        }
        return;
    }

    $configuration = ensure_smtp_mail_ready();
    $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $configuration['host'];
    $mailer->Port = $configuration['port'];
    $mailer->SMTPAuth = true;
    $mailer->Username = $configuration['username'];
    $mailer->Password = $configuration['password'];
    $mailer->SMTPSecure = $configuration['encryption'] === 'ssl'
        ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->Timeout = 15;
    $mailer->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
    $mailer->setFrom($configuration['from'], $configuration['from_name']);
    $mailer->addAddress($recipient);
    $mailer->Subject = $subject;
    $mailer->Body = $body;
    $mailer->isHTML(false);
    $mailer->send();
}
