-- =============================================================================
-- School Examination Marks and Report Card Management System
-- Combined: Schema Creation + Complete Validation Suite
-- =============================================================================
-- PORTABLE EXECUTION INSTRUCTIONS:
--
-- Method 1 (Recommended - from database directory):
--   cd "D:\Projects\School report card\database"
--   mysql -u <user> -p < run_all.sql
--   -- OR in MySQL CLI:
--   mysql -u <user> -p
--   source run_all.sql;
--
-- Method 2 (From project root):
--   mysql -u <user> -p < database/run_all.sql
--   (Note: MySQL CLI 'source' resolves paths relative to the current working
--    directory where the mysql client was started).
-- =============================================================================

source schema.sql;
source validate_schema.sql;
