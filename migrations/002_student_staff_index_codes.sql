USE school_management;

ALTER TABLE students
    ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER school_id,
    ADD COLUMN index_code VARCHAR(24) NULL AFTER admission_number;

ALTER TABLE teachers
    ADD COLUMN index_code VARCHAR(24) NULL AFTER employee_number;

UPDATE students
SET index_code = CONCAT('STU-', UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 12)))
WHERE index_code IS NULL;

UPDATE teachers
SET index_code = CONCAT('STF-', UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 12)))
WHERE index_code IS NULL;

ALTER TABLE students
    MODIFY index_code VARCHAR(24) NOT NULL,
    ADD UNIQUE KEY uq_students_index_code (school_id, index_code),
    ADD UNIQUE KEY uq_students_user (user_id),
    ADD CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE teachers
    MODIFY index_code VARCHAR(24) NOT NULL,
    ADD UNIQUE KEY uq_teachers_index_code (school_id, index_code);

INSERT INTO roles (name) VALUES ('student')
ON DUPLICATE KEY UPDATE name = VALUES(name);
