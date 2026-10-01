# PHASE 6.6 — HTTP LAYER, ROUTES, FORM REQUESTS & CONTROLLER CONTRACTS
## Architectural Specification for Routing, Form Requests, Controller Contracts, Middleware & Request Lifecycle

**Project:** School Examination Marks and Report Card Management System  
**Document Version:** 1.0  
**Phase:** 6.6 — HTTP Layer, Routes, Form Requests & Controller Contracts  
**Status:** Approved & Authoritative Architectural Specification  

---

## 1. Purpose

This document defines the authoritative architectural blueprint for the HTTP layer of the **School Examination Marks and Report Card Management System**. It establishes the formal contracts for:

1. **Request Lifecycle & Middleware Pipelines:** Exact sequencing of requests through broad security boundaries, session verification, active account checks, and contextual authorization.
2. **Route Hierarchy & Naming Conventions:** Clean, resource-oriented, and workflow-specific RESTful routing across all 23 application subdomains without unnecessary API drift.
3. **Route Model Binding & Contextual Integrity:** Safe resolution of Eloquent models, validation of parent-child relationships, and strict elimination of Insecure Direct Object References (IDOR).
4. **Form Request Architecture & Input Validation:** Explicit separation between syntactic input shape validation (Form Requests), actor permissions (Policies), and domain invariants (Application Services).
5. **Mark Entry & Batch Save Protocols:** Strict preservation of the ternary mark state (`blank` vs. `numeric` vs. `absent`), atomic multi-mark validation, and independent scope authorization for each submitted record.
6. **Thin Controller Contracts:** Standardized, orchestration-only controller methods that parse requests, invoke policies, delegate work to canonical Application Services, and return Blade views or structured JSON.
7. **Response & Status Code Conventions:** Predictable HTTP status codes, flash session feedback for standard forms, and clean JSON payloads for asynchronous Vanilla JS micro-interactions.
8. **Security & Threat Mitigation:** Concrete enforcement mechanisms for TH-01 through TH-17, parameter tampering defenses, role-aware closed-year rules, and mass assignment protections.

This specification serves as the binding technical manual for all subsequent implementation phases. No architectural guesswork or ad-hoc routing decisions are permitted during development.

---

## 2. Relationship to Previous Phases

Phase 6.6 builds directly upon the approved architectural foundation established in Phases 6.1 through 6.5:

```
Phase 6.1: Technology Baseline & Architecture Principles (Approved)
  - PHP 8.4+, Laravel 13, PostgreSQL 18.4, Blade, Vanilla JS, Plain CSS, Vite.
  - Session authentication, thin controllers, immutable audit logs.
      ↓
Phase 6.2: Domain Model & Eloquent Architecture (Approved)
  - 23 Eloquent models, 85 relationships, mutability tiers, historical placement.
      ↓
Phase 6.3: Laravel Project / Folder Structure (Approved & Corrected)
  - Directory tree, canonical services (MarkEntryService, StudentPlacementService, etc.).
      ↓
Phase 6.4: PostgreSQL + Eloquent Integration Architecture & Decision Ledger (Approved)
  - Schema types, MarkValueCast, PDO connection, transactions, DEC-001 to DEC-030.
      ↓
Phase 6.5: Authentication, Authorization & Security Architecture (Approved)
  - Session lifecycle, EnsureUserIsActive, multi-assignment teacher resolution, DEC-031 to DEC-040.
      ↓
Phase 6.6: HTTP Layer, Routes, Form Requests & Controller Contracts (Current Phase)
  - Web routing, Form Requests, thin controllers, batch mark endpoints, controller contracts.
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
  - Production environment, session driver, connection pooling, deployment hardening.
```

---

## 3. Authoritative Sources

All specifications in this blueprint derive strictly from the project's authoritative hierarchy:

1. **BRD V1.3** (`BRD/School_Examination_Marksheet_Requirements_v1_3.docx`) — Functional requirements for mark entry (BR-010 to BR-016), user roles (BR-030 to BR-034), report generation and downloads (BR-070, BR-071), audit trails (BR-080, BR-081), and clarification rules (CL-001 to CL-011).
2. **Finalized Business Rules & Project Handover Decisions** — Authoritative clarifications on multi-assignment teacher resolution, single-school scope, closed-year workflows, and elective locks.
3. **Approved 23-Table Relational Design** (`DB design/DB-table-definitons.txt`) — Database entities, keys, and foreign constraints.
4. **Approved ERD** (`DB design/mermaid-diagram.png`) — Relational cardinalities and topology.
5. **Validated PostgreSQL 18 Implementation** (`database/Postgres/schema.sql`, `seed.sql`) — 23 tables, 43 foreign keys, 7 CHECK constraints, and functional unique indexes.
6. **Technology Baseline** (`docs/architecture/PHASE_6_1_TECHNOLOGY_BASELINE.md`)
7. **Domain & Eloquent Architecture** (`docs/architecture/PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md`)
8. **Project Folder Structure** (`docs/architecture/PHASE_6_3_LARAVEL_PROJECT_FOLDER_STRUCTURE.md`)
9. **PostgreSQL + Eloquent Integration Architecture** (`docs/architecture/PHASE_6_4_POSTGRESQL_ELOQUENT_INTEGRATION_ARCHITECTURE.md`)
10. **UPDATED Authentication, Authorization & Security Architecture (Immediate Authorization Source)** (`docs/architecture/PHASE_6_5_AUTHENTICATION_AUTHORIZATION_SECURITY.md`)
11. **Project Decision Ledger** (`docs/decisions.md`, DEC-001 through DEC-044)

---

## 4. Fundamental HTTP Architecture Principles

The HTTP layer conforms to nine core architectural principles:

1. **Server-Rendered Architecture (No SPA / No API Drift):**  
   The application is a classic Laravel server-rendered web application utilizing Blade views, vanilla HTML forms, and Vanilla JavaScript for progressive micro-interactions. No REST/JSON API layer (Sanctum, Passport, JWT, GraphQL) is introduced.
2. **Thin Controllers (Transport Orchestration Only):**  
   Controllers are strictly responsible for: (a) binding HTTP inputs, (b) triggering authorization policies, (c) delegating business operations to canonical Application Services, and (d) returning Blade views, redirects, or lightweight JSON responses. Controllers never perform direct Eloquent queries for business logic, never calculate percentages, and never manage database transactions directly.
3. **Three-Tier Validation Boundary:**  
   Validation responsibilities are strictly separated across layers:
   - *Form Requests:* Syntactic shape, data types, required fields, scalar bounds, and enums.
   - *Laravel Policies:* User identity, role, active teacher assignment scope, and contextual ownership.
   - *Application Services:* Business invariants, cross-table integrity, database lookups (authoritative max marks, elective marks existence), and audit log creation.
4. **Zero-Trust Client Identity & Context:**  
   The server never trusts client-supplied actor IDs (`entered_by_user_id`, `updated_by_user_id`, `generated_by_user_id`), maximum mark values, report revision numbers, or submitted classroom scopes. All identity and contextual boundaries are derived exclusively from `Auth::id()` and authoritative database lookups.
5. **Ternary Mark Representation:**  
   The HTTP layer enforces strict differentiation between:
   - `blank`: `result_status = 'blank'`, `mark_value = NULL` (Incomplete assessment score; blocks final report generation).
   - `numeric`: `result_status = 'numeric'`, `mark_value >= 0.00` (Assessed score; participates in totals).
   - `absent`: `result_status = 'absent'`, `mark_value = NULL` (Student missed exam; contributes $0.00$ to totals; complete).
   Never allow client coercion of `blank` or `absent` into `0.00`.
6. **Multi-Assignment Live Teacher Scope Evaluation:**  
   Teachers may hold multiple concurrent classroom assignments. Teacher permissions are evaluated dynamically from PostgreSQL on every request; no teacher assignment IDs or classroom permissions are permanently cached in the session.
7. **Role-Aware Closed-Year Operation:**  
   When an academic year is `closed`, teachers become strictly read-only for marks and attendance. Administrators and Office Staff retain authorized correction privileges. Reopening a year does not automatically restore teacher editing. Blanket closure middleware is strictly prohibited.
8. **Relational IDOR Immunity:**  
   URL route parameters (`/marks/{mark}`, `/students/{student}`) resolve models via Route Model Binding, but authorization policies must traverse the complete relational graph back to the teacher's active assignment scope before granting access.
9. **Private Document & Report Authorization Protection:**  
   Generated report card PDFs reside on private storage outside the web document root and are accessible exclusively through the authenticated controller download route (`GET /reports/download/{generatedReport}`) after passing `Gate::authorize('download', $generatedReport)`. Authorization is granted unconditionally to `Administrator` and `Office Staff`, and to `Class Teacher` strictly after relational classroom-scope verification (`GeneratedReport` $\rightarrow$ `StudentAcademicRecord` $\rightarrow$ active `teacher_assignments` matching target academic year, class, and section). `Subject Teacher` is strictly denied. Direct file URLs, role-only authorization, and batch PDF downloads are strictly prohibited.

---

## 5. Request Lifecycle Architecture

Every incoming HTTP request passes through a strictly ordered pipeline:

### 5.1 Standard GET Request Lifecycle (Page Rendering)
```
Browser
   ↓ [HTTP GET /academic-years/1/classes/2/sections/3/marks]
Web Server (public/index.php)
   ↓
Global HTTP Kernel Middleware
   ↓
Route Matching (routes/web.php)
   ↓
Middleware Group: web
   ├─ EncryptCookies
   ├─ AddQueuedCookiesToResponse
   ├─ StartSession
   ├─ ShareErrorsFromSession
   └─ ValidateCsrfToken (bypassed on GET)
   ↓
Middleware: auth (Redirects to /login if unauthenticated)
   ↓
Middleware: EnsureUserIsActive (Kills session and redirects to /login if user.is_active is false)
   ↓
Middleware: CheckRole (Optional broad role perimeter check)
   ↓
Route Model Binding (Resolves AcademicYear, SchoolClass, Section)
   ↓
Controller: MarkEntryController@index
   ↓
Policy Check: Gate::authorize('viewAny', [Mark::class, $academicYear, $schoolClass, $section, $subject])
   ↓
Application Service / Query (MarkEntryService prepares student roster, applicability, and mark grid)
   ↓
Controller returns View: resources/views/marks/index.blade.php
   ↓
Browser renders Server-Rendered HTML
```

### 5.2 Standard Form Mutation Request Lifecycle (POST / PUT / PATCH / DELETE)
```
Browser
   ↓ [HTTP POST /marks/batch-save with _token and payload]
Web Server (public/index.php)
   ↓
Global HTTP Kernel Middleware
   ↓
Route Matching (routes/web.php)
   ↓
Middleware Group: web (Validates CSRF token; aborts 419 on mismatch)
   ↓
Middleware: auth (Redirects 302 or returns 401)
   ↓
Middleware: EnsureUserIsActive (Logs out immediately if user.is_active is false)
   ↓
Form Request Resolution: BatchSaveMarksRequest
   ├─ authorize(): High-level gate check
   └─ rules(): Validates structure, array sizes, numeric bounds, enums
   ↓ (Fails? -> 302 Redirect Back with Errors & Old Input / 422 for AJAX)
Controller: MarkEntryController@batchSave(BatchSaveMarksRequest $request)
   ↓
Policy Authorization: Evaluates actor permissions for target class, section, subject, and year
   ↓ (Fails? -> 403 Forbidden)
Application Service Invocation: MarkEntryService::saveBatch(array $validatedData, int $actorId)
   ↓
Database Transaction (BEGIN)
   ├─ Fetches authoritative AssessmentApplicability (max_marks) from PostgreSQL
   ├─ Validates business invariants (mark_value <= max_marks)
   ├─ Locks rows / Inserts / Updates Eloquent models
   ├─ Writes audit records to audit_logs (before_data / after_data)
   └─ COMMIT
   ↓
Controller redirects to named route with session flash message:
   return redirect()->route('marks.index', [...])->with('success', 'Marks saved successfully.');
   (Or returns JSON response if requested via Fetch / Vanilla JS)
```

---

## 6. Middleware Architecture & Pipeline Strategy

Middleware enforces coarse, cross-cutting perimeter checks. It does **not** replace contextual domain policies.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          MIDDLEWARE PIPELINE STACK                          │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
                                       ▼
                             Global Middleware
                    (TrustProxies, PreventRequestsDuringMaintenance)
                                       │
                                       ▼
                           Group: web Middleware
                    - EncryptCookies
                    - AddQueuedCookiesToResponse
                    - StartSession
                    - ShareErrorsFromSession
                    - VerifyCsrfToken
                                       │
                                       ▼
                             Group: auth Middleware
                    - Authenticate (Laravel standard)
                    - App\Http\Middleware\EnsureUserIsActive (DEC-032)
                                       │
                                       ▼
                       Route-Specific Parameterized Middleware
                    - CheckRole:Administrator
                    - CheckRole:Administrator,Office Staff
                    - ThrottleRequests (login: 5/min)
                                       │
                                       ▼
                         Controller / Form Request
```

### 6.1 Middleware Inventory & Specifications

| Middleware Class | Alias / Group | Execution Scope | Core Responsibility | Failure Behavior |
|---|---|---|---|---|
| `Illuminate\Session\Middleware\StartSession` | `web` | All Web Routes | Starts native PHP session cookie. | 500 on session storage failure. |
| `Illuminate\Foundation\Http\Middleware\VerifyCsrfToken` | `web` | All Web Mutations | Validates `_token` parameter or `X-CSRF-TOKEN` header. | HTTP 419 (Page Expired). |
| `Illuminate\Auth\Middleware\Authenticate` | `auth` | All Protected Routes | Verifies user session identity via `web` guard. | Redirect 302 to `login` (HTML) or 401 (AJAX). |
| `App\Http\Middleware\EnsureUserIsActive` | `active` (in `auth`) | All Protected Routes | Verifies `Auth::user()->is_active === true` on every request. | Logs out user, invalidates session, regenerates token, redirects to `login` with flash error. |
| `App\Http\Middleware\CheckRole` | `role:{roles}` | Admin & Operational Routes | Validates that `Auth::user()->role->name` is in the allowed whitelist. | HTTP 403 (Forbidden). |
| `Illuminate\Routing\Middleware\ThrottleRequests` | `throttle:{limit},{decay}` | Authentication Routes | Limits brute-force login attempts (5 requests / 60 seconds). | HTTP 429 (Too Many Requests). |

### 6.2 The "Blanket Closure Middleware" Prohibition
Phase 6.5 and DEC-035 strictly prohibit implementing a blanket middleware such as `PreventRequestsWhenYearClosed`.  
*Rationale:* When an academic year is `closed`, teachers must be restricted to read-only access, but Administrators and Office Staff retain authorized correction privileges. A blanket middleware checking `year.status === 'closed'` would break legitimate administrative mark corrections. Year closure rules are evaluated contextually inside Laravel Policies and Application Services.

---

## 7. Authentication Routes Architecture

Authentication is native, stateful, session-based, and server-rendered. API tokens, JWTs, OAuth, and external authenticators are strictly prohibited (DEC-031).

```
GET  /login   --> Auth\LoginController@showLoginForm  [guest, web]
POST /login   --> Auth\LoginController@login          [guest, web, throttle:5,1]
POST /logout  --> Auth\LogoutController@logout        [auth, web]
```

### 7.1 Specifications for Login & Logout Actions

#### `GET /login` (`login`)
- **Controller:** `App\Http\Controllers\Auth\LoginController@showLoginForm`
- **Middleware:** `guest`, `web`
- **Response:** Renders `resources/views/auth/login.blade.php`.
- **Behavior:** Redirects authenticated active users directly to `/dashboard`.

#### `POST /login` (`login.submit`)
- **Controller:** `App\Http\Controllers\Auth\LoginController@login`
- **Middleware:** `guest`, `web`, `throttle:5,1`
- **Form Request:** `App\Http\Requests\Auth\LoginRequest`
  - Validates `username` (required, string, max:50) and `password` (required, string).
  - Enforces rate limiting: key is `Str::lower($username).'|'.$request->ip()`. Max 5 attempts per 60 seconds.
- **Controller Logic:**
  1. Executes `Auth::attempt(['username' => $username, 'password' => $password], $remember = false)`.
  2. If authentication fails: Logs failed attempt to `audit_logs`, increments rate limiter, and redirects back to `/login` with generic flash error: *"These credentials do not match our records."* (Prevents username enumeration - TH-09).
  3. If authentication succeeds:
     - Verifies `Auth::user()->is_active === true`. If false: calls `Auth::logout()`, invalidates session, and redirects to `/login` with flash error: *"Your account has been deactivated. Please contact an Administrator."*
     - Calls `$request->session()->regenerate()` (Session fixation defense - TH-07).
     - Updates `users.last_login_at = now()`.
     - Writes successful login record to `audit_logs`.
     - Clears rate limiter for the throttle key.
     - Redirects intended to `/dashboard`.

#### `POST /logout` (`logout`)
- **Controller:** `App\Http\Controllers\Auth\LogoutController@logout`
- **Middleware:** `auth`, `web`
- **Security Constraint:** Handled strictly via HTTP POST with CSRF verification. Never expose logout via GET (prevents prefetching or CSRF logout exploits).
- **Controller Logic:**
  1. Captures `Auth::id()` for audit record.
  2. Calls `Auth::guard('web')->logout()`.
  3. Calls `$request->session()->invalidate()`.
  4. Calls `$request->session()->regenerateToken()`.
  5. Redirects to `/login` with flash message: *"You have been logged out successfully."*

---

## 8. Complete Route Hierarchy & Naming Conventions

Routes are structured logically around core business entities and workflows rather than raw database tables. All URLs use kebab-case (`academic-years`, `assessment-types`), and all route names use dot-separated snake_case (`academic_years.index`, `marks.batch_update`).

```
Web Routes Overview:
├── /login, /logout                                [Authentication]
├── /dashboard                                     [Dashboard]
├── /academic-years                                [Academic Calendar]
│   ├── /{academicYear}/close                      [Workflow: Close Year]
│   └── /{academicYear}/reopen                     [Workflow: Reopen Year]
├── /terms                                         [Terms Management]
├── /classes                                       [School Class Catalog]
├── /sections                                      [Section Catalog]
├── /subjects                                      [Subject Master Catalog]
├── /class-subjects                                [Curriculum Configuration]
├── /students                                      [Student Directory]
│   ├── /import                                    [Workflow: Student CSV Import]
│   ├── /{student}/placements                      [Placement History]
│   └── /{student}/transfer                        [Workflow: Internal Transfer]
├── /student-subject-allocations                   [Elective Allocation]
├── /teachers/assignments                          [Teacher Scope Management]
├── /assessments/types                             [Assessment Types]
├── /assessments                                   [Assessment Milestones]
├── /assessments/{assessment}/applicability        [Max Marks Applicability]
├── /marks                                         [Mark Grid Entry]
│   ├── /batch-save                                [Workflow: Batch Mark Save]
│   └── /{mark}/correct                            [Workflow: Staff Correction]
├── /attendance                                    [Attendance Entry]
│   └── /batch-save                                [Workflow: Batch Attendance Save]
├── /calculations/settings                         [Formula Configuration]
├── /reports/configurations                        [Report Layout Settings]
├── /reports/selections                            [Assessment Calculation Selections]
├── /reports                                       [Report Card Generation]
│   ├── /preview                                   [Workflow: Class Teacher Preview]
│   ├── /generate                                  [Workflow: Final Report Compilation]
│   └── /download/{generatedReport}                [Workflow: Secure PDF Download]
├── /admin/users                                   [Staff Account Administration]
│   ├── /{user}/deactivate                         [Workflow: Deactivate Account]
│   ├── /{user}/reactivate                         [Workflow: Reactivate Account]
│   └── /{user}/reset-password                     [Workflow: Password Reset]
├── /admin/audit-logs                              [Audit Inspection]
└── /admin/school-settings                         [Branding & Global Settings]
```

---

## 9. Route Model Binding & Contextual Integrity (IDOR Defense)

Laravel's Route Model Binding maps URL tokens to Eloquent models. However, **model resolution does not confer authorization**.

### 9.1 Parameter Naming Standards
To avoid naming collisions with PHP keywords and maintain consistency, route parameters map strictly to Eloquent models:

| Route Parameter | Target Eloquent Model | Underlying PostgreSQL Table |
|---|---|---|
| `{academicYear}` | `App\Models\AcademicYear` | `academic_years` |
| `{term}` | `App\Models\Term` | `terms` |
| `{schoolClass}` | `App\Models\SchoolClass` | `classes` (Custom binding: `SchoolClass`) |
| `{section}` | `App\Models\Section` | `sections` |
| `{subject}` | `App\Models\Subject` | `subjects` |
| `{classSubject}` | `App\Models\ClassSubject` | `class_subjects` |
| `{student}` | `App\Models\Student` | `students` |
| `{studentAcademicRecord}` | `App\Models\StudentAcademicRecord` | `student_academic_records` |
| `{studentSubjectAllocation}` | `App\Models\StudentSubjectAllocation` | `student_subject_allocations` |
| `{assessmentType}` | `App\Models\AssessmentType` | `assessment_types` |
| `{assessment}` | `App\Models\Assessment` | `assessments` |
| `{assessmentApplicability}` | `App\Models\AssessmentApplicability` | `assessment_applicability` |
| `{teacherAssignment}` | `App\Models\TeacherAssignment` | `teacher_assignments` |
| `{mark}` | `App\Models\Mark` | `marks` |
| `{attendance}` | `App\Models\Attendance` | `attendance` |
| `{calculationSetting}` | `App\Models\CalculationSetting` | `calculation_settings` |
| `{reportConfiguration}` | `App\Models\ReportConfiguration` | `report_configurations` |
| `{generatedReport}` | `App\Models\GeneratedReport` | `generated_reports` |
| `{user}` | `App\Models\User` | `users` |

### 9.2 Custom Route Model Binding: `SchoolClass`
Because `class` is a reserved PHP language keyword, the model is `SchoolClass`. Route model binding in `App\Providers\RouteServiceProvider` or `bootstrap/app.php` binds `{schoolClass}` explicitly:
```php
Route::model('schoolClass', \App\Models\SchoolClass::class);
```

### 9.3 Relational Scope Traversal (Anti-IDOR)
When an endpoint accepts an entity ID (e.g. `POST /marks/{mark}/correct`), an attacker could manipulate `{mark}` to target a record in another classroom. The policy must traverse the full relational graph to verify that the mark belongs to the teacher's authorized classroom context:

```php
// Inside MarkPolicy@update
public function update(User $user, Mark $mark): bool
{
    // Traverse relational hierarchy
    $placement = $mark->studentAcademicRecord; // student_academic_records
    $applicability = $mark->assessmentApplicability; // assessment_applicability
    $classSubject = $applicability->classSubject; // class_subjects

    // Context: academic_year_id, class_id, section_id, subject_id
    return $this->teacherAssignmentService->isAuthorized(
        $user,
        $placement->academic_year_id,
        $placement->class_id,
        $placement->section_id,
        $classSubject->subject_id
    );
}
```

### 9.4 Nested Resource Scoping
Where hierarchical routes are used (e.g. `/academic-years/{academicYear}/classes/{schoolClass}/sections/{section}`), controllers and Form Requests must verify that:
1. `{schoolClass}` belongs to the educational scope.
2. `{section}` is associated with `{schoolClass}`.
3. If an entity does not belong to the requested parent, abort with `HTTP 404 (Not Found)` to prevent cross-context data leakage.

---

## 10. Form Request Architecture & Validation Boundaries

Form Requests validate the **syntactic shape and structural constraints** of incoming HTTP requests. They do not validate business invariants that require authoritative database state resolution.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                       THREE-TIER VALIDATION RESPONSIBILITY                  │
├───────────────────┬─────────────────────────────────────────────────────────┤
│ Layer             │ Architectural Validation Responsibility                 │
├───────────────────┼─────────────────────────────────────────────────────────┤
│ **Form Request**  │ Syntactic shape, data types, required fields, string    │
│                   │ lengths, regex patterns, integer/decimal formats, enum   │
│                   │ values, array sizes, and static bounds (e.g. >= 0.00).  │
├───────────────────┼─────────────────────────────────────────────────────────┤
│ **Laravel Policy**│ Contextual authorization: Does this user have rights to │
│                   │ modify this student, subject, class, or year?           │
├───────────────────┼─────────────────────────────────────────────────────────┤
│ **App Service**   │ Authoritative business invariants:                      │
│                   │ - Is mark_value <= assessment_applicability.max_marks?  │
│                   │ - Does student already have marks for this elective?    │
│                   │ - Is the academic year currently open for this role?    │
│                   │ - Does the student have all mandatory subjects entered? │
└───────────────────┴─────────────────────────────────────────────────────────┘
```

### 10.1 Complete Form Request Inventory

| Form Request Class | Target Controller Action | Primary Validation Rules & Bounds |
|---|---|---|
| `App\Http\Requests\Auth\LoginRequest` | `LoginController@login` | `username`: required, string, max:50.<br>`password`: required, string.<br>Throttle key check. |
| `App\Http\Requests\Academic\StoreAcademicYearRequest` | `AcademicYearController@store` | `name`: required, string, max:50, unique:academic_years.<br>`start_date`: required, date.<br>`end_date`: required, date, after:start_date. |
| `App\Http\Requests\Academic\UpdateAcademicYearRequest` | `AcademicYearController@update` | `name`: required, string, max:50, unique:academic_years,id.<br>`start_date`: required, date.<br>`end_date`: required, date, after:start_date. |
| `App\Http\Requests\Academic\StoreTermRequest` | `TermController@store` | `academic_year_id`: required, exists:academic_years,id.<br>`name`: required, string, max:50.<br>`term_number`: required, integer, min:1, max:10.<br>`start_date`: required, date.<br>`end_date`: required, date, after:start_date. |
| `App\Http\Requests\Academic\StoreSchoolClassRequest` | `SchoolClassController@store` | `name`: required, string, max:50, unique:classes,name.<br>`numeric_grade`: required, integer, min:1, max:12. |
| `App\Http\Requests\Academic\StoreSectionRequest` | `SectionController@store` | `class_id`: required, exists:classes,id.<br>`name`: required, string, max:10. |
| `App\Http\Requests\Academic\StoreSubjectRequest` | `SubjectController@store` | `name`: required, string, max:100, unique:subjects,name.<br>`code`: required, string, max:20, unique:subjects,code. |
| `App\Http\Requests\Academic\StoreClassSubjectRequest` | `ClassSubjectController@store` | `academic_year_id`: required, exists:academic_years,id.<br>`class_id`: required, exists:classes,id.<br>`section_id`: nullable, exists:sections,id.<br>`subject_id`: required, exists:subjects,id.<br>`category`: required, enum:SubjectCategory.<br>`display_order`: required, integer, min:1. |
| `App\Http\Requests\Students\StoreStudentRequest` | `StudentController@store` | `student_name`: required, string, max:200.<br>`academic_year_id`: required, exists:academic_years,id.<br>`class_id`: required, exists:classes,id.<br>`section_id`: required, exists:sections,id.<br>`roll_number`: required, integer, min:1.<br>`effective_from`: required, date. |
| `App\Http\Requests\Students\UpdateStudentRequest` | `StudentController@update` | Master record updates: `student_name`: required, string, max:200. |
| `App\Http\Requests\Students\InternalTransferStudentRequest` | `StudentPlacementController@transfer` | `target_section_id`: required, exists:sections,id.<br>`transfer_date`: required, date.<br>`reason`: nullable, string, max:255. |
| `App\Http\Requests\Students\UpdateStudentSubjectAllocationRequest` | `StudentSubjectAllocationController@update` | `selected_subject_ids`: required, array.<br>`selected_subject_ids.*`: integer, exists:subjects,id. |
| `App\Http\Requests\Students\StudentImportRequest` | `StudentController@import` | `file`: required, file, mimes:csv,txt, max:5120 (5MB). |
| `App\Http\Requests\Teachers\StoreTeacherAssignmentRequest` | `TeacherAssignmentController@store` | `user_id`: required, exists:users,id.<br>`academic_year_id`: required, exists:academic_years,id.<br>`class_id`: required, exists:classes,id.<br>`section_id`: required, exists:sections,id.<br>`assignment_type`: required, enum:TeacherAssignmentType.<br>`subject_id`: required_if:assignment_type,subject_teacher\|nullable, exists:subjects,id.<br>`effective_from`: required, date.<br>`effective_to`: nullable, date, after_or_equal:effective_from. |
| `App\Http\Requests\Assessments\StoreAssessmentTypeRequest` | `AssessmentTypeController@store` | `name`: required, string, max:50, unique:assessment_types.<br>`code`: required, string, max:20, unique:assessment_types. |
| `App\Http\Requests\Assessments\StoreAssessmentRequest` | `AssessmentController@store` | `academic_year_id`: required, exists:academic_years,id.<br>`term_id`: required, exists:terms,id.<br>`assessment_type_id`: required, exists:assessment_types,id.<br>`name`: required, string, max:100.<br>`start_date`: required, date.<br>`end_date`: required, date, after_or_equal:start_date. |
| `App\Http\Requests\Assessments\StoreAssessmentApplicabilityRequest` | `AssessmentApplicabilityController@store` | `assessment_id`: required, exists:assessments,id.<br>`class_subject_id`: required, exists:class_subjects,id.<br>`max_marks`: required, numeric, min:1.00, max:500.00. |
| `App\Http\Requests\Marks\BatchSaveMarksRequest` | `MarkEntryController@batchSave` | `academic_year_id`: required, exists:academic_years,id.<br>`class_id`: required, exists:classes,id.<br>`section_id`: required, exists:sections,id.<br>`subject_id`: required, exists:subjects,id.<br>`assessment_id`: required, exists:assessments,id.<br>`marks`: required, array, min:1, max:100.<br>`marks.*.student_academic_record_id`: required, integer, exists:student_academic_records,id.<br>`marks.*.result_status`: required, in:blank,numeric,absent.<br>`marks.*.mark_value`: required_if:marks.*.result_status,numeric\|nullable, numeric, min:0.00, max:500.00. |
| `App\Http\Requests\Marks\CorrectMarkRequest` | `MarkCorrectionController@correct` | `mark_id`: required, exists:marks,id.<br>`result_status`: required, in:blank,numeric,absent.<br>`mark_value`: required_if:result_status,numeric\|nullable, numeric, min:0.00, max:500.00.<br>`reason`: required, string, min:5, max:500. |
| `App\Http\Requests\Attendance\BatchSaveAttendanceRequest` | `AttendanceController@batchSave` | `academic_year_id`: required, exists:academic_years,id.<br>`term_id`: required, exists:terms,id.<br>`class_id`: required, exists:classes,id.<br>`section_id`: required, exists:sections,id.<br>`attendance`: required, array, min:1, max:100.<br>`attendance.*.student_academic_record_id`: required, integer, exists:student_academic_records,id.<br>`attendance.*.total_working_days`: required, integer, min:0, max:365.<br>`attendance.*.days_attended`: required, integer, min:0, lte:attendance.*.total_working_days. |
| `App\Http\Requests\Calculations\UpdateCalculationSettingRequest` | `CalculationSettingController@update` | `class_id`: required, exists:classes,id.<br>`calculation_method`: required, in:average_percentage,combined_marks. |
| `App\Http\Requests\Reports\StoreReportConfigurationRequest` | `ReportConfigurationController@store` | `academic_year_id`: nullable, exists:academic_years,id.<br>`name`: required, string, max:150.<br>`report_type`: required, in:exam,term,final.<br>`configuration_data`: nullable, array. |
| `App\Http\Requests\Reports\StoreReportAssessmentSelectionRequest` | `ReportAssessmentSelectionController@store` | `report_configuration_id`: required, exists:report_configurations,id.<br>`assessment_id`: required, exists:assessments,id.<br>`display_order`: required, integer, min:1.<br>`is_displayed`: required, boolean. |
| `App\Http\Requests\Reports\GenerateReportRequest` | `ReportGenerationController@generate` | `student_academic_record_id`: required, exists:student_academic_records,id.<br>`report_type`: required, in:exam,term,final.<br>`term_id`: required_if:report_type,term\|nullable, exists:terms,id.<br>`assessment_id`: required_if:report_type,exam\|nullable, exists:assessments,id. |
| `App\Http\Requests\Settings\UpdateSchoolSettingRequest` | `SchoolSettingController@update` | `school_name`: required, string, max:200.<br>`pass_mark`: required, numeric, min:0.00, max:500.00.<br>`logo_file`: nullable, image, mimes:png,jpg,jpeg,webp, max:2048 (2MB). |
| `App\Http\Requests\Admin\StoreUserRequest` | `UserController@store` | `username`: required, string, max:100, unique:users,username.<br>`display_name`: required, string, max:150.<br>`email`: nullable, email, max:255, unique:users,email.<br>`password`: required, string, min:8, confirmed.<br>`role_id`: required, exists:roles,id. |
| `App\Http\Requests\Admin\UpdateUserRequest` | `UserController@update` | `display_name`: required, string, max:150.<br>`email`: nullable, email, max:255, unique:users,email,id.<br>`role_id`: required, exists:roles,id.<br>`is_active`: required, boolean. |
| `App\Http\Requests\Admin\ResetUserPasswordRequest` | `UserController@resetPassword` | `password`: required, string, min:8, confirmed. |

---

## 11. Mark HTTP Architecture & Batch Save Protocol

Mark entry is the most critical workflow in the institution. The HTTP layer must maintain strict transactional and mathematical integrity.

### 11.1 The Ternary Mark State Contract
The HTTP layer and Form Requests enforce three mutually exclusive states:
1. **`blank`:** The student has not yet been assessed.
   - Request Payload: `{"result_status": "blank", "mark_value": null}`
   - Eloquent Model: `$mark->result_status = 'blank'; $mark->mark_value = null;`
   - Final Report Impact: Blocks final report generation (incomplete grade).
2. **`numeric`:** The student sat the exam and received a valid score.
   - Request Payload: `{"result_status": "numeric", "mark_value": 45.50}`
   - Eloquent Model: `$mark->result_status = 'numeric'; $mark->mark_value = '45.50';`
   - Calculations: Contributes `45.50` to obtained marks and totals.
3. **`absent`:** The student was absent from the assessment.
   - Request Payload: `{"result_status": "absent", "mark_value": null}`
   - Eloquent Model: `$mark->result_status = 'absent'; $mark->mark_value = null;`
   - Report Presentation: Displays as `"A"` on marksheets and report cards.
   - Calculations: Contributes `0.00` to obtained marks. Assessment is considered completed (does not block report generation).

*Prohibited Coercions:*
- ❌ Never coerce `null` / `blank` into `0.00`.
- ❌ Never coerce `"absent"` into `0.00` in the request payload.
- ❌ Never permit `mark_value` to be populated when `result_status` is `blank` or `absent`.

### 11.2 Batch Save Endpoint Protocol
When teachers enter marks via the spreadsheet-style grid, Vanilla JS submits an asynchronous batch payload:

```
POST /marks/batch-save
Content-Type: application/json
X-CSRF-TOKEN: [token]

{
  "academic_year_id": 1,
  "class_id": 2,
  "section_id": 3,
  "subject_id": 5,
  "assessment_id": 4,
  "marks": [
    {
      "student_academic_record_id": 101,
      "result_status": "numeric",
      "mark_value": 85.50
    },
    {
      "student_academic_record_id": 102,
      "result_status": "absent",
      "mark_value": null
    },
    {
      "student_academic_record_id": 103,
      "result_status": "blank",
      "mark_value": null
    }
  ]
}
```

### 11.3 Independent Server-Side Scope Authorization (Atomic Guarantee)
A teacher submitting a batch cannot gain authority merely because they are authorized for the first record. The controller and policy execute an atomic verification:

```
                  Client Submits Batch (N records)
                                 │
                                 ▼
                     [BatchSaveMarksRequest]
           - Validates structure & scalar formats
                                 │
                                 ▼
                       [MarkPolicy@batchSave]
       - Verifies Year is OPEN (or Actor is Admin/Staff)
       - Verifies User has Class Teacher authority for Class/Section
         OR Subject Teacher authority for Subject
                                 │
                                 ▼
                 [MarkEntryService::saveBatch]
                     BEGIN TRANSACTION
                                 │
      ┌──────────────────────────┴──────────────────────────┐
      ▼                                                     ▼
For Each Mark in Batch:                               Authorization/Scope Check:
- Resolve StudentAcademicRecord                       Does placement match target
- Verify placement matches Class/Section              Class 2, Section 3, Year 1?
- Fetch authoritative AssessmentApplicability              │
  for Assessment 4 + ClassSubject 5                       ▼
- Verify mark_value <= max_marks                        NO: FAIL ENTIRE BATCH!
      │                                                 ROLLBACK & 403 / 422
      ▼
   ALL VALID?
      │
      ├─► YES: Upsert Marks, Write Audit Logs, COMMIT TRANSACTION -> Return JSON 200
      └─► NO:  ROLLBACK TRANSACTION -> Return JSON 422 with detailed row errors
```

*Partial Failure Prohibition:* The batch save is strictly atomic. If even one submitted student does not belong to the authorized section, or if a single mark exceeds the authoritative maximum mark, the entire database transaction rolls back. Partial saves that leave student rosters in inconsistent states are strictly prohibited.

---

## 12. Attendance HTTP Architecture

Attendance records track term-level participation per student academic placement.

### 12.1 Authorized Actors & Scopes
- **Administrator & Office Staff:** Full authority across all classes and sections.
- **Class Teacher:** Authorized strictly for students enrolled in their assigned class and section.
- **Subject Teacher:** **Zero authority.** HTTP POST requests to `/attendance/batch-save` from a Subject Teacher return HTTP 403 Forbidden.

### 12.2 Mathematical Invariants & Safe 0/0 Handling
Form Requests and `AttendancePolicy` enforce:
1. `days_attended <= total_working_days`: Form request validation rule `lte:attendance.*.total_working_days`.
2. Safe Zero Handling: When `total_working_days = 0`, the student attended 0 days out of 0 days held. In report rendering and service calculations, this scenario outputs `"N/A"` to prevent division-by-zero runtime exceptions.

---

## 13. Student Placement & Elective Allocation Architecture

Student identity is partitioned into:
- Master Student Identity (`students`): `student_name` and internal primary key `students.id`.
- Academic Placement (`student_academic_records`): Class, section, roll number, and placement status for a specific academic year.

### 13.1 Internal Section Transfer Protocol (`POST /students/{student}/transfer`)
When a student transfers between sections (e.g. 8-A to 8-B):
1. Historical Record Preserved: Prior `student_academic_records` row has its `status` updated to `internal_transfer` and `effective_to` set to the transfer date.
2. New Placement Created: A new `student_academic_records` row is inserted for the destination section with `status = 'active'` and `effective_from` set to the transfer date.
3. Historical Marks Untouched: Historical marks remain linked to the prior `student_academic_record_id`. No endpoint ever modifies foreign keys of past marks.

### 13.2 Elective Allocation & The Elective Lockout Rule
- Endpoint: `PATCH /student-subject-allocations/{studentAcademicRecord}`
- Business Invariant (BR-021, DEC-019): A student's elective allocation can be changed during an academic year **only before marks exist** for that student in that elective subject.
- Enforcement: `StudentSubjectService::updateAllocations()` queries PostgreSQL for existing rows in `marks` for the target student and subject. If any mark record exists (`blank` with audit history, `numeric`, or `absent`), the update is rejected with a domain exception, translating to HTTP 422 with message: *"Cannot modify elective subject allocation: Marks have already been recorded for this student."*

---

## 14. Report Generation & Download HTTP Architecture

Report compilation and PDF distribution are strictly segregated operations.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    REPORT WORKFLOW & DOWNLOAD BOUNDARIES                    │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
            ┌──────────────────────────┴──────────────────────────┐
            ▼                                                     ▼
[POST /reports/preview]                               [POST /reports/generate]
- Administrator & Office Staff                        - Administrator & Office Staff
- Class Teacher (Assigned Classroom)                  - Class Teacher (Assigned Classroom)
- Subject Teacher DENIED (403)                        - Subject Teacher DENIED (403)
- Generates watermarked draft preview                 - Compiles Final Formal Report Card
- Validates student mark status                       - Requires 100% complete required marks
- Does NOT increment formal revision                  - Increments generated_reports.revision_number
- Temporary stream or preview HTML                    - Stores PDF in private storage disk
                                                                  │
                                                                  ▼
                                                      [GET /reports/download/{id}]
                                                      - Administrator & Office Staff
                                                      - Class Teacher (Assigned Classroom)
                                                      - Subject Teacher DENIED (403)
                                                      - Streams file with Content-Disposition
```

### 14.1 The Report Completion Invariant
Before compiling a final report card, `ReportGenerationService` verifies mark completeness for all mandatory subjects:
- Missing / `blank` mark on mandatory subject: **Incomplete.** Generation aborted with HTTP 422: *"Report card cannot be generated: Incomplete assessment marks exist for required subjects."*
- `numeric` mark: Complete.
- `absent` mark: Complete. (An absent student can receive their report card showing `"A"`).
- Incompleteness is isolated per student: An incomplete mark for Student A does not block report generation for Student B.

### 14.2 Secure PDF Revision Streaming (No Public URLs)
- Route: `GET /reports/download/{generatedReport}` (`reports.download`)
- Controller: `App\Http\Controllers\Reports\GeneratedReportDownloadController@download`
- Authorization: `Gate::authorize('download', $generatedReport)` invoking `ReportPolicy@download`.
- Download Rules (DEC-023, DEC-026, DEC-038):
  - **Administrator:** Allowed (broad institutional scope).
  - **Office Staff:** Allowed (broad operational academic scope).
  - **Class Teacher:** Allowed **only** when the `GeneratedReport`'s associated `StudentAcademicRecord` belongs to the teacher's currently active assigned classroom scope (`academic_year_id`, `class_id`, `section_id`). Class Teachers are never authorized based on role name alone.
  - **Subject Teacher:** Denied -> HTTP 403 Forbidden.
- Storage & Delivery:
  - Files reside under `storage/app/private/reports/` outside the web root. Direct URLs (`/storage/reports/card.pdf`) do not exist.
  - Streaming: The controller returns a binary stream via `Storage::disk('private_reports')->download()`.

---

## 15. Academic Year Lifecycle HTTP Architecture

The academic year lifecycle dictates global data mutability across the institution.

### 15.1 State Transitions
- Transitions: `open` $\rightarrow$ `closed` (`POST /academic-years/{academicYear}/close`) and `closed` $\rightarrow$ `open` (`POST /academic-years/{academicYear}/reopen`).
- Allowed Actor: User holding the `Administrator` role exclusively (`AcademicYearPolicy@close`, `AcademicYearPolicy@reopen`). All other roles receive HTTP 403.

### 15.2 Closed Year HTTP Behavior
When `academic_years.status === 'closed'`:
- **Teachers (Marks & Attendance Read-Only):** All mark mutations (`POST /marks/batch-save`) and attendance mutations (`POST /attendance/batch-save`) return HTTP 403 Forbidden. Mark grid views remain accessible in read-only mode.
- **Class Teachers (Report Generation & Download Permitted):** Report generation (`POST /reports/generate`) and report downloading (`GET /reports/download/{generatedReport}`) are reporting operations, **not** data mutations. Class Teachers retain full authority to generate and download report cards for their assigned classroom scope in closed academic years, provided mark completion requirements are satisfied. This reporting privilege does **not** grant permission to modify marks or attendance.
- **Office Staff & Administrators:** Retain write access to execute legitimate mark corrections via `POST /marks/{mark}/correct`. Every closed-year correction mandates an audit reason (`min:5, max:500`) and logs both `before_data` and `after_data` to `audit_logs`.
- **Reopening:** Reopening an academic year does not automatically restore teacher mark-editing permissions.

---

## 16. User Administration & Audit Log HTTP Architecture

User credentials and activity histories represent the highest security perimeter.

### 16.1 User Administration (`/admin/users`)
- Authorization: Strictly restricted to the `Administrator` role (`CheckRole:Administrator` + `UserPolicy`). Office Staff and Teachers attempting access receive HTTP 403.
- Destruction Prohibition: User accounts are **never hard deleted**. Soft-delete columns (`deleted_at`) do not exist.
- Deactivation: Handled via `POST /admin/users/{user}/deactivate`. Sets `users.is_active = FALSE`. The deactivated user's active session is terminated on their very next HTTP request by `EnsureUserIsActive`.
- Reactivation: Handled via `POST /admin/users/{user}/reactivate`. Restores `users.is_active = TRUE`.
- Password Resets: Handled via `POST /admin/users/{user}/reset-password`. Admin sets a new temporary password; action is audited without logging raw passwords.

### 16.2 Immutable Audit Inspection (`/admin/audit-logs`)
- Authorization: Strictly restricted to `Administrator` (`AuditLogPolicy@viewAny`).
- Immutability: The audit log is append-only. No routes exist for `POST`, `PUT`, `PATCH`, or `DELETE` on audit logs. Any attempt to access mutating HTTP verbs returns HTTP 405 (Method Not Allowed).

---

## 17. Student CSV Import HTTP Architecture

Student onboarding supports bulk CSV upload adhering to BRD V1.3 specifications.

- Route: `POST /students/import` (`students.import.submit`)
- Allowed Actors: `Administrator`, `Office Staff`.
- Form Request: `App\Http\Requests\Students\StudentImportRequest`
  - Validates `file`: required, file, mimes:csv,txt, max:5120 (5MB).
- Import Business Invariants (BRD V1.3 & DEC-072 Reconciliation):
  1. **Historical Baseline (Pre-Phase 10):** Under original BRD V1.3 rules, admission numbers were prohibited and every CSV row created a new student master without identity matching.
  2. **Approved Phase 10 Contract (DEC-072):** Formally superseding the prohibition, `admission_number` (`VARCHAR(50) NOT NULL UNIQUE`) is the unique student business identifier. Form upload supplies Academic Year, Class, and Section context. CSV contains `admission_number,student_name,roll_number`.
  3. **Identity Matching Rules (DEC-072 Cases 1–6):**
     - Case 1 (New Admission Number): Creates new student master and initial academic placement.
     - Case 2 (Existing Admission Number, Matching Name): Reuses existing `students.id` master record; creates or confirms academic placement.
     - Case 3 (Existing Admission Number, Mismatched Name): Row is rejected; existing master name is protected and never overwritten.
     - Case 4 (Duplicate Admission Number in same CSV): Conflicting rows rejected with line numbers reported.
     - Case 5 (Blank/Missing Admission Number): Rejected with row-level error.
     - Case 6 (Historical Placement Preservation): Prior academic placements and marks remain preserved; historical records are never deleted or rewritten.
  4. Partial imports are supported: valid rows are inserted; invalid rows are skipped with row-level error reporting.
  5. Response returns a summary Blade view listing: total rows processed, successful insertions, and a table of rejected rows with exact validation failure reasons.

---

## 18. Blade & Vanilla JavaScript Boundary

To ensure complete clarity on client vs. server responsibilities:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         CLIENT VS SERVER RESPONSIBILITY                     │
├───────────────────────┬─────────────────────────────────────────────────────┤
│ Technology            │ Permitted Capabilities & Architectural Role         │
├───────────────────────┼─────────────────────────────────────────────────────┤
│ **Blade Templates**   │ - Server-side HTML rendering.                       │
│                       │ - Layout composition and component slots.           │
│                       │ - User experience conditional rendering (@can,      │
│                       │   @cannot, @if) to hide irrelevant buttons.         │
│                       │ - CSRF token embedding via @csrf directive.         │
│                       │ - Zero security authority.                          │
├───────────────────────┼─────────────────────────────────────────────────────┤
│ **Vanilla JavaScript**│ - Client-side progressive enhancement.              │
│                       │ - Spreadsheet mark grid keyboard navigation.        │
│                       │ - Dynamic option loading (dependent dropdowns).     │
│                       │ - Asynchronous Fetch requests for mark batch saves. │
│                       │ - Real-time DOM totals calculation for visual aid.   │
│                       │ - Zero security authority. Zero calculation trust.  │
├───────────────────────┼─────────────────────────────────────────────────────┤
│ **Laravel HTTP/Domain**│ - Absolute authorization, validation, calculation, │
│                       │   database persistence, and audit authority.        │
└───────────────────────┴─────────────────────────────────────────────────────┘
```

---

## 19. HTTP Response & Error Handling Conventions

The application standardizes responses across traditional HTML form submissions and asynchronous Vanilla JS calls.

### 19.1 Traditional Form Submissions (Blade)
- **Success:** Returns HTTP 302/303 Redirect to a named route accompanied by flash session data:
  ```php
  return redirect()->route('students.index')->with('success', 'Student enrolled successfully.');
  ```
- **Validation Failure:** Automatic HTTP 302 Redirect back to previous URL with `$errors` message bag and `old()` input.
- **Authorization Failure:** HTTP 403 Forbidden. Renders `resources/views/errors/403.blade.php`.
- **Authentication Failure:** HTTP 302 Redirect to `/login`.
- **Not Found:** HTTP 404 Not Found. Renders `resources/views/errors/404.blade.php`.
- **CSRF Token Expiry:** HTTP 419 Page Expired. Renders `resources/views/errors/419.blade.php`.

### 19.2 Asynchronous Vanilla JS Responses (Fetch)
For interactive components (e.g. mark grid batch saving), responses use structured JSON:
- **Success (HTTP 200 OK):**
  ```json
  {
    "success": true,
    "message": "Marks saved successfully.",
    "data": {
      "updated_count": 28,
      "academic_year_id": 1
    }
  }
  ```
- **Validation Failure (HTTP 422 Unprocessable Entity):**
  ```json
  {
    "success": false,
    "message": "The given data was invalid.",
    "errors": {
      "marks.4.mark_value": ["The mark value must not exceed the maximum allowed mark of 50.00."]
    }
  }
  ```
- **Authorization Failure (HTTP 403 Forbidden):**
  ```json
  {
    "success": false,
    "message": "You are not authorized to edit marks for this subject and classroom."
  }
  ```
- **Rate Limit Exceeded (HTTP 429 Too Many Requests):**
  ```json
  {
    "success": false,
    "message": "Too many requests. Please try again in 42 seconds."
  }
  ```

---

## 20. Comprehensive Responsibility Matrix

This matrix establishes the non-overlapping responsibilities across each layer of the application architecture:

| System Concern | Middleware | Form Request | Policy | Controller | Service | Eloquent / DB |
|---|---|---|---|---|---|---|
| **Authentication Verification** | `auth` | ❌ | ❌ | ❌ | ❌ | Password Hash |
| **Active User Enforcement** | `EnsureUserIsActive` | ❌ | ❌ | ❌ | ❌ | `users.is_active` |
| **CSRF Defense** | `VerifyCsrfToken` | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Broad Role Perimeter** | `CheckRole` | ❌ | ❌ | ❌ | ❌ | `roles` table |
| **Teacher Contextual Scope** | ❌ | ❌ | `MarkPolicy` | ❌ | `TeacherAssignmentService` | `teacher_assignments` |
| **Input Shape & Data Types** | ❌ | Form Requests | ❌ | ❌ | ❌ | Column Types |
| **Ternary Mark State Parsing**| ❌ | `BatchSaveMarksRequest` | ❌ | ❌ | `MarkEntryService` | `chk_marks_status_value_consistency` |
| **Authoritative Max Mark Check**| ❌ | ❌ | ❌ | ❌ | `MarkEntryService` | DB Max Marks |
| **Attendance Bounds** | ❌ | `BatchSaveAttendanceRequest` | ❌ | ❌ | ❌ | `chk_attendance_days_within_total` |
| **Closed-Year Permission** | ❌ | ❌ | `MarkPolicy` | ❌ | `MarkEntryService` | `academic_years.status` |
| **Elective Lockout Invariant** | ❌ | ❌ | ❌ | ❌ | `StudentSubjectService` | `marks` existence |
| **Section Transfer Workflow** | ❌ | `InternalTransferRequest` | `StudentPolicy` | ❌ | `StudentPlacementService` | `student_academic_records` |
| **Report Generation Authorization** | ❌ | ❌ | `ReportPolicy` | `ReportGenerationController` | ❌ | Relational Scope |
| **Report Completeness Check** | ❌ | ❌ | ❌ | ❌ | `ReportGenerationService` | Mandatory marks |
| **Report Revision Increment** | ❌ | ❌ | ❌ | ❌ | `ReportGenerationService` | `uk_gr_revision_identity` |
| **Report PDF Download Access** | ❌ | ❌ | `ReportPolicy` | `DownloadController` | ❌ | Private Disk |
| **Audit Trail Creation** | ❌ | ❌ | ❌ | ❌ | `AuditLogService` | `audit_logs` (Append-only) |
| **Database Transactions** | ❌ | ❌ | ❌ | ❌ | Services (`DB::transaction`) | ACID Boundaries |
| **Relational Integrity** | ❌ | ❌ | ❌ | ❌ | ❌ | 43 Foreign Keys (RESTRICT) |

---

## 21. Request Flow Scenarios (Examples A through V)

The following end-to-end walkthroughs demonstrate the execution path and security reasoning across major application workflows:

### EXAMPLE A: Subject Teacher updates Math mark in assigned Class 8-A
- **Request:** `POST /marks/batch-save` with `class_id: 8`, `section_id: 'A'`, `subject_id: 'Math'`, `marks: [...]`.
- **Pipeline:** `web` $\rightarrow$ `auth` $\rightarrow$ `EnsureUserIsActive` (Active) $\rightarrow$ `BatchSaveMarksRequest` (Valid syntax).
- **Policy:** `MarkPolicy@batchSave` calls `TeacherAssignmentService->isAuthorized(user, year, 8, 'A', 'Math')`.
  - Resolution: Finds active row: `assignment_type = 'subject_teacher'`, `subject_id = 'Math'`. Result: **AUTHORIZED**.
- **Service:** `MarkEntryService::saveBatch` verifies mark $\le$ applicability max_marks, writes to `marks`, writes to `audit_logs`.
- **Result:** HTTP 200 JSON: `{"success": true, "message": "Marks saved successfully."}`.

### EXAMPLE B: Subject Teacher attempts Science mark update in Class 8-A
- **Request:** `POST /marks/batch-save` with `class_id: 8`, `section_id: 'A'`, `subject_id: 'Science'`.
- **Pipeline:** `web` $\rightarrow$ `auth` $\rightarrow$ `EnsureUserIsActive` $\rightarrow$ `BatchSaveMarksRequest` (Valid syntax).
- **Policy:** `MarkPolicy@batchSave` calls `TeacherAssignmentService->isAuthorized(user, year, 8, 'A', 'Science')`.
  - Resolution: User holds subject assignment only for Math. No Class Teacher assignment exists. Result: **DENIED (403)**.
- **Service:** Never invoked. Database is completely untouched.
- **Result:** HTTP 403 Forbidden JSON: `{"success": false, "message": "Unauthorized."}`.

### EXAMPLE C: Class Teacher updates Science mark in assigned Class 8-A
- **Request:** `POST /marks/batch-save` with `class_id: 8`, `section_id: 'A'`, `subject_id: 'Science'`.
- **Pipeline:** `web` $\rightarrow$ `auth` $\rightarrow$ `EnsureUserIsActive` $\rightarrow$ `BatchSaveMarksRequest` (Valid syntax).
- **Policy:** `MarkPolicy@batchSave` queries `teacher_assignments`.
  - Resolution: Finds active row: `assignment_type = 'class_teacher'`, `subject_id = NULL` for 8-A. Under DEC-034, Class Teacher holds authority over **all applicable subjects** in 8-A. Result: **AUTHORIZED**.
- **Service:** `MarkEntryService` executes batch save inside transaction, logs audit trail with actor ID.
- **Result:** HTTP 200 JSON Success.

### EXAMPLE D: Teacher attempts mark update in unassigned Class 8-B
- **Request:** `POST /marks/batch-save` with `class_id: 8`, `section_id: 'B'`.
- **Pipeline:** `web` $\rightarrow$ `auth` $\rightarrow$ `EnsureUserIsActive` $\rightarrow$ `BatchSaveMarksRequest`.
- **Policy:** `MarkPolicy@batchSave` finds no active assignment for Section 'B'. Result: **DENIED (403)**.
- **Result:** HTTP 403 Forbidden.

### EXAMPLE E: Office Staff corrects mark in Closed Academic Year
- **Request:** `POST /marks/501/correct` with `result_status: "numeric"`, `mark_value: 72.00`, `reason: "Approved re-evaluation"`.
- **Pipeline:** `web` $\rightarrow$ `auth` $\rightarrow$ `EnsureUserIsActive` $\rightarrow$ `CorrectMarkRequest` (Validates reason string).
- **Policy:** `MarkPolicy@correct` checks actor role. User has role `Office Staff`. Under DEC-035, Office Staff retain correction authority on closed years. Result: **AUTHORIZED**.
- **Service:** `MarkEntryService::correctMark` executes inside transaction, updates `marks.mark_value`, writes audit log capturing previous mark, new mark, actor ID, and correction reason.
- **Result:** HTTP 302 Redirect with flash: *"Mark corrected and audited successfully."*

### EXAMPLE F: Subject Teacher attempts mark correction in Closed Academic Year
- **Request:** `POST /marks/501/correct` with `mark_value: 75.00`, `reason: "Late submission"`.
- **Pipeline:** `web` $\rightarrow$ `auth` $\rightarrow$ `EnsureUserIsActive` $\rightarrow$ `CorrectMarkRequest`.
- **Policy:** `MarkPolicy@correct` detects academic year is `closed` and actor has role `Teacher`. Under DEC-035, teachers are strictly read-only on closed years. Result: **DENIED (403)**.
- **Result:** HTTP 403 Forbidden.

### EXAMPLE G: Teacher submits manipulated `subject_id`
- **Request:** Attacker intercepts POST payload and replaces `subject_id: 1` (Math) with `subject_id: 2` (Physics).
- **Pipeline:** Form request validates syntax.
- **Policy:** `MarkPolicy` ignores frontend claims and queries PostgreSQL using the submitted `subject_id: 2`. Since the teacher has no assignment for Physics, authorization fails. Result: **DENIED (403)** (TH-03 mitigated).
- **Result:** HTTP 403 Forbidden.

### EXAMPLE H: Teacher submits another student's Mark ID (IDOR Attack)
- **Request:** Attacker submits `POST /marks/9999/correct` where Mark 9999 belongs to Class 10-C.
- **Pipeline:** Route model binding resolves `Mark 9999`.
- **Policy:** `MarkPolicy@correct` traverses: `Mark 9999` $\rightarrow$ `StudentAcademicRecord` (Class 10-C). Verifies user assignment against Class 10-C. Result: **DENIED (403)** (TH-02 mitigated).
- **Result:** HTTP 403 Forbidden.

### EXAMPLE I: Batch containing 9 authorized marks + 1 unauthorized mark
- **Request:** Teacher submits a batch array containing 9 students from assigned 8-A and 1 injected student from 8-B.
- **Pipeline:** Form request validates structure.
- **Service:** `MarkEntryService::saveBatch` evaluates each student's placement within the transaction. Upon detecting the student belonging to Section 8-B, it throws an `UnauthorizedContextException`.
- **Transaction:** `DB::rollBack()` rolls back all 9 authorized marks. Zero rows are saved.
- **Result:** HTTP 403 Forbidden JSON: `{"success": false, "message": "Batch contains records outside your authorized scope."}`.

### EXAMPLE J: Deactivated user sends request with existing session
- **Request:** Deactivated teacher clicks "Save Marks" using an existing active browser session.
- **Pipeline:** `web` $\rightarrow$ `auth` (Session valid) $\rightarrow$ `EnsureUserIsActive`.
- **Middleware:** Evaluates `Auth::user()->is_active`. Value is `false`.
  - Executes: `Auth::logout()`, `session()->invalidate()`, `session()->regenerateToken()`.
- **Result:** HTTP 302 Redirect to `/login` with flash error: *"Your account has been deactivated. Please contact an Administrator."* (TH-05 mitigated).

### EXAMPLE K: Student elective change attempted after marks exist
- **Request:** `PATCH /student-subject-allocations/42` with new elective subject.
- **Pipeline:** Form request validates format.
- **Policy:** `StudentPolicy@updateAllocation` authorizes staff role.
- **Service:** `StudentSubjectService::updateAllocations` queries `marks` table for placement 42 and target subject. Discovers an existing numeric mark record.
- **Result:** Throws `ElectiveModificationLockedException`. Controller catches and returns HTTP 422 Unprocessable Entity with error message: *"Cannot modify elective: Marks already exist for this student in this subject."*

### EXAMPLE L: Final report card generation requested for incomplete student
- **Request:** `POST /reports/generate` for Student X (Report Type: `final`).
- **Pipeline:** Form request validates syntax.
- **Policy:** `ReportPolicy@generate` authorizes Office Staff.
- **Service:** `ReportGenerationService::generate` checks marks across all mandatory subjects. Discovers Subject 'English' has `result_status = 'blank'`.
- **Result:** Aborts generation. Returns HTTP 422 Unprocessable Entity: *"Cannot generate final report: Incomplete marks exist for required subject English."*

### EXAMPLE M: Class Teacher generates final report in assigned classroom
- **Request:** `POST /reports/generate` with `student_academic_record_id: 101` (Class 8-A), `report_type: "final"`, `term_id: 1`.
- **Pipeline:** `web` $\rightarrow$ `auth` $\rightarrow$ `EnsureUserIsActive` $\rightarrow$ `GenerateReportRequest` (Syntax valid) $\rightarrow$ `ReportPolicy@generate`.
- **Policy:** Resolves target `StudentAcademicRecord` (Academic Year 1, Class 8, Section 'A'). Evaluates user assignments: finds active assignment with `assignment_type = 'class_teacher'`, `class_id = 8`, `section_id = 'A'`, `subject_id = NULL`, and valid date boundaries. Result: **AUTHORIZED**.
- **Service:** `ReportGenerationService::generateReportCard` verifies all mandatory subjects have valid marks (no `blank`), compiles report, increments `revision_number`, stores PDF to private storage, and records `generated_reports` row.
- **Result:** HTTP 302 Redirect to `reports.index` with flash: *"Report card generated successfully."*

### EXAMPLE N: Class Teacher attempts final report generation for another classroom
- **Request:** `POST /reports/generate` with `student_academic_record_id: 205` (Class 8-B).
- **Pipeline:** `web` $\rightarrow$ `auth` $\rightarrow$ `EnsureUserIsActive` $\rightarrow$ `GenerateReportRequest` $\rightarrow$ `ReportPolicy@generate`.
- **Policy:** Target placement belongs to Section 8-B. Authenticated Class Teacher holds assignment strictly for Section 8-A. No matching assignment found. Result: **DENIED (403)**.
- **Service:** Never invoked. No report or file is generated.
- **Result:** HTTP 403 Forbidden.

### EXAMPLE O: Class Teacher downloads report from assigned classroom
- **Request:** `GET /reports/download/120`.
- **Pipeline:** `web` $\rightarrow$ `auth` $\rightarrow$ `EnsureUserIsActive` $\rightarrow$ Route Model Binding (`GeneratedReport 120`) $\rightarrow$ `ReportPolicy@download`.
- **Policy:** Traverses `GeneratedReport 120` $\rightarrow$ `StudentAcademicRecord` (Class 8-A). Evaluates teacher's active assignment scope: matches Class 8-A. Result: **AUTHORIZED**.
- **Controller:** `GeneratedReportDownloadController` verifies file exists on `private_reports` disk and streams binary download with sanitized filename.
- **Result:** HTTP 200 Binary PDF Stream.

### EXAMPLE P: Class Teacher attempts to download another classroom's report
- **Request:** `GET /reports/download/125` (where Report 125 belongs to Class 8-B).
- **Pipeline:** `web` $\rightarrow$ `auth` $\rightarrow$ `EnsureUserIsActive` $\rightarrow$ Route Model Binding (`GeneratedReport 125`) $\rightarrow$ `ReportPolicy@download`.
- **Policy:** Traverses `GeneratedReport 125` $\rightarrow$ `StudentAcademicRecord` (Class 8-B). Class Teacher holds assignment only for 8-A. Scope mismatch. Result: **DENIED (403)**.
- **Controller:** Stream never initiated. File is not accessed.
- **Result:** HTTP 403 Forbidden (TH-10 mitigated).

### EXAMPLE Q: Subject Teacher attempts final report generation
- **Request:** `POST /reports/generate` with `student_academic_record_id: 101`.
- **Authenticated Actor:** Subject Teacher (even if assigned to Mathematics in Class 8-A).
- **Policy:** `ReportPolicy@generate` checks assignment type. User holds `assignment_type = 'subject_teacher'`. Subject Teachers possess subject mark-entry authority only and are barred from final report generation. Result: **DENIED (403)**.
- **Result:** HTTP 403 Forbidden.

### EXAMPLE R: Subject Teacher attempts final report download
- **Request:** `GET /reports/download/120`.
- **Authenticated Actor:** Subject Teacher.
- **Policy:** `ReportPolicy@download` checks role/scope. Subject Teachers cannot download final report cards. Result: **DENIED (403)**.
- **Result:** HTTP 403 Forbidden (TH-10 mitigated).

### EXAMPLE S: Class Teacher generates final report in Closed Academic Year
- **Academic Year Status:** `closed`.
- **Request:** `POST /reports/generate` for Student in assigned Class 8-A.
- **Pipeline:** `web` $\rightarrow$ `auth` $\rightarrow$ `EnsureUserIsActive` $\rightarrow$ `GenerateReportRequest` $\rightarrow$ `ReportPolicy@generate`.
- **Policy:** Verifies user is active Class Teacher for 8-A. Because report generation is a reporting operation (not a mark or attendance mutation), the closed-year teacher mutation block does not apply. Result: **AUTHORIZED**.
- **Service:** `ReportGenerationService` verifies required marks are complete and compiles the formal final report.
- **Result:** HTTP 302 Redirect with flash: *"Report card generated successfully."*

### EXAMPLE T: Class Teacher downloads report in Closed Academic Year
- **Academic Year Status:** `closed`.
- **Request:** `GET /reports/download/120` (Report in assigned Class 8-A).
- **Pipeline:** `web` $\rightarrow$ `auth` $\rightarrow$ `EnsureUserIsActive` $\rightarrow$ `ReportPolicy@download`.
- **Policy:** Validates classroom scope matches active assignment for the target year/class/section. Result: **AUTHORIZED**.
- **Controller:** Streams private PDF file.
- **Result:** HTTP 200 Binary PDF Stream.

### EXAMPLE U: Office Staff attempts audit log inspection
- **Request:** Office Staff browses to `GET /admin/audit-logs`.
- **Pipeline:** `web` $\rightarrow$ `auth` $\rightarrow$ `EnsureUserIsActive` $\rightarrow$ `CheckRole:Administrator`.
- **Middleware:** User role is `Office Staff`.
- **Result:** HTTP 403 Forbidden (TH-11 mitigated).

### EXAMPLE V: Administrator downloads generated report card PDF
- **Request:** Administrator browses to `GET /reports/download/120`.
- **Pipeline:** `web` $\rightarrow$ `auth` $\rightarrow$ `EnsureUserIsActive`.
- **Policy:** `ReportPolicy@download` authorizes Administrator role unconditionally.
- **Controller:** Verifies file existence on `private_reports` disk. Returns `Storage::disk('private_reports')->download(...)`.
- **Result:** HTTP 200 Binary PDF Stream with headers: `Content-Type: application/pdf`, `Content-Disposition: attachment; filename="Report_Card_John_Doe_Term1_Rev1.pdf"`.

---

## 22. Security Threat Traceability Matrix

This table maps the 17 security threats established in Phase 6.5 directly to their HTTP-layer enforcement mechanisms:

| Threat ID | Threat Vector | HTTP-Layer Architectural Countermeasure | Primary Defense Component |
|---|---|---|---|
| **TH-01** | Unauthorized URL Browsing | Route group middleware stack enforces authentication and coarse role perimeter. | `auth`, `CheckRole` |
| **TH-02** | Insecure Direct Object Reference (IDOR) | Route parameters resolved via model binding are verified against live relational graph scope. | Laravel Policies (`MarkPolicy`, etc.) |
| **TH-03** | Parameter Tampering (Payload) | Form Requests strictly whitelist input fields; server queries PostgreSQL using submitted IDs. | Form Requests + Policies |
| **TH-04** | Role Tampering / Escalation | Role updates restricted strictly to Admin routes; `role_id` unfillable on normal user profiles. | `CheckRole:Administrator`, Form Requests |
| **TH-05** | Deactivated User Lingering Session | `EnsureUserIsActive` intercepts every authenticated request, invalidating sessions immediately. | `EnsureUserIsActive` Middleware |
| **TH-06** | Cross-Site Request Forgery (CSRF) | Universal `VerifyCsrfToken` middleware validates tokens on all POST/PUT/PATCH/DELETE routes. | `VerifyCsrfToken` |
| **TH-07** | Session Fixation | Session ID is regenerated immediately upon successful authentication in `LoginController`. | `$request->session()->regenerate()` |
| **TH-08** | Brute-Force Credential Stuffing | Built-in `RateLimiter` throttles `/login` requests to 5 attempts/minute per IP and username. | `LoginRequest` Throttling |
| **TH-09** | Username Enumeration | Authentication failure returns identical generic message regardless of username existence. | `LoginController@login` |
| **TH-10** | Unauthorized Report Download | `ReportPolicy@download` authorizes Admin and Office Staff, validates live relational classroom scope for Class Teachers, and strictly denies Subject Teachers. | `ReportPolicy@download` |
| **TH-11** | Unauthorized Audit Log Access | Route middleware and `AuditLogPolicy` restrict audit log viewing strictly to Administrator. | `AuditLogPolicy@viewAny` |
| **TH-12** | Mass-Assignment Injection | Controllers pass `$request->validated()` to services; actor IDs are strictly derived from `Auth::id()`. | Form Requests + `$fillable` |
| **TH-13** | Stale Teacher Assignments | Policies query PostgreSQL live for active teacher assignments; permissions never stored in session. | `TeacherAssignmentService` |
| **TH-14** | Closed-Year Mark Tampering | Policies enforce read-only state for teachers when year is closed; Admin/Staff retain audited rights. | `MarkPolicy` |
| **TH-15** | Client-Side Security Bypass | Server ignores client DOM state; 100% of permissions and bounds are re-verified on the server. | Policies + Services |
| **TH-16** | Unauthenticated File Access | Report PDFs stored on private disk outside web root; streamed exclusively via authorized controller. | `GeneratedReportDownloadController` |
| **TH-17** | Password Exposure in Logs | Passwords hidden via `$hidden` on `User` model, `$dontFlash` in session, and redacted in exceptions. | Exception Handler Redaction |

---

## 23. Complete Route Matrix

The following table provides the exhaustive specification for every HTTP route in the application:

| Area | HTTP Method | URI | Route Name | Controller | Action | Middleware | Form Request | Policy / Gate | Target Service | Expected Response | Authorized Roles |
|---|---|---|---|---|---|---|---|---|---|---|---|
| **Auth** | GET | `/login` | `login` | `Auth\LoginController` | `showLoginForm` | `web`, `guest` | None | None | None | View (`auth.login`) | Guest |
| **Auth** | POST | `/login` | `login.submit` | `Auth\LoginController` | `login` | `web`, `guest`, `throttle:5,1` | `LoginRequest` | None | None | Redirect (`dashboard`) | Guest |
| **Auth** | POST | `/logout` | `logout` | `Auth\LogoutController` | `logout` | `web`, `auth` | None | None | None | Redirect (`login`) | Authenticated |
| **Dashboard** | GET | `/dashboard` | `dashboard` | `Dashboard\DashboardController` | `index` | `web`, `auth`, `active` | None | None | None | View (`dashboard.index`) | All Active Staff |
| **Academic** | GET | `/academic-years` | `academic_years.index` | `Academic\AcademicYearController` | `index` | `web`, `auth`, `active` | None | `AcademicYearPolicy@viewAny` | None | View (`academic.years.index`) | Admin, Staff |
| **Academic** | POST | `/academic-years` | `academic_years.store` | `Academic\AcademicYearController` | `store` | `web`, `auth`, `active` | `StoreAcademicYearRequest` | `AcademicYearPolicy@create` | None | Redirect (`academic_years.index`) | Admin |
| **Academic** | PUT | `/academic-years/{academicYear}` | `academic_years.update` | `Academic\AcademicYearController` | `update` | `web`, `auth`, `active` | `UpdateAcademicYearRequest` | `AcademicYearPolicy@update` | None | Redirect (`academic_years.index`) | Admin |
| **Academic** | POST | `/academic-years/{academicYear}/close` | `academic_years.close` | `Academic\AcademicYearController` | `close` | `web`, `auth`, `active` | None | `AcademicYearPolicy@close` | None | Redirect (`academic_years.index`) | Admin |
| **Academic** | POST | `/academic-years/{academicYear}/reopen` | `academic_years.reopen` | `Academic\AcademicYearController` | `reopen` | `web`, `auth`, `active` | None | `AcademicYearPolicy@reopen` | None | Redirect (`academic_years.index`) | Admin |
| **Academic** | GET | `/terms` | `terms.index` | `Academic\TermController` | `index` | `web`, `auth`, `active` | None | `AcademicYearPolicy@viewAny` | None | View (`academic.terms.index`) | Admin, Staff |
| **Academic** | POST | `/terms` | `terms.store` | `Academic\TermController` | `store` | `web`, `auth`, `active` | `StoreTermRequest` | `AcademicYearPolicy@update` | None | Redirect (`terms.index`) | Admin |
| **Academic** | GET | `/classes` | `classes.index` | `Academic\SchoolClassController` | `index` | `web`, `auth`, `active` | None | None | None | View (`academic.classes.index`) | Admin, Staff |
| **Academic** | POST | `/classes` | `classes.store` | `Academic\SchoolClassController` | `store` | `web`, `auth`, `active` | `StoreSchoolClassRequest` | None | None | Redirect (`classes.index`) | Admin |
| **Academic** | GET | `/sections` | `sections.index` | `Academic\SectionController` | `index` | `web`, `auth`, `active` | None | None | None | View (`academic.sections.index`) | Admin, Staff |
| **Academic** | POST | `/sections` | `sections.store` | `Academic\SectionController` | `store` | `web`, `auth`, `active` | `StoreSectionRequest` | None | None | Redirect (`sections.index`) | Admin, Staff |
| **Academic** | GET | `/subjects` | `subjects.index` | `Academic\SubjectController` | `index` | `web`, `auth`, `active` | None | None | None | View (`academic.subjects.index`) | Admin, Staff |
| **Academic** | POST | `/subjects` | `subjects.store` | `Academic\SubjectController` | `store` | `web`, `auth`, `active` | `StoreSubjectRequest` | None | None | Redirect (`subjects.index`) | Admin |
| **Academic** | GET | `/class-subjects` | `class_subjects.index` | `Academic\ClassSubjectController` | `index` | `web`, `auth`, `active` | None | `ClassSubjectPolicy@viewAny` | None | View (`academic.class_subjects.index`) | Admin, Staff |
| **Academic** | POST | `/class-subjects` | `class_subjects.store` | `Academic\ClassSubjectController` | `store` | `web`, `auth`, `active` | `StoreClassSubjectRequest` | `ClassSubjectPolicy@create` | None | Redirect (`class_subjects.index`) | Admin, Staff |
| **Students** | GET | `/students` | `students.index` | `Students\StudentController` | `index` | `web`, `auth`, `active` | None | `StudentPolicy@viewAny` | None | View (`students.index`) | All Staff |
| **Students** | POST | `/students` | `students.store` | `Students\StudentController` | `store` | `web`, `auth`, `active` | `StoreStudentRequest` | `StudentPolicy@create` | `StudentPlacementService` | Redirect (`students.show`) | Admin, Staff |
| **Students** | GET | `/students/{student}` | `students.show` | `Students\StudentController` | `show` | `web`, `auth`, `active` | None | `StudentPolicy@view` | None | View (`students.show`) | All Staff |
| **Students** | PUT | `/students/{student}` | `students.update` | `Students\StudentController` | `update` | `web`, `auth`, `active` | `UpdateStudentRequest` | `StudentPolicy@update` | None | Redirect (`students.show`) | Admin, Staff |
| **Students** | POST | `/students/import` | `students.import.submit` | `Students\StudentController` | `import` | `web`, `auth`, `active` | `StudentImportRequest` | `StudentPolicy@create` | `StudentPlacementService` | View (`students.import_summary`) | Admin, Staff |
| **Students** | POST | `/students/{student}/transfer` | `students.transfer` | `Students\StudentPlacementController` | `transfer` | `web`, `auth`, `active` | `InternalTransferStudentRequest` | `StudentPolicy@transfer` | `StudentPlacementService` | Redirect (`students.show`) | Admin, Staff |
| **Students** | PATCH | `/student-subject-allocations/{studentAcademicRecord}` | `student_subject_allocations.update` | `Students\StudentSubjectAllocationController` | `update` | `web`, `auth`, `active` | `UpdateStudentSubjectAllocationRequest` | `StudentPolicy@updateAllocation` | `StudentSubjectService` | Redirect (`students.show`) | Admin, Staff |
| **Teachers** | GET | `/teachers/assignments` | `teachers.assignments.index` | `Teachers\TeacherAssignmentController` | `index` | `web`, `auth`, `active` | None | `CheckRole:Administrator,Office Staff` | `TeacherAssignmentService` | View (`teachers.assignments.index`) | Admin, Staff |
| **Teachers** | POST | `/teachers/assignments` | `teachers.assignments.store` | `Teachers\TeacherAssignmentController` | `store` | `web`, `auth`, `active` | `StoreTeacherAssignmentRequest` | `CheckRole:Administrator,Office Staff` | `TeacherAssignmentService` | Redirect (`teachers.assignments.index`) | Admin, Staff |
| **Assessments**| GET | `/assessments/types` | `assessments.types.index` | `Assessments\AssessmentTypeController` | `index` | `web`, `auth`, `active` | None | `AssessmentPolicy@viewAny` | None | View (`assessments.types.index`) | Admin, Staff |
| **Assessments**| POST | `/assessments/types` | `assessments.types.store` | `Assessments\AssessmentTypeController` | `store` | `web`, `auth`, `active` | `StoreAssessmentTypeRequest` | `AssessmentPolicy@create` | None | Redirect (`assessments.types.index`) | Admin |
| **Assessments**| GET | `/assessments` | `assessments.index` | `Assessments\AssessmentController` | `index` | `web`, `auth`, `active` | None | `AssessmentPolicy@viewAny` | None | View (`assessments.index`) | All Staff |
| **Assessments**| POST | `/assessments` | `assessments.store` | `Assessments\AssessmentController` | `store` | `web`, `auth`, `active` | `StoreAssessmentRequest` | `AssessmentPolicy@create` | None | Redirect (`assessments.index`) | Admin, Staff |
| **Assessments**| GET | `/assessments/{assessment}/applicability` | `assessments.applicability.index` | `Assessments\AssessmentApplicabilityController` | `index` | `web`, `auth`, `active` | None | `AssessmentPolicy@view` | None | View (`assessments.applicability`) | All Staff |
| **Assessments**| POST | `/assessments/{assessment}/applicability` | `assessments.applicability.store` | `Assessments\AssessmentApplicabilityController` | `store` | `web`, `auth`, `active` | `StoreAssessmentApplicabilityRequest` | `AssessmentPolicy@update` | None | Redirect (`assessments.applicability.index`) | Admin, Staff |
| **Marks** | GET | `/marks` | `marks.index` | `Marks\MarkEntryController` | `index` | `web`, `auth`, `active` | None | `MarkPolicy@viewAny` | `MarkEntryService` | View (`marks.index`) | All Staff (Scoped) |
| **Marks** | POST | `/marks/batch-save` | `marks.batch_save` | `Marks\MarkEntryController` | `batchSave` | `web`, `auth`, `active` | `BatchSaveMarksRequest` | `MarkPolicy@batchSave` | `MarkEntryService` | JSON (200 / 422) | Teachers (Scoped), Admin, Staff |
| **Marks** | POST | `/marks/{mark}/correct` | `marks.correct` | `Marks\MarkCorrectionController` | `correct` | `web`, `auth`, `active` | `CorrectMarkRequest` | `MarkPolicy@correct` | `MarkEntryService` | Redirect / JSON | Admin, Staff |
| **Attendance**| GET | `/attendance` | `attendance.index` | `Attendance\AttendanceController` | `index` | `web`, `auth`, `active` | None | `AttendancePolicy@viewAny` | None | View (`attendance.index`) | Class Teachers, Admin, Staff |
| **Attendance**| POST | `/attendance/batch-save` | `attendance.batch_save` | `Attendance\AttendanceController` | `batchSave` | `web`, `auth`, `active` | `BatchSaveAttendanceRequest` | `AttendancePolicy@batchSave` | None | JSON (200 / 422) | Class Teachers, Admin, Staff |
| **Calculations**| GET | `/calculations/settings` | `calculations.settings.index` | `Calculations\CalculationSettingController` | `index` | `web`, `auth`, `active` | None | `CheckRole:Administrator,Office Staff` | `CalculationService` | View (`calculations.settings`) | Admin, Staff |
| **Calculations**| PUT | `/calculations/settings/{calculationSetting}`| `calculations.settings.update` | `Calculations\CalculationSettingController` | `update` | `web`, `auth`, `active` | `UpdateCalculationSettingRequest` | `CheckRole:Administrator,Office Staff` | `CalculationService` | Redirect (`calculations.settings.index`) | Admin, Staff |
| **Reports** | GET | `/reports/configurations` | `reports.configurations.index` | `Reports\ReportConfigurationController` | `index` | `web`, `auth`, `active` | None | `ReportPolicy@viewAny` | None | View (`reports.configurations`) | Admin, Staff |
| **Reports** | POST | `/reports/configurations` | `reports.configurations.store` | `Reports\ReportConfigurationController` | `store` | `web`, `auth`, `active` | `StoreReportConfigurationRequest` | `ReportPolicy@create` | None | Redirect (`reports.configurations.index`) | Admin, Staff |
| **Reports** | POST | `/reports/selections` | `reports.selections.store` | `Reports\ReportAssessmentSelectionController` | `store` | `web`, `auth`, `active` | `StoreReportAssessmentSelectionRequest` | `ReportPolicy@create` | None | Redirect (`reports.configurations.index`) | Admin, Staff |
| **Reports** | GET | `/reports` | `reports.index` | `Reports\ReportGenerationController` | `index` | `web`, `auth`, `active` | None | `ReportPolicy@viewAny` | None | View (`reports.index`) | All Staff |
| **Reports** | POST | `/reports/preview` | `reports.preview` | `Reports\ReportGenerationController` | `preview` | `web`, `auth`, `active` | `GenerateReportRequest` | `ReportPolicy@preview` | `ReportGenerationService` | Stream / View | Class Teachers, Admin, Staff |
| **Reports** | POST | `/reports/generate` | `reports.generate` | `Reports\ReportGenerationController` | `generate` | `web`, `auth`, `active` | `GenerateReportRequest` | `ReportPolicy@generate` | `ReportGenerationService` | Redirect / JSON | Admin, Staff, Class Teachers (Assigned Class); Subject Teachers DENIED |
| **Reports** | GET | `/reports/download/{generatedReport}` | `reports.download` | `Reports\GeneratedReportDownloadController` | `download` | `web`, `auth`, `active` | None | `ReportPolicy@download` | None | Binary Stream (PDF) | Admin, Staff, Class Teachers (Assigned Class); Subject Teachers DENIED |
| **Admin** | GET | `/admin/users` | `admin.users.index` | `Admin\UserController` | `index` | `web`, `auth`, `active`, `CheckRole:Administrator` | None | `UserPolicy@viewAny` | None | View (`admin.users.index`) | Admin ONLY |
| **Admin** | POST | `/admin/users` | `admin.users.store` | `Admin\UserController` | `store` | `web`, `auth`, `active`, `CheckRole:Administrator` | `StoreUserRequest` | `UserPolicy@create` | None | Redirect (`admin.users.index`) | Admin ONLY |
| **Admin** | PUT | `/admin/users/{user}` | `admin.users.update` | `Admin\UserController` | `update` | `web`, `auth`, `active`, `CheckRole:Administrator` | `UpdateUserRequest` | `UserPolicy@update` | None | Redirect (`admin.users.index`) | Admin ONLY |
| **Admin** | POST | `/admin/users/{user}/deactivate` | `admin.users.deactivate` | `Admin\UserController` | `deactivate` | `web`, `auth`, `active`, `CheckRole:Administrator` | None | `UserPolicy@deactivate` | None | Redirect (`admin.users.index`) | Admin ONLY |
| **Admin** | POST | `/admin/users/{user}/reactivate` | `admin.users.reactivate` | `Admin\UserController` | `reactivate` | `web`, `auth`, `active`, `CheckRole:Administrator` | None | `UserPolicy@reactivate` | None | Redirect (`admin.users.index`) | Admin ONLY |
| **Admin** | POST | `/admin/users/{user}/reset-password` | `admin.users.reset_password` | `Admin\UserController` | `resetPassword` | `web`, `auth`, `active`, `CheckRole:Administrator` | `ResetUserPasswordRequest` | `UserPolicy@resetPassword` | None | Redirect (`admin.users.index`) | Admin ONLY |
| **Admin** | GET | `/admin/audit-logs` | `admin.audit_logs.index` | `Admin\AuditLogController` | `index` | `web`, `auth`, `active`, `CheckRole:Administrator` | None | `AuditLogPolicy@viewAny` | None | View (`admin.audit_logs.index`) | Admin ONLY |
| **Admin** | GET | `/admin/school-settings` | `admin.school_settings.edit` | `Settings\SchoolSettingController` | `edit` | `web`, `auth`, `active` | None | `SchoolSettingPolicy@view` | None | View (`settings.school.edit`) | Admin, Staff |
| **Admin** | PUT | `/admin/school-settings` | `admin.school_settings.update` | `Settings\SchoolSettingController` | `update` | `web`, `auth`, `active`, `CheckRole:Administrator` | `UpdateSchoolSettingRequest` | `SchoolSettingPolicy@update` | None | Redirect (`admin.school_settings.edit`) | Admin ONLY |

---

## 24. Controller Contracts & Implementation Specifications

All controllers extend the base abstract controller `App\Http\Controllers\Controller`. Controllers are strictly orchestration boundaries.

### 24.1 Key Controller Contracts

#### `App\Http\Controllers\Marks\MarkEntryController`
- `index(Request $request)`:
  - Resolves active filters: `academic_year_id`, `class_id`, `section_id`, `subject_id`, `assessment_id`.
  - Authorizes: `Gate::authorize('viewAny', [Mark::class, $academicYear, $schoolClass, $section, $subject])`.
  - Invokes: `MarkEntryService::getMarkGridData(...)`.
  - Returns: `resources/views/marks/index.blade.php`.
- `batchSave(BatchSaveMarksRequest $request)`:
  - Authorizes: `Gate::authorize('batchSave', [Mark::class, $request->validated()])`.
  - Invokes: `MarkEntryService::saveBatch($request->validated(), Auth::id())`.
  - Returns: JSON response `{"success": true, "message": "Marks saved successfully."}` with HTTP 200.

#### `App\Http\Controllers\Reports\ReportGenerationController`
- `generate(GenerateReportRequest $request)`:
  - Form Request validates request shape (e.g. `student_academic_record_id`, `report_type`, `term_id`).
  - Resolves target `StudentAcademicRecord`.
  - Authorizes: `Gate::authorize('generate', [GeneratedReport::class, $studentAcademicRecord])` (or passes `$studentAcademicRecord` to `ReportPolicy@generate`):
    - `Administrator` $\rightarrow$ Allowed.
    - `Office Staff` $\rightarrow$ Allowed.
    - `Class Teacher` $\rightarrow$ Allowed **only** when the target `StudentAcademicRecord` belongs to the teacher's active assigned classroom scope (`academic_year_id`, `class_id`, `section_id`).
    - `Subject Teacher` $\rightarrow$ Denied (HTTP 403 Forbidden).
  - Invokes: `ReportGenerationService::generateReportCard($request->validated(), Auth::id())`.
  - Actor ID is strictly assigned server-side from `Auth::id()`; client cannot supply `generated_by_user_id` or `revision_number`.
  - Service performs report completion verification and increments revision number.
  - Returns: Redirect to `reports.index` with flash message: *"Report card generated successfully."* (or structured JSON for async requests).

#### `App\Http\Controllers\Reports\GeneratedReportDownloadController`
- `download(GeneratedReport $generatedReport)`:
  - Route model binding resolves `{generatedReport}`.
  - Authorizes: `Gate::authorize('download', $generatedReport)` invoking `ReportPolicy@download`:
    - `Administrator` $\rightarrow$ Allowed.
    - `Office Staff` $\rightarrow$ Allowed.
    - `Class Teacher` $\rightarrow$ Allowed **only** when the `GeneratedReport`'s associated `StudentAcademicRecord` matches the teacher's active assigned classroom scope (`academic_year_id`, `class_id`, `section_id`).
    - `Subject Teacher` $\rightarrow$ Denied (HTTP 403 Forbidden).
  - Verifies file exists on disk `private_reports`.
  - Returns: Binary file download stream via `Storage::disk('private_reports')->download(...)` with sanitized filename.
  - Generates zero public URLs; controller remains a thin orchestration boundary.

#### `App\Http\Controllers\Students\StudentPlacementController`
- `transfer(InternalTransferStudentRequest $request, Student $student)`:
  - Authorizes: `Gate::authorize('transfer', $student)`.
  - Invokes: `StudentPlacementService::executeInternalTransfer($student, $request->validated(), Auth::id())`.
  - Returns: Redirect to `students.show` with flash message: *"Student transferred successfully."*

---

## 25. Decision Ledger Additions (Phase 6.6)

In accordance with Section 72, the following four formal architectural decisions are recorded for Phase 6.6 in `docs/decisions.md`. Prior decisions DEC-001 through DEC-040 are strictly preserved.

### Summary of New Decisions:
- **DEC-041:** Resource-Oriented Web Route Hierarchy & Action-Oriented Workflow Endpoints
- **DEC-042:** Strict Three-Tier Validation Boundary (Form Request vs. Policy vs. Service)
- **DEC-043:** Atomic Batch Mark Save Protocol with Universal Scope Verification
- **DEC-044:** Controller Orchestration Contract & Prohibition of Business Logic in Web Transport

---

## 26. Prohibited Approaches Checklist

The following technical patterns are explicitly prohibited across the entire HTTP layer:

- ❌ **NO `request->all()`:** Mass assignment from raw request data is banned. Controllers must pass `$request->validated()`.
- ❌ **NO Client-Controlled Actor Fields:** Never accept `user_id`, `entered_by_user_id`, `updated_by_user_id`, or `generated_by_user_id` from client payloads. Actors are derived strictly from `Auth::id()`.
- ❌ **NO Client-Controlled Revision Numbers:** The client cannot supply `revision_number`. Revisions are determined server-side via `MAX(revision_number) + 1`.
- ❌ **NO Role-Only Teacher Authorization:** Never authorize mark or attendance operations based solely on `user.role == 'Teacher'`. Authorization requires matching active rows in `teacher_assignments`.
- ❌ **NO Role-Only Class Teacher Report Authorization:** Never authorize a Class Teacher to generate or download report cards based solely on role name. Authorization must dynamically verify the `StudentAcademicRecord` associated with the report against the teacher's active `teacher_assignments` record (`academic_year_id`, `class_id`, `section_id`).
- ❌ **NO Client-Submitted Authorization Parameters:** Never trust client-submitted `class_id`, `section_id`, `teacher_assignment_id`, or `generated_by_user_id` in report generation/download requests.
- ❌ **NO Current Placement Overwrite for Historical Reports:** Never authorize a Class Teacher against the student's CURRENT classroom placement if the report is tied to a historical `StudentAcademicRecord`. Authorization must evaluate the specific placement record bound to the report.
- ❌ **NO PDF Streaming Prior to Policy Check:** Never stream a report PDF before `ReportPolicy@download` succeeds.
- ❌ **NO Session-Cached Teacher Permissions:** Never cache authorized class or subject IDs in session. Permissions are resolved dynamically from PostgreSQL on every request.
- ❌ **NO Blanket Closed-Year Middleware:** Never implement route-level middleware that blindly rejects mutations when an academic year is closed, as this prevents authorized Admin/Staff mark corrections and blocks authorized Class Teacher report generation/download.
- ❌ **NO Public Report Card URLs:** Never store PDFs in `public/` or create routes pointing to public storage. All downloads are mediated through `GeneratedReportDownloadController`.
- ❌ **NO API-First / Token Architecture:** Do not introduce Sanctum, Passport, JWT, OAuth, or `/api/` route files.
- ❌ **NO Repository Layer:** Do not create repository classes. Controllers interact directly with Policies, Form Requests, and canonical Application Services.
- ❌ **NO Schema Alterations:** Do not add permission tables, tenant IDs, soft-delete columns (`deleted_at`), or workflow state tables. The 23-table relational model remains 100% fixed.
- ❌ **NO Batch PDF Generation:** Generating PDFs for entire classrooms in a single batch is out of scope. Report generation is executed strictly per-student.
- ❌ **NO Hard Deletions of Historical Records:** Deleting historical students, marks, audit logs, or generated reports is strictly prohibited.
- ❌ **NO Coercion of Blank or Absent to Zero:** Never convert `blank` or `absent` into numeric `0.00` during request parsing.
- ❌ **NO Floating-Point Business Calculations:** Calculations are performed in `CalculationService` with exact decimal arithmetic; controllers never perform floating-point math.

---

## 27. Deferred Items (Phases 6.7 through 6.10)

The following areas are intentionally deferred to subsequent architectural phases:

- **Phase 6.7 (Blade Component & UI Integration Architecture):**
  - Atomic Blade component tree (`<x-layout>`, `<x-mark-grid>`, `<x-alert>`).
  - Mark grid DOM construction, keyboard navigation (Arrow keys / Enter), and Vanilla JS state handling.
  - CSP nonce generation implementation in Blade layouts.
  - Tailored CSS design tokens and responsive grid layouts.
- **Phase 6.8 (PDF Generation & File Storage Blueprint):**
  - Selection of PDF compilation engine (`dompdf` vs. `browsershot`).
  - Report card HTML/CSS template compilation and print stylesheets.
  - Filesystem atomicity (generating to temporary paths before committing to `storage/app/private/reports`).
- **Phase 6.9 (Application Testing & Quality Gate Strategy):**
  - Automated PHPUnit Feature tests simulating HTTP requests for all controller endpoints.
  - Role and scope authorization test suite (testing Scenarios A through V).
  - Validation test suites and SQL regression tests.
- **Phase 6.10 (Environment Configuration & Deployment Readiness):**
  - Production session driver configuration (PostgreSQL table vs. Redis).
  - Production database connection pooling sizing (PgBouncer).
  - TLS termination, cookie domain parameters, and production deployment scripts.

---

## 28. Phase 6.6 Completion Verification

This document completes Phase 6.6. It provides a comprehensive, rigorous, and unambiguous blueprint for the HTTP layer, routes, Form Requests, and controller contracts of the School Examination Marks and Report Card Management System. Subsequent implementation phases must adhere strictly to the contracts and specifications defined herein.
