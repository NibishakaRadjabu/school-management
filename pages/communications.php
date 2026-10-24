<?php
require_once __DIR__ . '/../app/mailer.php';

$emailConfigured = smtp_mail_is_configured();
$schoolId = current_user()['school_id'];
$pdo = database();
$guardianQuery = $pdo->prepare('SELECT COUNT(DISTINCT g.id) FROM guardians g JOIN student_guardians sg ON sg.guardian_id = g.id WHERE g.school_id = ?');
$guardianQuery->execute([$schoolId]);
$guardianCount = (int) $guardianQuery->fetchColumn();
$studentCountQuery = $pdo->prepare(
    'SELECT COUNT(DISTINCT s.id) FROM students s
     JOIN users u ON u.id = s.user_id AND u.school_id = s.school_id
     WHERE s.school_id = ? AND s.status = "active" AND u.is_active = 1'
);
$studentCountQuery->execute([$schoolId]);
$studentCount = (int) $studentCountQuery->fetchColumn();
$announcementQuery = $pdo->prepare(
    'SELECT a.id, a.title, a.message, a.channels, a.audience, a.attachment_path, a.created_at, u.name AS author_name,
            (SELECT COUNT(*) FROM notification_outbox o WHERE o.announcement_id = a.id AND o.school_id = a.school_id) AS queued_count
     FROM announcements a LEFT JOIN users u ON u.id = a.author_id WHERE a.school_id = ? ORDER BY a.created_at DESC LIMIT 20'
);
$announcementQuery->execute([$schoolId]);
$announcements = $announcementQuery->fetchAll();
$conversationQuery = $pdo->prepare(
    'SELECT c.id, c.subject, c.updated_at, u.name AS parent_name,
            (SELECT m.body FROM parent_school_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS latest_message
     FROM parent_school_conversations c
     JOIN users u ON u.id = c.parent_user_id
     WHERE c.school_id = ?
     ORDER BY c.updated_at DESC LIMIT 50'
);
$conversationQuery->execute([$schoolId]);
$conversations = $conversationQuery->fetchAll();
$selectedConversation = null;
$requestedConversationId = filter_var($_GET['conversation'] ?? null, FILTER_VALIDATE_INT);
if ($requestedConversationId !== false && $requestedConversationId !== null && $requestedConversationId > 0) {
    $selectedConversationQuery = $pdo->prepare(
        'SELECT c.id, c.subject, u.name AS parent_name
         FROM parent_school_conversations c JOIN users u ON u.id = c.parent_user_id
         WHERE c.id = ? AND c.school_id = ?'
    );
    $selectedConversationQuery->execute([$requestedConversationId, $schoolId]);
    $selectedConversation = $selectedConversationQuery->fetch() ?: null;
} elseif ($conversations !== []) {
    $selectedConversation = [
        'id' => (int) $conversations[0]['id'],
        'subject' => $conversations[0]['subject'],
        'parent_name' => $conversations[0]['parent_name'],
    ];
}
$conversationMessages = [];
if ($selectedConversation !== null) {
    $messagesQuery = $pdo->prepare(
        'SELECT m.body, m.created_at, u.name AS sender_name
         FROM parent_school_messages m LEFT JOIN users u ON u.id = m.sender_id
         WHERE m.conversation_id = ?
         ORDER BY m.id'
    );
    $messagesQuery->execute([$selectedConversation['id']]);
    $conversationMessages = $messagesQuery->fetchAll();
}
?>
<section class="page-heading"><div><span class="eyebrow"><?= e(t('STAY CONNECTED')) ?></span><h1><?= e(t('Communications')) ?></h1><p><?= e(t('Share important updates with parents, guardians, and students.')) ?></p></div></section>
<div class="info-banner"><span>ⓘ</span><p><?= e(t('Messages are added to your delivery queue. Connect an SMS gateway, Gmail/SMTP account, or mobile app provider before messages can be delivered.')) ?></p></div>
<section class="content-grid">
    <article class="panel form-panel announcement-form-panel">
        <div class="panel-heading"><div><h2><?= e(t('New announcement')) ?></h2><p><?= e(t('Write once and choose where it should go.')) ?></p></div><span class="panel-icon">↗</span></div>
        <form method="post" action="index.php?page=communications" enctype="multipart/form-data" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create_announcement">
            <label><?= e(t('Announcement title')) ?><input name="title" required maxlength="180" placeholder="<?= e(t('e.g. Parents meeting this Saturday')) ?>"></label>
            <label><?= e(t('Announcement audience')) ?><select name="audience" required><option value="all_parents"><?= e(t('All parents & guardians')) ?> (<?= number_format($guardianCount) ?>)</option><option value="all_students"><?= e(t('All active students with accounts')) ?> (<?= number_format($studentCount) ?>)</option></select></label>
            <label><?= e(t('Message')) ?><textarea name="message" rows="5" required placeholder="<?= e(t('Write your school announcement...')) ?>"></textarea></label>
            <label><?= e(t('PDF attachment (optional)')) ?><input type="file" name="announcement_pdf" accept="application/pdf"><small><?= e(t('Upload one PDF, up to 10 MB. It will be available in the portal and attached to announcement emails.')) ?></small></label>
            <fieldset class="channel-fieldset"><legend><?= e(t('Delivery channels')) ?></legend><div class="channel-options">
                <label class="channel-option"><input type="checkbox" name="channels[]" value="sms"><span class="channel-symbol channel-sms">SMS</span><span><strong><?= e(t('Text message')) ?></strong><small><?= e(t('Requires an SMS provider')) ?></small></span></label>
                <label class="channel-option"><input type="checkbox" name="channels[]" value="email"><span class="channel-symbol channel-email">✉</span><span><strong><?= e(t('Email')) ?></strong><small><?= e(t('Requires Gmail / SMTP setup')) ?></small></span></label>
                <label class="channel-option"><input type="checkbox" name="channels[]" value="app"><span class="channel-symbol channel-app">▣</span><span><strong><?= e(t('Mobile app')) ?></strong><small><?= e(t('Requires an app notification service')) ?></small></span></label>
            </div></fieldset>
            <button class="button button-primary" type="submit"><?= e(t('Save announcement')) ?> <span>→</span></button>
        </form>
    </article>
    <article class="panel form-panel communication-summary">
        <div class="panel-heading"><div><h2><?= e(t('Your channels')) ?></h2><p><?= e(t('Delivery setup overview')) ?></p></div><span class="panel-icon">✳</span></div>
        <div class="channel-status"><span class="channel-symbol channel-sms">SMS</span><span><strong><?= e(t('Text messages')) ?></strong><small><?= e(t('Provider not connected')) ?></small></span><span class="setup-status"><?= e(t('Setup needed')) ?></span></div>
        <div class="channel-status"><span class="channel-symbol channel-email">✉</span><span><strong><?= e(t('Email')) ?></strong><small><?= e(t($emailConfigured ? 'Gmail / SMTP configured' : 'Gmail / SMTP not connected')) ?></small></span><span class="setup-status"><?= e(t($emailConfigured ? 'Configured' : 'Setup needed')) ?></span></div>
        <div class="channel-status"><span class="channel-symbol channel-app">▣</span><span><strong><?= e(t('Mobile app')) ?></strong><small><?= e(t('App service not connected')) ?></small></span><span class="setup-status"><?= e(t('Setup needed')) ?></span></div>
        <div class="integration-note"><strong><?= e(t($emailConfigured ? 'Email provider is configured' : 'Not sending just yet')) ?></strong><p><?= e(t($emailConfigured ? 'Schedule the email outbox worker to deliver queued announcement emails. SMS and app notifications still require their own providers.' : 'Connect your provider credentials in the server configuration before turning on live delivery. Your announcements are safely recorded here in the meantime.')) ?></p></div>
    </article>
</section>
<section class="content-grid">
    <article class="panel form-panel">
        <div class="panel-heading"><div><h2><?= e(t('Parent conversations')) ?></h2><p><?= e(t('Private messages from parents and guardians')) ?></p></div><span class="panel-icon">✉</span></div>
        <?php if ($conversations === []): ?><div class="empty-state compact"><p><?= e(t('There are no parent conversations yet.')) ?></p></div>
        <?php else: ?><div class="announcement-list"><?php foreach ($conversations as $conversation): ?>
            <a class="announcement-row" href="index.php?page=communications&amp;conversation=<?= (int) $conversation['id'] ?>"<?= $selectedConversation !== null && (int) $selectedConversation['id'] === (int) $conversation['id'] ? ' aria-current="true"' : '' ?>><div class="announcement-avatar">✉</div><div class="announcement-content"><div class="announcement-title-row"><h3><?= e($conversation['subject']) ?></h3><time><?= e(localized_date($conversation['updated_at'])) ?></time></div><p><?= e($conversation['parent_name']) ?> · <?= e($conversation['latest_message'] ?? '') ?></p></div></a>
        <?php endforeach; ?></div><?php endif; ?>
    </article>
    <article class="panel form-panel">
        <div class="panel-heading"><div><h2><?= e($selectedConversation !== null ? $selectedConversation['subject'] : t('Conversation details')) ?></h2><p><?= e($selectedConversation !== null ? t('Conversation with {{name}}', ['{{name}}' => $selectedConversation['parent_name']]) : t('Select a parent conversation to read and reply.')) ?></p></div><span class="panel-icon">↗</span></div>
        <?php if ($selectedConversation !== null): ?>
            <div class="parent-announcement-list"><?php foreach ($conversationMessages as $message): ?>
                <article class="parent-announcement"><div class="announcement-title-row"><strong><?= e($message['sender_name'] ?? t('Former staff member')) ?></strong><time><?= e(localized_date($message['created_at'])) ?></time></div><p><?= nl2br(e($message['body'])) ?></p></article>
            <?php endforeach; ?></div>
            <form method="post" action="index.php?page=communications&amp;conversation=<?= (int) $selectedConversation['id'] ?>" class="form-stack">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reply_parent_conversation"><input type="hidden" name="conversation_id" value="<?= (int) $selectedConversation['id'] ?>">
                <label><?= e(t('Reply to parent')) ?><textarea name="message" required maxlength="4000" rows="4"></textarea></label>
                <button class="button button-primary" type="submit"><?= e(t('Send reply')) ?> <span>→</span></button>
            </form>
        <?php else: ?><div class="empty-state compact"><p><?= e(t('Parent messages will appear here.')) ?></p></div><?php endif; ?>
    </article>
</section>
<section class="panel recent-panel">
    <div class="panel-heading"><div><h2><?= e(t('Recent announcements')) ?></h2><p><?= e(t('Your school’s communication history')) ?></p></div></div>
    <?php if ($announcements === []): ?><div class="empty-state compact"><span class="empty-icon">↗</span><p><?= e(t('No announcements yet. Your updates will appear here.')) ?></p></div><?php else: ?>
        <div class="announcement-list"><?php foreach ($announcements as $announcement): $channels = json_decode($announcement['channels'], true) ?: []; ?>
            <article class="announcement-row"><div class="announcement-avatar">↗</div><div class="announcement-content"><div class="announcement-title-row"><h3><?= e($announcement['title']) ?></h3><time><?= e(localized_date($announcement['created_at'])) ?></time></div><p><?= nl2br(e($announcement['message'])) ?></p><?php if ($announcement['attachment_path'] !== null): ?><p><a class="text-link" href="download_announcement.php?id=<?= (int) $announcement['id'] ?>"><?= e(t('Download attached PDF')) ?> ↓</a></p><?php endif; ?><div class="announcement-meta"><span><?= e(t('By')) ?> <?= e($announcement['author_name'] ?? t('Former staff member')) ?></span><?php foreach ($channels as $channel): ?><span class="channel-tag"><?= e(t(strtoupper($channel))) ?></span><?php endforeach; ?><span class="queue-count"><?= (int) $announcement['queued_count'] ?> <?= e(t('queued')) ?></span></div>
                <?php if (current_user()['role'] === 'admin'): ?>
                    <details class="student-edit">
                        <summary class="text-link"><?= e(t('Edit announcement')) ?></summary>
                        <form method="post" action="index.php?page=communications" enctype="multipart/form-data" class="form-stack">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="update_announcement">
                            <input type="hidden" name="announcement_id" value="<?= (int) $announcement['id'] ?>">
                            <label><?= e(t('Announcement title')) ?><input name="title" value="<?= e($announcement['title']) ?>" required maxlength="180"></label>
                            <label><?= e(t('Message')) ?><textarea name="message" rows="4" required><?= e($announcement['message']) ?></textarea></label>
                            <label><?= e(t('PDF attachment (optional)')) ?><input type="file" name="announcement_pdf" accept="application/pdf"><small><?= e(t('Upload one PDF, up to 10 MB. It will be available in the portal and attached to announcement emails.')) ?></small></label>
                            <?php if ($announcement['attachment_path'] !== null): ?><label class="checkbox-row"><input type="checkbox" name="remove_attachment" value="1"> <?= e(t('Remove current PDF attachment')) ?></label><?php endif; ?>
                            <small><?= e(t('Audience and delivery channels cannot be changed after an announcement is created.')) ?></small>
                            <button class="button button-secondary" type="submit"><?= e(t('Save changes')) ?></button>
                        </form>
                    </details>
                    <form method="post" action="index.php?page=communications" onsubmit="return confirm(<?= e(json_encode(t('Delete this announcement and cancel any pending notifications? This cannot be undone.'))) ?>)">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete_announcement">
                        <input type="hidden" name="announcement_id" value="<?= (int) $announcement['id'] ?>">
                        <button class="button button-danger" type="submit"><?= e(t('Delete announcement')) ?></button>
                    </form>
                <?php endif; ?>
            </div></article>
        <?php endforeach; ?></div>
    <?php endif; ?>
</section>
