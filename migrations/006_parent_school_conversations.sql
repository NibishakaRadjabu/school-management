USE school_management;

CREATE TABLE parent_school_conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id BIGINT UNSIGNED NOT NULL,
    parent_user_id BIGINT UNSIGNED NOT NULL,
    subject VARCHAR(180) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_parent_conversations_school_updated (school_id, updated_at),
    KEY idx_parent_conversations_parent (parent_user_id, updated_at),
    CONSTRAINT fk_parent_conversations_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_parent_conversations_parent FOREIGN KEY (parent_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE parent_school_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    sender_id BIGINT UNSIGNED NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_parent_messages_conversation (conversation_id, id),
    CONSTRAINT fk_parent_messages_conversation FOREIGN KEY (conversation_id) REFERENCES parent_school_conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_parent_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
