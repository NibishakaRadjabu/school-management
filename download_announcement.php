<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/notifications.php';

if (!school_is_registered()) {
    redirect('setup.php');
}

require_login();
$user = current_user();
$announcementId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($announcementId === false || $announcementId === null || $announcementId < 1) {
    http_response_code(404);
    exit(e(t('The announcement attachment could not be found.')));
}

$audienceAccess = match ($user['role']) {
    'parent' => 'a.audience = "all_parents"',
    'student' => 'a.audience IN ("all_parents", "all_students")',
    default => can_access_page('communications') ? '1 = 1' : '1 = 0',
};
$query = database()->prepare(
    'SELECT a.attachment_path FROM announcements a
     WHERE a.id = ? AND a.school_id = ? AND a.attachment_path IS NOT NULL AND ' . $audienceAccess
);
$query->execute([$announcementId, $user['school_id']]);
$attachmentPath = $query->fetchColumn();
if (!is_string($attachmentPath)) {
    http_response_code(404);
    exit(e(t('The announcement attachment could not be found.')));
}

$file = resolve_announcement_pdf($attachmentPath);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="announcement.pdf"');
header('Content-Length: ' . (string) filesize($file));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($file);
