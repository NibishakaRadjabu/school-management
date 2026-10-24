<?php
declare(strict_types=1);

function queue_parent_fee_notice(PDO $pdo, int $schoolId, int $studentId, string $subject, string $message, ?int $invoiceId = null): int
{
    $contacts = $pdo->prepare(
        'SELECT DISTINCT LOWER(TRIM(g.email)) AS email
         FROM guardians g
         JOIN student_guardians sg ON sg.guardian_id = g.id
         JOIN students s ON s.id = sg.student_id AND s.school_id = g.school_id
         WHERE g.school_id = ? AND s.id = ? AND g.email IS NOT NULL AND TRIM(g.email) <> ""'
    );
    $contacts->execute([$schoolId, $studentId]);
    $recipients = $contacts->fetchAll(PDO::FETCH_COLUMN);
    $queue = $pdo->prepare(
        'INSERT INTO notification_outbox (school_id, announcement_id, fee_invoice_id, subject, channel, recipient, message)
         VALUES (?, NULL, ?, ?, "email", ?, ?)'
    );
    $queued = 0;
    foreach ($recipients as $recipient) {
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            error_log('Fee notice skipped an invalid guardian email address.');
            continue;
        }
        $queue->execute([$schoolId, $invoiceId, $subject, $recipient, $message]);
        $queued++;
    }

    return $queued;
}

function store_announcement_pdf(array $file): ?string
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK
        || !isset($file['tmp_name'], $file['size'])
        || !is_string($file['tmp_name'])
        || !is_numeric($file['size'])
        || (int) $file['size'] <= 0
        || (int) $file['size'] > 10 * 1024 * 1024
        || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Upload a PDF announcement attachment no larger than 10 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $handle = fopen($file['tmp_name'], 'rb');
    if ($handle === false) {
        throw new RuntimeException('The uploaded PDF could not be read.');
    }
    $signature = fread($handle, 5);
    fclose($handle);
    if ($mime !== 'application/pdf' || $signature !== '%PDF-') {
        throw new RuntimeException('The announcement attachment must be a valid PDF file.');
    }

    $directory = dirname(__DIR__) . '/storage/announcements';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The announcement attachment directory could not be created.');
    }
    $path = 'storage/announcements/' . bin2hex(random_bytes(20)) . '.pdf';
    if (!move_uploaded_file($file['tmp_name'], dirname(__DIR__) . '/' . $path)) {
        throw new RuntimeException('The announcement PDF could not be saved.');
    }

    return $path;
}

function delete_announcement_pdf(?string $path): void
{
    if ($path === null || !preg_match('~\Astorage/announcements/[a-f0-9]{40}\.pdf\z~', $path)) {
        return;
    }
    $fullPath = dirname(__DIR__) . '/' . $path;
    if (is_file($fullPath) && !unlink($fullPath)) {
        error_log('Could not remove announcement PDF: ' . $path);
    }
}

function resolve_announcement_pdf(string $path): string
{
    if (!preg_match('~\Astorage/announcements/[a-f0-9]{40}\.pdf\z~', $path)) {
        throw new RuntimeException('The announcement attachment path is invalid.');
    }
    $fullPath = dirname(__DIR__) . '/' . $path;
    $realPath = realpath($fullPath);
    $directory = realpath(dirname(__DIR__) . '/storage/announcements');
    if ($realPath === false || $directory === false || !str_starts_with($realPath, $directory . DIRECTORY_SEPARATOR) || !is_file($realPath)) {
        throw new RuntimeException('The announcement attachment could not be found.');
    }

    return $realPath;
}
