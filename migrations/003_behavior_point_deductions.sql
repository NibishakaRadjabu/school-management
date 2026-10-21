USE school_management;

CREATE TABLE behavior_point_deductions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    recorded_by BIGINT UNSIGNED NULL,
    points_deducted TINYINT UNSIGNED NOT NULL,
    misconduct TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_behavior_school_student (school_id, student_id, created_at),
    CONSTRAINT fk_behavior_deductions_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    CONSTRAINT fk_behavior_deductions_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_behavior_deductions_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
