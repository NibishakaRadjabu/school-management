USE school_management;

ALTER TABLE announcements
    ADD COLUMN attachment_path VARCHAR(255) NULL AFTER audience;

ALTER TABLE notification_outbox
    ADD COLUMN subject VARCHAR(180) NULL AFTER announcement_id;
