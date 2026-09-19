-- =============================================================================
-- School Examination Marks and Report Card Management System
-- Seed Data - PostgreSQL 15+ Compatible
-- =============================================================================
-- Database:    school_report_card
-- Target:      PostgreSQL 15+
-- =============================================================================

-- Required baseline roles (with explicit IDs 1..4)
INSERT INTO roles (id, name, description, is_active)
VALUES
    (1, 'Administrator',   'Full system access including user management, configuration, and audit log viewing', TRUE),
    (2, 'Office Staff',    'Student management, data entry, and report generation', TRUE),
    (3, 'Subject Teacher', 'Mark entry and viewing for assigned subjects', TRUE),
    (4, 'Class Teacher',   'Mark entry and viewing for all subjects in assigned class/section', TRUE)
ON CONFLICT (id) DO NOTHING;

-- Synchronize identity sequence for roles table
SELECT setval(
    pg_get_serial_sequence('roles', 'id'),
    COALESCE((SELECT MAX(id) FROM roles), 1)
);
