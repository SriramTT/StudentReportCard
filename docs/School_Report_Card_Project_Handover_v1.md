# School Examination Marks and Report Card Management System
## Project Handover Document — v1

**Purpose:** Complete context handover for continuing this project in another ChatGPT conversation.

**Project root:** `D:\Projects\School report card`

**Current date/context:** September 2026

---

# 1. PROJECT IDENTITY

The project is an **online web application** for a school to manage:

- academic years
- dynamic terms
- classes and sections
- students and historical academic placements
- main/elective subject allocation
- teacher assignments and authorization scopes
- assessments/examinations
- student marks
- attendance
- term calculations
- configurable report cards
- PDF generation and historical revisions
- school settings
- audit logging

The application is for **one school only** in the current version.

This is a school administrative/academic management system, not a desktop-only application.

---

# 2. DEVELOPMENT APPROACH ALREADY COMPLETED

The project has been deliberately developed in phases.

## Phase 1 — Requirements & BRD Finalization
Completed.

The original requirements document was revised into:

`School_Examination_Marksheet_Requirements_v1_3.docx`

The BRD V1.3 is the authoritative business-requirements document.

Do NOT create another BRD version unless a genuine new requirement is explicitly introduced.

## Phase 2 — Business Rules
Completed.

The important business rules were resolved through a large question-and-answer pass.

## Phase 3 — System Roles & Permission Matrix
Completed conceptually.

A critical authorization decision was finalized:

> One teacher account can have multiple assignments/scopes.

Therefore authorization cannot assume one teacher = one assignment.

## Phase 4 — Database Design
Completed and implemented as a 23-table MySQL design.

The approved ERD and table relationship summary are stored in:

`DB design\mermaid-diagram.png`

`DB design\DB-table-definitons.txt`

The current SQL implementation is under:

`database\schema.sql`

The database implementation has subsequently been reviewed and a final correction/validation pass was requested.

---

# 3. AUTHORITATIVE PROJECT FILES

The project currently contains approximately:

```text
School report card/
├── BRD/
│   └── School_Examination_Marksheet_Requirements_v1_3.docx
│
├── database/
│   ├── schema.sql
│   ├── validate_schema.sql
│   ├── run_all.sql
│   └── validation_results.txt
│
└── DB design/
    ├── DB-table-definitons.txt
    └── mermaid-diagram.png
```

When continuing the project, inspect these files before making changes.

Source-of-truth priority:

1. BRD V1.3
2. finalized business rules from this handover
3. approved DB relationship summary
4. approved ERD
5. current SQL implementation

The SQL implementation must not silently redefine business requirements.

---

# 4. TECHNOLOGY DIRECTION

The intended technology stack is:

- Online web application
- Node.js
- Express.js
- MySQL 8.x
- HTML/CSS/JavaScript
- EJS was the preferred frontend/server-rendered view approach

The user already knows:

- JavaScript
- Node.js
- Express.js
- Electron.js

Electron is NOT required for the core product because the product is explicitly an online web application.

Do not turn this into an Electron desktop application unless a new requirement explicitly calls for it.

ORM choice should be treated as an implementation decision only if it has not yet been explicitly finalized. It must not alter the approved relational model.

---

# 5. CORE USERS / ROLES

There are four system roles:

1. Administrator
2. Office Staff
3. Subject Teacher
4. Class Teacher

## Administrator

Full system access.

Administrator can manage users and all major configuration/data.

Administrator can edit marks.

Administrator can view audit logs.

Administrator can perform administrative corrections even after an academic year is closed.

## Office Staff

Broad administrative/data-entry access defined by the BRD.

Office Staff can:

- manage student data
- create/configure assessments
- create/configure terms
- edit marks
- enter attendance
- generate/download reports
- make administrative corrections
- participate in academic-year configuration/corrections

Office Staff cannot manage users if user management is restricted to Administrator by the finalized permission model.

## Subject Teacher

Responsible for mark entry within the teacher's authorized subject assignment.

Can:

- view relevant students/marks
- enter marks
- edit marks

Only within authorized assignment scope.

## Class Teacher

Has access to all subjects for the assigned class/section.

A Class Teacher assignment automatically grants access to every applicable subject for that class/section.

No separate subject assignment is required.

Class Teacher can:

- view marks across all subjects in assigned class/section
- enter marks that were never entered by Subject Teacher
- correct marks already entered by Subject Teacher
- enter/edit attendance for assigned class/section

---

# 6. MULTI-SCOPE TEACHER AUTHORIZATION

This is a critical architectural requirement.

A teacher account can have multiple teacher assignment rows.

Example:

```text
Teacher A
├── 8A → Mathematics → Subject Teacher
├── 8B → Science → Subject Teacher
└── 9A → Class Teacher → all subjects
```

Authorization must evaluate the user's complete set of active assignments.

Do NOT implement:

```text
one teacher = one class
```

Do NOT implement:

```text
one teacher = one subject
```

A Class Teacher assignment is a broad scope:

```text
class + section + all applicable subjects
```

A Subject Teacher assignment is:

```text
class + section + specific subject
```

Teacher deactivation immediately removes access, but historical data created by that teacher remains.

If a teacher is removed from an assignment, their historical marks remain unchanged.

---

# 7. MARK OWNERSHIP / CONFLICT RULES

Subject Teacher enters and edits marks.

Class Teacher can also enter/edit all marks for their class.

Administrator and Office Staff can edit marks broadly.

There is intentionally **no permanent locking of marks** for authorized users.

If Subject Teacher and Class Teacher both modify a mark:

> The latest authorized edit becomes the current value.

The change must be logged.

The reverse situation follows the same rule:

> Latest authorized edit wins.

Historical audit information must remain available.

---

# 8. MARK SEMANTICS — CRITICAL

There are exactly three result states:

### Blank

Means no result has been entered.

- incomplete
- NOT zero
- must not contribute as zero simply because it is blank
- a blank is not a completed result

### Numeric

Means the student was assessed and received a numeric mark.

Examples:

- `0`
- `17.5`
- `25`

Numeric marks may contain decimals.

`0` is a valid completed result.

### A / Absent

The teacher enters `A`.

The report displays:

`A`

For calculations:

`A = 0`

`A` counts as a completed/valid result.

Therefore:

```text
Blank → incomplete
A     → complete
0     → complete
25    → complete
```

No additional result states are currently required.

Do not introduce:

- EX
- Exempted
- WH
- Withheld
- NA
- Not Assessed

---

# 9. MARK VALIDATION

Numeric marks:

- may be decimal
- cannot be negative
- cannot exceed the applicable maximum marks

Example:

```text
17.5 / 20 → valid
21 / 20   → invalid
```

The current database can enforce non-negative numeric marks, but because maximum marks are stored in `assessment_applicability`, the upper-bound comparison may need application/service-layer validation.

This must be explicitly enforced in the backend.

---

# 10. ASSESSMENT MODEL

Assessment Type and Assessment are separate entities.

Example:

```text
Assessment Type:
Unit Test

Assessments:
Unit Test 1
Unit Test 2
Unit Test 3
```

Other examples:

```text
Assessment Type: Class Test
Assessment: Class Test 1
Assessment: Class Test 2
```

Assessment names are flexible.

Assessments are created/configured by:

- Administrator
- Office Staff

---

# 11. DYNAMIC TERMS

Terms are dynamic.

Administrator and Office Staff create/configure terms.

There is no fixed number of terms.

Examples:

- Term 1
- Term 2
- Term 3
- Semester 1
- Semester 2
- Pre-Final

Terms use ascending sequence order.

Terms are not manually reordered as a free-form operation; their configured sequence determines ascending order.

Do NOT hard-code:

```text
Term 1
Term 2
Term 3
```

as the only possible structure.

---

# 12. ASSESSMENT APPLICABILITY

An assessment may apply only to selected classes/subjects.

Example:

Unit Test 1 is conducted only for:

- Class 8A
- Class 8B

It should automatically appear only for those applicable classes.

Maximum marks can differ:

- between assessments
- between subjects within the same assessment

Example:

```text
Unit Test 1
Mathematics → 20
English     → 25
Science     → 30
```

Therefore `maximum_marks` belongs at the assessment-applicability level.

---

# 13. TERM REPORT CALCULATION — VERY IMPORTANT

There is a deliberate distinction between:

1. assessments displayed on the report
2. assessments contributing to the term percentage

The term report can display ALL configured/applicable assessments selected for display.

Example:

```text
Class Test 1
Class Test 2
Monthly Test 1
Monthly Test 2
Unit Test 1
Unit Test 2
Unit Test 3
Term Exam
```

However:

> ONLY the Term Exam contributes to the term percentage under the finalized current business rule.

This is a critical decision.

Do NOT interpret:

"all assessments are displayed"

as:

"all assessments are included in the term calculation."

---

# 14. CALCULATION METHODS

The school can choose one of two calculation methods per class:

## Method 1 — Equal average of assessment percentages

For each included assessment:

```text
obtained / maximum × 100
```

Then average those percentages.

## Method 2 — Combined marks

```text
sum(obtained marks) / sum(maximum marks) × 100
```

Calculation method is stored per:

```text
academic year + class
```

Administrator/Office Staff select it.

If the method changes after marks already exist:

> existing term results immediately recalculate.

Percentages use two decimal places:

```text
75.00%
```

Current finalized rule still says only Term Exam contributes to the term percentage.

---

# 15. MARKSHEET COMPLETION

A student's marksheet is complete only when:

> Every displayed/applicable assessment for every applicable subject has a valid result.

Every displayed assessment must have a result.

A valid result is:

- numeric
- A

Blank is incomplete.

Therefore:

```text
numeric → complete
A       → complete
blank   → incomplete
```

A student with missing required results should not receive a completed PDF under the finalized rule.

---

# 16. ATTENDANCE

Attendance is entered per student per term.

One attendance record:

```text
student academic placement + term
```

Class Teacher enters attendance.

Office Staff also has access.

Fields:

- days attended
- total working days

Validation:

```text
days_attended <= total_working_days
```

Attendance percentage:

```text
days_attended / total_working_days × 100
```

Use two decimal places.

If:

```text
total_working_days = 0
```

do not divide by zero.

Application display should be:

```text
N/A
```

---

# 17. STUDENT TRANSFERS / HISTORICAL PLACEMENT

Student academic placement is historical.

Example:

```text
John Kumar
2026/27
8A
Roll 15
```

moves to:

```text
8B
Roll 22
```

The system must:

- preserve the 8A placement
- create a new 8B placement from the effective date
- keep marks associated with the original academic placement

Example:

```text
Unit Test 1 = 45 under 8A
Unit Test 2 = 40 under 8B
```

Historical records must remain intact.

For a Term 2 report, the student is considered according to the applicable/current placement while historical marks remain attached to their original placement.

---

# 18. ROLL NUMBERS

Roll number is unique within:

```text
academic year + class + section
```

Example:

```text
8A / Roll 15
```

can later become:

```text
8B / Roll 22
```

Historical records retain the old roll number.

Future reports use the new roll number.

---

# 19. STUDENT WITHDRAWAL / TRANSFER-OUT

Internal transfer and leaving the school are separate concepts.

Student academic status distinguishes:

- active
- internal transfer
- withdrawn
- transferred out

A student leaving school:

- disappears from future mark-entry lists after the effective exit date
- remains available in historical records/reports

---

# 20. ELECTIVE SUBJECTS

Subjects can be:

- main
- elective

Class subjects define what a class/section offers.

Student subject allocations define which subjects actually apply to a particular student.

Example:

Class 10 offers:

```text
Computer Science
Economics
Physical Education
```

A particular student may have:

```text
Computer Science
```

Only Computer Science should appear on that student's report.

An elective can be changed during the academic year only before marks have been entered.

Once marks exist for that elective:

> the elective subject cannot be changed.

---

# 21. SUBJECT NAME SNAPSHOT — CRITICAL HISTORICAL RULE

`class_subjects.subject_name_snapshot` is required.

It captures the subject name at the academic configuration point.

Example:

2026/27:

```text
Mathematics
```

2027/28:

```text
Advanced Mathematics
```

Historical 2026/27 reports must continue showing:

```text
Mathematics
```

not:

```text
Advanced Mathematics
```

Do not remove `subject_name_snapshot`.

Do not make historical report generation depend only on the current `subjects.name`.

---

# 22. ACADEMIC YEAR CLOSURE

Academic year lifecycle:

```text
OPEN → CLOSED
```

When closed:

- Teachers become read-only
- Administrator can still correct marks
- Office Staff can still correct marks

Reopening is primarily for configuration.

If a closed year is reopened:

- this does NOT restore normal teacher mark-editing rights
- marks should not become editable by teachers merely because the year was reopened
- configuration can be changed according to the finalized rules

Do not reinterpret reopening as "everything becomes editable again."

---

# 23. NEW ACADEMIC YEAR

A new academic year should be able to copy forward:

- school configuration
- classes
- subjects
- teacher-related configuration

Then the new year's configuration can be modified.

Historical data from previous years must remain unchanged.

Example:

If Mathematics is renamed in 2027/28, old reports remain historically correct.

---

# 24. REPORT CONFIGURATION

Reports are configurable.

Term reports can contain all configured/applicable assessments.

Final reports dynamically adapt to the number of terms.

Example:

```text
Subject | Term 1 | Term 2 | Term 3 | Final Exam
```

If there are 4 terms, the report must be able to show 4 term columns.

Do NOT hard-code exactly 3 terms.

Final reports may optionally include additional assessment information such as:

- Class Tests
- Monthly Tests
- Unit Tests

depending on school configuration.

The report system therefore separates:

- what is available
- what is displayed
- what is calculated

---

# 25. REPORT CARD CONTENT DECISIONS

Confirmed:

- school logo/header
- student information
- applicable subjects
- configured assessment columns
- marks
- percentages where applicable
- attendance
- pass/fail
- dynamically configured term columns
- historical report context

Not required:

- student rank
- overall annual percentage
- letter grades such as A/B/C

Pass rule:

```text
mark/percentage >= school pass mark → PASS
mark/percentage < school pass mark  → FAIL
```

The boundary is inclusive:

```text
mark >= pass mark
```

---

# 26. SCHOOL HEADER / LOGO

The application will have a school settings area.

The school can upload a logo.

The logo is displayed as the report header.

School name/settings are common to reports.

Digital signature configuration for Class Teacher / Headmaster was intentionally left for the later report-configuration phase and should not be invented now.

---

# 27. PDF GENERATION / HISTORICAL REVISIONS

Reports are historical files.

First generation:

```text
Revision 1
```

If a mark changes afterward:

- Revision 1 remains untouched
- the next generated PDF becomes Revision 2

Then:

```text
Revision 3
Revision 4
...
```

Historical PDFs must remain available.

Never overwrite a previous PDF.

Who can download reports:

- Administrator
- Office Staff

The database stores generated-report metadata and revision number.

---

# 28. AUDIT LOGGING

Audit logs are immutable through the application.

Administrator cannot delete audit logs.

At minimum log:

- account changes
- teacher assignments
- student imports/edits
- examination setup
- school/class settings
- calculation changes
- mark changes
- report/PDF generation
- academic year open/close
- student transfers
- login/logout

Mark changes should retain:

- actor
- previous value
- new value
- timestamp
- relevant context

The current JSON `before_data` / `after_data` approach is acceptable.

---

# 29. STUDENT IMPORT RULES

For this version:

> Never automatically match an imported student to an existing student.

If a student named John Kumar already exists and a new import contains John Kumar:

> create a new student record.

Future versions may introduce an admission/student ID.

Partial import is allowed.

Example:

```text
100 rows
95 valid
5 invalid
```

Result:

```text
95 imported
5 rejected/reported
```

The entire import should not fail simply because a few rows are invalid.

---

# 30. DATABASE TECHNOLOGY

Approved database:

**MySQL 8.x**

Database:

```text
school_report_card
```

Storage:

```text
InnoDB
```

Character set:

```text
utf8mb4
```

Collation:

```text
utf8mb4_unicode_ci
```

---

# 31. APPROVED 23-TABLE DATABASE MODEL

The approved database contains exactly these 23 tables:

1. `roles`
2. `users`
3. `academic_years`
4. `terms`
5. `classes`
6. `sections`
7. `subjects`
8. `class_subjects`
9. `students`
10. `student_academic_records`
11. `student_subject_allocations`
12. `assessment_types`
13. `assessments`
14. `assessment_applicability`
15. `teacher_assignments`
16. `marks`
17. `attendance`
18. `calculation_settings`
19. `report_configurations`
20. `report_assessment_selections`
21. `school_settings`
22. `generated_reports`
23. `audit_logs`

Do not casually add or remove tables.

---

# 32. DATABASE TABLE RESPONSIBILITIES

## roles
System roles.

## users
Application accounts, authentication identity, activation status.

## academic_years
Academic year lifecycle and current-year state.

## terms
Dynamic terms within academic years.

## classes
Master class definitions.

## sections
Year-specific class sections.

## subjects
Master subject catalogue.

## class_subjects
Subjects configured for a class/section, including historical `subject_name_snapshot`.

## students
Student master records.

## student_academic_records
Historical academic placement records.

## student_subject_allocations
Student-specific applicable subjects.

## assessment_types
Assessment category/type definitions.

## assessments
Individual assessment instances.

## assessment_applicability
Maps assessments to class subjects and stores per-subject maximum marks.

## teacher_assignments
Teacher authorization scopes, including multiple assignments and Class Teacher all-subject scope.

## marks
Individual student results for assessment applicability.

## attendance
Term-level student attendance.

## calculation_settings
Calculation method per academic year/class.

## report_configurations
Configurable report definitions.

## report_assessment_selections
Controls assessment display selection/order separately from calculation.

## school_settings
Single-school settings, logo, pass mark.

## generated_reports
Historical PDF generation/revision records.

## audit_logs
Immutable activity history.

---

# 33. CURRENT SQL IMPLEMENTATION

Antigravity created:

```text
database/schema.sql
database/validate_schema.sql
database/run_all.sql
database/validation_results.txt
```

The schema currently implements all 23 tables.

It uses:

- BIGINT UNSIGNED auto-increment PKs
- foreign keys
- unique constraints
- indexes
- CHECK constraints
- status fields
- timestamps
- JSON audit before/after data

Foreign keys were intentionally configured with restrictive deletion/update behavior rather than destructive cascades.

---

# 34. IMPORTANT DATABASE INVARIANTS

Some rules can be enforced directly by MySQL.

Examples:

- positive maximum marks
- non-negative marks
- attendance bounds
- unique term sequence within an academic year
- unique roll number within placement scope
- unique student subject allocation
- unique assessment applicability
- teacher assignment type/subject consistency

Other rules are domain/application rules.

Examples:

- Class Teacher has all-subject access
- teacher authorization
- numeric mark <= applicable maximum
- latest authorized mark edit wins
- only Term Exam contributes to term percentage
- one current academic year
- marks cross-reference consistency
- report revision sequencing
- report completeness
- elective change restrictions
- academic-year closure authorization

The next backend implementation must explicitly enforce these application-level rules.

---

# 35. IMPORTANT CURRENT DATABASE REVIEW FINDINGS

The existing SQL implementation was reviewed.

The foundation is good, but a final correction/validation pass was identified.

## A. Marks cross-reference integrity

`marks` contains:

- `student_academic_record_id`
- `student_subject_allocation_id`
- `assessment_applicability_id`

MySQL does not automatically guarantee that all three point to the same student/class/subject context.

The backend must validate:

1. subject allocation belongs to the academic record
2. assessment applicability matches the correct class subject
3. student placement is compatible with assessment applicability
4. allocated subject matches assessment subject
5. mark is entered for the correct student/subject/assessment context

This should be treated as a transactional service-layer invariant.

## B. class_subjects.section_id

The current design permits `section_id` to be NULL for possible class-wide subject configuration.

MySQL UNIQUE constraints treat NULL as distinct, so:

```text
UNIQUE(academic_year_id, class_id, section_id, subject_id)
```

does not prevent duplicate class-wide rows where `section_id IS NULL`.

This needs to remain consistent with the approved DB design.

Do not arbitrarily change it without checking the BRD/ERD/table summary.

## C. Generated report revision identity

The generated-report design must prevent accidental duplicate revision identities.

Because report types use different contextual fields (term vs assessment vs final), do not add an incorrect UNIQUE constraint that fails to work with MySQL NULL semantics.

If necessary, enforce revision identity transactionally in the service layer and document it.

## D. Numeric maximum validation

The DB checks non-negative numeric marks.

The backend must additionally ensure:

```text
numeric mark <= applicable maximum marks
```

## E. Current academic year

At most one academic year should be current.

Application logic is acceptable.

## F. Validation script quality

The existing validation script contains some statements/comments describing expected failures without actually executing all of those invalid operations.

The validation suite should genuinely execute negative tests and distinguish:

- PASS
- EXPECTED FAILURE
- FAIL
- APPLICATION-LEVEL INVARIANT

Do not claim a constraint was tested if the invalid SQL was never executed.

## G. Validation safety

Validation should not accidentally destroy a real database.

Clearly distinguish disposable test databases from production.

---

# 36. LAST TASK GIVEN TO ANTIGRAVITY

A correction/finalization prompt was prepared for Antigravity.

The requested task was:

> FINAL DATABASE ENGINEERING REVIEW AND CORRECTION PASS

It must:

- inspect BRD V1.3
- inspect approved DB design files
- inspect current schema
- inspect current validation script
- preserve the 23-table model
- fix technical database issues
- strengthen validation
- document DB-enforced vs application-enforced rules
- update validation results
- NOT start backend/frontend work

Expected files potentially modified:

```text
database/schema.sql
database/validate_schema.sql
database/run_all.sql
database/validation_results.txt
```

Potential walkthrough:

```text
database/DATABASE_FINALIZATION_WALKTHROUGH.md
```

The BRD must NOT be modified during this database correction pass.

IMPORTANT STATUS:

**Do not assume Antigravity has completed this correction pass unless the current project files are inspected and its final walkthrough/results confirm completion.**

---

# 37. CURRENT PROJECT STATUS

## Completed

- Requirements gathering
- BRD V1.3
- Business rules
- Role/permission model
- Multi-scope teacher authorization concept
- Database entity modeling
- 23-table database design
- ERD
- subject name snapshot decision
- initial MySQL schema implementation
- initial validation suite

## In progress / last known task

Final database correction and validation pass.

## Not yet started

The following should NOT be assumed complete:

- backend architecture
- Express application
- authentication implementation
- authorization middleware
- database connection layer
- ORM integration, if chosen
- EJS UI
- mark-entry screens
- student import UI
- assessment configuration UI
- attendance UI
- report configuration UI
- PDF rendering
- production deployment
- security hardening
- automated application-level tests

---

# 38. WHAT SHOULD HAPPEN NEXT

The immediate next action is NOT to redesign requirements.

First:

1. Inspect the current Antigravity database correction results.
2. Confirm SQL/ERD/BRD consistency.
3. Confirm real validation tests pass.
4. Confirm no database blocker remains.
5. Then begin application/backend architecture.

The next major development stage should be application architecture and implementation, based strictly on the finalized BRD and database.

---

# 39. IMPORTANT DON'Ts FOR THE NEXT CHAT

Do NOT:

- restart requirements gathering
- create BRD V1.4 without a new requirement
- replace MySQL
- redesign the 23-table schema casually
- add multi-school support
- add parent/student accounts
- add mobile apps
- add ranking
- add annual overall percentage
- add letter grades
- add extra mark states
- automatically match imported students
- overwrite historical PDFs
- delete historical marks
- delete audit logs
- make teachers able to edit simply because a closed year is reopened
- make all displayed assessments contribute to term percentage
- hard-code three terms
- hard-code a fixed number of assessment columns
- remove subject snapshots
- treat blank as zero
- treat A as incomplete
- introduce term locking without explicit approval
- invent signature rules before report configuration is discussed

---

# 40. WORKING PRINCIPLE FOR FUTURE DEVELOPMENT

Every implementation decision should trace back through:

```text
BRD V1.3
      ↓
Finalized Business Rules
      ↓
Roles / Permissions
      ↓
Approved DB Design / ERD
      ↓
Database Implementation
      ↓
Backend Domain Rules
      ↓
UI / Reports
```

If a new implementation idea conflicts with an approved business rule, stop and resolve the conflict rather than silently changing the requirement.

If a rule cannot be enforced in MySQL, enforce it in the backend service layer and document it.

Prefer correctness and historical integrity over unnecessary complexity.

---

# 41. SHORT PROJECT SUMMARY

This project is a single-school online school examination and report-card management system.

Teachers operate under assignment-based authorization. A teacher can have multiple scopes. Subject Teachers manage marks for assigned subjects; Class Teachers have all-subject access within assigned class/section. Administrator and Office Staff have broader administrative capabilities.

The system models academic years, dynamic terms, classes, sections, subjects, student placements, student-specific subjects, assessments, assessment applicability, marks, attendance, calculations, configurable reports, PDFs, and audit history.

The most important data principles are:

- historical records are preserved
- blank is different from zero
- A is displayed as A but calculates as zero
- maximum marks can vary by subject
- assessment display is separate from calculation
- only Term Exam currently contributes to term percentage
- terms are dynamic
- report layouts are configurable
- student placements are historical
- subject names require snapshots
- teacher authorization is scope-based and multi-assignment
- generated PDFs are immutable historical revisions
- audit logs are immutable
- MySQL is the approved database

The current database has 23 approved tables and has been implemented in MySQL. A final technical correction/validation pass is the last known database task before moving into application development.

---

# 42. HANDOVER INSTRUCTION TO THE NEXT CHAT

Start the new conversation by providing this handover document and saying:

> "This is the complete handover for my School Examination Marks and Report Card Management System. Read it carefully and treat it as the project context. Do not restart requirements gathering. Before proposing implementation changes, ask me for the current project files if you need to verify the latest Antigravity changes. The BRD V1.3, finalized business rules, approved 23-table database design, and historical-data rules are authoritative."

The next chat should then verify the actual current project state before proceeding.
