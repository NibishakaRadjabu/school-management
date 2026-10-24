<?php
$schoolId = current_user()['school_id'];
$pdo = database();
$studentQuery = $pdo->prepare('SELECT id, first_name, last_name, admission_number FROM students WHERE school_id = ? AND status = "active" ORDER BY first_name, last_name');
$studentQuery->execute([$schoolId]);
$studentOptions = $studentQuery->fetchAll();
$invoiceQuery = $pdo->prepare(
    'SELECT i.id, i.student_id, i.description, i.amount, i.due_date, i.status, s.first_name, s.last_name, s.admission_number,
            COALESCE(SUM(p.amount), 0) AS paid
     FROM fee_invoices i JOIN students s ON s.id = i.student_id
     LEFT JOIN fee_payments p ON p.invoice_id = i.id
     WHERE i.school_id = ? GROUP BY i.id ORDER BY i.created_at DESC'
);
$invoiceQuery->execute([$schoolId]);
$invoices = $invoiceQuery->fetchAll();
$collectedQuery = $pdo->prepare('SELECT COALESCE(SUM(p.amount), 0) FROM fee_payments p JOIN fee_invoices i ON i.id = p.invoice_id WHERE i.school_id = ?');
$collectedQuery->execute([$schoolId]);
$outstanding = 0.0;
foreach ($invoices as $invoice) {
    $outstanding += max(0, (float) $invoice['amount'] - (float) $invoice['paid']);
}
?>
<section class="page-heading"><div><span class="eyebrow"><?= e(t('SCHOOL FINANCES')) ?></span><h1><?= e(t('Fees & payments')) ?></h1><p><?= e(t('Track fee invoices, balances, and payments in one place.')) ?></p></div></section>
<section class="stat-grid fee-stats">
    <article class="stat-card"><div class="stat-top"><span class="stat-label"><?= e(t('Total collected')) ?></span><span class="stat-icon accent-green">◇</span></div><strong class="stat-value money"><?= number_format((float) $collectedQuery->fetchColumn(), 0) ?><small> RWF</small></strong><div class="stat-foot"><span class="stat-trend neutral"><?= e(t('Recorded payments')) ?></span></div></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label"><?= e(t('Outstanding balance')) ?></span><span class="stat-icon accent-orange">◷</span></div><strong class="stat-value money"><?= number_format($outstanding, 0) ?><small> RWF</small></strong><div class="stat-foot"><span class="stat-trend neutral"><?= e(t('Across open invoices')) ?></span></div></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label"><?= e(t('Open invoices')) ?></span><span class="stat-icon accent-blue">▤</span></div><strong class="stat-value"><?= number_format(count(array_filter($invoices, static fn(array $invoice): bool => $invoice['status'] !== 'paid'))) ?></strong><div class="stat-foot"><span class="stat-trend neutral"><?= e(t('Awaiting full payment')) ?></span></div></article>
</section>
<section class="content-grid">
    <article class="panel form-panel">
        <div class="panel-heading"><div><h2><?= e(t('Create a fee invoice')) ?></h2><p><?= e(t('Assign a fee to a student.')) ?></p></div><span class="panel-icon">◇</span></div>
        <?php if ($studentOptions === []): ?><div class="empty-state compact"><p><?= e(t('Add students before creating invoices.')) ?></p><a href="index.php?page=students#add-student"><?= e(t('Go to students')) ?> →</a></div><?php else: ?>
        <form method="post" action="index.php?page=fees" class="form-stack compact-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create_invoice">
            <label><?= e(t('Student')) ?><select name="student_id" required><option value=""><?= e(t('Select a student')) ?></option><option value="all"><?= e(t('All active students')) ?> (<?= count($studentOptions) ?>)</option><?php foreach ($studentOptions as $student): ?><option value="<?= (int) $student['id'] ?>"><?= e($student['first_name'] . ' ' . $student['last_name'] . ' · ' . $student['admission_number']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('Description')) ?><input name="description" required maxlength="150" placeholder="<?= e(t('e.g. Term 1 school fees')) ?>"></label>
            <label><?= e(t('Amount (RWF)')) ?><input type="number" name="amount" required min="0.01" step="0.01"></label>
            <label><?= e(t('Due date')) ?><input type="date" name="due_date"></label>
            <button class="button button-primary" type="submit"><?= e(t('Create invoice')) ?> <span>→</span></button>
        </form><?php endif; ?>
    </article>
    <article class="panel form-panel payment-panel">
        <div class="panel-heading"><div><h2><?= e(t('Record a payment')) ?></h2><p><?= e(t('Apply a payment to an open invoice.')) ?></p></div><span class="panel-icon accent-green">✓</span></div>
        <?php $openInvoices = array_filter($invoices, static fn(array $invoice): bool => (float) $invoice['amount'] > (float) $invoice['paid']); ?>
        <?php if ($openInvoices === []): ?><div class="empty-state compact"><p><?= e(t('No open invoices to pay. Create an invoice first.')) ?></p></div><?php else: ?>
        <form method="post" action="index.php?page=fees" class="form-stack compact-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="record_payment">
            <label><?= e(t('Invoice')) ?><select name="invoice_id" required><option value=""><?= e(t('Select an invoice')) ?></option><?php foreach ($openInvoices as $invoice): ?><option value="<?= (int) $invoice['id'] ?>"><?= e($invoice['first_name'] . ' ' . $invoice['last_name'] . ' · ' . $invoice['description'] . ' · ' . number_format((float) $invoice['amount'] - (float) $invoice['paid'], 0) . ' RWF ' . t('due')) ?></option><?php endforeach; ?></select></label>
            <label><?= e(t('Payment amount (RWF)')) ?><input type="number" name="amount" required min="0.01" step="0.01"></label>
            <label><?= e(t('Payment date')) ?><input type="date" name="payment_date" value="<?= e(date('Y-m-d')) ?>" required></label>
            <label><?= e(t('Payment method')) ?><input name="method" maxlength="40" placeholder="<?= e(t('e.g. Cash, bank transfer')) ?>"></label>
            <label><?= e(t('Reference (optional)')) ?><input name="reference" maxlength="100"></label>
            <button class="button button-secondary" type="submit"><?= e(t('Record a payment')) ?> <span>→</span></button>
        </form><?php endif; ?>
    </article>
</section>
<section class="panel recent-panel">
    <div class="panel-heading"><div><h2><?= e(t('Fee register')) ?></h2><p><?= count($invoices) ?> <?= e(t('invoices')) ?></p></div></div>
    <?php if ($invoices === []): ?><div class="empty-state compact"><span class="empty-icon">◇</span><p><?= e(t('No invoices yet. Create a fee invoice above to get started.')) ?></p></div><?php else: ?>
        <div class="table-wrap"><table><thead><tr><th><?= e(t('STUDENT')) ?></th><th><?= e(t('DESCRIPTION')) ?></th><th><?= e(t('AMOUNT')) ?></th><th><?= e(t('PAID')) ?></th><th><?= e(t('BALANCE')) ?></th><th><?= e(t('DUE DATE')) ?></th><th><?= e(t('STATUS')) ?></th><?php if (current_user()['role'] === 'admin'): ?><th><?= e(t('ACTIONS')) ?></th><?php endif; ?></tr></thead><tbody>
        <?php foreach ($invoices as $invoice): ?><tr><td><span class="cell-primary"><?= e($invoice['first_name'] . ' ' . $invoice['last_name']) ?></span><small class="cell-secondary"><?= e($invoice['admission_number']) ?></small></td><td><?= e($invoice['description']) ?></td><td><?= number_format((float) $invoice['amount'], 0) ?> RWF</td><td><?= number_format((float) $invoice['paid'], 0) ?> RWF</td><td><strong><?= number_format(max(0, (float) $invoice['amount'] - (float) $invoice['paid']), 0) ?> RWF</strong></td><td><?= $invoice['due_date'] ? e(localized_date($invoice['due_date'])) : '—' ?></td><td><span class="status-pill status-<?= e($invoice['status']) ?>"><?= e(t(ucfirst($invoice['status']))) ?></span></td>
            <?php if (current_user()['role'] === 'admin'): ?><td><details class="student-edit"><summary class="text-link"><?= e(t('Edit invoice')) ?></summary>
                <form method="post" action="index.php?page=fees" class="form-stack">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_invoice">
                    <input type="hidden" name="invoice_id" value="<?= (int) $invoice['id'] ?>">
                    <label><?= e(t('Description')) ?><input name="description" value="<?= e($invoice['description']) ?>" required maxlength="150"></label>
                    <label><?= e(t('Amount (RWF)')) ?><input type="number" name="amount" value="<?= e((string) $invoice['amount']) ?>" required min="0.01" step="0.01"><small><?= e(t('Cannot be less than payments already recorded.')) ?></small></label>
                    <label><?= e(t('Due date')) ?><input type="date" name="due_date" value="<?= e($invoice['due_date'] ?? '') ?>"></label>
                    <button class="button button-secondary" type="submit"><?= e(t('Save changes')) ?></button>
                </form>
                <form method="post" action="index.php?page=fees" onsubmit="return confirm(<?= e(json_encode(t('Delete this invoice and all its payment records? This cannot be undone.'))) ?>)">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_invoice">
                    <input type="hidden" name="invoice_id" value="<?= (int) $invoice['id'] ?>">
                    <button class="button button-danger" type="submit"><?= e(t('Delete invoice')) ?></button>
                </form>
            </details></td><?php endif; ?>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</section>
