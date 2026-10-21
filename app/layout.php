<?php
declare(strict_types=1);

function render_language_selector(string $class = 'language-picker'): void
{
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    $query = $_GET;
    unset($query['lang']);
    ?>
    <form class="<?= e($class) ?>" method="get" action="<?= e($script) ?>">
        <?php foreach ($query as $key => $value): ?>
            <?php if (is_string($key) && is_scalar($value)): ?>
                <input type="hidden" name="<?= e($key) ?>" value="<?= e((string) $value) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <label class="visually-hidden" for="language-select"><?= e(t('Language')) ?></label>
        <select id="language-select" name="lang" aria-label="<?= e(t('Language')) ?>" onchange="this.form.submit()">
            <?php foreach (['en' => 'English', 'fr' => 'French', 'sw' => 'Kiswahili'] as $code => $label): ?>
                <option value="<?= e($code) ?>"<?= current_language() === $code ? ' selected' : '' ?>><?= e(t($label)) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php
}

function render_header(string $title, string $activePage): void
{
    $user = current_user();
    $flash = take_flash();
    $navigation = [
        'dashboard' => ['Dashboard', '⌂'],
        'students' => ['Students', '♧'],
        'classes' => ['Classes', '▦'],
        'academics' => ['Academics', '✎'],
        'teachers' => ['Staff', '♙'],
        'attendance' => ['Attendance', '◷'],
        'fees' => ['Fees', '◇'],
        'communications' => ['Communications', '↗'],
        'parent' => ['Parent portal', '♧'],
    ];
    ?>
    <!doctype html>
    <html lang="<?= e(current_language()) ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e(t($title)) ?> · <?= e($user['school_name']) ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="assets/css/app.css">
    </head>
    <body>
    <div class="app-shell">
        <aside class="sidebar">
            <a class="brand" href="index.php">
                <span class="brand-mark">S<span>+</span></span>
                <span class="brand-copy"><strong><?= e($user['school_name']) ?></strong><small><?= e(t('School management')) ?></small></span>
            </a>
            <div class="nav-label"><?= e(t('WORKSPACE')) ?></div>
            <nav class="main-nav" aria-label="<?= e(t('Main navigation')) ?>">
                <?php foreach ($navigation as $key => [$label, $icon]): ?>
                    <?php if (!can_access_page($key)) { continue; } ?>
                    <a class="nav-link<?= $activePage === $key ? ' active' : '' ?>" href="index.php?page=<?= e($key) ?>">
                        <span class="nav-icon"><?= e($icon) ?></span><span><?= e(t($label)) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar-bottom">
                <div class="help-card">
                    <span class="help-icon">✳</span>
                    <strong><?= e(t('Here to help')) ?></strong>
                    <p><?= e(t('Keep your school running smoothly, all in one place.')) ?></p>
                </div>
                <div class="profile-row">
                    <a class="avatar-link" href="index.php?page=profile" aria-label="<?= e(t('My profile')) ?>">
                        <?php if ($user['profile_picture'] !== ''): ?><img class="avatar" src="<?= e($user['profile_picture']) ?>" alt=""><?php else: ?><span class="avatar"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></span><?php endif; ?>
                    </a>
                    <a class="profile-copy" href="index.php?page=profile"><strong><?= e($user['name']) ?></strong><small><?= e(role_label($user['role'])) ?> · <?= e(t('My profile')) ?></small></a>
                    <a class="logout-link" href="logout.php" aria-label="<?= e(t('Sign out')) ?>" title="<?= e(t('Sign out')) ?>">↪</a>
                </div>
            </div>
        </aside>
        <main class="main-area">
            <header class="topbar">
                <div class="breadcrumb"><?= e(t('School workspace')) ?> <span>/</span> <strong><?= e(t($title)) ?></strong></div>
                <div class="topbar-right"><?= render_language_selector() ?><span class="today"><?= e(localized_date(time(), 'full')) ?></span><span class="top-avatar"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></span></div>
            </header>
            <div class="page-content">
                <?php if ($flash !== null): ?>
                    <div class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e(t($flash['message'])) ?></div>
                <?php endif; ?>
    <?php
}

function render_footer(): void
{
    ?>
            </div>
        </main>
    </div>
    </body>
    </html>
    <?php
}
