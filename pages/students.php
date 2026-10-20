<?php
$schoolId = current_user()['school_id'];
$pdo = database();
$classQuery = $pdo->prepare('SELECT id, name FROM classes WHERE school_id = ? ORDER BY name');
$classQuery->execute([$schoolId]);
$classOptions = $classQuery->fetchAll();
$search = trim((string) ($_GET['q'] ?? ''));
$studentsQuery = $pdo->prepare(
    'SELECT s.id, s.user_id, s.admission_number, s.index_code, s.first_name, s.last_name, s.enrollment_date, s.status, c.name AS class_name,
            g.name AS guardian_name, g.phone AS guardian_phone
     FROM students s LEFT JOIN classes c ON c.id = s.class_id
     LEFT JOIN student_guardians sg ON sg.student_id = s.id AND sg.is_primary = 1
     LEFT JOIN guardians g ON g.id = sg.guardian_id
     WHERE s.school_id = ? AND (? = "" OR CONCAT(s.first_name, " ", s.last_name, " ", s.admission_number) LIKE ?)
     ORDER BY s.created_at DESC'
);
$studentsQuery->execute([$schoolId, $search, '%' . $search . '%']);
$students = $studentsQuery->fetchAll();
$studentsWithoutAccounts = array_filter($students, static fn(array $student): bool => $student['user_id'] === null);
?>
<section class="page-heading"><div><span class="eyebrow"><?= e(t('YOUR SCHOOL COMMUNITY')) ?></span><h1><?= e(t('Student register')) ?></h1><p><?= e(t('Keep student records and family contacts together.')) ?></p></div><a class="button button-primary" href="#add-student"><span>＋</span> <?= e(t('Add student')) ?></a></section>
<section class="panel form-panel" id="add-student">
    <div class="panel-heading"><div><h2><?= e(t('Add a student')) ?></h2><p><?= e(t('Enter their details, create a student sign-in, and, if available, add a parent or guardian contact.')) ?></p></div><span class="panel-icon">♧</span></div>
    <form method="post" action="index.php?page=students#add-student" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create_student">
        <div class="form-grid form-grid-three">
            <label><?= e(t('First name')) ?> <span class="required">*</span><input name="first_name" required maxlength="80"></label>
            <label><?= e(t('Last name')) ?> <span class="required">*</span><input name="last_name" required maxlength="80"></label>
            <label><?= e(t('Admission number')) ?> <span class="required">*</span><input name="admission_number" required maxlength="50" placeholder="<?= e(t('e.g. 2026-001')) ?>"></label>
            <label><?= e(t('Student login email')) ?> <span class="required">*</span><input type="email" name="email" required maxlength="190" autocomplete="off"></label>
            <label><?= e(t('Initial password')) ?> <span class="required">*</span><input type="password" name="password" required minlength="10" autocomplete="new-password"><small><?= e(t('At least 10 characters.')) ?></small></label>
            <label><?= e(t('Class')) ?><select name="class_id"><option value="0"><?= e(t('Unassigned')) ?></option><?php foreach ($classOptions as $class): ?><option value="<?= (int) $class['id'] ?>"><?= e($class['name']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('Date of birth')) ?><input type="date" name="date_of_birth"></label>
            <label><?= e(t('Gender')) ?><select name="gender"><option value=""><?= e(t('Prefer not to say')) ?></option><option value="female"><?= e(t('Female')) ?></option><option value="male"><?= e(t('Male')) ?></option><option value="other"><?= e(t('Other')) ?></option></select></label>
            <label><?= e(t('Enrollment date')) ?> <span class="required">*</span><input type="date" name="enrollment_date" value="<?= e(date('Y-m-d')) ?>" required></label>
        </div>
        <div class="form-divider"></div><h3 class="form-section-title inline-section-title"><?= e(t('Parent or guardian')) ?> <span><?= e(t('Optional — you can add contact details later.')) ?></span></h3>
        <div class="form-grid form-grid-three">
            <label><?= e(t('Full name')) ?><input name="guardian_name" maxlength="150"></label>
            <label><?= e(t('Relationship')) ?><input name="guardian_relationship" maxlength="40" placeholder="<?= e(t('e.g. Parent')) ?>"></label>
            <label><?= e(t('Phone number')) ?><input name="guardian_phone" maxlength="40" placeholder="+250 700 000 000"></label>
            <label><?= e(t('Email address')) ?><input type="email" name="guardian_email" maxlength="190"></label>
        </div>
        <div><button class="button button-primary" type="submit"><?= e(t('Save student')) ?> <span>→</span></button></div>
    </form>
</section>
<?php if (current_user()['role'] === 'admin' && $studentsWithoutAccounts !== []): ?>
<section class="panel form-panel" id="create-student-account">
    <div class="panel-heading"><div><h2><?= e(t('Create an account for an existing student')) ?></h2><p><?= e(t('Set a student login email and initial password so they can use their index code for password recovery.')) ?></p></div></div>
    <form method="post" action="index.php?page=students#create-student-account" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create_student_account">
        <div class="form-grid form-grid-three">
            <label><?= e(t('Student')) ?><select name="student_id" required><option value=""><?= e(t('Choose a student')) ?></option><?php foreach ($studentsWithoutAccounts as $student): ?><option value="<?= (int) $student['id'] ?>"><?= e($student['first_name'] . ' ' . $student['last_name'] . ' · ' . $student['admission_number']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('Student login email')) ?><input type="email" name="email" required maxlength="190" autocomplete="off"></label>
            <label><?= e(t('Initial password')) ?><input type="password" name="password" required minlength="10" autocomplete="new-password"><small><?= e(t('At least 10 characters.')) ?></small></label>
        </div>
        <button class="button button-secondary" type="submit"><?= e(t('Create student account')) ?> <span>→</span></button>
    </form>
</section>
<?php endif; ?>
<section class="panel recent-panel">
    <div class="panel-heading"><div><h2><?= e(t('All students')) ?></h2><p><?= count($students) ?> <?= e(t('students in your register')) ?><?= $search !== '' ? ' · ' . e(t('matching your search')) : '' ?></p></div>
        <form method="get" class="search-form"><input type="hidden" name="page" value="students"><input type="hidden" name="lang" value="<?= e(current_language()) ?>"><label class="visually-hidden" for="student-search"><?= e(t('Search students')) ?></label><input id="student-search" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('Search name or admission no.')) ?>"><button type="submit" aria-label="<?= e(t('Search')) ?>">⌕</button></form>
    </div>
    <?php if ($students === []): ?><div class="empty-state compact"><span class="empty-icon">♧</span><p><?= e(t($search !== '' ? 'No students match that search.' : 'Your student register is empty. Add a student above to get started.')) ?></p></div><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th><?= e(t('STUDENT')) ?></th><th><?= e(t('ADMISSION NO.')) ?></th><th><?= e(t('INDEX CODE')) ?></th><th><?= e(t('CLASS')) ?></th><th><?= e(t('PARENT / GUARDIAN')) ?></th><th><?= e(t('ENROLLED')) ?></th><th><?= e(t('STATUS')) ?></th><th></th></tr></thead><tbody>
        <?php foreach ($students as $student): ?><tr><td><span class="student-cell"><span class="table-avatar"><?= e(strtoupper(substr($student['first_name'], 0, 1))) ?></span><strong><?= e($student['first_name'] . ' ' . $student['last_name']) ?></strong></span></td><td><?= e($student['admission_number']) ?></td><td><?= e($student['index_code']) ?></td><td><?= e($student['class_name'] ?? t('Unassigned')) ?></td><td><?php if ($student['guardian_name']): ?><span class="cell-primary"><?= e($student['guardian_name']) ?></span><small class="cell-secondary"><?= e($student['guardian_phone'] ?? '') ?></small><?php else: ?>—<?php endif; ?></td><td><?= e(localized_date($student['enrollment_date'])) ?></td><td><span class="status-pill status-<?= e($student['status']) ?>"><?= e(t(ucfirst($student['status']))) ?></span></td><td><?php if (can_access_page('academics')): ?><a class="text-link" href="report_card.php?student_id=<?= (int) $student['id'] ?>"><?= e(t('Report card')) ?></a><?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
