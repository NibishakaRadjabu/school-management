<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_language(): string
{
    return in_array($_SESSION['language'] ?? '', ['en', 'fr', 'sw'], true)
        ? $_SESSION['language']
        : 'en';
}

if (isset($_GET['lang']) && is_string($_GET['lang']) && in_array($_GET['lang'], ['en', 'fr', 'sw'], true)) {
    $_SESSION['language'] = $_GET['lang'];
}

function t(string $message, array $replacements = []): string
{
    static $translations;
    $translations ??= require __DIR__ . '/translations.php';

    $translated = $translations[current_language()][$message] ?? $message;
    foreach ($replacements as $placeholder => $replacement) {
        $translated = str_replace((string) $placeholder, (string) $replacement, $translated);
    }

    return $translated;
}

function localized_date(string|int $date, string $style = 'medium'): string
{
    $timestamp = is_int($date) ? $date : strtotime($date);
    if ($timestamp === false) {
        return (string) $date;
    }

    $weekday = [
        'fr' => ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'],
        'sw' => ['Jumapili', 'Jumatatu', 'Jumanne', 'Jumatano', 'Alhamisi', 'Ijumaa', 'Jumamosi'],
    ];
    $monthsShort = [
        'fr' => ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'],
        'sw' => ['Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun', 'Jul', 'Ago', 'Sep', 'Okt', 'Nov', 'Des'],
    ];
    $monthsLong = [
        'fr' => ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
        'sw' => ['Januari', 'Februari', 'Machi', 'Aprili', 'Mei', 'Juni', 'Julai', 'Agosti', 'Septemba', 'Oktoba', 'Novemba', 'Desemba'],
    ];
    $language = current_language();
    $day = (int) date('w', $timestamp);
    $month = (int) date('n', $timestamp) - 1;
    $shortMonth = $language === 'en' ? date('M', $timestamp) : $monthsShort[$language][$month];
    $longMonth = $language === 'en' ? date('F', $timestamp) : $monthsLong[$language][$month];

    if ($style === 'full') {
        $dayName = $language === 'en' ? date('l', $timestamp) : $weekday[$language][$day];
        return ucfirst($dayName) . ', ' . date('j', $timestamp) . ' ' . $shortMonth . ' ' . date('Y', $timestamp);
    }

    if ($style === 'long') {
        return date('j', $timestamp) . ' ' . $longMonth . ' ' . date('Y', $timestamp);
    }

    return date('j', $timestamp) . ' ' . $shortMonth . ' ' . date('Y', $timestamp);
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function render_application_error(Throwable $exception): never
{
    error_log('Unhandled application exception: ' . $exception->getMessage());
    http_response_code($exception instanceof PDOException ? 503 : 500);

    if ($exception instanceof PDOException) {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        if ($driverCode === 2002 || $driverCode === 2003) {
            $heading = 'MySQL is not accepting connections';
            $message = 'Start MySQL from the XAMPP Control Panel, then refresh this page. If MySQL is already running, check that its port matches DB_PORT in your database configuration.';
        } elseif ($driverCode === 1049) {
            $heading = 'The school database has not been created';
            $message = 'Import schema.sql using phpMyAdmin, then refresh this page.';
        } elseif ($driverCode === 1045) {
            $heading = 'MySQL could not sign in';
            $message = 'Check the DB_USER and DB_PASSWORD settings in config/database.php or your PHP environment.';
        } else {
            $heading = 'The school database is temporarily unavailable';
            $message = 'Check that MySQL is running, that schema.sql has been imported, and that your database connection settings are correct.';
        }
    } else {
        $heading = 'Something went wrong';
        $message = 'The request could not be completed. Please try again, or ask your administrator to check the PHP error log.';
    }
    ?>
    <!doctype html>
    <html lang="<?= e(current_language()) ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($heading) ?> · Schoolhouse</title>
        <style>
            * { box-sizing: border-box; }
            body { display: grid; min-height: 100vh; margin: 0; place-items: center; padding: 24px; background: #f7f8fc; color: #262b40; font: 15px/1.6 "Segoe UI", Arial, sans-serif; }
            main { width: min(100%, 520px); padding: 36px; border: 1px solid #eceef3; border-radius: 14px; background: #fff; box-shadow: 0 12px 40px #1d27400d; }
            .mark { display: grid; width: 42px; height: 42px; margin-bottom: 22px; place-items: center; border-radius: 12px; background: #f0efff; color: #6258d7; font-weight: 800; }
            h1 { margin: 0 0 10px; font-size: 22px; }
            p { margin: 0; color: #737c8d; }
            .hint { margin-top: 22px; padding-top: 18px; border-top: 1px solid #eceef3; color: #9299a7; font-size: 12px; }
            button { margin-top: 22px; padding: 11px 16px; border: 0; border-radius: 8px; background: #6258d7; color: #fff; cursor: pointer; font: inherit; font-size: 13px; font-weight: 700; }
        </style>
    </head>
    <body>
        <main>
            <div class="mark">S+</div>
            <h1><?= e(t($heading)) ?></h1>
            <p><?= e(t($message)) ?></p>
            <div class="hint"><?= e(t('For local XAMPP setup, open the Control Panel and make sure MySQL is running alongside Apache.')) ?></div>
            <form method="get">
                <label class="visually-hidden" for="error-language"><?= e(t('Language')) ?></label>
                <select id="error-language" name="lang" onchange="this.form.submit()">
                    <?php foreach (['en' => 'English', 'fr' => 'French', 'sw' => 'Kiswahili'] as $code => $label): ?>
                        <option value="<?= e($code) ?>"<?= current_language() === $code ? ' selected' : '' ?>><?= e(t($label)) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <button type="button" onclick="window.location.reload()"><?= e(t('Try again')) ?></button>
        </main>
    </body>
    </html>
    <?php
    exit;
}

set_exception_handler(static function (Throwable $exception): void {
    render_application_error($exception);
});

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        exit(e(t('Your session has expired. Please go back, refresh the page, and try again.')));
    }
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $message;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['school_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
    $account = database()->prepare(
        'SELECT u.is_active, u.name, u.email, u.profile_picture, r.name AS role
         FROM users u JOIN roles r ON r.id = u.role_id
         WHERE u.id = ? AND u.school_id = ?'
    );
    $account->execute([(int) $_SESSION['user_id'], (int) $_SESSION['school_id']]);
    $status = $account->fetch();
    if (!$status || !(bool) $status['is_active']) {
        $_SESSION = [];
        session_regenerate_id(true);
        redirect('login.php');
    }
    $_SESSION['user_role'] = $status['role'];
    $_SESSION['user_name'] = $status['name'];
    $_SESSION['user_email'] = $status['email'];
    $_SESSION['profile_picture'] = (string) ($status['profile_picture'] ?? '');
}

function require_admin(): void
{
    require_login();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit(e(t('Administrator access is required to manage this school workspace.')));
    }
}

function role_pages(string $role): array
{
    return match ($role) {
        'admin' => ['dashboard', 'students', 'classes', 'academics', 'teachers', 'attendance', 'fees', 'communications', 'profile'],
        'teacher' => ['dashboard', 'students', 'academics', 'attendance', 'profile'],
        'accountant' => ['dashboard', 'fees', 'profile'],
        'dos', 'head_of_studies' => ['dashboard', 'students', 'classes', 'academics', 'attendance', 'profile'],
        'behavior_officer' => ['dashboard', 'students', 'attendance', 'communications', 'profile'],
        'parent' => ['parent', 'profile'],
        'student' => ['profile'],
        default => ['dashboard'],
    };
}

function can_access_page(string $page): bool
{
    return in_array($page, role_pages((string) ($_SESSION['user_role'] ?? '')), true);
}

function can_edit_student_records(): bool
{
    return in_array((string) ($_SESSION['user_role'] ?? ''), ['admin', 'dos', 'behavior_officer'], true);
}

function role_label(string $role): string
{
    $labels = [
        'admin' => 'Administrator',
        'teacher' => 'Teacher',
        'accountant' => 'Accountant',
        'dos' => 'Director of Studies (DOS)',
        'head_of_studies' => 'Head of Studies',
        'behavior_officer' => 'Behavior Officer',
        'parent' => 'Parent',
        'student' => 'Student',
    ];

    return t($labels[$role] ?? ucfirst(str_replace('_', ' ', $role)));
}

function require_page_access(string $page): void
{
    require_login();
    if (!can_access_page($page)) {
        http_response_code(403);
        exit(e(t('You do not have permission to access this section.')));
    }
}

function current_user(): array
{
    return [
        'id' => (int) $_SESSION['user_id'],
        'school_id' => (int) $_SESSION['school_id'],
        'name' => (string) $_SESSION['user_name'],
        'email' => (string) ($_SESSION['user_email'] ?? ''),
        'role' => (string) $_SESSION['user_role'],
        'school_name' => (string) $_SESSION['school_name'],
        'profile_picture' => (string) ($_SESSION['profile_picture'] ?? ''),
    ];
}

function generate_index_code(PDO $pdo, string $entity, int $schoolId): string
{
    $table = match ($entity) {
        'student' => ['students', 'STU'],
        'staff' => ['teachers', 'STF'],
        default => throw new InvalidArgumentException('Unsupported index code entity.'),
    };
    [$tableName, $prefix] = $table;
    $lookup = $pdo->prepare("SELECT 1 FROM {$tableName} WHERE school_id = ? AND index_code = ? LIMIT 1");

    do {
        $code = $prefix . '-' . strtoupper(bin2hex(random_bytes(6)));
        $lookup->execute([$schoolId, $code]);
    } while ($lookup->fetchColumn());

    return $code;
}

function save_profile_picture(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK
        || !isset($file['tmp_name'], $file['size'])
        || !is_string($file['tmp_name'])
        || !is_numeric($file['size'])
        || (int) $file['size'] <= 0
        || (int) $file['size'] > 3 * 1024 * 1024
        || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The profile picture upload failed or is larger than 3 MB.');
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if ($imageInfo === false || !isset($extensions[$mime]) || $imageInfo['mime'] !== $mime || $imageInfo[0] < 1 || $imageInfo[1] < 1 || $imageInfo[0] > 6000 || $imageInfo[1] > 6000) {
        throw new RuntimeException('Upload a valid JPG, PNG, or WebP profile picture.');
    }

    $directory = __DIR__ . '/../assets/uploads/staff';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The profile picture directory could not be created.');
    }

    $filename = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('The profile picture could not be saved.');
    }

    return 'assets/uploads/staff/' . $filename;
}

function delete_profile_picture(?string $path): void
{
    if ($path === null || !preg_match('~\Aassets/uploads/staff/[a-f0-9]{40}\.(?:jpg|png|webp)\z~', $path)) {
        return;
    }
    $fullPath = __DIR__ . '/../' . $path;
    if (is_file($fullPath) && !unlink($fullPath)) {
        error_log('Could not remove profile picture: ' . $path);
    }
}

function school_is_registered(): bool
{
    return (int) database()->query('SELECT COUNT(*) FROM schools')->fetchColumn() > 0;
}
