USE school_management;

ALTER TABLE notification_outbox
    ADD COLUMN fee_invoice_id BIGINT UNSIGNED NULL AFTER announcement_id,
    ADD KEY idx_outbox_fee_invoice_status (fee_invoice_id, status),
    ADD CONSTRAINT fk_outbox_fee_invoice
        FOREIGN KEY (fee_invoice_id) REFERENCES fee_invoices(id) ON DELETE SET NULL;
