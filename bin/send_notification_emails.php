<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/mailer.php';

$limit = 50;
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/\A--limit=([0-9]+)\z/', $argument, $matches) !== 1
        || (int) $matches[1] < 1
        || (int) $matches[1] > 500) {
        fwrite(STDERR, "Usage: php bin/send_notification_emails.php [--limit=1..500]\n");
        exit(2);
    }
    $limit = (int) $matches[1];
}

try {
    ensure_smtp_mail_ready();
    $pdo = database();
    $sent = 0;
    $failed = 0;

    for ($processed = 0; $processed < $limit; $processed++) {
        $pdo->beginTransaction();
        $query = $pdo->query(
            "SELECT o.id, o.recipient, o.message, a.title AS announcement_title, s.name AS school_name
             FROM notification_outbox o
             LEFT JOIN announcements a ON a.id = o.announcement_id
             JOIN schools s ON s.id = o.school_id
             WHERE o.channel = 'email' AND o.status = 'queued'
             ORDER BY o.id ASC
             LIMIT 1
             FOR UPDATE"
        );
        $notification = $query->fetch();
        if (!$notification) {
            $pdo->commit();
            break;
        }

        $status = 'sent';
        try {
            $subject = trim((string) ($notification['announcement_title'] ?? ''));
            if ($subject === '') {
                $subject = (string) $notification['school_name'];
            } else {
                $subject = (string) $notification['school_name'] . ': ' . $subject;
            }
            send_application_email(
                (string) $notification['recipient'],
                $subject,
                (string) $notification['message']
            );
            $sent++;
        } catch (Throwable $exception) {
            $status = 'failed';
            $failed++;
            error_log('Email outbox delivery failed for notification ' . (int) $notification['id'] . ': ' . $exception->getMessage());
        }

        $update = $pdo->prepare('UPDATE notification_outbox SET status = ? WHERE id = ? AND status = ?');
        $update->execute([$status, $notification['id'], 'queued']);
        $pdo->commit();
    }

    fwrite(STDOUT, "Email outbox run complete: {$sent} sent, {$failed} failed.\n");
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Email outbox could not run: ' . $exception->getMessage() . "\n");
    exit(1);
}
