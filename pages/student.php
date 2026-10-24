<?php
$user = current_user();
$pdo = database();
$studentQuery = $pdo->prepare(
    'SELECT s.id, s.first_name, s.last_name, s.admission_number, s.status, c.name AS class_name,
            GREATEST(0, 100 - COALESCE((
                SELECT SUM(bpd.points_deducted)
                FROM behavior_point_deductions bpd
                WHERE bpd.student_id = s.id AND bpd.school_id = s.school_id
            ), 0)) AS behavior_score
     FROM students s LEFT JOIN classes c ON c.id = s.class_id
     WHERE s.school_id = ? AND s.user_id = ?
     LIMIT 1'
);
$studentQuery->execute([$user['school_id'], $user['id']]);
$student = $studentQuery->fetch() ?: null;

$behaviorHistory = [];
$announcements = [];
if ($student !== null) {
    $behaviorQuery = $pdo->prepare(
        'SELECT points_deducted, misconduct, created_at
         FROM behavior_point_deductions
         WHERE school_id = ? AND student_id = ?
         ORDER BY created_at DESC, id DESC LIMIT 50'
    );
    $behaviorQuery->execute([$user['school_id'], $student['id']]);
    $behaviorHistory = $behaviorQuery->fetchAll();

    $announcementsQuery = $pdo->prepare(
        'SELECT a.id, a.title, a.message, a.audience, a.attachment_path, a.created_at, u.name AS author_name
         FROM announcements a LEFT JOIN users u ON u.id = a.author_id
         WHERE a.school_id = ? AND a.audience IN ("all_parents", "all_students")
         ORDER BY a.created_at DESC LIMIT 30'
    );
    $announcementsQuery->execute([$user['school_id']]);
    $announcements = $announcementsQuery->fetchAll();
}
?>
<section class="page-heading">
    <div>
        <span class="eyebrow"><?= e(t('STUDENT PORTAL')) ?></span>
        <h1><?= e(t('Welcome, {{name}}', ['{{name}}' => $student !== null ? $student['first_name'] : $user['name']])) ?></h1>
        <p><?= e(t('Your behavior record and latest school announcements.')) ?></p>
    </div>
</section>

<?php if ($student === null): ?>
    <section class="panel empty-state">
        <p><?= e(t('Your student record could not be found. Please contact the school office.')) ?></p>
    </section>
<?php else: ?>
    <section class="content-grid">
        <article class="panel form-panel">
            <div class="panel-heading">
                <div>
                    <h2><?= e(t('My student details')) ?></h2>
                    <p><?= e($student['admission_number']) ?> · <?= e($student['class_name'] ?? t('Unassigned')) ?></p>
                </div>
                <span class="status-pill status-<?= e($student['status']) ?>"><?= e(t(ucfirst($student['status']))) ?></span>
            </div>
            <div>
                <small class="stat-label"><?= e(t('Remaining behavior points')) ?></small>
                <strong class="stat-value"><?= (int) $student['behavior_score'] ?><small> / 100</small></strong>
                <p class="form-note"><?= e(t('Your remaining behavior points are calculated from recorded deductions.')) ?></p>
            </div>
        </article>
        <article class="panel form-panel">
            <div class="panel-heading">
                <div>
                    <h2><?= e(t('Behavior records')) ?></h2>
                    <p><?= e(t('All recorded behavior deductions for you.')) ?></p>
                </div>
                <span class="panel-icon">✓</span>
            </div>
            <?php if ($behaviorHistory === []): ?>
                <div class="empty-state compact"><p><?= e(t('No behavior deductions have been recorded for you.')) ?></p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th><?= e(t('DATE')) ?></th><th><?= e(t('POINTS DEDUCTED')) ?></th><th><?= e(t('SPECIFIC MISCONDUCT')) ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($behaviorHistory as $record): ?>
                            <tr>
                                <td><?= e(localized_date($record['created_at'])) ?></td>
                                <td><?= (int) $record['points_deducted'] ?></td>
                                <td><?= e($record['misconduct']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
    </section>

    <section class="panel form-panel">
        <div class="panel-heading">
            <div>
                <h2><?= e(t('School announcements')) ?></h2>
                <p><?= e(t('Recent updates from the school')) ?></p>
            </div>
            <span class="panel-icon">↗</span>
        </div>
        <?php if ($announcements === []): ?>
            <div class="empty-state compact"><p><?= e(t('There are no school announcements yet.')) ?></p></div>
        <?php else: ?>
            <div class="parent-announcement-list">
                <?php foreach ($announcements as $announcement): ?>
                    <article class="parent-announcement">
                        <div class="announcement-title-row">
                            <h3><?= e($announcement['title']) ?></h3>
                            <time><?= e(localized_date($announcement['created_at'])) ?></time>
                        </div>
                        <p><?= nl2br(e($announcement['message'])) ?></p>
                        <?php if ($announcement['attachment_path'] !== null): ?><p><a class="text-link" href="download_announcement.php?id=<?= (int) $announcement['id'] ?>"><?= e(t('Download attached PDF')) ?> ↓</a></p><?php endif; ?>
                        <small><?= e($announcement['author_name'] ?? t('School')) ?> · <?= e(t($announcement['audience'] === 'all_students' ? 'For students' : 'For parents and guardians')) ?></small>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
