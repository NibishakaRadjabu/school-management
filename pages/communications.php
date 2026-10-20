<?php
$schoolId = current_user()['school_id'];
$pdo = database();
$guardianQuery = $pdo->prepare('SELECT COUNT(DISTINCT g.id) FROM guardians g JOIN student_guardians sg ON sg.guardian_id = g.id WHERE g.school_id = ?');
$guardianQuery->execute([$schoolId]);
$guardianCount = (int) $guardianQuery->fetchColumn();
$announcementQuery = $pdo->prepare(
    'SELECT a.id, a.title, a.message, a.channels, a.created_at, u.name AS author_name,
            (SELECT COUNT(*) FROM notification_outbox o WHERE o.announcement_id = a.id AND o.school_id = a.school_id) AS queued_count
     FROM announcements a LEFT JOIN users u ON u.id = a.author_id WHERE a.school_id = ? ORDER BY a.created_at DESC LIMIT 20'
);
$announcementQuery->execute([$schoolId]);
$announcements = $announcementQuery->fetchAll();
?>
<section class="page-heading"><div><span class="eyebrow"><?= e(t('STAY CONNECTED')) ?></span><h1><?= e(t('Communications')) ?></h1><p><?= e(t('Share important updates with parents and guardians.')) ?></p></div></section>
<div class="info-banner"><span>ⓘ</span><p><?= e(t('Messages are added to your delivery queue. Connect an SMS gateway, Gmail/SMTP account, or mobile app provider before messages can be delivered.')) ?></p></div>
<section class="content-grid">
    <article class="panel form-panel announcement-form-panel">
        <div class="panel-heading"><div><h2><?= e(t('New announcement')) ?></h2><p><?= e(t('Write once and choose where it should go.')) ?></p></div><span class="panel-icon">↗</span></div>
        <form method="post" action="index.php?page=communications" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create_announcement">
            <label><?= e(t('Announcement title')) ?><input name="title" required maxlength="180" placeholder="<?= e(t('e.g. Parents meeting this Saturday')) ?>"></label>
            <label><?= e(t('Message')) ?><textarea name="message" rows="5" required placeholder="<?= e(t('Write your message to parents and guardians...')) ?>"></textarea></label>
            <fieldset class="channel-fieldset"><legend><?= e(t('Delivery channels')) ?></legend><div class="channel-options">
                <label class="channel-option"><input type="checkbox" name="channels[]" value="sms"><span class="channel-symbol channel-sms">SMS</span><span><strong><?= e(t('Text message')) ?></strong><small><?= e(t('Requires an SMS provider')) ?></small></span></label>
                <label class="channel-option"><input type="checkbox" name="channels[]" value="email"><span class="channel-symbol channel-email">✉</span><span><strong><?= e(t('Email')) ?></strong><small><?= e(t('Requires Gmail / SMTP setup')) ?></small></span></label>
                <label class="channel-option"><input type="checkbox" name="channels[]" value="app"><span class="channel-symbol channel-app">▣</span><span><strong><?= e(t('Mobile app')) ?></strong><small><?= e(t('Requires an app notification service')) ?></small></span></label>
            </div></fieldset>
            <div class="audience-note"><span>♧</span><span><strong><?= e(t('All parents & guardians')) ?></strong><small><?= number_format($guardianCount) ?> <?= e(t('family contacts on file')) ?></small></span></div>
            <button class="button button-primary" type="submit"><?= e(t('Save announcement')) ?> <span>→</span></button>
        </form>
    </article>
    <article class="panel form-panel communication-summary">
        <div class="panel-heading"><div><h2><?= e(t('Your channels')) ?></h2><p><?= e(t('Delivery setup overview')) ?></p></div><span class="panel-icon">✳</span></div>
        <div class="channel-status"><span class="channel-symbol channel-sms">SMS</span><span><strong><?= e(t('Text messages')) ?></strong><small><?= e(t('Provider not connected')) ?></small></span><span class="setup-status"><?= e(t('Setup needed')) ?></span></div>
        <div class="channel-status"><span class="channel-symbol channel-email">✉</span><span><strong><?= e(t('Email')) ?></strong><small><?= e(t('Gmail / SMTP not connected')) ?></small></span><span class="setup-status"><?= e(t('Setup needed')) ?></span></div>
        <div class="channel-status"><span class="channel-symbol channel-app">▣</span><span><strong><?= e(t('Mobile app')) ?></strong><small><?= e(t('App service not connected')) ?></small></span><span class="setup-status"><?= e(t('Setup needed')) ?></span></div>
        <div class="integration-note"><strong><?= e(t('Not sending just yet')) ?></strong><p><?= e(t('Connect your provider credentials in the server configuration before turning on live delivery. Your announcements are safely recorded here in the meantime.')) ?></p></div>
    </article>
</section>
<section class="panel recent-panel">
    <div class="panel-heading"><div><h2><?= e(t('Recent announcements')) ?></h2><p><?= e(t('Your school’s communication history')) ?></p></div></div>
    <?php if ($announcements === []): ?><div class="empty-state compact"><span class="empty-icon">↗</span><p><?= e(t('No announcements yet. Your updates will appear here.')) ?></p></div><?php else: ?>
        <div class="announcement-list"><?php foreach ($announcements as $announcement): $channels = json_decode($announcement['channels'], true) ?: []; ?>
            <article class="announcement-row"><div class="announcement-avatar">↗</div><div class="announcement-content"><div class="announcement-title-row"><h3><?= e($announcement['title']) ?></h3><time><?= e(localized_date($announcement['created_at'])) ?></time></div><p><?= nl2br(e($announcement['message'])) ?></p><div class="announcement-meta"><span><?= e(t('By')) ?> <?= e($announcement['author_name'] ?? t('Former staff member')) ?></span><?php foreach ($channels as $channel): ?><span class="channel-tag"><?= e(t(strtoupper($channel))) ?></span><?php endforeach; ?><span class="queue-count"><?= (int) $announcement['queued_count'] ?> <?= e(t('queued')) ?></span></div></div></article>
        <?php endforeach; ?></div>
    <?php endif; ?>
</section>
