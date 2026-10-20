<?php
$schoolId = current_user()['school_id'];
$staffQuery = database()->prepare(
    'SELECT t.id, t.employee_number, t.name, t.email, t.phone, t.job_title, t.status,
            t.user_id, u.profile_picture, r.name AS role_name
     FROM teachers t LEFT JOIN users u ON u.id = t.user_id
     LEFT JOIN roles r ON r.id = u.role_id
     WHERE t.school_id = ? ORDER BY t.created_at DESC'
);
$staffQuery->execute([$schoolId]);
$staff = $staffQuery->fetchAll();
$staffWithoutAccounts = array_filter($staff, static fn(array $member): bool => $member['user_id'] === null);
$staffRoles = [
    'admin' => 'Administrator',
    'teacher' => 'Teacher',
    'accountant' => 'Accountant',
    'dos' => 'Director of Studies (DOS)',
    'head_of_studies' => 'Head of Studies',
    'behavior_officer' => 'Behavior Officer',
];
$editingId = filter_input(INPUT_GET, 'edit_staff', FILTER_VALIDATE_INT) ?: 0;
$editingStaff = null;
foreach ($staff as $member) {
    if ((int) $member['id'] === $editingId) {
        $editingStaff = $member;
        break;
    }
}
?>
<section class="page-heading"><div><span class="eyebrow"><?= e(t('PEOPLE & CULTURE')) ?></span><h1><?= e(t('Your staff team')) ?></h1><p><?= e(t('Keep staff contact details and roles in one place.')) ?></p></div></section>
<section class="content-grid">
    <article class="panel form-panel">
        <div class="panel-heading"><div><h2><?= e(t('Add a staff member')) ?></h2><p><?= e(t('Create a staff profile and individual sign-in account.')) ?></p></div><span class="panel-icon">♙</span></div>
        <form method="post" action="index.php?page=teachers" enctype="multipart/form-data" class="form-stack compact-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create_teacher">
            <label><?= e(t('Full name')) ?><input name="name" required maxlength="150" placeholder="<?= e(t('e.g. Jordan Lee')) ?>"></label>
            <label><?= e(t('Employee number')) ?><input name="employee_number" required maxlength="50" placeholder="<?= e(t('e.g. EMP-001')) ?>"></label>
            <label><?= e(t('Staff role')) ?><select name="role" required><?php foreach ($staffRoles as $role => $label): ?><option value="<?= e($role) ?>"><?= e(t($label)) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('Job title')) ?><input name="job_title" maxlength="100" placeholder="<?= e(t('e.g. Primary teacher')) ?>"></label>
            <label><?= e(t('Login email')) ?><input type="email" name="email" maxlength="190" required autocomplete="off"></label>
            <label><?= e(t('Initial password')) ?><input type="password" name="password" minlength="10" required autocomplete="new-password"><small><?= e(t('At least 10 characters. Share it securely; staff can use it to sign in.')) ?></small></label>
            <label><?= e(t('Phone number')) ?><input name="phone" maxlength="40"></label>
            <label><?= e(t('Profile picture')) ?><input type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp"><small><?= e(t('JPG, PNG, or WebP; maximum 3 MB.')) ?></small></label>
            <button class="button button-primary" type="submit"><?= e(t('Add to staff')) ?> <span>→</span></button>
        </form>
    </article>
    <article class="panel form-panel staff-summary">
        <div class="panel-heading"><div><h2><?= e(t('Your team')) ?></h2><p><?= e(t('A growing school team.')) ?></p></div><span class="panel-icon">✳</span></div>
        <div class="team-number"><?= number_format(count($staff)) ?></div><span class="team-caption"><?= e(t('active staff members')) ?></span>
        <div class="team-note"><?= e(t('Each staff member signs in with their own email and password. Access to pages is based on their role.')) ?></div>
    </article>
</section>
<?php if ($staffWithoutAccounts !== []): ?>
<section class="panel form-panel" id="create-account">
    <div class="panel-heading"><div><h2><?= e(t('Create an account for existing staff')) ?></h2><p><?= e(t('Give a staff member already in the directory their own sign-in.')) ?></p></div><span class="panel-icon">↗</span></div>
    <form method="post" action="index.php?page=teachers#create-account" enctype="multipart/form-data" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create_teacher_account">
        <div class="form-grid form-grid-three">
            <label><?= e(t('Staff member')) ?><select name="teacher_id" required><option value=""><?= e(t('Choose a staff member')) ?></option><?php foreach ($staffWithoutAccounts as $member): ?><option value="<?= (int) $member['id'] ?>"><?= e($member['name'] . ' · ' . $member['employee_number']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('Staff role')) ?><select name="role" required><?php foreach ($staffRoles as $role => $label): ?><option value="<?= e($role) ?>"><?= e(t($label)) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('Login email')) ?><input type="email" name="email" maxlength="190" required autocomplete="off"></label>
            <label><?= e(t('Initial password')) ?><input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
            <label><?= e(t('Profile picture')) ?><input type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp"><small><?= e(t('JPG, PNG, or WebP; maximum 3 MB.')) ?></small></label>
        </div>
        <div><button class="button button-secondary" type="submit"><?= e(t('Create staff account')) ?> <span>→</span></button></div>
    </form>
</section>
<?php endif; ?>
<?php if ($editingStaff !== null): ?>
<section class="panel form-panel" id="edit-staff">
    <div class="panel-heading"><div><h2><?= e(t('Edit staff profile')) ?></h2><p><?= e($editingStaff['name']) ?></p></div></div>
    <form method="post" action="index.php?page=teachers#edit-staff" enctype="multipart/form-data" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_teacher"><input type="hidden" name="teacher_id" value="<?= (int) $editingStaff['id'] ?>">
        <div class="form-grid form-grid-three">
            <label><?= e(t('Full name')) ?><input name="name" value="<?= e($editingStaff['name']) ?>" maxlength="150" required></label>
            <label><?= e(t('Employee number')) ?><input name="employee_number" value="<?= e($editingStaff['employee_number']) ?>" maxlength="50" required></label>
            <label><?= e(t('Login email')) ?><input type="email" name="email" value="<?= e($editingStaff['email'] ?? '') ?>" maxlength="190" required></label>
            <label><?= e(t('Phone number')) ?><input name="phone" value="<?= e($editingStaff['phone'] ?? '') ?>" maxlength="40"></label>
            <label><?= e(t('Job title')) ?><input name="job_title" value="<?= e($editingStaff['job_title'] ?? '') ?>" maxlength="100"></label>
            <label><?= e(t('Status')) ?><select name="status" required><option value="active"<?= $editingStaff['status'] === 'active' ? ' selected' : '' ?>><?= e(t('Active')) ?></option><option value="inactive"<?= $editingStaff['status'] === 'inactive' ? ' selected' : '' ?>><?= e(t('Inactive')) ?></option></select></label>
            <?php if ($editingStaff['user_id'] !== null): ?>
                <label><?= e(t('Staff role')) ?><select name="role" required><?php foreach ($staffRoles as $role => $label): ?><option value="<?= e($role) ?>"<?= $editingStaff['role_name'] === $role ? ' selected' : '' ?>><?= e(t($label)) ?></option><?php endforeach; ?></select></label>
                <label><?= e(t('Set a new password')) ?><input type="password" name="new_password" minlength="10" autocomplete="new-password"><small><?= e(t('Leave blank to keep the current password.')) ?></small></label>
            <?php else: ?>
                <input type="hidden" name="role" value="">
                <p class="form-note"><?= e(t('This staff member has no sign-in account yet. Create one from the staff directory first.')) ?></p>
            <?php endif; ?>
            <label><?= e(t('Profile picture')) ?><input type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp"><small><?= e(t('JPG, PNG, or WebP; maximum 3 MB.')) ?></small></label>
        </div>
        <?php if ($editingStaff['profile_picture']): ?><label class="checkbox-row"><input type="checkbox" name="remove_picture" value="1"> <?= e(t('Remove profile picture')) ?></label><?php endif; ?>
        <div class="form-actions"><button class="button button-primary" type="submit"><?= e(t('Save changes')) ?> <span>→</span></button><a class="button button-secondary" href="index.php?page=teachers"><?= e(t('Cancel')) ?></a></div>
    </form>
</section>
<?php endif; ?>
<section class="panel recent-panel">
    <div class="panel-heading"><div><h2><?= e(t('Staff directory')) ?></h2><p><?= count($staff) ?> <?= e(t('team members')) ?></p></div></div>
    <?php if ($staff === []): ?><div class="empty-state compact"><span class="empty-icon">♙</span><p><?= e(t('Your staff directory is empty. Add your first team member above.')) ?></p></div><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th><?= e(t('TEAM MEMBER')) ?></th><th><?= e(t('EMPLOYEE NO.')) ?></th><th><?= e(t('Staff role')) ?></th><th><?= e(t('CONTACT')) ?></th><th><?= e(t('Account')) ?></th><th><?= e(t('STATUS')) ?></th><th><?= e(t('Actions')) ?></th></tr></thead><tbody>
        <?php foreach ($staff as $member): ?><tr><td><span class="student-cell"><?php if ($member['profile_picture']): ?><img class="table-avatar" src="<?= e($member['profile_picture']) ?>" alt=""><?php else: ?><span class="table-avatar avatar-violet"><?= e(strtoupper(substr($member['name'], 0, 1))) ?></span><?php endif; ?><strong><?= e($member['name']) ?></strong></span></td><td><?= e($member['employee_number']) ?></td><td><?= e($member['role_name'] ? t($staffRoles[$member['role_name']] ?? $member['role_name']) : ($member['job_title'] ?? '—')) ?></td><td><span class="cell-primary"><?= e($member['email'] ?? '—') ?></span><small class="cell-secondary"><?= e($member['phone'] ?? '') ?></small></td><td><?php if ($member['user_id'] !== null): ?><span class="status-pill status-active"><?= e(t('Account ready')) ?></span><?php else: ?><a class="text-link" href="#create-account"><?= e(t('Create account')) ?></a><?php endif; ?></td><td><span class="status-pill status-<?= e($member['status']) ?>"><?= e(t(ucfirst($member['status']))) ?></span></td><td><div class="table-actions"><a class="text-link" href="index.php?page=teachers&amp;edit_staff=<?= (int) $member['id'] ?>#edit-staff"><?= e(t('Edit')) ?></a><form method="post" action="index.php?page=teachers" onsubmit="return confirm(<?= e(json_encode(t('Delete this staff member? Their sign-in account will also be deleted if linked.'))) ?>)"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_teacher"><input type="hidden" name="teacher_id" value="<?= (int) $member['id'] ?>"><button class="text-button text-danger" type="submit"><?= e(t('Delete')) ?></button></form></div></td></tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
