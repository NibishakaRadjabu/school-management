<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

if (!school_is_registered()) {
    redirect('setup.php');
}
require_login();
header('Cache-Control: private, no-store');

$user = current_user();
if (!in_array($user['role'], ['admin', 'behavior_officer'], true)) {
    http_response_code(403);
    exit(e(t('You do not have permission to view behavior deduction records.')));
}

$deductionId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($deductionId === false || $deductionId < 1) {
    http_response_code(404);
    exit(e(t('Behavior deduction record not found.')));
}

$query = database()->prepare(
    'SELECT bpd.id, bpd.points_deducted, bpd.misconduct, bpd.student_signature, bpd.officer_signature, bpd.created_at,
            s.first_name, s.last_name, s.admission_number, c.name AS class_name,
            u.name AS recorded_by_name, sch.name AS school_name,
            GREATEST(0, 100 - (
                SELECT COALESCE(SUM(all_deductions.points_deducted), 0)
                FROM behavior_point_deductions all_deductions
                WHERE all_deductions.school_id = bpd.school_id AND all_deductions.student_id = bpd.student_id
                    AND all_deductions.id <= bpd.id
            )) AS behavior_score
     FROM behavior_point_deductions bpd
     JOIN students s ON s.id = bpd.student_id AND s.school_id = bpd.school_id
     LEFT JOIN classes c ON c.id = s.class_id
     LEFT JOIN users u ON u.id = bpd.recorded_by
     JOIN schools sch ON sch.id = bpd.school_id
     WHERE bpd.id = ? AND bpd.school_id = ?'
);
$query->execute([$deductionId, $user['school_id']]);
$record = $query->fetch();
if (!$record) {
    http_response_code(404);
    exit(e(t('Behavior deduction record not found.')));
}
?>
<!doctype html>
<html lang="<?= e(current_language()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(t('Behavior deduction record')) ?> · <?= e($record['school_name']) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px; color: #202638; background: #f4f5f8; font: 15px/1.5 Arial, sans-serif; }
        .record { max-width: 850px; margin: 0 auto; padding: 40px; background: white; border: 1px solid #dfe2e8; }
        .actions { max-width: 850px; margin: 0 auto 16px; display: flex; gap: 12px; }
        button, .actions a { padding: 10px 16px; border: 0; border-radius: 6px; background: #5145cd; color: white; text-decoration: none; font: inherit; cursor: pointer; }
        .school { color: #5145cd; font-size: 13px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
        h1 { margin: 8px 0 4px; font-size: 28px; }
        .subtitle { margin: 0 0 28px; color: #687084; }
        .details { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin: 20px 0; }
        .detail { padding: 13px; background: #f7f8fb; border-radius: 6px; }
        .detail small { display: block; margin-bottom: 4px; color: #687084; }
        .misconduct { min-height: 90px; margin: 12px 0 25px; padding: 16px; border: 1px solid #dfe2e8; white-space: pre-wrap; overflow-wrap: anywhere; }
        .signatures { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 28px; margin-top: 30px; }
        .signature { min-height: 145px; padding: 12px; border: 1px solid #dfe2e8; }
        .signature img { display: block; width: 100%; max-width: 600px; height: 105px; object-fit: contain; object-position: left center; }
        .signature-missing { height: 105px; color: #687084; font-size: 34px; }
        .signature strong { display: block; margin-top: 8px; }
        .signature small { color: #687084; }
        @media (max-width: 600px) { body { padding: 12px; } .record { padding: 22px; } .details, .signatures { grid-template-columns: 1fr; } }
        @media print {
            body { padding: 0; background: #fff; }
            .actions { display: none; }
            .record { max-width: none; border: 0; padding: 0; }
            .detail { background: #fff; border: 1px solid #dfe2e8; }
        }
    </style>
</head>
<body>
<div class="actions"><button type="button" onclick="window.print()"><?= e(t('Print record')) ?></button><a href="index.php?page=students"><?= e(t('Back to student register')) ?></a></div>
<main class="record">
    <div class="school"><?= e($record['school_name']) ?></div>
    <h1><?= e(t('Behavior deduction record')) ?></h1>
    <p class="subtitle"><?= e(t('Official record of a student behavior-point deduction.')) ?></p>
    <div class="details">
        <div class="detail"><small><?= e(t('Student')) ?></small><strong><?= e($record['first_name'] . ' ' . $record['last_name']) ?></strong></div>
        <div class="detail"><small><?= e(t('Admission number')) ?></small><strong><?= e($record['admission_number']) ?></strong></div>
        <div class="detail"><small><?= e(t('Class')) ?></small><strong><?= e($record['class_name'] ?? t('Unassigned')) ?></strong></div>
        <div class="detail"><small><?= e(t('Recorded at')) ?></small><strong><?= e(date('Y-m-d H:i:s', strtotime($record['created_at']))) ?></strong></div>
        <div class="detail"><small><?= e(t('Points deducted')) ?></small><strong><?= (int) $record['points_deducted'] ?></strong></div>
        <div class="detail"><small><?= e(t('Remaining behavior points')) ?></small><strong><?= (int) $record['behavior_score'] ?> / 100</strong></div>
    </div>
    <h2><?= e(t('Specific misconduct')) ?></h2>
    <div class="misconduct"><?= e($record['misconduct']) ?></div>
    <div class="signatures">
        <div class="signature">
            <?php if ($record['student_signature'] !== null): ?>
            <img src="data:image/png;base64,<?= e(base64_encode($record['student_signature'])) ?>" alt="<?= e(t('Student signature')) ?>">
            <?php else: ?><div class="signature-missing">—</div><?php endif; ?>
            <strong><?= e($record['first_name'] . ' ' . $record['last_name']) ?></strong>
            <small><?= e(t('Student signature')) ?></small>
        </div>
        <div class="signature">
            <?php if ($record['officer_signature'] !== null): ?>
            <img src="data:image/png;base64,<?= e(base64_encode($record['officer_signature'])) ?>" alt="<?= e(t('Behavior Officer signature')) ?>">
            <?php else: ?><div class="signature-missing">—</div><?php endif; ?>
            <strong><?= e($record['recorded_by_name'] ?? t('Former staff member')) ?></strong>
            <small><?= e(t('Behavior Officer signature')) ?></small>
        </div>
    </div>
</main>
</body>
</html>
