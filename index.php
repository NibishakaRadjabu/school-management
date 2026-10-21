<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/behavior.php';

if (!school_is_registered()) {
    redirect('setup.php');
}

require_login();

$pages = [
    'dashboard' => ['Dashboard', __DIR__ . '/pages/dashboard.php'],
    'students' => ['Students', __DIR__ . '/pages/students.php'],
    'classes' => ['Classes', __DIR__ . '/pages/classes.php'],
    'academics' => ['Academics', __DIR__ . '/pages/academics.php'],
    'teachers' => ['Staff', __DIR__ . '/pages/teachers.php'],
    'attendance' => ['Attendance', __DIR__ . '/pages/attendance.php'],
    'fees' => ['Fees', __DIR__ . '/pages/fees.php'],
    'communications' => ['Communications', __DIR__ . '/pages/communications.php'],
    'profile' => ['My profile', __DIR__ . '/pages/profile.php'],
];
$page = $_GET['page'] ?? (($_SESSION['user_role'] ?? '') === 'student' ? 'profile' : 'dashboard');
if (!is_string($page) || !isset($pages[$page])) {
    http_response_code(404);
    $page = 'dashboard';
    flash('The page you requested could not be found.', 'error');
}
require_page_access($page);

$user = current_user();
$pdo = database();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $actionPages = [
        'create_section' => 'classes',
        'create_class' => 'classes',
        'create_student' => 'students',
        'create_student_account' => 'students',
        'deduct_behavior_points' => 'students',
        'create_teacher' => 'teachers',
        'create_teacher_account' => 'teachers',
        'update_teacher' => 'teachers',
        'delete_teacher' => 'teachers',
        'update_profile' => 'profile',
        'delete_my_account' => 'profile',
        'create_subject' => 'academics',
        'create_exam' => 'academics',
        'save_mark' => 'academics',
        'create_assignment' => 'academics',
        'create_timetable' => 'academics',
        'save_attendance' => 'attendance',
        'create_invoice' => 'fees',
        'record_payment' => 'fees',
        'create_announcement' => 'communications',
    ];
    if (isset($actionPages[$action])) {
        require_page_access($actionPages[$action]);
    }
    $returnPage = $page;
    $newProfilePicture = null;

    try {
        switch ($action) {
            case 'create_section':
                $name = trim((string) ($_POST['name'] ?? ''));
                if ($name === '') {
                    flash('Enter a section name.', 'error');
                    break;
                }
                $statement = $pdo->prepare('INSERT INTO sections (school_id, name) VALUES (?, ?)');
                $statement->execute([$user['school_id'], $name]);
                flash('Section created.');
                break;

            case 'create_class':
                $name = trim((string) ($_POST['name'] ?? ''));
                $year = trim((string) ($_POST['academic_year'] ?? ''));
                $sectionId = (int) ($_POST['section_id'] ?? 0);
                if ($name === '') {
                    flash('Enter a class name.', 'error');
                    break;
                }
                if ($sectionId > 0) {
                    $sectionCheck = $pdo->prepare('SELECT id FROM sections WHERE id = ? AND school_id = ?');
                    $sectionCheck->execute([$sectionId, $user['school_id']]);
                    if (!$sectionCheck->fetchColumn()) {
                        flash('Choose a section belonging to this school.', 'error');
                        break;
                    }
                }
                $statement = $pdo->prepare('INSERT INTO classes (school_id, section_id, name, academic_year) VALUES (?, ?, ?, ?)');
                $statement->execute([$user['school_id'], $sectionId ?: null, $name, $year ?: null]);
                flash('Class created.');
                break;

            case 'create_student':
                $firstName = trim((string) ($_POST['first_name'] ?? ''));
                $lastName = trim((string) ($_POST['last_name'] ?? ''));
                $admission = trim((string) ($_POST['admission_number'] ?? ''));
                $email = trim((string) ($_POST['email'] ?? ''));
                $password = (string) ($_POST['password'] ?? '');
                $classId = (int) ($_POST['class_id'] ?? 0);
                $enrollmentDate = (string) ($_POST['enrollment_date'] ?? '');
                if ($firstName === '' || $lastName === '' || $admission === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $enrollmentDate)) {
                    flash('Complete the student details, enter a valid login email, and choose a password with at least 10 characters.', 'error');
                    break;
                }
                if ($classId > 0) {
                    $classCheck = $pdo->prepare('SELECT id FROM classes WHERE id = ? AND school_id = ?');
                    $classCheck->execute([$classId, $user['school_id']]);
                    if (!$classCheck->fetchColumn()) {
                        flash('Choose a class belonging to this school.', 'error');
                        break;
                    }
                }
                $gender = (string) ($_POST['gender'] ?? '');
                if (!in_array($gender, ['', 'female', 'male', 'other'], true)) {
                    flash('Choose a valid gender option.', 'error');
                    break;
                }
                $pdo->beginTransaction();
                $studentRole = $pdo->prepare('INSERT INTO roles (name) VALUES (?) ON DUPLICATE KEY UPDATE name = VALUES(name)');
                $studentRole->execute(['student']);
                $roleQuery = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
                $roleQuery->execute(['student']);
                $roleId = (int) $roleQuery->fetchColumn();
                $account = $pdo->prepare('INSERT INTO users (school_id, role_id, name, email, password_hash) VALUES (?, ?, ?, ?, ?)');
                $account->execute([$user['school_id'], $roleId, $firstName . ' ' . $lastName, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $studentUserId = (int) $pdo->lastInsertId();
                $indexCode = generate_index_code($pdo, 'student', (int) $user['school_id']);
                $insert = $pdo->prepare(
                    'INSERT INTO students (school_id, user_id, class_id, admission_number, index_code, first_name, last_name, date_of_birth, gender, enrollment_date)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $birthDate = trim((string) ($_POST['date_of_birth'] ?? ''));
                $insert->execute([
                    $user['school_id'],
                    $studentUserId,
                    $classId ?: null,
                    $admission,
                    $indexCode,
                    $firstName,
                    $lastName,
                    $birthDate !== '' ? $birthDate : null,
                    $gender !== '' ? $gender : null,
                    $enrollmentDate,
                ]);
                $studentId = (int) $pdo->lastInsertId();
                $guardianName = trim((string) ($_POST['guardian_name'] ?? ''));
                if ($guardianName !== '') {
                    $guardianEmail = trim((string) ($_POST['guardian_email'] ?? ''));
                    $guardianPhone = trim((string) ($_POST['guardian_phone'] ?? ''));
                    $guardianLookup = $pdo->prepare(
                        'SELECT id FROM guardians WHERE school_id = ?
                         AND ((? <> "" AND email = ?) OR (? <> "" AND phone = ?))
                         LIMIT 1'
                    );
                    $guardianLookup->execute([
                        $user['school_id'],
                        $guardianEmail,
                        $guardianEmail,
                        $guardianPhone,
                        $guardianPhone,
                    ]);
                    $guardianId = (int) $guardianLookup->fetchColumn();
                    if ($guardianId === 0) {
                        $guardian = $pdo->prepare('INSERT INTO guardians (school_id, name, email, phone) VALUES (?, ?, ?, ?)');
                        $guardian->execute([
                            $user['school_id'],
                            $guardianName,
                            $guardianEmail ?: null,
                            $guardianPhone ?: null,
                        ]);
                        $guardianId = (int) $pdo->lastInsertId();
                    }
                    $link = $pdo->prepare('INSERT INTO student_guardians (student_id, guardian_id, relationship, is_primary) VALUES (?, ?, ?, TRUE)');
                    $link->execute([$studentId, $guardianId, trim((string) ($_POST['guardian_relationship'] ?? '')) ?: null]);
                }
                $pdo->commit();
                flash(t('Student added to the register. Index code: {{code}}', ['{{code}}' => $indexCode]));
                break;

            case 'deduct_behavior_points':
                if (!in_array($user['role'], ['admin', 'behavior_officer'], true)) {
                    http_response_code(403);
                    exit(e(t('You do not have permission to record behavior point deductions.')));
                }
                $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
                $points = filter_var($_POST['points_deducted'] ?? null, FILTER_VALIDATE_INT);
                $misconduct = trim((string) ($_POST['misconduct'] ?? ''));
                if ($studentId === false || $points === false || $points < 1 || $points > 100 || $misconduct === '' || strlen($misconduct) > 8000 || preg_match('/\A.{1,2000}\z/us', $misconduct) !== 1) {
                    flash('Choose an active student, enter a valid point deduction, and describe the specific misconduct.', 'error');
                    break;
                }
                $studentSignature = decode_behavior_signature((string) ($_POST['student_signature'] ?? ''));
                $officerSignature = decode_behavior_signature((string) ($_POST['officer_signature'] ?? ''));
                $pdo->beginTransaction();
                $studentCheck = $pdo->prepare('SELECT id FROM students WHERE id = ? AND school_id = ? AND status = ? FOR UPDATE');
                $studentCheck->execute([$studentId, $user['school_id'], 'active']);
                if (!$studentCheck->fetchColumn()) {
                    $pdo->rollBack();
                    flash('Choose an active student in this school.', 'error');
                    break;
                }
                $deductedQuery = $pdo->prepare('SELECT COALESCE(SUM(points_deducted), 0) FROM behavior_point_deductions WHERE school_id = ? AND student_id = ?');
                $deductedQuery->execute([$user['school_id'], $studentId]);
                $currentScore = 100 - (int) $deductedQuery->fetchColumn();
                if ($points > $currentScore) {
                    $pdo->rollBack();
                    flash('The deduction cannot be greater than the student’s remaining behavior points.', 'error');
                    break;
                }
                $deduction = $pdo->prepare('INSERT INTO behavior_point_deductions (school_id, student_id, recorded_by, points_deducted, misconduct, student_signature, officer_signature) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $deduction->execute([$user['school_id'], $studentId, $user['id'], $points, $misconduct, $studentSignature, $officerSignature]);
                $pdo->commit();
                flash('Behavior points deducted and misconduct recorded.');
                break;

            case 'create_student_account':
                require_admin();
                $studentId = (int) ($_POST['student_id'] ?? 0);
                $email = trim((string) ($_POST['email'] ?? ''));
                $password = (string) ($_POST['password'] ?? '');
                if ($studentId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
                    flash('Choose a student, enter a valid login email, and use a password with at least 10 characters.', 'error');
                    break;
                }
                $pdo->beginTransaction();
                $studentQuery = $pdo->prepare('SELECT id, first_name, last_name FROM students WHERE id = ? AND school_id = ? AND user_id IS NULL FOR UPDATE');
                $studentQuery->execute([$studentId, $user['school_id']]);
                $student = $studentQuery->fetch();
                if (!$student) {
                    $pdo->rollBack();
                    flash('That student already has an account or could not be found.', 'error');
                    break;
                }
                $studentRole = $pdo->prepare('INSERT INTO roles (name) VALUES (?) ON DUPLICATE KEY UPDATE name = VALUES(name)');
                $studentRole->execute(['student']);
                $roleQuery = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
                $roleQuery->execute(['student']);
                $roleId = (int) $roleQuery->fetchColumn();
                $account = $pdo->prepare('INSERT INTO users (school_id, role_id, name, email, password_hash) VALUES (?, ?, ?, ?, ?)');
                $account->execute([
                    $user['school_id'],
                    $roleId,
                    $student['first_name'] . ' ' . $student['last_name'],
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                ]);
                $studentUserId = (int) $pdo->lastInsertId();
                $linkStudent = $pdo->prepare('UPDATE students SET user_id = ? WHERE id = ? AND school_id = ? AND user_id IS NULL');
                $linkStudent->execute([$studentUserId, $studentId, $user['school_id']]);
                if ($linkStudent->rowCount() !== 1) {
                    $pdo->rollBack();
                    flash('The student account could not be linked.', 'error');
                    break;
                }
                $pdo->commit();
                flash('Student sign-in account created.');
                break;

            case 'create_teacher':
                require_admin();
                $name = trim((string) ($_POST['name'] ?? ''));
                $employeeNumber = trim((string) ($_POST['employee_number'] ?? ''));
                $email = trim((string) ($_POST['email'] ?? ''));
                $password = (string) ($_POST['password'] ?? '');
                $role = (string) ($_POST['role'] ?? '');
                $staffRoles = ['admin', 'teacher', 'accountant', 'dos', 'head_of_studies', 'behavior_officer'];
                if ($name === '' || $employeeNumber === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, $staffRoles, true) || strlen($password) < 10) {
                    flash('Enter the staff details, a valid email, a staff role, and a password with at least 10 characters.', 'error');
                    break;
                }
                $newProfilePicture = save_profile_picture($_FILES['profile_picture'] ?? []);
                $roleNames = ['admin', 'teacher', 'accountant', 'dos', 'head_of_studies', 'behavior_officer'];
                $pdo->beginTransaction();
                $seedRoles = $pdo->prepare('INSERT INTO roles (name) VALUES (?) ON DUPLICATE KEY UPDATE name = VALUES(name)');
                foreach ($roleNames as $roleName) {
                    $seedRoles->execute([$roleName]);
                }
                $roleQuery = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
                $roleQuery->execute([$role]);
                $roleId = (int) $roleQuery->fetchColumn();
                $phone = trim((string) ($_POST['phone'] ?? ''));
                $account = $pdo->prepare('INSERT INTO users (school_id, role_id, name, email, phone, password_hash, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $account->execute([$user['school_id'], $roleId, $name, $email, $phone ?: null, password_hash($password, PASSWORD_DEFAULT), $newProfilePicture]);
                $userId = (int) $pdo->lastInsertId();
                $staff = $pdo->prepare(
                    'INSERT INTO teachers (school_id, user_id, employee_number, index_code, name, email, phone, job_title)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $jobTitle = trim((string) ($_POST['job_title'] ?? ''));
                $indexCode = generate_index_code($pdo, 'staff', (int) $user['school_id']);
                $staff->execute([
                    $user['school_id'],
                    $userId,
                    $employeeNumber,
                    $indexCode,
                    $name,
                    $email,
                    $phone ?: null,
                    $jobTitle ?: $role,
                ]);
                $pdo->commit();
                flash(t('Staff member and sign-in account created. Index code: {{code}}', ['{{code}}' => $indexCode]));
                break;

            case 'create_teacher_account':
                require_admin();
                $teacherId = (int) ($_POST['teacher_id'] ?? 0);
                $email = trim((string) ($_POST['email'] ?? ''));
                $password = (string) ($_POST['password'] ?? '');
                $role = (string) ($_POST['role'] ?? '');
                $staffRoles = ['admin', 'teacher', 'accountant', 'dos', 'head_of_studies', 'behavior_officer'];
                if ($teacherId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, $staffRoles, true) || strlen($password) < 10) {
                    flash('Choose a staff member and role, enter a valid email, and use a password with at least 10 characters.', 'error');
                    break;
                }
                $pdo->beginTransaction();
                $staffQuery = $pdo->prepare('SELECT id, name, phone FROM teachers WHERE id = ? AND school_id = ? AND user_id IS NULL FOR UPDATE');
                $staffQuery->execute([$teacherId, $user['school_id']]);
                $staffMember = $staffQuery->fetch();
                if (!$staffMember) {
                    $pdo->rollBack();
                    flash('That staff member already has an account or could not be found.', 'error');
                    break;
                }
                $newProfilePicture = save_profile_picture($_FILES['profile_picture'] ?? []);
                $seedRoles = $pdo->prepare('INSERT INTO roles (name) VALUES (?) ON DUPLICATE KEY UPDATE name = VALUES(name)');
                foreach ($staffRoles as $roleName) {
                    $seedRoles->execute([$roleName]);
                }
                $roleQuery = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
                $roleQuery->execute([$role]);
                $roleId = (int) $roleQuery->fetchColumn();
                $account = $pdo->prepare('INSERT INTO users (school_id, role_id, name, email, phone, password_hash, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $account->execute([$user['school_id'], $roleId, $staffMember['name'], $email, $staffMember['phone'], password_hash($password, PASSWORD_DEFAULT), $newProfilePicture]);
                $userId = (int) $pdo->lastInsertId();
                $link = $pdo->prepare('UPDATE teachers SET user_id = ?, email = ? WHERE id = ? AND school_id = ? AND user_id IS NULL');
                $link->execute([$userId, $email, $teacherId, $user['school_id']]);
                if ($link->rowCount() !== 1) {
                    $pdo->rollBack();
                    delete_profile_picture($newProfilePicture);
                    $newProfilePicture = null;
                    flash('The staff account could not be linked.', 'error');
                    break;
                }
                $pdo->commit();
                flash('Staff sign-in account created.');
                break;

            case 'update_teacher':
                require_admin();
                $teacherId = (int) ($_POST['teacher_id'] ?? 0);
                $name = trim((string) ($_POST['name'] ?? ''));
                $employeeNumber = trim((string) ($_POST['employee_number'] ?? ''));
                $email = trim((string) ($_POST['email'] ?? ''));
                $phone = trim((string) ($_POST['phone'] ?? ''));
                $jobTitle = trim((string) ($_POST['job_title'] ?? ''));
                $status = (string) ($_POST['status'] ?? '');
                $role = (string) ($_POST['role'] ?? '');
                $newPassword = (string) ($_POST['new_password'] ?? '');
                $staffRoles = ['admin', 'teacher', 'accountant', 'dos', 'head_of_studies', 'behavior_officer'];
                if ($teacherId <= 0 || $name === '' || $employeeNumber === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($status, ['active', 'inactive'], true) || ($role !== '' && !in_array($role, $staffRoles, true)) || ($newPassword !== '' && strlen($newPassword) < 10)) {
                    flash('Check the staff details, role, status, email, and password length.', 'error');
                    break;
                }
                $pdo->beginTransaction();
                $select = $pdo->prepare('SELECT t.user_id, u.profile_picture FROM teachers t LEFT JOIN users u ON u.id = t.user_id WHERE t.id = ? AND t.school_id = ? FOR UPDATE');
                $select->execute([$teacherId, $user['school_id']]);
                $target = $select->fetch();
                if (!$target) {
                    $pdo->rollBack();
                    flash('That staff record could not be found.', 'error');
                    break;
                }
                $removePicture = isset($_POST['remove_picture']) && $_POST['remove_picture'] === '1';
                $updateStaff = $pdo->prepare('UPDATE teachers SET employee_number = ?, name = ?, email = ?, phone = ?, job_title = ?, status = ? WHERE id = ? AND school_id = ?');
                $updateStaff->execute([$employeeNumber, $name, $email, $phone ?: null, $jobTitle ?: null, $status, $teacherId, $user['school_id']]);
                if ($target['user_id'] !== null) {
                    if (!in_array($role, $staffRoles, true)) {
                        $pdo->rollBack();
                        flash('Choose a valid staff account role.', 'error');
                        break;
                    }
                    $roleQuery = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
                    $roleQuery->execute([$role]);
                    $roleId = $roleQuery->fetchColumn();
                    if ($roleId === false) {
                        $pdo->rollBack();
                        flash('The selected staff role is not available.', 'error');
                        break;
                    }
                    $newProfilePicture = save_profile_picture($_FILES['profile_picture'] ?? []);
                    $picture = $newProfilePicture ?? ($removePicture ? null : $target['profile_picture']);
                    $userSql = 'UPDATE users SET name = ?, email = ?, phone = ?, role_id = ?, profile_picture = ?, is_active = ?';
                    $userValues = [$name, $email, $phone ?: null, $roleId, $picture, $status === 'active' ? 1 : 0];
                    if ($newPassword !== '') {
                        $userSql .= ', password_hash = ?';
                        $userValues[] = password_hash($newPassword, PASSWORD_DEFAULT);
                    }
                    $userSql .= ' WHERE id = ? AND school_id = ?';
                    array_push($userValues, $target['user_id'], $user['school_id']);
                    $updateUser = $pdo->prepare($userSql);
                    $updateUser->execute($userValues);
                    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$target['user_id']]);
                } elseif (isset($_FILES['profile_picture']) && ($_FILES['profile_picture']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE || $removePicture) {
                    $pdo->rollBack();
                    flash('Create a sign-in account before changing this staff member’s profile picture.', 'error');
                    break;
                }
                $pdo->commit();
                if ($newProfilePicture !== null || $removePicture) {
                    delete_profile_picture($target['profile_picture']);
                }
                if ($target['user_id'] !== null && (int) $target['user_id'] === $user['id']) {
                    $_SESSION['user_name'] = $name;
                    $_SESSION['profile_picture'] = $picture ?? '';
                }
                flash('Staff profile updated.');
                break;

            case 'delete_teacher':
                require_admin();
                $teacherId = (int) ($_POST['teacher_id'] ?? 0);
                $pdo->beginTransaction();
                $select = $pdo->prepare('SELECT t.user_id, u.profile_picture FROM teachers t LEFT JOIN users u ON u.id = t.user_id WHERE t.id = ? AND t.school_id = ? FOR UPDATE');
                $select->execute([$teacherId, $user['school_id']]);
                $target = $select->fetch();
                if (!$target || ($target['user_id'] !== null && (int) $target['user_id'] === $user['id'])) {
                    $pdo->rollBack();
                    flash('That staff record cannot be deleted.', 'error');
                    break;
                }
                $deleteStaff = $pdo->prepare('DELETE FROM teachers WHERE id = ? AND school_id = ?');
                $deleteStaff->execute([$teacherId, $user['school_id']]);
                if ($target['user_id'] !== null) {
                    $deleteUser = $pdo->prepare('DELETE FROM users WHERE id = ? AND school_id = ?');
                    $deleteUser->execute([$target['user_id'], $user['school_id']]);
                }
                $pdo->commit();
                delete_profile_picture($target['profile_picture']);
                flash('Staff member and sign-in account deleted.');
                break;

            case 'update_profile':
                $name = trim((string) ($_POST['name'] ?? ''));
                $email = trim((string) ($_POST['email'] ?? ''));
                $phone = trim((string) ($_POST['phone'] ?? ''));
                $currentPassword = (string) ($_POST['current_password'] ?? '');
                $newPassword = (string) ($_POST['new_password'] ?? '');
                if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || ($newPassword !== '' && (strlen($newPassword) < 10 || $currentPassword === ''))) {
                    flash('Enter a valid name and email; password changes need your current password and at least 10 new characters.', 'error');
                    break;
                }
                $removePicture = isset($_POST['remove_picture']) && $_POST['remove_picture'] === '1';
                $pdo->beginTransaction();
                $select = $pdo->prepare('SELECT password_hash, profile_picture FROM users WHERE id = ? AND school_id = ? FOR UPDATE');
                $select->execute([$user['id'], $user['school_id']]);
                $account = $select->fetch();
                if (!$account || ($newPassword !== '' && !password_verify($currentPassword, $account['password_hash']))) {
                    $pdo->rollBack();
                    flash('Your current password is incorrect.', 'error');
                    break;
                }
                $newProfilePicture = save_profile_picture($_FILES['profile_picture'] ?? []);
                $picture = $newProfilePicture ?? ($removePicture ? null : $account['profile_picture']);
                $profileSql = 'UPDATE users SET name = ?, email = ?, phone = ?, profile_picture = ?';
                $profileValues = [$name, $email, $phone ?: null, $picture];
                if ($newPassword !== '') {
                    $profileSql .= ', password_hash = ?';
                    $profileValues[] = password_hash($newPassword, PASSWORD_DEFAULT);
                }
                $profileSql .= ' WHERE id = ? AND school_id = ?';
                array_push($profileValues, $user['id'], $user['school_id']);
                $pdo->prepare($profileSql)->execute($profileValues);
                $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$user['id']]);
                $updateLinkedStaff = $pdo->prepare('UPDATE teachers SET name = ?, email = ?, phone = ? WHERE user_id = ? AND school_id = ?');
                $updateLinkedStaff->execute([$name, $email, $phone ?: null, $user['id'], $user['school_id']]);
                $pdo->commit();
                if ($newProfilePicture !== null || $removePicture) {
                    delete_profile_picture($account['profile_picture']);
                }
                $_SESSION['user_name'] = $name;
                $_SESSION['profile_picture'] = $picture ?? '';
                flash('Your profile was updated.');
                break;

            case 'delete_my_account':
                require_admin();
                $password = (string) ($_POST['current_password'] ?? '');
                $pdo->beginTransaction();
                $accountQuery = $pdo->prepare('SELECT password_hash, profile_picture FROM users WHERE id = ? AND school_id = ? AND is_active = 1 FOR UPDATE');
                $accountQuery->execute([$user['id'], $user['school_id']]);
                $account = $accountQuery->fetch();
                if (!$account || !password_verify($password, $account['password_hash'])) {
                    $pdo->rollBack();
                    flash('Your current password is incorrect.', 'error');
                    break;
                }
                $otherAdmins = $pdo->prepare(
                    'SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
                     WHERE u.school_id = ? AND u.is_active = 1 AND r.name = "admin" AND u.id <> ?'
                );
                $otherAdmins->execute([$user['school_id'], $user['id']]);
                if ((int) $otherAdmins->fetchColumn() < 1) {
                    $pdo->rollBack();
                    flash('Add another active administrator before deleting your account.', 'error');
                    break;
                }
                $delete = $pdo->prepare('DELETE FROM users WHERE id = ? AND school_id = ?');
                $delete->execute([$user['id'], $user['school_id']]);
                $pdo->commit();
                delete_profile_picture($account['profile_picture']);
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
                }
                session_destroy();
                redirect('login.php');

            case 'create_subject':
                $subjectName = trim((string) ($_POST['name'] ?? ''));
                $subjectCode = trim((string) ($_POST['code'] ?? ''));
                if ($subjectName === '') {
                    flash('Enter a subject name.', 'error');
                    break;
                }
                $subjectInsert = $pdo->prepare('INSERT INTO subjects (school_id, name, code) VALUES (?, ?, ?)');
                $subjectInsert->execute([$user['school_id'], $subjectName, $subjectCode ?: null]);
                flash('Subject added.');
                break;

            case 'create_exam':
                $classId = (int) ($_POST['class_id'] ?? 0);
                $subjectId = (int) ($_POST['subject_id'] ?? 0);
                $examName = trim((string) ($_POST['name'] ?? ''));
                $examDate = (string) ($_POST['exam_date'] ?? '');
                $maxScore = filter_var($_POST['max_score'] ?? null, FILTER_VALIDATE_FLOAT);
                $classCheck = $pdo->prepare('SELECT id FROM classes WHERE id = ? AND school_id = ?');
                $classCheck->execute([$classId, $user['school_id']]);
                $subjectCheck = $pdo->prepare('SELECT id FROM subjects WHERE id = ? AND school_id = ?');
                $subjectCheck->execute([$subjectId, $user['school_id']]);
                if (!$classCheck->fetchColumn() || !$subjectCheck->fetchColumn() || $examName === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $examDate) || $maxScore === false || $maxScore <= 0) {
                    flash('Choose this school’s class and subject, and enter valid exam details.', 'error');
                    break;
                }
                $examInsert = $pdo->prepare('INSERT INTO exams (school_id, class_id, subject_id, name, exam_date, max_score) VALUES (?, ?, ?, ?, ?, ?)');
                $examInsert->execute([$user['school_id'], $classId, $subjectId, $examName, $examDate, $maxScore]);
                flash('Exam created.');
                break;

            case 'save_mark':
                $examId = (int) ($_POST['exam_id'] ?? 0);
                $studentId = (int) ($_POST['student_id'] ?? 0);
                $score = filter_var($_POST['score'] ?? null, FILTER_VALIDATE_FLOAT);
                $examCheck = $pdo->prepare(
                    'SELECT e.max_score FROM exams e WHERE e.id = ? AND e.school_id = ?'
                );
                $examCheck->execute([$examId, $user['school_id']]);
                $examMaximum = $examCheck->fetchColumn();
                $studentCheck = $pdo->prepare(
                    'SELECT s.id FROM students s JOIN exams e ON e.class_id = s.class_id
                     WHERE s.id = ? AND s.school_id = ? AND e.id = ? AND e.school_id = ?'
                );
                $studentCheck->execute([$studentId, $user['school_id'], $examId, $user['school_id']]);
                if ($examMaximum === false || !$studentCheck->fetchColumn() || $score === false || $score < 0 || $score > (float) $examMaximum) {
                    flash('Choose a student in the exam class and enter a score within the maximum marks.', 'error');
                    break;
                }
                $markInsert = $pdo->prepare(
                    'INSERT INTO student_results (exam_id, student_id, score, remarks) VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE score = VALUES(score), remarks = VALUES(remarks)'
                );
                $markInsert->execute([$examId, $studentId, $score, trim((string) ($_POST['remarks'] ?? '')) ?: null]);
                flash('Student mark saved.');
                break;

            case 'create_assignment':
                $classId = (int) ($_POST['class_id'] ?? 0);
                $subjectId = (int) ($_POST['subject_id'] ?? 0);
                $assignmentTitle = trim((string) ($_POST['title'] ?? ''));
                $dueDate = trim((string) ($_POST['due_date'] ?? ''));
                $classCheck = $pdo->prepare('SELECT id FROM classes WHERE id = ? AND school_id = ?');
                $classCheck->execute([$classId, $user['school_id']]);
                $subjectCheck = $pdo->prepare('SELECT id FROM subjects WHERE id = ? AND school_id = ?');
                $subjectCheck->execute([$subjectId, $user['school_id']]);
                if (!$classCheck->fetchColumn() || !$subjectCheck->fetchColumn() || $assignmentTitle === '') {
                    flash('Choose this school’s class and subject, and enter an assignment title.', 'error');
                    break;
                }
                $assignment = $pdo->prepare('INSERT INTO assignments (school_id, class_id, subject_id, teacher_id, title, description, due_date) VALUES (?, ?, ?, NULL, ?, ?, ?)');
                $assignment->execute([
                    $user['school_id'],
                    $classId,
                    $subjectId,
                    $assignmentTitle,
                    trim((string) ($_POST['description'] ?? '')) ?: null,
                    $dueDate !== '' ? $dueDate : null,
                ]);
                flash('Assignment added.');
                break;

            case 'create_timetable':
                $classId = (int) ($_POST['class_id'] ?? 0);
                $subjectId = (int) ($_POST['subject_id'] ?? 0);
                $teacherId = (int) ($_POST['teacher_id'] ?? 0);
                $day = (string) ($_POST['day_of_week'] ?? '');
                $startTime = (string) ($_POST['start_time'] ?? '');
                $endTime = (string) ($_POST['end_time'] ?? '');
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                $classCheck = $pdo->prepare('SELECT id FROM classes WHERE id = ? AND school_id = ?');
                $classCheck->execute([$classId, $user['school_id']]);
                $subjectCheck = $pdo->prepare('SELECT id FROM subjects WHERE id = ? AND school_id = ?');
                $subjectCheck->execute([$subjectId, $user['school_id']]);
                $teacherCheck = $pdo->prepare('SELECT id FROM teachers WHERE id = ? AND school_id = ?');
                $teacherCheck->execute([$teacherId, $user['school_id']]);
                if (!$classCheck->fetchColumn() || !$subjectCheck->fetchColumn() || ($teacherId > 0 && !$teacherCheck->fetchColumn()) || !in_array($day, $days, true) || !preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime) || $startTime >= $endTime) {
                    flash('Choose valid class, subject, day, and lesson times.', 'error');
                    break;
                }
                $entry = $pdo->prepare('INSERT INTO timetable_entries (school_id, class_id, subject_id, teacher_id, day_of_week, start_time, end_time, room) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $entry->execute([
                    $user['school_id'],
                    $classId,
                    $subjectId,
                    $teacherId ?: null,
                    $day,
                    $startTime,
                    $endTime,
                    trim((string) ($_POST['room'] ?? '')) ?: null,
                ]);
                flash('Timetable lesson added.');
                break;

            case 'save_attendance':
                $date = (string) ($_POST['attendance_date'] ?? '');
                $statuses = $_POST['status'] ?? [];
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !is_array($statuses)) {
                    flash('Choose a valid date and attendance records.', 'error');
                    break;
                }
                $save = $pdo->prepare(
                    'INSERT INTO attendance (school_id, student_id, attendance_date, status)
                     SELECT ?, id, ?, ? FROM students WHERE id = ? AND school_id = ?
                     ON DUPLICATE KEY UPDATE status = VALUES(status)'
                );
                foreach ($statuses as $studentId => $status) {
                    if (!ctype_digit((string) $studentId) || !in_array($status, ['present', 'absent', 'late', 'excused'], true)) {
                        continue;
                    }
                    $save->execute([$user['school_id'], $date, $status, (int) $studentId, $user['school_id']]);
                }
                flash('Attendance records saved for the selected date.');
                break;

            case 'create_invoice':
                $studentId = (int) ($_POST['student_id'] ?? 0);
                $description = trim((string) ($_POST['description'] ?? ''));
                $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
                $dueDate = trim((string) ($_POST['due_date'] ?? ''));
                $studentCheck = $pdo->prepare('SELECT id FROM students WHERE id = ? AND school_id = ?');
                $studentCheck->execute([$studentId, $user['school_id']]);
                if (!$studentCheck->fetchColumn() || $description === '' || $amount === false || $amount <= 0) {
                    flash('Choose a student, enter a fee description, and provide a valid amount.', 'error');
                    break;
                }
                $insert = $pdo->prepare('INSERT INTO fee_invoices (school_id, student_id, description, amount, due_date) VALUES (?, ?, ?, ?, ?)');
                $insert->execute([$user['school_id'], $studentId, $description, $amount, $dueDate !== '' ? $dueDate : null]);
                flash('Fee invoice created.');
                break;

            case 'record_payment':
                $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
                $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
                $paymentDate = (string) ($_POST['payment_date'] ?? '');
                $pdo->beginTransaction();
                $invoiceQuery = $pdo->prepare(
                    'SELECT amount FROM fee_invoices WHERE id = ? AND school_id = ? FOR UPDATE'
                );
                $invoiceQuery->execute([$invoiceId, $user['school_id']]);
                $invoiceAmount = $invoiceQuery->fetchColumn();
                $paidQuery = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM fee_payments WHERE invoice_id = ?');
                $paidQuery->execute([$invoiceId]);
                $paidCents = (int) round((float) $paidQuery->fetchColumn() * 100);
                $invoiceCents = $invoiceAmount === false ? 0 : (int) round((float) $invoiceAmount * 100);
                $amountCents = $amount === false ? 0 : (int) round($amount * 100);
                if ($invoiceAmount === false || $amount === false || $amountCents <= 0 || $amountCents > ($invoiceCents - $paidCents) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
                    $pdo->rollBack();
                    flash('Enter a valid payment that does not exceed the outstanding balance.', 'error');
                    break;
                }
                $payment = $pdo->prepare('INSERT INTO fee_payments (invoice_id, amount, payment_date, method, reference) VALUES (?, ?, ?, ?, ?)');
                $payment->execute([
                    $invoiceId,
                    $amount,
                    $paymentDate,
                    trim((string) ($_POST['method'] ?? '')) ?: null,
                    trim((string) ($_POST['reference'] ?? '')) ?: null,
                ]);
                $status = ($paidCents + $amountCents) >= $invoiceCents ? 'paid' : 'partial';
                $update = $pdo->prepare('UPDATE fee_invoices SET status = ? WHERE id = ? AND school_id = ?');
                $update->execute([$status, $invoiceId, $user['school_id']]);
                $pdo->commit();
                flash('Payment recorded.');
                break;

            case 'create_announcement':
                $title = trim((string) ($_POST['title'] ?? ''));
                $message = trim((string) ($_POST['message'] ?? ''));
                $channels = $_POST['channels'] ?? [];
                $allowedChannels = ['sms', 'email', 'app'];
                if ($title === '' || $message === '' || !is_array($channels) || count(array_filter($channels, 'is_string')) !== count($channels) || array_diff($channels, $allowedChannels) !== [] || $channels === []) {
                    flash('Enter an announcement and choose at least one delivery channel.', 'error');
                    break;
                }
                $pdo->beginTransaction();
                $announcement = $pdo->prepare('INSERT INTO announcements (school_id, author_id, title, message, channels, audience) VALUES (?, ?, ?, ?, ?, ?)');
                $announcement->execute([$user['school_id'], $user['id'], $title, $message, json_encode(array_values(array_unique($channels)), JSON_THROW_ON_ERROR), 'all_parents']);
                $announcementId = (int) $pdo->lastInsertId();
                $contacts = $pdo->prepare(
                    'SELECT DISTINCT g.id, g.email, g.phone FROM guardians g
                     JOIN student_guardians sg ON sg.guardian_id = g.id
                     WHERE g.school_id = ?'
                );
                $contacts->execute([$user['school_id']]);
                $recipients = $contacts->fetchAll();
                $queue = $pdo->prepare('INSERT INTO notification_outbox (school_id, announcement_id, channel, recipient, message) VALUES (?, ?, ?, ?, ?)');
                $queuedCount = 0;
                foreach ($channels as $channel) {
                    foreach ($recipients as $recipient) {
                        $destination = match ($channel) {
                            'sms' => $recipient['phone'],
                            'email' => $recipient['email'],
                            'app' => 'guardian:' . $recipient['id'],
                        };
                        if ($destination !== null && $destination !== '') {
                            $queue->execute([$user['school_id'], $announcementId, $channel, $destination, $message]);
                            $queuedCount++;
                        }
                    }
                }
                $pdo->commit();
                flash(t('Announcement saved; {{count}} notification(s) queued. Run the email outbox worker for email; other channels need their own integrations.', ['{{count}}' => (string) $queuedCount]));
                break;

            default:
                flash('That action is not available.', 'error');
        }
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        delete_profile_picture($newProfilePicture);
        error_log('School management action failed: ' . $exception->getMessage());
        $message = $exception->getCode() === '23000'
            ? 'That record already exists or conflicts with another record.'
            : 'The record could not be saved. Please check your details and try again.';
        flash($message, 'error');
    } catch (RuntimeException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        delete_profile_picture($newProfilePicture);
        flash($exception->getMessage(), 'error');
    } catch (JsonException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Announcement encoding failed: ' . $exception->getMessage());
        flash('The announcement could not be saved.', 'error');
    }

    redirect('index.php?page=' . rawurlencode($returnPage));
}

[$title, $file] = $pages[$page];
render_header($title, $page);
require $file;
render_footer();
