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
            (SELECT COALESCE(SUM(a.status IN ("present", "late")), 0) FROM attendance a WHERE a.student_id = s.id AND a.school_id = s.school_id) AS days_present
     FROM students s LEFT JOIN classes c ON c.id = s.class_id
     WHERE s.school_id = ? AND EXISTS (
         SELECT 1 FROM student_guardians sg JOIN guardians g ON g.id = sg.guardian_id
         WHERE sg.student_id = s.id AND g.user_id = ?
     )
     ORDER BY s.first_name, s.last_name'
);
$childrenQuery->execute([$user['school_id'], $user['id']]);
$children = $childrenQuery->fetchAll();

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
    'SELECT a.title, a.message, a.created_at, u.name AS author_name
     FROM announcements a LEFT JOIN users u ON u.id = a.author_id
     WHERE a.school_id = ? AND a.audience = "all_parents"
     ORDER BY a.created_at DESC LIMIT 30'
);
$announcementsQuery->execute([$user['school_id']]);
$announcements = $announcementsQuery->fetchAll();
?>
<section class="page-heading"><div><span class="eyebrow"><?= e(t('PARENT PORTAL')) ?></span><h1><?= e(t('Welcome, {{name}}', ['{{name}}' => $user['name']])) ?></h1><p><?= e(t('Your children’s school updates, academic progress, attendance, and fee balances.')) ?></p></div></section>
<section class="content-grid parent-summary">
    <article class="panel form-panel">
        <div class="panel-heading"><div><h2><?= e(t('Linked children')) ?></h2><p><?= e(t('Children associated with your verified parent contact.')) ?></p></div><span class="panel-icon">♧</span></div>
        <?php if ($children === []): ?><div class="empty-state compact"><p><?= e(t('No children are linked yet. Add a child using their admission number and the email registered with the school.')) ?></p></div>
        <?php else: ?><div class="child-list"><?php foreach ($children as $child): ?>
            <article class="child-card"><span class="child-avatar"><?= e(strtoupper(substr($child['first_name'], 0, 1))) ?></span><div><strong><?= e($child['first_name'] . ' ' . $child['last_name']) ?></strong><small><?= e($child['admission_number']) ?> · <?= e($child['class_name'] ?? t('Unassigned')) ?></small></div><span class="status-pill status-<?= e($child['status'] === 'active' ? 'active' : 'inactive') ?>"><?= e(t(ucfirst($child['status']))) ?></span><small class="child-attendance"><?= e(t('Attendance')) ?>: <?= (int) $child['days_present'] ?>/<?= (int) $child['days_marked'] ?></small></article>
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
            <article class="parent-announcement"><div class="announcement-title-row"><h3><?= e($announcement['title']) ?></h3><time><?= e(localized_date($announcement['created_at'])) ?></time></div><p><?= nl2br(e($announcement['message'])) ?></p><small><?= e(t('By')) ?> <?= e($announcement['author_name'] ?? t('Former staff member')) ?></small></article>
        <?php endforeach; ?></div><?php endif; ?>
    </article>
</section>
<section class="panel recent-panel">
    <div class="panel-heading"><div><h2><?= e(t('Student marks')) ?></h2><p><?= e(t('Latest recorded exam results for your children')) ?></p></div></div>
    <?php if ($results === []): ?><div class="empty-state compact"><p><?= e(t('No exam marks have been recorded for your linked children yet.')) ?></p></div>
    <?php else: ?><div class="table-wrap"><table><thead><tr><th><?= e(t('STUDENT')) ?></th><th><?= e(t('EXAM')) ?></th><th><?= e(t('SUBJECT')) ?></th><th><?= e(t('DATE')) ?></th><th><?= e(t('SCORE')) ?></th><th><?= e(t('REMARK')) ?></th></tr></thead><tbody><?php foreach ($results as $result): ?><tr><td><?= e($result['first_name'] . ' ' . $result['last_name']) ?><small class="cell-secondary"><?= e($result['admission_number']) ?></small></td><td><?= e($result['exam_name']) ?></td><td><?= e($result['subject_name']) ?></td><td><?= e(localized_date($result['exam_date'])) ?></td><td><strong><?= e((string) $result['score']) ?> / <?= e((string) $result['max_score']) ?></strong></td><td><?= e($result['remarks'] ?? '—') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<section class="panel recent-panel">
    <div class="panel-heading"><div><h2><?= e(t('Fee balances')) ?></h2><p><?= e(t('Invoices and payments for your linked children')) ?></p></div></div>
    <?php if ($invoices === []): ?><div class="empty-state compact"><p><?= e(t('There are no fee invoices for your linked children.')) ?></p></div>
    <?php else: ?><div class="table-wrap"><table><thead><tr><th><?= e(t('STUDENT')) ?></th><th><?= e(t('DESCRIPTION')) ?></th><th><?= e(t('AMOUNT')) ?></th><th><?= e(t('PAID')) ?></th><th><?= e(t('BALANCE')) ?></th><th><?= e(t('DUE DATE')) ?></th><th><?= e(t('STATUS')) ?></th></tr></thead><tbody><?php foreach ($invoices as $invoice): ?><tr><td><?= e($invoice['first_name'] . ' ' . $invoice['last_name']) ?><small class="cell-secondary"><?= e($invoice['admission_number']) ?></small></td><td><?= e($invoice['description']) ?></td><td><?= number_format((float) $invoice['amount'], 0) ?> RWF</td><td><?= number_format((float) $invoice['paid'], 0) ?> RWF</td><td><strong><?= number_format(max(0, (float) $invoice['amount'] - (float) $invoice['paid']), 0) ?> RWF</strong></td><td><?= $invoice['due_date'] ? e(localized_date($invoice['due_date'])) : '—' ?></td><td><span class="status-pill status-<?= e($invoice['status']) ?>"><?= e(t(ucfirst($invoice['status']))) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
