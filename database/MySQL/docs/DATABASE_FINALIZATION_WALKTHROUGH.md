# Database Finalization & Verification Walkthrough
## School Examination Marks and Report Card Management System

**Document Version:** 1.0  
**Database System:** MySQL 8.0.29 / InnoDB  
**Database Name:** `school_report_card`  
**Character Set / Collation:** `utf8mb4` / `utf8mb4_unicode_ci`  
**Date:** September 15, 2026  
**Status:** **FINALIZED & READY FOR APPLICATION DEVELOPMENT**

---

## Table of Contents
- [A. Files Inspected](#a-files-inspected)
- [B. Existing Schema Assessment](#b-existing-schema-assessment)
- [C. Problems Discovered](#c-problems-discovered)
- [D. Corrections Made](#d-corrections-made)
- [E. Corrections NOT Made and Why](#e-corrections-not-made-and-why)
- [F. Final 23-Table Confirmation](#f-final-23-table-confirmation)
- [G. Final Constraints Summary](#g-final-constraints-summary)
- [H. Final Indexes Summary](#h-final-indexes-summary)
- [I. DB-Enforced vs. Application-Enforced Rules](#i-db-enforced-vs-application-enforced-rules)
- [J. Marks Integrity Rules](#j-marks-integrity-rules)
- [K. Teacher Authorization Implications](#k-teacher-authorization-implications)
- [L. Report Revision Integrity](#l-report-revision-integrity)
- [M. Subject Snapshot Behavior](#m-subject-snapshot-behavior)
- [N. Academic Year Closure Behavior](#n-academic-year-closure-behavior)
- [O. Validation Scenarios Executed](#o-validation-scenarios-executed)
- [P. Negative Constraint Tests Executed](#p-negative-constraint-tests-executed)
- [Q. Validation Results](#q-validation-results)
- [R. Any Remaining Limitations](#r-any-remaining-limitations)
- [S. Final Verdict](#s-final-verdict)

---

## A. Files Inspected

Before making any changes, the authoritative documentation, relationship designs, and existing implementations were thoroughly inspected in the designated priority order:

1. **BRD V1.3 (`BRD\School_Examination_Marksheet_Requirements_v1_3.docx`):**
   - Extracted document text covering 84 business rules (BR-001 through BR-084), report behavior, and clarification notes (CL-001 through CL-011).
   - Confirmed critical decisions: 3 mark states (`blank`, `numeric`, `absent`), only Term Exam contributes to term percentage, attendance stored as days/total per term, immutable generated report PDFs with revision tracking, student non-matching imports, historical student academic placements, and class teacher scope covering all applicable subjects.

2. **Approved Database Relationship Summary (`DB design\DB-table-definitons.txt`):**
   - Verified the 23 approved tables and their core relationships, including the mandatory preservation of `class_subjects.subject_name_snapshot`.

3. **Approved ERD (`DB design\mermaid-diagram.png`):**
   - Inspected visual schema topology, confirming foreign key directions and cardinalities across the 23 tables.

4. **Existing Schema Implementation (`database\schema.sql`):**
   - Inspected table definitions, storage engine (`InnoDB`), foreign keys (all `ON DELETE RESTRICT ON UPDATE RESTRICT`), CHECK constraints, and uniqueness constraints.

5. **Existing Validation Script (`database\validate_schema.sql`):**
   - Inspected the 11 scenarios and evaluated test rigor, identifying gaps in active constraint violation execution.

6. **Combined Runner Script (`database\run_all.sql`):**
   - Inspected execution flow and noted hardcoded absolute Windows paths.

7. **Previous Validation Output (`database\validation_results.txt`):**
   - Inspected previous test output baseline.

---

## B. Existing Schema Assessment

The initial 23-table schema provided a robust relational baseline with excellent data integrity practices:
- **Engine & Charset:** Standardized on `InnoDB` with `utf8mb4` / `utf8mb4_unicode_ci`.
- **Referential Integrity:** All 43 foreign key relationships strictly used `ON DELETE RESTRICT ON UPDATE RESTRICT` to guarantee historical data cannot be pruned by cascading deletes.
- **Check Constraints:** Enforced core domain rules:
  - `chk_marks_result_consistency`: `numeric` requires non-negative value; `blank` and `absent` require `NULL`.
  - `chk_attendance_days_within_total`: `days_attended <= total_working_days` and non-negative bounds.
  - `chk_ta_assignment_subject_consistency`: Class Teachers must have `subject_id IS NULL`; Subject Teachers must have `subject_id NOT NULL`.
  - `chk_aa_max_marks_positive`: Maximum marks must be `> 0`.
  - `chk_ss_pass_mark_non_negative`: Pass mark must be `>= 0`.

However, the assessment identified several specific technical gaps requiring refinement before production application development.

---

## C. Problems Discovered

1. **`class_subjects.section_id` NULL Uniqueness Vulnerability (Item B):**
   - The unique key `uk_class_subjects_config (academic_year_id, class_id, section_id, subject_id)` allowed duplicate class-wide subject configurations because standard SQL unique indexes treat multiple `NULL` values as distinct. If a school configured a class-wide subject with `section_id = NULL`, duplicate rows could be inserted without database rejection.

2. **`generated_reports` Missing Revision Identity Constraint (Item C):**
   - `generated_reports` lacked a unique constraint preventing duplicate revisions for the same report context. Two records could accidentally be inserted for Student X, Term 1, Revision 1. Furthermore, because `term_id` and `assessment_id` are nullable (final reports omit both, term reports omit assessment), a naive unique constraint would fail due to MySQL's NULL handling in unique indexes.

3. **Validation Suite Lacked Real Negative Constraint Testing (Item F):**
   - In the original `validate_schema.sql`, several negative tests merely printed `SELECT '--- Test: should fail ---'` or checked `INFORMATION_SCHEMA` metadata without actually attempting the invalid SQL statement. Expected failures were not actively executed, trapped, and asserted.

4. **Validation Test Cleanup and Safety (Item G):**
   - Validation inserts remained permanently in the database unless dropped manually, creating potential pollution in development environments.

5. **`run_all.sql` Portability (Item H):**
   - Hardcoded absolute paths (`D:/Projects/School report card/...`) made script execution machine-dependent.

---

## D. Corrections Made

### 1. `class_subjects` Functional Unique Key (`database\schema.sql`)
- Leveraged MySQL 8.0's native functional key parts in secondary indexes to index `(COALESCE(section_id, 0))`:
  ```sql
  UNIQUE KEY `uk_class_subjects_config` (
      `academic_year_id`,
      `class_id`,
      (COALESCE(`section_id`, 0)),
      `subject_id`
  )
  ```
- **Result:**
  - Section-specific configurations (`section_id >= 1`) are uniquely indexed by their actual ID.
  - Class-wide configurations (`section_id IS NULL`) are indexed as `0`.
  - Duplicate class-wide configurations for the same academic year, class, and subject are strictly rejected by MySQL with error `1062` at the database engine level without needing triggers or stored generated columns.

### 2. `generated_reports` Revision Identity Uniqueness (`database\schema.sql`)
- Added a functional unique key enforcing revision uniqueness across nullable foreign keys:
  ```sql
  UNIQUE KEY `uk_gr_revision_identity` (
      `student_academic_record_id`,
      `report_type`,
      (COALESCE(`term_id`, 0)),
      (COALESCE(`assessment_id`, 0)),
      `revision_number`
  )
  ```
- **Result:**
  - For any student, report type, term, and assessment, each revision number (Revision 1, Revision 2, etc.) is uniquely guaranteed.
  - Attempting to insert duplicate revisions triggers MySQL error `1062`.

### 3. Upgraded Validation Harness (`database\validate_schema.sql`)
- Built an active test harness using MySQL stored procedures:
  - `record_result(...)`: Records positive, negative, and structural assertions into a temporary table `test_results`.
  - `assert_negative_sql(...)`: Executes dynamic SQL inside a procedure with `DECLARE CONTINUE HANDLER FOR SQLEXCEPTION, SQLWARNING` and `GET DIAGNOSTICS`. It traps the exact MySQL error number and message, marking the test as `PASS (EXPECTED REJECTION)`. If the invalid statement unexpectedly succeeds, it marks `FAIL (UNEXPECTED SUCCESS)`.
- Expanded test coverage to **40 distinct scenarios (52 individual test assertions)**.

### 4. Database Test Cleanup & Safety (`database\validate_schema.sql`)
- Explicitly documented the target environment: clean development or disposable test databases only.
- Added comprehensive cleanup at the end of the test run, wiping mock test rows in reverse dependency order while strictly preserving the 4 system seed roles.

### 5. Portable Runner (`database\run_all.sql`)
- Replaced absolute paths with portable relative paths (`source schema.sql;`, `source validate_schema.sql;`) and added execution instructions for both CLI and terminal environments.

---

## E. Corrections NOT Made and Why

1. **Did NOT add redundant foreign key columns to `marks`:**
   - Evaluated compound foreign keys `(student_academic_record_id, student_subject_allocation_id)` and `(assessment_applicability_id, class_subject_id)`. Doing so would require denormalizing `class_subject_id` into `marks` and altering primary/candidate keys across multiple tables, violating the approved 23-table specification. Kept the normalized schema and formally documented the application-level cross-reference validation invariant.

2. **Did NOT add database triggers for `academic_years.is_current`:**
   - Triggers for cross-row single-boolean enforcement introduce locking overhead, migration complexity, and edge-case fragility. Enforcing that only one academic year is current is cleanly handled transactionally in the application service layer.

3. **Did NOT introduce unapproved mark states or grading:**
   - Preserved exactly the 3 approved states: `blank`, `numeric`, `absent`. No `EX`, `WH`, `NA`, or letter grades were introduced.

4. **Did NOT modify the BRD or create BRD V1.4:**
   - Strict adherence to the rule that BRD V1.3 is the authoritative business requirement document.

---

## F. Final 23-Table Confirmation

The final database contains **exactly 23 base tables**, verified via `INFORMATION_SCHEMA.TABLES`:

| # | Table Name | Purpose / Core Relationships |
|---|---|---|
| 1 | `roles` | System roles (Administrator, Office Staff, Subject Teacher, Class Teacher). |
| 2 | `users` | User accounts linked to roles. Deactivated users preserved. |
| 3 | `academic_years` | Academic year lifecycle (`open`, `closed`). |
| 4 | `terms` | Dynamic terms per academic year ordered by `sequence_no`. |
| 5 | `classes` | Master class definitions (e.g. 8, 9, 10). |
| 6 | `sections` | Academic-year and class-specific sections (e.g. 8A, 8B). |
| 7 | `subjects` | Master subject catalogue (main vs. elective). |
| 8 | `class_subjects` | Year/class/section subject configuration with **`subject_name_snapshot`**. |
| 9 | `students` | Master student records. Names are non-unique; imports create new records. |
| 10 | `student_academic_records` | Historical student academic placements and historical roll numbers. |
| 11 | `student_subject_allocations` | Student-specific subject allocations (main and electives). |
| 12 | `assessment_types` | Master assessment types (e.g. Unit Test, Term Exam). |
| 13 | `assessments` | Individual assessment instances linked to year/term. |
| 14 | `assessment_applicability` | Subject-level assessment link with subject-specific `maximum_marks`. |
| 15 | `teacher_assignments` | Teacher user mappings to academic scope (class vs. subject teacher). |
| 16 | `marks` | Student marks per assessment applicability (`blank`, `numeric`, `absent`). |
| 17 | `attendance` | Term-level attendance (`days_attended`, `total_working_days`). |
| 18 | `calculation_settings` | One calculation method per academic-year/class. |
| 19 | `report_configurations` | Report metadata and JSON configuration settings. |
| 20 | `report_assessment_selections`| Assessments selected for display and their column sequence. |
| 21 | `school_settings` | Singleton school configuration (name, logo, pass mark). |
| 22 | `generated_reports` | Historical PDF revisions with unique revision identities. |
| 23 | `audit_logs` | Immutable audit log trail with JSON `before_data`/`after_data`. |

---

## G. Final Constraints Summary

### 1. Foreign Key Constraints (43 Total)
Every foreign key across all 23 tables uses `ON DELETE RESTRICT ON UPDATE RESTRICT`. Cascading deletes are strictly prohibited to protect historical records.

### 2. Check Constraints (7 Total)
1. `chk_aa_max_marks_positive` on `assessment_applicability`: `maximum_marks > 0`
2. `chk_marks_result_consistency` on `marks`:
   - `result_status = 'numeric' AND mark_value IS NOT NULL AND mark_value >= 0`
   - `OR (result_status = 'blank' AND mark_value IS NULL)`
   - `OR (result_status = 'absent' AND mark_value IS NULL)`
3. `chk_attendance_days_not_negative` on `attendance`: `days_attended >= 0`
4. `chk_attendance_total_not_negative` on `attendance`: `total_working_days >= 0`
5. `chk_attendance_days_within_total` on `attendance`: `days_attended <= total_working_days`
6. `chk_ta_assignment_subject_consistency` on `teacher_assignments`:
   - `(assignment_type = 'class_teacher' AND subject_id IS NULL)`
   - `OR (assignment_type = 'subject_teacher' AND subject_id IS NOT NULL)`
7. `chk_ss_pass_mark_non_negative` on `school_settings`: `pass_mark >= 0`

### 3. Unique Constraints & Functional Keys (16 Total)
1. `uk_roles_name` on `roles`: `(name)`
2. `uk_users_username` on `users`: `(username)`
3. `uk_academic_years_name` on `academic_years`: `(name)`
4. `uk_terms_year_seq` on `terms`: `(academic_year_id, sequence_no)`
5. `uk_terms_year_name` on `terms`: `(academic_year_id, name)`
6. `uk_classes_name` on `classes`: `(name)`
7. `uk_sections_year_class_name` on `sections`: `(academic_year_id, class_id, name)`
8. `uk_subjects_name` on `subjects`: `(name)`
9. `uk_class_subjects_config` on `class_subjects`: `(academic_year_id, class_id, (COALESCE(section_id, 0)), subject_id)` *(Functional Key)*
10. `uk_sar_year_class_section_roll` on `student_academic_records`: `(academic_year_id, class_id, section_id, roll_number)`
11. `uk_ssa_record_subject` on `student_subject_allocations`: `(student_academic_record_id, class_subject_id)`
12. `uk_assessment_types_name` on `assessment_types`: `(name)`
13. `uk_aa_assessment_class_subject` on `assessment_applicability`: `(assessment_id, class_subject_id)`
14. `uk_marks_allocation_applicability` on `marks`: `(student_subject_allocation_id, assessment_applicability_id)`
15. `uk_attendance_record_term` on `attendance`: `(student_academic_record_id, term_id)`
16. `uk_cs_year_class` on `calculation_settings`: `(academic_year_id, class_id)`
17. `uk_ras_config_assessment` on `report_assessment_selections`: `(report_configuration_id, assessment_id)`
18. `uk_gr_revision_identity` on `generated_reports`: `(student_academic_record_id, report_type, (COALESCE(term_id, 0)), (COALESCE(assessment_id, 0)), revision_number)` *(Functional Key)*

---

## H. Final Indexes Summary

All foreign key columns and frequently queried paths have explicit indexes to ensure high query performance:
- `users`: `(role_id)`, `(is_active)`
- `sections`: `(academic_year_id)`, `(class_id)`
- `class_subjects`: `(academic_year_id)`, `(class_id)`, `(section_id)`, `(subject_id)`
- `students`: `(student_name)`
- `student_academic_records`: `(student_id)`, `(academic_year_id)`, `(class_id)`, `(section_id)`
- `student_subject_allocations`: `(student_academic_record_id)`, `(class_subject_id)`
- `assessments`: `(academic_year_id)`, `(term_id)`, `(assessment_type_id)`
- `assessment_applicability`: `(assessment_id)`, `(class_subject_id)`
- `teacher_assignments`: `(user_id)`, `(academic_year_id)`, `(class_id)`, `(section_id)`, `(subject_id)`
- `marks`: `(student_academic_record_id)`, `(student_subject_allocation_id)`, `(assessment_applicability_id)`, `(entered_by_user_id)`, `(updated_by_user_id)`
- `attendance`: `(student_academic_record_id)`, `(term_id)`
- `report_configurations`: `(academic_year_id)`, `(report_type)`
- `report_assessment_selections`: `(report_configuration_id)`, `(assessment_id)`, `(report_configuration_id, display_order)`
- `generated_reports`: `(student_academic_record_id)`, `(term_id)`, `(assessment_id)`, `(generated_by_user_id)`
- `audit_logs`: `(user_id)`, `(action)`, `(entity_type)`, `(entity_id)`, `(created_at)`

---

## I. DB-Enforced vs. Application-Enforced Rules

| Rule / Invariant | Classification | Enforcement Mechanism |
|---|---|---|
| Roll number unique within Year + Class + Section | **DB-Enforced** | `uk_sar_year_class_section_roll` |
| Negative mark rejection (`mark_value < 0`) | **DB-Enforced** | `chk_marks_result_consistency` |
| Result status consistency (blank/absent have NULL; numeric has value) | **DB-Enforced** | `chk_marks_result_consistency` |
| Maximum marks must be positive (`> 0`) | **DB-Enforced** | `chk_aa_max_marks_positive` |
| Numeric mark upper bound (`mark_value <= maximum_marks`) | **Application-Enforced** | Validated in marks service (cross-table) |
| Marks cross-reference integrity (context matching) | **Application-Enforced** | Validated in marks service transaction |
| Attendance bounds (`0 <= days_attended <= total_working_days`) | **DB-Enforced** | `chk_attendance_days_within_total` |
| Attendance 0/0 divide-by-zero handling | **Application-Enforced** | UI/Calculation service renders `N/A` |
| Class Teacher has no subject scope (`subject_id IS NULL`) | **DB-Enforced** | `chk_ta_assignment_subject_consistency` |
| Class Teacher access to all subjects in class | **Application-Enforced** | Authorization middleware / service |
| Subject Teacher restricted to assigned subjects | **Application-Enforced** | Authorization middleware / service |
| Only Term Exam contributes to term percentage | **Application-Enforced** | Term calculation service |
| Changing calculation method recalculates term results | **Application-Enforced** | Recalculation service on method change |
| Class-wide subject uniqueness (`section_id IS NULL`) | **DB-Enforced** | `uk_class_subjects_config` with `COALESCE` |
| Generated report revision identity uniqueness | **DB-Enforced** | `uk_gr_revision_identity` with `COALESCE` |
| Previous PDF revisions never overwritten | **Both** | DB unique revision identity + app file storage |
| Single current academic year (`is_current = TRUE`) | **Application-Enforced** | Academic year activation transaction |
| Academic year closure makes teachers read-only | **Application-Enforced** | Authorization middleware checks year status |
| Audit log immutability | **Both** | App append-only policy + restricted DB grants |
| Student import creates new record (no auto-match) | **Application-Enforced** | Student import service workflow |
| Elective change blocked once marks exist | **Application-Enforced** | Allocation service checks marks existence |

---

## J. Marks Integrity Rules

Because MySQL cannot cross-reference multiple foreign key targets across three different tables in a single constraint without excessive denormalization, the backend marks service **MUST** enforce the following 5-point invariant inside a database transaction before inserting or updating any mark:

1. **Placement & Allocation Alignment:**
   Verify `student_subject_allocations.student_academic_record_id` matches `marks.student_academic_record_id`.
2. **Context Matching:**
   Verify `assessment_applicability.class_subject_id` matches `student_subject_allocations.class_subject_id`.
3. **Academic Year & Class Consistency:**
   Verify the student's placement (`student_academic_records`), the configured subject (`class_subjects`), and the assessment (`assessments`) all belong to the same `academic_year_id` and `class_id`.
4. **Subject Matching:**
   Verify the allocated subject matches the assessment applicability subject.
5. **Upper Bound Value Validation:**
   Verify that if `result_status = 'numeric'`, `mark_value <= assessment_applicability.maximum_marks`. A mark such as 21/20 must be rejected with a validation error.

---

## K. Teacher Authorization Implications

The schema fully enables the approved multi-scope authorization model:
- One teacher account (`users`) can hold multiple records in `teacher_assignments`.
- **Subject Teacher Assignment:** Contains `class_id`, `section_id`, and `subject_id`. The application authorizes mark entry only for that specific subject in that section.
- **Class Teacher Assignment:** Contains `class_id`, `section_id`, and `subject_id IS NULL`. The application automatically derives access to **ALL** subjects configured for that class/section without requiring individual subject assignment records.
- **Precedence:** Class Teachers can view and edit marks entered by Subject Teachers. The latest authorized edit updates `mark_value`, sets `updated_by_user_id`, and writes an immutable entry to `audit_logs`.

---

## L. Report Revision Integrity

- Report PDFs are permanent historical artifacts stored in the filesystem and cataloged in `generated_reports`.
- The first generated report is stored with `revision_number = 1`.
- If marks or configurations change and a new report is generated, it is stored with `revision_number = 2`, and so forth.
- The database-level functional unique constraint `uk_gr_revision_identity` guarantees that no two records can share the same revision number for the same student and report context, preventing accidental duplicate file registrations.

---

## M. Subject Snapshot Behavior

- `class_subjects.subject_name_snapshot` captures the subject name at the time of academic year configuration.
- **Validation Scenario 40 proved:** Renaming a master subject in `subjects` (e.g., from *Mathematics* to *Advanced Mathematics*) leaves `class_subjects.subject_name_snapshot` completely unaffected (*Mathematics*).
- Historical marksheets and report cards will always display the exact subject name applicable during that academic year.

---

## N. Academic Year Closure Behavior

- The `academic_years.status` column supports `'open'` and `'closed'`.
- When `status = 'closed'`:
  - Subject Teachers and Class Teachers are restricted to read-only access by application authorization middleware.
  - Administrator and Office Staff retain permissions to make authorized mark corrections as defined by BRD V1.3.
- When a closed academic year is reopened:
  - Reopening is strictly for configuration adjustments.
  - Teacher mark-entry capability is **NOT** restored.

---

## O. Validation Scenarios Executed

All 40 required validation scenarios from Section 22 were implemented and executed:
1. **Academic Hierarchy:** Academic year, terms, class, and sections created.
2. **Student Placement:** Master student and initial placement in 8A Roll 15.
3. **Internal Transfer:** Transfer to 8B, marking 8A as `internal_transfer` and 8B as `active`.
4. **Historical Roll Number:** Coexistence of 8A Roll 15 and 8B Roll 22.
5. **Student-Specific Subject Allocation:** Main subjects allocated to student.
6. **Elective Allocation:** Specific electives allocated; unallocated electives excluded.
7. **Subject Name Snapshot:** Snapshot column populated and immutable.
8. **Assessment Type & Assessment:** Unit Test and Term Exam types and instances.
9. **Assessment Applicability:** Linking assessments to subjects with max marks.
10. **Different Maximum Marks Per Subject:** Math (20), English (25), Science (30).
11. **Blank Mark:** Result status `blank` with `NULL` mark value.
12. **Numeric Zero:** Result status `numeric` with `0.00` mark value.
13. **Numeric Decimal:** Result status `numeric` with `17.50` decimal value.
14. **Numeric Positive Mark:** Result status `numeric` with `25.00` value.
15. **Absent/A Mark:** Result status `absent` with `NULL` mark value.
16. **Invalid Negative Mark Rejection:** Real negative test on negative mark.
17. **Invalid Mark > Maximum Rejection:** Explicit application invariant demo.
18. **Invalid Result-State Combinations:** Real negative tests on state mismatches.
19. **Attendance Valid Case:** 45/50 days attended (90.00%).
20. **Attendance 0/0 Case:** 0/0 days handled without error; app displays N/A.
21. **Attendance > Working Days Rejection:** Real negative test on attendance bounds.
22. **Multiple Teacher Assignments:** Single user holding 3 distinct assignments.
23. **Class Teacher All-Subject Scope:** Assignment type `class_teacher` with NULL subject.
24. **Subject Teacher Subject Scope:** Assignment type `subject_teacher` with non-NULL subject.
25. **Calculation Settings Uniqueness:** One calculation setting per year + class.
26. **Calculation Method Values:** Validated `average_percentage` and `combined_marks`.
27. **Report Configuration:** Report config record with JSON metadata.
28. **Report Assessment Display Selection:** Selection and display ordering.
29. **Generated Report Revision 1:** Initial PDF generation recorded.
30. **Generated Report Revision 2:** Subsequent PDF generation recorded.
31. **Historical PDFs Not Overwritten:** Both revisions coexist with distinct paths.
32. **Revision Identity Integrity:** Real negative test on duplicate revision.
33. **Audit Log JSON Before/After Data:** Storing and extracting JSON audit payloads.
34. **Audit Log Immutability Expectation:** Explicit application invariant demo.
35. **FK Delete Restrictions:** Real negative test on parent delete under RESTRICT.
36. **Academic-Year Status:** Validated `open` and `closed` status transitions.
37. **Current Academic-Year Invariant:** Explicit application invariant demo.
38. **23-Table Count:** Structural confirmation of all 23 base tables.
39. **Required Indexes & Constraints:** Structural confirmation of 43 RESTRICT FKs and 7 checks.
40. **Subject Snapshot Historical Behavior:** Master subject rename does not alter snapshot.

---

## P. Negative Constraint Tests Executed

Seventeen (17) distinct negative constraint tests were actively executed and verified against MySQL 8.0:

| Test ID | Scenario | SQL Tested | Constraint Name / Type | MySQL Error Code | Result |
|---|---|---|---|---|---|
| 2 | 1 | Duplicate term `sequence_no` in same academic year | `uk_terms_year_seq` | `1062` | **PASS (EXPECTED REJECTION)** |
| 6 | 4 | Duplicate `roll_number` in same year/class/section | `uk_sar_year_class_section_roll` | `1062` | **PASS (EXPECTED REJECTION)** |
| 13 | 10 | `maximum_marks = 0.00` in assessment applicability | `chk_aa_max_marks_positive` | `3819` | **PASS (EXPECTED REJECTION)** |
| 19 | 16 | `mark_value = -5.00` with `numeric` status | `chk_marks_result_consistency` | `3819` | **PASS (EXPECTED REJECTION)** |
| 21 | 18 | `numeric` status with `mark_value = NULL` | `chk_marks_result_consistency` | `3819` | **PASS (EXPECTED REJECTION)** |
| 22 | 18 | `blank` status with `mark_value = 15.00` | `chk_marks_result_consistency` | `3819` | **PASS (EXPECTED REJECTION)** |
| 23 | 18 | `absent` status with `mark_value = 15.00` | `chk_marks_result_consistency` | `3819` | **PASS (EXPECTED REJECTION)** |
| 26 | 21 | `days_attended (55) > total_working_days (50)` | `chk_attendance_days_within_total` | `3819` | **PASS (EXPECTED REJECTION)** |
| 29 | 23 | `class_teacher` with non-NULL `subject_id` | `chk_ta_assignment_subject_consistency` | `3819` | **PASS (EXPECTED REJECTION)** |
| 31 | 24 | `subject_teacher` with NULL `subject_id` | `chk_ta_assignment_subject_consistency` | `3819` | **PASS (EXPECTED REJECTION)** |
| 33 | 25 | Duplicate calculation settings for same year/class | `uk_cs_year_class` | `1062` | **PASS (EXPECTED REJECTION)** |
| 35 | 26 | Invalid calculation method enum `'weighted_formula'` | `ENUM` Data Truncation | `1265` | **PASS (EXPECTED REJECTION)** |
| 38 | 28 | Duplicate assessment in report selection | `uk_ras_config_assessment` | `1062` | **PASS (EXPECTED REJECTION)** |
| 42 | 32 | Duplicate Revision 1 for same student/report/term | `uk_gr_revision_identity` | `1062` | **PASS (EXPECTED REJECTION)** |
| 45 | 35 | Delete academic year with active children | `ON DELETE RESTRICT` | `1451` | **PASS (EXPECTED REJECTION)** |
| 47 | 36 | Invalid academic year status enum `'archived'` | `ENUM` Data Truncation | `1265` | **PASS (EXPECTED REJECTION)** |
| 52 | 40 | Duplicate class-wide config (`section_id IS NULL`) | `uk_class_subjects_config` | `1062` | **PASS (EXPECTED REJECTION)** |

---

## Q. Validation Results

Execution summary from `database\validation_results.txt`:

```text
=============================================================================
VALIDATION SUITE EXECUTION SUMMARY
=============================================================================
Total Tests Executed:                52
Total Tests Passed:                  52
Total Tests Failed:                  0
Expected Rejections Verified:        17
Application Invariants Documented:   3
Final Cleanup:                       All test fixtures cleaned up; 4 seed roles intact.
Overall Exit Status:                 0 (SUCCESS)
=============================================================================
```

---

## R. Any Remaining Limitations

The following domain rules are intentionally classified as **Application-Level Invariants** because enforcing them in the database engine would require multi-table triggers or excessive schema denormalization:

1. **Mark Value Upper Bound (`0 <= mark_value <= maximum_marks`):**
   MySQL CHECK constraints cannot query other tables. The marks service must transactionally compare `mark_value` against `assessment_applicability.maximum_marks`.
2. **Marks Cross-Reference Consistency:**
   The marks service must transactionally verify that the student placement, subject allocation, and assessment applicability all align with the same academic year, class, and section.
3. **Single Current Academic Year (`academic_years.is_current = TRUE`):**
   The application service must ensure that activating an academic year deactivates any previously current year inside an atomic transaction.
4. **Attendance 0/0 Display:**
   When `total_working_days = 0`, the application rendering engine must output `N/A` rather than executing division.
5. **Elective Modification Lockout:**
   The student allocation service must check whether marks already exist before allowing a student's elective allocation to be changed.

---

## S. Final Verdict

# **READY FOR APPLICATION DEVELOPMENT**

### Summary of Justification:
1. **Full Compliance:** Strict alignment with BRD V1.3, the approved database design (`DB-table-definitons.txt`), and the approved ERD.
2. **23 Tables Preserved:** Exactly 23 approved tables are implemented without additions or removals.
3. **Historical Data Safety:** All 43 foreign keys enforce `ON DELETE RESTRICT ON UPDATE RESTRICT`; cascading deletes are eliminated; `subject_name_snapshot` preserves historical subject labels; generated PDF revisions are immutable.
4. **Active Verification:** All 52 positive and negative tests passed with 0 failures, including 17 verified constraint rejections.
5. **Clean Baseline:** The database is pristine with only the 4 baseline roles populated, fully prepared for backend and API implementation.
