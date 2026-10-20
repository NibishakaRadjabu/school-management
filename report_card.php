<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/layout.php';
require_page_access('academics');

$studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
$studentQuery = database()->prepare(
    'SELECT s.id, s.first_name, s.last_name, s.admission_number, s.enrollment_date,
            c.name AS class_name, sec.name AS section_name
     FROM students s LEFT JOIN classes c ON c.id = s.class_id
     LEFT JOIN sections sec ON sec.id = c.section_id
     WHERE s.id = ? AND s.school_id = ?'
);
$studentQuery->execute([$studentId ?: 0, current_user()['school_id']]);
$student = $studentQuery->fetch();
if (!$student) {
    http_response_code(404);
    flash('That student could not be found.', 'error');
    redirect('index.php?page=students');
}

$resultQuery = database()->prepare(
    'SELECT e.name AS exam_name, e.exam_date, e.max_score, sub.name AS subject_name, r.score, r.remarks
     FROM student_results r JOIN exams e ON e.id = r.exam_id
     JOIN subjects sub ON sub.id = e.subject_id
     WHERE r.student_id = ? AND e.school_id = ?
     ORDER BY e.exam_date DESC, sub.name, e.name'
);
$resultQuery->execute([$student['id'], current_user()['school_id']]);
$results = $resultQuery->fetchAll();
$attendanceQuery = database()->prepare(
    'SELECT COUNT(*) AS total, SUM(status IN ("present", "late")) AS present_count,
            SUM(status = "absent") AS absent_count
     FROM attendance WHERE student_id = ? AND school_id = ?'
);
$attendanceQuery->execute([$student['id'], current_user()['school_id']]);
$attendance = $attendanceQuery->fetch();
?>
<?php render_header('Report card', 'academics'); ?>
<section class="page-heading no-print">
    <div><span class="eyebrow"><?= e(t('STUDENT ACADEMIC RECORD')) ?></span><h1><?= e(t('Report card')) ?></h1><p><?= e($student['first_name'] . ' ' . $student['last_name']) ?> · <?= e($student['admission_number']) ?></p></div>
    <button class="button button-primary" type="button" onclick="window.print()"><?= e(t('Print report card')) ?> <span>↗</span></button>
</section>
<section class="panel report-card">
    <div class="report-brand"><span class="brand-mark">S<span>+</span></span><div><strong><?= e(current_user()['school_name']) ?></strong><small><?= e(t('Student report card')) ?></small></div><time><?= e(localized_date(time(), 'long')) ?></time></div>
    <div class="report-student-grid">
        <div><small><?= e(t('STUDENT NAME')) ?></small><strong><?= e($student['first_name'] . ' ' . $student['last_name']) ?></strong></div>
        <div><small><?= e(t('ADMISSION NUMBER')) ?></small><strong><?= e($student['admission_number']) ?></strong></div>
        <div><small><?= e(t('CLASS')) ?></small><strong><?= e($student['class_name'] ?? t('Unassigned')) ?></strong></div>
        <div><small><?= e(t('Section')) ?></small><strong><?= e($student['section_name'] ?? '—') ?></strong></div>
    </div>
    <div class="report-section-heading"><h2><?= e(t('Academic results')) ?></h2><p><?= count($results) ?> <?= e(t('recorded assessments')) ?></p></div>
    <?php if ($results === []): ?><div class="empty-state compact"><p><?= e(t('No exam results have been recorded for this student yet.')) ?></p></div><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th><?= e(t('SUBJECT')) ?></th><th><?= e(t('ASSESSMENT')) ?></th><th><?= e(t('DATE')) ?></th><th><?= e(t('MARK')) ?></th><th><?= e(t('RESULT')) ?></th><th><?= e(t('REMARK')) ?></th></tr></thead><tbody>
            <?php foreach ($results as $result): $percentage = (float) $result['max_score'] > 0 ? round(((float) $result['score'] / (float) $result['max_score']) * 100, 1) : 0; ?>
                <tr><td><strong><?= e($result['subject_name']) ?></strong></td><td><?= e($result['exam_name']) ?></td><td><?= e(localized_date($result['exam_date'])) ?></td><td><?= e((string) $result['score']) ?> / <?= e((string) $result['max_score']) ?></td><td><span class="result-score"><?= number_format($percentage, 1) ?>%</span></td><td><?= e($result['remarks'] ?? '—') ?></td></tr>
            <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
    <div class="report-section-heading attendance-report-heading"><h2><?= e(t('Attendance summary')) ?></h2><p><?= e(t('Recorded school days')) ?></p></div>
    <div class="report-attendance">
        <div><small><?= e(t('Days marked')) ?></small><strong><?= number_format((int) $attendance['total']) ?></strong></div>
        <div><small><?= e(t('Present / late')) ?></small><strong><?= number_format((int) $attendance['present_count']) ?></strong></div>
        <div><small><?= e(t('Absent')) ?></small><strong><?= number_format((int) $attendance['absent_count']) ?></strong></div>
        <div><small><?= e(t('Enrollment date')) ?></small><strong><?= e(localized_date($student['enrollment_date'])) ?></strong></div>
    </div>
    <div class="report-signatures"><span><?= e(t('Class teacher')) ?> <i></i></span><span><?= e(t('Headmaster')) ?> <i></i></span></div>
</section>
<?php render_footer(); ?>
