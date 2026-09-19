# PHASE 6.1 — TECHNOLOGY BASELINE & ARCHITECTURE PRINCIPLES

**Project:** School Examination Marks and Report Card Management System  
**Document Version:** 1.0  
**Phase:** 6.1 — Technology Baseline & Architecture Principles  
**Status:** Approved & Authoritative  

---

## 1. Purpose

The purpose of this document is to establish the authoritative technology baseline, runtime environment parameters, and core software architecture principles for the Laravel web application implementation of the **School Examination Marks and Report Card Management System**.

This document serves as the architectural foundation for all subsequent Phase 6 activities (domain modeling, service layer design, authentication/authorization specifications, route architecture, controller contracts, and UI integration). Every implementation choice made in later steps must conform to the principles and constraints established herein.

---

## 2. Relationship to Phase 6

Phase 6 governs the complete application architecture and technical blueprint prior to code generation. It is subdivided into sequential, disciplined steps:

- **Phase 6.1 (Current Step):** Technology Baseline & Architecture Principles (Defines versions, runtime environment, design philosophy, boundary rules, and non-negotiables).
- **Phase 6.2:** Domain & Eloquent Architecture (Mapping the 23-table PostgreSQL schema to Eloquent models, relationships, casts, and immutability rules).
- **Phase 6.3:** Service Layer & Business Logic Architecture (Designing application services for mark entry, calculation engines, transfer workflows, and report generators).
- **Phase 6.4:** Authentication, Multi-Assignment Authorization & Security Blueprint (Session auth, policy matrices, scope resolution, and audit pipelines).
- **Phase 6.5:** HTTP Layer, Routing, Form Requests & Controller Contracts (Route hierarchies, request validation, and thin controller contracts).
- **Phase 6.6:** Blade Component & UI Integration Architecture (Server-rendered layouts, atomic components, and Vanilla JS micro-interactions).
- **Phase 6.7:** PDF Generation & File Storage Blueprint (Revision storage, template rendering, and streaming contracts).
- **Phase 6.8:** Application Testing & Quality Gate Strategy (Unit, feature, authorization, and regression suites).
- **Phase 6.9:** Environment Configuration & Deployment Readiness (Environment variables, containerization/production guidelines).

**Scope Boundary for Phase 6.1:** This step defines the technical constraints, stack selections, and architecture rules. **No application code, migrations, models, controllers, routes, views, or packages are created in this phase.**

---

## 3. Authoritative Source Hierarchy

When resolving any design, modeling, or implementation ambiguity, all developers and automated agents must adhere to this strict precedence order:

1. **BRD V1.3** (`BRD/School_Examination_Marksheet_Requirements_v1_3.docx`) — Foundational functional and business requirements.
2. **Finalized Business Rules & Project Handover Decisions** — Authoritative clarifications, mark states, calculations, and role scopes.
3. **Approved 23-Table Database Relationship Design** (`DB design/DB-table-definitions.txt`) — Approved schema topology, constraints, and field definitions.
4. **Approved ERD** (`DB design/mermaid-diagram.png`) — Canonical relational graph and cardinalities.
5. **Current Validated PostgreSQL Implementation & Documentation** (`database/Postgres/schema.sql`, `seed.sql`, `validation/`, and `docs/POSTGRES_MIGRATION_WALKTHROUGH.md`) — Live, validated database foundation.

> [!IMPORTANT]
> **Historical Baseline Isolation:**  
> The `database/MySQL/` directory is an immutable historical reference baseline. The old Node.js / Express / EJS tech stack mentioned in early discovery documents is **completely obsolete**. The validated PostgreSQL 18 database represents the live data tier. The database design is closed: **no tables may be added, removed, merged, or split.**

---

## 4. Approved Application Stack

The application is structured as a robust, server-rendered Laravel web application:

```text
[ Browser / Client ]
         ↓  (HTTP/HTTPS Requests, HTML Form Submissions, AJAX)
[ Blade Views + Vanilla JS + Plain CSS (Vite Build Tooling) ]
         ↓
[ HTTP Routing & Global Web Middleware (CSRF, Session, Auth, TrimStrings) ]
         ↓
[ Route Middleware (Role Checks, Deactivation Guard, Scope Verification) ]
         ↓
[ Form Request Classes (Input Validation & Normalization) ]
         ↓
[ Thin HTTP Controllers (Coordination, Ingestion & Response Dispatch) ]
         ↓
[ Policy / Gate Checks (Contextual Assignment Scope Authorization) ]
         ↓
[ Application Service Layer (Calculations, Mark Lifecycles, Revisions, Auditing) ]
         ↓  (DB Transactions: Complete Atomicity)
[ Eloquent ORM Models & PostgreSQL Types (Timestamps, JSONB, Enums, Custom Casts) ]
         ↓
[ PostgreSQL 18 Database Engine (23 Tables, 43 Restrictive FKs, 7 CHECKs) ]
```

---

## 5. Version Baseline

### 5.1 PHP Target: PHP 8.4 / 8.5
- **Evaluated Range:** PHP 8.4 (Active/Current installed environment: `PHP 8.4.15 (cli) Visual C++ 2022 x64`) to PHP 8.5.
- **Decision:** **PHP 8.4 / 8.5 compatible codebase** (Minimum `^8.4`).
- **Rationale:** The local development workstation currently runs PHP 8.4.15 with all necessary PostgreSQL extensions (`pdo_pgsql`, `pgsql`, `mbstring`, `intl`, `bcmath`, `curl`, `openssl`, `sodium`, `zip`, `dom`, `libxml`). PHP 8.4 and PHP 8.5 are officially supported stable branches. PHP 8.4 property hooks, asymmetric visibility, and enhanced DOM manipulation provide ideal primitives for clean domain modeling.

### 5.2 Framework Target: Laravel 13 (or current stable 12/13)
- **Evaluated Target:** **Laravel 13** (`^13.0` / minimum PHP requirement 8.3+).
- **Decision:** **Laravel 13** as primary target (with backward compatibility for Laravel 12.x).
- **Rationale:** Laravel 13 represents the modern standard for Laravel releases starting in 2026, offering long-term support through 2028, native PHP attribute adoption, enhanced performance, and native modern testing pipelines.

### 5.3 Database Engine: PostgreSQL 18.x
- **Target:** **PostgreSQL 18.4 (x86_64-windows)**.
- **Decision:** **Preserve PostgreSQL 18.x without modification.**
- **Rationale:** The database layer has already completed comprehensive PASS 1 analysis, PASS 2 implementation, and PASS 2.1 cleanup. It has been validated through 70 automated assertions and 16 real-world scenarios covering 40 students, 8 subjects, 7 assessments, and 280 allocations. It is fully supported by Laravel via `pdo_pgsql`.

### 5.4 Frontend Tooling: Node.js 22.x LTS + npm 10.x + Vite 6.x
- **Target:** Node.js v22.23.2, npm 10.9.8, Vite (bundled via `laravel-vite-plugin`).
- **Critical Architectural Distinction:**
  - **Node.js/npm:** Restricted exclusively to **local/build-time asset compilation** (Vite bundling, CSS minification, JS module packaging).
  - **Runtime Execution:** Node.js is **NEVER** executed as an application runtime or backend server in production or development serving. The application is executed purely by the PHP runtime.

### 5.5 ORM: Eloquent ORM
- **Target:** Native Laravel Eloquent ORM.
- **Decision:** Exclusively use Eloquent ORM. No external ORMs (Doctrine, Prisma, TypeORM) are permitted.

---

## 6. Technology Decision Matrix

| Layer / Component | Technology Selected | Version Target | Purpose / Role | Justification / Compatibility Notes |
|---|---|---|---|---|
| **Language Runtime** | PHP | `^8.4` (8.4.15 / 8.5) | Core application execution | Installed locally; native attributes, typed properties, property hooks, PDO PostgreSQL driver verified. |
| **Web Framework** | Laravel | `^13.0` (or `^12.0`) | Full-stack web framework | Mature session auth, Form Requests, Policies, Blade, Eloquent, database migrations/seeders. |
| **Database Tier** | PostgreSQL | `18.4` | Relational persistence & integrity | Validated across 23 tables, 43 restrictive FKs, 7 CHECKs, JSONB, clock_timestamp triggers. |
| **Database Driver** | PDO_PGSQL | Ext-pdo_pgsql | PHP-to-Postgres bridge | Verified enabled in active PHP 8.4 runtime; supports transaction savepoints and native types. |
| **Object-Relational Mapping** | Eloquent ORM | Framework native | Active Record domain mapping | Built-in support for casts (JSONB $\rightarrow$ array, enums, dates, decimals), scopes, and eager loading. |
| **Templating Engine** | Blade | Framework native | Server-rendered view presentation | Zero-build fast compilation, native layout inheritance, component slots, XSS auto-escaping (`{{ }}`). |
| **Client-side Scripting** | Vanilla JavaScript | ES2024 / ES6 Modules | Progressive enhancement & UI | Mark sheet grid navigation, asynchronous save/dirty tracking, modal dialogs, dynamic DOM filters. |
| **CSS Styling** | Vanilla CSS / Tailored CSS | CSS3 Custom Properties | UI styling & responsive layouts | Tailored design system, CSS grid/flexbox, no bulky unnecessary framework dependencies. |
| **Frontend Asset Bundler** | Vite | `^6.0` (`laravel-vite-plugin`) | Asset bundling & HMR | Official Laravel asset compilation standard; compiles SCSS/CSS and modular JS into public assets. |
| **Build Runtime Tool** | Node.js / npm | Node v22.x / npm v10.x | Development build tool only | Build-time utility only. Strictly prohibited from serving runtime backend HTTP requests. |
| **PHP Dependency Manager**| Composer | `^2.10` (2.10.2) | PHP package management | Installed locally; PSR-4 autoloading, dependency lockfile enforcement. |
| **Testing Framework** | PHPUnit / Pest PHP | `^11.0` / Pest `^3.0` | Automated test suites | Unit, feature, transaction rollback, and authorization policy verification. |
| **Session Management** | Laravel Native Session | Database or Redis/File | State & authentication tokens | Server-managed, secure HTTP-only cookies, automatic CSRF token integration. |
| **Authentication System** | Laravel Session Auth | Framework native guard | User session authentication | Standard `web` guard, password hashing (Argon2id/Bcrypt), session invalidation on deactivation. |
| **Authorization Engine** | Laravel Policies & Gates | Framework native | Granular scope authorization | Enforces subject-teacher assessment scopes and class-teacher all-subject access rules. |
| **Audit Logging** | Dedicated Application Service| Custom Service + Eloquent | Write before/after JSONB logs | Appends immutable records to `audit_logs`; no external package needed. |
| **PDF Generation Engine** | Headless Chrome or Dompdf | To be finalized in 6.7 | PDF report generation | Deferred to Phase 6.7; will stream immutable PDFs to disk and track revision metadata. |
| **File Storage System** | Laravel Storage (Flysystem) | Local / Private Disk | Report PDF & asset storage | Segregates public assets (school logo) from protected report PDFs (`storage/app/reports/`). |

---

## 7. Architecture Principles

All software design and code generation throughout Phase 6 and subsequent phases must strictly adhere to these eighteen principles:

### A. Requirements-First (BRD Primacy)
Framework conventions must serve the business rules, never the reverse. If a Laravel default pattern (e.g., cascading deletes, soft deletes, or loose string enums) conflicts with BRD V1.3 or finalized rules, the business rule wins without compromise.

### B. Database Integrity & Model Immutability
The validated 23-table PostgreSQL schema is fixed. Laravel Eloquent models must conform to the database tables, column types, foreign key behaviors (`RESTRICT`), and constraints—never alter the database to suit Eloquent conventions.

### C. Server-Rendered Blade Architecture
The frontend is rendered by Laravel Blade. HTML is generated on the server with full security context, descriptive heading tags, and semantic structure. Client-side state hydration frameworks (Inertia, React, Vue) are prohibited.

### D. Thin Controllers
Controllers are HTTP traffic coordinators only. Their responsibilities are limited to:
1. Ingesting validated input from Form Requests.
2. Invoking authorization gates/policies.
3. Delegating execution to an Application Service.
4. Returning a Blade view or an appropriate HTTP/JSON response.
Controllers must never contain mark calculation math, multi-model mutation transactions, or direct SQL construction.

### E. Form Requests for Input Validation
All mutation requests (POST, PUT, PATCH, DELETE) must pass through dedicated Laravel `FormRequest` classes. Validation rules must validate:
- Mark format (nullable decimal, result status compatibility).
- Non-negative attendance days and total working days bounds.
- Explicit existence of referenced keys in the active context.

### F. Policies & Gates for Authorization
Authorization is enforced server-side via Laravel Policies and Gates. Hiding a button or menu link in Blade is purely a UI convenience and does not constitute security. Every endpoint must challenge the user's active teacher assignment scopes.

### G. Dedicated Application Services for Domain Workflows
Complex business operations must be encapsulated in dedicated Application Services:
- `MarkEntryService`: Validates assignment scope, handles numeric/absent/blank state transitions, enforces max mark caps, dispatches audit logging.
- `CalculationService`: Executes Method 1 (Average Percentage) and Method 2 (Combined Marks), handles absent zero contribution, enforces Term Exam exclusivity.
- `StudentTransferService`: Manages internal transfer lifecycles, marks historical records, provisions new academic placements, links subject allocations.
- `ReportGenerationService`: Manages PDF revision incrementation, generates immutable disk files, records entries in `generated_reports`.

### H. Eloquent for Persistence Without Artificial Repository Layers
Eloquent models provide data mapping, relationship traversal, and casting. Do not create artificial, pass-through repository interfaces (e.g., `UserRepositoryInterface` / `UserRepository`) that merely wrap standard Eloquent calls unless multi-driver abstraction is genuinely required.

### I. Transactions for Multi-Step State Changes
Every multi-table mutation must execute inside a `DB::transaction()` block. If an audit log insertion fails, or a report revision record cannot be written, the entire operation must roll back cleanly.

### J. Auditability by Design
The `audit_logs` table is append-only. Application code must record actor (`user_id`), target entity (`marks`, `student_academic_records`, etc.), before-state JSONB snapshot, after-state JSONB snapshot, client IP address, and timestamp. The application UI must never expose edit or delete capabilities for audit logs.

### K. Historical Record Immutability
- Student academic placements (`student_academic_records`) are permanent historical records.
- Historical roll numbers are never overwritten upon transfer.
- Previously generated report PDFs are never overwritten; regenerations increment `revision_number` and write new discrete physical files.
- Master subject name changes must never overwrite historical `class_subjects.subject_name_snapshot`.

### L. Explicit Contextual Teacher Authorization Scope
Teacher authorization is strictly contextual and dynamic:
- A single teacher may hold multiple concurrent assignment records across different classes, sections, and subjects.
- A **Class Teacher** assignment grants class-wide access to all applicable subjects in that assigned class and section (`subject_id IS NULL`).
- A **Subject Teacher** assignment grants access strictly to the assigned subject in that class/section (`subject_id IS NOT NULL`).
- Application authorization logic must evaluate the complete set of active assignments for the authenticated user.

### M. Single-School Architectural Simplicity
The current system is explicitly single-school. Do not introduce multi-tenant database partitioning, tenant identification middleware, or multi-school routing complexity.

### N. Minimal Dependency Principle
Rely on Laravel native facilities first. Third-party packages must be justified against stability, security, and necessity criteria. No package should be introduced for simple tasks that native PHP/Laravel can accomplish in a few lines of clean code.

### O. Security by Default
- Passwords hashed via native Argon2id/Bcrypt.
- Automatic CSRF token verification across all non-GET requests.
- Strict mass-assignment protection via `$fillable` white-listing on all Eloquent models.
- Strict XSS escaping in views (`{{ }}`).
- Secure session cookie configuration (`HttpOnly`, `SameSite=Lax`, `Secure` in production).
- Immediate access revocation upon user deactivation (`is_active = false`).

### P. Testability
Domain math (percentages, totals, attendance rates) and assignment scope resolution algorithms must be decoupled from HTTP controllers so they can be thoroughly tested via automated PHPUnit/Pest unit tests.

### Q. Configuration Through Environment
All environment-specific configuration (database credentials, application keys, debug mode, storage root paths) must be managed exclusively via `.env` and accessed through `config/*.php`. Hardcoded credentials or absolute machine paths are strictly prohibited.

---

## 8. Frontend Technology Principles

1. **Server-Side Rendering:** HTML structure is rendered entirely by Blade templates. The initial page load carries complete, semantic DOM trees.
2. **CSS Architecture:** Plain, modular CSS using CSS custom properties (variables) for theme consistency, or standard utilities built via Vite. Avoid heavy, unneeded client frameworks.
3. **Vanilla JavaScript for Micro-Interactions:** JavaScript is used as a progressive enhancement tool, specifically for:
   - **Mark Entry Grid:** Arrow-key navigation (Up/Down/Left/Right/Enter/Tab), dirty state indicators, auto-tabbing, and immediate client-side visual validation.
   - **Dynamic Filters:** Cascading dropdowns (selecting Academic Year filters Classes; selecting Class filters Sections/Subjects).
   - **Modal Dialogs & Confirmation Prompts:** Confirmation on critical operations (e.g., final report generation, student transfer).
   - **Dynamic Calculations Preview:** Instant recalculation of totals/percentages in browser before form submission.
4. **Server-Side Authority:** Client-side calculations and validations are conveniences for the user; **the server-side application service is the sole authoritative decision-maker.**

---

## 9. Authentication Principles

1. **Stateful Session Authentication:** Authentication uses Laravel’s built-in session-based guard (`web`), utilizing secure, encrypted HTTP cookies.
2. **No Token API Over-Engineering:** Since the application is not an SPA or mobile client, stateless JWT or OAuth2 / Laravel Passport tokens are rejected.
3. **Deactivation Guard Middleware:** A custom authentication middleware (`EnsureUserIsActive`) will verify on every request that `users.is_active === true`. If a user is deactivated by an Administrator, their session is immediately invalidated, and further actions are blocked.
4. **Historical Actor Identity:** Deactivated users remain intact in the database so that their foreign key references in `marks.entered_by_user_id`, `marks.updated_by_user_id`, `audit_logs.user_id`, and `generated_reports.generated_by_user_id` remain historically accurate.

---

## 10. Authorization Principles

1. **Role Hierarchy & Separation:**
   - **Administrator:** Full administrative control (user accounts, activation/deactivation, global school settings, audit log review).
   - **Office Staff:** Academic year, class, section, student enrollment, student transfer, report configuration setup, and batch report generation.
   - **Class Teacher:** Access to all subjects, students, marks, attendance, and reports within their assigned class and section.
   - **Subject Teacher:** Restricted strictly to marks entry/editing for their assigned class, section, subject, and applicable assessments.
2. **Multi-Assignment Authorization Resolution:**
   - A teacher’s access rights are evaluated dynamically by querying all active rows in `teacher_assignments` matching their `user_id`, the active `academic_year_id`, and the target context.
3. **Policy-Driven Authorization:**
   - Controllers challenge permissions using `$this->authorize('update', [$mark, $context])` or `Gate::authorize()`.
   - Authorization logic lives in dedicated Policy classes (`MarkPolicy`, `StudentPolicy`, `ReportPolicy`).

---

## 11. Database & ORM Principles

1. **Schema Non-Interference:** Eloquent models map directly to existing PostgreSQL tables. Migrations in Laravel will represent the approved 23-table schema without altering column names, types, or constraints.
2. **PostgreSQL Native Type Casting:**
   - `NUMERIC(6,2)` columns mapped to `decimal:2`.
   - `BOOLEAN` columns mapped to `boolean`.
   - `TIMESTAMPTZ` columns mapped to `immutable_datetime` or `datetime`.
   - `JSONB` columns (`report_configurations.configuration_data`, `audit_logs.before_data`, `audit_logs.after_data`) mapped to native PHP `array`.
   - Custom PostgreSQL ENUM types mapped to PHP 8.1+ backed Enums.
3. **Restrictive Referential Actions Respected:**
   - Eloquent cascading deletes (`deleting` model events that cascade) are prohibited. Foreign key deletions must be rejected by PostgreSQL `ON DELETE RESTRICT`.
4. **Database-Enforced Auto-Update Preserved:**
   - The PostgreSQL trigger `trigger_set_updated_at()` updates `updated_at` on physical record modification. Eloquent's timestamp handling will work harmoniously with this trigger.

---

## 12. Business-Rule Preservation Principles

The Laravel application architecture must explicitly protect and enforce the 50 critical business rules documented in the project baseline, including:

1. **Mark States Integrity:**
   - Only three states exist: `blank`, `numeric`, `absent`.
   - `numeric 0.00` is a valid completed score.
   - `blank` represents an incomplete/unentered result (`mark_value = NULL`).
   - `absent` (`A`) represents an assessed absent result (`mark_value = NULL`), which displays as `A` and contributes `0` to calculations.
   - Do NOT introduce additional mark states (`EX`, `WH`, `NA`).
2. **Dynamic Terms & Sequencing:**
   - The system must dynamically handle any number of terms configured for an academic year, ordered by `sequence_no`. No logic may assume exactly three terms.
3. **Assessment Applicability & Maximum Marks:**
   - Maximum marks are configured per subject within each assessment via `assessment_applicability.maximum_marks`.
   - Application logic must enforce `0 <= mark_value <= assessment_applicability.maximum_marks`.
4. **Calculation Methods:**
   - Supported methods: Method 1 (`average_percentage`) and Method 2 (`combined_marks`).
   - Only assessments categorized as Term Exams contribute to the term percentage in the current specification.
   - Percentage values display formatted to two decimal places.
5. **Historical Student Placement & Transfers:**
   - When a student transfers between sections (e.g., 8A to 8B), the existing `student_academic_records` row is updated with `status = 'internal_transfer'` and an `effective_to` date.
   - A new record is inserted for Section 8B with `status = 'active'`.
   - Marks scored in Section 8A remain immutably linked to the historical 8A placement record.
6. **Immutable Subject Snapshot:**
   - Marks and report cards display the subject name from `class_subjects.subject_name_snapshot`. Renaming a master subject in `subjects` must never alter snapshots in existing class-subject configurations.
7. **Attendance Division-by-Zero Safety:**
   - Percentage is computed as `(days_attended / total_working_days) * 100`.
   - If `total_working_days == 0`, the application must render `N/A` without throwing an arithmetic division-by-zero exception.
8. **Independent Student Report Generation:**
   - An incomplete student (one with blank marks) cannot receive a completed report card. However, an incomplete student must never block report card generation for other students in the same section who are complete.
9. **Immutable PDF Report Revisions:**
   - Generated reports in `generated_reports` increment `revision_number` for each re-generation. Existing PDF files are never overwritten on disk.

---

## 13. PDF & File Storage Architectural Direction

1. **Storage Segregation:**
   - **Public Disk (`storage/app/public`):** School logo (`school_settings.school_logo_path`), accessible via web server for report headers and navigation.
   - **Private/Protected Disk (`storage/app/reports/`):** Generated student report card PDFs. These files are strictly private and accessible only via authenticated, authorized controller download endpoints.
2. **Revision Identity Pathing:**
   - Files are stored using structured paths incorporating year, class, section, student roll, and revision:  
     `/reports/{academic_year}/{class}_{section}/{student_id}_r{revision_number}_{timestamp}.pdf`
3. **Stateless Generation Contracts:**
   - PDF generation will be orchestrated by a `ReportGeneratorContract` interface, allowing the application to render Blade views into PDF byte-streams and store them atomically alongside database revision records.

---

## 14. Testing Principles

The testing architecture comprises three distinct validation tiers:

1. **PostgreSQL Database Integrity Tier (Regression Baseline):**
   - The existing SQL validation runner (`database/Postgres/validation/run_all.sql`) remains the baseline regression gate for direct schema, constraint, trigger, and PL/pgSQL verification.
2. **Laravel Application Feature Tests:**
   - Test full HTTP workflows: login, session deactivation, form request rejection, controller responses, and Blade rendering.
   - Verify negative authorization: Subject Teacher attempting to enter marks outside assigned subject/section receives HTTP 403 Forbidden.
3. **Laravel Unit Tests:**
   - Test isolated domain calculations:
     - Method 1 vs Method 2 calculation accuracy.
     - Absent mark zero contribution.
     - Attendance percentage and 0/0 `N/A` handling.
     - Mark state transitions (`blank` $\leftrightarrow$ `numeric` $\leftrightarrow$ `absent`).
4. **Database Test Isolation:**
   - Feature tests utilize Laravel's `DatabaseTransactions` or `RefreshDatabase` trait against a dedicated test database, ensuring tests do not contaminate development data.

---

## 15. Environment & Configuration Principles

1. **Configuration Immutability:** Application code must never access `env()` directly outside of configuration files (`config/*.php`). All code accesses settings via `config('app.name')`, `config('database.default')`, etc.
2. **Environment Variable Segregation:**
   - `.env.example` — Authoritative template checked into version control documenting all required variables with dummy values.
   - `.env` — Local workstation configuration containing machine-specific credentials; strictly ignored by `.gitignore`.
3. **Critical Environment Keys:**
   - `APP_ENV` (`local`, `testing`, `production`)
   - `APP_KEY` (Application encryption key)
   - `DB_CONNECTION=pgsql`
   - `DB_HOST`, `DB_PORT=5432`, `DB_DATABASE=school_report_card`, `DB_USERNAME`, `DB_PASSWORD`
   - `SESSION_DRIVER=database` (or `file`)
   - `FILESYSTEM_DISK=local`
   - `LOG_CHANNEL=stack`

---

## 16. Dependency & Package Policy

To prevent dependency bloat, technical debt, and version incompatibilities, third-party packages are admitted only under strict conditions:

1. **Core-First Rule:** Always utilize native PHP 8.4+ and native Laravel built-in components first.
2. **Justification Criteria:** Any external package proposal must satisfy all five criteria:
   - Fulfills a mandatory BRD requirement that Laravel does not natively solve (e.g., HTML-to-PDF rendering).
   - Actively maintained with support for PHP 8.4+ and Laravel 13/12.
   - No breaking architectural conflicts with PostgreSQL or Blade.
   - Documented security track record and permissible open-source license (MIT, Apache 2.0, BSD).
   - Formally documented in the architecture blueprint before inclusion.
3. **Explicitly Prohibited Packages:**
   - Multi-tenancy packages (e.g., `stancl/tenancy`).
   - Heavy administrative dashboard generators (e.g., Filament, Nova) that overwrite custom teacher assignment workflows and custom mark entry grids.
   - Client-side SPA bridges (e.g., `inertiajs/inertia-laravel`).
   - Alternative ORMs or DB query abstraction layers.

---

## 17. Explicitly Rejected Technologies & Approaches

| Rejected Technology / Approach | Primary Reason for Rejection | Approved Alternative |
|---|---|---|
| **Node.js as Backend Runtime** | Obsolete legacy direction; single language PHP/Laravel ecosystem chosen. | PHP 8.4+ / Laravel 13 |
| **MySQL Database Engine** | PostgreSQL selected and validated for robust constraints, JSONB, and types. | PostgreSQL 18.4 |
| **Single-Page Application (React/Vue/Inertia)**| Excessive complexity, state duplication, SEO/accessibility overhead. | Server-rendered Blade + Vanilla JS |
| **TailwindCSS (without confirmation)** | Vanilla tailored CSS requested to guarantee complete styling control and speed. | Tailored Vanilla CSS / Vite |
| **Prisma / Doctrine ORM** | Violates native framework integration and adds redundant complexity. | Native Laravel Eloquent ORM |
| **Pass-through Repository Interfaces** | Redundant abstraction layer over Active Record Eloquent. | Application Services + Eloquent |
| **Soft Deletes on Historical Placement**| Masquerades deletion; historical placements must be explicitly tracked by status. | `student_academic_records.status` |
| **Cascading Foreign Key Deletes** | Destroys historical auditability and report archives. | `ON DELETE RESTRICT` (enforced in DB) |
| **Token-Based Authentication (JWT/Sanctum)** | Over-engineered for a server-rendered browser web application. | Standard Laravel Session Auth (`web`) |
| **UI-Only Authorization Checks** | Security vulnerability; users could forge POST requests. | Server-side Policies & Gates |

---

## 18. Open Decisions Deferred to Later Phase 6 Steps

The following technical decisions are intentionally deferred to subsequent blueprint steps:

1. **Exact PDF Generation Library (Phase 6.7):** Selection between `barryvdh/laravel-dompdf` (Dompdf) versus Chromium headless/Browsershot (`spatie/browsershot`) will be made based on layout rendering fidelity, performance, and environment dependencies.
2. **Mark Entry Table Component Structure (Phase 6.6):** Detailed grid layout, responsive table overflow, and specific keyboard event listener bindings.
3. **Batch Import CSV Processing Architecture (Phase 6.3):** Queue worker vs synchronous chunked stream processing for student roster imports.
4. **Cache Strategy for Subject Snapshots & Configs (Phase 6.3):** Redis vs database-driven cache tags for academic year configurations.
5. **Exact Form Request Validation Rule Sets (Phase 6.5):** Field-by-field validation syntax for every endpoint.

---

## 19. Phase 6.1 Completion Checklist

- [x] Authoritative source hierarchy confirmed and documented.
- [x] Application stack confirmed (PHP 8.4+, Laravel 13/12, PostgreSQL 18.4, Blade, Vanilla JS, Vite).
- [x] Node.js strictly categorized as build tooling only, not backend runtime.
- [x] Validated PostgreSQL 18 database preserved as fixed, closed relational foundation.
- [x] Eloquent confirmed as exclusive ORM.
- [x] Blade confirmed as primary server-rendered view engine.
- [x] Thin controller and Application Service architecture established.
- [x] Form Request input validation principle established.
- [x] Policy and Gate server-side authorization principle established.
- [x] Contextual teacher multi-assignment authorization scope explicitly preserved.
- [x] Discrete mark states (`blank`, `numeric`, `absent`) and calculation participation preserved.
- [x] Historical student placement and report revision immutability rules preserved.
- [x] Session-based authentication with active-status middleware guard established.
- [x] Segmented file storage and PDF revision architecture outlined.
- [x] Three-tier testing strategy (SQL regression, Feature tests, Unit tests) defined.
- [x] Environment configuration principles defined.
- [x] Strict dependency and package evaluation policy established.
- [x] Explicitly rejected technologies and approaches documented.
- [x] No application or database implementation code was written.
- [x] Documentation saved to `docs/architecture/PHASE_6_1_TECHNOLOGY_BASELINE.md`.
