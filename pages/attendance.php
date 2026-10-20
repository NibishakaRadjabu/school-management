<?php
$schoolId = current_user()['school_id'];
$pdo = database();
$date = (string) ($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}
$studentsQuery = $pdo->prepare(
    'SELECT s.id, s.first_name, s.last_name, s.admission_number, c.name AS class_name, a.status
     FROM students s LEFT JOIN classes c ON c.id = s.class_id
     LEFT JOIN attendance a ON a.student_id = s.id AND a.attendance_date = ?
     WHERE s.school_id = ? AND s.status = "active" ORDER BY c.name, s.last_name, s.first_name'
);
$studentsQuery->execute([$date, $schoolId]);
$students = $studentsQuery->fetchAll();
?>
<section class="page-heading"><div><span class="eyebrow"><?= e(t('DAILY SCHOOL LIFE')) ?></span><h1><?= e(t('Attendance')) ?></h1><p><?= e(t('Take a quick register and keep families in the loop.')) ?></p></div></section>
<section class="panel recent-panel">
    <div class="panel-heading"><div><h2><?= e(t('Daily register')) ?></h2><p><?= e(localized_date($date, 'full')) ?></p></div>
        <form method="get" class="date-filter"><input type="hidden" name="page" value="attendance"><input type="hidden" name="lang" value="<?= e(current_language()) ?>"><label for="attendance-date"><?= e(t('Attendance date')) ?></label><input id="attendance-date" type="date" name="date" value="<?= e($date) ?>"><button class="button button-secondary button-small" type="submit"><?= e(t('View date')) ?></button></form>
    </div>
    <?php if ($students === []): ?><div class="empty-state"><span class="empty-icon">◷</span><h3><?= e(t('No students to mark yet')) ?></h3><p><?= e(t('Add students to your register before taking attendance.')) ?></p><a class="button button-secondary" href="index.php?page=students#add-student"><?= e(t('Add a student')) ?></a></div><?php else: ?>
        <form method="post" action="index.php?page=attendance">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_attendance"><input type="hidden" name="attendance_date" value="<?= e($date) ?>">
            <div class="table-wrap"><table><thead><tr><th><?= e(t('STUDENT')) ?></th><th><?= e(t('ADMISSION NO.')) ?></th><th><?= e(t('CLASS')) ?></th><th><?= e(t('Attendance')) ?></th></tr></thead><tbody>
                <?php foreach ($students as $student): ?><tr><td><span class="student-cell"><span class="table-avatar"><?= e(strtoupper(substr($student['first_name'], 0, 1))) ?></span><strong><?= e($student['first_name'] . ' ' . $student['last_name']) ?></strong></span></td><td><?= e($student['admission_number']) ?></td><td><?= e($student['class_name'] ?? t('Unassigned')) ?></td><td><select class="attendance-select attendance-<?= e($student['status'] ?? 'present') ?>" name="status[<?= (int) $student['id'] ?>]"><option value="present"<?= ($student['status'] ?? 'present') === 'present' ? ' selected' : '' ?>><?= e(t('Present')) ?></option><option value="absent"<?= $student['status'] === 'absent' ? ' selected' : '' ?>><?= e(t('Absent')) ?></option><option value="late"<?= $student['status'] === 'late' ? ' selected' : '' ?>><?= e(t('Late')) ?></option><option value="excused"<?= $student['status'] === 'excused' ? ' selected' : '' ?>><?= e(t('Excused')) ?></option></select></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <div class="table-actions"><span class="muted-copy"><?= count($students) ?> <?= e(t('active students')) ?></span><button class="button button-primary" type="submit"><?= e(t('Save attendance')) ?> <span>→</span></button></div>
        </form>
    <?php endif; ?>
</section>
<div class="info-banner"><span>ⓘ</span><p><?= e(t('Attendance is saved to the register. Parent absence alerts can be enabled after an SMS or email provider is connected.')) ?></p></div>
