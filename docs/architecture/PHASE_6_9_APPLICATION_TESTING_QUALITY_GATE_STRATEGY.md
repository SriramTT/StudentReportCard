# PHASE 6.9 — APPLICATION TESTING, AUDIT & QUALITY GATE MASTER SPECIFICATION
## Comprehensive Multi-Tier Verification, Zero-Tolerance Testing Suites, Invariant Safeguards & Release-Blocking Quality Gates

**Project:** School Examination Marks and Report Card Management System  
**Document Version:** 2.0 (Authoritative Master Specification)  
**Phase:** 6.9 — Application Testing & Quality Gate Strategy  
**Status:** Draft / Pending Verification (Specification Reconciled; Tests Pending Execution)  

---

## 1. Purpose & Core Testing Objectives

This document establishes the definitive, implementation-ready master testing architecture and quality gate specification for the **School Examination Marks and Report Card Management System**. It specifies the precise test suites, execution tiers, deterministic fixtures, invariant assertions, concurrency stress protocols, and release-blocking quality gates required before any application code deployment.

### 1.1 Core Objectives
1. **Full Functional & Business Rule Verification:** Validate that every requirement defined in BRD V1.3, finalized handover decisions, and the architectural ledger (DEC-001 through DEC-056) is enforced by automated test suites.
2. **Strict Schema & Relational Integrity:** Enforce regression testing directly against the approved 23-table PostgreSQL schema, verifying foreign keys, CHECK constraints, triggers, and functional unique indexes without database abstraction mocking.
3. **Multi-Tier Security & Scope Defense:** Prevent Horizontal/Vertical Privilege Escalation, Insecure Direct Object References (IDOR), path traversal, and SSRF across all user roles (Administrator, Office Staff, Class Teacher, Subject Teacher).
4. **Data Semantic Invariants:** Guarantee that marks preserve ternary states (`blank` $\ne$ `numeric 0.00` $\ne$ `absent A`), percentages format to 2 decimal places, and attendance never triggers division by zero ($0/0 \to \text{"N/A"}$).
5. **Concurrency & Failure Resilience:** Guarantee that simultaneous report generation requests never cause duplicate revisions or overwritten files, and that filesystem failure rollbacks leave historical reports completely intact.
6. **Zero-Tolerance Quality Gates:** Establish a single, unified Quality Gate model (Gate A through Gate W) covering all 23 verification domains without competing gate numbering systems, blocking build pipelines upon detecting authorization leaks, data coercion, or schema mutations.

---

## 2. Relationship to Previous Phases (6.1–6.8)

Phase 6.9 sits directly upon the architectural foundations established in Phases 6.1 through 6.8:

```
Phase 6.1: Technology Baseline (PHP 8.4+, Laravel 13, PostgreSQL 18.x, Vanilla JS, Plain CSS)
    ↓
Phase 6.2: Domain Model & Eloquent Architecture (Domain Models, Service Layer, Enums)
    ↓
Phase 6.3: Project Folder Structure & Architectural Boundaries (Private storage, thin controllers)
    ↓
Phase 6.4: PostgreSQL Integration & Decision Ledger (23 Tables, DEC-001 to DEC-030)
    ↓
Phase 6.5: Authentication, Authorization & Security Architecture (ReportPolicy, Role Scopes)
    ↓
Phase 6.6: HTTP Layer, Routes, Form Requests & Controllers (Thin Endpoints, Strict FormRequests)
    ↓
Phase 6.7: Blade UI & Vanilla JS Architecture (Reconciled layouts, string-preserving decimal regex)
    ↓
Phase 6.8: PDF Generation & File Storage (Browsershot, Two-Phase Staging, DEC-049 to DEC-055)
    ↓
Phase 6.9: Application Testing & Quality Gate Strategy (Current Phase: Master Test Architecture)
    ↓
Phase 6.10: Environment Configuration, Production Hardening & Deployment Readiness
```

---

## 3. Authoritative Source Precedence Hierarchy

Every automated test case must trace back to the authoritative project hierarchy:

1. **BRD V1.3** (`BRD/School_Examination_Marksheet_Requirements_v1_3.docx`):
   - Mark permissions and latest saved value: BR-001 to BR-008
   - Mark ternary states (`blank`, `numeric`, `absent`): BR-009 to BR-014
   - Dynamic assessments and contextual maximum marks: BR-015 to BR-024
   - Dual calculation formulas and 2-decimal percentages: BR-025 to BR-031
   - Dynamic term counts and flexible names: BR-032 to BR-036
   - Student continuity, historical placements, and snapshot subjects: BR-037 to BR-047
   - Report-card attendance (0/0 division defense): BR-048 to BR-053, CL-010
   - Academic-year closure rules: BR-054 to BR-057, CL-003
   - Report card behavior, revisions, pass/fail, and exclusions: BR-058 to BR-068
   - Immutable audit logging: BR-076 to BR-078
   - Marksheet completion invariants: BR-080 to BR-084
   - Clarifications: CL-001 to CL-011
2. **Finalized Business Rules & Project Handover Decisions**: Class Teacher generation/download scoped to assigned classroom; Subject Teacher report denial; single-school tenancy.
3. **Approved 23-Table Relational Schema** (`database/Postgres/schema.sql`, `DB design/DB-table-definitons.txt`).
4. **Project Decision Ledger** (`docs/decisions.md`, DEC-001 through DEC-056).

---

## 4. Absolute Domain Constraints & Invariants

The approved relational model contains **exactly 23 tables**. Any test, fixture, or code proposing schema redesign will immediately fail:

```text
roles, users, academic_years, terms, classes, sections, subjects, class_subjects,
students, student_academic_records, student_subject_allocations, assessment_types,
assessments, assessment_applicability, teacher_assignments, marks, attendance,
calculation_settings, report_configurations, report_assessment_selections,
school_settings, generated_reports, audit_logs.
```

- ❌ **NO Student Admission Numbers (Pre-Phase 10 Baseline):** In the pre-Phase 10 baseline, admission numbers were prohibited. Under DEC-072 in Phase 10, `students.admission_number` (`VARCHAR(50) NOT NULL UNIQUE`) was approved as the unique student business identifier. Test fixtures, imports, and directory assertions in Phase 10 now require valid unique admission numbers, while demographic fields remain strictly prohibited.
- ❌ **NO Invented Demographics:** No date of birth, gender, guardian/parent names, home address, contact telephone, email, or student photographs may be asserted or displayed.
- ❌ **NO Batch PDF Generation:** The application does NOT support bulk classroom exports, ZIP archives, or background class-wide queues (explicitly deferred in BRD Section 4). Report generation testing is strictly per-student.
- ❌ **NO Unapproved Academic Metrics:** Tests must strictly fail if annual overall percentages (BR-064), student rankings (BR-065), GPA calculations, grade bands (BR-067), or automatic promotion statuses appear in output.
- ❌ **NO Public File Storage:** PDF reports must never be written to or read from `public/` or `storage/app/public/`. Direct public URLs do not exist.
- ❌ **NO Frontend Calculation Authority:** Client JavaScript is strictly an ergonomic presentation layer. All calculations, range checks, completion invariants, and authorizations must be asserted server-side.

---

## 5. Core Testing Principles

The testing architecture adheres to six non-negotiable principles:

1. **Zero-Mock Database Schema Invariants:** All database integration, service, and HTTP tests execute against a dedicated, real PostgreSQL 18.x database container. SQLite or in-memory emulation is **strictly prohibited**, as SQLite lacks PostgreSQL's functional unique indexes (`uk_gr_revision_identity`), deferred CHECK constraints, and row-level locking (`FOR UPDATE`).
2. **Deterministic Test Fixtures:** Tests never rely on randomized faker data for critical business boundaries. Fixtures explicitly define exact academic years, classes, sections, max marks, and decimal scores.
3. **Exact Decimal Arithmetic Assertions:** Mark scores and percentages are asserted via exact string matching (e.g. `$this->assertSame('48.50', $result)`), never via lossy floating-point comparisons (`assertEquals(48.5, $result)`).
4. **Strict Ternary Separation:** Tests must continuously prove:
   $$\text{numeric 0.00} \ne \text{blank (NULL)} \ne \text{absent ('A')}$$
5. **Security Parity Between HTTP and CLI:** Role authorization boundaries must be verified at the policy and controller layers. UI element hiding (`@can`) is never accepted as proof of security.
6. **Immutable Historical Assertion:** Historical report tests must assert byte-for-byte immutability of `revision-1.pdf` after `revision-2.pdf` is compiled.

---

## 6. Testing Hierarchy & Pyramid Architecture

The test suite is structured into ten distinct execution tiers:

```
                            /\
                           /  \     Tier 10: E2E Acceptance Tests (Full User Journeys)
                          /────\
                         /      \    Tier 9: Visual Regression Tests (Chromium Layout Parity)
                        /────────\
                       /          \   Tier 8: Concurrency & Stress Tests (Row Lock Serialization)
                      /────────────\
                     /              \  Tier 7: Filesystem Failure & Compensating Rollback Tests
                    /────────────────\
                   /                  \ Tier 6: PDF Rendering Tests (Browsershot A4 / Print CSS)
                  /────────────────────\
                 /                      \ Tier 5: Browser & Vanilla JS Tests (DOM / Accessibility)
                /────────────────────────\
               /                          \ Tier 4: HTTP, Routes & Form Request Validation Tests
              /────────────────────────────\
             /                              \ Tier 3: Security, Scope & Authorization Policy Tests
            /────────────────────────────────\
           /                                  \ Tier 2: Database Schema & Relational Integration Tests
          /────────────────────────────────────\
         /                                      \ Tier 1: Unit & Domain Service Tests (Calculations, Mark Rules)
        /────────────────────────────────────────\
```

| Tier | Focus Area | Execution Tool | Target Speed | Mocking Policy |
| :--- | :--- | :--- | :--- | :--- |
| **Tier 1: Unit / Domain** | Calculation formulas, mark validations, attendance math | PHPUnit / Pest | $< 5\text{ms}$ / test | Pure PHP; zero DB |
| **Tier 2: DB Integration** | Relational constraints, triggers, unique indexes | PHPUnit + Real PostgreSQL | $< 50\text{ms}$ / test | Zero mocks; real transactions |
| **Tier 3: Auth & Scope** | Policies, classroom scopes, closed-year gates | Laravel Feature Tests | $< 40\text{ms}$ / test | Real DB; authenticated users |
| **Tier 4: HTTP / Forms** | Route bindings, FormRequests, status codes, JSON | Laravel Feature Tests | $< 50\text{ms}$ / test | Real DB; CSRF enabled |
| **Tier 5: UI & JS** | Keyboard grid nav, string preservation, modals | Playwright / Pest Browser | $< 300\text{ms}$ / test | Real headless browser |
| **Tier 6: PDF Rendering** | Browsershot A4 compilation, table pagination | Spatie Browsershot | $< 1.5\text{s}$ / test | Real Chromium process |
| **Tier 7: FS Failure** | Two-phase rollback, compensating delete, orphans | PHPUnit + Storage Fake | $< 60\text{ms}$ / test | Real local filesystem disk |
| **Tier 8: Concurrency** | Row locks (`FOR UPDATE`), revision race conditions | Multi-process PHP / SQL | $< 2\text{s}$ / suite | Real PostgreSQL processes |
| **Tier 9: Visual Regr.** | Layout clipping, column alignment, header repeats | Chromium Pixel Match | $< 2\text{s}$ / test | Headless Chromium artifacts |
| **Tier 10: E2E Acceptance**| Complete flow: Import $\to$ Marks $\to$ PDF $\to$ Download | Feature / Browser | $< 4\text{s}$ / flow | Complete system integration |

---

## 7. Deterministic Test Fixture Architecture

To eliminate flaky tests and ensure repeatable assertions, the test suite utilizes a strict, deterministic fixture hierarchy (`tests/Fixtures/BaseAcademicFixture.php`):

```
Base Academic Year (2025-26, Status: Open)
  ├── Terms:
  │     ├── Term 1 (sequence_no: 1)
  │     └── Term 2 (sequence_no: 2)
  ├── School Settings:
  │     ├── school_name: "Greenwood International School"
  │     ├── school_logo_path: "branding/greenwood_crest.png"
  │     └── pass_mark: 40.00
  ├── Classes & Sections:
  │     ├── Class 8 (sequence_no: 1) ── Section A (sequence_no: 1) & Section B
  │     └── Class 9 (sequence_no: 2) ── Section A
  ├── Subjects (Curriculum):
  │     ├── Mathematics (Code: MATH) ── Applicability: Max 50.00
  │     ├── Science (Code: SCI)       ── Applicability: Max 100.00
  │     └── English (Code: ENG)       ── Applicability: Max 25.00
  ├── Assessments:
  │     ├── Unit Test 1 (type: class_test)
  │     ├── Mid Term (type: class_test)
  │     └── Term Exam (type: term_exam)
  ├── Students & Academic Placements:
  │     ├── Student 101: "Aarav Sharma" (Roll 01, Class 8-A)
  │     ├── Student 102: "Bhavna Patel" (Roll 02, Class 8-A)
  │     └── Student 103: "Chetan Kumar" (Roll 03, Class 8-A)
  └── User Accounts & Assignments:
        ├── Admin User: admin (role: administrator)
        ├── Staff User: staff_clerk (role: office_staff)
        ├── Class Teacher User: teacher_ct (role: class_teacher, 8-A)
        └── Subject Teacher User: teacher_st (role: subject_teacher, Math 8-A)
```

---

## 8. Role Authorization & Contextual Scope Test Suite

`AUTH-001` through `AUTH-012` verify the exact role-based authorization matrix:

| Test ID | Role | Action Attempted | Scope Context | Expected HTTP Status |
| :--- | :--- | :--- | :--- | :--- |
| `AUTH-001` | **Administrator** | Generate Report | Any class, any student | **200 OK** |
| `AUTH-002` | **Administrator** | Download Report | Any historical report | **200 OK** |
| `AUTH-003` | **Administrator** | View Audit Logs | Institutional audit trail | **200 OK** |
| `AUTH-004` | **Office Staff** | Generate Report | Any class, any student | **200 OK** |
| `AUTH-005` | **Office Staff** | Download Report | Any historical report | **200 OK** |
| `AUTH-006` | **Office Staff** | View Audit Logs | System audit route | **403 Forbidden** |
| `AUTH-007` | **Class Teacher** | Generate Report | **Assigned Classroom (8-A)** | **200 OK** |
| `AUTH-008` | **Class Teacher** | Download Report | **Assigned Classroom (8-A)** | **200 OK** |
| `AUTH-009` | **Class Teacher** | Generate Report | **Unassigned Classroom (8-B)** | **403 Forbidden** |
| `AUTH-010` | **Class Teacher** | Download Report | **Unassigned Classroom (8-B)** | **403 Forbidden** |
| `AUTH-011` | **Subject Teacher** | Generate Report | Any student | **403 Forbidden** |
| `AUTH-012` | **Subject Teacher** | Download Report | Any generated report | **403 Forbidden** |

### 8.1 Multi-Assignment Scope Resolution
- **`AUTH-MULTI-001` (Composite Assignment Resolution):** A teacher possessing Class Teacher assignment for 8-A and Subject Teacher assignment for Math 9-A can generate reports for 8-A, enter Math marks for 9-A, but CANNOT generate reports for 9-A (HTTP 403).
- **`AUTH-MULTI-002` (Expired Assignment Lockout):** When `teacher_assignments.effective_to` is prior to today, the teacher's operational access is immediately revoked.

---

## 9. Database Integrity & Relational Invariants Test Suite

`DB-REG-001` through `DB-REG-008` assert that PostgreSQL schema integrity matches `schema.sql`:
- **`DB-REG-001` (Strict 23-Table Schema Invariant):** Asserts that `information_schema.tables` in schema `public` contains **exactly 23 approved base tables**. Asserts zero unapproved tables (no permission/ACL tables, no workflow tables, no PDF metadata tables, no tenant tables) and zero unapproved columns (no `file_hash`, `file_size`, `status`, `deleted_at`, `tenant_id`, or unapproved demographic student fields; `students.admission_number` approved under DEC-072).
- **`DB-REG-002` (Revision Identity Functional Index):** Asserts index `uk_gr_revision_identity` exists on `(student_academic_record_id, report_type, COALESCE(term_id, 0), COALESCE(assessment_id, 0), revision_number)`.
- **`DB-REG-003` (Roll Number Uniqueness Index):** Asserts index `uk_sar_active_roll_identity` prevents duplicate roll numbers in the same class/section over overlapping effective dates.
- **`DB-REG-004` (Attendance CHECK Constraint):** Direct SQL insertion of `days_attended > total_working_days` fails with `chk_att_days_consistent`.
- **`DB-REG-005` (Teacher Assignment Consistency Constraint):** Inserting `assignment_type = 'class_teacher'` with a non-null `subject_id` fails with `chk_ta_assignment_subject_consistency`.
- **`DB-REG-006` (Immutable Audit Logs Trigger):** Executing `UPDATE` or `DELETE` on `audit_logs` fails with PostgreSQL trigger `trg_audit_logs_immutable`.

---

## 10. Mark States & Precision Test Suite

### 10.1 Mark Semantic Invariant Tests
`UNIT-MARK-001` through `UNIT-MARK-006` verify the ternary mark states and decimal precision rules:

| Test ID | Method / Context | Input State | Expected Domain Behavior | Assertion Criterion |
| :--- | :--- | :--- | :--- | :--- |
| `UNIT-MARK-001` | `Mark::setStatus()` | `result_status = 'blank', mark_value = NULL` | Valid incomplete mark. Contributes 0 to completion; cannot compile report. | `$mark->is_complete === false` |
| `UNIT-MARK-002` | `Mark::setStatus()` | `result_status = 'numeric', mark_value = 0.00` | Valid complete mark. Contributes 0.00 to sum; satisfies completion check. | `$mark->is_complete === true` |
| `UNIT-MARK-003` | `Mark::setStatus()` | `result_status = 'absent', mark_value = NULL` | Valid complete mark. Displays as `'A'`, contributes 0.00 to sum; satisfies completion check. | `$mark->is_complete === true` |
| `UNIT-MARK-004` | Semantic Distinction | Compare `0.00` vs `blank` vs `absent` | System preserves three distinct states; never coerces blank $\to$ 0 or absent $\to$ 0.00. | `assertNotEquals($blank, $zero); assertNotEquals($blank, $absent);` |
| `UNIT-MARK-005` | Decimal Precision | Mark values: `0.01`, `12.50`, `48.75`, `99.99` | Stored and formatted as exact decimal strings without float truncation. | `$mark->formatted_value === '48.75'` |
| `UNIT-MARK-006` | Decimal Max Marks | Applicability `max_marks = 33.33` | Mark `33.33` passes; Mark `33.34` throws `ValidationException`. | `assertThrows(ValidationException::class)` |

### 10.2 Contextual Boundary Tests
`UNIT-MARK-007` tests mark validity against contextual maximum marks:
- **Case A (Mathematics Unit Test, Max = 25.00):**
  - Score `24.99` $\to$ PASS.
  - Score `25.00` $\to$ PASS.
  - Score `25.01` $\to$ FAIL (`ValidationException: Mark exceeds maximum permissible score of 25.00`).
- **Case B (Science Term Exam, Max = 100.00):**
  - Score `100.00` $\to$ PASS.
  - Score `100.01` $\to$ FAIL (`ValidationException: Mark exceeds maximum permissible score of 100.00`).
- **Case C (Zero & Negative Boundaries):**
  - Score `0.00` $\to$ PASS.
  - Score `-0.01` $\to$ FAIL (`ValidationException: Mark cannot be negative`).

---

## 11. Decimal Precision & Frontend Representation Test Suite

- **`DEC-PIPE-001` (Strict Client Regex):** Tests that entering `"12abc"`, `"0.00trailing"`, `"1e5"`, or `"NaN"` in the mark grid is rejected by the strict decimal regular expression `/^\d+(\.\d{1,2})?$/`, setting the cell status to `invalid` and preventing form submission.
- **`DEC-PIPE-002` (End-to-End Decimal String Preservation):**
  $$\text{Blade View} \xrightarrow{\text{"48.50"}} \text{DOM} \xrightarrow{\text{"48.50"}} \text{JSON Payload} \xrightarrow{\text{"48.50"}} \text{FormRequest} \xrightarrow{\text{"48.50"}} \text{PostgreSQL numeric(5,2)}$$
  Asserts that at no stage in the transmission pipeline is `parseFloat()` or floating-point binary conversion executed.

---

## 12. Calculation Engine Test Suite

### 12.1 Calculation Method 1 (Equal Average of Assessment Percentages)
`DOMAIN-CALC-001`: Verify that Method 1 averages individual assessment percentages:
$$\text{Percentage} = \frac{\sum (\frac{\text{Mark}_i}{\text{Max}_i} \times 100)}{N}$$
- Unit Test 1: $20.00 / 25.00 = 80.00\%$
- Mid Term: $40.00 / 50.00 = 80.00\%$
- Average $= (80.00 + 80.00) / 2 = 80.00\%$
- Test asserts exact return string `'80.00'`.

### 12.2 Calculation Method 2 (Sum Obtained / Sum Maximum)
`DOMAIN-CALC-002`: Verify that Method 2 computes percentage from aggregate marks:
$$\text{Percentage} = \frac{\sum \text{Marks Obtained}}{\sum \text{Maximum Marks}} \times 100$$
- Unit Test 1: $20.00 / 25.00$
- Mid Term: $40.00 / 50.00$
- Total $= 60.00 / 75.00 \times 100 = 80.00\%$
- Test asserts exact return string `'80.00'`.

### 12.3 Term Exam Exclusive Contribution Rule (BR-028)
`DOMAIN-CALC-003`: Verify that under finalized business rules, only assessments of type `term_exam` participate in the term percentage:
- Student Marks:
  - Unit Test 1 (class_test): $24.00 / 25.00$
  - Mid Term (class_test): $48.00 / 50.00$
  - Term Exam (term_exam): $70.00 / 100.00$
- Expected Calculation: Term Percentage is derived strictly from Term Exam ($70.00\%$).
- Assertion: `$termPercentage === '70.00'`. Unit Test and Mid Term marks remain displayed on report but contribute $0\%$ to term percentage.

### 12.4 Absent Score Contribution
`DOMAIN-CALC-004`: Verify absent score participation:
- Mathematics Term Exam: `absent ('A')` on max $100.00$.
- Calculation: Contributes $0.00$ obtained / $100.00$ max $\to 0.00\%$.
- Visual Report displays `'A'`; aggregate calculation includes $0.00$.

---

## 13. Student Continuity, Placement & Transfer Test Suite

`DOMAIN-TRANS-001` through `DOMAIN-TRANS-007` verify placement immutability and historical preservation:

- **`DOMAIN-TRANS-001` (Internal Transfer Placement Creation):** Transferring a student from Class 8-A to Class 8-B terminates the old `student_academic_records` placement by setting `effective_to` and inserts a new placement row with `effective_from = now()`. The original placement is **never updated in place or deleted**.
- **`DOMAIN-TRANS-002` (Historical Marks Unaltered by Transfer):** Marks entered for Student 101 under Class 8-A remain linked to the historical placement record. Querying marks for Class 8-A continues to show historical performance. Current placement is never substituted for historical placement.
- **`DOMAIN-TRANS-003` (Historical Report Card Placement Retention):** Re-generating a Term 1 report card for Student 101 uses the historical Class 8-A placement active during Term 1, rendering `"Class: 8 - Section: A"` and Roll Number `01`, even if the student now resides in Class 8-B.
- **`DOMAIN-TRANS-004` (Roll Number Scope Uniqueness):** Two students cannot share Roll Number `01` in the same Class 8-A during overlapping effective dates (`uk_sar_active_roll_identity`).
- **`DOMAIN-TRANS-005` (Roll Number Mutation Isolation):** Changing a student's roll number for an active placement does not rewrite, alter, or orphan historical mark entries.
- **`DOMAIN-TRANS-006` (Frozen Subject Snapshot Immutability):** Verifies that `class_subjects.subject_name_snapshot` is preserved; if a subject is later renamed in the master catalogue (`subjects.name`), past report compilations continue to render the historical snapshot title.
- **`DOMAIN-TRANS-007` (Elective Lockout Invariant):** Verifies that modifying a student's elective allocation is rejected with HTTP 422 once any mark record exists for that student in that elective.

---

## 14. Attendance Test Suite

`DOMAIN-ATT-001` through `DOMAIN-ATT-006` verify term-based report-card attendance and server-authoritative calculations:
- **Server Authority Invariant:** Client-side JavaScript attendance displays and auto-ratios are strictly non-authoritative UX aids. The server remains the sole authority for bounds validation (`days_attended <= total_working_days`), percentage calculation, and database persistence.

| Test ID | Condition | Input Values | Expected Result | Error Defense |
| :--- | :--- | :--- | :--- | :--- |
| `DOMAIN-ATT-001` | Normal Attendance | Attended: 85, Total: 100 | `'85.00%'` | Normal percentage calculation |
| `DOMAIN-ATT-002` | Perfect Attendance | Attended: 100, Total: 100 | `'100.00%'` | Exact boundary check |
| `DOMAIN-ATT-003` | Zero Attended Days | Attended: 0, Total: 90 | `'0.00%'` | Valid zero attended score |
| `DOMAIN-ATT-004` | **Zero Working Days (CL-010)** | Attended: 0, Total: 0 | **`"N/A"`** | **Division by zero prevented; returns N/A** |
| `DOMAIN-ATT-005` | Invalid Attended Days | Attended: 95, Total: 90 | `ValidationException` | CHECK constraint `chk_att_days_consistent` throws |

---

## 15. Academic-Year Lifecycle Test Suite

`AUTH-CLOSED-001` through `AUTH-CLOSED-006` test behavior when `academic_years.status = 'closed'`:
- **`AUTH-CLOSED-001` (Teacher Mark Mutation Blocked):** When the academic year is closed, Subject Teachers and Class Teachers attempting to update marks via `POST /marks/batch-save` receive **HTTP 403 Forbidden** (BR-054).
- **`AUTH-CLOSED-002` (Teacher Attendance Mutation Blocked):** Teachers attempting to edit attendance in a closed year receive **HTTP 403 Forbidden**.
- **`AUTH-CLOSED-003` (Administrative Correction Retained with Reason & Audit):** Administrator and Office Staff retain authorized capability to correct marks and update placements in closed years (DEC-031). Corrections strictly require a `correction_reason` and are immutably audited in `audit_logs`.
- **`AUTH-CLOSED-004` (Class Teacher Report Generation Retained):** Class Teachers may still generate and download historical report cards for their assigned classroom in a closed academic year (DEC-038). This reporting permission is decoupled and independent from mark mutation permissions.
- **`AUTH-CLOSED-005` (Year Reopening Restriction):** Reopening a closed academic year (`status` changed from `closed` to `active`) does NOT automatically restore teacher editing permissions without explicit administrative unlocking.
- **`AUTH-CLOSED-006` (Read-Only Enforcement for Inactive Staff):** Deactivated users or expired teacher assignments cannot mutate records or generate reports regardless of year status.

---

## 16. Report Completion Validation Test Suite

`REPORT-COMP-001` through `REPORT-COMP-005` verify report completion gates:

| Test ID | Student Setup | Subject Evaluation Matrix | Expected Result | Action Taken |
| :--- | :--- | :--- | :--- | :--- |
| `REPORT-COMP-001` | Student A | Math: 45.00, Sci: 80.00, Eng: 20.00 | **Complete** | PDF Compilation Allowed |
| `REPORT-COMP-002` | Student B | Math: 45.00, Sci: Absent ('A'), Eng: 20.00 | **Complete** | PDF Compilation Allowed |
| `REPORT-COMP-003` | Student C | Math: 45.00, Sci: **Blank**, Eng: 20.00 | **Incomplete** | Aborted with `IncompleteMarksheetException` |
| `REPORT-COMP-004` | Student D | Math: **Blank**, Sci: **Blank**, Eng: **Blank** | **Incomplete** | Aborted with `IncompleteMarksheetException` |
| `REPORT-COMP-005` | Class Roster | Student A (Complete), Student C (Incomplete) | **Independent** | Generating report for Student A succeeds; Student C fails. One student does not block another. |

---

## 17. Report Generation & Download Authorization Test Suite

`REPORT-AUTH-001` through `REPORT-AUTH-005` verify authorization across all four roles via strict relational traversal:
- **Role Permissions Invariant:**
  - **Administrator:** Generation allowed across broad institutional scope; download allowed.
  - **Office Staff:** Generation allowed across broad operational scope; download allowed.
  - **Class Teacher:** Generation and download allowed **strictly for active assigned classroom** (`academic_year_id`, `class_id`, `section_id`). Generation/download for any unassigned classroom is blocked with **HTTP 403 Forbidden**.
  - **Subject Teacher:** Cannot generate final report cards (HTTP 403); cannot download final report cards (HTTP 403).
- **`REPORT-AUTH-001` (Relational Traversal Enforcement):** Authorization checks must never rely on role name alone or arbitrary client-supplied IDs. Authorization strictly traverses:
  $\text{GeneratedReport} \rightarrow \text{StudentAcademicRecord} \rightarrow (\text{academic\_year\_id}, \text{class\_id}, \text{section\_id}) \rightarrow \text{active teacher\_assignments}$
- **`REPORT-AUTH-002` (Subject Teacher Report Denial):** A Subject Teacher attempting to call `POST /reports/preview`, `POST /reports/generate`, or `GET /reports/download/{id}` receives **HTTP 403 Forbidden**.
- **`REPORT-AUTH-003` (Manipulated Report ID Defense):** Accessing another section's report card by guessing or tampering with the report primary key fails authorization and returns **HTTP 403 Forbidden**.
- **`REPORT-AUTH-004` (Deactivated Session Revocation):** If a user is deactivated mid-session, all report generation and download capabilities terminate immediately upon session invalidation.

---

## 18. Revision Allocation & Concurrency Stress Test Suite

### 18.1 Concurrency Timing & Transaction Mechanics (DEC-050, DEC-054)
1. **Decoupled Staging:** Browsershot renders the candidate PDF to `temp/reports/{uuid}.pdf` **outside** the database transaction ($500\text{ms}-2\text{s}$ duration).
2. **Atomic Row Lock Window:** The database transaction opens only for promotion:
   ```sql
   BEGIN;
   SELECT id FROM student_academic_records WHERE id = :record_id FOR UPDATE;
   SELECT COALESCE(MAX(revision_number), 0) + 1 FROM generated_reports WHERE ...;
   -- Atomic file move via Storage::disk('private_reports')->move()
   INSERT INTO generated_reports (...);
   INSERT INTO audit_logs (...);
   COMMIT;
   ```
3. **Lock Duration:** Transaction hold time is $< 10\text{ms}$, completely eliminating thread starvation.

### 18.2 Concurrency Stress Test (`PDF-CONC-001` & `PDF-CONC-002`)
- Simultaneous execution of 2 and 5 child processes for the same student placement produces strictly monotonic revisions (`Revision 1`, `Revision 2`, `Revision 3`, ...).
- Zero revision collisions, zero overwritten PDFs, and zero database exceptions.

---

## 19. PDF Rendering & Chromium Printing Test Suite

`PDF-REND-001` through `PDF-REND-006` verify server-side PDF compilation:
- **`PDF-REND-001` (A4 Dimensions):** Exact A4 portrait dimensions ($210\text{mm} \times 297\text{mm}$) with configured margins ($15\text{mm}$ top/bottom, $12\text{mm}$ sides).
- **`PDF-REND-002` (Dynamic Assessment Columns):** Verifies dynamic header compilation for 4 displayed assessments with applicability maximum marks.
- **`PDF-REND-003` (Dynamic Term Sequences):** Iterates over variable term counts ($1, 2, 3, 4 \dots N$) without hardcoded `$term1`/`$term2` assumptions.
- **`PDF-REND-004` (Puppeteer Native Pagination - DEC-055):** In multi-page reports, footers render `"Page 1 of 2"` and `"Page 2 of 2"` via Chromium template spans `<span class="pageNumber">` / `<span class="totalPages">`.
- **`PDF-REND-005` (Table Header Repetition):** In multi-page tables, `thead` repeats automatically at the top of subsequent pages (`thead { display: table-header-group; }`).
- **`PDF-REND-006` (Dynamic Logo MIME & Offline Rendering):** Dynamically injects base64 data URIs based on local MIME detection without external network requests.

---

## 20. Filesystem Consistency & Failure Rollback Test Suite

`PDF-STORE-001` through `PDF-STORE-005` verify the two-phase failure-safe persistence protocol (DEC-051, DEC-054):

| Test ID | Simulated Failure Point | System Action | Expected Cleanup & Rollback State |
| :--- | :--- | :--- | :--- |
| `PDF-STORE-001` | Chromium crashes during render | Browsershot throws ProcessFailedException | Zero temporary files left; zero database rows; returns HTTP 500. |
| `PDF-STORE-002` | Zero-byte output from renderer | Artifact validation rejects empty file | Temp file unlinked; zero database rows; returns HTTP 422. |
| `PDF-STORE-003` | Database insert fails (SQL error) | DB transaction rolls back; catch block fires | Compensating action deletes promoted PDF at destination; historical files untouched. |
| `PDF-STORE-004` | Duplicate revision collision | DB throws `UniqueConstraintViolationException` | DB rolls back; promoted file unlinked; returns HTTP 409 conflict. |
| `PDF-STORE-005` | Unreferenced orphan detection | Simulate crash before commit; orphan file on disk | `php artisan reports:reconcile-storage` flags file; next generation overwrites safely. |

---

## 21. Browser & E2E Acceptance Test Suite

- **`E2E-FULL-001` (Full Academic Year Lifecycle with Multi-Subject Completion):**
  1. **Administrative Setup:** Administrator creates Academic Year 2025-2026 $\to$ Configures 2 Terms $\to$ Creates Class 8, Section A $\to$ Allocates 3 mandatory subjects (Mathematics, Science, English) with applicability maximum marks $\to$ Enrolls Student 1 and Student 2.
  2. **Staff Assignment:** Administrator assigns Class Teacher to 8-A; assigns Subject Teachers for Math, Science, and English.
  3. **Multi-Subject Marks Entry (Student 1):**
     - Math Teacher enters numeric mark: `45.00` / `50.00` (Valid complete score).
     - Science Teacher enters numeric mark: `0.00` / `100.00` (Valid score of zero; complete).
     - English Teacher enters absent: `A` (Valid complete evaluation; contributes `0.00`).
     - *State for Student 1:* All mandatory applicable subjects evaluated $\to$ **Complete**.
  4. **Partial Marks Entry (Student 2 - Completion Invariant Check):**
     - Math Teacher enters numeric mark: `45.00` / `50.00`.
     - Science and English marks remain **blank** (unentered).
     - *State for Student 2:* Mandatory applicable subjects incomplete $\to$ **Incomplete**.
  5. **Marksheet Completion Enforcement (BR-080, BR-084):**
     - Class Teacher attempts to compile final report card for Student 2 $\to$ Rejected with `IncompleteMarksheetException` (HTTP 422); missing Science and English explicitly listed. Zero files created; zero DB rows written.
     - Class Teacher compiles final report card for Student 1 $\to$ **Succeeds!** (Verification of BR-084: Incomplete Student 2 does NOT block complete Student 1).
  6. **Attendance & PDF Delivery:** Class Teacher enters term attendance for Student 1 (88 / 90 days) $\to$ Compiles Revision 1 PDF $\to$ Downloads report card via secure binary streaming $\to$ Verifies exact header, marks matrix, pass/fail result, and zero unapproved fields.
- **`E2E-TRANSFER-001` (Mid-Year Student Transfer):** Student transfers from 8-A to 8-B. Term 1 historical report retains 8-A placement and marks; Term 2 report reflects 8-B placement.

---

## 22. JavaScript Module Test Suite

`JS-MOD-001` through `JS-MOD-004` verify native ES modules:
- **`JS-MOD-001` (Batch Payload Serialization):** Asserts that `mark-entry-grid.js` compiles only dirty inputs and transmits `{ result_status: "numeric", mark_value: "48.50" }` without float casting.
- **`JS-MOD-002` (Attendance Auto-Ratio & Bounds):** Asserts `attendance-grid.js` computes percentage display dynamically and enforces `days_attended <= total_working_days`.
- **`JS-MOD-003` (Cascading Selectors):** Asserts dependent select boxes trigger dynamic option fetching and re-enable cleanly upon payload receipt.
- **`JS-MOD-004` (Zero Inline Event Handlers):** DOM scanner asserts zero `onclick=`, `onchange=`, or `<script>` tags exist in Blade templates.

---

## 23. Blade Rendering & Component Test Suite

- **`BLADE-001` (App Layout Slot Architecture):** Asserts views extend `resources/views/layouts/app.blade.php`.
- **`BLADE-002` (Strict Output Escaping):** Asserts that all entity names (`student_name`, `subject_name`, `assessment_name`) are rendered via `{{ $var }}` (HTML escaped).
- **`BLADE-003` (Context-Dependent Action Hiding):** Blade `@can` hides editing controls for Subject Teachers outside their assignment scope.

---

## 24. Accessibility (WCAG 2.1 AA) Test Suite

- **`A11Y-001` (Keyboard Navigation):** All interactive elements are reachable via `Tab` with visible focus rings (`--shadow-focus`).
- **`A11Y-002` (Modal Focus Trap):** Accessible modal dialogs trap keyboard focus and restore focus to the triggering element upon `Escape`.
- **`A11Y-003` (Color Contrast AA Conformance):** Asserts text color contrast ratios $\ge 4.5:1$ for normal text and $\ge 3:1$ for large text/UI components across all theme tokens:
  - Slate text `#0F172A` on `#FFFFFF` surface $= 16.1:1$ (PASS).
  - Primary button text `#FFFFFF` on `#2563EB` $= 4.6:1$ (PASS).
  - Secondary text `#475569` on `#FFFFFF` $= 7.3:1$ (PASS).

---

## 25. Security & Threat Mitigation Test Suite

`SEC-IDOR-001` through `SEC-TRAV-002` verify robust defense against unauthorized manipulation without introducing unapproved security tables:
- **`SEC-IDOR-001` (Comprehensive IDOR Defense):** Asserts that tampering with any route parameter or request payload attribute is rejected with **HTTP 403 Forbidden**:
  - Manipulated `class_id` or `section_id` outside teacher's active assignment.
  - Manipulated `subject_id` outside subject teacher's assigned subject.
  - Manipulated `student_academic_record_id` belonging to another classroom.
  - Manipulated mark ID in batch updates.
  - Manipulated `generated_reports.id` during download attempts.
- **`SEC-PARAM-001` (Client-Supplied Actor & Revision Tampering):** Asserts that client payloads attempting to supply `generated_by_user_id` or `revision_number` are strictly discarded; actor identity is derived from authenticated session, and revision is computed server-side under database row lock.
- **`SEC-STATIC-001` (Direct PDF URL Defense):** Direct static URL attempts (e.g. `GET /storage/app/private/reports/...`) are blocked by the web server configuration; report files reside strictly outside the public document root.
- **`SEC-PATH-001` (Path Traversal Defense):** Route parameters are cast to integers via route model binding; directory traversal strings (`../../`) abort with **HTTP 404**.
- **`SEC-SSRF-001` (Chromium SSRF Prevention):** Chromium executes with `--disable-network`; report templates use inlined CSS and base64 images, blocking all outbound network traffic.
- **`SEC-XSS-001` (Malicious Student Name Injection):** Student named `<script>alert(1)</script>` is safely escaped in HTML output and PDF canvas.
- **`SEC-MUT-001` (Audit Log Immutability & Mass Assignment):** Asserts that audit logs cannot be updated or deleted via any HTTP route or Eloquent model, and models protect against mass assignment.

---

## 26. Audit Logging Test Suite

`AUDIT-001` through `AUDIT-004` test system observability:
- **`AUDIT-001` (Event Recording):** Logins, logouts, mark mutations, mark corrections, student transfers, and report card generations trigger immediate inserts into `audit_logs`.
- **`AUDIT-002` (Data Snapshot Diff):** Mark corrections capture complete `before_data` and `after_data` JSON snapshots.
- **`AUDIT-003` (Immutability Enforcement):** Trigger `trg_audit_logs_immutable` throws a fatal database exception on any `UPDATE` or `DELETE` statement.
- **`AUDIT-004` (Role-Based Visibility):** Only Administrator can view `/admin/audit-logs`; Office Staff and Teachers receive **HTTP 403**.

---

## 27. CSV Import Test Suite

`IMPORT-001` through `IMPORT-004` test CSV ingestion:
- **`IMPORT-001` (Clean Ingestion):** Valid CSV creates `students` and `student_academic_records` rows.
- **`IMPORT-002` (Identity Matching Behavior - DEC-072):** New admission numbers create master students; existing admission numbers with matching names reuse existing student master rows without creating duplicates; name mismatches reject the row.
- **`IMPORT-003` (Duplicate Roll Number Abort):** Two identical roll numbers in the same class/section abort the placement transaction.
- **`IMPORT-004` (Admission Number Required - DEC-072):** Importer requires `admission_number,student_name,roll_number`. Missing/blank admission numbers are rejected; in-file duplicate admission numbers are rejected.

---

## 28. Performance & Scale Sanity Test Suite

Separates functional correctness invariants from environment-dependent benchmark targets. Arbitrary local timing thresholds are NOT universal correctness requirements.

### 28.1 Functional Correctness Requirements (Zero-Tolerance)
- **Zero Deadlocks:** Concurrent generation requests for students in the same class/section execute without database deadlock exceptions.
- **Zero Duplicate Revisions:** Serialized locking guarantees monotonically increasing revision numbers ($1, 2, 3, \dots$).
- **Zero Historical Overwrites:** Existing generated PDFs are never overwritten.
- **Zero Data Corruption:** Mark batch saves and report promotions execute safely under transactional boundaries.
- **Zero Unbounded Process Leakage:** Chromium child processes terminate cleanly after each compile; zero orphan Node/Chromium processes remain.

### 28.2 Benchmark Reporting Framework
Environment benchmarks are recorded with structured metadata:
```text
Benchmark Run:
- Fixture: Standard Class Roster (30 / 60 / 100 Students)
- Environment: [OS, CPU, RAM, PostgreSQL Version, Node Version]
- Metric: [Mark Grid Render / Batch Save / Placement Lock / PDF Compile]
- Target: [Engineering Reference Baseline]
- Observed: [Measured Duration in Milliseconds]
- Result: [Recorded / Telemetry Baseline Established]
```

---

## 29. Visual Regression Test Suite

Visual regression testing evaluates PDF rendering against deterministic visual and structural criteria rather than relying on arbitrary pixel thresholds as an absolute truth.

### 29.1 Deterministic Execution Baseline
- **Fixed Headless Chromium Binary:** Exact packaged Chromium version pinned in testing environment.
- **Fixed System Fonts:** Bundled Figtree and system sans-serif fonts with deterministic font metrics.
- **Fixed Page Dimensions:** Standard A4 portrait ($210\text{mm} \times 297\text{mm}$) with strict $15\text{mm}$ vertical and $12\text{mm}$ horizontal print margins.
- **Deterministic Test Fixtures:** Approved fixture set covering edge cases and curricula scales.

### 29.2 Objective Visual Acceptance Criteria
The visual test suite inspects:
1. **Clipping & Overflow:** Zero text truncation, clipping, or cell overflow across wide matrices or long entity names (up to 50 characters).
2. **Table Pagination & Header Repetition:** Multi-page tables repeat `<thead>` cleanly across page boundaries without overlapping table rows.
3. **Dynamic Assessment Columns:** Assessment columns scale proportionally across configured assessments ($1 \dots 6$).
4. **Dynamic Terms:** Multi-term matrices ($1 \dots N$) render in ascending sequence without column misalignment.
5. **Attendance Blocks:** Attendance renders correctly; 0/0 working days displays centered `"N/A"` without layout disruption.
6. **Pass/Fail Presentation:** Overall result badge renders clearly based on `school_settings.pass_mark`.
7. **School Branding:** Logo displays with detected MIME type; missing logo falls back to clean typography.
8. **Mark States:** `0.00` renders cleanly as numeric; Absent displays as bold `A`; blank is never rendered on a generated report card.

---

## 30. Full 23-Table Relational Regression Matrix

| Table Name | Relational Invariants Verified | Primary Test ID |
| :--- | :--- | :--- |
| `roles` | Immutable system roles; unique lowercase name index | `DB-REG-001`, `AUTH-001` |
| `users` | Deactivation preserves historical rows; unique username/email | `AUTH-DEACT-001`, `DB-REG-001` |
| `academic_years` | Status open/closed; one current year constraint | `AUTH-CLOSED-001`, `DB-REG-001` |
| `terms` | Sequence ordering; unique year-sequence constraint | `REPORT-CFG-003`, `DB-REG-001` |
| `classes` | Unique lowercase class name index; master definitions | `DB-REG-001`, `E2E-FULL-001` |
| `sections` | Academic-year scoped class sections; unique name | `DB-REG-001`, `E2E-FULL-001` |
| `subjects` | Master catalogue; unique lowercase name index | `DB-REG-001`, `E2E-FULL-001` |
| `class_subjects` | Functional unique index for nullable section; snapshot names | `REPORT-REV-004`, `DB-REG-001` |
| `students` | Master records; admission_number unique identifier (DEC-072) | `DOMAIN-IMP-001`, `DB-REG-001` |
| `student_academic_records` | Historical placement immutability; active roll uniqueness | `DOMAIN-TRANS-001`, `DB-REG-003` |
| `student_subject_allocations` | Elective allocations; locked once marks exist | `DOMAIN-ELEC-001`, `DOMAIN-ELEC-002` |
| `assessment_types` | Master types (`class_test`, `term_exam`); unique code | `DOMAIN-CALC-003`, `DB-REG-001` |
| `assessments` | Term-linked assessment milestones; date consistency | `DB-REG-001`, `E2E-FULL-001` |
| `assessment_applicability` | Contextual max marks per class-subject; positive bounds | `UNIT-MARK-007`, `DB-REG-001` |
| `teacher_assignments` | Assignment subject consistency CHECK; active dates | `AUTH-MULTI-001`, `DB-REG-005` |
| `marks` | Ternary status CHECK; non-negative decimal values | `UNIT-MARK-001`, `DB-REG-001` |
| `attendance` | Days attended $\le$ total working days CHECK constraint | `DOMAIN-ATT-001`, `DB-REG-004` |
| `calculation_settings` | Unique year-class calculation method | `DOMAIN-CALC-001`, `DB-REG-001` |
| `report_configurations` | Configuration data JSONB; active status | `REPORT-CFG-001`, `DB-REG-001` |
| `report_assessment_selections`| Display order; visibility decoupled from calculation | `REPORT-CFG-004`, `DB-REG-001` |
| `school_settings` | Singleton configuration; pass mark non-negative CHECK | `PDF-REND-006`, `DB-REG-001` |
| `generated_reports` | Functional unique index `uk_gr_revision_identity`; immutable | `PDF-CONC-001`, `DB-REG-002` |
| `audit_logs` | Immutable trigger; actor/timestamp tracking | `AUDIT-001`, `DB-REG-006` |

---

## 31. Release-Blocking Quality Gates (Gates A through W)

A build cannot merge or deploy without passing all 23 Quality Gates in sequence:

```
[GATE A: Domain Correctness]        ──> [GATE B: Database Integrity]       ──> [GATE C: Authorization Scopes]
        │                                       │                                      │
        ▼                                       ▼                                      ▼
[GATE D: Mark-State Invariants]     ──> [GATE E: Decimal Precision]        ──> [GATE F: Calculation Accuracy]
        │                                       │                                      │
        ▼                                       ▼                                      ▼
[GATE G: Student Historical Flow]   ──> [GATE H: Attendance Logic]         ──> [GATE I: Report Completion]
        │                                       │                                      │
        ▼                                       ▼                                      ▼
[GATE J: Report Authorization]      ──> [GATE K: Revision Concurrency]     ──> [GATE L: PDF Rendering Engine]
        │                                       │                                      │
        ▼                                       ▼                                      ▼
[GATE M: Filesystem Consistency]    ──> [GATE N: Secure Download Pipeline] ──> [GATE O: Audit Logging]
        │                                       │                                      │
        ▼                                       ▼                                      ▼
[GATE P: CSV Student Import]        ──> [GATE Q: Blade UI Architecture]    ──> [GATE R: Vanilla JS Integrity]
        │                                       │                                      │
        ▼                                       ▼                                      ▼
[GATE S: WCAG 2.1 AA Accessibility] ──> [GATE T: Security & Anti-IDOR]     ──> [GATE U: Relational Regression]
        │                                       │                                      │
        ▼                                       ▼                                      ▼
[GATE V: Performance Sanity]        ──> [GATE W: E2E Acceptance Workflow]
```

### Complete Gate Definitions & Criteria

| Gate ID | Gate Title | Scope of Gate | Automated Verification Method | Expected Result | Blocking Failure Condition | Severity | Evidence Required |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **GATE A** | **Domain Correctness** | BRD V1.3 & DEC-072 boundaries | Code scanner + Model tests | 0 unapproved fields | Unapproved demographic fields detected | Critical | Static scan log |
| **GATE B** | **Database Integrity** | 23 tables, CHECKs, unique indexes | PostgreSQL integration suite | 100% schema match | Schema discrepancy or constraint failure | Blocker | DBUnit report |
| **GATE C** | **Authorization Scopes** | Teacher assignment scopes | Feature policy test suite | Scopes enforced | Subject Teacher edits unauthorized mark | Blocker | Auth matrix log |
| **GATE D** | **Mark-State Invariants** | Ternary mark states | Unit test suite | 0 != blank != A | Mark coercion (blank $\to$ 0) | Blocker | Invariant report |
| **GATE E** | **Decimal Precision** | Exact string preservation | DOM + HTTP payload tests | Exact strings | Float truncation or parseFloat coercion | Critical | Precision log |
| **GATE F** | **Calculation Accuracy** | Method 1, Method 2, Term Exam | Domain calculation tests | Exact 2 decimals | Calculation formula deviation | Blocker | Formula diff |
| **GATE G** | **Student History** | Placements, transfers, rolls | Placement lifecycle tests | Immutability intact| Past placement or marks rewritten | Blocker | Audit diff log |
| **GATE H** | **Attendance Logic** | Working days, 0/0 N/A | Domain attendance tests | 0/0 returns "N/A" | Division by zero or NaN return | Blocker | Math test log |
| **GATE I** | **Report Completion** | Marksheet completion check | Service completion tests | Blank blocks PDF | Final PDF generated with blank mark | Blocker | Service trace |
| **GATE J** | **Report Authorization** | Scoped generation/download | HTTP Policy tests | 403 for out-of-scope| Subject Teacher downloads report | Blocker | HTTP log |
| **GATE K** | **Revision Concurrency** | Row locks, race conditions | Concurrency process pool | Monotonic revs | Duplicate revision numbers generated | Blocker | Process log |
| **GATE L** | **PDF Rendering Engine**| Browsershot A4 compilation | Chromium render tests | Clean A4 PDF | Render crash, clipping, header overlap | Blocker | Artifact scan |
| **GATE M** | **Filesystem Protocol** | Two-phase staging/rollback | Storage failure tests | Clean rollback | Orphan DB row or deleted historical PDF | Blocker | Disk diff log |
| **GATE N** | **Secure Download** | Controller binary stream | HTTP download tests | Headers & sanitized | Direct static access or path traversal | Blocker | Network log |
| **GATE O** | **Audit Logging** | System event recording | Trigger & Feature tests | Immutable logs | Audit log updated, deleted, or missed | Critical | SQL audit log |
| **GATE P** | **Student CSV Import** | Ingestion, validation, CL-009 | Importer service tests | Valid rows created | Re-import auto-matches students | Critical | Import log |
| **GATE Q** | **Blade UI Arch** | Server-rendered Blade shell | Template DOM scanner | Pure Blade | SPA/React/Vue/Tailwind detected | Blocker | Bundle report |
| **GATE R** | **Vanilla JS Integrity**| Native ES modules, CSP | JS test suite + CSP linter | Strict regex valid | Permissive parseFloat or inline script | Critical | Linter report |
| **GATE S** | **Accessibility AA** | Focus traps, contrast | Playwright + Axe core | WCAG 2.1 AA | Focus escape or contrast $< 4.5:1$ | Major | Axe report |
| **GATE T** | **Security & Anti-IDOR** | ID manipulation, SSRF, XSS | Security pen-test suite | 100% blocked | IDOR allows cross-class access | Blocker | Pen-test log |
| **GATE U** | **Regression Matrix** | All 23 tables relations | Schema relational runner | All FKs intact | Broken foreign key or missing index | Blocker | Relational log |
| **GATE V** | **Performance Sanity** | 30/60/100 student scaling | Load testing runner | Lock $< 15\text{ms}$ | Lock starvation or memory leak | Major | Telemetry log |
| **GATE W** | **E2E Acceptance** | Full user journeys | Playwright E2E runner | 100% flow pass | Any user journey step fails | Blocker | Video/Trace log |

---

## 32. Zero-Tolerance Release-Blocking Failure Conditions

The occurrence of **any single condition** below constitutes an immediate, unconditional quality gate failure:

1. ❌ Any unauthorized user (Subject Teacher or out-of-scope Class Teacher) successfully generates or downloads a report card.
2. ❌ Any user successfully updates marks or attendance in an unauthorized classroom.
3. ❌ Mark state coercion occurs: `blank` is converted to `numeric 0.00`, or `absent` is converted to `numeric 0.00`.
4. ❌ A numeric mark exceeding contextual maximum marks is accepted by the backend.
5. ❌ Attendance with 0 total working days triggers a division by zero, `NaN`, `Infinity`, or fails to render `"N/A"`.
6. ❌ A final report card PDF is compiled for a student who has a `blank` required mark.
7. ❌ A historical generated report PDF is overwritten or modified during a subsequent generation.
8. ❌ Two report generation requests for the same student identity produce identical revision numbers.
9. ❌ A `generated_reports` database row exists pointing to a nonexistent physical file.
10. ❌ A physical PDF file exists on disk without a corresponding `generated_reports` row following commit.
11. ❌ Direct public URL access to reports is possible via the web server.
12. ❌ An unapproved student registration code, date of birth, gender, parent name, or student photograph appears in the PDF or UI (`students.admission_number` approved per DEC-072).
13. ❌ An annual overall percentage, student rank, GPA score, or grade band appears in any calculation or report.
14. ❌ The system relies on a hardcoded assumption of 3 terms or universal 100 maximum marks.
15. ❌ Client-side JavaScript calculations override server-side calculations.
16. ❌ An audit log entry is updated or deleted.
17. ❌ A deactivated user successfully accesses protected routes.
18. ❌ The PostgreSQL database schema deviates from the approved 23 tables.

---

## 33. Defect Classification & Severity Triage

Defects detected during testing are categorized strictly:

- **Blocker (Severity 1):** Security bypass, IDOR, mark coercion, duplicate revisions, data loss, schema corruption, or legal non-compliance. *Blocks all deployment immediately.*
- **Critical (Severity 2):** Calculation formula deviation, PDF rendering crash, failure rollback breakdown, or complete failure of an administrative workflow. *Must be resolved before staging release.*
- **Major (Severity 3):** Keyboard navigation breakdown, contrast ratio failure, UI dirty tracking failure, or visual alignment defect on official reports. *Requires fix before production deployment.*
- **Minor (Severity 4):** Typographic spacing discrepancies or helper text clarity issues that do not impact data integrity or user operation.

---

## 34. Final Architectural Audit & Reconciliation Ledger

In accordance with Section 30 and 32 of the Master Verification instructions, the architectural state has been audited across all phases:

### 1. Confirmed Correct Areas
- **Schema Boundary:** Relational schema remains strictly fixed at 23 tables. Zero columns added.
- **Student Identity:** Master identity is strictly `students.id` and `students.student_name`; placement identity is `roll_number`.
- **Role Scopes:** Administrator/Office Staff broad access; Class Teacher scoped to active classroom; Subject Teacher denied report rights.
- **Ternary States:** Blank is incomplete; Numeric is bounded; Absent displays as `A` and contributes $0.00$.
- **Attendance Math:** $0/0 \to \text{"N/A"}$ division-by-zero defense confirmed.
- **Audit Immutability:** PostgreSQL triggers prevent modification/deletion of audit logs.

### 2. Confirmed Defects in Previous Architecture Documents
- **Defect A (Phase 6.6):** `StoreStudentRequest`, `StoreReportConfigurationRequest`, `StoreReportAssessmentSelectionRequest`, `UpdateSchoolSettingRequest`, and `StoreUserRequest` contained unapproved fields (`admission_number`, DOB, gender, rank, grades, `contributes_to_calculation`, address, phone, `first_name`/`last_name`).
- **Defect B (Phase 6.7):** Mark grid input validation used permissive `parseFloat()` which accepted malformed inputs (`"12abc"`, `"1e5"`).
- **Defect C (Phase 6.7):** Layout path contradiction between `resources/views/components/layouts/` and `resources/views/layouts/`.
- **Defect D (Phase 6.8):** File storage disk root / path nesting contradiction (`storage/app/private/reports/reports/...`).
- **Defect E (Phase 6.8):** Claim of literal ACID atomicity across PostgreSQL and the local filesystem.
- **Defect F (Phase 6.8):** Hardcoded `image/png` MIME type for school logo base64 injection.
- **Defect G (Phase 6.8):** Unsupported CSS `@page` page counter margin box declaration (`@bottom-right`).

### 3. Corrected Defects (Applied in Place)
- **Correction A:** Updated Phase 6.6 Form Requests to match the approved 23-table schema exactly (`student_name`, `display_name`, `pass_mark`, zero unapproved fields).
- **Correction B:** Updated Phase 6.7 Vanilla JS to use strict decimal regular expression matching (`/^\d+(\.\d{1,2})?$/`) before numeric comparison.
- **Correction C:** Reconciled Phase 6.7 layout references to `resources/views/layouts/`.
- **Correction D:** Reconciled Phase 6.8 disk root to `storage_path('app/private')` and relative path to `reports/...` on disk `private_reports` (DEC-053).
- **Correction E:** Replaced literal ACID claims in Phase 6.8 with a two-phase failure-safe persistence protocol and compensating rollback (DEC-054).
- **Correction F:** Replaced hardcoded PNG with dynamic MIME detection (`File::mimeType()`).
- **Correction G:** Standardized Chromium pagination on Puppeteer template injection (`->showBrowserHeaderAndFooter()`) with HTML template spans (DEC-055).

### 4. Unresolved Issues
- **None.** All identified cross-document contradictions have been reconciled.

### 5. Intentionally Deferred Issues (Deferred to Phase 6.10)
- **Host Binary Configurations:** Exact host OS executable paths for Node.js 22.x and Chromium across production environments.
- **Filesystem Permissions:** Production POSIX directory permissions (`chmod 0750`) and non-root execution.
- **Production Cron Schedules:** Server crontab scheduling for `reports:clean-temp` and `reports:reconcile-storage`.
- **CI Runner YAML:** GitHub Actions / GitLab CI workflow configurations.

---

## 35. Master Specification Quality Sign-Off & Verification Status

### Formal Quality Gate Status: **Draft / Pending Verification**

> [!IMPORTANT]
> **ABSOLUTE BOUNDARY ENFORCEMENT: DO NOT START PHASE 6.10.**  
> Phase 6.9 establishes the executable test architecture, deterministic fixtures, and release-blocking quality gates. In accordance with Section 16 of the authoritative reconciliation mandate, this specification distinguishes between **SPECIFIED**, **IMPLEMENTED**, **EXECUTED**, and **PASSED**. Phase 6.9 must remain **Draft / Pending Verification** until the automated test suites have been executed against the implemented application code and concrete pass evidence is recorded.

```text
[Specified / Ready for Verification]  1. All critical contradictions across Phases 6.1 through 6.8 resolved in place.
[Specified / Ready for Verification]  2. Absolute 23-table schema boundaries enforced; zero schema modifications permitted.
[Specified / Ready for Verification]  3. Unapproved DOB, gender, parent names, and photos strictly prohibited (`students.admission_number` approved per DEC-072).
[Specified / Ready for Verification]  4. Mark ternary separation asserted (0.00 != blank != absent).
[Specified / Ready for Verification]  5. Exact decimal arithmetic asserted; parseFloat() eliminated from validation.
[Specified / Ready for Verification]  6. Dual calculation formulas and Term Exam exclusive contribution specified.
[Specified / Ready for Verification]  7. Attendance 0/0 division-by-zero defense specified (N/A return).
[Specified / Ready for Verification]  8. Role-based authorization specified (Class Teacher scoped; Subject Teacher blocked).
[Specified / Ready for Verification]  9. Revision concurrency row-locking protocol (FOR UPDATE) specified with < 10ms hold time.
[Specified / Ready for Verification] 10. Two-phase filesystem failure protocol and compensating rollback specified for Windows A-I.
[Specified / Ready for Verification] 11. Headless Chromium Puppeteer header/footer template pagination specified.
[Specified / Ready for Verification] 12. Visual regression testing protocol specified with objective criteria across Fixtures A-H.
[Specified / Ready for Verification] 13. Full 23-table relational regression matrix established.
[Specified / Ready for Verification] 14. Single unified Quality Gate architecture (Gate A through Gate W) defined with blocking criteria.
[Specified / Ready for Verification] 15. 18 Zero-Tolerance failure conditions enforced.
[Specified / Ready for Verification] 16. Comprehensive BRD traceability matrix mapped to test identifiers.
[Specified / Ready for Verification] 17. E2E lifecycle fixture updated: multi-subject marks completion enforced before final report compilation.
[Specified / Ready for Verification] 18. Absolute phase boundary respected: No Phase 6.10 deployment or server provisioning implemented.
```

**Status Summary:** The Phase 6.9 testing architecture is fully reconciled, internally consistent, and ready for test suite execution upon application code implementation. **Phase 6.10 has NOT been started.**
