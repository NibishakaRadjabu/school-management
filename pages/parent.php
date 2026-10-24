<?php
$user = current_user();
$pdo = database();
$guardianQuery = $pdo->prepare(
    'SELECT id, name, email, phone FROM guardians
     WHERE school_id = ? AND user_id = ? ORDER BY id'
);
$guardianQuery->execute([$user['school_id'], $user['id']]);
$guardians = $guardianQuery->fetchAll();

$childrenQuery = $pdo->prepare(
    'SELECT s.id, s.first_name, s.last_name, s.admission_number, s.status, c.name AS class_name,
            (SELECT COUNT(*) FROM attendance a WHERE a.student_id = s.id AND a.school_id = s.school_id) AS days_marked,
            (SELECT COALESCE(SUM(a.status IN ("present", "late")), 0) FROM attendance a WHERE a.student_id = s.id AND a.school_id = s.school_id) AS days_present,
            GREATEST(0, 100 - COALESCE((SELECT SUM(bpd.points_deducted) FROM behavior_point_deductions bpd WHERE bpd.student_id = s.id AND bpd.school_id = s.school_id), 0)) AS behavior_score
     FROM students s LEFT JOIN classes c ON c.id = s.class_id
     WHERE s.school_id = ? AND EXISTS (
         SELECT 1 FROM student_guardians sg JOIN guardians g ON g.id = sg.guardian_id
         WHERE sg.student_id = s.id AND g.user_id = ?
     )
     ORDER BY s.first_name, s.last_name'
);
$childrenQuery->execute([$user['school_id'], $user['id']]);
$children = $childrenQuery->fetchAll();

$behaviorQuery = $pdo->prepare(
    'SELECT bpd.points_deducted, bpd.misconduct, bpd.created_at,
            s.first_name, s.last_name, s.admission_number
     FROM behavior_point_deductions bpd
     JOIN students s ON s.id = bpd.student_id AND s.school_id = bpd.school_id
     WHERE bpd.school_id = ? AND EXISTS (
         SELECT 1 FROM student_guardians sg JOIN guardians g ON g.id = sg.guardian_id
         WHERE sg.student_id = s.id AND g.user_id = ?
     )
     ORDER BY bpd.created_at DESC, bpd.id DESC LIMIT 50'
);
$behaviorQuery->execute([$user['school_id'], $user['id']]);
$behaviorHistory = $behaviorQuery->fetchAll();

$resultsQuery = $pdo->prepare(
    'SELECT r.score, r.remarks, e.name AS exam_name, e.exam_date, e.max_score,
            sub.name AS subject_name, s.first_name, s.last_name, s.admission_number
     FROM student_results r
     JOIN exams e ON e.id = r.exam_id
     JOIN students s ON s.id = r.student_id
     JOIN subjects sub ON sub.id = e.subject_id
     WHERE e.school_id = ? AND EXISTS (
         SELECT 1 FROM student_guardians sg JOIN guardians g ON g.id = sg.guardian_id
         WHERE sg.student_id = s.id AND g.user_id = ?
     )
     ORDER BY e.exam_date DESC, r.id DESC LIMIT 30'
);
$resultsQuery->execute([$user['school_id'], $user['id']]);
$results = $resultsQuery->fetchAll();

$invoicesQuery = $pdo->prepare(
    'SELECT i.description, i.amount, i.due_date, i.status, s.first_name, s.last_name, s.admission_number,
            COALESCE(SUM(p.amount), 0) AS paid
     FROM fee_invoices i
     JOIN students s ON s.id = i.student_id
     LEFT JOIN fee_payments p ON p.invoice_id = i.id
     WHERE i.school_id = ? AND EXISTS (
         SELECT 1 FROM student_guardians sg JOIN guardians g ON g.id = sg.guardian_id
         WHERE sg.student_id = s.id AND g.user_id = ?
     )
     GROUP BY i.id ORDER BY i.due_date IS NULL, i.due_date, i.id DESC LIMIT 50'
);
$invoicesQuery->execute([$user['school_id'], $user['id']]);
$invoices = $invoicesQuery->fetchAll();

$announcementsQuery = $pdo->prepare(
    'SELECT a.id, a.title, a.message, a.attachment_path, a.created_at, u.name AS author_name
     FROM announcements a LEFT JOIN users u ON u.id = a.author_id
     WHERE a.school_id = ? AND a.audience = "all_parents"
     ORDER BY a.created_at DESC LIMIT 30'
);
$announcementsQuery->execute([$user['school_id']]);
$announcements = $announcementsQuery->fetchAll();

$conversationsQuery = $pdo->prepare(
    'SELECT c.id, c.subject, c.updated_at,
            (SELECT m.body FROM parent_school_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS latest_message
     FROM parent_school_conversations c
     WHERE c.school_id = ? AND c.parent_user_id = ?
     ORDER BY c.updated_at DESC LIMIT 20'
);
$conversationsQuery->execute([$user['school_id'], $user['id']]);
$conversations = $conversationsQuery->fetchAll();
$selectedConversation = null;
$requestedConversationId = filter_var($_GET['conversation'] ?? null, FILTER_VALIDATE_INT);
if ($requestedConversationId !== false && $requestedConversationId !== null && $requestedConversationId > 0) {
    $selectedConversationQuery = $pdo->prepare(
        'SELECT id, subject FROM parent_school_conversations WHERE id = ? AND school_id = ? AND parent_user_id = ?'
    );
    $selectedConversationQuery->execute([$requestedConversationId, $user['school_id'], $user['id']]);
    $selectedConversation = $selectedConversationQuery->fetch() ?: null;
} elseif ($conversations !== []) {
    $selectedConversation = ['id' => (int) $conversations[0]['id'], 'subject' => $conversations[0]['subject']];
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
<section class="page-heading"><div><span class="eyebrow"><?= e(t('PARENT PORTAL')) ?></span><h1><?= e(t('Welcome, {{name}}', ['{{name}}' => $user['name']])) ?></h1><p><?= e(t('Your children’s school updates, academic progress, attendance, and fee balances.')) ?></p></div></section>
<section class="content-grid parent-summary">
    <article class="panel form-panel">
        <div class="panel-heading"><div><h2><?= e(t('Linked children')) ?></h2><p><?= e(t('Children associated with your verified parent contact.')) ?></p></div><span class="panel-icon">♧</span></div>
        <?php if ($children === []): ?><div class="empty-state compact"><p><?= e(t('No children are linked yet. Add a child using their admission number and the email registered with the school.')) ?></p></div>
        <?php else: ?><div class="child-list"><?php foreach ($children as $child): ?>
            <article class="child-card"><span class="child-avatar"><?= e(strtoupper(substr($child['first_name'], 0, 1))) ?></span><div><strong><?= e($child['first_name'] . ' ' . $child['last_name']) ?></strong><small><?= e($child['admission_number']) ?> · <?= e($child['class_name'] ?? t('Unassigned')) ?></small></div><span class="status-pill status-<?= e($child['status'] === 'active' ? 'active' : 'inactive') ?>"><?= e(t(ucfirst($child['status']))) ?></span><small class="child-attendance"><?= e(t('Attendance')) ?>: <?= (int) $child['days_present'] ?>/<?= (int) $child['days_marked'] ?></small><small class="child-attendance"><?= e(t('Behavior points')) ?>: <?= (int) $child['behavior_score'] ?>/100</small></article>
        <?php endforeach; ?></div><?php endif; ?>
        <form method="post" action="index.php?page=parent" class="form-stack parent-link-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="link_parent_child">
            <label><?= e(t('Link another child by admission number')) ?><input name="admission_number" required maxlength="50" autocomplete="off"></label>
            <button class="button button-secondary" type="submit"><?= e(t('Link child')) ?> <span>→</span></button>
        </form>
    </article>
    <article class="panel parent-announcements">
        <div class="panel-heading"><div><h2><?= e(t('School announcements')) ?></h2><p><?= e(t('Recent updates from the school')) ?></p></div><span class="panel-icon">↗</span></div>
        <?php if ($announcements === []): ?><div class="empty-state compact"><p><?= e(t('There are no school announcements yet.')) ?></p></div>
        <?php else: ?><div class="parent-announcement-list"><?php foreach ($announcements as $announcement): ?>
            <article class="parent-announcement"><div class="announcement-title-row"><h3><?= e($announcement['title']) ?></h3><time><?= e(localized_date($announcement['created_at'])) ?></time></div><p><?= nl2br(e($announcement['message'])) ?></p><?php if ($announcement['attachment_path'] !== null): ?><p><a class="text-link" href="download_announcement.php?id=<?= (int) $announcement['id'] ?>"><?= e(t('Download attached PDF')) ?> ↓</a></p><?php endif; ?><small><?= e(t('By')) ?> <?= e($announcement['author_name'] ?? t('Former staff member')) ?></small></article>
        <?php endforeach; ?></div><?php endif; ?>
    </article>
</section>
<section class="panel recent-panel" id="behavior-records">
    <div class="panel-heading"><div><h2><?= e(t('Behavior records')) ?></h2><p><?= e(t('Behavior notes and deductions recorded for your children')) ?></p></div></div>
    <?php if ($behaviorHistory === []): ?><div class="empty-state compact"><p><?= e(t('There are no behavior records for your children.')) ?></p></div>
    <?php else: ?><div class="table-wrap"><table><thead><tr><th><?= e(t('STUDENT')) ?></th><th><?= e(t('DATE')) ?></th><th><?= e(t('POINTS DEDUCTED')) ?></th><th><?= e(t('SPECIFIC MISCONDUCT')) ?></th></tr></thead><tbody><?php foreach ($behaviorHistory as $entry): ?><tr><td><?= e($entry['first_name'] . ' ' . $entry['last_name']) ?><small class="cell-secondary"><?= e($entry['admission_number']) ?></small></td><td><?= e(localized_date($entry['created_at'])) ?></td><td><?= (int) $entry['points_deducted'] ?></td><td><?= e($entry['misconduct']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<section class="content-grid" id="contact-school">
    <article class="panel form-panel">
        <div class="panel-heading"><div><h2><?= e(t('Contact the school')) ?></h2><p><?= e(t('Start a private conversation with school staff.')) ?></p></div><span class="panel-icon">✉</span></div>
        <form method="post" action="index.php?page=parent" class="form-stack">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create_parent_conversation">
            <label><?= e(t('Subject')) ?><input name="subject" required maxlength="180"></label>
            <label><?= e(t('Message')) ?><textarea name="message" required maxlength="4000" rows="4"></textarea></label>
            <button class="button button-primary" type="submit"><?= e(t('Send message')) ?> <span>→</span></button>
        </form>
    </article>
    <article class="panel form-panel">
        <div class="panel-heading"><div><h2><?= e(t('Your conversations')) ?></h2><p><?= e(t('Messages between you and the school')) ?></p></div><span class="panel-icon">↗</span></div>
        <?php if ($conversations === []): ?><div class="empty-state compact"><p><?= e(t('You have not started a conversation with the school yet.')) ?></p></div>
        <?php else: ?><div class="announcement-list"><?php foreach ($conversations as $conversation): ?>
            <a class="announcement-row" href="index.php?page=parent&amp;conversation=<?= (int) $conversation['id'] ?>"<?= $selectedConversation !== null && (int) $selectedConversation['id'] === (int) $conversation['id'] ? ' aria-current="true"' : '' ?>><div class="announcement-avatar">↗</div><div class="announcement-content"><div class="announcement-title-row"><h3><?= e($conversation['subject']) ?></h3><time><?= e(localized_date($conversation['updated_at'])) ?></time></div><p><?= e($conversation['latest_message'] ?? '') ?></p></div></a>
        <?php endforeach; ?></div><?php endif; ?>
        <?php if ($selectedConversation !== null): ?>
            <div class="parent-announcement-list"><?php foreach ($conversationMessages as $message): ?>
                <article class="parent-announcement"><div class="announcement-title-row"><strong><?= e($message['sender_name'] ?? t('Former staff member')) ?></strong><time><?= e(localized_date($message['created_at'])) ?></time></div><p><?= nl2br(e($message['body'])) ?></p></article>
            <?php endforeach; ?></div>
            <form method="post" action="index.php?page=parent&amp;conversation=<?= (int) $selectedConversation['id'] ?>" class="form-stack">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reply_parent_conversation"><input type="hidden" name="conversation_id" value="<?= (int) $selectedConversation['id'] ?>">
                <label><?= e(t('Reply')) ?><textarea name="message" required maxlength="4000" rows="3"></textarea></label>
                <button class="button button-secondary" type="submit"><?= e(t('Send reply')) ?> <span>→</span></button>
            </form>
        <?php endif; ?>
    </article>
</section>
<section class="panel recent-panel" id="student-marks">
    <div class="panel-heading"><div><h2><?= e(t('Student marks')) ?></h2><p><?= e(t('Latest recorded exam results for your children')) ?></p></div></div>
    <?php if ($results === []): ?><div class="empty-state compact"><p><?= e(t('No exam marks have been recorded for your linked children yet.')) ?></p></div>
    <?php else: ?><div class="table-wrap"><table><thead><tr><th><?= e(t('STUDENT')) ?></th><th><?= e(t('EXAM')) ?></th><th><?= e(t('SUBJECT')) ?></th><th><?= e(t('DATE')) ?></th><th><?= e(t('SCORE')) ?></th><th><?= e(t('REMARK')) ?></th></tr></thead><tbody><?php foreach ($results as $result): ?><tr><td><?= e($result['first_name'] . ' ' . $result['last_name']) ?><small class="cell-secondary"><?= e($result['admission_number']) ?></small></td><td><?= e($result['exam_name']) ?></td><td><?= e($result['subject_name']) ?></td><td><?= e(localized_date($result['exam_date'])) ?></td><td><strong><?= e((string) $result['score']) ?> / <?= e((string) $result['max_score']) ?></strong></td><td><?= e($result['remarks'] ?? '—') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<section class="panel recent-panel" id="fee-balances">
    <div class="panel-heading"><div><h2><?= e(t('Fee balances')) ?></h2><p><?= e(t('Invoices and payments for your linked children')) ?></p></div></div>
    <?php if ($invoices === []): ?><div class="empty-state compact"><p><?= e(t('There are no fee invoices for your linked children.')) ?></p></div>
    <?php else: ?><div class="table-wrap"><table><thead><tr><th><?= e(t('STUDENT')) ?></th><th><?= e(t('DESCRIPTION')) ?></th><th><?= e(t('AMOUNT')) ?></th><th><?= e(t('PAID')) ?></th><th><?= e(t('BALANCE')) ?></th><th><?= e(t('DUE DATE')) ?></th><th><?= e(t('STATUS')) ?></th></tr></thead><tbody><?php foreach ($invoices as $invoice): ?><tr><td><?= e($invoice['first_name'] . ' ' . $invoice['last_name']) ?><small class="cell-secondary"><?= e($invoice['admission_number']) ?></small></td><td><?= e($invoice['description']) ?></td><td><?= number_format((float) $invoice['amount'], 0) ?> RWF</td><td><?= number_format((float) $invoice['paid'], 0) ?> RWF</td><td><strong><?= number_format(max(0, (float) $invoice['amount'] - (float) $invoice['paid']), 0) ?> RWF</strong></td><td><?= $invoice['due_date'] ? e(localized_date($invoice['due_date'])) : '—' ?></td><td><span class="status-pill status-<?= e($invoice['status']) ?>"><?= e(t(ucfirst($invoice['status']))) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
