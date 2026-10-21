<?php
$schoolId = current_user()['school_id'];
$canManageBehavior = in_array(current_user()['role'], ['admin', 'behavior_officer'], true);
$pdo = database();
$classQuery = $pdo->prepare('SELECT id, name FROM classes WHERE school_id = ? ORDER BY name');
$classQuery->execute([$schoolId]);
$classOptions = $classQuery->fetchAll();
$activeStudents = [];
if ($canManageBehavior) {
    $activeStudentsQuery = $pdo->prepare('SELECT id, first_name, last_name, admission_number FROM students WHERE school_id = ? AND status = ? ORDER BY last_name, first_name');
    $activeStudentsQuery->execute([$schoolId, 'active']);
    $activeStudents = $activeStudentsQuery->fetchAll();
}
$search = trim((string) ($_GET['q'] ?? ''));
$behaviorScoreColumn = $canManageBehavior
    ? ', GREATEST(0, 100 - (SELECT COALESCE(SUM(bpd.points_deducted), 0) FROM behavior_point_deductions bpd WHERE bpd.school_id = s.school_id AND bpd.student_id = s.id)) AS behavior_score'
    : '';
$studentsQuery = $pdo->prepare(
    'SELECT s.id, s.user_id, s.admission_number, s.index_code, s.first_name, s.last_name, s.enrollment_date, s.status, c.name AS class_name' . $behaviorScoreColumn . ',
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
$behaviorHistory = [];
if ($canManageBehavior) {
    $historyQuery = $pdo->prepare(
        'SELECT bpd.id, bpd.points_deducted, bpd.misconduct, bpd.created_at, s.first_name, s.last_name, u.name AS recorded_by_name
         FROM behavior_point_deductions bpd
         JOIN students s ON s.id = bpd.student_id AND s.school_id = bpd.school_id
         LEFT JOIN users u ON u.id = bpd.recorded_by
         WHERE bpd.school_id = ?
         ORDER BY bpd.created_at DESC, bpd.id DESC
         LIMIT 50'
    );
    $historyQuery->execute([$schoolId]);
    $behaviorHistory = $historyQuery->fetchAll();
}
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
<?php if ($canManageBehavior): ?>
<section class="panel form-panel" id="behavior-points">
    <div class="panel-heading"><div><h2><?= e(t('Deduct behavior points')) ?></h2><p><?= e(t('Students start with 100 points. Record the specific misconduct for every deduction.')) ?></p></div><span class="panel-icon">−</span></div>
    <?php if ($activeStudents === []): ?><div class="empty-state compact"><p><?= e(t('There are no active students to record behavior points for.')) ?></p></div><?php else: ?>
    <form method="post" action="index.php?page=students#behavior-points" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="deduct_behavior_points">
        <div class="form-grid form-grid-three">
            <label><?= e(t('Student')) ?><select name="student_id" required><option value=""><?= e(t('Choose a student')) ?></option><?php foreach ($activeStudents as $student): ?><option value="<?= (int) $student['id'] ?>"><?= e($student['first_name'] . ' ' . $student['last_name'] . ' · ' . $student['admission_number']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('Points to deduct')) ?><input type="number" name="points_deducted" required min="1" max="100" step="1"></label>
            <label><?= e(t('Specific misconduct')) ?><textarea name="misconduct" required maxlength="2000" rows="3" placeholder="<?= e(t('Describe the misconduct that led to this deduction.')) ?>"></textarea></label>
        </div>
        <div class="signature-grid" data-required-message="<?= e(t('Both signatures are required.')) ?>">
            <div class="signature-field">
                <label for="student-signature"><?= e(t('Student signature')) ?></label>
                <canvas id="student-signature" class="signature-canvas" width="600" height="180" aria-label="<?= e(t('Student signature')) ?>"></canvas>
                <input type="hidden" name="student_signature">
                <small><?= e(t('Sign using a mouse, touch screen, or stylus.')) ?></small>
                <button class="button button-secondary signature-clear" type="button"><?= e(t('Clear signature')) ?></button>
            </div>
            <div class="signature-field">
                <label for="officer-signature"><?= e(t('Behavior Officer signature')) ?></label>
                <canvas id="officer-signature" class="signature-canvas" width="600" height="180" aria-label="<?= e(t('Behavior Officer signature')) ?>"></canvas>
                <input type="hidden" name="officer_signature">
                <small><?= e(t('Sign using a mouse, touch screen, or stylus.')) ?></small>
                <button class="button button-secondary signature-clear" type="button"><?= e(t('Clear signature')) ?></button>
            </div>
        </div>
        <button class="button button-primary" type="submit"><?= e(t('Record deduction')) ?> <span>→</span></button>
    </form>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php if ($canManageBehavior && $activeStudents !== []): ?>
<script>
document.querySelectorAll('.signature-grid').forEach((group) => {
    const form = group.closest('form');
    const signatures = [];

    group.querySelectorAll('.signature-field').forEach((field) => {
        const canvas = field.querySelector('canvas');
        const context = canvas.getContext('2d');
        const output = field.querySelector('input[type="hidden"]');
        const clearButton = field.querySelector('.signature-clear');
        let drawing = false;
        let hasSignature = false;

        context.strokeStyle = '#202638';
        context.lineWidth = 3;
        context.lineCap = 'round';
        context.lineJoin = 'round';

        const position = (event) => {
            const bounds = canvas.getBoundingClientRect();
            return {
                x: (event.clientX - bounds.left) * canvas.width / bounds.width,
                y: (event.clientY - bounds.top) * canvas.height / bounds.height
            };
        };

        canvas.addEventListener('pointerdown', (event) => {
            event.preventDefault();
            drawing = true;
            canvas.setPointerCapture(event.pointerId);
            const point = position(event);
            context.beginPath();
            context.arc(point.x, point.y, 1.5, 0, 2 * Math.PI);
            context.fill();
            context.beginPath();
            context.moveTo(point.x, point.y);
            hasSignature = true;
        });
        canvas.addEventListener('pointermove', (event) => {
            if (!drawing) return;
            event.preventDefault();
            const point = position(event);
            context.lineTo(point.x, point.y);
            context.stroke();
            hasSignature = true;
        });
        const stopDrawing = () => { drawing = false; };
        canvas.addEventListener('pointerup', stopDrawing);
        canvas.addEventListener('pointercancel', stopDrawing);
        canvas.addEventListener('lostpointercapture', stopDrawing);
        clearButton.addEventListener('click', () => {
            context.clearRect(0, 0, canvas.width, canvas.height);
            output.value = '';
            hasSignature = false;
        });
        signatures.push({ canvas, output, get hasSignature() { return hasSignature; } });
    });

    form.addEventListener('submit', (event) => {
        if (signatures.some((signature) => !signature.hasSignature)) {
            event.preventDefault();
            window.alert(group.dataset.requiredMessage);
            return;
        }
        signatures.forEach((signature) => {
            signature.output.value = signature.canvas.toDataURL('image/png');
        });
    });
});
</script>
<?php endif; ?>
<section class="panel recent-panel">
    <div class="panel-heading"><div><h2><?= e(t('All students')) ?></h2><p><?= count($students) ?> <?= e(t('students in your register')) ?><?= $search !== '' ? ' · ' . e(t('matching your search')) : '' ?></p></div>
        <form method="get" class="search-form"><input type="hidden" name="page" value="students"><input type="hidden" name="lang" value="<?= e(current_language()) ?>"><label class="visually-hidden" for="student-search"><?= e(t('Search students')) ?></label><input id="student-search" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('Search name or admission no.')) ?>"><button type="submit" aria-label="<?= e(t('Search')) ?>">⌕</button></form>
    </div>
    <?php if ($students === []): ?><div class="empty-state compact"><span class="empty-icon">♧</span><p><?= e(t($search !== '' ? 'No students match that search.' : 'Your student register is empty. Add a student above to get started.')) ?></p></div><?php else: ?>
        <div class="profile-card-grid">
            <?php foreach ($students as $student): ?><article class="profile-card">
                <div class="profile-card-header">
                    <span class="profile-card-avatar profile-card-initial"><?= e(strtoupper(substr($student['first_name'], 0, 1))) ?></span>
                    <div class="profile-card-identity"><span class="eyebrow"><?= e(t('Student')) ?></span><h3><?= e($student['first_name'] . ' ' . $student['last_name']) ?></h3><span><?= e(t('Admission number')) ?> · <?= e($student['admission_number']) ?></span></div>
                    <span class="status-pill status-<?= e($student['status']) ?>"><?= e(t(ucfirst($student['status']))) ?></span>
                </div>
                <div class="profile-card-details">
                    <div><small><?= e(t('INDEX CODE')) ?></small><strong><?= e($student['index_code']) ?></strong></div>
                    <div><small><?= e(t('Class')) ?></small><strong><?= e($student['class_name'] ?? t('Unassigned')) ?></strong></div>
                    <div><small><?= e(t('Enrollment date')) ?></small><strong><?= e(localized_date($student['enrollment_date'])) ?></strong></div>
                    <?php if ($canManageBehavior): ?><div><small><?= e(t('Behavior points')) ?></small><strong><?= (int) $student['behavior_score'] ?> / 100</strong></div><?php endif; ?>
                    <div><small><?= e(t('Parent or guardian')) ?></small><strong><?= e($student['guardian_name'] ?? '—') ?></strong><?php if ($student['guardian_phone']): ?><span><?= e($student['guardian_phone']) ?></span><?php endif; ?></div>
                </div>
                <?php if (can_access_page('academics')): ?><div class="profile-card-actions"><a class="text-link" href="report_card.php?student_id=<?= (int) $student['id'] ?>"><?= e(t('Report card')) ?> →</a></div><?php endif; ?>
            </article><?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php if ($canManageBehavior): ?>
<section class="panel recent-panel">
    <div class="panel-heading"><div><h2><?= e(t('Recent behavior deductions')) ?></h2><p><?= e(t('The latest recorded misconduct and point deductions.')) ?></p></div></div>
    <?php if ($behaviorHistory === []): ?><div class="empty-state compact"><p><?= e(t('No behavior point deductions have been recorded.')) ?></p></div><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th><?= e(t('Student')) ?></th><th><?= e(t('Points deducted')) ?></th><th><?= e(t('Specific misconduct')) ?></th><th><?= e(t('Recorded by')) ?></th><th><?= e(t('Recorded at')) ?></th><th><?= e(t('Actions')) ?></th></tr></thead><tbody>
        <?php foreach ($behaviorHistory as $entry): ?><tr><td><?= e($entry['first_name'] . ' ' . $entry['last_name']) ?></td><td><?= (int) $entry['points_deducted'] ?></td><td><?= e($entry['misconduct']) ?></td><td><?= e($entry['recorded_by_name'] ?? t('Former staff member')) ?></td><td><?= e(date('Y-m-d H:i:s', strtotime($entry['created_at']))) ?></td><td><a class="text-link" href="behavior_deduction.php?id=<?= (int) $entry['id'] ?>"><?= e(t('Print record')) ?></a></td></tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
<?php endif; ?>
