-- =============================================================================
-- School Examination Marks and Report Card Management System
-- Database Schema - MySQL 8.x / InnoDB
-- =============================================================================
-- Database:    school_report_card
-- Engine:      InnoDB
-- Charset:     utf8mb4
-- Collation:   utf8mb4_unicode_ci
-- Created:     2026-09-15
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 0. CREATE DATABASE
-- -----------------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `school_report_card`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `school_report_card`;

-- =============================================================================
-- TABLE 1: roles
-- System roles for access control
-- =============================================================================
CREATE TABLE `roles` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(50)     NOT NULL,
    `description`   VARCHAR(255)    NULL,
    `is_active`     BOOLEAN         NOT NULL DEFAULT TRUE,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 2: users
-- Application user accounts; deactivated users are never deleted
-- =============================================================================
CREATE TABLE `users` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id`       BIGINT UNSIGNED NOT NULL,
    `username`      VARCHAR(100)    NOT NULL,
    `password_hash` VARCHAR(255)    NOT NULL,
    `display_name`  VARCHAR(150)    NOT NULL,
    `email`         VARCHAR(255)    NULL,
    `is_active`     BOOLEAN         NOT NULL DEFAULT TRUE,
    `last_login_at` DATETIME        NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_username` (`username`),
    CONSTRAINT `fk_users_role`
        FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    INDEX `idx_users_role_id` (`role_id`),
    INDEX `idx_users_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 3: academic_years
-- Academic year lifecycle: open → closed
-- Only one year should normally be current (enforced by application logic)
-- =============================================================================
CREATE TABLE `academic_years` (
    `id`            BIGINT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(20)         NOT NULL,
    `start_date`    DATE                NULL,
    `end_date`      DATE                NULL,
    `status`        ENUM('open','closed') NOT NULL DEFAULT 'open',
    `is_current`    BOOLEAN             NOT NULL DEFAULT FALSE,
    `created_at`    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_academic_years_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 4: terms
-- Dynamically configurable terms within an academic year
-- =============================================================================
CREATE TABLE `terms` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `academic_year_id`  BIGINT UNSIGNED NOT NULL,
    `name`              VARCHAR(100)    NOT NULL,
    `sequence_no`       INT UNSIGNED    NOT NULL,
    `is_active`         BOOLEAN         NOT NULL DEFAULT TRUE,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_terms_year_seq` (`academic_year_id`, `sequence_no`),
    UNIQUE KEY `uk_terms_year_name` (`academic_year_id`, `name`),
    CONSTRAINT `fk_terms_academic_year`
        FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT

    -- Note: idx on academic_year_id is provided by uk_terms_year_seq (leftmost prefix)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 5: classes
-- Master class definitions (e.g. 1, 2, 8, 9, 10)
-- =============================================================================
CREATE TABLE `classes` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(50)     NOT NULL,
    `is_active`     BOOLEAN         NOT NULL DEFAULT TRUE,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_classes_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 6: sections
-- Academic-year-specific sections within a class
-- =============================================================================
CREATE TABLE `sections` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `academic_year_id`  BIGINT UNSIGNED NOT NULL,
    `class_id`          BIGINT UNSIGNED NOT NULL,
    `name`              VARCHAR(50)     NOT NULL,
    `is_active`         BOOLEAN         NOT NULL DEFAULT TRUE,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_sections_year_class_name` (`academic_year_id`, `class_id`, `name`),
    CONSTRAINT `fk_sections_academic_year`
        FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sections_class`
        FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    INDEX `idx_sections_academic_year_id` (`academic_year_id`),
    INDEX `idx_sections_class_id` (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 7: subjects
-- Master subject catalogue
-- =============================================================================
CREATE TABLE `subjects` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(150)    NOT NULL,
    `code`          VARCHAR(50)     NULL,
    `category`      ENUM('main','elective') NOT NULL DEFAULT 'main',
    `is_active`     BOOLEAN         NOT NULL DEFAULT TRUE,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_subjects_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 8: class_subjects
-- Academic-year configuration linking classes/sections to subjects.
-- subject_name_snapshot preserves the historical subject name for reports.
--
-- NULLABLE section_id UNIQUENESS:
--   In MySQL 8.0+, functional key parts in secondary UNIQUE indexes allow
--   indexing expressions such as (COALESCE(`section_id`, 0)).
--   This cleanly enforces uniqueness for BOTH:
--   1) Section-specific configs (where section_id >= 1)
--   2) Class-wide configs (where section_id IS NULL, coalesced to 0)
--   preventing duplicate configurations without triggers or redundant columns.
-- =============================================================================
CREATE TABLE `class_subjects` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `academic_year_id`      BIGINT UNSIGNED NOT NULL,
    `class_id`              BIGINT UNSIGNED NOT NULL,
    `section_id`            BIGINT UNSIGNED NULL,
    `subject_id`            BIGINT UNSIGNED NOT NULL,
    `subject_name_snapshot` VARCHAR(150)    NOT NULL,
    `is_active`             BOOLEAN         NOT NULL DEFAULT TRUE,
    `created_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    -- Functional UNIQUE constraint prevents duplicate configurations for both
    -- section-specific (section_id >= 1) and class-wide (section_id IS NULL -> 0).
    UNIQUE KEY `uk_class_subjects_config` (`academic_year_id`, `class_id`, (COALESCE(`section_id`, 0)), `subject_id`),

    CONSTRAINT `fk_class_subjects_academic_year`
        FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_class_subjects_class`
        FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_class_subjects_section`
        FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_class_subjects_subject`
        FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    INDEX `idx_class_subjects_academic_year_id` (`academic_year_id`),
    INDEX `idx_class_subjects_class_id` (`class_id`),
    INDEX `idx_class_subjects_section_id` (`section_id`),
    INDEX `idx_class_subjects_subject_id` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 9: students
-- Student master records. Names are NOT unique (two students can share a name).
-- Re-import creates new student records; no automatic matching.
-- =============================================================================
CREATE TABLE `students` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_name`  VARCHAR(200)    NOT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_students_student_name` (`student_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 10: student_academic_records
-- Historical student academic placements. Each row represents a student's
-- placement in a class/section for an academic year. Transfers create new
-- rows; old placements are never destructively updated.
-- =============================================================================
CREATE TABLE `student_academic_records` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`        BIGINT UNSIGNED NOT NULL,
    `academic_year_id`  BIGINT UNSIGNED NOT NULL,
    `class_id`          BIGINT UNSIGNED NOT NULL,
    `section_id`        BIGINT UNSIGNED NOT NULL,
    `roll_number`       INT UNSIGNED    NOT NULL,
    `status`            ENUM('active','internal_transfer','withdrawn','transferred_out') NOT NULL DEFAULT 'active',
    `effective_from`    DATE            NOT NULL,
    `effective_to`      DATE            NULL,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    -- Roll number uniqueness within year/class/section
    UNIQUE KEY `uk_sar_year_class_section_roll` (`academic_year_id`, `class_id`, `section_id`, `roll_number`),

    CONSTRAINT `fk_sar_student`
        FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sar_academic_year`
        FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sar_class`
        FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_sar_section`
        FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    INDEX `idx_sar_student_id` (`student_id`),
    INDEX `idx_sar_academic_year_id` (`academic_year_id`),
    INDEX `idx_sar_class_id` (`class_id`),
    INDEX `idx_sar_section_id` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 11: student_subject_allocations
-- Which subjects apply to each student. Controls elective and main subject
-- assignment per student placement.
-- =============================================================================
CREATE TABLE `student_subject_allocations` (
    `id`                            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_academic_record_id`    BIGINT UNSIGNED NOT NULL,
    `class_subject_id`              BIGINT UNSIGNED NOT NULL,
    `allocation_type`               ENUM('main','elective') NOT NULL DEFAULT 'main',
    `effective_from`                DATE            NOT NULL,
    `effective_to`                  DATE            NULL,
    `is_active`                     BOOLEAN         NOT NULL DEFAULT TRUE,
    `created_at`                    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ssa_record_subject` (`student_academic_record_id`, `class_subject_id`),

    CONSTRAINT `fk_ssa_academic_record`
        FOREIGN KEY (`student_academic_record_id`) REFERENCES `student_academic_records` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_ssa_class_subject`
        FOREIGN KEY (`class_subject_id`) REFERENCES `class_subjects` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    INDEX `idx_ssa_student_academic_record_id` (`student_academic_record_id`),
    INDEX `idx_ssa_class_subject_id` (`class_subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 12: assessment_types
-- Master assessment type definitions (e.g. Class Test, Term Exam)
-- =============================================================================
CREATE TABLE `assessment_types` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(100)    NOT NULL,
    `is_active`     BOOLEAN         NOT NULL DEFAULT TRUE,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_assessment_types_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 13: assessments
-- Individual assessment instances. term_id is nullable (e.g. Final Exam may
-- belong to academic year, not a specific term). Assessments with marks must
-- be deactivated, never deleted.
-- =============================================================================
CREATE TABLE `assessments` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `academic_year_id`      BIGINT UNSIGNED NOT NULL,
    `term_id`               BIGINT UNSIGNED NULL,
    `assessment_type_id`    BIGINT UNSIGNED NOT NULL,
    `name`                  VARCHAR(150)    NOT NULL,
    `assessment_date`       DATE            NULL,
    `status`                ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_assessments_academic_year`
        FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_assessments_term`
        FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_assessments_type`
        FOREIGN KEY (`assessment_type_id`) REFERENCES `assessment_types` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    INDEX `idx_assessments_academic_year_id` (`academic_year_id`),
    INDEX `idx_assessments_term_id` (`term_id`),
    INDEX `idx_assessments_assessment_type_id` (`assessment_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 14: assessment_applicability
-- Links assessments to class/subjects with per-subject maximum marks.
-- Maximum marks can differ between subjects for the same assessment.
-- =============================================================================
CREATE TABLE `assessment_applicability` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `assessment_id`     BIGINT UNSIGNED NOT NULL,
    `class_subject_id`  BIGINT UNSIGNED NOT NULL,
    `maximum_marks`     DECIMAL(6,2)    NOT NULL,
    `is_active`         BOOLEAN         NOT NULL DEFAULT TRUE,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_aa_assessment_class_subject` (`assessment_id`, `class_subject_id`),

    CONSTRAINT `fk_aa_assessment`
        FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_aa_class_subject`
        FOREIGN KEY (`class_subject_id`) REFERENCES `class_subjects` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    -- maximum_marks must be > 0
    CONSTRAINT `chk_aa_max_marks_positive` CHECK (`maximum_marks` > 0),

    INDEX `idx_aa_assessment_id` (`assessment_id`),
    INDEX `idx_aa_class_subject_id` (`class_subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 15: teacher_assignments
-- Maps teacher users to their academic scope. A single teacher can have
-- MULTIPLE assignments. subject_id is NULL for class_teacher assignments.
-- Temporal overlap prevention is application-level validation.
-- =============================================================================
CREATE TABLE `teacher_assignments` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`           BIGINT UNSIGNED NOT NULL,
    `academic_year_id`  BIGINT UNSIGNED NOT NULL,
    `class_id`          BIGINT UNSIGNED NOT NULL,
    `section_id`        BIGINT UNSIGNED NOT NULL,
    `subject_id`        BIGINT UNSIGNED NULL,
    `assignment_type`   ENUM('subject_teacher','class_teacher') NOT NULL,
    `effective_from`    DATE            NOT NULL,
    `effective_to`      DATE            NULL,
    `is_active`         BOOLEAN         NOT NULL DEFAULT TRUE,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_ta_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_ta_academic_year`
        FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_ta_class`
        FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_ta_section`
        FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_ta_subject`
        FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    -- Class teacher must have subject_id = NULL;
    -- Subject teacher must have subject_id NOT NULL.
    -- Enforced via CHECK constraint.
    CONSTRAINT `chk_ta_assignment_subject_consistency` CHECK (
        (`assignment_type` = 'class_teacher' AND `subject_id` IS NULL)
        OR
        (`assignment_type` = 'subject_teacher' AND `subject_id` IS NOT NULL)
    ),

    INDEX `idx_ta_user_id` (`user_id`),
    INDEX `idx_ta_academic_year_id` (`academic_year_id`),
    INDEX `idx_ta_class_id` (`class_id`),
    INDEX `idx_ta_section_id` (`section_id`),
    INDEX `idx_ta_subject_id` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 16: marks
-- Individual student marks per assessment applicability.
-- Three result states: blank, numeric, absent — each is distinguishable.
-- =============================================================================
CREATE TABLE `marks` (
    `id`                                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_academic_record_id`        BIGINT UNSIGNED NOT NULL,
    `student_subject_allocation_id`     BIGINT UNSIGNED NOT NULL,
    `assessment_applicability_id`       BIGINT UNSIGNED NOT NULL,
    `mark_value`                        DECIMAL(6,2)    NULL,
    `result_status`                     ENUM('blank','numeric','absent') NOT NULL DEFAULT 'blank',
    `entered_by_user_id`                BIGINT UNSIGNED NULL,
    `updated_by_user_id`                BIGINT UNSIGNED NULL,
    `created_at`                        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- Each student/subject/assessment combination must have at most one mark.
    -- Note: Application must additionally validate that student_subject_allocation
    -- belongs to the student_academic_record and that assessment_applicability
    -- matches the class/subject context.
    UNIQUE KEY `uk_marks_allocation_applicability` (`student_subject_allocation_id`, `assessment_applicability_id`),

    CONSTRAINT `fk_marks_academic_record`
        FOREIGN KEY (`student_academic_record_id`) REFERENCES `student_academic_records` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_marks_subject_allocation`
        FOREIGN KEY (`student_subject_allocation_id`) REFERENCES `student_subject_allocations` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_marks_assessment_applicability`
        FOREIGN KEY (`assessment_applicability_id`) REFERENCES `assessment_applicability` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_marks_entered_by`
        FOREIGN KEY (`entered_by_user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_marks_updated_by`
        FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    -- Result status / mark_value consistency:
    -- numeric → mark_value IS NOT NULL and >= 0
    -- blank   → mark_value IS NULL
    -- absent  → mark_value IS NULL
    CONSTRAINT `chk_marks_result_consistency` CHECK (
        (`result_status` = 'numeric' AND `mark_value` IS NOT NULL AND `mark_value` >= 0)
        OR
        (`result_status` = 'blank' AND `mark_value` IS NULL)
        OR
        (`result_status` = 'absent' AND `mark_value` IS NULL)
    ),

    INDEX `idx_marks_student_academic_record_id` (`student_academic_record_id`),
    INDEX `idx_marks_student_subject_allocation_id` (`student_subject_allocation_id`),
    INDEX `idx_marks_assessment_applicability_id` (`assessment_applicability_id`),
    INDEX `idx_marks_entered_by_user_id` (`entered_by_user_id`),
    INDEX `idx_marks_updated_by_user_id` (`updated_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 17: attendance
-- Term-level attendance per student placement.
-- Percentage is calculated (not stored): days_attended / total_working_days * 100
-- When total_working_days = 0, result is NULL / N/A (application layer).
-- =============================================================================
CREATE TABLE `attendance` (
    `id`                            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_academic_record_id`    BIGINT UNSIGNED NOT NULL,
    `term_id`                       BIGINT UNSIGNED NOT NULL,
    `days_attended`                 INT UNSIGNED    NOT NULL DEFAULT 0,
    `total_working_days`            INT UNSIGNED    NOT NULL DEFAULT 0,
    `entered_by_user_id`            BIGINT UNSIGNED NOT NULL,
    `updated_by_user_id`            BIGINT UNSIGNED NOT NULL,
    `created_at`                    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    -- One attendance record per student placement per term
    UNIQUE KEY `uk_attendance_record_term` (`student_academic_record_id`, `term_id`),

    CONSTRAINT `fk_attendance_academic_record`
        FOREIGN KEY (`student_academic_record_id`) REFERENCES `student_academic_records` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_attendance_term`
        FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_attendance_entered_by`
        FOREIGN KEY (`entered_by_user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_attendance_updated_by`
        FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    -- Attendance bounds
    CONSTRAINT `chk_attendance_days_not_negative` CHECK (`days_attended` >= 0),
    CONSTRAINT `chk_attendance_total_not_negative` CHECK (`total_working_days` >= 0),
    CONSTRAINT `chk_attendance_days_within_total` CHECK (`days_attended` <= `total_working_days`),

    INDEX `idx_attendance_student_academic_record_id` (`student_academic_record_id`),
    INDEX `idx_attendance_term_id` (`term_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 18: calculation_settings
-- One calculation method per class per academic year.
-- Only Term Exam contributes to term percentage (application-level rule).
-- =============================================================================
CREATE TABLE `calculation_settings` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `academic_year_id`      BIGINT UNSIGNED NOT NULL,
    `class_id`              BIGINT UNSIGNED NOT NULL,
    `calculation_method`    ENUM('average_percentage','combined_marks') NOT NULL,
    `created_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_cs_year_class` (`academic_year_id`, `class_id`),

    CONSTRAINT `fk_cs_academic_year`
        FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_cs_class`
        FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT

    -- Note: idx on academic_year_id and class_id covered by uk_cs_year_class
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 19: report_configurations
-- Flexible report configuration; configuration_data JSON for extensibility.
-- Exact PDF layout is deferred to a later phase.
-- =============================================================================
CREATE TABLE `report_configurations` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `academic_year_id`      BIGINT UNSIGNED NULL,
    `name`                  VARCHAR(150)    NOT NULL,
    `report_type`           ENUM('exam','term','final') NOT NULL,
    `configuration_data`    JSON            NULL,
    `is_active`             BOOLEAN         NOT NULL DEFAULT TRUE,
    `created_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_rc_academic_year`
        FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    INDEX `idx_rc_academic_year_id` (`academic_year_id`),
    INDEX `idx_rc_report_type` (`report_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 20: report_assessment_selections
-- Controls which assessments appear in a report and their display order.
-- Separate from assessment_applicability (different concept).
-- =============================================================================
CREATE TABLE `report_assessment_selections` (
    `id`                        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `report_configuration_id`   BIGINT UNSIGNED NOT NULL,
    `assessment_id`             BIGINT UNSIGNED NOT NULL,
    `display_order`             INT UNSIGNED    NOT NULL,
    `is_displayed`              BOOLEAN         NOT NULL DEFAULT TRUE,
    `created_at`                DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ras_config_assessment` (`report_configuration_id`, `assessment_id`),

    CONSTRAINT `fk_ras_report_config`
        FOREIGN KEY (`report_configuration_id`) REFERENCES `report_configurations` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_ras_assessment`
        FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    INDEX `idx_ras_report_configuration_id` (`report_configuration_id`),
    INDEX `idx_ras_assessment_id` (`assessment_id`),
    INDEX `idx_ras_config_display_order` (`report_configuration_id`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 21: school_settings
-- Singleton school configuration (single-school application).
-- =============================================================================
CREATE TABLE `school_settings` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `school_name`       VARCHAR(200)    NOT NULL,
    `school_logo_path`  VARCHAR(500)    NULL,
    `pass_mark`         DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- pass_mark must be >= 0
    CONSTRAINT `chk_ss_pass_mark_non_negative` CHECK (`pass_mark` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 22: generated_reports
-- Historical PDF report records. PDFs are NEVER overwritten.
-- Revision numbers increment for subsequent regenerations.
-- =============================================================================
CREATE TABLE `generated_reports` (
    `id`                            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_academic_record_id`    BIGINT UNSIGNED NOT NULL,
    `report_type`                   ENUM('exam','term','final') NOT NULL,
    `term_id`                       BIGINT UNSIGNED NULL,
    `assessment_id`                 BIGINT UNSIGNED NULL,
    `revision_number`               INT UNSIGNED    NOT NULL DEFAULT 1,
    `file_path`                     VARCHAR(500)    NOT NULL,
    `generated_by_user_id`          BIGINT UNSIGNED NOT NULL,
    `generated_at`                  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- Revision identity uniqueness: prevents duplicate revision numbers for the same report context.
    -- Functional key part COALESCE handles NULL term_id (e.g. final reports) and NULL assessment_id (e.g. term reports).
    UNIQUE KEY `uk_gr_revision_identity` (
        `student_academic_record_id`,
        `report_type`,
        (COALESCE(`term_id`, 0)),
        (COALESCE(`assessment_id`, 0)),
        `revision_number`
    ),

    CONSTRAINT `fk_gr_academic_record`
        FOREIGN KEY (`student_academic_record_id`) REFERENCES `student_academic_records` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_gr_term`
        FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_gr_assessment`
        FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_gr_generated_by`
        FOREIGN KEY (`generated_by_user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    INDEX `idx_gr_student_academic_record_id` (`student_academic_record_id`),
    INDEX `idx_gr_term_id` (`term_id`),
    INDEX `idx_gr_assessment_id` (`assessment_id`),
    INDEX `idx_gr_generated_by_user_id` (`generated_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- TABLE 23: audit_logs
-- Immutable activity history. Must NEVER be deleted through the application.
-- =============================================================================
CREATE TABLE `audit_logs` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       BIGINT UNSIGNED NULL,
    `action`        VARCHAR(100)    NOT NULL,
    `entity_type`   VARCHAR(100)    NOT NULL,
    `entity_id`     BIGINT UNSIGNED NULL,
    `before_data`   JSON            NULL,
    `after_data`    JSON            NULL,
    `description`   TEXT            NULL,
    `ip_address`    VARCHAR(45)     NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    CONSTRAINT `fk_audit_logs_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,

    INDEX `idx_audit_logs_user_id` (`user_id`),
    INDEX `idx_audit_logs_action` (`action`),
    INDEX `idx_audit_logs_entity_type` (`entity_type`),
    INDEX `idx_audit_logs_entity_id` (`entity_id`),
    INDEX `idx_audit_logs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- SEED DATA: Required system roles
-- =============================================================================
INSERT INTO `roles` (`name`, `description`) VALUES
    ('Administrator',    'Full system access including user management, configuration, and audit log viewing'),
    ('Office Staff',     'Student management, data entry, and report generation'),
    ('Subject Teacher',  'Mark entry and viewing for assigned subjects'),
    ('Class Teacher',    'Mark entry and viewing for all subjects in assigned class/section');
