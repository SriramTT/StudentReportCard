-- =============================================================================
-- School Examination Marks and Report Card Management System
-- PostgreSQL Validation Suite Master Runner
-- Target: PostgreSQL 15+ / psql
-- =============================================================================
-- Usage:
--   psql -U postgres -d school_report_card_audit -f run_all.sql
-- =============================================================================

-- Safety check: Prevent running destructive validation suite against persistent application DB
DO $$
BEGIN
    IF current_database() = 'school_report_card' THEN
        RAISE EXCEPTION 'CRITICAL SAFETY GUARD TRIGGERED: Validation suite contains teardown scripts and cannot be executed against persistent application database ''school_report_card''. Please target dedicated test/validation database ''school_report_card_audit''.';
    END IF;
END $$;

\echo '============================================================================='
\echo 'Starting School Report Card PostgreSQL Validation Master Suite'
\echo '============================================================================='

\echo ''
\echo '-----------------------------------------------------------------------------'
\echo '1. Executing Schema and Core Integrity Validation (40 Scenarios + Extensions)'
\echo '-----------------------------------------------------------------------------'
\ir 'validate_schema.sql'

\echo ''
\echo '-----------------------------------------------------------------------------'
\echo '2. Executing Real-World Classroom Scale Validation Gate (Scenarios A through P)'
\echo '-----------------------------------------------------------------------------'
\ir 'phase5_real_world_validation.sql'

\echo ''
\echo '-----------------------------------------------------------------------------'
\echo '3. Final Baseline Verification (Seed Roles Preservation)'
\echo '-----------------------------------------------------------------------------'
SELECT id, name, description, is_active FROM roles ORDER BY id;

\echo '============================================================================='
\echo 'ALL POSTGRESQL VALIDATION RUNS COMPLETE.'
\echo '============================================================================='
