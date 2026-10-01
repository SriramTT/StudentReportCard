# School Examination Marks and Report Card Management System
## Project Decision Ledger

**Document Version:** 1.0  
**Established In:** Phase 6.4 (September 16, 2026)  
**Status:** Permanent, Living Project Architecture Ledger  

---

## 1. Ledger Purpose & Governance Rules

This document is the authoritative, immutable decision ledger for the **School Examination Marks and Report Card Management System**. It records all high-impact architectural, domain, engineering, and technical decisions made across the system lifecycle.

### Governance Rules:
1. **Historical Immutability:** Decisions recorded in this ledger are permanent historical facts. A decision record is **never deleted or silently modified**.
2. **Superseding Protocol:** If a subsequent phase or requirement genuinely alters an established decision:
   - The original decision status is updated to `Superseded`.
   - A new Decision ID is created documenting the updated decision and rationale.
   - Cross-references (`Superseded By` on the old decision, `Supersedes` on the new decision) are explicitly established.
3. **Requirement vs. Decision Distinction:**
   - **Requirement:** *What* the system must accomplish (originates from BRD V1.3, approved business rules).
   - **Decision:** *How* we architect, design, and structure the system to fulfill requirements.
   - Implementation decisions must never contradict or rewrite business requirements.
4. **Traceability:** Every decision must reference its authoritative sources and document the impact on downstream phases.

---

## 2. Decision Summary Index

| Decision ID | Title | Status | Phase | Primary Impact Area |
|---|---|---|---|---|
| [DEC-001](#dec-001--core-application-technology-stack) | Core Application Technology Stack | Approved | 6.1 | Backend runtime, framework, database |
| [DEC-002](#dec-002--server-rendered-blade-architecture) | Server-Rendered Blade Architecture | Approved | 6.1 | Presentation architecture |
| [DEC-003](#dec-003--vanilla-javascript-micro-interactions) | Vanilla JavaScript Micro-Interactions | Approved | 6.1 | Client-side scripting |
| [DEC-004](#dec-004--tailored-plain-css-design-system) | Tailored Plain CSS Design System | Approved | 6.1 | Styling & UI design |
| [DEC-005](#dec-005--vite-as-offline-build-tooling-only) | Vite as Offline Build Tooling Only | Approved | 6.1 | Asset pipeline |
| [DEC-006](#dec-006--single-school-architecture) | Single-School Architecture | Approved | 6.1 | Domain boundary |
| [DEC-007](#dec-007--closed-23-table-postgresql-schema) | Closed 23-Table PostgreSQL Schema | Approved | 6.1, 6.2 | Relational schema |
| [DEC-008](#dec-008--eloquent-as-exclusive-orm) | Eloquent as Exclusive ORM | Approved | 6.1, 6.2 | Data persistence |
| [DEC-009](#dec-009--flat-23-model-directory-structure) | Flat 23-Model Directory Structure | Approved | 6.2, 6.3 | Model organization |
| [DEC-010](#dec-010--schoolclass-model-naming) | `SchoolClass` Model Naming for `classes` Table | Approved | 6.2 | PHP syntax compatibility |
| [DEC-011](#dec-011--strict-exclusion-of-artificial-repositories) | Strict Exclusion of Artificial Repositories | Approved | 6.1, 6.2 | Architecture layering |
| [DEC-012](#dec-012--application-services-for-domain-workflows) | Application Services for Domain Workflows | Approved | 6.1, 6.3 | Business logic organization |
| [DEC-013](#dec-013--discrete-three-state-mark-system) | Discrete Three-State Mark System (`blank`, `numeric`, `absent`) | Approved | 6.1, 6.2 | Academic grading logic |
| [DEC-014](#dec-014--markvaluecast-and-markresult-value-object) | `MarkValueCast` and `MarkResult` Value Object | Approved | 6.2, 6.3 | Null vs 0.00 casting integrity |
| [DEC-015](#dec-015--historical-student-academic-placement-immutability) | Historical Student Academic Placement Immutability | Approved | 6.2, 6.3 | Enrollment & transfer history |
| [DEC-016](#dec-016--immutable-subject-name-snapshot) | Immutable Subject Name Snapshot | Approved | 6.2 | Report archival fidelity |
| [DEC-017](#dec-017--elective-modification-lockout) | Elective Modification Lockout on Existing Marks | Approved | 6.2, 6.3 | Curriculum integrity |
| [DEC-018](#dec-018--multi-assignment-teacher-authorization) | Multi-Assignment Teacher Authorization | Approved | 6.1, 6.2 | Access control & scope resolution |
| [DEC-019](#dec-019--class-teacher-broad-scope-authorization) | Class Teacher Broad-Scope Authorization | Approved | 6.2, 6.3 | Teacher permissions |
| [DEC-020](#dec-020--dual-supported-calculation-methods) | Dual Supported Calculation Methods (Method 1 & 2) | Approved | 6.1, 6.2 | Mathematical calculations |
| [DEC-021](#dec-021--term-exam-exclusive-term-percentage-rule) | Term Exam Exclusive Term Percentage Rule | Approved | 6.1, 6.2 | Term percentage evaluation |
| [DEC-022](#dec-022--dynamic-academic-terms-architecture) | Dynamic Academic Terms Architecture | Approved | 6.1, 6.2 | Calendar configuration |
| [DEC-023](#dec-023--private-secure-storage-for-generated-reports) | Private, Secure Storage for Generated Reports | Approved | 6.1, 6.3 | PDF asset security |
| [DEC-024](#dec-024--append-only-immutable-report-revisions) | Append-Only Immutable Report Revisions | Approved | 6.2, 6.3 | Archival report generation |
| [DEC-025](#dec-025--append-only-immutable-audit-logging) | Append-Only Immutable Audit Logging | Approved | 6.1, 6.2 | Compliance & security |
| [DEC-026](#dec-026--exclusion-of-batch-pdf-generation) | Exclusion of Batch PDF Generation | Approved | 6.3, 6.4 | Reporting scope boundary |
| [DEC-027](#dec-027--role-aware-closed-academic-year-lifecycle) | Role-Aware Closed Academic Year Lifecycle | Approved | 6.1, 6.3 | Year closing workflows |
| [DEC-028](#dec-028--attendance-bounds-and-safe-00-handling) | Attendance Bounds and Safe 0/0 Handling | Approved | 6.1, 6.2 | Attendance reporting |
| [DEC-029](#dec-029--postgresql-functional-expression-indexes) | PostgreSQL Functional Expression Indexes for Nullable Uniqueness | Approved | 6.2, 6.4 | Database schema constraints |
| [DEC-030](#dec-030--strict-downward-dependency-flow) | Strict Downward Dependency Flow | Approved | 6.3, 6.4 | Architectural layering |
| [DEC-031](#dec-031--native-laravel-session-based-web-authentication) | Native Laravel Session-Based Web Authentication | Approved | 6.5 | Authentication architecture |
| [DEC-032](#dec-032--immediate-access-revocation-via-active-state-middleware) | Immediate Access Revocation via Active State Middleware | Approved | 6.5 | User account lifecycle & session security |
| [DEC-033](#dec-033--multi-assignment-composite-scope-resolution) | Multi-Assignment Composite Scope Resolution | Approved | 6.5 | Teacher access control & authorization |
| [DEC-034](#dec-034--class-teacher-broad-scope-authorization-resolution) | Class Teacher Broad-Scope Authorization Resolution | Approved | 6.5 | Classroom oversight authorization |
| [DEC-035](#dec-035--role-aware-closed-academic-year-authorization-matrix) | Role-Aware Closed Academic Year Authorization Matrix | Approved | 6.5 | Academic year lifecycle access control |
| [DEC-036](#dec-036--exclusive-administrator-user-account-management) | Exclusive Administrator User Account Management | Approved | 6.5 | Account administration boundary |
| [DEC-037](#dec-037--exclusive-administrator-audit-log-inspection) | Exclusive Administrator Audit Log Inspection | Approved | 6.5 | Audit trail security & compliance |
| [DEC-038](#dec-038--restricted-report-card-pdf-downloads) | Restricted Report Card PDF Downloads (Admin & Staff Only) | Approved | 6.5 | Student privacy & document distribution |
| [DEC-039](#dec-039--server-side-contextual-authorization-gateways) | Server-Side Contextual Authorization Gateways | Approved | 6.5 | Security perimeter & client-side untrust |
| [DEC-040](#dec-040--relational-idor-defense-via-scope-graph-traversal) | Relational IDOR Defense via Scope Graph Traversal | Approved | 6.5 | Resource-level parameter tampering defense |

---

## 3. Decision Records

### DEC-001 — Core Application Technology Stack
- **Status:** Approved
- **Phase:** 6.1 (Technology Baseline)
- **Date / Context:** Establishing the verified technology baseline.
- **Decision:** Build the application using PHP 8.4+ (targeting Laravel 13 with backward compatibility to Laravel 12 LTS) and PostgreSQL 18.4.
- **Why Chosen:** The legacy Node.js/Express/EJS stack was obsolete. Laravel provides first-class server-side web application capabilities, session authentication, Eloquent ORM, and Form Request validation out of the box. PostgreSQL 18 provides robust constraints, functional indexing, and JSONB support.
- **Alternatives Considered:**
  - Node.js / Express / EJS (Legacy baseline)
  - Python / Django
  - Laravel with MySQL
- **Rejected Alternatives:**
  - Node.js / Express: Discarded per project direction in favor of modern PHP/Laravel.
  - MySQL: Replaced by PostgreSQL during Phase 5 validation pass due to superior constraint enforcement and enterprise relational features.
- **Authoritative Sources:** `BRD V1.3`, `docs/architecture/PHASE_6_1_TECHNOLOGY_BASELINE.md`.
- **Impact:** All subsequent code and architecture must target PHP 8.4+ and PostgreSQL 18.
- **Dependencies:** None.

---

### DEC-002 — Server-Rendered Blade Architecture
- **Status:** Approved
- **Phase:** 6.1 (Technology Baseline)
- **Date / Context:** Choosing the frontend architectural model.
- **Decision:** Implement a classic server-rendered web application using Laravel Blade templates.
- **Why Chosen:** A school examination management system is document- and form-heavy, requiring strong server-side validation and session security. Blade eliminates state synchronization overhead, complex client build pipelines, and routing duplication.
- **Alternatives Considered:**
  - Single-Page Application (React / Vue)
  - Inertia.js hybrid SPA
  - Next.js / Nuxt headless frontend
- **Rejected Alternatives:**
  - React/Vue/Inertia: Rejected to avoid unnecessary complexity, duplicate validation layers, hydration bugs, and external SPA dependencies.
- **Authoritative Sources:** `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 2 & 7.
- **Impact:** All UI screens, layout trees, and forms are authored as Blade views.
- **Dependencies:** DEC-001.

---

### DEC-003 — Vanilla JavaScript Micro-Interactions
- **Status:** Approved
- **Phase:** 6.1 (Technology Baseline)
- **Date / Context:** Defining client-side scripting boundaries.
- **Decision:** Use Vanilla JavaScript (ES modules) exclusively for micro-interactions (spreadsheet keyboard navigation in mark grids, cascading selects, modal dialogs, and dirty-state tracking).
- **Why Chosen:** Lightweight, zero external dependencies, ultra-fast execution, and seamless integration with server-rendered HTML.
- **Alternatives Considered:**
  - Alpine.js
  - Vue.js / React widgets
  - jQuery
- **Rejected Alternatives:**
  - Alpine.js / jQuery: Unnecessary runtime overhead when modern Vanilla JS DOM APIs fully satisfy all interactive requirements.
- **Authoritative Sources:** `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 7; `PHASE_6_3_LARAVEL_PROJECT_FOLDER_STRUCTURE.md`, Section 21.
- **Impact:** JavaScript files are structured under `resources/js/modules/` as focused, modular components.
- **Dependencies:** DEC-002.

---

### DEC-004 — Tailored Plain CSS Design System
- **Status:** Approved
- **Phase:** 6.1 (Technology Baseline)
- **Date / Context:** Establishing UI styling framework and design tokens.
- **Decision:** Use tailored Vanilla CSS structured around custom properties (design tokens) without utility frameworks.
- **Why Chosen:** Complete control over dense academic tables, print stylesheets, responsive layouts, and exact visual hierarchy without the bloat or class soup of third-party frameworks.
- **Alternatives Considered:**
  - TailwindCSS
  - Bootstrap 5
- **Rejected Alternatives:**
  - TailwindCSS: Prohibited unless explicitly approved by user; plain CSS was requested for maximum control and clean markup.
  - Bootstrap: Generic appearance and restrictive pre-defined component conventions.
- **Authoritative Sources:** `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 4; `PHASE_6_3_LARAVEL_PROJECT_FOLDER_STRUCTURE.md`, Section 22.
- **Impact:** CSS organized under `resources/css/` (`tokens.css`, `base.css`, `layout.css`, `components.css`, `mark-grid.css`, `report-print.css`).
- **Dependencies:** DEC-002.

---

### DEC-005 — Vite as Offline Build Tooling Only
- **Status:** Approved
- **Phase:** 6.1 (Technology Baseline)
- **Date / Context:** Clarifying the role of Node.js and asset bundlers.
- **Decision:** Use Vite with Node.js 22 LTS strictly as a development asset bundler and production compiler. Node.js is **never** executed as an application runtime.
- **Why Chosen:** Fast Hot Module Replacement (HMR) during local development and automated cache-busting asset compilation for production.
- **Alternatives Considered:**
  - Laravel Mix (Webpack)
  - Raw uncompiled static assets
- **Rejected Alternatives:**
  - Laravel Mix: Deprecated in modern Laravel in favor of native Vite.
  - Node.js backend: Strictly obsolete.
- **Authoritative Sources:** `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 3.
- **Impact:** Production deployment requires running `npm run build` once; production web server runs purely PHP.
- **Dependencies:** DEC-001, DEC-003, DEC-004.

---

### DEC-006 — Single-School Architecture
- **Status:** Approved
- **Phase:** 6.1 (Technology Baseline)
- **Date / Context:** Defining the tenant scope of the system.
- **Decision:** Design the system strictly as a single-school application. Do not introduce multi-tenancy constructs, `tenant_id` columns, or multi-school routing.
- **Why Chosen:** Aligns directly with BRD V1.3 and finalized business rules. Introducing multi-tenancy prematurely adds massive schema complexity and operational risk.
- **Alternatives Considered:**
  - Multi-tenant single database (row-level `tenant_id`)
  - Multi-database tenancy
- **Rejected Alternatives:**
  - Multi-tenancy: Explicitly out of scope in BRD V1.3 and rejected.
- **Authoritative Sources:** `BRD V1.3`, `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 5-M.
- **Impact:** Singleton `school_settings` table stores institutional parameters.
- **Dependencies:** None.

---

### DEC-007 — Closed 23-Table PostgreSQL Schema
- **Status:** Approved
- **Phase:** 6.1, 6.2 (Domain Model)
- **Date / Context:** Establishing the relational database baseline.
- **Decision:** Treat the validated 23-table PostgreSQL schema (`database/Postgres/schema.sql`) as completely closed and authoritative. Laravel must adapt to this schema; no tables or columns will be added, removed, or redesigned.
- **Why Chosen:** The schema has been rigorously validated through PASS 2 and PASS 5 (54 schema tests, 16 real-world scenarios passed). Redesigning or adding convenience columns would introduce schema drift.
- **Alternatives Considered:**
  - Adding Laravel-conventional convenience columns (e.g. `deleted_at`, `remember_token` everywhere)
  - Redesigning relationships to match default Eloquent naming
- **Rejected Alternatives:**
  - Schema alterations: Rejected to preserve validated integrity and historical baseline.
- **Authoritative Sources:** `DB design/DB-table-definitons.txt`, `database/Postgres/schema.sql`, `PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md`.
- **Impact:** All 23 Eloquent models adapt to existing table names, column names, and constraints.
- **Dependencies:** DEC-001.

---

### DEC-008 — Eloquent as Exclusive ORM
- **Status:** Approved
- **Phase:** 6.1, 6.2 (Domain Model)
- **Date / Context:** Choosing the persistence abstraction layer.
- **Decision:** Use native Laravel Eloquent ORM exclusively for data mapping, relationships, and queries.
- **Why Chosen:** Eloquent provides rich relationship definitions, custom casting, query scopes, and seamless integration with Laravel authentication, authorization, and validation.
- **Alternatives Considered:**
  - Doctrine ORM
  - Raw SQL / Query Builder only
  - Prisma / TypeORM
- **Rejected Alternatives:**
  - Doctrine / Prisma: External complexity with zero architectural benefit in a Laravel application.
- **Authoritative Sources:** `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 3 & 4.
- **Impact:** Models encapsulate table mappings and relationship definitions.
- **Dependencies:** DEC-001, DEC-007.

---

### DEC-009 — Flat 23-Model Directory Structure
- **Status:** Approved
- **Phase:** 6.2, 6.3 (Folder Structure)
- **Date / Context:** Organizing Eloquent model files in the codebase.
- **Decision:** Place all 23 Eloquent models directly in `app/Models/` in a flat namespace (`App\Models\{Model}`).
- **Why Chosen:** In a cohesive 23-table relational graph, relationships cross domain boundaries universally. Subdirectory partitioning creates deep namespaces and artificial boundaries without reducing complexity.
- **Alternatives Considered:**
  - Domain subfolders (e.g., `app/Models/Academic/`, `app/Models/Marks/`)
  - Modular package structure
- **Rejected Alternatives:**
  - Subfolders: Creates verbose namespaces and circular namespace imports without improving maintainability.
- **Authoritative Sources:** `PHASE_6_3_LARAVEL_PROJECT_FOLDER_STRUCTURE.md`, Section 7.
- **Impact:** Clean, consistent model imports across services and controllers.
- **Dependencies:** DEC-007, DEC-008.

---

### DEC-010 — `SchoolClass` Model Naming
- **Status:** Approved
- **Phase:** 6.2 (Domain Model)
- **Date / Context:** Resolving PHP syntax conflict with the `classes` database table.
- **Decision:** Name the Eloquent model representing the `classes` table `SchoolClass` (`protected $table = 'classes';`).
- **Why Chosen:** `class` is a reserved keyword in PHP (`class Class extends Model` produces a fatal syntax error). `SchoolClass` clearly denotes the academic grade entity while mapping to `classes`.
- **Alternatives Considered:**
  - `ClassModel`
  - `AcademicClass`
  - `Grade`
- **Rejected Alternatives:**
  - `ClassModel`: Clumsy suffix anti-pattern.
  - `Grade`: Conflicts with letter-grade and marks terminology in the school domain.
- **Authoritative Sources:** `PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md`, Section 6.5.
- **Impact:** All references in PHP code use `SchoolClass::class`.
- **Dependencies:** DEC-007, DEC-008.

---

### DEC-011 — Strict Exclusion of Artificial Repositories
- **Status:** Approved
- **Phase:** 6.1, 6.2, 6.3
- **Date / Context:** Evaluating repository pattern abstractions over Eloquent.
- **Decision:** Strictly prohibit pass-through repository interfaces (e.g. `UserRepositoryInterface`, `MarkRepository`).
- **Why Chosen:** Eloquent is already an Active Record implementation with query builder capabilities. Wrapping it in 1:1 repository interfaces creates redundant boilerplate and violates the minimal dependency principle without adding testability.
- **Alternatives Considered:**
  - Repository / Interface pattern for every model
  - Generic CRUD repository base class
- **Rejected Alternatives:**
  - Repository wrappers: Rejected as unnecessary overengineering in a single-database Laravel application.
- **Authoritative Sources:** `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 5-H; `PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md`, Section 24.
- **Impact:** Application Services query Eloquent models directly.
- **Dependencies:** DEC-008, DEC-012.

---

### DEC-012 — Application Services for Domain Workflows
- **Status:** Approved
- **Phase:** 6.1, 6.3
- **Date / Context:** Establishing where business logic and multi-entity workflows live.
- **Decision:** Place complex domain workflows, multi-step operations, mathematical calculations, and database transactions into dedicated Application Services in `app/Services/`.
- **Why Chosen:** Keeps controllers thin, isolates business logic from HTTP transport, and prevents model bloat or implicit side effects in model observers.
- **Alternatives Considered:**
  - Fat controllers
  - Fat models with heavy business methods
  - Model observers for multi-table workflows
- **Rejected Alternatives:**
  - Fat controllers: Untestable and violates separation of concerns.
  - Model observers: Conceals execution order and creates unexpected side effects.
- **Authoritative Sources:** `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 5-D & 5-G; `PHASE_6_3_LARAVEL_PROJECT_FOLDER_STRUCTURE.md`, Section 13.
- **Impact:** 7 canonical services established (`MarkEntryService`, `CalculationService`, `StudentPlacementService`, `StudentSubjectService`, `ReportGenerationService`, `TeacherAssignmentService`, `AuditLogService`).
- **Dependencies:** DEC-008, DEC-011.

---

### DEC-013 — Discrete Three-State Mark System
- **Status:** Approved
- **Phase:** 6.1, 6.2
- **Date / Context:** Preserving academic grading integrity across unentered, zero, and absent states.
- **Decision:** Maintain three discrete, mutually exclusive mark states:
  1. `blank`: `result_status = 'blank'`, `mark_value = NULL` (Incomplete assessment; blocks report completion).
  2. `numeric`: `result_status = 'numeric'`, `mark_value >= 0.00` (Assessed score; contributes to total).
  3. `absent`: `result_status = 'absent'`, `mark_value = NULL` (Displays as `"A"`; contributes $0.00$; complete).
- **Why Chosen:** Mandatory business requirement from BRD V1.3 (BR-010, BR-011, BR-012). Conflating blank with zero or absent violates academic grading rules.
- **Alternatives Considered:**
  - Storing string values in a single column (e.g. `"A"`, `"0"`, `""`)
  - Sentinel numeric values (e.g. `-1` for absent)
- **Rejected Alternatives:**
  - String columns: Destroys numeric aggregation, indexing, and range checking.
  - Sentinel values: Dangerous; risk of accidental inclusion in mathematical averages.
- **Authoritative Sources:** `BRD V1.3`, `database/Postgres/schema.sql` (`chk_marks_result_consistency`), `PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md`, Section 11.
- **Impact:** Database constraint enforces consistency; casts and UI components respect distinct states.
- **Dependencies:** DEC-007, DEC-014.

---

### DEC-014 — `MarkValueCast` and `MarkResult` Value Object
- **Status:** Approved
- **Phase:** 6.2, 6.3
- **Date / Context:** Preventing PHP/Eloquent from converting SQL `NULL` to `0.00`.
- **Decision:** Implement a custom `MarkValueCast` in `app/Casts/` and an immutable `MarkResult` value object in `app/ValueObjects/`.
- **Why Chosen:** Default Eloquent float or string casting can inadvertently convert `null` to `0.00` in PHP memory, corrupting the distinction between an unentered mark (`blank`) and an assessed zero (`0.00`).
- **Alternatives Considered:**
  - Using raw uncasted attributes
  - Simple accessor/mutator methods on `Mark`
- **Rejected Alternatives:**
  - Raw attributes: Inconsistent type representation across controllers, Blade, and services.
- **Authoritative Sources:** `PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md`, Section 7.3; `PHASE_6_3_LARAVEL_PROJECT_FOLDER_STRUCTURE.md`, Section 15.
- **Impact:** Models cast `mark_value` safely; `MarkResult` provides unified formatting (`displayValue()`, `isComplete()`, `numericScore()`).
- **Dependencies:** DEC-013.

---

### DEC-015 — Historical Student Academic Placement Immutability
- **Status:** Approved
- **Phase:** 6.2, 6.3
- **Date / Context:** Managing student class/section transfers and roll-number history.
- **Decision:** When an internal student transfer occurs, close the previous `StudentAcademicRecord` (`status = 'internal_transfer'`, set `effective_to`), and insert a new record for the new section/roll number. Historical marks and attendance remain attached to the **original** historical placement.
- **Why Chosen:** Preserves historical auditability. Migrating marks onto a new placement would falsify the academic context under which those marks were originally earned.
- **Alternatives Considered:**
  - Updating the existing record's `section_id` and `roll_number`
  - Migrating historical marks to the new placement row
  - Using `SoftDeletes`
- **Rejected Alternatives:**
  - Mutating existing records: Destroys historical placement records.
  - Migrating marks: Falsifies assessment records.
  - Soft deletes: Masquerades deletion rather than modeling state transitions.
- **Authoritative Sources:** `BRD V1.3` (BR-020, BR-021, BR-022), `PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md`, Section 12.
- **Impact:** `StudentPlacementService` manages atomic placement transitions.
- **Dependencies:** DEC-007, DEC-012.

---

### DEC-016 — Immutable Subject Name Snapshot
- **Status:** Approved
- **Phase:** 6.2
- **Date / Context:** Preserving historical subject names on report cards across catalog renames.
- **Decision:** Populate and freeze `class_subjects.subject_name_snapshot` upon curriculum configuration. Report cards resolve subject names exclusively through this snapshot.
- **Why Chosen:** If a school renames a master subject in year 2 (e.g. "Maths" to "Mathematics"), historical reports from year 1 must display the exact subject title that was active when the report was issued.
- **Alternatives Considered:**
  - Querying `subjects.name` directly in reports
  - Subject renaming audit table
- **Rejected Alternatives:**
  - Direct query on `subjects.name`: Causes retro-active changes to historical report cards.
- **Authoritative Sources:** `DB design/DB-table-definitons.txt`, `database/Postgres/schema.sql`, `PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md`, Section 10.
- **Impact:** Report compilation services read `classSubject.subject_name_snapshot`.
- **Dependencies:** DEC-007.

---

### DEC-017 — Elective Modification Lockout
- **Status:** Approved
- **Phase:** 6.2, 6.3
- **Date / Context:** Restricting student elective subject alterations after academic assessment begins.
- **Decision:** A student's elective allocation (`StudentSubjectAllocation`) can be modified or deallocated during the academic year **only if no marks exist** for that student in that subject. Once marks exist, modifications are blocked.
- **Why Chosen:** Mandatory business rule from BRD V1.3. Allowing elective changes after marks exist would create orphaned marks or falsify academic totals.
- **Alternatives Considered:**
  - Permitting elective changes and deleting existing marks
  - Permitting changes with administrative override
- **Rejected Alternatives:**
  - Deleting marks: Violates academic integrity and audit rules.
- **Authoritative Sources:** `BRD V1.3` (BR-026), `PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md`, Section 13.
- **Impact:** `StudentSubjectService` checks `$allocation->marks()->exists()` before allowing updates.
- **Dependencies:** DEC-007, DEC-012.

---

### DEC-018 — Multi-Assignment Teacher Authorization
- **Status:** Approved
- **Phase:** 6.1, 6.2, 6.3
- **Date / Context:** Modeling teacher academic access across multiple classes and subjects.
- **Decision:** Evaluate teacher authorization dynamically against the teacher's complete set of active rows in `teacher_assignments`. Never assume 1 teacher = 1 class or 1 teacher = 1 subject.
- **Why Chosen:** Real-world school teachers frequently instruct multiple sections or subjects (e.g. Class Teacher for 8-A, Math Teacher for 9-B and 10-A).
- **Alternatives Considered:**
  - Storing `teacher_id` on `classes` or `sections` directly
  - Limiting a teacher account to a single assignment row
- **Rejected Alternatives:**
  - Single assignment: Incompatible with real-world school operations and violates BRD V1.3.
- **Authoritative Sources:** `BRD V1.3` (BR-040, BR-041), `database/Postgres/schema.sql`, `PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md`, Section 16.
- **Impact:** `TeacherAssignmentService` resolves composite scopes; Policies enforce contextual access.
- **Dependencies:** DEC-007, DEC-012.

---

### DEC-019 — Class Teacher Broad-Scope Authorization
- **Status:** Approved
- **Phase:** 6.2, 6.3
- **Date / Context:** Defining the scope granted by a Class Teacher assignment row.
- **Decision:** A `teacher_assignments` row with `assignment_type = 'class_teacher'` has `subject_id = NULL` and automatically grants authority over **all applicable subjects** in that class and section.
- **Why Chosen:** Enforced at the database level via `chk_ta_assignment_subject_consistency`. A class teacher has pastoral and comprehensive academic oversight for their entire classroom.
- **Alternatives Considered:**
  - Requiring a separate row for every subject the class teacher oversees
- **Rejected Alternatives:**
  - Explicit subject rows for class teachers: Redundant, fragile, and contrary to the database design.
- **Authoritative Sources:** `database/Postgres/schema.sql`, `PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md`, Section 16.1.
- **Impact:** Authorization policies check if a teacher has a Class Teacher assignment for the target section before checking specific subject teacher assignments.
- **Dependencies:** DEC-007, DEC-018.

---

### DEC-020 — Dual Supported Calculation Methods
- **Status:** Approved
- **Phase:** 6.1, 6.2
- **Date / Context:** Implementing percentage calculation formulas per class and academic year.
- **Decision:** Architecturally maintain and implement both calculation methods defined in `calculation_settings`:
  - **Method 1:** Equal average of included assessment percentages.
  - **Method 2:** $\frac{\sum \text{Marks Obtained}}{\sum \text{Maximum Marks}} \times 100$.
- **Why Chosen:** Mandatory requirement from BRD V1.3. Even though the current single Term Exam rule produces identical numerical results, the calculation engine must support both methods for compliance and future extensibility.
- **Alternatives Considered:**
  - Hard-coding a single formula
- **Rejected Alternatives:**
  - Single formula: Violates BRD V1.3 and ignores `calculation_settings.calculation_method`.
- **Authoritative Sources:** `BRD V1.3` (BR-050, BR-051), `PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md`, Section 15.
- **Impact:** `CalculationService` evaluates the configured method and computes percentages to 2 decimal places.
- **Dependencies:** DEC-007, DEC-012.

---

### DEC-021 — Term Exam Exclusive Term Percentage Rule
- **Status:** Approved
- **Phase:** 6.1, 6.2
- **Date / Context:** Defining assessment contribution to term percentages.
- **Decision:** In the current finalized business rules, **only assessments of type 'Term Exam' contribute to the term percentage**. Class Tests and Unit Tests do not contribute to the term percentage unless a future business rule is enacted.
- **Why Chosen:** Explicitly finalized in BRD V1.3 clarification notes and project handover decisions.
- **Alternatives Considered:**
  - Arbitrary custom percentage weighting (e.g. 20% Unit Test + 80% Term Exam)
- **Rejected Alternatives:**
  - Custom weighting: Explicitly prohibited by BRD V1.3; no custom weighting exists in the approved schema.
- **Authoritative Sources:** `BRD V1.3` (CL-004), `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 6.
- **Impact:** `CalculationService` filters assessments participating in term percentages by assessment type.
- **Dependencies:** DEC-020.

---

### DEC-022 — Dynamic Academic Terms Architecture
- **Status:** Approved
- **Phase:** 6.1, 6.2
- **Date / Context:** Modeling terms within an academic year.
- **Decision:** Support dynamic term counts (2 terms, 3 terms, 4 quarters) configured in the `terms` table via `sequence_no`. Never hard-code an assumption of 3 terms.
- **Why Chosen:** BRD V1.3 requires flexible term naming and counts to accommodate different school calendar structures.
- **Alternatives Considered:**
  - Hard-coded `term_1`, `term_2`, `term_3` columns
  - Fixed 3-term system
- **Rejected Alternatives:**
  - Fixed 3 terms: Violates schema flexibility and approved relational model.
- **Authoritative Sources:** `BRD V1.3` (BR-005), `database/Postgres/schema.sql`, `PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md`, Section 6.4.
- **Impact:** UI tables and report generators dynamically loop over active terms.
- **Dependencies:** DEC-007.

---

### DEC-023 — Private, Secure Storage for Generated Reports
- **Status:** Approved
- **Phase:** 6.1, 6.3
- **Date / Context:** Securing generated PDF report card files on disk.
- **Decision:** Store all generated report card PDFs in private application storage (`storage/app/private/reports/{year_id}/{class_id}/{section_id}/`). They are never placed in public storage or directly accessible via web server URLs.
- **Why Chosen:** Student academic records are sensitive personal data. Report downloads must be mediated through an authenticated, authorized controller endpoint (`GeneratedReportDownloadController`).
- **Alternatives Considered:**
  - Storing PDFs in `storage/app/public/` with symlinks
  - Storing PDF binary blobs directly in PostgreSQL
- **Rejected Alternatives:**
  - Public storage: Massive security risk; allows unauthorized URL guessing.
  - Database blobs: Bloats database backups and degrades query performance.
- **Authoritative Sources:** `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 8; `PHASE_6_3_LARAVEL_PROJECT_FOLDER_STRUCTURE.md`, Section 27.
- **Impact:** Files streamed via `response()->download()` after policy verification.
- **Dependencies:** DEC-001, DEC-024.

---

### DEC-024 — Append-Only Immutable Report Revisions
- **Status:** Approved
- **Phase:** 6.2, 6.3
- **Date / Context:** Managing report card regeneration following mark corrections.
- **Decision:** Regenerating a report card creates a new `generated_reports` row with an incremented `revision_number` and a distinct file on disk. Prior PDF files and database records are **never overwritten or deleted**.
- **Why Chosen:** Preserves historical auditability. When marks are corrected, school administrators must retain the ability to inspect both original and revised report cards.
- **Alternatives Considered:**
  - Overwriting the existing PDF file and updating the single record
  - Soft deleting previous revisions
- **Rejected Alternatives:**
  - Overwriting files: Destroys historical audit records.
  - Soft deletes: Unnecessary and hides historical revisions from legitimate audit inspection.
- **Authoritative Sources:** `BRD V1.3` (BR-070), `database/Postgres/schema.sql` (`uk_gr_revision_identity`), `PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md`, Section 19.
- **Impact:** `ReportGenerationService` allocates `revision_number = MAX + 1` within an atomic transaction.
- **Dependencies:** DEC-007, DEC-023.

---

### DEC-025 — Append-Only Immutable Audit Logging
- **Status:** Approved
- **Phase:** 6.1, 6.2, 6.3
- **Date / Context:** Architecture for system activity and mark change tracking.
- **Decision:** `AuditLog` is strictly append-only. Normal update and delete operations are prohibited at the application layer. The model has no `updated_at` timestamp. Only Administrators can view logs.
- **Why Chosen:** Mandated by BRD V1.3 and security best practices. Audit trails must be tamper-proof within the application.
- **Alternatives Considered:**
  - Using third-party audit packages (e.g. `spatie/laravel-activitylog`, `owen-it/laravel-auditing`)
  - Mutable audit records with edit timestamps
- **Rejected Alternatives:**
  - Third-party packages: Rejected to avoid schema drift, package dependency risks, and unnecessary abstractions. The validated `audit_logs` table already satisfies 100% of requirements.
- **Authoritative Sources:** `BRD V1.3` (BR-080, BR-081), `database/Postgres/schema.sql`, `PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md`, Section 20.
- **Impact:** `AuditLogService` manages structured `before_data` and `after_data` JSONB writes.
- **Dependencies:** DEC-007, DEC-012.

---

### DEC-026 — Exclusion of Batch PDF Generation
- **Status:** Approved
- **Phase:** 6.3, 6.4
- **Date / Context:** Resolving scope boundaries for report card PDF generation.
- **Decision:** Whole-class / batch PDF generation is **strictly out of scope** in the current project version. Report cards are generated individually per student.
- **Why Chosen:** BRD V1.3 explicitly excludes batch generation from V1 requirements. Introducing batch queues, background workers, or multi-student PDF merging prematurely introduces massive operational complexity.
- **Alternatives Considered:**
  - Background queue-driven batch ZIP / merged PDF generation
- **Rejected Alternatives:**
  - Batch generation: Discarded as an out-of-scope requirement. Phase 6.3 was corrected to remove batch request classes and polling scripts.
- **Authoritative Sources:** `BRD V1.3`, Section 13 of User Prompt for Phase 6.4.
- **Impact:** `ReportGenerationController` and `GenerateReportRequest` handle single-student generation.
- **Dependencies:** DEC-023, DEC-024.

---

### DEC-027 — Role-Aware Closed Academic Year Lifecycle
- **Status:** Approved
- **Phase:** 6.1, 6.3, 6.4
- **Date / Context:** Managing year-closing state and post-closure mark correction access.
- **Decision:** When an `AcademicYear` status is `closed`:
  1. Teachers become strictly read-only for that year.
  2. Administrators and Office Staff retain authorized correction access per finalized business rules.
  3. Reopening an academic year does not automatically restore teacher editing rights.
- **Why Chosen:** Mandatory business rule from BRD V1.3 and handover clarifications. Blanket route-level write blockers would break the Administrator's required ability to perform post-closure mark corrections.
- **Alternatives Considered:**
  - Blanket prohibition of all writes on closed years
  - Complete lock preventing even Administrator access
- **Rejected Alternatives:**
  - Blanket lock: Fails required post-closure mark correction workflows.
- **Authoritative Sources:** `BRD V1.3` (CL-007, CL-008), `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 6.
- **Impact:** Middleware guards teacher mutation routes; Policies verify role-specific correction privileges.
- **Dependencies:** DEC-007, DEC-018.

---

### DEC-028 — Attendance Bounds and Safe 0/0 Handling
- **Status:** Approved
- **Phase:** 6.1, 6.2
- **Date / Context:** Preserving attendance calculation integrity and avoiding division-by-zero errors.
- **Decision:** Enforce `days_attended <= total_working_days`. When `total_working_days = 0`, the attendance percentage renders as `"N/A"`. Division by zero is strictly prevented.
- **Why Chosen:** Schools may initialize attendance records before school sessions begin. An unhandled `0 / 0` triggers fatal runtime division exceptions.
- **Alternatives Considered:**
  - Rendering `0%` when working days are 0
  - Defaulting `total_working_days` to 1
- **Rejected Alternatives:**
  - Rendering 0%: Falsifies attendance records (implies the student attended 0 out of days held).
  - Defaulting to 1: Corrupts actual school working days.
- **Authoritative Sources:** `database/Postgres/schema.sql` (`chk_attendance_days_within_total`), `PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md`, Section 17.
- **Impact:** Calculation services and Blade views check for `total_working_days > 0` before calculating percentages.
- **Dependencies:** DEC-007.

---

### DEC-029 — PostgreSQL Functional Expression Indexes
- **Status:** Approved
- **Phase:** 6.2, 6.4
- **Date / Context:** Preserving composite uniqueness across nullable foreign key columns in PostgreSQL.
- **Decision:** Preserve and utilize PostgreSQL functional expression unique indexes with `COALESCE`:
  1. `class_subjects`: `uk_class_subjects_config` on `(academic_year_id, class_id, COALESCE(section_id, 0), subject_id)`.
  2. `generated_reports`: `uk_gr_revision_identity` on `(student_academic_record_id, report_type, COALESCE(term_id, 0), COALESCE(assessment_id, 0), revision_number)`.
- **Why Chosen:** Under ANSI SQL standard implemented by PostgreSQL, `NULL != NULL`. A standard composite unique index would permit duplicate entries when nullable columns contain `NULL`. Functional indexes with `COALESCE` enforce strict uniqueness.
- **Alternatives Considered:**
  - Application-only uniqueness checks
  - Replacing `NULL` with sentinel foreign key IDs (e.g. `section_id = 0`)
- **Rejected Alternatives:**
  - Application-only checks: Vulnerable to race conditions.
  - Sentinel foreign keys: Violates referential integrity (requires fake records in parent tables).
- **Authoritative Sources:** `database/Postgres/schema.sql`, `POSTGRES_MIGRATION_IMPACT_ANALYSIS.md`.
- **Impact:** Laravel migrations must define raw expression unique indexes; Eloquent queries must handle nullable sections correctly.
- **Dependencies:** DEC-007.

---

### DEC-030 — Strict Downward Dependency Flow
- **Status:** Approved
- **Phase:** 6.3, 6.4
- **Date / Context:** Establishing architectural boundaries and preventing circular dependencies.
- **Decision:** Code dependencies must flow strictly downward:
  $$\text{HTTP Layer (Controllers / Requests)} \rightarrow \text{Application Services} \rightarrow \text{Eloquent Models} \rightarrow \text{PostgreSQL Database}$$
  - Controllers coordinate transport; they never contain business math or transactions.
  - Blade templates consume prepared view models; they never execute database queries.
  - Eloquent models handle persistence and casting; they never invoke HTTP or service logic.
  - JavaScript provides client-side micro-interactions; it never holds authority over calculations or validation.
- **Why Chosen:** Prevents spaghetti code, guarantees high maintainability, and ensures testability of domain rules in isolation from the web server.
- **Alternatives Considered:**
  - Bidirectional dependencies / Active Record business callbacks
  - Direct database queries from Blade templates
- **Rejected Alternatives:**
  - Direct Blade queries: Major N+1 risk and violates layered architecture.
- **Authoritative Sources:** `PHASE_6_3_LARAVEL_PROJECT_FOLDER_STRUCTURE.md`, Section 30 & 31.
- **Impact:** Strict architectural boundary enforced across all subsequent implementation phases.
- **Dependencies:** DEC-001, DEC-008, DEC-012.

---

### DEC-031 — Native Laravel Session-Based Web Authentication
- **Status:** Approved
- **Phase:** 6.5 (Authentication & Security)
- **Date / Context:** Establishing user identity verification for a server-rendered web application.
- **Decision:** Use standard Laravel session authentication (`web` guard, Eloquent provider) with username/password credentials. Prohibit API tokens (Sanctum/Passport), JWT, OAuth, or external identity providers.
- **Why Chosen:** The application is a single-school, server-rendered web system. Stateful sessions natively support secure cookies, CSRF protection, and zero client-side token handling.
- **Alternatives Considered:**
  - Token-based API authentication (Sanctum/JWT)
  - OAuth / Single Sign-On (SSO)
- **Rejected Alternatives:**
  - JWT / Sanctum: Unnecessary complexity for a server-rendered web application; introduces token storage vulnerabilities.
  - OAuth: Overengineered; school user directory is self-contained.
- **Authoritative Sources:** `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 9; `PHASE_6_5_AUTHENTICATION_AUTHORIZATION_SECURITY.md`, Section 5.
- **Impact:** Login is handled via standard session forms; authenticated state is checked on every web request.
- **Dependencies:** DEC-001, DEC-002.

---

### DEC-032 — Immediate Access Revocation via Active State Middleware
- **Status:** Approved
- **Phase:** 6.5 (Authentication & Security)
- **Date / Context:** Preventing deactivated accounts from retaining access via existing browser sessions.
- **Decision:** Implement `EnsureUserIsActive` middleware that evaluates `Auth::user()->is_active` on every authenticated request. If false, the session is invalidated immediately, the user is logged out, and a redirection to `/login` occurs.
- **Why Chosen:** Merely disabling login leaves active sessions alive. This middleware guarantees instant revocation upon deactivation while preserving historical user records.
- **Alternatives Considered:**
  - Hard deleting user records
  - Relying on session expiration timeout
- **Rejected Alternatives:**
  - Hard delete: Destroys historical audit trails, mark actor references, and report archives.
  - Session timeout: Leaves an unauthorized window where deactivated staff could modify grades.
- **Authoritative Sources:** `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 9; `PHASE_6_5_AUTHENTICATION_AUTHORIZATION_SECURITY.md`, Section 24.
- **Impact:** Deactivating a user immediately blocks their next HTTP request.
- **Dependencies:** DEC-007, DEC-031.

---

### DEC-033 — Multi-Assignment Composite Scope Resolution
- **Status:** Approved
- **Phase:** 6.5 (Authentication & Security)
- **Date / Context:** Resolving authorization for teachers with multiple classroom and subject assignments.
- **Decision:** Teacher authorization evaluates the teacher's complete active assignment set in `teacher_assignments`. A teacher's role (`Teacher`) never confers automatic access; permissions require matching an active assignment row.
- **Why Chosen:** Real-world school teachers hold multiple roles (e.g. Class Teacher of 8-A, Math Teacher of 9-B). Caching a single classroom ID in session breaks multi-classroom teachers.
- **Alternatives Considered:**
  - Role-based permissions (`if user.role == teacher`)
  - Storing a single active classroom in session (`session('class_id')`)
- **Rejected Alternatives:**
  - Role-only checks: Complete security failure; permits any teacher to edit any grade across the school.
  - Single session assignment: Prevents multi-assignment teachers from operating seamlessly.
- **Authoritative Sources:** `BRD V1.3` (BR-040, BR-041), `PHASE_6_5_AUTHENTICATION_AUTHORIZATION_SECURITY.md`, Section 12 & 15.
- **Impact:** `TeacherAssignmentService` evaluates composite assignment records dynamically from PostgreSQL.
- **Dependencies:** DEC-007, DEC-018.

---

### DEC-034 — Class Teacher Broad-Scope Authorization Resolution
- **Status:** Approved
- **Phase:** 6.5 (Authentication & Security)
- **Date / Context:** Establishing the scope of authority conferred by a Class Teacher assignment.
- **Decision:** A `teacher_assignments` row with `assignment_type = 'class_teacher'` and `subject_id = NULL` automatically grants authority over **all applicable subjects** in that class and section without requiring individual subject rows.
- **Why Chosen:** Aligns with database constraint `chk_ta_assignment_subject_consistency`. Class teachers hold pastoral and academic oversight over their classroom.
- **Alternatives Considered:**
  - Requiring individual assignment rows for every subject in the class
- **Rejected Alternatives:**
  - Individual subject rows: Redundant, brittle, and violates the validated relational model.
- **Authoritative Sources:** `database/Postgres/schema.sql`, `PHASE_6_5_AUTHENTICATION_AUTHORIZATION_SECURITY.md`, Section 13.
- **Impact:** Policies check Class Teacher status first, granting access to all subjects in the section if matched.
- **Dependencies:** DEC-007, DEC-033.

---

### DEC-035 — Role-Aware Closed Academic Year Authorization Matrix
- **Status:** Approved
- **Phase:** 6.5 (Authentication & Security)
- **Date / Context:** Restricting mutations when an academic year status transitions to `closed`.
- **Decision:** When an academic year is closed:
  1. Teachers (both Class and Subject Teachers) become strictly read-only.
  2. Administrators and Office Staff retain authorized correction access accompanied by mandatory audit logging.
  3. Reopening an academic year does not automatically restore teacher mark editing.
- **Why Chosen:** Mandatory business rules from BRD V1.3 (CL-007, CL-008). A blanket route blocker on closed years would prevent required administrative mark corrections.
- **Alternatives Considered:**
  - Blanket closure blocking all users including Administrator
  - Automatic restoration of teacher rights on reopen
- **Rejected Alternatives:**
  - Blanket closure: Breaks post-closure mark correction requirements.
  - Automatic restoration: Violates finalized institutional handover rules.
- **Authoritative Sources:** `BRD V1.3` (CL-007, CL-008), `PHASE_6_5_AUTHENTICATION_AUTHORIZATION_SECURITY.md`, Section 18.
- **Impact:** Policies check role and year status; teachers receive 403 on closed years.
- **Dependencies:** DEC-007, DEC-027.

---

### DEC-036 — Exclusive Administrator User Account Management
- **Status:** Approved
- **Phase:** 6.5 (Authentication & Security)
- **Date / Context:** Restricting user account creation, deactivation, and credential resets.
- **Decision:** Only users holding the `Administrator` role may access `/admin/users`, create accounts, deactivate accounts, or execute password resets. Office Staff are strictly barred.
- **Why Chosen:** User accounts control system-wide access. Delegating account creation to Office Staff introduces privilege escalation and account hijacking risks.
- **Alternatives Considered:**
  - Permitting Office Staff to create teacher accounts
  - Self-service password resets
- **Rejected Alternatives:**
  - Office Staff account management: Violates least-privilege administrative separation.
  - Self-service resets: Out of scope for single-school internal system.
- **Authoritative Sources:** `BRD V1.3` (BR-030), `PHASE_6_5_AUTHENTICATION_AUTHORIZATION_SECURITY.md`, Section 16 & 17.
- **Impact:** `UserPolicy` restricts account management strictly to `Administrator`.
- **Dependencies:** DEC-007, DEC-031.

---

### DEC-037 — Exclusive Administrator Audit Log Inspection
- **Status:** Approved
- **Phase:** 6.5 (Authentication & Security)
- **Date / Context:** Restricting access to historical system activity logs.
- **Decision:** Only users holding the `Administrator` role are authorized to view audit logs (`/admin/audit-logs`). Office Staff and Teachers are strictly barred. Audit logs cannot be modified or deleted.
- **Why Chosen:** Audit logs contain complete historical records of mark corrections, user actions, and IP metadata. Exposing them to operational staff violates privacy and security principles.
- **Alternatives Considered:**
  - Allowing Office Staff to inspect audit logs
- **Rejected Alternatives:**
  - Office Staff audit inspection: Prohibited by BRD V1.3 (BR-081).
- **Authoritative Sources:** `BRD V1.3` (BR-081), `PHASE_6_5_AUTHENTICATION_AUTHORIZATION_SECURITY.md`, Section 22.
- **Impact:** `AuditLogPolicy` blocks all non-Administrator access with HTTP 403.
- **Dependencies:** DEC-007, DEC-025.

---

### DEC-038 — Report Card PDF Download Authorization (Admin, Staff & Scoped Class Teachers)
- **Status:** Approved
- **Phase:** 6.5 (Authentication & Security)
- **Date / Context:** Controlling distribution and download rights for generated student report cards.
- **Decision:** Report Card PDF Downloads are authorized for `Administrator`, `Office Staff`, and `Class Teachers`. For a Class Teacher, download authorization is strictly restricted to reports belonging to their active assigned classroom scope (`academic_year_id`, `class_id`, `section_id`). Subject Teachers cannot download final report cards.
- **Why Chosen:** Aligns with finalized institutional workflow where Class Teachers hold pastoral and academic oversight over their assigned classroom and require report card distribution capabilities for their students. Subject Teachers remain restricted to subject mark entry.
- **Alternatives Considered:**
  - Restricting downloads strictly to Administrator and Office Staff
  - Unrestricted downloads for all teachers across all classrooms
- **Rejected Alternatives:**
  - Admin/Staff only: Inconveniences Class Teachers responsible for student academic reviews and card distribution.
  - Unrestricted teacher downloads: Critical data privacy and authorization violation (allows teachers to view/download reports of unassigned classes).
- **Authoritative Sources:** Finalized Handover Decisions, `PHASE_6_5_AUTHENTICATION_AUTHORIZATION_SECURITY.md`, Section 21.
- **Impact:** `ReportPolicy@download` checks role and verifies the student academic placement against active `teacher_assignments` for Class Teachers; returns HTTP 403 for Subject Teachers or unassigned classrooms.
- **Dependencies:** DEC-023, DEC-026, DEC-033, DEC-034.

---

### DEC-039 — Server-Side Contextual Authorization Gateways
- **Status:** Approved
- **Phase:** 6.5 (Authentication & Security)
- **Date / Context:** Establishing the security perimeter and handling client-side controls.
- **Decision:** Client-side controls (disabled inputs, hidden fields, frontend validation) are strictly presentation aids with zero security authority. Every mutation request must be independently authenticated, validated, and authorized on the server.
- **Why Chosen:** Attackers can trivially tamper with HTTP payloads, re-enable disabled DOM inputs, or forge POST requests. Security must be enforced on the server.
- **Alternatives Considered:**
  - Relying on client-side route guards or form disabling
- **Rejected Alternatives:**
  - Client-side trust: Critical vulnerability enabling privilege escalation and unauthorized mark tampering.
- **Authoritative Sources:** `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 5-O; `PHASE_6_5_AUTHENTICATION_AUTHORIZATION_SECURITY.md`, Section 4.
- **Impact:** All mutations pass through Form Requests, Policies, and Service invariant checks.
- **Dependencies:** DEC-008, DEC-012, DEC-033.

---

### DEC-040 — Relational IDOR Defense via Scope Graph Traversal
- **Status:** Approved
- **Phase:** 6.5 (Authentication & Security)
- **Date / Context:** Defending against Insecure Direct Object References in resource endpoints.
- **Decision:** When an action targets a specific database entity (e.g. `/marks/{id}`), the authorization policy must traverse the full relational graph (`Mark` $\rightarrow$ `StudentAcademicRecord` and `AssessmentApplicability` $\rightarrow$ `ClassSubject`) and verify that the resolved academic context matches the user's active assignment scope.
- **Why Chosen:** Validating only that the target ID exists allows a teacher to submit another classroom's mark ID. Relational graph traversal ensures the target resource belongs strictly to the teacher's authorized classroom.
- **Alternatives Considered:**
  - Authorizing only by checking `user_id == mark.entered_by_user_id`
  - Trusting class/section IDs submitted in route parameters
- **Rejected Alternatives:**
  - `entered_by_user_id` check: Breaks when Class Teacher edits marks entered by Subject Teacher, or when staff re-assigns classrooms.
  - Parameter trust: Vulnerable to parameter tampering.
- **Authoritative Sources:** `PHASE_6_5_AUTHENTICATION_AUTHORIZATION_SECURITY.md`, Section 26.
- **Impact:** All entity-level policies traverse relationships before returning authorization verdicts.
- **Dependencies:** DEC-007, DEC-033, DEC-039.

---

### DEC-041 — Resource-Oriented Route Hierarchy and Action-Oriented Workflow Endpoints
- **Status:** Approved
- **Phase:** 6.6 (HTTP Layer, Routes & Controller Contracts)
- **Date / Context:** Establishing URL hierarchy, route naming conventions, and workflow-specific action routes.
- **Decision:** Structure web routes using standard resource conventions (`/academic-years`, `/students`, `/marks`) while establishing dedicated action-oriented POST routes for specific business workflows (e.g. `/students/{student}/transfer`, `/marks/batch-save`, `/marks/{mark}/correct`, `/academic-years/{academicYear}/close`, `/reports/generate`, `/reports/download/{generatedReport}`). Avoid arbitrary RPC route names and separate API routing namespaces.
- **Why Chosen:** Balances standard RESTful resource predictability with real-world school workflows that cannot be reduced to simple CRUD operations. Preserves the server-rendered application architecture without introducing unnecessary API token infrastructure.
- **Alternatives Considered:**
  - Pure RESTful CRUD with nested update routes for all workflows
  - Separate `/api/` routing namespace with token authentication
- **Rejected Alternatives:**
  - Pure CRUD: Awkward mapping for complex domain events (e.g. closing an academic year or transferring student sections).
  - `/api/` namespace: Violates single server-rendered architecture and introduces API token security risks.
- **Authoritative Sources:** `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 5; `PHASE_6_6_HTTP_LAYER_ROUTES_FORM_REQUESTS_CONTROLLERS.md`, Section 8.
- **Impact:** Routes registered in `routes/web.php` map directly to dedicated controller actions.
- **Dependencies:** DEC-001, DEC-030, DEC-031.

---

### DEC-042 — Strict Three-Tier Validation Boundary (Form Request vs. Policy vs. Service)
- **Status:** Approved
- **Phase:** 6.6 (HTTP Layer, Routes & Controller Contracts)
- **Date / Context:** Defining the exact separation of validation responsibilities between HTTP requests, authorization policies, and domain services.
- **Decision:** Enforce a strict three-tier validation boundary:
  1. **Form Requests:** Validate only syntactic shape, data types, presence/required fields, regex patterns, scalar bounds (e.g. `>= 0.00`), and static enum sets.
  2. **Laravel Policies:** Validate only user identity, active role, and live contextual teacher assignment scope.
  3. **Application Services:** Validate all dynamic business invariants, cross-table rules (e.g. mark $\le$ applicability max_marks, elective lock if marks exist), and database transaction boundaries.
- **Why Chosen:** Prevents duplication of business rules, stops Form Requests from executing complex database queries, and ensures domain invariants remain enforceable outside HTTP contexts (e.g. Artisan CLI or automated tests).
- **Alternatives Considered:**
  - Putting all business checks into Form Request custom validation rules
  - Validating everything inside controllers
- **Rejected Alternatives:**
  - Form Request business checks: Leads to brittle database lookups inside request classes and bypasses validation in CLI contexts.
  - Controller validation: Violates thin controller architecture and causes code duplication.
- **Authoritative Sources:** `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 5-L; `PHASE_6_6_HTTP_LAYER_ROUTES_FORM_REQUESTS_CONTROLLERS.md`, Section 10.
- **Impact:** Form Requests remain thin and fast; business invariants are centralized inside canonical services.
- **Dependencies:** DEC-008, DEC-012, DEC-030, DEC-039.

---

### DEC-043 — Atomic Batch Mark Save Protocol with Universal Scope Verification
- **Status:** Approved
- **Phase:** 6.6 (HTTP Layer, Routes & Controller Contracts)
- **Date / Context:** Designing the batch mark saving endpoint for spreadsheet-style UI grid entry.
- **Decision:** The batch mark saving endpoint (`POST /marks/batch-save`) must operate as a strictly atomic transaction. Every submitted mark record must have its student placement, class, section, and subject independently authorized against the teacher's active assignment scope. If any submitted record fails authorization or exceeds the authoritative maximum mark, the entire database transaction rolls back, rejecting the batch with zero partial saves.
- **Why Chosen:** Partial batch saving leaves classroom mark rosters in corrupt, inconsistent states and introduces privilege escalation vulnerabilities where an attacker slips unauthorized records into a valid batch.
- **Alternatives Considered:**
  - Saving authorized records and silently discarding unauthorized records
  - Saving authorized records and returning partial error arrays
- **Rejected Alternatives:**
  - Silent discarding: Hides security violations and corrupts teacher expectations.
  - Partial saving: Leads to race conditions and inconsistent classroom evaluation states.
- **Authoritative Sources:** `BRD V1.3` (BR-010 to BR-016), `PHASE_6_5_AUTHENTICATION_AUTHORIZATION_SECURITY.md`, Section 19; `PHASE_6_6_HTTP_LAYER_ROUTES_FORM_REQUESTS_CONTROLLERS.md`, Section 11.
- **Impact:** `MarkEntryService::saveBatch` wraps all record processing inside `DB::transaction()`.
- **Dependencies:** DEC-007, DEC-020, DEC-033, DEC-040.

---

### DEC-044 — Controller Transport Orchestration Contract & Prohibition of Business Logic
- **Status:** Approved
- **Phase:** 6.6 (HTTP Layer, Routes & Controller Contracts)
- **Date / Context:** Standardizing controller contracts and eliminating fat controllers.
- **Decision:** Controllers must function strictly as transport orchestrators. A controller action may only:
  1. Receive validated data from a Form Request.
  2. Authorize the action via `Gate::authorize()` or `$this->authorize()`.
  3. Invoke a method on a canonical Application Service, passing validated inputs and `Auth::id()`.
  4. Return an HTTP response (Blade view, redirect with flash feedback, or structured JSON).
  Controllers are strictly prohibited from executing raw database transactions, performing academic calculations, directly querying Eloquent models for complex business rules, or constructing audit log payloads.
- **Why Chosen:** Guarantees separation of concerns, ensures testability, prevents security bypasses, and keeps the HTTP transport layer completely decoupled from domain logic.
- **Alternatives Considered:**
  - Allowing small Eloquent helper queries in controllers
  - Active Record pattern where controllers call `$model->save()` directly
- **Rejected Alternatives:**
  - Direct controller saves: Bypasses audit logging, transaction safety, and domain invariants.
- **Authoritative Sources:** `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 5-L; `PHASE_6_3_LARAVEL_PROJECT_FOLDER_STRUCTURE.md`, Section 8; `PHASE_6_6_HTTP_LAYER_ROUTES_FORM_REQUESTS_CONTROLLERS.md`, Section 24.
- **Impact:** All controller methods conform to uniform orchestration signatures.
- **Dependencies:** DEC-001, DEC-008, DEC-012, DEC-030.

---

### DEC-045 — Modern Light-Themed SaaS Design Token Architecture
- **Status:** Approved
- **Phase:** 6.7 (Blade / UI / Vanilla JavaScript Integration Architecture)
- **Date / Context:** Defining the visual theme, design tokens, and aesthetic direction for the administrative application.
- **Decision:** The application must utilize a modern, calm, professional, high-density light theme centered around CSS custom properties (`tokens.css`). The approved palette designates `#2563EB` as the primary brand blue, `#F8FAFC` as the global page background, `#FFFFFF` for primary surfaces and cards, `#E2E8F0` for structural borders, and `#0F172A` for primary typography. Dark themes, gamified gradients, cartoonish illustrations, and excessive glassmorphism are strictly prohibited.
- **Why Chosen:** Educational administration workflows involve intensive, multi-hour data entry sessions. A crisp, high-contrast light theme with subdued neutral slates minimizes cognitive fatigue, maximizes data density, and ensures compliance with WCAG 2.1 AA contrast standards ($> 15:1$ for body text).
- **Alternatives Considered:**
  - Dark theme application
  - User-switchable light/dark theme toggle
  - Vibrant multi-color theme
- **Rejected Alternatives:**
  - Dark theme: Poor readability for complex spreadsheet data tables and inconsistent with institutional administrative tools.
  - Theme toggling: Adds gratuitous frontend complexity and asset payload for an internal school administrative system.
  - Multi-color theme: Visually distracting and lowers contrast.
- **Authoritative Sources:** `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 5; `PHASE_6_7_BLADE_UI_VANILLA_JS_INTEGRATION.md`, Section 5 & 8.
- **Impact:** Centralized CSS tokens govern all colors, typography, spacing, radius, and shadows across the entire UI.
- **Dependencies:** DEC-001, DEC-030.

---

### DEC-046 — Modular Plain CSS & Zero Framework Styling Architecture
- **Status:** Approved
- **Phase:** 6.7 (Blade / UI / Vanilla JavaScript Integration Architecture)
- **Date / Context:** Establishing styling conventions, build pipeline, and dependency boundaries.
- **Decision:** Style the application using standard Vanilla CSS with custom properties, organized into focused modular stylesheets (`tokens.css`, `base.css`, `layout.css`, `components.css`, `modules/mark-grid.css`) and compiled via Vite. Third-party CSS frameworks (Tailwind CSS, Bootstrap, Bulma, Foundation) are strictly prohibited.
- **Why Chosen:** Eliminates external framework lock-in, avoids utility-class bloat in server-rendered Blade templates, guarantees full control over high-density spreadsheet grid layouts, and ensures long-term maintainability with zero breaking CSS dependency upgrades.
- **Alternatives Considered:**
  - Tailwind CSS
  - Bootstrap 5
  - Preprocessed Sass / SCSS
- **Rejected Alternatives:**
  - Tailwind CSS: Explicitly prohibited by project architecture; clutters Blade markup with thousands of utility classes and creates build fragility.
  - Bootstrap: Imposes heavy, dated visual semantics and unnecessary JavaScript plugins.
  - Sass/SCSS: Redundant given modern native CSS capabilities (nesting, custom properties, color-mix).
- **Authoritative Sources:** `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 5-J; `PHASE_6_7_BLADE_UI_VANILLA_JS_INTEGRATION.md`, Section 19.
- **Impact:** Clean, lightweight stylesheets bundled natively via Vite without third-party CSS packages.
- **Dependencies:** DEC-001, DEC-045.

---

### DEC-047 — Atomic Blade Component Tree & Layout Composition Standard
- **Status:** Approved
- **Phase:** 6.7 (Blade / UI / Vanilla JavaScript Integration Architecture)
- **Date / Context:** Defining the server-rendered template hierarchy and reusable UI component architecture.
- **Decision:** Structure the user interface using a composable, atomic Blade component tree categorized into `layouts/` (`app`, `auth`, `sidebar`, `topbar`), `ui/` (`button`, `card`, `table`, `badge`, `alert`, `modal`, `empty-state`), `forms/` (`input`, `select`, `checkbox`, `textarea`, `file-input`), and domain-specific `marks/` (`mark-cell`, `status-legend`). Components render complete semantic HTML5 markup with integrated accessibility attributes and automated Laravel error feedback.
- **Why Chosen:** Guarantees absolute UI consistency across all 23 domain entities, enforces DRY principles, centralizes accessibility attributes (`aria-describedby`, `:focus-visible`), and allows server-rendered pages to assemble rapidly without client-side rendering overhead.
- **Alternatives Considered:**
  - Raw Blade views with duplicated inline HTML elements
  - Monolithic page templates without components
  - Blade view partials (`@include`) exclusively
- **Rejected Alternatives:**
  - Raw HTML duplication: Leads to visual inconsistencies and brittle form updates.
  - View partials exclusively: Lacks slot projection, typed component attributes, and default prop fallbacks offered by Blade classless components.
- **Authoritative Sources:** `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 5-I; `PHASE_6_3_LARAVEL_PROJECT_FOLDER_STRUCTURE.md`, Section 21; `PHASE_6_7_BLADE_UI_VANILLA_JS_INTEGRATION.md`, Section 17.
- **Impact:** All operational screens consume standardized Blade components from `resources/views/components/`.
- **Dependencies:** DEC-030, DEC-045, DEC-046.

---

### DEC-048 — Vanilla JavaScript Progressive Enhancement & Event-Driven Architecture
- **Status:** Approved
- **Phase:** 6.7 (Blade / UI / Vanilla JavaScript Integration Architecture)
- **Date / Context:** Defining the scope, runtime, and constraints of client-side scripting.
- **Decision:** Client-side scripting is strictly restricted to native ES modules (`resources/js/modules/`) operating via HTML5 data attributes (`data-mark-grid`, `data-modal`, `data-cascading-select`) and event delegation. Vanilla JavaScript is utilized purely for progressive micro-interactions (spreadsheet keyboard traversal, dirty-state tracking, batch save fetch dispatch, modal focus traps, and cascading dropdown population). JavaScript holds zero security authority and zero calculation authority; all domain rules, max-mark caps, and authorization policies remain exclusively server-side. SPA frameworks (React, Vue, Angular, Svelte, Inertia.js, Livewire, Alpine.js, jQuery) are strictly barred.
- **Why Chosen:** Preserves the bulletproof security model of server-rendered Laravel applications, eliminates client-side state synchronization bugs, ensures instant page loads with minimal JavaScript payloads, and guarantees full compliance with strict Content Security Policy (CSP Level 3) requirements with zero inline scripts.
- **Alternatives Considered:**
  - Livewire / Alpine.js stack
  - Vue.js / Inertia.js hybrid
  - Single monolithic `app.js` file with global functions
- **Rejected Alternatives:**
  - Livewire/Alpine: Adds runtime weight, server roundtrip overhead for simple DOM interactions, and creates dependency vulnerabilities.
  - Vue/Inertia: Drifts toward SPA architecture and separates frontend state from backend Eloquent models.
  - Monolithic global script: Pollutes global namespace and hinders modular maintainability.
- **Authoritative Sources:** `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 5-K; `PHASE_6_5_AUTHENTICATION_AUTHORIZATION_SECURITY.md`, Section 4; `PHASE_6_7_BLADE_UI_VANILLA_JS_INTEGRATION.md`, Section 20.
- **Impact:** Fast, modular client scripts bundled via Vite that progressively enhance server-rendered Blade templates.
- **Dependencies:** DEC-001, DEC-030, DEC-039, DEC-043.

---

### DEC-049 — Browsershot / Headless Chromium PDF Rendering Engine Selection
- **Status:** Approved
- **Phase:** 6.8 (PDF Generation & File Storage Architecture)
- **Date / Context:** Selecting the concrete rendering engine implementing `ReportGeneratorContract`.
- **Decision:** Select Spatie Browsershot (Headless Chromium / Puppeteer) as the primary PDF compilation engine for student report cards. Eliminate Dompdf due to its inability to reliably handle dynamic multi-page tables, auto-sizing dynamic assessment columns, and modern W3C Paged Media CSS specifications (`@page`, repeated `<thead>`).
- **Why Chosen:** Browsershot provides pixel-perfect rendering parity with modern desktop Chromium, accurately compiles complex A4 matrices across dynamic term sequences without header clipping, and cleanly supports localized print CSS and Figtree typography.
- **Alternatives Considered:**
  - Dompdf (`barryvdh/laravel-dompdf`)
  - Snappy (`knplabs/knp-snappy` / `wkhtmltopdf`)
- **Rejected Alternatives:**
  - Dompdf: Severe CSS 2.1 limitations; broken multi-page table pagination, header overlaps, and lack of modern layout primitives.
  - Snappy / wkhtmltopdf: Deprecated, unmaintained engine with critical rendering and security vulnerabilities.
- **Authoritative Sources:** `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 5; `PHASE_6_8_PDF_GENERATION_FILE_STORAGE_ARCHITECTURE.md`, Section 8.
- **Impact:** Application invokes Browsershot behind `ReportGeneratorContract`; requires Node.js and Chromium on host environments.
- **Dependencies:** DEC-001, DEC-030.

---

### DEC-050 — Concurrency-Safe Student Placement Row-Locking Revision Allocation
- **Status:** Approved
- **Phase:** 6.8 (PDF Generation & File Storage Architecture)
- **Date / Context:** Preventing revision number collisions during simultaneous report generation requests.
- **Decision:** Allocate new report revision numbers within a database transaction by acquiring an exclusive pessimistic row lock on the parent placement record: `SELECT id FROM student_academic_records WHERE id = ? FOR UPDATE`. While holding this lock, query `SELECT COALESCE(MAX(revision_number), 0) + 1` for the target report identity context.
- **Why Chosen:** Serializes concurrent report generations for the same student placement, guaranteeing strictly monotonic revision numbering (`Revision 1`, `Revision 2`, ...) without race conditions, and works strictly within the existing 23-table schema backed by PostgreSQL unique index `uk_gr_revision_identity`.
- **Alternatives Considered:**
  - Optimistic locking with automatic retries only
  - Standalone revision sequence counter table
- **Rejected Alternatives:**
  - Optimistic locking only: Wastes heavy PDF compilation CPU cycles when collisions occur on final commit.
  - New counter table: Violates the fixed 23-table schema boundary.
- **Authoritative Sources:** `BRD V1.3` (BR-059, BR-060, CL-004); `database/Postgres/schema.sql`; `PHASE_6_8_PDF_GENERATION_FILE_STORAGE_ARCHITECTURE.md`, Section 13.
- **Impact:** `ReportGenerationService` executes pessimistic locking before resolving revision paths and writing records.
- **Dependencies:** DEC-007, DEC-023, DEC-030.

---

### DEC-051 — Two-Phase Atomic Filesystem-to-Database PDF Storage Protocol
- **Status:** Approved
- **Phase:** 6.8 (PDF Generation & File Storage Architecture)
- **Date / Context:** Eliminating orphaned files and broken database pointers across disparate filesystem and database storage boundaries.
- **Decision:** Mandate a two-phase staging protocol: (1) Render the PDF to a private temporary staging directory (`storage/app/private/temp/reports/{uuid}.pdf`) and validate that file size is $> 0$ bytes and header matches `%PDF-`. (2) Begin database transaction, acquire placement lock, promote temporary PDF to immutable final path (`storage/app/private/reports/{year}/{class}/{sec}/{record}/{type}/revision-{n}.pdf`), insert `generated_reports` row, record audit log, and commit transaction. If the transaction fails, roll back database and delete newly promoted file, preserving historical revisions intact.
- **Why Chosen:** Bridges the non-transactional nature of filesystem operations with PostgreSQL relational transactions, preventing partial PDF writes, missing file references, and corrupted revision histories.
- **Alternatives Considered:**
  - Direct rendering directly into final destination path before database commit
  - Storing PDF binary blobs directly inside PostgreSQL database
- **Rejected Alternatives:**
  - Direct rendering to final path: Leaves corrupt files on disk if the rendering process crashes midway.
  - Database binary storage: Bloats database backups, degrades I/O performance, and violates relational storage guidelines.
- **Authoritative Sources:** `PHASE_6_3_LARAVEL_PROJECT_FOLDER_STRUCTURE.md`, Section 27; `PHASE_6_8_PDF_GENERATION_FILE_STORAGE_ARCHITECTURE.md`, Section 14.
- **Impact:** Zero orphaned files and zero broken database pointers across all report generation workflows.
- **Dependencies:** DEC-007, DEC-023, DEC-050.

---

### DEC-052 — Dedicated PDF Blade Rendering Canvas & Inline Print CSS Architecture
- **Status:** Approved
- **Phase:** 6.8 (PDF Generation & File Storage Architecture)
- **Date / Context:** Establishing template hierarchy, CSS delivery, and SSRF prevention for PDF generation.
- **Decision:** Compile PDFs using a dedicated Blade template hierarchy (`resources/views/reports/pdf/`) completely isolated from the web application shell (`layouts/app.blade.php`). Inline raw print CSS (`report-print.css`) and base64 image strings (`school_logo_path`) directly into the HTML document, blocking all outbound network access during Chromium compilation.
- **Why Chosen:** Guarantees zero extraneous web navigation elements appear in official report cards, eliminates SSRF vulnerabilities, ensures deterministic A4 page layouts, and eliminates network latency during Headless Chromium execution.
- **Alternatives Considered:**
  - Reusing standard web views with `@media print` stylesheets
  - Serving CSS and logo assets via internal HTTP localhost URLs to Chromium
- **Rejected Alternatives:**
  - Reusing web views: Pollutes PDF DOM with unneeded web JavaScript, navigation, and application buttons.
  - HTTP localhost URLs: Introduces SSRF attack vectors and fails in containerized environments where localhost bindings differ.
- **Authoritative Sources:** `PHASE_6_1_TECHNOLOGY_BASELINE.md`, Section 5; `PHASE_6_8_PDF_GENERATION_FILE_STORAGE_ARCHITECTURE.md`, Section 9 & 10.
- **Impact:** Fast, sandboxed, and secure PDF compilation with zero external HTTP dependencies.
- **Dependencies:** DEC-045, DEC-046, DEC-049.

---

### DEC-053 — Canonical Private Storage Disk Root & Reconciled Promotion Architecture
- **Status:** Approved
- **Phase:** 6.8 / 6.9 (Storage & Testing Architecture)
- **Date / Context:** Reconciling private filesystem disk root configuration with stored database paths and temporary staging paths to eliminate path nesting bugs (`reports/reports/...`).
- **Decision:** Configure the canonical private storage disk `private_reports` with root `storage_path('app/private')`. Stored report file paths in `generated_reports.file_path` are relative to this root: `reports/{academic_year_id}/{class_id}/{section_id}/{student_academic_record_id}/{report_type}/revision-{n}.pdf`. Temporary rendering artifacts are staged on the same disk under `temp/reports/{uuid}.pdf`. Promotion uses `Storage::disk('private_reports')->move($tempPath, $finalPath)`.
- **Why Chosen:** Eliminates duplicate directory nesting, matches `generated_reports.file_path` values directly to the Laravel disk contract, ensures local same-filesystem atomic renames, and provides fallback stream copy + unlink for cross-partition environments.
- **Alternatives Considered:**
  - Setting disk root to `storage_path('app/private/reports')` and omitting `reports/` from database paths
  - Using two separate configured disks (`local_temp` and `private_reports`)
- **Rejected Alternatives:**
  - Omitting `reports/` from database paths: Breaks consistency with existing Phase 6.3 and Phase 6.6 controller contracts that expect `reports/...` paths.
  - Two separate disks: Prevents native local atomic renames and complicates cross-disk cleanup logic.
- **Authoritative Sources:** `PHASE_6_3_LARAVEL_PROJECT_FOLDER_STRUCTURE.md`, Section 27; `PHASE_6_8_PDF_GENERATION_FILE_STORAGE_ARCHITECTURE.md`, Section 15.
- **Impact:** `ReportGenerationService` and `GeneratedReportDownloadController` interact with `Storage::disk('private_reports')` with zero path ambiguity.
- **Dependencies:** DEC-051.

---

### DEC-054 — Two-Phase Persistence Protocol & Compensating Rollback Architecture
- **Status:** Approved
- **Phase:** 6.8 / 6.9 (Storage & Testing Architecture)
- **Date / Context:** Establishing failure recovery boundaries and eliminating claims of literal ACID atomicity across PostgreSQL and local filesystem storage.
- **Decision:** Replace literal DB/filesystem atomicity claims with a failure-safe two-phase persistence protocol with compensating rollback actions. Heavy Chromium PDF rendering executes in Phase 1 outside the database transaction. In Phase 2, the placement row is locked `FOR UPDATE`, the file is promoted, and the database record is inserted. If database insertion fails, a compensating catch block deletes the newly promoted file while strictly preserving all historical revisions intact (`revision-1.pdf` through `revision-(N-1).pdf`). Unreferenced files from power failures are detected via `php artisan reports:reconcile-storage`.
- **Why Chosen:** Reflects real-world distributed transaction realities where filesystems cannot roll back transactions alongside PostgreSQL, eliminating orphaned database rows, preventing corrupted revisions, and safeguarding historical academic records.
- **Alternatives Considered:**
  - Holding PostgreSQL transaction locks during the entire Chromium render
  - Blindly relying on Laravel DB transactions without filesystem rollback handlers
- **Rejected Alternatives:**
  - Holding locks during render: Causes thread starvation and catastrophic lock contention ($1\text{s}-3\text{s}$ holds).
  - Blind DB transactions: Leaves orphaned physical PDFs on disk if SQL insert throws unique constraint or foreign key errors.
- **Authoritative Sources:** `PHASE_6_8_PDF_GENERATION_FILE_STORAGE_ARCHITECTURE.md`, Section 14; `database/Postgres/schema.sql`.
- **Impact:** Robust, crash-resilient report card generation with zero data corruption and zero loss of historical records.
- **Dependencies:** DEC-050, DEC-051, DEC-053.

---

### DEC-055 — Headless Chromium Native Template Header/Footer Pagination Strategy
- **Status:** Approved
- **Phase:** 6.8 / 6.9 (PDF & Print Architecture)
- **Date / Context:** Resolving real-world Headless Chromium limitations regarding CSS Paged Media `@page` margin box page counters (`@bottom-right { content: counter(page); }`).
- **Decision:** Acknowledge that Blink/Chromium does not support CSS Paged Media Level 3 margin box counters. Page numbering and institutional footer metadata must be compiled via Puppeteer / Browsershot's native header/footer template system (`->showBrowserHeaderAndFooter()`) using standard HTML template classes (`<span class="pageNumber"></span> of <span class="totalPages"></span>`).
- **Why Chosen:** Chromium natively supports page numbers and total page counts only through its Puppeteer printing bridge; attempting to use pure CSS `@page` counters in Chromium produces blank footers.
- **Alternatives Considered:**
  - Relying on pure CSS `@page` margin box counters
  - Injecting page numbers via client JavaScript DOM manipulation
- **Rejected Alternatives:**
  - Pure CSS `@page` counters: Completely ignored by Chromium Blink engine.
  - Client JS DOM manipulation: Cannot accurately know page breaks prior to print pagination.
- **Authoritative Sources:** `PHASE_6_8_PDF_GENERATION_FILE_STORAGE_ARCHITECTURE.md`, Section 10; Chromium Blink Print Specifications.
- **Impact:** Report cards display accurate, reliable page counts across multi-page tables under automated visual testing.
- **Dependencies:** DEC-049, DEC-052.

---

### DEC-056 — Multi-Tier Testing Pyramid, Invariant Assertions & Zero-Tolerance Quality Gate Architecture
- **Status:** Approved
- **Phase:** 6.9 (Application Testing & Quality Gate Strategy)
- **Date / Context:** Establishing the testing philosophy, verification layers, and release-blocking quality gates for the application.
- **Decision:** Mandate a multi-tier test pyramid (Unit, Domain Services, Database Integration against real PostgreSQL, HTTP/Feature, Authorization Scope, Concurrency, Filesystem Failure/Compensating Rollback, Browser/UI, PDF Rendering, Visual Regression, and E2E Acceptance). Reject database mocking for schema constraints. Enforce a single, unified Quality Gate structure (Gate A through Gate W) covering all 23 architectural verification dimensions with zero-tolerance release-blocking criteria for authorization bypass, mark state coercion (0 != blank != absent), attendance division by zero, duplicate revisions, or unapproved data fields (admission numbers, annual percentages, grade bands).
- **Why Chosen:** Guarantees end-to-end correctness, schema fidelity, historical immutability, and role-scoped security before any application code deployment.
- **Alternatives Considered:**
  - Mocking the database in unit tests only
  - Relying solely on manual exploratory testing
- **Rejected Alternatives:**
  - Database mocking: Conceals real-world PostgreSQL CHECK constraint, trigger, and unique index behaviors.
  - Manual testing: Error-prone, non-repeatable, and incapable of detecting concurrency race conditions.
- **Authoritative Sources:** `BRD V1.3`; `database/Postgres/schema.sql`; `PHASE_6_1` through `PHASE_6_8`; `PHASE_6_9_APPLICATION_TESTING_QUALITY_GATE_STRATEGY.md`.
- **Impact:** Comprehensive test suite with deterministic fixtures, reproducible assertions, unified Gate A through Gate W criteria, and automated CI gatekeeping.
- **Dependencies:** DEC-001 through DEC-055.

---

### DEC-057 — Production Environment Configuration, Secret Isolation & Operational Hardening Contract
- **Status:** Approved
- **Phase:** 6.10 (Environment Configuration & Deployment Readiness)
- **Date / Context:** Establishing the production configuration contract, zero-secrets policy, and operational hardening standards before application deployment.
- **Decision:** Mandate that production deployments enforce `APP_ENV=production` and `APP_DEBUG=false`. Secrets (`APP_KEY`, database passwords) must remain strictly external to source control and are provisioned solely via `.env` or system environment variables. Production web execution uses a dedicated PostgreSQL application role (`school_app_user`) restricted strictly to DML operations (`SELECT`, `INSERT`, `UPDATE` where permitted) without DDL (`CREATE`, `ALTER`, `DROP`) or `SUPERUSER` privileges. DDL migrations and schema operations are executed via a separated deployment pipeline account.
- **Why Chosen:** Prevents catastrophic credential leaks in version control, eliminates exposure of server paths, stack traces, and database schemas on unhandled exceptions, and prevents web-layer SQL injection or compromise from altering the relational structure.
- **Alternatives Considered:**
  - Allowing the web application to connect as PostgreSQL `postgres` superuser
  - Embedding encrypted production secrets in the repository
- **Rejected Alternatives:**
  - Web superuser: Violates principle of least privilege; allows any application vulnerability to destroy tables or bypass security controls.
  - Repository secrets: Creates permanent leakage risk in git history.
- **Authoritative Sources:** `PHASE_6_5_AUTHENTICATION_AUTHORIZATION_SECURITY.md`; `database/Postgres/schema.sql`; `PHASE_6_10_ENVIRONMENT_CONFIGURATION_DEPLOYMENT_READINESS.md`.
- **Impact:** Secure runtime execution with complete protection of credentials and database structural integrity.
- **Dependencies:** DEC-031, DEC-033, DEC-056.

---

### DEC-058 — Production Content Security Policy & Strict Security Response Headers Architecture
- **Status:** Approved
- **Phase:** 6.10 (Environment Configuration & Deployment Readiness)
- **Date / Context:** Establishing browser security boundaries, clickjacking defense, and anti-XSS mitigation for the server-rendered Blade application.
- **Decision:** Enforce a strict, zero-inline-script Content Security Policy (CSP) and HTTP response headers at the application and web server layer:
  - `default-src 'self'`
  - `script-src 'self'` (zero `'unsafe-inline'`, zero third-party CDNs; loads only versioned compiled ES modules from Vite)
  - `style-src 'self'` (plain CSS loaded from compiled Vite bundles)
  - `img-src 'self' data:` (allows dynamic local base64-inlined school crest logos)
  - `font-src 'self'`
  - `object-src 'none'`
  - `frame-ancestors 'none'` (anti-clickjacking)
  - `base-uri 'self'`
  - `form-action 'self'`
  - Response headers: `Strict-Transport-Security: max-age=31536000; includeSubDomains`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()`.
- **Why Chosen:** Complements the Phase 6.7 Vanilla JS architecture (which eliminated all inline event handlers and inline `<script>` tags), completely neutralizing Cross-Site Scripting (XSS) vectors and clickjacking attacks.
- **Alternatives Considered:**
  - Using `script-src 'unsafe-inline'` for convenience
  - Relying solely on framework HTML escaping
- **Rejected Alternatives:**
  - `unsafe-inline`: Disables browser XSS protections and contradicts Phase 6.7 UI architecture.
  - HTML escaping alone: Leaves the application vulnerable to browser extension injection or DOM-based injection.
- **Authoritative Sources:** `PHASE_6_5_AUTHENTICATION_AUTHORIZATION_SECURITY.md`, Section 13; `PHASE_6_7_BLADE_UI_VANILLA_JS_INTEGRATION.md`, Section 20; `PHASE_6_10_ENVIRONMENT_CONFIGURATION_DEPLOYMENT_READINESS.md`.
- **Impact:** Hardened client-side execution environment with zero tolerance for external malicious script injection.
- **Dependencies:** DEC-045, DEC-046, DEC-052.

---

### DEC-059 — Operational Private PDF Storage Permissions & Dual-Domain Backup Architecture
- **Status:** Approved
- **Phase:** 6.10 (Environment Configuration & Deployment Readiness)
- **Date / Context:** Operationalizing the private filesystem storage boundary and disaster recovery readiness for generated student report cards.
- **Decision:** Mandate that all report card artifacts reside strictly on the private disk root (`storage_path('app/private')`) with filesystem permissions restricted to the web server application user (`0750` on Linux / restrictive ACLs on Windows). Public symlinking (`storage:link`) for reports is strictly prohibited. Define disaster recovery as a **dual-domain persistence requirement**: any operational backup must capture both the PostgreSQL relational database dump (`pg_dump`) AND the physical report storage directory (`storage/app/private/reports/`). A database-only or file-only backup is officially declared invalid and incomplete.
- **Why Chosen:** Protects sensitive student academic records from unauthorized public access, prevents path traversal leakage, and ensures that catastrophic system failure recovery restores both report metadata and the exact historical PDF artifacts.
- **Alternatives Considered:**
  - Storing PDFs under `public/storage/reports`
  - Backing up only the database under the assumption that PDFs can be re-rendered
- **Rejected Alternatives:**
  - Public storage: Exposes student records via static URL guessing.
  - Database-only backup: Re-rendering historical reports upon restore would increment revision numbers or fail if underlying student data has historical nuances, violating report immutability.
- **Authoritative Sources:** `BRD V1.3` (BR-067, BR-068); `PHASE_6_8_PDF_GENERATION_FILE_STORAGE_ARCHITECTURE.md`, Section 14–16; `PHASE_6_10_ENVIRONMENT_CONFIGURATION_DEPLOYMENT_READINESS.md`.
- **Impact:** Absolute privacy of academic records and guaranteed fidelity across system restoration scenarios.
- **Dependencies:** DEC-051, DEC-053, DEC-054.

---

### DEC-060 — Laravel Migration Repository Isolation via Dedicated `meta` Schema
- **Status:** Approved
- **Phase:** 7.0 (Laravel Project Foundation & PostgreSQL Integration)
- **Date / Context:** Establishing Laravel 13 database migration infrastructure connected to PostgreSQL 18 while preserving the approved, closed 23-table domain schema without structural drift.
- **Decision:** Isolate Laravel's internal migration tracking repository table (`migrations`) within a dedicated PostgreSQL schema (`meta.migrations`), configured via `config('database.migrations.table') = 'meta.migrations'`. This guarantees that the default `public` schema contains exclusively the 23 approved domain tables, ensuring 100% fidelity to the validated PostgreSQL database specification and preventing false assertion failures in schema validation suites that count base tables in `public`.
- **Why Chosen:** The project requirements explicitly mandate exactly 23 tables in the domain model with zero invented persistence tables in the business schema. Housing migration metadata in `meta.migrations` cleanly separates infrastructure state from domain state, satisfies strict database structural assertions (e.g. `validate_schema.sql` Scenario 38), and prevents schema dump pollution.
- **Alternatives Considered:**
  - Placing `migrations` in the `public` schema (`public.migrations`)
  - Suppressing or relaxing the 23-table count assertions in the validation suite
- **Rejected Alternatives:**
  - `public.migrations`: Pollutes the domain schema and results in 24 base tables in `public`, violating the approved 23-table specification and failing the Phase 5 schema assertion runner.
  - Relaxing validation assertions: Violates the core governance rule that migration layers must never alter approved database design or validation criteria.
- **Authoritative Sources:** `database/Postgres/schema.sql`; `database/Postgres/validation/validate_schema.sql`; Phase 7 Specification, Section 6.
- **Impact:** Absolute domain schema purity in `public`, deterministic reproducibility, and zero schema drift across migrations.
- **Dependencies:** DEC-007, DEC-008, DEC-029.

---

### DEC-061 — Lifecycle Synchronization Hook for Isolated `meta.migrations` Schema
- **Status:** Approved
- **Phase:** 8.0 (Phase 7 Finding B Resolution & Migration Hardening)
- **Date / Context:** Ensuring deterministic behavior when executing `migrate:fresh`, `db:wipe`, and rollback cycles with PostgreSQL custom schemas.
- **Decision:** In PostgreSQL, Laravel's `db:wipe` command drops base tables located within the connection search path (`public`) but does not drop custom non-default schemas (such as `meta`). To ensure deterministic fresh migrations without leaving stale migration history in `meta.migrations`, `AppServiceProvider` listens to the `Illuminate\Database\Events\DatabaseWiped` event and executes `DROP SCHEMA IF EXISTS meta CASCADE; CREATE SCHEMA IF NOT EXISTS meta;`. In addition, the initial migration (`2026_09_18_000001_create_enums_and_trigger_function.php`) applies idempotent `DROP TYPE IF EXISTS ... CASCADE;` pre-drops matching `database/Postgres/schema.sql`.
- **Why Chosen:** Guarantees that `migrate:fresh --seed` and `db:wipe` completely clear both the business domain tables and the migration tracking ledger, preventing "Nothing to migrate" false skips on fresh runs while preserving the isolation of `meta.migrations` and leaving `public` with exactly 23 domain tables.
- **Alternatives Considered:**
  - Moving `migrations` table back to `public`
  - Requiring developers to manually execute raw psql drops prior to `migrate:fresh`
- **Rejected Alternatives:**
  - Moving back to `public`: Pollutes the business schema and violates DEC-060.
  - Manual psql scripts: Fragile, error-prone, and breaks standard artisan workflows.
- **Authoritative Sources:** Phase 7 Audit Report, Section 12; Phase 8 Implementation Instructions, Section 3.
- **Impact:** Seamless, deterministic execution of `php artisan migrate:fresh --seed` across all environments.
- **Dependencies:** DEC-060.

---

### DEC-071: Database Persistence, Environment Isolation, and Protection of `school_report_card`
- **Phase:** 9.0 (Data Persistence, Database Roles & AI Environment Safety)
- **Date / Context:** Investigation into intermittent loss of user credentials and business data following AI implementation runs.
- **Decision:** Establish strict, permanent tripartite database role separation and implement fail-closed software guards against destructive operations:
  1. `school_report_card`: The **REAL LOCAL APPLICATION DATABASE**. Holds persistent development data, configured users, and live credentials. `php artisan migrate:fresh`, `db:wipe`, `migrate:rollback`, `migrate:reset`, and `migrate:refresh` are strictly **FORBIDDEN** against this database. Guarded by `DB::prohibitDestructiveCommands(true)` and fail-closed runtime listeners in `AppServiceProvider`.
  2. `school_report_card_audit`: The **DEDICATED AUTOMATED TEST & VALIDATION DATABASE**. Configured exclusively in `phpunit.xml`. Tests and validation runners may reset this database, but automated tests must abort if connected to `school_report_card`.
  3. `school_report_card_baseline`: Dormant reference database with 0 code references. Kept preserved until explicit user approval for deletion.
  4. PostgreSQL validation scripts (`run_all.sql`, `validate_schema.sql`, `phase5_real_world_validation.sql`) contain explicit PL/pgSQL guards preventing execution against `school_report_card`.
- **Why Chosen:** Solves the root cause of credential and data loss caused by accidental execution of teardown SQL scripts and `migrate:fresh` during AI development cycles.
- **Alternatives Considered:** Relying on developer/AI discipline without code guards.
- **Rejected Alternatives:**
  - Unenforced rules: Repeatedly failed because AI agents default to executing `migrate:fresh` or validation runners against the default `.env` database.
- **Authoritative Sources:** Database Persistence Investigation Report; Project Rules Section 1-32.
- **Impact:** Permanent protection of application credentials and business data across all AI and developer workflows.
- **Dependencies:** DEC-060, DEC-070.

---

### DEC-072: Student Master Identity / Admission Number Requirement Reconciliation
- **Phase:** 10.0 (Student Management, Admission Number & Student Import)
- **Date / Context:** Business requirement confirmation establishing `admission_number` as the unique student business identifier across manual creation, editing, directory search, and CSV import workflows.
- **Previous Rule:** Phase 6 specifications (`PHASE_6_6`, `PHASE_6_7`, `PHASE_6_8`, `PHASE_6_9`) explicitly prohibited admission numbers, defining student master identity solely by `student_name` and internal technical primary key `id`. Re-import was previously specified as creating a new student without matching existing records.
- **Newly Approved Requirement:** `admission_number` is the unique student business identifier across the system.
- **Decision:**
  1. **Schema Definition:** Table `public.students` receives `admission_number VARCHAR(50) NOT NULL` with a global database uniqueness constraint `uk_students_admission_number UNIQUE (admission_number)` and index `idx_students_admission_number`.
  2. **Technical vs Business Identity Separation:** `students.id` (BIGINT GENERATED BY DEFAULT AS IDENTITY) remains the technical primary key and foreign key target for all relational dependencies (`student_academic_records`, etc.). `admission_number` is the human/business identifier and does not replace numeric foreign keys.
  3. **Immutability & Reservation:** `admission_number` is mandatory, trimmed of whitespace, immutable upon creation (read-only during student updates), and permanently reserved (never reused even if a student is withdrawn or transferred out).
  4. **Closed 23-Table Model Preserved:** No secondary student identity or mapping table is created. Exactly 23 business tables remain in the schema.
  5. **Historical Placement & Marks Isolation:** `admission_number` identifies the student master. Academic placement remains historically tracked in `student_academic_records` (`roll_number` remains placement-scoped). Historical marks, attendance, and reports remain attached to their respective placement rows and are never migrated or overwritten.
  6. **CSV Import Contract & Identity Matching:**
     - Contextual upload: The upload form supplies the placement context (`academic_year_id`, `class_id`, `section_id`).
     - CSV format: Exactly `admission_number,student_name,roll_number`.
     - Matching Rule: If `admission_number` does not exist, a new master student + placement is created. If `admission_number` exists and matches the stored `student_name`, the existing master student (`students.id`) is reused and assigned the placement in the target context. If an imported `admission_number` exists but the CSV `student_name` differs, or if duplicate admission numbers appear in the same CSV, the conflicting rows are rejected with clear error reporting.
- **Why Chosen:** Provides definitive, unambiguous business identity matching for school administration while strictly preserving the 23-table model, historical academic placements, and relational integrity.
- **Authoritative Sources:** Phase 10 Final Implementation Contract; Approved Phase 10 Requirement Reconciliation.
- **Impact:** Aligns student master management and CSV import with standard school administration operations while safeguarding historical records.
- **Dependencies:** DEC-014, DEC-060, DEC-071.

---

### DEC-073: Teacher & Staff User Management and Mixed Assignment Architecture
- **Phase:** 9.5 (Teacher & Staff User Management)
- **Date / Context:** Establishing post-installation User Account Management and Teacher Assignment Management workflows.
- **Decision:**
  1. **Strict Separation of User Accounts and Teacher Assignments:** Creating a teacher user account does NOT automatically grant classroom or subject access. Conversely, assigning a teacher does NOT mutate the user's role.
  2. **Role & Authority Boundaries:**
     - User Account Management (`/users`): Restricted strictly to Administrator (`UserPolicy`). Office Staff, Subject Teachers, and Class Teachers receive HTTP 403. Self-deactivation and deactivating the last remaining active Administrator are prohibited. User hard deletion is forbidden.
     - Teacher Assignment Management (`/teacher-assignments`): Authorized for Administrator and Office Staff (`TeacherAssignmentPolicy`). Teachers receive HTTP 403 and cannot manage their own or other teachers' assignments.
  3. **Mixed Teacher Assignment Model:** A single teacher account (with role `Class Teacher` or `Subject Teacher`) may simultaneously hold multiple active contextual assignments across different classes and sections:
     - Class Teacher assignment (`subject_id = NULL`): Grants scope over all applicable subjects for that class/section.
     - Subject Teacher assignment (`subject_id = <ID>`): Grants scope strictly over the designated subject for that class/section.
     - Assignment semantics are determined by the assignment row (`subject_id IS NULL` vs `subject_id IS NOT NULL`), NOT restricted by the user's account role.
  4. **Live, Assignment-Based Authorization:** `TeacherAuthorizationService` resolves active assignments (`is_active = true`, `effective_from <= now()`, `effective_to IS NULL OR >= now()`) dynamically from PostgreSQL without caching in the user session. Changes in assignments take immediate effect without requiring relogin.
  5. **Separate Lifecycle Controls:** User deactivation terminates authentication entirely; assignment deactivation immediately revokes only that specific classroom/subject contextual scope while permitting login.
  6. **Zero Schema Migration:** All workflows utilize the existing approved 23-table schema (`users`, `roles`, `teacher_assignments`) without adding new tables, permissions, or migrations.
- **Why Chosen:** Fulfills real-world school staffing needs where teachers frequently teach specific subjects in some grades while serving as Class Teachers in others, while upholding the zero-trust authorization pipeline and immutable database protections.
- **Authoritative Sources:** Phase 9.5 Final Implementation Instruction; BRD V1.3; Phase 8 Authentication Architecture.
- **Impact:** Clean, auditable staff administration with zero credential leaks and robust contextual permission evaluation.
- **Dependencies:** DEC-007, DEC-018, DEC-060, DEC-071.

---

### DEC-074: Annual Exam Assessment Total Percentage, Final Overall Result Sourcing, and Dynamic Exam Subject Applicability
- **Phase:** 14.0 (Annual Exam Assessment Total, Final Overall Result & Dynamic Exam Subject Applicability)
- **Date / Context:** Clarification of BR-064 ("No Overall Annual Percentage"), Final Report overall outcome governance, and Exam report subject applicability resolution.
- **Decision:**
  1. **Annual Exam Assessment Total Percentage (Assessment-Level Only):** The total row of the Annual Exam assessment table in the Final Comprehensive Report displays the total percentage achieved across its applicable subjects, evaluated via `CalculationService` using the class's configured calculation method (Method 1: equal average of percentages; Method 2: sum obtained / sum max * 100).
  2. **Preservation of BR-064 Prohibition on Academic-Year Composite:** BR-064's prohibition against calculating or displaying an academic-year composite percentage across dynamic terms (e.g., averaging Term 1, Term 2, and Term 3 together) remains strictly intact. No academic-year overall percentage, annual GPA, or student rank is computed or shown.
  3. **Annual Exam Aggregate Result:** If all applicable Annual Exam subjects meet or exceed `school_settings.pass_mark`, the Annual Exam aggregate result is `PASS`. If any applicable Annual Exam subject fails, it is `FAIL`. If any mark is unentered/blank, it is `Incomplete`.
  4. **Final Report Overall Result Sourcing:** When a non-term Annual Exam assessment is configured in a Final Report, the report's `Overall Result` represents the student's Annual Exam outcome exclusively. Historical term subject results (including any prior failures in Term 1, Term 2, or Term 3) remain fully displayed in the Term Reports Summary table for academic record fidelity, but do not override or cause the Final Report Overall Result to become `FAIL` if the student passed the Annual Examination.
  5. **Dynamic Exam/Mid-Term Subject Applicability:** For `ReportType::EXAM`, the report subject roster is strictly derived from the active `AssessmentApplicability` records configured for the displayed assessment(s) intersected with the student's active subject allocations. Non-applicable subjects are excluded from subject rows, maximum mark aggregations, and completion evaluations. Phantom maximum marks (e.g. 100-mark defaults for unconfigured subjects) and N/A row clutter are strictly prohibited.
- **Why Chosen:** Resolves user-reported report discrepancies where Annual Exam totals were missing percentage/result badges, multi-term failures incorrectly failed passing graduates, and mid-term tests displayed unrelated class subjects.
- **Authoritative Sources:** BRD V1.3 (BR-019, BR-020, BR-021, BR-044, BR-064, BR-066); Phase 14 Approved Implementation Plan.
- **Impact:** Accurate, configuration-driven Annual Comprehensive and Mid-Term report cards with zero database schema alterations.
- **Dependencies:** DEC-007, DEC-020, DEC-021, DEC-024, DEC-071.



