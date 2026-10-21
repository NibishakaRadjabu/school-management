USE school_management;

ALTER TABLE behavior_point_deductions
    ADD COLUMN student_signature MEDIUMBLOB NULL AFTER misconduct,
    ADD COLUMN officer_signature MEDIUMBLOB NULL AFTER student_signature;
