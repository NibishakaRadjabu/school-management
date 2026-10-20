<?php
$schoolId = current_user()['school_id'];
$pdo = database();

$classQuery = $pdo->prepare('SELECT id, name FROM classes WHERE school_id = ? ORDER BY name');
$classQuery->execute([$schoolId]);
$classes = $classQuery->fetchAll();
$subjectQuery = $pdo->prepare('SELECT id, name, code FROM subjects WHERE school_id = ? ORDER BY name');
$subjectQuery->execute([$schoolId]);
$subjects = $subjectQuery->fetchAll();
$teacherQuery = $pdo->prepare('SELECT id, name FROM teachers WHERE school_id = ? AND status = "active" ORDER BY name');
$teacherQuery->execute([$schoolId]);
$teachers = $teacherQuery->fetchAll();
$studentQuery = $pdo->prepare('SELECT id, first_name, last_name, admission_number, class_id FROM students WHERE school_id = ? AND status = "active" ORDER BY first_name, last_name');
$studentQuery->execute([$schoolId]);
$students = $studentQuery->fetchAll();
$examQuery = $pdo->prepare(
    'SELECT e.id, e.name, e.exam_date, e.max_score, c.name AS class_name, sub.name AS subject_name
     FROM exams e JOIN classes c ON c.id = e.class_id JOIN subjects sub ON sub.id = e.subject_id
     WHERE e.school_id = ? ORDER BY e.exam_date DESC, e.id DESC'
);
$examQuery->execute([$schoolId]);
$exams = $examQuery->fetchAll();
$resultQuery = $pdo->prepare(
    'SELECT r.student_id, r.score, r.remarks, e.name AS exam_name, e.max_score, sub.name AS subject_name,
            s.first_name, s.last_name, s.admission_number, c.name AS class_name
     FROM student_results r JOIN exams e ON e.id = r.exam_id
     JOIN subjects sub ON sub.id = e.subject_id JOIN students s ON s.id = r.student_id
     LEFT JOIN classes c ON c.id = s.class_id
     WHERE e.school_id = ? ORDER BY r.created_at DESC LIMIT 30'
);
$resultQuery->execute([$schoolId]);
$results = $resultQuery->fetchAll();
$timetableQuery = $pdo->prepare(
    'SELECT t.day_of_week, t.start_time, t.end_time, t.room, c.name AS class_name,
            sub.name AS subject_name, teachers.name AS teacher_name
     FROM timetable_entries t JOIN classes c ON c.id = t.class_id
     JOIN subjects sub ON sub.id = t.subject_id
     LEFT JOIN teachers ON teachers.id = t.teacher_id
     WHERE t.school_id = ?
     ORDER BY FIELD(t.day_of_week, "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"), t.start_time'
);
$timetableQuery->execute([$schoolId]);
$timetable = $timetableQuery->fetchAll();
$assignmentQuery = $pdo->prepare(
    'SELECT a.title, a.due_date, c.name AS class_name, sub.name AS subject_name
     FROM assignments a JOIN classes c ON c.id = a.class_id JOIN subjects sub ON sub.id = a.subject_id
     WHERE a.school_id = ? ORDER BY a.due_date IS NULL, a.due_date ASC, a.created_at DESC LIMIT 12'
);
$assignmentQuery->execute([$schoolId]);
$assignments = $assignmentQuery->fetchAll();
?>
<section class="page-heading"><div><span class="eyebrow"><?= e(t('LEARNING & TEACHING')) ?></span><h1><?= e(t('Academics')) ?></h1><p><?= e(t('Manage subjects, exams, student marks, assignments, and timetables.')) ?></p></div></section>
<section class="content-grid">
    <article class="panel form-panel" id="add-subject">
        <div class="panel-heading"><div><h2><?= e(t('Subjects')) ?></h2><p><?= e(t('Add subjects taught at your school.')) ?></p></div><span class="panel-icon">✎</span></div>
        <form method="post" action="index.php?page=academics" class="form-stack compact-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create_subject">
            <label><?= e(t('Subject name')) ?><input name="name" required maxlength="100" placeholder="<?= e(t('e.g. Mathematics')) ?>"></label>
            <label><?= e(t('Subject code')) ?> <span class="muted-copy"><?= e(t('(optional)')) ?></span><input name="code" maxlength="30" placeholder="<?= e(t('e.g. MATH')) ?>"></label>
            <button class="button button-primary" type="submit"><?= e(t('Add subject')) ?> <span>→</span></button>
        </form>
        <?php if ($subjects !== []): ?><div class="simple-list"><?php foreach ($subjects as $subject): ?><span><?= e($subject['name']) ?><?= $subject['code'] ? '<small>' . e($subject['code']) . '</small>' : '' ?></span><?php endforeach; ?></div><?php endif; ?>
    </article>
    <article class="panel form-panel" id="create-exam">
        <div class="panel-heading"><div><h2><?= e(t('Create an exam')) ?></h2><p><?= e(t('Set an assessment for one class and subject.')) ?></p></div><span class="panel-icon accent-orange">▤</span></div>
        <?php if ($classes === [] || $subjects === []): ?>
            <div class="empty-state compact">
                <p><?= e(t('Create a class and a subject before setting up an exam.')) ?></p>
                <div class="empty-actions">
                    <?php if ($classes === []): ?><a href="index.php?page=classes#add-class"><?= e(t('Add a class')) ?> →</a><?php endif; ?>
                    <?php if ($subjects === []): ?><a href="index.php?page=academics#add-subject"><?= e(t('Add subject')) ?> →</a><?php endif; ?>
                </div>
            </div>
        <?php else: ?>
        <form method="post" action="index.php?page=academics" class="form-stack compact-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create_exam">
            <label><?= e(t('Exam name')) ?><input name="name" required maxlength="120" placeholder="<?= e(t('e.g. Term 1 assessment')) ?>"></label>
            <div class="form-grid">
                <label><?= e(t('Class')) ?><select name="class_id" required><option value=""><?= e(t('Select a class')) ?></option><?php foreach ($classes as $class): ?><option value="<?= (int) $class['id'] ?>"><?= e($class['name']) ?></option><?php endforeach; ?></select></label>
                <label><?= e(t('Subject')) ?><select name="subject_id" required><option value=""><?= e(t('Select a subject')) ?></option><?php foreach ($subjects as $subject): ?><option value="<?= (int) $subject['id'] ?>"><?= e($subject['name']) ?></option><?php endforeach; ?></select></label>
            </div>
            <div class="form-grid"><label><?= e(t('Exam date')) ?><input type="date" name="exam_date" value="<?= e(date('Y-m-d')) ?>" required></label><label><?= e(t('Maximum marks')) ?><input type="number" name="max_score" min="0.01" step="0.01" value="100" required></label></div>
            <button class="button button-primary" type="submit"><?= e(t('Create exam')) ?> <span>→</span></button>
        </form><?php endif; ?>
    </article>
    <article class="panel form-panel">
        <div class="panel-heading"><div><h2><?= e(t('Record a student mark')) ?></h2><p><?= e(t('Scores are checked against the exam maximum.')) ?></p></div><span class="panel-icon accent-green">✓</span></div>
        <?php if ($exams === [] || $students === []): ?>
            <div class="empty-state compact">
                <p><?= e(t('Add an exam and students to start recording results.')) ?></p>
                <div class="empty-actions">
                    <?php if ($exams === []): ?><a href="index.php?page=academics#create-exam"><?= e(t('Create exam')) ?> →</a><?php endif; ?>
                    <?php if ($students === []): ?><a href="index.php?page=students#add-student"><?= e(t('Add a student')) ?> →</a><?php endif; ?>
                </div>
            </div>
        <?php else: ?>
        <form method="post" action="index.php?page=academics" class="form-stack compact-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_mark">
            <label><?= e(t('Exam')) ?><select name="exam_id" required><option value=""><?= e(t('Select an exam')) ?></option><?php foreach ($exams as $exam): ?><option value="<?= (int) $exam['id'] ?>"><?= e($exam['name'] . ' · ' . $exam['class_name'] . ' · ' . $exam['subject_name']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('Student')) ?><select name="student_id" required><option value=""><?= e(t('Select a student')) ?></option><?php foreach ($students as $student): ?><option value="<?= (int) $student['id'] ?>"><?= e($student['first_name'] . ' ' . $student['last_name'] . ' · ' . ($student['admission_number'])) ?></option><?php endforeach; ?></select></label>
            <div class="form-grid"><label><?= e(t('Score')) ?><input type="number" name="score" min="0" step="0.01" required></label><label><?= e(t('Teacher’s remark')) ?><input name="remarks" maxlength="255" placeholder="<?= e(t('Optional')) ?>"></label></div>
            <button class="button button-secondary" type="submit"><?= e(t('Save mark')) ?> <span>→</span></button>
        </form><?php endif; ?>
    </article>
    <article class="panel form-panel" id="create-assignment">
        <div class="panel-heading"><div><h2><?= e(t('Class assignment')) ?></h2><p><?= e(t('Share work with a class and subject.')) ?></p></div><span class="panel-icon">▧</span></div>
        <?php if ($classes === [] || $subjects === []): ?>
            <div class="empty-state compact">
                <p><?= e(t('Create a class and a subject before adding assignments.')) ?></p>
                <div class="empty-actions">
                    <?php if ($classes === []): ?><a href="index.php?page=classes#add-class"><?= e(t('Add a class')) ?> →</a><?php endif; ?>
                    <?php if ($subjects === []): ?><a href="index.php?page=academics#add-subject"><?= e(t('Add subject')) ?> →</a><?php endif; ?>
                </div>
            </div>
        <?php else: ?>
        <form method="post" action="index.php?page=academics" class="form-stack compact-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create_assignment">
            <label><?= e(t('Assignment title')) ?><input name="title" required maxlength="180" placeholder="<?= e(t('e.g. Fractions practice')) ?>"></label>
            <div class="form-grid"><label><?= e(t('Class')) ?><select name="class_id" required><option value=""><?= e(t('Select a class')) ?></option><?php foreach ($classes as $class): ?><option value="<?= (int) $class['id'] ?>"><?= e($class['name']) ?></option><?php endforeach; ?></select></label><label><?= e(t('Subject')) ?><select name="subject_id" required><option value=""><?= e(t('Select a subject')) ?></option><?php foreach ($subjects as $subject): ?><option value="<?= (int) $subject['id'] ?>"><?= e($subject['name']) ?></option><?php endforeach; ?></select></label></div>
            <label><?= e(t('Instructions')) ?><textarea name="description" rows="3" placeholder="<?= e(t('Add instructions for students')) ?>"></textarea></label>
            <label><?= e(t('Due date')) ?><input type="date" name="due_date"></label>
            <button class="button button-secondary" type="submit"><?= e(t('Add assignment')) ?> <span>→</span></button>
        </form><?php endif; ?>
    </article>
    <article class="panel form-panel">
        <div class="panel-heading"><div><h2><?= e(t('Add a timetable lesson')) ?></h2><p><?= e(t('Plan a class period and teacher.')) ?></p></div><span class="panel-icon">◷</span></div>
        <?php if ($classes === [] || $subjects === []): ?>
            <div class="empty-state compact">
                <p><?= e(t('Create a class and a subject before building a timetable.')) ?></p>
                <div class="empty-actions">
                    <?php if ($classes === []): ?><a href="index.php?page=classes#add-class"><?= e(t('Add a class')) ?> →</a><?php endif; ?>
                    <?php if ($subjects === []): ?><a href="index.php?page=academics#add-subject"><?= e(t('Add subject')) ?> →</a><?php endif; ?>
                </div>
            </div>
        <?php else: ?>
        <form method="post" action="index.php?page=academics" class="form-stack compact-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create_timetable">
            <div class="form-grid"><label><?= e(t('Class')) ?><select name="class_id" required><option value=""><?= e(t('Select a class')) ?></option><?php foreach ($classes as $class): ?><option value="<?= (int) $class['id'] ?>"><?= e($class['name']) ?></option><?php endforeach; ?></select></label><label><?= e(t('Subject')) ?><select name="subject_id" required><option value=""><?= e(t('Select a subject')) ?></option><?php foreach ($subjects as $subject): ?><option value="<?= (int) $subject['id'] ?>"><?= e($subject['name']) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-grid"><label><?= e(t('Day')) ?><select name="day_of_week" required><?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): ?><option value="<?= e($day) ?>"><?= e(t($day)) ?></option><?php endforeach; ?></select></label><label><?= e(t('Teacher')) ?><select name="teacher_id"><option value="0"><?= e(t('Not assigned')) ?></option><?php foreach ($teachers as $teacher): ?><option value="<?= (int) $teacher['id'] ?>"><?= e($teacher['name']) ?></option><?php endforeach; ?></select></label></div>
            <div class="form-grid"><label><?= e(t('Start time')) ?><input type="time" name="start_time" required></label><label><?= e(t('End time')) ?><input type="time" name="end_time" required></label></div>
            <label><?= e(t('Room or location')) ?><input name="room" maxlength="80" placeholder="<?= e(t('Optional')) ?>"></label>
            <button class="button button-secondary" type="submit"><?= e(t('Add lesson')) ?> <span>→</span></button>
        </form><?php endif; ?>
    </article>
</section>
<section class="panel recent-panel">
    <div class="panel-heading"><div><h2><?= e(t('Exam results')) ?></h2><p><?= count($results) ?> <?= e(t('recent marks')) ?></p></div></div>
    <?php if ($results === []): ?><div class="empty-state compact"><p><?= e(t('Student results will appear here as marks are recorded.')) ?></p><?php if ($exams === []): ?><a href="index.php?page=academics#create-exam"><?= e(t('Create exam')) ?> →</a><?php endif; ?></div><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th><?= e(t('STUDENT')) ?></th><th><?= e(t('CLASS')) ?></th><th><?= e(t('EXAM')) ?></th><th><?= e(t('SUBJECT')) ?></th><th><?= e(t('SCORE')) ?></th><th><?= e(t('REMARK')) ?></th><th></th></tr></thead><tbody><?php foreach ($results as $result): ?><tr><td><span class="cell-primary"><?= e($result['first_name'] . ' ' . $result['last_name']) ?></span><small class="cell-secondary"><?= e($result['admission_number']) ?></small></td><td><?= e($result['class_name'] ?? '—') ?></td><td><?= e($result['exam_name']) ?></td><td><?= e($result['subject_name']) ?></td><td><strong><?= e((string) $result['score']) ?> / <?= e((string) $result['max_score']) ?></strong></td><td><?= e($result['remarks'] ?? '—') ?></td><td><a class="text-link" href="report_card.php?student_id=<?= (int) $result['student_id'] ?>"><?= e(t('Report')) ?></a></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
</section>
<section class="content-grid academics-lists">
    <article class="panel recent-panel">
        <div class="panel-heading"><div><h2><?= e(t('Your class timetable')) ?></h2><p><?= count($timetable) ?> <?= e(t('scheduled lessons')) ?></p></div></div>
        <?php if ($timetable === []): ?><div class="empty-state compact"><p><?= e(t('Your class timetable will appear here.')) ?></p></div><?php else: ?><div class="table-wrap"><table><thead><tr><th><?= e(t('DAY / TIME')) ?></th><th><?= e(t('CLASS')) ?></th><th><?= e(t('SUBJECT')) ?></th><th><?= e(t('TEACHER')) ?></th><th><?= e(t('ROOM')) ?></th></tr></thead><tbody><?php foreach ($timetable as $lesson): ?><tr><td><?= e(t($lesson['day_of_week'])) ?><small class="cell-secondary"><?= e(substr($lesson['start_time'], 0, 5) . '–' . substr($lesson['end_time'], 0, 5)) ?></small></td><td><?= e($lesson['class_name']) ?></td><td><?= e($lesson['subject_name']) ?></td><td><?= e($lesson['teacher_name'] ?? '—') ?></td><td><?= e($lesson['room'] ?? '—') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </article>
    <article class="panel recent-panel">
        <div class="panel-heading"><div><h2><?= e(t('Assignments')) ?></h2><p><?= count($assignments) ?> <?= e(t('recent class assignments')) ?></p></div></div>
        <?php if ($assignments === []): ?><div class="empty-state compact"><p><?= e(t('Class assignments will appear here.')) ?></p><a href="index.php?page=academics#create-assignment"><?= e(t('Add assignment')) ?> →</a></div><?php else: ?><div class="assignment-list"><?php foreach ($assignments as $assignment): ?><div class="assignment-row"><span class="assignment-icon">▧</span><div><strong><?= e($assignment['title']) ?></strong><small><?= e($assignment['class_name'] . ' · ' . $assignment['subject_name']) ?></small></div><time><?= $assignment['due_date'] ? e(localized_date($assignment['due_date'])) : e(t('No due date')) ?></time></div><?php endforeach; ?></div><?php endif; ?>
    </article>
</section>
