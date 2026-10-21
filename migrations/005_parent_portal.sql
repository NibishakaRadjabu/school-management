USE school_management;

ALTER TABLE guardians
    ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER school_id,
    ADD KEY idx_guardians_user (user_id),
    ADD CONSTRAINT fk_guardians_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE parent_email_verifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_parent_email_verifications_user (user_id),
    KEY idx_parent_email_verifications_expiry (expires_at),
    CONSTRAINT fk_parent_email_verifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
