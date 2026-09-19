# PHASE 6.2 — LARAVEL APPLICATION ARCHITECTURE
## Domain Model & Eloquent Architecture

**Project:** School Examination Marks and Report Card Management System  
**Document Version:** 1.0  
**Phase:** 6.2 — Domain Model & Eloquent Architecture  
**Status:** Approved & Authoritative Architectural Specification  

---

## 1. Purpose

This document defines the comprehensive domain model and Eloquent ORM architecture for the Laravel application representing the **School Examination Marks and Report Card Management System**.

The primary objective of Phase 6.2 is to bridge the closed, validated 23-table PostgreSQL database schema with Laravel's Object-Relational Mapping (Eloquent) layer without altering the database schema or compromising any finalized business requirements. This blueprint establishes:

- Exact mapping of all 23 PostgreSQL database tables to Eloquent domain models.
- Primary key, timestamp, PostgreSQL data type, and cast definitions.
- Canonical foreign key relationships, cardinalities, inverse mappings, and eager-loading paths.
- Model mutability classifications and historical placement/snapshot preservation rules.
- Architectural boundaries separating persistence (Eloquent), authorization (Policies/Gates), input validation (Form Requests), and complex multi-table domain operations (Application Services).
- Eloquent convention exceptions (including PHP keyword collisions, custom timestamp schemas, and composite unique constraints).
- Strict prohibitions against anti-patterns (such as soft deletes on placements, UI-only authorization, cascading deletes, and pass-through repository abstractions).

---

## 2. Relationship to Phase 6.1

Phase 6.1 established the technology baseline and architectural foundations:
- **Backend Stack:** PHP 8.4+ / Laravel 13, running in server-rendered mode.
- **Database Baseline:** Validated PostgreSQL 18.4 database (`school_report_card`) with 23 tables, 43 foreign keys, 7 CHECK constraints, and functional indexes.
- **Frontend Stack:** Server-rendered Blade with tailored Vanilla CSS and Vanilla JavaScript micro-interactions built via Vite (Node.js 22 LTS as offline build tool only).
- **Core Principles:** Single-school architecture, thin controllers, rich application services, server-side policies, immutable audit logging, and discrete mark states (`blank`, `numeric`, `absent`).

Phase 6.2 translates these principles into concrete Eloquent model specifications. It provides the domain-layer blueprint for Phase 6.3 (Application Services & Business Workflows) and Phase 6.4 (Authentication & Multi-Assignment Authorization).

```
Phase 6.1: Technology Baseline & Principles (Approved)
      ↓
Phase 6.2: Domain Model & Eloquent Architecture (Current Step)
      ↓
Phase 6.3: Service Layer & Business Logic Architecture
      ↓
Phase 6.4: Authentication, Multi-Assignment Authorization & Security Blueprint
      ↓
Phase 6.5: HTTP Layer, Routing, Form Requests & Controller Contracts
      ↓
Phase 6.6: Blade Component & UI Integration Architecture
      ↓
Phase 6.7: PDF Generation & File Storage Blueprint
      ↓
Phase 6.8: Application Testing & Quality Gate Strategy
      ↓
Phase 6.9: Environment Configuration & Deployment Readiness
```

---

## 3. Authoritative Sources

All specifications in this document derive strictly from the project's authoritative source hierarchy:

1. **BRD V1.3** (`BRD/School_Examination_Marksheet_Requirements_v1_3.docx`)
2. **Finalized Business Rules & Project Handover Decisions**
3. **Approved 23-Table Database Relationship Design** (`DB design/DB-table-definitons.txt`)
4. **Approved ERD** (`DB design/mermaid-diagram.png`)
5. **Validated PostgreSQL Implementation & Evidence** (`database/Postgres/schema.sql`, `seed.sql`, `validation/run_all.sql`, `validation_results.txt`)
6. **Technology Baseline Document** (`docs/architecture/PHASE_6_1_TECHNOLOGY_BASELINE.md`)

---

## 4. Application Domain Model Overview

The domain model represents the academic, examination, grading, and reporting lifecycle of a single school institution. The 23 domain entities organize into six logical subdomains:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            1. INSTITUTION & ACCESS                          │
│     [SchoolSetting]  ──  [Role]  ──  [User]  ──  [AuditLog]                 │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
┌──────────────────────────────────────▼──────────────────────────────────────┐
│                            2. ACADEMIC STRUCTURE                            │
│   [AcademicYear] ──┬── [Term]                                               │
│                    ├── [SchoolClass] ──┬── [Section]                        │
│                    │                   └── [CalculationSetting]            │
│                    └── [Subject] ───────── [ClassSubject]                   │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
┌──────────────────────────────────────▼──────────────────────────────────────┐
│                            3. STUDENT & ENROLLMENT                          │
│   [Student] ── [StudentAcademicRecord] ── [StudentSubjectAllocation]        │
│                         │                                                   │
│                         └── [Attendance]                                    │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
┌──────────────────────────────────────▼──────────────────────────────────────┐
│                        4. TEACHER SCOPE & ASSIGNMENT                        │
│                           [TeacherAssignment]                               │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
┌──────────────────────────────────────▼──────────────────────────────────────┐
│                           5. ASSESSMENT & MARKING                           │
│   [AssessmentType] ── [Assessment] ── [AssessmentApplicability] ── [Mark]   │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
┌──────────────────────────────────────▼──────────────────────────────────────┐
│                            6. REPORTING & ARCHIVES                          │
│   [ReportConfiguration] ── [ReportAssessmentSelection] ── [GeneratedReport] │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 5. Complete 23-Model Inventory

Every database table maps to an Eloquent model. The table below provides the authoritative model class inventory.

| # | PostgreSQL Table | Eloquent Model Class | Namespace | Primary Role |
|---|---|---|---|---|
| 1 | `roles` | `Role` | `App\Models` | Access control role catalog (Admin, Office Staff, Teacher) |
| 2 | `users` | `User` | `App\Models` | User accounts, authentication credentials, and active status |
| 3 | `academic_years` | `AcademicYear` | `App\Models` | Academic year lifecycle (`open` / `closed`) |
| 4 | `terms` | `Term` | `App\Models` | Dynamic terms within academic year (sequential numbering) |
| 5 | `classes` | `SchoolClass` | `App\Models` | Master class definitions (Named `SchoolClass` to avoid PHP keyword `class`) |
| 6 | `sections` | `Section` | `App\Models` | Class sections scoped to an academic year |
| 7 | `subjects` | `Subject` | `App\Models` | Master subject catalog (`main` / `elective`) |
| 8 | `class_subjects` | `ClassSubject` | `App\Models` | Academic configuration linking class/section to subject with name snapshot |
| 9 | `students` | `Student` | `App\Models` | Permanent student master entity |
| 10 | `student_academic_records` | `StudentAcademicRecord` | `App\Models` | Historical academic placement (year, class, section, roll number, status) |
| 11 | `student_subject_allocations` | `StudentSubjectAllocation` | `App\Models` | Student-specific subject enrollment (core vs elective) |
| 12 | `assessment_types` | `AssessmentType` | `App\Models` | Master assessment category catalog (Class Test, Term Exam) |
| 13 | `assessments` | `Assessment` | `App\Models` | Named assessment event within an academic year and optional term |
| 14 | `assessment_applicability` | `AssessmentApplicability` | `App\Models` | Contextual assessment applicability & subject-level maximum marks |
| 15 | `teacher_assignments` | `TeacherAssignment` | `App\Models` | Teacher authorization scope (Class Teacher vs Subject Teacher) |
| 16 | `marks` | `Mark` | `App\Models` | Student marks per assessment applicability (`blank`, `numeric`, `absent`) |
| 17 | `attendance` | `Attendance` | `App\Models` | Term-level attendance records per placement |
| 18 | `calculation_settings` | `CalculationSetting` | `App\Models` | Term percentage calculation formula per year and class |
| 19 | `report_configurations` | `ReportConfiguration` | `App\Models` | Report generation display schemas and options |
| 20 | `report_assessment_selections` | `ReportAssessmentSelection` | `App\Models` | Ordered assessment display selection for report cards |
| 21 | `school_settings` | `SchoolSetting` | `App\Models` | Singleton institutional settings (name, logo, passing mark) |
| 22 | `generated_reports` | `GeneratedReport` | `App\Models` | Immutable historical PDF report generation archive |
| 23 | `audit_logs` | `AuditLog` | `App\Models` | Immutable, append-only system activity log |

---

## 6. PostgreSQL-to-Eloquent Mapping

### 6.1 Entity 1: `Role` (`roles`)
- **Table:** `roles`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `name`: string (`VARCHAR(50)`, unique lowercase index `uk_roles_name`)
  - `description`: string/null (`VARCHAR(255)`)
  - `is_active`: boolean (`BOOLEAN`, default `true`)
  - `created_at`: `TIMESTAMPTZ`
  - `updated_at`: `TIMESTAMPTZ`
- **Eloquent Configuration:**
  - `$fillable = ['name', 'description', 'is_active']`
  - `$casts = ['is_active' => 'boolean', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime']`
- **Mutability & Lifecycle:** Low mutability. Core roles (`Administrator`, `Office Staff`, `Teacher`) are seeded. Deleting system roles is prohibited.

### 6.2 Entity 2: `User` (`users`)
- **Table:** `users`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `role_id`: foreign key (`BIGINT` -> `roles.id`, Restrict)
  - `username`: string (`VARCHAR(100)`, unique lowercase index `uk_users_username`)
  - `password_hash`: string (`VARCHAR(255)`)
  - `display_name`: string (`VARCHAR(150)`)
  - `email`: string/null (`VARCHAR(255)`)
  - `is_active`: boolean (`BOOLEAN`, default `true`, indexed)
  - `last_login_at`: `TIMESTAMPTZ` nullable
  - `created_at`: `TIMESTAMPTZ`
  - `updated_at`: `TIMESTAMPTZ`
- **Eloquent Configuration:**
  - Implements `Illuminate\Contracts\Auth\Authenticatable`
  - `$hidden = ['password_hash', 'remember_token']`
  - `$fillable = ['role_id', 'username', 'password_hash', 'display_name', 'email', 'is_active', 'last_login_at']`
  - `$casts = ['is_active' => 'boolean', 'last_login_at' => 'immutable_datetime']`
  - Auth password accessor: `public function getAuthPassword() { return $this->password_hash; }`
- **Mutability & Lifecycle:** User deletion is prohibited. Users are deactivated (`is_active = false`) to preserve historical audit, marks entry, and report generation actor references.

### 6.3 Entity 3: `AcademicYear` (`academic_years`)
- **Table:** `academic_years`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `name`: string (`VARCHAR(20)`, unique lowercase index `uk_academic_years_name`)
  - `start_date`: date/null (`DATE`)
  - `end_date`: date/null (`DATE`)
  - `status`: enum string (`academic_year_status_enum`: `'open'`, `'closed'`)
  - `is_current`: boolean (`BOOLEAN`, default `false`)
  - `created_at`: `TIMESTAMPTZ`
  - `updated_at`: `TIMESTAMPTZ`
- **Eloquent Configuration:**
  - `$fillable = ['name', 'start_date', 'end_date', 'status', 'is_current']`
  - `$casts = ['start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d', 'is_current' => 'boolean']`
- **Lifecycle Rule:** Only one academic year may have `is_current = true` at any time. When closed, teacher mark entry becomes strictly read-only.

### 6.4 Entity 4: `Term` (`terms`)
- **Table:** `terms`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `academic_year_id`: foreign key (`BIGINT` -> `academic_years.id`, Restrict)
  - `name`: string (`VARCHAR(100)`)
  - `sequence_no`: integer (`INTEGER`)
  - `is_active`: boolean (`BOOLEAN`, default `true`)
  - `created_at`: `TIMESTAMPTZ`
  - `updated_at`: `TIMESTAMPTZ`
- **Unique Constraints:**
  - `uk_terms_year_seq`: `(academic_year_id, sequence_no)`
  - `uk_terms_year_name`: `(academic_year_id, name)`
- **Eloquent Configuration:**
  - `$fillable = ['academic_year_id', 'name', 'sequence_no', 'is_active']`
  - `$casts = ['sequence_no' => 'integer', 'is_active' => 'boolean']`
- **Domain Rule:** The system supports dynamic term counts (e.g. 2 terms, 3 terms, 4 quarters). Code must never assume 3 terms.

### 6.5 Entity 5: `SchoolClass` (`classes`)
- **Table:** `classes`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `name`: string (`VARCHAR(50)`, unique lowercase index `uk_classes_name`)
  - `is_active`: boolean (`BOOLEAN`, default `true`)
  - `created_at`: `TIMESTAMPTZ`
  - `updated_at`: `TIMESTAMPTZ`
- **Eloquent Configuration:**
  - `protected $table = 'classes';` (Class named `SchoolClass` to bypass PHP reserved word collision)
  - `$fillable = ['name', 'is_active']`
  - `$casts = ['is_active' => 'boolean']`

### 6.6 Entity 6: `Section` (`sections`)
- **Table:** `sections`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `academic_year_id`: foreign key (`BIGINT` -> `academic_years.id`, Restrict)
  - `class_id`: foreign key (`BIGINT` -> `classes.id`, Restrict)
  - `name`: string (`VARCHAR(50)`)
  - `is_active`: boolean (`BOOLEAN`, default `true`)
  - `created_at`: `TIMESTAMPTZ`
  - `updated_at`: `TIMESTAMPTZ`
- **Unique Constraints:** `uk_sections_year_class_name`: `(academic_year_id, class_id, name)`
- **Eloquent Configuration:**
  - `$fillable = ['academic_year_id', 'class_id', 'name', 'is_active']`
  - `$casts = ['is_active' => 'boolean']`

### 6.7 Entity 7: `Subject` (`subjects`)
- **Table:** `subjects`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `name`: string (`VARCHAR(150)`, unique lowercase index `uk_subjects_name`)
  - `code`: string/null (`VARCHAR(50)`)
  - `category`: enum string (`subject_category_enum`: `'main'`, `'elective'`)
  - `is_active`: boolean (`BOOLEAN`, default `true`)
  - `created_at`: `TIMESTAMPTZ`
  - `updated_at`: `TIMESTAMPTZ`
- **Eloquent Configuration:**
  - `$fillable = ['name', 'code', 'category', 'is_active']`
  - `$casts = ['is_active' => 'boolean']`

### 6.8 Entity 8: `ClassSubject` (`class_subjects`)
- **Table:** `class_subjects`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `academic_year_id`: foreign key (`BIGINT` -> `academic_years.id`, Restrict)
  - `class_id`: foreign key (`BIGINT` -> `classes.id`, Restrict)
  - `section_id`: foreign key/null (`BIGINT` -> `sections.id`, Restrict)
  - `subject_id`: foreign key (`BIGINT` -> `subjects.id`, Restrict)
  - `subject_name_snapshot`: string (`VARCHAR(150)`, historical immutable snapshot)
  - `is_active`: boolean (`BOOLEAN`, default `true`)
  - `created_at`: `TIMESTAMPTZ`
  - `updated_at`: `TIMESTAMPTZ`
- **Unique Constraints:**
  - Functional unique index: `uk_class_subjects_config` on `(academic_year_id, class_id, COALESCE(section_id, 0), subject_id)`
- **Domain Rule:** `subject_name_snapshot` freezes the subject name at assignment time. Reports read this snapshot, never raw `subjects.name`.

### 6.9 Entity 9: `Student` (`students`)
- **Table:** `students`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `student_name`: string (`VARCHAR(200)`, indexed `idx_students_student_name`)
  - `created_at`: `TIMESTAMPTZ`
  - `updated_at`: `TIMESTAMPTZ`
- **Domain Rule:** Student names are non-unique. Student identity across years is tied to `student_id`. Re-imports create new student entities unless explicit manual linkage is specified.

### 6.10 Entity 10: `StudentAcademicRecord` (`student_academic_records`)
- **Table:** `student_academic_records`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `student_id`: foreign key (`BIGINT` -> `students.id`, Restrict)
  - `academic_year_id`: foreign key (`BIGINT` -> `academic_years.id`, Restrict)
  - `class_id`: foreign key (`BIGINT` -> `classes.id`, Restrict)
  - `section_id`: foreign key (`BIGINT` -> `sections.id`, Restrict)
  - `roll_number`: integer (`INTEGER`)
  - `status`: enum string (`student_placement_status_enum`: `'active'`, `'internal_transfer'`, `'withdrawn'`, `'transferred_out'`)
  - `effective_from`: date (`DATE`)
  - `effective_to`: date/null (`DATE`)
  - `created_at`: `TIMESTAMPTZ`
  - `updated_at`: `TIMESTAMPTZ`
- **Unique Constraints:** `uk_sar_year_class_section_roll`: `(academic_year_id, class_id, section_id, roll_number)`
- **Domain Rule:** Represents a historical placement. Internal transfer creates a new row with new section/roll number; the previous placement record is marked `internal_transfer` with `effective_to` populated. Marks stay on the historical placement.

### 6.11 Entity 11: `StudentSubjectAllocation` (`student_subject_allocations`)
- **Table:** `student_subject_allocations`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `student_academic_record_id`: foreign key (`BIGINT` -> `student_academic_records.id`, Restrict)
  - `class_subject_id`: foreign key (`BIGINT` -> `class_subjects.id`, Restrict)
  - `allocation_type`: enum string (`subject_category_enum`: `'main'`, `'elective'`)
  - `effective_from`: date (`DATE`)
  - `effective_to`: date/null (`DATE`)
  - `is_active`: boolean (`BOOLEAN`, default `true`)
  - `created_at`: `TIMESTAMPTZ`
  - `updated_at`: `TIMESTAMPTZ`
- **Unique Constraints:** `uk_ssa_record_subject`: `(student_academic_record_id, class_subject_id)`
- **Domain Rule:** Controls subject applicability for a student. An elective allocation cannot be deactivated/changed once marks have been entered for that subject.

### 6.12 Entity 12: `AssessmentType` (`assessment_types`)
- **Table:** `assessment_types`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `name`: string (`VARCHAR(100)`, unique lowercase index `uk_assessment_types_name`)
  - `is_active`: boolean (`BOOLEAN`, default `true`)
  - `created_at`: `TIMESTAMPTZ`
  - `updated_at`: `TIMESTAMPTZ`
- **Eloquent Configuration:** `$fillable = ['name', 'is_active']`

### 6.13 Entity 13: `Assessment` (`assessments`)
- **Table:** `assessments`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `academic_year_id`: foreign key (`BIGINT` -> `academic_years.id`, Restrict)
  - `term_id`: foreign key/null (`BIGINT` -> `terms.id`, Restrict; null for multi-term or final exams)
  - `assessment_type_id`: foreign key (`BIGINT` -> `assessment_types.id`, Restrict)
  - `name`: string (`VARCHAR(150)`)
  - `assessment_date`: date/null (`DATE`)
  - `status`: enum string (`assessment_status_enum`: `'active'`, `'inactive'`)
  - `created_at`: `TIMESTAMPTZ`
  - `updated_at`: `TIMESTAMPTZ`
- **Domain Rule:** Flexible assessment instances. Does not contain maximum marks directly because maximum marks vary per subject via `assessment_applicability`.

### 6.14 Entity 14: `AssessmentApplicability` (`assessment_applicability`)
- **Table:** `assessment_applicability`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `assessment_id`: foreign key (`BIGINT` -> `assessments.id`, Restrict)
  - `class_subject_id`: foreign key (`BIGINT` -> `class_subjects.id`, Restrict)
  - `maximum_marks`: decimal (`NUMERIC(6,2)`, must be > 0 via `chk_aa_max_marks_positive`)
  - `is_active`: boolean (`BOOLEAN`, default `true`)
  - `created_at`: `TIMESTAMPTZ`
  - `updated_at`: `TIMESTAMPTZ`
- **Unique Constraints:** `uk_aa_assessment_class_subject`: `(assessment_id, class_subject_id)`
- **Eloquent Configuration:**
  - `$casts = ['maximum_marks' => 'decimal:2', 'is_active' => 'boolean']`

### 6.15 Entity 15: `TeacherAssignment` (`teacher_assignments`)
- **Table:** `teacher_assignments`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `user_id`: foreign key (`BIGINT` -> `users.id`, Restrict)
  - `academic_year_id`: foreign key (`BIGINT` -> `academic_years.id`, Restrict)
  - `class_id`: foreign key (`BIGINT` -> `classes.id`, Restrict)
  - `section_id`: foreign key (`BIGINT` -> `sections.id`, Restrict)
  - `subject_id`: foreign key/null (`BIGINT` -> `subjects.id`, Restrict)
  - `assignment_type`: enum string (`teacher_assignment_type_enum`: `'subject_teacher'`, `'class_teacher'`)
  - `effective_from`: date (`DATE`)
  - `effective_to`: date/null (`DATE`)
  - `is_active`: boolean (`BOOLEAN`, default `true`)
  - `created_at`: `TIMESTAMPTZ`
  - `updated_at`: `TIMESTAMPTZ`
- **Database Consistency Constraint:**
  - `chk_ta_assignment_subject_consistency`:
    `('class_teacher' AND subject_id IS NULL) OR ('subject_teacher' AND subject_id IS NOT NULL)`
- **Domain Rule:** A teacher may hold multiple assignments. Class Teacher assignment automatically confers authority over all subjects in that section.

### 6.16 Entity 16: `Mark` (`marks`)
- **Table:** `marks`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `student_academic_record_id`: foreign key (`BIGINT` -> `student_academic_records.id`, Restrict)
  - `student_subject_allocation_id`: foreign key (`BIGINT` -> `student_subject_allocations.id`, Restrict)
  - `assessment_applicability_id`: foreign key (`BIGINT` -> `assessment_applicability.id`, Restrict)
  - `mark_value`: decimal/null (`NUMERIC(6,2)`)
  - `result_status`: enum string (`mark_result_status_enum`: `'blank'`, `'numeric'`, `'absent'`)
  - `entered_by_user_id`: foreign key/null (`BIGINT` -> `users.id`, Restrict)
  - `updated_by_user_id`: foreign key/null (`BIGINT` -> `users.id`, Restrict)
  - `created_at`: `TIMESTAMPTZ`
  - `updated_at`: `TIMESTAMPTZ`
- **Unique Constraints:** `uk_marks_allocation_applicability`: `(student_subject_allocation_id, assessment_applicability_id)`
- **Consistency Constraint:**
  - `chk_marks_result_consistency`:
    - `result_status = 'numeric' AND mark_value IS NOT NULL AND mark_value >= 0`
    - `result_status = 'blank' AND mark_value IS NULL`
    - `result_status = 'absent' AND mark_value IS NULL`
- **Critical Casting Rule:** `mark_value` MUST NOT be cast to an unqualified integer or float that converts `NULL` to `0.00`. Custom cast or nullable string/decimal accessor preserves `blank` vs `0.00` vs `A`.

### 6.17 Entity 17: `Attendance` (`attendance`)
- **Table:** `attendance`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `student_academic_record_id`: foreign key (`BIGINT` -> `student_academic_records.id`, Restrict)
  - `term_id`: foreign key (`BIGINT` -> `terms.id`, Restrict)
  - `days_attended`: integer (`INTEGER`, default 0)
  - `total_working_days`: integer (`INTEGER`, default 0)
  - `entered_by_user_id`: foreign key (`BIGINT` -> `users.id`, Restrict)
  - `updated_by_user_id`: foreign key (`BIGINT` -> `users.id`, Restrict)
  - `created_at`: `TIMESTAMPTZ`
  - `updated_at`: `TIMESTAMPTZ`
- **Unique Constraints:** `uk_attendance_record_term`: `(student_academic_record_id, term_id)`
- **Bounds Constraints:** `days_attended >= 0`, `total_working_days >= 0`, `days_attended <= total_working_days`.
- **Domain Rule:** When `total_working_days = 0`, report percentage renders as `N/A`. Division by zero is strictly prohibited.

### 6.18 Entity 18: `CalculationSetting` (`calculation_settings`)
- **Table:** `calculation_settings`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `academic_year_id`: foreign key (`BIGINT` -> `academic_years.id`, Restrict)
  - `class_id`: foreign key (`BIGINT` -> `classes.id`, Restrict)
  - `calculation_method`: enum string (`calculation_method_enum`: `'average_percentage'`, `'combined_marks'`)
  - `created_at`: `TIMESTAMPTZ`
  - `updated_at`: `TIMESTAMPTZ`
- **Unique Constraints:** `uk_cs_year_class`: `(academic_year_id, class_id)`
- **Domain Rule:** Exactly one calculation formula per academic year and class. Currently, only Term Exam contributes to the term percentage.

### 6.19 Entity 19: `ReportConfiguration` (`report_configurations`)
- **Table:** `report_configurations`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `academic_year_id`: foreign key/null (`BIGINT` -> `academic_years.id`, Restrict)
  - `name`: string (`VARCHAR(150)`)
  - `report_type`: enum string (`report_type_enum`: `'exam'`, `'term'`, `'final'`)
  - `configuration_data`: JSONB/null (`JSONB`)
  - `is_active`: boolean (`BOOLEAN`, default `true`)
  - `created_at`: `TIMESTAMPTZ`
  - `updated_at`: `TIMESTAMPTZ`
- **Eloquent Configuration:** `$casts = ['configuration_data' => 'array', 'is_active' => 'boolean']`

### 6.20 Entity 20: `ReportAssessmentSelection` (`report_assessment_selections`)
- **Table:** `report_assessment_selections`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `report_configuration_id`: foreign key (`BIGINT` -> `report_configurations.id`, Restrict)
  - `assessment_id`: foreign key (`BIGINT` -> `assessments.id`, Restrict)
  - `display_order`: integer (`INTEGER`)
  - `is_displayed`: boolean (`BOOLEAN`, default `true`)
  - `created_at`: `TIMESTAMPTZ`
  - `updated_at`: `TIMESTAMPTZ`
- **Unique Constraints:** `uk_ras_config_assessment`: `(report_configuration_id, assessment_id)`
- **Domain Rule:** Separates visual display from calculation participation. Being displayed does not imply participation in calculation.

### 6.21 Entity 21: `SchoolSetting` (`school_settings`)
- **Table:** `school_settings`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `school_name`: string (`VARCHAR(200)`)
  - `school_logo_path`: string/null (`VARCHAR(500)`)
  - `pass_mark`: decimal (`NUMERIC(6,2)`, default 0.00, check `>= 0`)
  - `created_at`: `TIMESTAMPTZ`
  - `updated_at`: `TIMESTAMPTZ`
- **Domain Rule:** Singleton table for single-school application. No tenant keys.

### 6.22 Entity 22: `GeneratedReport` (`generated_reports`)
- **Table:** `generated_reports`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `student_academic_record_id`: foreign key (`BIGINT` -> `student_academic_records.id`, Restrict)
  - `report_type`: enum string (`report_type_enum`: `'exam'`, `'term'`, `'final'`)
  - `term_id`: foreign key/null (`BIGINT` -> `terms.id`, Restrict)
  - `assessment_id`: foreign key/null (`BIGINT` -> `assessments.id`, Restrict)
  - `revision_number`: integer (`INTEGER`, default 1)
  - `file_path`: string (`VARCHAR(500)`)
  - `generated_by_user_id`: foreign key (`BIGINT` -> `users.id`, Restrict)
  - `generated_at`: `TIMESTAMPTZ` default `CURRENT_TIMESTAMP`
- **Timestamp Note:** No `updated_at` column. Reports are immutable revisions. Re-generating increments `revision_number`.
- **Unique Constraints:** Functional unique index `uk_gr_revision_identity` on `(student_academic_record_id, report_type, COALESCE(term_id, 0), COALESCE(assessment_id, 0), revision_number)`

### 6.23 Entity 23: `AuditLog` (`audit_logs`)
- **Table:** `audit_logs`
- **Primary Key:** `id` (BIGINT, Identity)
- **Attributes & Types:**
  - `user_id`: foreign key/null (`BIGINT` -> `users.id`, Restrict)
  - `action`: string (`VARCHAR(100)`)
  - `entity_type`: string (`VARCHAR(100)`)
  - `entity_id`: integer/null (`BIGINT`)
  - `before_data`: JSONB/null (`JSONB`)
  - `after_data`: JSONB/null (`JSONB`)
  - `description`: text/null (`TEXT`)
  - `ip_address`: string/null (`VARCHAR(45)`)
  - `created_at`: `TIMESTAMPTZ` default `CURRENT_TIMESTAMP`
- **Timestamp Note:** No `updated_at` column. Append-only ledger. Normal delete operations are prohibited.

---

## 7. Primary Keys, Timestamps & Type Casting

### 7.1 Primary Key Handling
All 23 tables use 64-bit auto-incrementing identity keys:
```sql
id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY
```
In Eloquent, all models use standard integer configuration:
```php
protected $keyType = 'int';
public $incrementing = true;
```

### 7.2 Timestamps & Coexistence with PostgreSQL Triggers
In the validated PostgreSQL database, 21 tables utilize an `updated_at` trigger:
```sql
CREATE TRIGGER trg_{table}_updated_at
    BEFORE UPDATE ON {table}
    FOR EACH ROW
    EXECUTE FUNCTION trigger_set_updated_at();
```
Two tables are append-only and do not have an `updated_at` column:
- `generated_reports` (has `generated_at`, no `updated_at`)
- `audit_logs` (has `created_at`, no `updated_at`)

**Eloquent Coexistence Strategy:**
1. For standard tables (21 tables), Eloquent manages `public $timestamps = true;`. When Eloquent executes an `UPDATE`, it supplies `updated_at = Carbon::now()`. The PostgreSQL trigger also assigns `NEW.updated_at = clock_timestamp()`. Because both values represent the current UTC timestamp, they align seamlessly.
2. For `GeneratedReport`:
   ```php
   public $timestamps = false;
   protected $casts = [
       'generated_at' => 'immutable_datetime',
   ];
   ```
3. For `AuditLog`:
   ```php
   const UPDATED_AT = null;
   protected $casts = [
       'created_at' => 'immutable_datetime',
       'before_data' => 'array',
       'after_data' => 'array',
   ];
   ```

### 7.3 Data Type Casting Matrix

| PostgreSQL Type | Eloquent Model Cast | Critical Handling & Boundary Behavior |
|---|---|---|
| `BIGINT` (PK / FK) | `'integer'` | Represented as standard 64-bit PHP integer. |
| `BOOLEAN` | `'boolean'` | Casts PostgreSQL `'t'`/`'f'` to native PHP `true`/`false`. |
| `DATE` | `'date:Y-m-d'` | Carbon instance formatted as `YYYY-MM-DD`. |
| `TIMESTAMPTZ` | `'immutable_datetime'` | Stored in UTC, cast to `CarbonImmutable` to prevent inadvertent mutation. |
| `JSONB` | `'array'` | Decoded automatically into PHP associative arrays. |
| `NUMERIC(6,2)` (Max marks) | `'decimal:2'` | Preserves exact two-place precision as a string/decimal. |
| `NUMERIC(6,2)` (`mark_value`) | Custom Value Object or Nullable Accessor | **CRITICAL:** Standard casting must not convert SQL `NULL` to string `"0.00"`. Must maintain explicit distinction: `null` (blank), `"0.00"` (zero), and `"A"` (absent via `result_status`). |
| PostgreSQL ENUM | Native PHP Backed Enum | Cast to dedicated PHP 8.4 Backed Enums (`AcademicYearStatus`, `MarkResultStatus`, `TeacherAssignmentType`, etc.). |

---

## 8. Complete Eloquent Relationship Matrix

Below is the authoritative mapping of all foreign-key relationships across all 23 models.

| Source Model | Relationship Method | Target Model | Relation Type | Foreign Key | Local Key | Inverse Method | Nullable | Historical Implication |
|---|---|---|---|---|---|---|---|---|
| `Role` | `users()` | `User` | `hasMany` | `role_id` | `id` | `role()` | No | Master access control |
| `User` | `role()` | `Role` | `belongsTo` | `role_id` | `id` | `users()` | No | User belongs to exactly 1 role |
| `User` | `teacherAssignments()` | `TeacherAssignment`| `hasMany` | `user_id` | `id` | `user()` | No | Teacher may have multiple scopes |
| `User` | `enteredMarks()` | `Mark` | `hasMany` | `entered_by_user_id` | `id` | `enteredByUser()` | Yes | Audit trail of entry |
| `User` | `updatedMarks()` | `Mark` | `hasMany` | `updated_by_user_id` | `id` | `updatedByUser()` | Yes | Audit trail of modification |
| `User` | `enteredAttendance()` | `Attendance` | `hasMany` | `entered_by_user_id` | `id` | `enteredByUser()` | No | Attendance actor audit |
| `User` | `generatedReports()` | `GeneratedReport` | `hasMany` | `generated_by_user_id`| `id` | `generatedByUser()` | No | Report author audit |
| `User` | `auditLogs()` | `AuditLog` | `hasMany` | `user_id` | `id` | `user()` | Yes | Actor activity history |
| `AcademicYear` | `terms()` | `Term` | `hasMany` | `academic_year_id` | `id` | `academicYear()` | No | Terms within academic year |
| `AcademicYear` | `sections()` | `Section` | `hasMany` | `academic_year_id` | `id` | `academicYear()` | No | Sections configured for year |
| `AcademicYear` | `classSubjects()` | `ClassSubject` | `hasMany` | `academic_year_id` | `id` | `academicYear()` | No | Academic curriculum config |
| `AcademicYear` | `studentAcademicRecords()`| `StudentAcademicRecord`| `hasMany`| `academic_year_id` | `id` | `academicYear()` | No | Student placements in year |
| `AcademicYear` | `assessments()` | `Assessment` | `hasMany` | `academic_year_id` | `id` | `academicYear()` | No | Assessment events in year |
| `AcademicYear` | `teacherAssignments()` | `TeacherAssignment`| `hasMany` | `academic_year_id` | `id` | `academicYear()` | No | Teacher assignments in year |
| `AcademicYear` | `calculationSettings()`| `CalculationSetting`| `hasMany` | `academic_year_id` | `id` | `academicYear()` | No | Class formulas for year |
| `AcademicYear` | `reportConfigurations()`| `ReportConfiguration`| `hasMany`| `academic_year_id` | `id` | `academicYear()` | Yes | Scoped report configs |
| `Term` | `academicYear()` | `AcademicYear` | `belongsTo` | `academic_year_id` | `id` | `terms()` | No | Parent academic year |
| `Term` | `assessments()` | `Assessment` | `hasMany` | `term_id` | `id` | `term()` | Yes | Assessments in term |
| `Term` | `attendanceRecords()` | `Attendance` | `hasMany` | `term_id` | `id` | `term()` | No | Term-level attendance |
| `Term` | `generatedReports()` | `GeneratedReport` | `hasMany` | `term_id` | `id` | `term()` | Yes | Term report cards |
| `SchoolClass` | `sections()` | `Section` | `hasMany` | `class_id` | `id` | `schoolClass()` | No | Sections in class |
| `SchoolClass` | `classSubjects()` | `ClassSubject` | `hasMany` | `class_id` | `id` | `schoolClass()` | No | Curriculum assigned to class |
| `SchoolClass` | `studentAcademicRecords()`| `StudentAcademicRecord`| `hasMany`| `class_id` | `id` | `schoolClass()` | No | Placements in class |
| `SchoolClass` | `teacherAssignments()` | `TeacherAssignment`| `hasMany` | `class_id` | `id` | `schoolClass()` | No | Teacher assignments in class |
| `SchoolClass` | `calculationSettings()`| `CalculationSetting`| `hasMany` | `class_id` | `id` | `schoolClass()` | No | Calculation formula per class |
| `Section` | `academicYear()` | `AcademicYear` | `belongsTo` | `academic_year_id` | `id` | `sections()` | No | Academic year context |
| `Section` | `schoolClass()` | `SchoolClass` | `belongsTo` | `class_id` | `id` | `sections()` | No | Class definition |
| `Section` | `classSubjects()` | `ClassSubject` | `hasMany` | `section_id` | `id` | `section()` | Yes | Section-specific curriculum |
| `Section` | `studentAcademicRecords()`| `StudentAcademicRecord`| `hasMany`| `section_id` | `id` | `section()` | No | Students placed in section |
| `Section` | `teacherAssignments()` | `TeacherAssignment`| `hasMany` | `section_id` | `id` | `section()` | No | Class/Subject teachers |
| `Subject` | `classSubjects()` | `ClassSubject` | `hasMany` | `subject_id` | `id` | `subject()` | No | Catalog usage in curricula |
| `Subject` | `teacherAssignments()` | `TeacherAssignment`| `hasMany` | `subject_id` | `id` | `subject()` | Yes | Specific subject assignment |
| `ClassSubject` | `academicYear()` | `AcademicYear` | `belongsTo` | `academic_year_id` | `id` | `classSubjects()` | No | Academic year context |
| `ClassSubject` | `schoolClass()` | `SchoolClass` | `belongsTo` | `class_id` | `id` | `classSubjects()` | No | Class context |
| `ClassSubject` | `section()` | `Section` | `belongsTo` | `section_id` | `id` | `classSubjects()` | Yes | Null for class-wide subject |
| `ClassSubject` | `subject()` | `Subject` | `belongsTo` | `subject_id` | `id` | `classSubjects()` | No | Master catalog record |
| `ClassSubject` | `studentSubjectAllocations()`| `StudentSubjectAllocation`| `hasMany`| `class_subject_id` | `id` | `classSubject()` | No | Student enrollments |
| `ClassSubject` | `assessmentApplicabilities()`| `AssessmentApplicability`| `hasMany`| `class_subject_id` | `id` | `classSubject()` | No | Maximum marks per exam |
| `Student` | `academicRecords()` | `StudentAcademicRecord`| `hasMany`| `student_id` | `id` | `student()` | No | Lifelong enrollment history |
| `StudentAcademicRecord` | `student()` | `Student` | `belongsTo` | `student_id` | `id` | `academicRecords()` | No | Master student record |
| `StudentAcademicRecord` | `academicYear()` | `AcademicYear` | `belongsTo` | `academic_year_id` | `id` | `studentAcademicRecords()`| No | Historical year context |
| `StudentAcademicRecord` | `schoolClass()` | `SchoolClass` | `belongsTo` | `class_id` | `id` | `studentAcademicRecords()`| No | Historical class context |
| `StudentAcademicRecord` | `section()` | `Section` | `belongsTo` | `section_id` | `id` | `studentAcademicRecords()`| No | Historical section context |
| `StudentAcademicRecord` | `subjectAllocations()`| `StudentSubjectAllocation`| `hasMany`| `student_academic_record_id`| `id`| `studentAcademicRecord()`| No | Historical subject scope |
| `StudentAcademicRecord` | `marks()` | `Mark` | `hasMany` | `student_academic_record_id`| `id`| `studentAcademicRecord()`| No | Historical marks earned |
| `StudentAcademicRecord` | `attendanceRecords()`| `Attendance` | `hasMany` | `student_academic_record_id`| `id`| `studentAcademicRecord()`| No | Historical attendance |
| `StudentAcademicRecord` | `generatedReports()` | `GeneratedReport` | `hasMany` | `student_academic_record_id`| `id`| `studentAcademicRecord()`| No | Historical report cards |
| `StudentSubjectAllocation` | `studentAcademicRecord()`| `StudentAcademicRecord`| `belongsTo`| `student_academic_record_id`| `id`| `subjectAllocations()`| No | Student placement |
| `StudentSubjectAllocation` | `classSubject()` | `ClassSubject` | `belongsTo` | `class_subject_id` | `id` | `studentSubjectAllocations()`| No | Curriculum configuration |
| `StudentSubjectAllocation` | `marks()` | `Mark` | `hasMany` | `student_subject_allocation_id`| `id`| `studentSubjectAllocation()`| No | Marks tied to allocation |
| `AssessmentType` | `assessments()` | `Assessment` | `hasMany` | `assessment_type_id` | `id` | `assessmentType()` | No | Master type categorization |
| `Assessment` | `academicYear()` | `AcademicYear` | `belongsTo` | `academic_year_id` | `id` | `assessments()` | No | Academic year context |
| `Assessment` | `term()` | `Term` | `belongsTo` | `term_id` | `id` | `assessments()` | Yes | Null for final/full-year exam |
| `Assessment` | `assessmentType()` | `AssessmentType` | `belongsTo` | `assessment_type_id` | `id` | `assessments()` | No | Assessment category |
| `Assessment` | `applicabilities()` | `AssessmentApplicability`| `hasMany`| `assessment_id` | `id` | `assessment()` | No | Per-subject max marks |
| `Assessment` | `reportSelections()` | `ReportAssessmentSelection`| `hasMany`| `assessment_id` | `id` | `assessment()` | No | Report display config |
| `Assessment` | `generatedReports()` | `GeneratedReport` | `hasMany` | `assessment_id` | `id` | `assessment()` | Yes | Exam-specific report card |
| `AssessmentApplicability` | `assessment()` | `Assessment` | `belongsTo` | `assessment_id` | `id` | `applicabilities()` | No | Parent assessment |
| `AssessmentApplicability` | `classSubject()` | `ClassSubject` | `belongsTo` | `class_subject_id` | `id` | `assessmentApplicabilities()`| No | Target class subject |
| `AssessmentApplicability` | `marks()` | `Mark` | `hasMany` | `assessment_applicability_id`| `id`| `assessmentApplicability()`| No | Marks graded under max |
| `TeacherAssignment` | `user()` | `User` | `belongsTo` | `user_id` | `id` | `teacherAssignments()` | No | Teacher user account |
| `TeacherAssignment` | `academicYear()` | `AcademicYear` | `belongsTo` | `academic_year_id` | `id` | `teacherAssignments()` | No | Assigned academic year |
| `TeacherAssignment` | `schoolClass()` | `SchoolClass` | `belongsTo` | `class_id` | `id` | `teacherAssignments()` | No | Assigned class |
| `TeacherAssignment` | `section()` | `Section` | `belongsTo` | `section_id` | `id` | `teacherAssignments()` | No | Assigned section |
| `TeacherAssignment` | `subject()` | `Subject` | `belongsTo` | `subject_id` | `id` | `teacherAssignments()` | Yes | Null for Class Teacher |
| `Mark` | `studentAcademicRecord()`| `StudentAcademicRecord`| `belongsTo`| `student_academic_record_id`| `id`| `marks()` | No | Student placement |
| `Mark` | `studentSubjectAllocation()`| `StudentSubjectAllocation`| `belongsTo`| `student_subject_allocation_id`| `id`| `marks()` | No | Subject allocation |
| `Mark` | `assessmentApplicability()`| `AssessmentApplicability`| `belongsTo`| `assessment_applicability_id`| `id`| `marks()` | No | Max marks & exam context |
| `Mark` | `enteredByUser()` | `User` | `belongsTo` | `entered_by_user_id` | `id` | `enteredMarks()` | Yes | Data entry actor |
| `Mark` | `updatedByUser()` | `User` | `belongsTo` | `updated_by_user_id` | `id` | `updatedMarks()` | Yes | Modification actor |
| `Attendance` | `studentAcademicRecord()`| `StudentAcademicRecord`| `belongsTo`| `student_academic_record_id`| `id`| `attendanceRecords()` | No | Student placement |
| `Attendance` | `term()` | `Term` | `belongsTo` | `term_id` | `id` | `attendanceRecords()` | No | Term context |
| `Attendance` | `enteredByUser()` | `User` | `belongsTo` | `entered_by_user_id` | `id` | `enteredAttendance()` | No | Entry actor |
| `Attendance` | `updatedByUser()` | `User` | `belongsTo` | `updated_by_user_id` | `id` | `updatedAttendance()` | No | Update actor |
| `CalculationSetting` | `academicYear()` | `AcademicYear` | `belongsTo` | `academic_year_id` | `id` | `calculationSettings()`| No | Academic year context |
| `CalculationSetting` | `schoolClass()` | `SchoolClass` | `belongsTo` | `class_id` | `id` | `calculationSettings()`| No | Class context |
| `ReportConfiguration` | `academicYear()` | `AcademicYear` | `belongsTo` | `academic_year_id` | `id` | `reportConfigurations()`| Yes | Null for global template |
| `ReportConfiguration` | `assessmentSelections()`| `ReportAssessmentSelection`| `hasMany`| `report_configuration_id`| `id`| `reportConfiguration()`| No | Ordered assessments |
| `ReportAssessmentSelection` | `reportConfiguration()`| `ReportConfiguration`| `belongsTo`| `report_configuration_id`| `id`| `assessmentSelections()`| No | Parent report config |
| `ReportAssessmentSelection` | `assessment()` | `Assessment` | `belongsTo` | `assessment_id` | `id` | `reportSelections()` | No | Target assessment |
| `SchoolSetting` | *(None)* | *(None)* | *(None)* | *(None)* | *(None)* | *(None)* | *(None)* | Singleton record |
| `GeneratedReport` | `studentAcademicRecord()`| `StudentAcademicRecord`| `belongsTo`| `student_academic_record_id`| `id`| `generatedReports()` | No | Placement record |
| `GeneratedReport` | `term()` | `Term` | `belongsTo` | `term_id` | `id` | `generatedReports()` | Yes | Null for exam/final |
| `GeneratedReport` | `assessment()` | `Assessment` | `belongsTo` | `assessment_id` | `id` | `generatedReports()` | Yes | Null for term/final |
| `GeneratedReport` | `generatedByUser()` | `User` | `belongsTo` | `generated_by_user_id`| `id` | `generatedReports()` | No | Staff member |
| `AuditLog` | `user()` | `User` | `belongsTo` | `user_id` | `id` | `auditLogs()` | Yes | Actor (null for system) |

---

## 9. Domain Relationship Graph

The ASCII graph below illustrates the navigation paths through Eloquent:

```
User (Account & Auth)
 ├── belongsTo Role
 ├── hasMany TeacherAssignment ──► [AcademicYear, SchoolClass, Section, Subject]
 ├── hasMany Mark (as entered_by / updated_by)
 ├── hasMany Attendance (as entered_by / updated_by)
 ├── hasMany GeneratedReport (as generated_by)
 └── hasMany AuditLog (as actor)

AcademicYear (Year Lifecycle)
 ├── hasMany Term
 ├── hasMany Section (per class)
 ├── hasMany ClassSubject ──► [SchoolClass, Section, Subject] (Holds subject_name_snapshot)
 ├── hasMany StudentAcademicRecord (Historical Placements)
 ├── hasMany Assessment ──► [Term, AssessmentType]
 ├── hasMany TeacherAssignment
 ├── hasMany CalculationSetting (Formula per class)
 └── hasMany ReportConfiguration

Student (Master Identity)
 └── hasMany StudentAcademicRecord (Historical Placements per Year)
      ├── belongsTo AcademicYear, SchoolClass, Section
      ├── hasMany StudentSubjectAllocation ──► ClassSubject
      │    └── hasMany Mark ◄── AssessmentApplicability (Max Marks)
      ├── hasMany Attendance (per Term)
      └── hasMany GeneratedReport (PDF Revisions per Term/Exam/Final)

ReportConfiguration (Report Schemas)
 └── hasMany ReportAssessmentSelection ──► Assessment
```

---

## 10. Model Mutability & Historical Integrity

To preserve academic integrity and prevent accidental data loss, all 23 models are classified into four architectural mutability tiers.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    MODEL MUTABILITY CLASSIFICATION TIERS                     │
├───────────────────────────────┬─────────────────────────────────────────────┤
│ Tier A: Configuration & Data  │ Role, User, Class, Section, Subject,        │
│                               │ AssessmentType, CalculationSetting,         │
│                               │ ReportConfiguration, SchoolSetting          │
├───────────────────────────────┼─────────────────────────────────────────────┤
│ Tier B: Historically Sensitive│ AcademicYear, Term, ClassSubject,           │
│                               │ Student, Assessment, AssessmentApplicability│
├───────────────────────────────┼─────────────────────────────────────────────┤
│ Tier C: Append-Only / Frozen  │ StudentAcademicRecord,                      │
│                               │ GeneratedReport, AuditLog                   │
├───────────────────────────────┼─────────────────────────────────────────────┤
│ Tier D: Context-Dependent     │ StudentSubjectAllocation, Mark, Attendance, │
│                               │ TeacherAssignment                           │
└───────────────────────────────┴─────────────────────────────────────────────┘
```

### 10.1 Tier A: Normal Configuration / Data Models
- May be edited by authorized Administrators or Office Staff following standard validation.
- Deletion is restricted at the database level if child records exist (`ON DELETE RESTRICT`).

### 10.2 Tier B: Historically Sensitive Models
- Once academic activities begin, structural modifications are constrained:
  - `AcademicYear`: Transitions from `open` to `closed`. Reopening requires Administrator privileges.
  - `ClassSubject`: `subject_name_snapshot` is frozen upon creation.
  - `AssessmentApplicability`: `maximum_marks` cannot be altered after marks exist for that applicability row.

### 10.3 Tier C: Append-Only / Historical Models
- `StudentAcademicRecord`: Historical placement. Never overwritten on student transfer; new placement row created.
- `GeneratedReport`: PDFs and revision records are strictly append-only. Existing files are never overwritten.
- `AuditLog`: Immutable system ledger. No updates or deletions permitted under any circumstance.

### 10.4 Tier D: Context-Dependent Mutable Models
- `Mark`: Updates are permitted throughout the open academic year (latest authorized value becomes current). Every update is audited.
- `StudentSubjectAllocation`: Electives may only be modified *before* marks have been entered for that subject.
- `Attendance`: Editable within the open academic year with bounds validation (`attended <= total`).
- `TeacherAssignment`: Assignments can be granted or revoked by setting `effective_to` or `is_active = false`.

---

## 11. Mark Domain Model Architecture

The `Mark` model represents the core academic grading record. It must strictly adhere to finalized business requirements:

```
                      [AssessmentApplicability] (defines maximum_marks)
                                 ▲
                                 │
[StudentSubjectAllocation] ──► [Mark] ◄── [StudentAcademicRecord]
                                 │
                   ┌─────────────┴─────────────┐
                   ▼                           ▼
          mark_value (decimal/null)      result_status (enum)
          - null                         - 'blank'  (incomplete)
          - 0.00 to max_marks            - 'numeric' (evaluated)
          - null                         - 'absent'  (attended: 0)
```

### 11.1 Discrete Mark States
1. **Unentered / Blank (`result_status = 'blank'`, `mark_value = NULL`):**
   - Represents an incomplete assessment.
   - Distinct from numeric zero.
   - Prevents report card generation (reports cannot be finalized if any mandatory assessment is blank).
2. **Assessed Zero (`result_status = 'numeric'`, `mark_value = 0.00`):**
   - Student took the exam and scored zero.
   - Valid completion state; contributes $0$ to totals and percentages.
3. **Absent (`result_status = 'absent'`, `mark_value = NULL`):**
   - Student was absent.
   - Displays as `"A"` on reports and mark entry grids.
   - Valid completion state; contributes $0$ to totals and percentages.

### 11.2 Mark Model Boundary
The `Mark` Eloquent model is strictly a **persistence entity**. It holds attributes, casts, and relationships. It must **not** contain mark validation logic, lockouts, or audit logging side-effects. The `MarkEntryService` (Phase 6.3) manages mark transitions inside database transactions.

---

## 12. Student Placement & Transfer Model

```
                    ┌───────────────────────────┐
                    │      Student Master       │
                    │        (student_id)       │
                    └─────────────┬─────────────┘
                                  │
         ┌────────────────────────┴────────────────────────┐
         ▼                                                 ▼
[Historical Placement]                           [Current Placement]
- Class 9, Section A                             - Class 9, Section B
- Roll: 14                                       - Roll: 22
- Status: 'internal_transfer'                    - Status: 'active'
- Effective: 2025-04-01 to 2025-08-15            - Effective: 2025-08-16 to null
- Marks: Term 1 Exam Marks Attached              - Marks: Future Marks Attached
```

### 12.1 Historical Placement Integrity
- `student_academic_records` models the student's physical placement.
- When an internal transfer occurs:
  1. The existing `StudentAcademicRecord` is closed by setting `status = 'internal_transfer'` and `effective_to = current_date`.
  2. A new `StudentAcademicRecord` is created with the new `section_id`, new `roll_number`, `status = 'active'`, and `effective_from = current_date`.
  3. Historical marks and attendance remain attached to the **original** `student_academic_record_id`. They are **never** migrated or updated to the new record.
- Soft deletes are prohibited.

---

## 13. Student Subject Allocation / Elective Model

The `StudentSubjectAllocation` model resolves subject enrollment per placement:
- Core subjects (`allocation_type = 'main'`) apply to all students in the class/section.
- Elective subjects (`allocation_type = 'elective'`) apply on a per-student basis.

### 13.1 Elective Lockout Rule
- An elective subject may be modified or de-allocated during the academic year **only if no marks exist** for that allocation.
- In Eloquent:
  ```php
  public function hasMarks(): bool
  {
      return $this->marks()->exists();
  }
  ```
- Mutation enforcement is managed by `StudentEnrollmentService` (Phase 6.3).

---

## 14. Assessment & Applicability Model

Assessment architecture decouples assessment definitions from class-level subject configurations:

```
[AssessmentType] ──► [Assessment] ──► [AssessmentApplicability] ◄── [ClassSubject]
  (Term Exam)       (Term 1 Exam)         (Max Marks: 100.00)           (Class 10 - Math)
```

1. `Assessment`: Defines the evaluation milestone (e.g. "Unit Test 1", "Term 1 Exam", "Annual Exam").
2. `AssessmentApplicability`: Links the `Assessment` to a specific `ClassSubject` and assigns `maximum_marks` (e.g., Math = 100, Physics Theory = 70, Practical = 30).
3. Display vs. Calculation Separation: `ReportAssessmentSelection` controls whether an assessment appears on a report card. Calculation participation is determined independently by the calculation engine (currently, only `Term Exam` participates in term percentage).

---

## 15. Calculation Configuration Model

The `CalculationSetting` model stores the approved calculation formula per `academic_year_id` and `class_id`:
- `calculation_method`:
  - `average_percentage`: Equal average of included assessment percentages.
  - `combined_marks`: $\frac{\sum \text{Marks Obtained}}{\sum \text{Maximum Marks}} \times 100$.

### 15.1 Calculation Boundary
- Both calculation methods must remain fully implemented even though the current rule (only Term Exam contributes) yields identical results.
- Dynamic Terms: The engine adapts dynamically to the number of terms defined for the academic year.
- Calculation logic resides exclusively in `CalculationService` (Phase 6.3).

---

## 16. Teacher Assignment Domain Model

Teacher authorization is governed by the `TeacherAssignment` model.

```
                             [User] (Teacher)
                                │
                 hasMany TeacherAssignment
                                │
        ┌───────────────────────┴───────────────────────┐
        ▼                                               ▼
[Class Teacher Assignment]                     [Subject Teacher Assignment]
- academic_year_id                             - academic_year_id
- class_id                                     - class_id
- section_id                                   - section_id
- subject_id = NULL                            - subject_id = 4 (e.g. Science)
- assignment_type = 'class_teacher'            - assignment_type = 'subject_teacher'
- Scope: ALL subjects in Section               - Scope: Assigned Subject ONLY
```

### 16.1 Architectural Rules
1. **Multi-Assignment Support:** A teacher may hold multiple assignments simultaneously (e.g., Class Teacher of 8-A, and Subject Teacher for Math in 9-B and 10-A).
2. **Scope Resolution:**
   - A `ClassTeacher` assignment grants access to **all** applicable subjects within the assigned class and section.
   - A `SubjectTeacher` assignment restricts access to the **assigned subject** within that class and section.
3. **Boundary:** The model exposes relationships and query scopes. Full authorization checks are performed by `TeacherAuthorizationService` and Laravel Policies (Phase 6.4).

---

## 17. Attendance Model

The `Attendance` model records term-level attendance:
- Unique per `(student_academic_record_id, term_id)`.
- Enforces `days_attended <= total_working_days`.

### 17.1 Attendance Division-by-Zero Rule
- Formula: $\text{Percentage} = \frac{\text{days\_attended}}{\text{total\_working\_days}} \times 100$.
- **Safe Handling:** When `total_working_days = 0`, report cards render `"N/A"`. Eloquent models and calculation services must never trigger division by zero.

---

## 18. Report Configuration Model

The `ReportConfiguration` model manages report templates (`exam`, `term`, `final`):
- `configuration_data` (`JSONB`): Stores layout preferences, grading scale visibility, signature lines, and header options.
- `report_assessment_selections`: Has-many relationship defining the sequence and visibility of assessment columns.

---

## 19. Generated Report Revision Model

The `GeneratedReport` model preserves generated PDF report cards:
- Attributes: `student_academic_record_id`, `report_type`, `term_id`, `assessment_id`, `revision_number`, `file_path`, `generated_by_user_id`, `generated_at`.
- **Append-Only Revisions:** When a report card is regenerated (e.g. following an approved mark correction), `revision_number` is incremented. The previous PDF file remains on disk and its database record is retained for auditability.
- No `updated_at` column; records are immutable.

---

## 20. Audit Log Model

The `AuditLog` model provides the institutional activity record:
- Attributes: `user_id`, `action`, `entity_type`, `entity_id`, `before_data` (`JSONB`), `after_data` (`JSONB`), `description`, `ip_address`, `created_at`.
- **Immutability:** No `updated_at` timestamp. Normal updates and deletions are strictly disabled.
- **Access Scope:** Only users with the `Administrator` role can view audit logs.

---

## 21. School Settings Model

The `SchoolSetting` model stores institutional metadata:
- Attributes: `school_name`, `school_logo_path`, `pass_mark` (default `0.00`).
- **Singleton Architecture:** The database contains a single record. Multi-tenancy columns (`tenant_id`, `school_id`) are excluded.

---

## 22. Eloquent Convention Exceptions

The table below documents every deviation from default Laravel Eloquent conventions necessitated by the PostgreSQL schema.

| Model / Area | Default Laravel Convention | Approved PostgreSQL / Project Reality | Eloquent Architectural Solution |
|---|---|---|---|
| **Class Model Name** | Model named `Class` | `class` is a reserved keyword in PHP | Model named `SchoolClass` with `protected $table = 'classes';` |
| **Timestamps on Reports** | Expects `created_at` & `updated_at` | `generated_reports` has only `generated_at` | `public $timestamps = false;` |
| **Timestamps on Audit** | Expects `created_at` & `updated_at` | `audit_logs` has only `created_at` | `const UPDATED_AT = null;` |
| **Mark Value Cast** | Casts decimal to string / float | Standard casts convert `NULL` to `0.00` | Custom Value Object or Nullable Accessor to preserve `blank` vs `0.00` |
| **Nullable Section in Config** | Standard composite unique check | `class_subjects` unique uses `COALESCE(section_id, 0)` | Model scope handles section-specific and class-wide configurations |
| **Revision Identity** | Auto-incrementing unique index | `generated_reports` uses functional index with `COALESCE` | Revision number computed via `MAX(revision_number) + 1` in service |
| **Soft Deletes** | `SoftDeletes` trait adds `deleted_at` | Prohibited on historical placement & audit | Explicit status enums (`status`, `is_active`); soft delete trait excluded |
| **Foreign Key Constraints** | Cascade deletes common in tutorials | Database enforces `ON DELETE RESTRICT` | Eloquent models do not define cascading deletes |

---

## 23. Model Events / Observers Policy

Model events (observers) can conceal critical domain logic and produce unexpected side effects.

### 23.1 Observer Policy
- **No Implicit Observers for Complex Operations:** Workflows such as mark entry, student transfers, report generation, and audit logging must **never** be triggered inside model `saving`, `saved`, `updating`, or `deleted` hooks.
- **Explicit Service Workflows:** All multi-entity mutations are executed explicitly through Application Services wrapped in database transactions.
- **Permitted Model Events:** Simple, self-contained attribute normalization (e.g. trimming string inputs) is permitted in model `saving` events.

---

## 24. Query & Eager-Loading Strategy

To eliminate $N+1$ query issues across high-volume academic screens, standard eager-loading profiles are defined:

```php
// Profile 1: Mark Entry Grid
$allocations = StudentSubjectAllocation::query()
    ->where('class_subject_id', $classSubjectId)
    ->where('is_active', true)
    ->with([
        'studentAcademicRecord.student',
        'marks' => fn($q) => $q->where('assessment_applicability_id', $applicabilityId)
    ])
    ->get();

// Profile 2: Report Card Generation
$placement = StudentAcademicRecord::query()
    ->with([
        'student',
        'schoolClass',
        'section',
        'academicYear',
        'subjectAllocations.classSubject.subject',
        'subjectAllocations.marks.assessmentApplicability.assessment',
        'attendanceRecords.term'
    ])
    ->findOrFail($recordId);

// Profile 3: Teacher Assignment Resolution
$assignments = TeacherAssignment::query()
    ->where('user_id', $userId)
    ->where('academic_year_id', $currentYearId)
    ->where('is_active', true)
    ->with(['schoolClass', 'section', 'subject'])
    ->get();
```

---

## 25. Authorization Boundary

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          AUTHORIZATION BOUNDARIES                           │
├───────────────────────┬─────────────────────────────────────────────────────┤
│ 1. Eloquent Models    │ Pure data mapping and relationships. Exposes        │
│                       │ teacher assignment associations and scopes.         │
├───────────────────────┼─────────────────────────────────────────────────────┤
│ 2. Laravel Policies   │ Authorizes HTTP actions via Gate/Policy checks      │
│                       │ (e.g. `MarkPolicy::update()`, `ReportPolicy::view()`)│
├───────────────────────┼─────────────────────────────────────────────────────┤
│ 3. Application Service│ Resolves contextual scopes over all active teacher  │
│                       │ assignments across academic years, classes, and     │
│                       │ subjects.                                           │
└───────────────────────┴─────────────────────────────────────────────────────┘
```

The domain model supports queries such as:
```php
// Check whether teacher holds subject or class authority
$isAuthorized = $teacher->teacherAssignments()
    ->where('academic_year_id', $academicYearId)
    ->where('class_id', $classId)
    ->where('section_id', $sectionId)
    ->where('is_active', true)
    ->where(function ($q) use ($subjectId) {
        $q->where('assignment_type', 'class_teacher')
          ->orWhere(fn($sq) => $sq->where('assignment_type', 'subject_teacher')->where('subject_id', $subjectId));
    })
    ->exists();
```

---

## 26. Service-Layer Boundary

Eloquent models remain focused persistence wrappers. Business workflows belong in dedicated Application Services:

| Domain Workflow | Responsible Application Service | Primary Responsibilities |
|---|---|---|
| **Mark Entry & Updating** | `MarkEntryService` | Validates max marks, verifies academic year is open, executes updates inside DB transaction, writes audit log. |
| **Student Placement & Transfer** | `StudentPlacementService` | Closes previous placement record, creates new placement record, preserves historical marks. |
| **Elective Allocation** | `StudentSubjectService` | Enforces elective lockout rule (checks if marks exist before allowing modification). |
| **Percentage & Total Calculation**| `CalculationService` | Evaluates Method 1 vs Method 2, applies Term Exam contribution rule, rounds to 2 decimal places. |
| **Report Card PDF Generation** | `ReportGenerationService` | Validates complete mark entries, compiles data, increments revision number, stores PDF file. |
| **Teacher Scope Resolution** | `TeacherAssignmentService` | Evaluates multi-assignment permissions across classes, sections, and subjects. |

---

## 27. Security / Mass Assignment Considerations

To safeguard domain integrity against injection and tampering:
1. **Guarded Strategy:** All models explicitly declare protected `$guarded = ['id']` or comprehensive `$fillable` arrays.
2. **Hidden Attributes:** Sensitive authentication data (`password_hash`, `remember_token`) is hidden via `$hidden` on `User`.
3. **Actor Immutability:** Foreign keys representing actors (`entered_by_user_id`, `updated_by_user_id`, `generated_by_user_id`) are populated strictly from authenticated session state (`Auth::id()`), never from mass-assignment payloads.
4. **Active User Enforcement:** Deactivated users (`is_active = false`) are prevented from executing actions by middleware.

---

## 28. Prohibited Patterns

The following patterns are strictly prohibited in the domain architecture:

- ❌ **Soft Deletes on Historical Records:** Never use `SoftDeletes` on `student_academic_records`, `marks`, or `attendance`. Historical records are preserved through status fields and explicit date ranges.
- ❌ **Pass-Through Repository Abstractions:** Never create empty interfaces like `UserRepositoryInterface` or `MarkRepository` that merely forward calls to Eloquent.
- ❌ **Cascading Foreign Key Deletes:** Never bypass database `ON DELETE RESTRICT` constraints with Eloquent cascading deletes.
- ❌ **UI-Only Authorization:** Never rely on hiding buttons in Blade templates; server-side Policies must authorize every mutation.
- ❌ **Converting Blank Marks to Zero:** Never allow casts or helpers to convert SQL `NULL` marks to numeric zero.
- ❌ **Overwriting Historical Report PDFs:** Never overwrite existing report card PDF files on disk or in the database.
- ❌ **Multi-Tenancy Injection:** Never add `tenant_id` or `school_id` columns to this single-school system.

---

## 29. Traceability Matrix

| Architectural Decision | Authoritative Requirement | Implementation Consequence |
|---|---|---|
| Model named `SchoolClass` | Approved 23-table schema (`classes`) | Resolves PHP reserved word collision while mapping to table `classes`. |
| Discrete `Mark` states | BRD V1.3 & Business Rules | Distinguishes `blank` (incomplete), `numeric` ($0$), and `absent` (`A`). |
| Multi-assignment scope | BRD V1.3 & Business Rules | Evaluates dynamic assignments; does not assume 1 teacher = 1 class. |
| Historical placement immutability | Finalized Handover Decisions | Transfers generate new `StudentAcademicRecord` rows without moving marks. |
| Frozen `subject_name_snapshot` | Approved 23-table schema | Historical reports render subject names as they were when the report was issued. |
| Separate display & calculation | BRD V1.3 & Finalized Rules | `ReportAssessmentSelection` controls display; calculation is managed separately. |
| Append-only report revisions | BRD V1.3 & Finalized Rules | `GeneratedReport` increments `revision_number`; historical files are never overwritten. |
| Append-only audit logs | Finalized Handover Decisions | `AuditLog` has no `updated_at`; deletion and modification are prohibited. |
| Coexistence with DB triggers | PostgreSQL validated schema | Standard models align with `trigger_set_updated_at()`; append-only models omit `updated_at`. |
| Single-school configuration | Finalized Handover Decisions | `SchoolSetting` is a singleton; multi-tenancy constructs are excluded. |

---

## 30. Phase 6.2 Decisions & Deferred Items

### 30.1 Approved Phase 6.2 Architectural Decisions
1. Confirmed mapping of all 23 PostgreSQL tables to Eloquent models.
2. Adopted `SchoolClass` for the `classes` table to resolve the PHP reserved word collision.
3. Defined custom casting strategy for `mark_value` to maintain strict distinction between blank, zero, and absent.
4. Established coexistence rules for Eloquent timestamps and PostgreSQL update triggers.
5. Classified models into four mutability tiers, establishing immutability for historical placements, audit logs, and generated reports.
6. Reaffirmed that complex business workflows belong in Application Services, not inside Eloquent models or observers.

### 30.2 Deferred Decisions (Phases 6.3 – 6.9)
- **Phase 6.3:** Exact class signatures, method parameters, and transaction boundaries for Application Services.
- **Phase 6.4:** Method-by-method definitions of Laravel Policies (`MarkPolicy`, `ReportPolicy`, `StudentPolicy`).
- **Phase 6.5:** Detailed Form Request validation rules and RESTful route parameters.
- **Phase 6.6:** Blade template component hierarchy, CSS styling tokens, and Vanilla JS event listeners.
- **Phase 6.7:** Final PDF rendering library selection (`dompdf` vs headless Chrome/Browsershot) and file storage drivers.
- **Phase 6.8:** Application-level unit, feature, and integration test suites.
- **Phase 6.9:** Production deployment configurations and environment templates.

---

## 31. Phase 6.2 Completion Checklist

- [x] BRD V1.3 inspected and verified.
- [x] Finalized business rules and handover decisions verified.
- [x] Approved 23-table database relationship design verified.
- [x] Current validated PostgreSQL schema inspected.
- [x] Approved ERD verified.
- [x] Phase 6.1 Technology Baseline verified.
- [x] All 23 tables mapped to Eloquent models.
- [x] All foreign key relationships and cardinalities documented.
- [x] PostgreSQL types and Eloquent casts defined.
- [x] PHP reserved word collision for `classes` resolved (`SchoolClass`).
- [x] Mark states (`blank`, `numeric`, `absent`) preserved.
- [x] Teacher multi-assignment authorization scope preserved.
- [x] Class Teacher broad scope vs Subject Teacher contextual scope preserved.
- [x] Dynamic term configuration preserved.
- [x] Contextual assessment applicability and per-subject maximum marks preserved.
- [x] Display selection vs calculation participation separation preserved.
- [x] Historical subject name snapshots preserved.
- [x] Student transfer placement and historical mark attachment preserved.
- [x] Elective lockout dependency on existing marks defined.
- [x] Attendance bounds and $0/0$ safe `"N/A"` handling defined.
- [x] Generated report revision immutability preserved.
- [x] Audit log immutability preserved.
- [x] Redundant repository layers excluded.
- [x] Soft deletes excluded.
- [x] Database schema changes strictly avoided.
- [x] No application or migration code was generated.
- [x] Documentation saved to `docs/architecture/PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md`.
