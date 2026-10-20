<?php
$user = current_user();
$profileQuery = database()->prepare(
    'SELECT u.name, u.email, u.phone, u.profile_picture, COALESCE(t.index_code, s.index_code) AS index_code
     FROM users u LEFT JOIN teachers t ON t.user_id = u.id AND t.school_id = u.school_id
     LEFT JOIN students s ON s.user_id = u.id AND s.school_id = u.school_id
     WHERE u.id = ? AND u.school_id = ?'
);
$profileQuery->execute([$user['id'], $user['school_id']]);
$profile = $profileQuery->fetch();
$otherAdministrators = 0;
if ($user['role'] === 'admin') {
    $adminQuery = database()->prepare(
        'SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
         WHERE u.school_id = ? AND u.is_active = 1 AND r.name = "admin" AND u.id <> ?'
    );
    $adminQuery->execute([$user['school_id'], $user['id']]);
    $otherAdministrators = (int) $adminQuery->fetchColumn();
}
?>
<section class="page-heading"><div><span class="eyebrow"><?= e(t('ACCOUNT SETTINGS')) ?></span><h1><?= e(t('My profile')) ?></h1><p><?= e(t('Manage your personal details and sign-in password.')) ?></p></div></section>
<section class="profile-card profile-card-self">
    <div class="profile-card-header">
        <?php if ($profile['profile_picture']): ?><img class="profile-card-avatar" src="<?= e($profile['profile_picture']) ?>" alt="<?= e(t('Profile picture')) ?>"><?php else: ?><span class="profile-card-avatar profile-card-initial"><?= e(strtoupper(substr($profile['name'], 0, 1))) ?></span><?php endif; ?>
        <div class="profile-card-identity"><span class="eyebrow"><?= e(role_label($user['role'])) ?></span><h2><?= e($profile['name']) ?></h2><span><?= e($profile['email']) ?></span></div>
    </div>
    <div class="profile-card-details">
        <div><small><?= e(t('Phone number')) ?></small><strong><?= e($profile['phone'] ?: '—') ?></strong></div>
        <?php if ($profile['index_code']): ?><div><small><?= e(t('INDEX CODE')) ?></small><strong><?= e($profile['index_code']) ?></strong></div><?php endif; ?>
    </div>
</section>
<section class="panel form-panel profile-panel">
    <div class="panel-heading"><div><h2><?= e(t('Personal details')) ?></h2><p><?= e(t('Keep your account information up to date.')) ?></p></div>
        <?php if ($profile['profile_picture']): ?><img class="profile-preview" src="<?= e($profile['profile_picture']) ?>" alt="<?= e(t('Profile picture')) ?>"><?php else: ?><span class="profile-preview profile-initial"><?= e(strtoupper(substr($profile['name'], 0, 1))) ?></span><?php endif; ?>
    </div>
    <form method="post" action="index.php?page=profile" enctype="multipart/form-data" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_profile">
        <div class="form-grid">
            <label><?= e(t('Full name')) ?><input name="name" value="<?= e($profile['name']) ?>" required maxlength="150"></label>
            <label><?= e(t('Login email')) ?><input type="email" name="email" value="<?= e($profile['email']) ?>" required maxlength="190" autocomplete="email"></label>
            <label><?= e(t('Phone number')) ?><input name="phone" value="<?= e($profile['phone'] ?? '') ?>" maxlength="40"></label>
            <label><?= e(t('Profile picture')) ?><input type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp"><small><?= e(t('JPG, PNG, or WebP; maximum 3 MB.')) ?></small></label>
        </div>
        <?php if ($profile['profile_picture']): ?><label class="checkbox-row"><input type="checkbox" name="remove_picture" value="1"> <?= e(t('Remove profile picture')) ?></label><?php endif; ?>
        <div class="inline-section-title"><div><h3><?= e(t('Change password')) ?></h3><p><?= e(t('Leave both new password fields blank to keep your current password.')) ?></p></div></div>
        <div class="form-grid">
            <label><?= e(t('Current password')) ?><input type="password" name="current_password" autocomplete="current-password"></label>
            <label><?= e(t('New password')) ?><input type="password" name="new_password" minlength="10" autocomplete="new-password"><small><?= e(t('At least 10 characters.')) ?></small></label>
        </div>
        <button class="button button-primary" type="submit"><?= e(t('Save profile')) ?> <span>→</span></button>
    </form>
</section>
<?php if ($user['role'] === 'admin'): ?>
<section class="panel form-panel profile-panel danger-panel">
    <div class="panel-heading"><div><h2><?= e(t('Delete administrator account')) ?></h2><p><?= e(t('This removes your sign-in account. School records are kept.')) ?></p></div></div>
    <?php if ($otherAdministrators === 0): ?>
        <p class="form-note"><?= e(t('Add another active administrator from the staff page before deleting this account.')) ?></p>
        <a class="button button-secondary" href="index.php?page=teachers"><?= e(t('Manage staff')) ?> →</a>
    <?php else: ?>
        <p><?= e(t('There are {{count}} other active administrator(s) who can continue managing the school.', ['{{count}}' => (string) $otherAdministrators])) ?></p>
        <form method="post" action="index.php?page=profile" class="form-stack" onsubmit="return confirm(<?= e(json_encode(t('Delete your administrator account? You will be signed out and cannot undo this.'))) ?>)">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_my_account">
            <label><?= e(t('Current password')) ?><input type="password" name="current_password" required autocomplete="current-password"></label>
            <button class="button button-danger" type="submit"><?= e(t('Delete my account')) ?></button>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>
