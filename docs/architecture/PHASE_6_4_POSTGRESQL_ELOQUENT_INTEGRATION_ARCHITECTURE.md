# PHASE 6.4 — POSTGRESQL + ELOQUENT INTEGRATION ARCHITECTURE
## Database Integration, Data Integrity & Persistence Boundary Blueprint

**Project:** School Examination Marks and Report Card Management System  
**Document Version:** 1.0  
**Phase:** 6.4 — PostgreSQL + Eloquent Integration Architecture  
**Status:** Approved & Authoritative Architectural Specification  

---

## 1. Purpose

This document defines the comprehensive integration blueprint between the validated 23-table PostgreSQL 18 relational database and Laravel's Eloquent ORM for the **School Examination Marks and Report Card Management System**.

While Phase 6.2 established the domain entities and relationship matrix, and Phase 6.3 established the physical project tree, **Phase 6.4 focuses exclusively on the persistence integration boundary**:

$$\text{Laravel Application Layer} \xrightarrow{\quad\text{Data Mapping & Types}\quad} \text{Eloquent ORM} \xrightarrow{\quad\text{PDO / SQL / Constraints}\quad} \text{PostgreSQL 18 Database}$$

This specification resolves:
- Exact database connection parameters, PDO attributes, and session timezone handling.
- Bidirectional data type mapping between PostgreSQL types (`NUMERIC`, `JSONB`, `TIMESTAMPTZ`, `IDENTITY`, Custom ENUMs) and PHP 8.4 types.
- Safeguards for critical business invariants (e.g. preserving `blank != 0.00`, discrete `absent` state, and preventing silent float conversions).
- A rigorous **Database vs. Application Responsibility Matrix** separating schema-level constraints from service-layer validations.
- Concurrency, race condition mitigation, and transactional boundaries across all multi-entity mutations.
- Historical data preservation, immutable PDF report revisions, and append-only audit logging.
- Future migration authoring and schema drift prevention protocols.
- Testing database strategy ensuring 100% engine parity (no SQLite compromises).

---

## 2. Relationship to Phases 6.1–6.3

The Phase 6 technical blueprint roadmap progresses in disciplined sequence:

```
Phase 6.1: Technology Baseline & Architecture Principles (Approved)
  - PHP 8.4+, Laravel 13, PostgreSQL 18.4, Blade, Vanilla JS, Plain CSS, Vite.
      ↓
Phase 6.2: Domain Model & Eloquent Architecture (Approved)
  - 23 Eloquent models mapped to closed 23-table schema, 85 relationships, mutability tiers.
      ↓
Phase 6.3: Laravel Project / Folder Structure (Approved & Corrected)
  - Physical directory tree, canonical services, custom casts, and view hierarchy.
      ↓
Phase 6.4: PostgreSQL + Eloquent Integration Architecture & Decision Ledger (Current Step)
  - Data types, PDO connection, constraint responsibilities, transactions, drift prevention.
      ↓
Phase 6.5: Authentication, Multi-Assignment Authorization & Security Blueprint
  - Session lifecycle, active user guards, teacher scope resolution algorithms.
      ↓
Phase 6.6: HTTP Layer, Routing, Form Requests & Controller Contracts
  - RESTful route definitions, thin controller contracts, explicit validation rules.
      ↓
Phase 6.7: Blade Component & UI Integration Architecture
  - Atomic UI design system, layout trees, mark-entry grid DOM, CSS variables.
      ↓
Phase 6.8: PDF Generation & File Storage Blueprint
  - Engine selection, template compilation, revision incrementing, secure streaming.
      ↓
Phase 6.9: Application Testing & Quality Gate Strategy
  - Unit, feature, authorization, and SQL regression test suites.
      ↓
Phase 6.10: Environment Configuration & Deployment Readiness
  - Environment templates, queue setup, production hardening guidelines.
```

Phase 6.4 solidifies the data tier integration before higher-level HTTP, authorization, and UI layers are detailed.

---

## 3. Authoritative Sources

All specifications in this blueprint strictly derive from the project's source-of-truth hierarchy:

1. **BRD V1.3** (`BRD/School_Examination_Marksheet_Requirements_v1_3.docx`)
2. **Finalized Business Rules & Handover Decisions**
3. **Approved 23-Table Relational Design** (`DB design/DB-table-definitons.txt`)
4. **Approved ERD** (`DB design/mermaid-diagram.png`)
5. **Validated PostgreSQL 18 Implementation & Documentation** (`database/Postgres/schema.sql`, `seed.sql`, `validation/run_all.sql`, `docs/POSTGRES_MIGRATION_IMPACT_ANALYSIS.md`)
6. **Technology Baseline** (`docs/architecture/PHASE_6_1_TECHNOLOGY_BASELINE.md`)
7. **Domain & Eloquent Architecture** (`docs/architecture/PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md`)
8. **Project Folder Structure** (`docs/architecture/PHASE_6_3_LARAVEL_PROJECT_FOLDER_STRUCTURE.md`)
9. **Project Decision Ledger** (`docs/decisions.md`, DEC-001 through DEC-030)

---

## 4. Integration Architecture Overview

The persistence tier integrates the application and database via dedicated, non-overlapping layers:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                             APPLICATION SERVICES LAYER                      │
│   (MarkEntryService, StudentPlacementService, ReportGenerationService, etc.)│
│   - Coordinates multi-table workflows                                       │
│   - Manages DB::transaction() boundaries                                    │
│   - Enforces business rules (mark <= max_marks, elective lockout)           │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
┌──────────────────────────────────────▼──────────────────────────────────────┐
│                               ELOQUENT ORM LAYER                            │
│   (App\Models, App\Casts\MarkValueCast, App\Enums)                          │
│   - Active Record persistence mapping for all 23 tables                     │
│   - Type casting: JSONB -> array, ENUM -> PHP Backed Enum                   │
│   - Protects SQL NULL from 0.00 coercion via MarkValueCast                  │
│   - Immutability guards on AuditLog, StudentAcademicRecord, GeneratedReport │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
┌──────────────────────────────────────▼──────────────────────────────────────┐
│                            LARAVEL DATABASE CONNECTION                      │
│   (config/database.php -> pgsql driver via ext-pdo_pgsql)                   │
│   - Connection pooling & persistent PDO settings                            │
│   - Strict mode, UTF-8 encoding, UTC timezone                               │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
┌──────────────────────────────────────▼──────────────────────────────────────┐
│                         POSTGRESQL 18 RELATIONAL ENGINE                     │
│   (Database: school_report_card)                                            │
│   - 23 base tables with BIGINT GENERATED BY DEFAULT AS IDENTITY             │
│   - 43 Foreign Keys (100% ON DELETE RESTRICT ON UPDATE RESTRICT)            │
│   - 7 Domain CHECK constraints (marks, attendance bounds, pass_mark)        │
│   - 2 Functional expression unique indexes with COALESCE                    │
│   - Triggers: BEFORE UPDATE trigger_set_updated_at()                        │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 5. PostgreSQL Connection Strategy

The Laravel PostgreSQL connection configuration in `config/database.php` must be tailored to the specific characteristics of PostgreSQL 18:

```php
'pgsql' => [
    'driver'         => 'pgsql',
    'url'            => env('DATABASE_URL'),
    'host'           => env('DB_HOST', '127.0.0.1'),
    'port'           => env('DB_PORT', '5432'),
    'database'       => env('DB_DATABASE', 'school_report_card'),
    'username'       => env('DB_USERNAME', 'postgres'),
    'password'       => env('DB_PASSWORD', ''),
    'charset'        => 'utf8',
    'prefix'         => '',
    'prefix_indexes' => true,
    'search_path'    => 'public',
    'sslmode'        => env('DB_SSLMODE', 'prefer'),
    'options'        => [
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT            => 5,
    ],
],
```

### Critical Connection Parameters:
1. **`PDO::ATTR_EMULATE_PREPARES => false`:** Guarantees that PostgreSQL natively prepares statements and correctly infers data types (preventing numbers from being transmitted as raw strings).
2. **`PDO::ATTR_STRINGIFY_FETCHES => false`:** Prevents PDO from converting PostgreSQL integer and boolean columns into PHP strings.
3. **`'search_path' => 'public'`:** Explicitly isolates all queries to the validated `public` schema.
4. **`'charset' => 'utf8'`:** Matches the UTF-8 database encoding, preventing collation errors.

---

## 6. Eloquent Integration Principles

Eloquent is an Active Record ORM. To ensure it integrates cleanly with PostgreSQL 18 without violating established business rules, the following principles are enforced:

1. **Strict Active Record Compliance:** Eloquent models map directly to their underlying PostgreSQL tables. No synthetic columns or extra tables may be assumed.
2. **Schema-First Primacy:** When an Eloquent convention conflicts with the validated schema, Eloquent **must adapt to the schema**, never the reverse.
3. **Prevention of Implicit Mutations:** Eloquent's `$fillable` or `$guarded` properties must be declared explicitly on every model. Mass assignment must never be allowed on foreign keys representing security actors (`entered_by_user_id`, `generated_by_user_id`).
4. **No Repository Wrappers:** Services interact directly with Eloquent models. Artificial repository classes are strictly prohibited (DEC-011).
5. **Strict Model Integrity:** Models should activate `Model::shouldBeStrict()` in development (`AppServiceProvider`) to prevent lazy loading, silent attribute discarding, and accessing non-existent attributes.

---

## 7. PostgreSQL Data Type Mapping

The table below provides the authoritative type mapping between PostgreSQL 18 column definitions and PHP 8.4 / Eloquent attributes.

| PostgreSQL 18 Column Type | PHP 8.4 Primitive | Eloquent Cast Definition | Critical Handling & Boundary Rules |
|---|---|---|---|
| `BIGINT GENERATED BY DEFAULT AS IDENTITY` | `int` | `'integer'` | Auto-incrementing 64-bit integer. Eloquent `$keyType = 'int'`. |
| `BIGINT` (Foreign Keys) | `int` / `null` | `'integer'` | Represented as nullable or non-nullable 64-bit integer. |
| `VARCHAR(n)` / `TEXT` | `string` / `null` | `'string'` | UTF-8 encoded string. Whitespace trimmed before save. |
| `BOOLEAN` | `bool` | `'boolean'` | Maps PostgreSQL `'t'`/`'f'` to native PHP `true`/`false`. |
| `DATE` | `CarbonImmutable` / `null` | `'date:Y-m-d'` | Carbon instance formatted as `YYYY-MM-DD`. |
| `TIMESTAMPTZ` | `CarbonImmutable` | `'immutable_datetime'` | Persisted in UTC; read as immutable datetime object. |
| `JSONB` | `array` / `null` | `'array'` | Automatically serialized/deserialized as associative array. |
| `NUMERIC(6,2)` (`maximum_marks`) | `string` | `'decimal:2'` | Preserved to 2 decimal places. BCMath used for calculations. |
| `NUMERIC(6,2)` (`mark_value`) | `?float` / `null` | `App\Casts\MarkValueCast` | **CRITICAL:** Custom cast prevents `null` from coercing to `0.00`. |
| Custom PostgreSQL ENUM | Backed Enum | `App\Enums\{EnumName}` | Cast directly to PHP 8.4 String-Backed Enums. |

---

## 8. Enum Integration

The PostgreSQL schema establishes 8 distinct custom ENUM types. Eloquent models map these directly to PHP 8.4 string-backed enums:

```
PostgreSQL Schema ENUM                       PHP 8.4 Backed Enum
─────────────────────────────────────────────────────────────────────────────
academic_year_status_enum      ─────────►   App\Enums\AcademicYearStatus
subject_category_enum          ─────────►   App\Enums\SubjectCategory
student_placement_status_enum  ─────────►   App\Enums\StudentPlacementStatus
assessment_status_enum         ─────────►   App\Enums\AssessmentStatus
teacher_assignment_type_enum   ─────────►   App\Enums\TeacherAssignmentType
mark_result_status_enum        ─────────►   App\Enums\MarkResultStatus
calculation_method_enum        ─────────►   App\Enums\CalculationMethod
report_type_enum               ─────────►   App\Enums\ReportType
```

### Architectural Enum Rules:
1. **Enum Exclusivity:** No alternative or extra enum cases (such as `EX`, `WH`, `NA`) may be declared in PHP enums. The PHP enums must contain **only** the exact string literals defined in `database/Postgres/schema.sql`.
2. **Database Rejection Safeguard:** If an invalid enum string is submitted, Eloquent casting throws a `ValueError` during assignment, and PostgreSQL rejects invalid literals with `SQLSTATE 22P02` (`invalid_text_representation`).
3. **Form Request Validation:** Form Requests validate enum inputs using `Rule::enum(EnumClass::class)`.

---

## 9. Numeric / Decimal Handling

Marks and percentages represent legal academic records. Inadvertent IEEE 754 floating-point inaccuracies (e.g. `0.1 + 0.2 = 0.30000000000000004`) cannot be tolerated in academic evaluation.

### Numeric Rules:
1. **Precision in Persistence:** PostgreSQL columns `marks.mark_value`, `assessment_applicability.maximum_marks`, and `school_settings.pass_mark` are defined as `NUMERIC(6,2)`. Eloquent models interact with these fields using exact 2-decimal representation.
2. **Calculation Math:** Inside `CalculationService`, calculations are conducted using standard PHP high-precision math, and final percentages are explicitly rounded to 2 decimal places using `round($percentage, 2, PHP_ROUND_HALF_UP)` or `bcdiv()`.
3. **Database Rounding Alignment:** PostgreSQL's `ROUND(numeric, 2)` implements round-half-away-from-zero, which matches PHP's `PHP_ROUND_HALF_UP`.

---

## 10. NULL Semantics

In relational database theory and PostgreSQL ANSI compliance, `NULL` represents the **complete absence of a value**, not zero, false, or an empty string.

### Nullable Semantic Fields in Schema:
- `class_subjects.section_id`:
  - `section_id IS NULL` $\rightarrow$ The subject applies **class-wide** to all sections in that class.
  - `section_id IS NOT NULL` $\rightarrow$ The subject applies **specifically** to that individual section.
- `teacher_assignments.subject_id`:
  - `subject_id IS NULL` $\rightarrow$ The assignment is a **Class Teacher** assignment (conferring authority over all subjects in that section).
  - `subject_id IS NOT NULL` $\rightarrow$ The assignment is a **Subject Teacher** assignment (restricted to that subject).
- `assessments.term_id`:
  - `term_id IS NULL` $\rightarrow$ The assessment is a full-year or final milestone not bound to a single term.
  - `term_id IS NOT NULL` $\rightarrow$ The assessment belongs to that specific academic term.
- `marks.mark_value`:
  - `mark_value IS NULL` $\rightarrow$ Allowed **only** when `result_status` is `'blank'` (unentered) or `'absent'` (absent).

**Integration Rule:** Eloquent must never replace `NULL` with a sentinel integer (like `0`) in foreign key queries or model attributes.

---

## 11. Mark State Preservation Architecture

Preserving the distinction between unentered marks, assessed zeroes, and absences is a mandatory business requirement (DEC-013, DEC-014).

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           MARK RESULT TRIPLE STATES                         │
├─────────────────┬──────────────┬─────────────┬──────────────┬───────────────┤
│ Business State  │ result_status│ mark_value  │ Report Card  │ Calculation   │
│                 │ (Enum)       │ (Postgres)  │ Presentation │ Participation │
├─────────────────┼──────────────┼─────────────┼──────────────┼───────────────┤
│ 1. Blank        │ 'blank'      │ NULL        │ '-' / Empty  │ INCOMPLETE    │
│    (Unentered)  │              │             │              │ (Blocks PDF)  │
├─────────────────┼──────────────┼─────────────┼──────────────┼───────────────┤
│ 2. Numeric Zero │ 'numeric'    │ 0.00        │ '0.00'       │ Participates  │
│    (Evaluated)  │              │             │              │ (Score = 0.00)│
├─────────────────┼──────────────┼─────────────┼──────────────┼───────────────┤
│ 3. Absent       │ 'absent'     │ NULL        │ 'A'          │ Participates  │
│    (Attended: 0)│              │             │              │ (Score = 0.00)│
└─────────────────┴──────────────┴─────────────┴──────────────┴───────────────┘
```

### Enforcement Layers:
1. **PostgreSQL Constraint:** Table `marks` enforces `chk_marks_result_consistency`:
   ```sql
   CHECK (
       (result_status = 'numeric' AND mark_value IS NOT NULL AND mark_value >= 0)
       OR
       (result_status = 'blank' AND mark_value IS NULL)
       OR
       (result_status = 'absent' AND mark_value IS NULL)
   )
   ```
2. **Eloquent Custom Cast (`MarkValueCast`):**
   ```php
   namespace App\Casts;

   use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
   use Illuminate\Database\Eloquent\Model;

   class MarkValueCast implements CastsAttributes
   {
       public function get(Model $model, string $key, mixed $value, array $attributes): ?float
       {
           if ($value === null) {
               return null;
           }
           return (float) $value;
       }

       public function set(Model $model, string $key, mixed $value, array $attributes): ?string
       {
           if ($value === null || $value === '') {
               return null;
           }
           return number_format((float) $value, 2, '.', '');
       }
   }
   ```
3. **Value Object (`MarkResult`):** Encapsulates the evaluation rules:
   ```php
   $result = MarkResult::fromModel($mark);
   $result->isComplete();     // false if blank, true if numeric or absent
   $result->score();          // null if blank, 0.0 if absent, numeric if numeric
   $result->displayString();  // "" if blank, "A" if absent, "85.50" if numeric
   ```

---

## 12. Primary Keys and Timestamps

### 12.1 Primary Keys
All 23 tables utilize PostgreSQL Identity columns:
```sql
id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY
```
In Eloquent:
- `$primaryKey = 'id';`
- `$keyType = 'int';`
- `$incrementing = true;`
When Eloquent inserts a new record, PDO automatically executes `INSERT ... RETURNING id`, populating the model's `$model->id` attribute without requiring secondary queries.

### 12.2 Coexistence with PostgreSQL `BEFORE UPDATE` Triggers
21 tables have the database trigger:
```sql
CREATE TRIGGER trg_{table}_updated_at
    BEFORE UPDATE ON {table}
    FOR EACH ROW
    EXECUTE FUNCTION trigger_set_updated_at();
```
- **Standard Tables (21 tables):** Eloquent has `public $timestamps = true;`. When Eloquent saves an updated model, it sets `updated_at = Carbon::now()`. The PostgreSQL trigger executes `NEW.updated_at = clock_timestamp()`. Because both values represent the current UTC timestamp, there is zero contradiction or race condition.
- **Append-Only Tables (2 tables):**
  - `generated_reports`: Has `generated_at` but no `updated_at`. Eloquent model specifies: `public $timestamps = false;` and casts `'generated_at' => 'immutable_datetime'`.
  - `audit_logs`: Has `created_at` but no `updated_at`. Eloquent model specifies: `const UPDATED_AT = null;` and casts `'created_at' => 'immutable_datetime'`.

---

## 13. Relationships and Foreign Keys

All 43 foreign keys in the validated PostgreSQL schema are strictly configured with:
```sql
ON DELETE RESTRICT ON UPDATE RESTRICT
```

### Relational Integration Rules:
1. **No Cascading Deletes in Eloquent:** Eloquent models must **never** define cascading deletes (`deleting` event handlers deleting child records). If an attempt is made to delete a parent record with existing children (e.g. attempting to delete a `SchoolClass` with attached `Section` records), PostgreSQL immediately rejects the query with `SQLSTATE 23503` (`foreign_key_violation`).
2. **Restricted Deletion Handling:** Application services and controllers catch `QueryException` with SQLSTATE `23503` and convert it into a clear, user-friendly error message: *"This record cannot be deleted because dependent academic records exist."*
3. **Eager Loading Constraints:** Foreign keys are explicitly indexed in PostgreSQL (e.g. `idx_marks_student_academic_record_id`). Eloquent eager loading (`with(...)`) maps cleanly to indexed `IN (...)` queries.

---

## 14. Database Constraints vs. Application Rules Responsibility Matrix

This matrix establishes the boundary between schema-level enforcement and application-level logic:

| Functional Rule / Constraint | PostgreSQL Schema Enforcement | Eloquent / Application Service Enforcement | UI / JavaScript Role |
|---|---|---|---|
| **Primary Key Uniqueness** | `PRIMARY KEY` (Identity index) | Handled automatically by database | None |
| **Unique Usernames / Roles** | `CREATE UNIQUE INDEX ... (LOWER(name))` | Form Request `Rule::unique()` | Immediate validation feedback |
| **Foreign Key Integrity** | `REFERENCES ... ON DELETE RESTRICT` | Model `belongsTo()` / `hasMany()` | Dropdown options filtered by parent |
| **Attendance Bounds** | `days_attended <= total_working_days` | Form Request validates `lte:total_working_days` | Inline bounds alert |
| **Attendance 0/0 Handling** | None (DB allows `0/0`) | `CalculationService` renders `"N/A"` | Renders `"N/A"` dynamically |
| **Discrete Mark States** | `chk_marks_result_consistency` | `MarkValueCast` & `BatchSaveMarksRequest` | Input formatting (`0.00`, `A`, empty) |
| **Mark <= Max Marks** | None (Max marks is cross-table) | `MarkEntryService` validates against applicability | Cell highlight if value > max |
| **Section-Nullable Uniqueness** | `uk_class_subjects_config` with `COALESCE` | Model queries scope section-specific configs | Dynamic section dropdown |
| **Report Revision Identity** | `uk_gr_revision_identity` with `COALESCE` | `ReportGenerationService` calculates `MAX + 1` | Revision history table |
| **Elective Lockout on Marks** | None (Cross-table dependency) | `StudentSubjectService` checks `marks()->exists()` | Disables deallocate button |
| **Teacher Multi-Assignment** | `chk_ta_assignment_subject_consistency` | `TeacherAssignmentService` resolves composite scopes | Shows authorized grids only |
| **Closed Academic Year** | `status = 'closed'` enum | `PreventRequestsWhenYearClosed` + Policies | Disables edit controls for teachers |
| **Audit Log Immutability** | No `updated_at` column | `AuditLog` model rejects update/delete calls | Read-only inspection table |
| **Report PDF Immutability** | `uk_gr_revision_identity` | `ReportGenerationService` never overwrites files | Displays historical download links |

---

## 15. Transaction Boundaries

Database transactions ensure that multi-table state transitions are strictly atomic. If an error occurs midway, the entire transaction is rolled back:

$$\text{DB::beginTransaction()} \xrightarrow{\quad\text{Mutations}\quad} \text{All Operations Succeed} \xrightarrow{\quad\text{DB::commit()}\quad} \text{PostgreSQL Permanent State}$$
$$\text{DB::beginTransaction()} \xrightarrow{\quad\text{Mutations}\quad} \text{Any Exception Thrown} \xrightarrow{\quad\text{DB::rollBack()}\quad} \text{Zero State Mutation}$$

### Mandatory Transaction Boundaries in Application Services:
1. **Mark Batch Save & Audit Log:**
   - Service: `MarkEntryService::saveMarks(array $marks, int $userId)`
   - Operations: Validate applicability $\rightarrow$ Upsert marks into `marks` $\rightarrow$ Insert audit trail into `audit_logs` $\rightarrow$ Commit.
2. **Internal Student Transfer:**
   - Service: `StudentPlacementService::transferStudent(int $recordId, int $newSectionId, int $newRollNo)`
   - Operations: Verify new roll number is unique in section $\rightarrow$ Update old `student_academic_records` (`status = 'internal_transfer'`, `effective_to = now()`) $\rightarrow$ Insert new `student_academic_records` (`status = 'active'`, `effective_from = now()`) $\rightarrow$ Create default subject allocations $\rightarrow$ Write audit log $\rightarrow$ Commit.
3. **Report Card Revision Generation:**
   - Service: `ReportGenerationService::generateStudentReport(int $recordId, string $reportType, ?int $termId, ?int $assessmentId)`
   - Operations: Verify mark completeness $\rightarrow$ Compile calculation data $\rightarrow$ Query `MAX(revision_number) + 1` $\rightarrow$ Generate PDF to private storage $\rightarrow$ Insert `generated_reports` row $\rightarrow$ Write audit log $\rightarrow$ Commit.
4. **Curriculum Subject Configuration:**
   - Service: `AcademicConfigurationService::configureClassSubject(...)`
   - Operations: Insert `class_subjects` with snapshot $\rightarrow$ Create `student_subject_allocations` for active students in class/section $\rightarrow$ Write audit log $\rightarrow$ Commit.

---

## 16. Concurrency and Race Condition Considerations

In a multi-user academic environment (multiple teachers entering marks, administrators generating reports), concurrent operations present concurrency risks:

### 16.1 Simultaneous Mark Edits
- **Risk:** Teacher A and Teacher B edit the same student's mark simultaneously. Teacher B's save accidentally overwrites Teacher A's update.
- **PostgreSQL / Eloquent Solution:** The system implements **latest authorized save wins** with full audit tracking. When saving marks, the update updates `updated_by_user_id` and `updated_at`. The transaction logs an audit row containing the exact previous value (`before_data`) and new value (`after_data`), preserving complete accountability.

### 16.2 Simultaneous Report Revision Allocation
- **Risk:** Two staff members trigger report generation for the same student and term at the exact same second. Both read `revision_number = 1`, and both attempt to insert `revision_number = 2`.
- **PostgreSQL / Eloquent Solution:**
  1. PostgreSQL's functional unique index `uk_gr_revision_identity` immediately rejects the second insert with a unique constraint violation (`SQLSTATE 23505`).
  2. `ReportGenerationService` catches `QueryException` with code `23505` and retries the generation with the next incremented revision number.

### 16.3 Roll Number Collisions During Student Transfers
- **Risk:** Two staff members transfer two different students into Class 9-A with roll number 15 simultaneously.
- **PostgreSQL / Eloquent Solution:** Table `student_academic_records` enforces `uk_sar_year_class_section_roll`. PostgreSQL immediately rejects the second transaction.

---

## 17. Historical Data Integrity Integration

Historical data integrity is a non-negotiable architectural concern:

1. **Student Academic Placement:**
   - Placements in `student_academic_records` represent physical classroom enrollments.
   - When transfers occur, the old placement row is **never updated destructively**. Its `status` becomes `'internal_transfer'` and `effective_to` is populated.
   - Historical marks remain tied to the historical placement ID. They are **never migrated** to the new placement ID.
2. **Subject Name Snapshots:**
   - `class_subjects.subject_name_snapshot` is populated during curriculum setup.
   - When generating historical report cards, Eloquent loads `classSubject.subject_name_snapshot`. The query **never** falls back to `subjects.name`.
3. **Historical Roll Numbers:**
   - Roll numbers belong to `student_academic_records`. A student may have roll number 14 in Term 1 and roll number 22 in Term 2 (after a section transfer). The historical mark sheet for Term 1 reflects roll number 14.

---

## 18. Assessment Applicability Integration

The assessment schema separates assessment instances from class-subject applicability:

```
[assessments] (e.g. "Term 1 Exam", term_id: 1)
      │
      ▼ (1:N)
[assessment_applicability] (maximum_marks: 100.00)
      │
      ▲ (N:1)
[class_subjects] (Class 10 - Mathematics)
```

### Integration Rules:
1. **Maximum Marks Ownership:** `maximum_marks` is an attribute of `assessment_applicability`, **not** `assessments`. Math may have max marks 100.00, while Computer Science has max marks 50.00 under the identical assessment milestone.
2. **Separation of Display and Calculation:**
   - `report_assessment_selections` governs which assessments are displayed on a report card.
   - `CalculationService` independently determines which assessments contribute to percentages (currently, only assessments of type `'Term Exam'` participate). Being displayed does **not** cause an assessment to participate in calculation.

---

## 19. Migration Strategy & Schema Drift Prevention

### 19.1 Migration Role and Baseline Relationship
- The validated SQL scripts in `database/Postgres/` (`schema.sql`, `seed.sql`, `validation/run_all.sql`) represent the **permanent, authoritative database baseline**.
- When application development begins in Phase 7, Laravel migrations will be created in `database/migrations/` to reproduce this exact 23-table schema for local developer setup and automated testing (`RefreshDatabase`).
- **No Migrations Created in Phase 6.4:** In strict accordance with planning rules, no migration files are written during Phase 6.

### 19.2 Handling PostgreSQL-Specific Constructs in Migrations
Standard Laravel migration helpers (`$table->id()`, `$table->string()`) do not natively express all PostgreSQL-specific features. Future migrations must utilize raw SQL expressions where required:

1. **Custom ENUM Types:** Created via `DB::statement("CREATE TYPE ... AS ENUM (...)");` before table creation.
2. **Functional Expression Indexes:**
   ```php
   DB::statement('CREATE UNIQUE INDEX uk_class_subjects_config ON class_subjects (academic_year_id, class_id, (COALESCE(section_id, 0)), subject_id);');
   DB::statement('CREATE UNIQUE INDEX uk_gr_revision_identity ON generated_reports (student_academic_record_id, report_type, (COALESCE(term_id, 0)), (COALESCE(assessment_id, 0)), revision_number);');
   ```
3. **Domain CHECK Constraints:**
   ```php
   DB::statement('ALTER TABLE marks ADD CONSTRAINT chk_marks_result_consistency CHECK (...);');
   DB::statement('ALTER TABLE attendance ADD CONSTRAINT chk_attendance_days_within_total CHECK (days_attended <= total_working_days);');
   ```
4. **Trigger Setup:**
   ```php
   DB::statement('CREATE OR REPLACE FUNCTION trigger_set_updated_at() RETURNS TRIGGER AS ...');
   DB::statement('CREATE TRIGGER trg_roles_updated_at BEFORE UPDATE ON roles FOR EACH ROW EXECUTE FUNCTION trigger_set_updated_at();');
   ```

### 19.3 Schema Drift Prevention Protocol
To guarantee that Laravel migrations never drift from the validated PostgreSQL baseline:
1. **Automated Schema Dump Comparison:** CI/CD test runners run `pg_dump --schema-only` against a database built by Laravel migrations and compare the diff against `database/Postgres/schema.sql`.
2. **Validation Suite Re-Execution:** The complete 54-assertion PostgreSQL validation script (`database/Postgres/validation/run_all.sql`) must run and pass 100% against any freshly migrated database before code is approved.

---

## 20. Query and Eager Loading Strategy

To eliminate $N+1$ query degradation on dense academic screens, Eloquent relationship queries must adhere to standardized eager-loading profiles:

```php
// Standard Profile: Mark Entry Grid
$students = StudentAcademicRecord::query()
    ->where('academic_year_id', $yearId)
    ->where('class_id', $classId)
    ->where('section_id', $sectionId)
    ->where('status', StudentPlacementStatus::Active)
    ->with([
        'student:id,student_name',
        'subjectAllocations' => function ($query) use ($classSubjectId) {
            $query->where('class_subject_id', $classSubjectId)
                  ->where('is_active', true);
        },
        'marks' => function ($query) use ($applicabilityId) {
            $query->where('assessment_applicability_id', $applicabilityId);
        }
    ])
    ->orderBy('roll_number')
    ->get();
```

### Prohibited Query Practices:
- ❌ **Querying Models Inside Blade:** Calling `{{ $student->marks()->where(...)->first() }}` inside a Blade `@foreach` loop is strictly prohibited.
- ❌ **Unindexed Sorting:** Sorting by unindexed dynamic calculations in SQL. All primary ordering uses indexed columns (`roll_number`, `sequence_no`, `created_at`).
- ❌ **Lazy Loading in Production:** Strict mode (`Model::preventLazyLoading()`) prevents hidden queries.

---

## 21. Testing Database Strategy

A critical hazard in Laravel applications is testing against an in-memory SQLite database while deploying to PostgreSQL. **This practice is strictly prohibited for this project.**

### Why SQLite is Prohibited for Testing:
1. SQLite does not support custom PostgreSQL ENUM types (`CREATE TYPE ... AS ENUM`).
2. SQLite does not support PostgreSQL functional expression unique indexes (`COALESCE(section_id, 0)`).
3. SQLite does not support PostgreSQL JSONB operators or containment checks (`@>`).
4. SQLite CHECK constraints have different coercion semantics than PostgreSQL.

### Mandatory Testing Configuration:
- Automated tests (`phpunit.xml`) must execute against a dedicated PostgreSQL testing database:
  ```xml
  <env name="DB_CONNECTION" value="pgsql"/>
  <env name="DB_DATABASE" value="school_report_card_test"/>
  ```
- The test suite uses `Illuminate\Foundation\Testing\RefreshDatabase` on PostgreSQL, ensuring 100% semantic fidelity with production.

---

## 22. PostgreSQL / Eloquent Implementation Hazards & Safeguards

| Hazard / Technical Risk | Manifestation | Required Architectural Safeguard |
|---|---|---|
| **Silent Float Conversion** | Casting `mark_value` to `float` turns SQL `NULL` into `0.0`. | Custom `MarkValueCast` preserves `null` vs `0.00`. |
| **Collation Case-Sensitivity** | PostgreSQL `UNIQUE (username)` allows duplicate `"admin"` and `"Admin"`. | PostgreSQL schema enforces `CREATE UNIQUE INDEX ... (LOWER(username))`. Form Requests use `LOWER()`. |
| **COALESCE Nullable Uniqueness** | Standard Laravel unique validation ignores `COALESCE` functional index. | Form Requests use custom validation rule querying the exact functional index expression. |
| **Timestamp Timezone Drift** | Storing localized timestamps causes report discrepancies across timezones. | All dates persisted in UTC via `TIMESTAMPTZ` and cast to `CarbonImmutable`. |
| **Foreign Key Restrict Failures** | UI allows deleting a class, resulting in an unhandled raw PDO exception. | Service layer catches `QueryException` (`SQLSTATE 23503`) and presents a friendly error message. |
| **Missing Transaction on Revisions** | Report PDF written to disk, but database insertion fails, leaving orphaned files. | PDF written to temporary path; moved to final path inside database transaction commit hook. |

---

## 23. Prohibited Approaches

The following architectural patterns are strictly forbidden:

- ❌ **No Schema Modifications:** Do not add convenience columns, soft delete columns, or rename tables to satisfy Laravel conventions.
- ❌ **No Artificial Repository Interfaces:** Do not create interfaces that merely wrap Eloquent method calls.
- ❌ **No SQLite Testing:** Never run automated tests against SQLite.
- ❌ **No Whole-Class Batch PDF Generation:** BRD V1.3 defines single-student report card generation. Batch generation is strictly out of scope.
- ❌ **No Blanket Closed-Year Write Blocks:** Administrators and Office Staff retain authorized post-closure correction rights.
- ❌ **No In-Memory Calculation of Grades/Averages in Models:** Calculations belong exclusively in `CalculationService`.

---

## 24. Traceability Matrix

| Architectural Specification | Authoritative Requirement | System Impact & Implementation Consequence |
|---|---|---|
| Discrete `blank` vs `0.00` vs `A` | BRD V1.3 (BR-010 to BR-012) | `MarkValueCast` ensures SQL `NULL` is preserved in PHP; database CHECK enforces consistency. |
| Dynamic Assessment Max Marks | BRD V1.3 (BR-015) | Max marks stored on `assessment_applicability`; `MarkEntryService` validates bounds. |
| Separation of Display & Calculation | BRD V1.3 (BR-055, CL-004) | `ReportAssessmentSelection` controls display; `CalculationService` enforces Term Exam rule. |
| Immutable Historical Placements | BRD V1.3 (BR-020 to BR-022) | Transfers create new `StudentAcademicRecord` rows; historical marks remain attached to prior row. |
| Frozen Subject Name Snapshots | Approved Schema (`class_subjects`) | Historical reports read `subject_name_snapshot`, protecting cards against catalog renames. |
| Dual Calculation Formulas | BRD V1.3 (BR-050, BR-051) | `CalculationService` implements both Method 1 and Method 2 to 2 decimal places. |
| Append-Only Report Revisions | BRD V1.3 (BR-070, BR-071) | Regenerating increments `revision_number`; historical PDF files are never overwritten. |
| Append-Only Audit Logging | BRD V1.3 (BR-080) | `AuditLog` has no `updated_at`; deletion and modification are prohibited. |
| Functional Uniqueness with COALESCE | Validated Schema (`schema.sql`) | PostgreSQL expression unique indexes prevent duplicates on nullable foreign keys. |
| Dedicated PostgreSQL Test Database | Technical Baseline (Phase 6.1) | Eliminates SQLite dialect mismatch; guarantees test parity with production engine. |

---

## 25. Decision Ledger Integration

All major architectural decisions governing this integration specification are recorded in the permanent project ledger (`docs/decisions.md`):

- **DEC-007:** Closed 23-Table PostgreSQL Schema
- **DEC-008:** Eloquent as Exclusive ORM
- **DEC-013:** Discrete Three-State Mark System
- **DEC-014:** `MarkValueCast` and `MarkResult` Value Object
- **DEC-015:** Historical Student Academic Placement Immutability
- **DEC-016:** Immutable Subject Name Snapshot
- **DEC-020:** Dual Supported Calculation Methods
- **DEC-021:** Term Exam Exclusive Term Percentage Rule
- **DEC-023:** Private, Secure Storage for Generated Reports
- **DEC-024:** Append-Only Immutable Report Revisions
- **DEC-025:** Append-Only Immutable Audit Logging
- **DEC-026:** Exclusion of Batch PDF Generation
- **DEC-027:** Role-Aware Closed Academic Year Lifecycle
- **DEC-028:** Attendance Bounds and Safe 0/0 Handling
- **DEC-029:** PostgreSQL Functional Expression Indexes for Nullable Uniqueness
- **DEC-030:** Strict Downward Dependency Flow

---

## 26. Deferred Decisions (Phases 6.5 – 6.10)

The following implementation-specific decisions are deferred to subsequent blueprint phases:
- **Phase 6.5:** User authentication session timeout, teacher assignment scope resolution queries, and policy method arrays.
- **Phase 6.6:** HTTP controller action parameters, Form Request validation rule syntax, and API response headers.
- **Phase 6.7:** Blade layout HTML templates, CSS token palette values, and Vanilla JS mark-entry event listeners.
- **Phase 6.8:** Exact PDF rendering library (`dompdf` vs `browsershot`) and PDF streaming controller implementation.
- **Phase 6.9:** Concrete PHPUnit test methods, test data factories, and assertion matrices.
- **Phase 6.10:** Production PostgreSQL connection pool sizes, deployment scripts, and Redis session configuration.

---

## 27. Phase 6.4 Completion Checklist

- [x] BRD V1.3 inspected and verified.
- [x] Finalized business rules and handover decisions verified.
- [x] Approved 23-table relational design verified.
- [x] Current PostgreSQL 18 implementation and test evidence inspected.
- [x] Phases 6.1, 6.2, and 6.3 blueprints verified.
- [x] PostgreSQL connection parameters and PDO configuration defined.
- [x] PostgreSQL data types mapped to PHP 8.4 and Eloquent casts.
- [x] 8 PostgreSQL custom ENUMs mapped to PHP Backed Enums.
- [x] High-precision decimal calculation and rounding rules established.
- [x] NULL semantics for nullable foreign keys defined.
- [x] Discrete mark state preservation (`blank`, `numeric`, `absent`) established.
- [x] `MarkValueCast` and `MarkResult` casting integration detailed.
- [x] Identity primary keys and `BEFORE UPDATE` trigger coexistence resolved.
- [x] 43 `ON DELETE RESTRICT` foreign keys mapped to Eloquent relationships.
- [x] Database vs Application Responsibility Matrix established.
- [x] Transaction boundaries for all multi-table mutations defined.
- [x] Concurrency and race condition mitigation documented.
- [x] Historical placement, subject snapshot, and elective lockout preserved.
- [x] Attendance bounds and $0/0$ safe `"N/A"` handling documented.
- [x] Migration translation and schema drift prevention protocols defined.
- [x] Standardized eager-loading profiles defined.
- [x] Mandatory PostgreSQL test database strategy established (no SQLite).
- [x] Project decision ledger (`docs/decisions.md`) created and backfilled with DEC-001 through DEC-030.
- [x] Phase 6.3 corrections applied (roadmap aligned, batch generation removed, storage immutability clarified).
- [x] No application or migration code was generated.
- [x] Documentation saved to `docs/architecture/PHASE_6_4_POSTGRESQL_ELOQUENT_INTEGRATION_ARCHITECTURE.md`.
