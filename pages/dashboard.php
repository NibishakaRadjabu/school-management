<?php
$schoolId = current_user()['school_id'];
$pdo = database();

$metrics = [];
$queries = [];
if (can_access_page('students')) {
    $queries['students'] = ['SELECT COUNT(*) FROM students WHERE school_id = ? AND status = "active"', 'accent-blue'];
}
if (can_access_page('teachers')) {
    $queries['teachers'] = ['SELECT COUNT(*) FROM teachers WHERE school_id = ? AND status = "active"', 'accent-violet'];
}
if (can_access_page('classes')) {
    $queries['classes'] = ['SELECT COUNT(*) FROM classes WHERE school_id = ?', 'accent-orange'];
}
if (can_access_page('fees')) {
    $queries['fees'] = ['SELECT COALESCE(SUM(p.amount), 0) FROM fee_payments p JOIN fee_invoices i ON i.id = p.invoice_id WHERE i.school_id = ?', 'accent-green'];
}
if (can_access_page('communications')) {
    $queries['parents'] = [
        'SELECT COUNT(DISTINCT g.id) FROM guardians g JOIN student_guardians sg ON sg.guardian_id = g.id WHERE g.school_id = ?',
        'accent-orange',
    ];
}
foreach ($queries as $key => [$sql, $class]) {
    $statement = $pdo->prepare($sql);
    $statement->execute([$schoolId]);
    $metrics[$key] = ['value' => $statement->fetchColumn(), 'class' => $class];
}

if (can_access_page('classes')) {
    $sections = $pdo->prepare('SELECT sec.name, COUNT(st.id) AS total FROM sections sec LEFT JOIN classes c ON c.section_id = sec.id LEFT JOIN students st ON st.class_id = c.id AND st.school_id = sec.school_id AND st.status = "active" WHERE sec.school_id = ? GROUP BY sec.id ORDER BY sec.id');
    $sections->execute([$schoolId]);
    $sectionCounts = $sections->fetchAll();
}

if (can_access_page('attendance')) {
    $attendance = $pdo->prepare(
        'SELECT COUNT(*) AS marked, SUM(status IN ("present", "late")) AS present_count
         FROM attendance WHERE school_id = ? AND attendance_date = CURDATE()'
    );
    $attendance->execute([$schoolId]);
    $attendanceToday = $attendance->fetch();
}

if (can_access_page('students')) {
    $recent = $pdo->prepare(
        'SELECT s.id, s.admission_number, s.first_name, s.last_name, c.name AS class_name, s.enrollment_date
         FROM students s LEFT JOIN classes c ON c.id = s.class_id
         WHERE s.school_id = ? ORDER BY s.created_at DESC LIMIT 5'
    );
    $recent->execute([$schoolId]);
    $recentStudents = $recent->fetchAll();
}
$dashboardPanelCount = (int) can_access_page('classes') + (int) can_access_page('attendance');
$dashboardStatsClass = count($metrics) === 5
    ? ' stat-grid-five'
    : (count($metrics) === 1 ? ' stat-grid-single' : (count($metrics) === 2 ? ' stat-grid-two' : (isset($metrics['fees']) ? '' : ' stat-grid-three')));
?>
<section class="page-heading">
    <div><span class="eyebrow"><?= e(t('YOUR SCHOOL AT A GLANCE')) ?></span><h1><?= e(t('A good day to make progress.')) ?></h1><p><?= e(t('Here’s what’s happening across your school.')) ?> <?= e(current_user()['school_name']) ?>.</p></div>
    <?php if (can_access_page('students')): ?>
    <a class="button button-primary" href="index.php?page=students#add-student"><span>＋</span> <?= e(t('Add a student')) ?></a>
    <?php endif; ?>
</section>
<section class="stat-grid<?= $dashboardStatsClass ?>" aria-label="<?= e(t('School overview')) ?>">
    <?php if (isset($metrics['students'])): ?><article class="stat-card"><div class="stat-top"><span class="stat-label"><?= e(t('Active students')) ?></span><span class="stat-icon accent-blue">♧</span></div><strong class="stat-value"><?= number_format((int) $metrics['students']['value']) ?></strong><div class="stat-foot"><span class="stat-trend neutral"><?= e(t('Across your school')) ?></span></div></article><?php endif; ?>
    <?php if (isset($metrics['teachers'])): ?><article class="stat-card"><div class="stat-top"><span class="stat-label"><?= e(t('Teaching staff')) ?></span><span class="stat-icon accent-violet">♙</span></div><strong class="stat-value"><?= number_format((int) $metrics['teachers']['value']) ?></strong><div class="stat-foot"><span class="stat-trend neutral"><?= e(t('Active staff members')) ?></span></div></article><?php endif; ?>
    <?php if (isset($metrics['classes'])): ?><article class="stat-card"><div class="stat-top"><span class="stat-label"><?= e(t('Classes')) ?></span><span class="stat-icon accent-orange">▦</span></div><strong class="stat-value"><?= number_format((int) $metrics['classes']['value']) ?></strong><div class="stat-foot"><span class="stat-trend neutral"><?= e(t('Across all sections')) ?></span></div></article><?php endif; ?>
    <?php if (isset($metrics['parents'])): ?><article class="stat-card"><div class="stat-top"><span class="stat-label"><?= e(t('Parents')) ?></span><span class="stat-icon accent-orange">♧</span></div><strong class="stat-value"><?= number_format((int) $metrics['parents']['value']) ?></strong><div class="stat-foot"><span class="stat-trend neutral"><?= e(t('Linked parent contacts')) ?></span></div></article><?php endif; ?>
    <?php if (isset($metrics['fees'])): ?><article class="stat-card"><div class="stat-top"><span class="stat-label"><?= e(t('Fees collected')) ?></span><span class="stat-icon accent-green">◇</span></div><strong class="stat-value money"><?= number_format((float) $metrics['fees']['value'], 0) ?><small> RWF</small></strong><div class="stat-foot"><span class="stat-trend neutral"><?= e(t('Payments recorded')) ?></span></div></article><?php endif; ?>
</section>
<?php if (can_access_page('students')): ?>
<section class="dashboard-grid<?= $dashboardPanelCount === 1 ? ' dashboard-grid-single' : '' ?>">
    <?php if (can_access_page('classes')): ?>
    <article class="panel section-panel">
        <div class="panel-heading"><div><h2><?= e(t('Students by section')) ?></h2><p><?= e(t('A quick look at your school community')) ?></p></div><span class="panel-icon">▤</span></div>
        <div class="section-list">
            <?php if ($sectionCounts === []): ?><div class="empty-state compact"><span class="empty-icon">▦</span><p><?= e(t('Add a section to start organising your classes.')) ?></p><a href="index.php?page=classes"><?= e(t('Set up classes')) ?> →</a></div><?php endif; ?>
            <?php foreach ($sectionCounts as $section): ?>
                <div class="section-row"><span class="section-bullet"></span><span class="section-name"><?= e(t($section['name'])) ?></span><span class="section-progress"><span style="width: <?= $metrics['students']['value'] ? min(100, ((int) $section['total'] / (int) $metrics['students']['value']) * 100) : 0 ?>%"></span></span><strong><?= number_format((int) $section['total']) ?></strong></div>
            <?php endforeach; ?>
        </div>
        <a class="panel-link" href="index.php?page=classes"><?= e(t('Manage sections & classes')) ?> <span>→</span></a>
    </article>
    <?php endif; ?>
    <?php if (can_access_page('attendance')): ?>
    <article class="panel attendance-panel">
        <div class="panel-heading"><div><h2><?= e(t('Today’s attendance')) ?></h2><p><?= e(localized_date(time(), 'long')) ?></p></div><span class="panel-icon attendance-icon">◷</span></div>
        <?php $attendancePercent = (int) $attendanceToday['marked'] > 0 ? (int) round(((int) $attendanceToday['present_count'] / (int) $attendanceToday['marked']) * 100) : 0; ?>
        <div class="attendance-summary"><div class="attendance-ring" style="--attendance: <?= $attendancePercent ?>%"><div><strong><?= $attendancePercent ?>%</strong><span><?= e(t('present')) ?></span></div></div><div class="attendance-legend"><span><i class="legend-dot present-dot"></i><?= e(t('Present / late')) ?> <strong><?= number_format((int) $attendanceToday['present_count']) ?></strong></span><span><i class="legend-dot absent-dot"></i><?= e(t('Marked today')) ?> <strong><?= number_format((int) $attendanceToday['marked']) ?></strong></span></div></div>
        <a class="panel-link" href="index.php?page=attendance"><?= e(t('Take attendance')) ?> <span>→</span></a>
    </article>
    <?php endif; ?>
</section>
<section class="panel recent-panel">
    <div class="panel-heading"><div><h2><?= e(t('Recently enrolled')) ?></h2><p><?= e(t('Your newest students, all in one place')) ?></p></div><a class="text-link" href="index.php?page=students"><?= e(t('View all students')) ?> <span>→</span></a></div>
    <?php if ($recentStudents === []): ?>
        <div class="empty-state"><span class="empty-icon">♧</span><h3><?= e(t('Your student register starts here')) ?></h3><p><?= e(t('Add your first student to keep their class and family details organised.')) ?></p><a class="button button-secondary" href="index.php?page=students#add-student"><?= e(t('Add your first student')) ?></a></div>
    <?php else: ?>
        <div class="table-wrap"><table><thead><tr><th><?= e(t('STUDENT')) ?></th><th><?= e(t('ADMISSION NO.')) ?></th><th><?= e(t('CLASS')) ?></th><th><?= e(t('ENROLLED')) ?></th><th><?= e(t('STATUS')) ?></th></tr></thead><tbody>
        <?php foreach ($recentStudents as $student): ?><tr><td><span class="student-cell"><span class="table-avatar"><?= e(strtoupper(substr($student['first_name'], 0, 1))) ?></span><strong><?= e($student['first_name'] . ' ' . $student['last_name']) ?></strong></span></td><td><?= e($student['admission_number']) ?></td><td><?= e($student['class_name'] ?? t('Unassigned')) ?></td><td><?= e(localized_date($student['enrollment_date'])) ?></td><td><span class="status-pill status-active"><?= e(t('Active')) ?></span></td></tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php endif; ?>
