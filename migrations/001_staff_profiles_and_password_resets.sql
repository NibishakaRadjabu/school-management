USE school_management;

ALTER TABLE users
    ADD COLUMN phone VARCHAR(40) NULL AFTER email,
    ADD COLUMN profile_picture VARCHAR(255) NULL AFTER password_hash;

ALTER TABLE announcements
    DROP FOREIGN KEY fk_announcements_author,
    MODIFY author_id BIGINT UNSIGNED NULL,
    ADD CONSTRAINT fk_announcements_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_password_resets_user (user_id),
    KEY idx_password_resets_expiry (expires_at),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
