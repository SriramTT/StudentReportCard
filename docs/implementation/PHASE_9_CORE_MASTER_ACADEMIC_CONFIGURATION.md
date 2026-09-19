# Phase 9 Implementation Report: Core Master & Academic Configuration Modules

## Scope Summary
Phase 9 delivers the administrative and academic configuration backbone for the School Examination Marks and Report Card Management System across 12 modules:
1. School Settings (`SchoolSetting`, `school_settings`)
2. Academic Years (`AcademicYear`, `academic_years`)
3. Terms (`Term`, `terms`)
4. Classes (`SchoolClass`, `classes`)
5. Sections (`Section`, `sections`)
6. Subjects (`Subject`, `subjects`)
7. Class Subjects (`ClassSubject`, `class_subjects`)
8. Assessment Types (`AssessmentType`, `assessment_types`)
9. Assessments (`Assessment`, `assessments`)
10. Assessment Applicability (`AssessmentApplicability`, `assessment_applicability`)
11. Calculation Settings (`CalculationSetting`, `calculation_settings`)
12. Report Configurations & Selections (`ReportConfiguration`, `report_configurations`, `ReportAssessmentSelection`, `report_assessment_selections`)

---

## Architectural Invariants Verified
1. **Singleton School Settings**:
   - Exactly one settings row.
   - Administrator-only modification with audit trail.
   - Pass mark validation ($\ge 0$).
2. **Atomic Single-Current Academic Year**:
   - At most one year has `is_current = true`. Switched atomically in a transaction.
   - Open/Close/Reopen lifecycle managed solely by Administrator.
3. **Dynamic Terms**:
   - $N$ terms supported ($N \ge 1$), ordered by `sequence_no ASC`.
   - Contextual uniqueness on `(academic_year_id, sequence_no)` and `(academic_year_id, name)`.
4. **Server-Side Subject Name Snapshot**:
   - `class_subjects.subject_name_snapshot` is locked to `Subject.name` on creation.
   - Renaming master subjects does not alter historical snapshots. Client snapshot tampering is rejected.
5. **Maximum Marks on Applicability Only**:
   - No `maximum_marks` column on `assessments`.
   - Stored strictly on `assessment_applicability`, permitting different maximum marks per subject for the same assessment.
   - Validated: decimal marks, marks $> 100$, and rejection of $\le 0$.
6. **Cross-Year Integrity**:
   - Assessment term must belong to assessment academic year.
   - Assessment applicability must link assessment and class subject within the identical academic year.
7. **Calculation & Report Decoupling**:
   - `calculation_settings` configured per `(academic_year_id, class_id)` with `average_percentage` or `combined_marks`.
   - `report_assessment_selections` governs visual report presentation only (`display_order`, `is_displayed`). Calculation participation is decoupled and contains no `contributes_to_calculation` column.

---

## Verification Summary
- **PHPUnit / Pest Test Suites**: 71 tests, 328 assertions, 0 failures (100% pass).
- **PostgreSQL Validation Scripts (`run_all.sql`)**: 54 core schema assertions + 16 classroom scale scenarios = 70 assertions, 0 failures, 0 schema drift.
- **Vite Build**: Compiled cleanly into `public/build/`. Node.js remains build-time only.
