# PHASE 6.3 — LARAVEL PROJECT / FOLDER STRUCTURE
## Project Structure & Architectural Boundaries Blueprint

**Project:** School Examination Marks and Report Card Management System  
**Document Version:** 1.0  
**Phase:** 6.3 — Laravel Project / Folder Structure  
**Status:** Approved & Authoritative Architectural Specification  

---

## 1. Purpose

This document establishes the complete physical directory layout, logical module organization, and architectural boundary rules for the Laravel application representing the **School Examination Marks and Report Card Management System**.

Phase 6.3 bridges the technology baseline (Phase 6.1) and the 23-model domain architecture (Phase 6.2) into a cohesive, concrete project directory blueprint. Its primary purpose is to define **where every responsibility lives** across the backend, frontend, storage, testing, and operational layers prior to application implementation.

This blueprint guarantees:
- A clean, maintainable separation of concerns adhering to the layered request architecture.
- Full structural representation for all 23 Eloquent models and their domain boundaries.
- Dedicated locations for business workflows (Application Services), authorization (Policies), request validation (Form Requests), and discrete type casting (Enums, Casts, Value Objects).
- A server-rendered frontend architecture combining Blade views, reusable UI components, tailored Vanilla CSS, and targeted Vanilla JavaScript micro-interactions bundled with Vite.
- Secure, segmented storage for private, immutable PDF report revisions and public institutional assets.
- Complete avoidance of anti-patterns, including empty repository layers, SPA frameworks, multi-tenant abstractions, and dumping-ground helper directories.

---

## 2. Relationship to Phase 6.1 and Phase 6.2

The Laravel architecture progresses through disciplined, sequential technical specifications:

```
Phase 6.1: Technology Baseline & Architecture Principles (Approved)
  - PHP 8.4+, Laravel 13, PostgreSQL 18.4, Blade, Vanilla JS, Plain CSS, Vite.
  - Thin controllers, application services, session auth, immutable audit logs.
      ↓
Phase 6.2: Domain Model & Eloquent Architecture (Approved)
  - 23 Eloquent models mapped to closed 23-table PostgreSQL schema.
  - Relational matrix (85 relations), casting rules, mutability tiers, historical placement.
      ↓
Phase 6.3: Laravel Project / Folder Structure (Current Step)
  - Complete physical directory tree, namespace conventions, and layer boundaries.
  - Structural mapping for Models, Services, Policies, Requests, Views, JS, and CSS.
      ↓
Phase 6.4: PostgreSQL + Eloquent Integration Architecture & Decision Ledger
  - PostgreSQL connection, types, schema-to-model integration, transaction boundaries.
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

Phase 6.3 establishes the structural foundation required for Phases 6.4 through 6.9.

---

## 3. Authoritative Sources

All structural decisions in this blueprint strictly derive from the project's source-of-truth hierarchy:

1. **BRD V1.3** (`BRD/School_Examination_Marksheet_Requirements_v1_3.docx`)
2. **Finalized Business Rules & Handover Decisions**
3. **Approved 23-Table Database Relationship Design** (`DB design/DB-table-definitons.txt`)
4. **Approved ERD** (`DB design/mermaid-diagram.png`)
5. **Validated PostgreSQL 18 Implementation & Documentation** (`database/Postgres/schema.sql`, `seed.sql`, `validation/run_all.sql`, `docs/POSTGRES_MIGRATION_WALKTHROUGH.md`)
6. **Technology Baseline Document** (`docs/architecture/PHASE_6_1_TECHNOLOGY_BASELINE.md`)
7. **Domain & Eloquent Architecture Document** (`docs/architecture/PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md`)

---

## 4. Laravel Project Architecture Overview

The system operates as a classic, high-performance, server-rendered web application. The physical folder structure mirrors the runtime request execution pipeline:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           REQUEST EXECUTION PIPELINE                        │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
                                       ▼
                       Browser Request (HTTP GET / POST)
                                       │
                                       ▼
                     [routes/web.php] (Route Resolution)
                                       │
                                       ▼
                  [app/Http/Middleware/] (Session, Active User, Role)
                                       │
                                       ▼
                  [app/Http/Requests/] (Input Validation)
                                       │
                                       ▼
               [app/Http/Controllers/] (Thin Orchestration)
                                       │
                                       ▼
                 [app/Policies/] (Contextual Authorization)
                                       │
                                       ▼
                  [app/Services/] (Business Workflows & DB Transactions)
                                       │
                                       ▼
                [app/Models/] (Eloquent Persistence & Casts)
                                       │
                                       ▼
               PostgreSQL 18 Database (`school_report_card`)
                                       │
                                       ▼
                  Prepared ViewModel / Response Data
                                       │
                                       ▼
                [resources/views/] (Blade Layouts & Components)
                                       │
                                       ▼
                HTML Response Rendered to Browser
```

---

## 5. Root Project Directory

The application root directory structure is clean, standard, and fully aligned with Laravel conventions:

```
school-report-card/
├── app/                  # Application core code (Models, Services, Http, etc.)
├── bootstrap/            # Framework bootstrapper and application configuration
├── config/               # Application configuration files
├── database/             # Migrations, seeders, and factories (coexisting with PG baseline)
├── docs/                 # Architectural specifications, BRD, and database docs
│   └── architecture/     # Phase 6 architecture blueprint documents
├── public/               # Web server document root (index.php, compiled assets)
├── resources/            # Server-rendered views (Blade), raw CSS, and raw JS
├── routes/               # Web and console route definitions
├── storage/              # Private reports, framework cache, and logs
├── tests/                # Automated feature, unit, and integration tests
├── .env.example          # Environment configuration blueprint
├── artisan               # Laravel command-line interface
├── composer.json         # PHP dependency declarations
├── package.json          # Frontend build dependency declarations (Node/Vite)
├── phpunit.xml           # Test runner configuration
└── vite.config.js        # Vite asset compilation configuration
```

---

## 6. `app/` Directory Architecture

The `app/` directory encapsulates the complete domain and application layers. Every subdirectory has a single, unambiguous architectural responsibility:

```
app/
├── Casts/                # Custom Eloquent attribute casters (e.g. MarkValueCast)
├── Console/              # Artisan commands for academic year closing / maintenance
├── Contracts/            # Abstract interfaces for interchangeable engines (PDF generation)
├── Enums/                # PHP 8.4 Backed Enums matching PostgreSQL ENUM types
├── Exceptions/           # Domain-specific business exceptions
├── Http/                 # Web interface layer
│   ├── Controllers/      # Thin controllers delegating to services
│   ├── Middleware/       # Request filtering (Active user, session auth, role checks)
│   └── Requests/         # Form Requests containing HTTP validation rules
├── Models/               # Exactly 23 Eloquent persistence models
├── Policies/             # Server-side authorization policies (Gates/Policies)
├── Providers/            # Service providers configuring application bindings
├── Services/             # Domain workflows, calculation engines, and transactions
├── ValueObjects/         # Immutable value objects encapsulating domain concepts
└── Support/              # Highly restricted, non-dumping-ground domain helpers
```

---

## 7. Models Structure (`app/Models/`)

In strict accordance with Phase 6.2, exactly 23 Eloquent models reside directly under `app/Models/`. They are not divided into arbitrary subfolders because:
- The 23 models form an interconnected academic graph where cross-subdomain relationships are universal.
- Flat organization preserves standard Laravel conventions (`App\Models\ModelName`) without deep, verbose namespaces.
- All models represent the validated 23-table PostgreSQL schema without duplication.

```
app/Models/
├── AcademicYear.php              # Table: academic_years
├── Assessment.php                # Table: assessments
├── AssessmentApplicability.php   # Table: assessment_applicability
├── AssessmentType.php            # Table: assessment_types
├── Attendance.php                # Table: attendance
├── AuditLog.php                  # Table: audit_logs (append-only)
├── CalculationSetting.php        # Table: calculation_settings
├── ClassSubject.php              # Table: class_subjects (holds subject_name_snapshot)
├── GeneratedReport.php           # Table: generated_reports (append-only revisions)
├── Mark.php                      # Table: marks (blank, numeric, absent)
├── ReportAssessmentSelection.php # Table: report_assessment_selections
├── ReportConfiguration.php       # Table: report_configurations
├── Role.php                      # Table: roles
├── SchoolClass.php               # Table: classes (named SchoolClass to avoid PHP keyword)
├── SchoolSetting.php             # Table: school_settings (singleton)
├── Section.php                   # Table: sections
├── Student.php                   # Table: students
├── StudentAcademicRecord.php     # Table: student_academic_records (historical placement)
├── StudentSubjectAllocation.php  # Table: student_subject_allocations (electives)
├── Subject.php                   # Table: subjects
├── TeacherAssignment.php         # Table: teacher_assignments (multi-assignment scope)
├── Term.php                      # Table: terms (dynamic terms)
└── User.php                      # Table: users (accounts, credentials, active status)
```

---

## 8. HTTP Layer Structure (`app/Http/`)

The HTTP layer handles the web transport mechanism: receiving requests, applying middleware, validating payload structures, invoking thin controllers, and returning Blade views or redirects.

```
app/Http/
├── Controllers/
├── Middleware/
└── Requests/
```

Controllers never query models directly for complex operations, never contain transaction blocks, and never encode authorization or calculation rules.

---

## 9. Controllers Structure (`app/Http/Controllers/`)

Controllers are organized into feature-driven subdirectories matching the functional domains of the application. All controllers extend a shared `App\Http\Controllers\Controller`.

```
app/Http/Controllers/
├── Controller.php                        # Base abstract controller
├── Auth/
│   ├── LoginController.php               # Session authentication
│   └── LogoutController.php              # Session termination
├── Dashboard/
│   └── DashboardController.php           # Role-based dashboard landing
├── Academic/
│   ├── AcademicYearController.php        # Year lifecycle (open/closed)
│   ├── TermController.php                # Dynamic terms management
│   ├── SchoolClassController.php         # Class management
│   ├── SectionController.php             # Section management
│   ├── SubjectController.php             # Subject catalog management
│   └── ClassSubjectController.php        # Curriculum & snapshot configuration
├── Students/
│   ├── StudentController.php             # Student directory
│   ├── StudentPlacementController.php    # Academic placement & transfer actions
│   └── StudentSubjectAllocationController.php # Elective allocations
├── Teachers/
│   └── TeacherAssignmentController.php   # Scope & multi-assignment management
├── Assessments/
│   ├── AssessmentTypeController.php      # Type master management
│   ├── AssessmentController.php          # Assessment milestone instances
│   └── AssessmentApplicabilityController.php # Per-subject max marks
├── Marks/
│   ├── MarkEntryController.php           # Grid view and batch entry submission
│   └── MarkCorrectionController.php      # Admin/Staff correction workflows
├── Attendance/
│   └── AttendanceController.php          # Term-level attendance entry
├── Calculations/
│   └── CalculationSettingController.php  # Class-level formula configuration
├── Reports/
│   ├── ReportConfigurationController.php # Layout and selection management
│   ├── ReportGenerationController.php    # PDF generation trigger & progress
│   └── GeneratedReportDownloadController.php # Secure PDF revision streaming
├── Settings/
│   └── SchoolSettingController.php       # Singleton school branding/pass mark
└── Admin/
    ├── UserController.php                # Staff accounts & activation toggling
    └── AuditLogController.php            # Read-only audit log inspection
```

---

## 10. Form Requests Structure (`app/Http/Requests/`)

Form Requests isolate HTTP input validation, request parsing, and authorization gating from controllers. They ensure that controllers only receive sanitized, valid payloads.

```
app/Http/Requests/
├── Auth/
│   └── LoginRequest.php
├── Academic/
│   ├── StoreAcademicYearRequest.php
│   ├── UpdateAcademicYearRequest.php
│   ├── StoreTermRequest.php
│   ├── StoreSchoolClassRequest.php
│   ├── StoreSectionRequest.php
│   ├── StoreSubjectRequest.php
│   └── StoreClassSubjectRequest.php
├── Students/
│   ├── StoreStudentRequest.php
│   ├── InternalTransferStudentRequest.php
│   └── UpdateStudentSubjectAllocationRequest.php
├── Teachers/
│   ├── StoreTeacherAssignmentRequest.php
│   └── UpdateTeacherAssignmentRequest.php
├── Assessments/
│   ├── StoreAssessmentTypeRequest.php
│   ├── StoreAssessmentRequest.php
│   └── StoreAssessmentApplicabilityRequest.php
├── Marks/
│   ├── BatchSaveMarksRequest.php         # Validates numeric/blank/absent bounds
│   └── CorrectMarkRequest.php           # Validates actor audit reason & value
├── Attendance/
│   └── BatchSaveAttendanceRequest.php    # Validates attended <= total
├── Calculations/
│   └── UpdateCalculationSettingRequest.php
├── Reports/
│   ├── StoreReportConfigurationRequest.php
│   └── GenerateReportRequest.php         # Single-student report generation request (batch generation out of scope)
├── Settings/
│   └── UpdateSchoolSettingRequest.php
└── Admin/
    ├── StoreUserRequest.php
    └── UpdateUserRequest.php
```

---

## 11. Middleware Structure (`app/Http/Middleware/`)

Middleware enforces cross-cutting request boundaries before controllers are executed:

```
app/Http/Middleware/
├── EnsureUserIsActive.php        # Checks user.is_active; invalidates session if false
├── CheckRole.php                 # Coarse route guarding by system role name
└── PreventRequestsWhenYearClosed.php # Guards teacher mutations when year is closed (Admin/Staff retain correction access)
```

### Architectural Boundary: Middleware vs Policies
- **Middleware:** Operates globally or per route group. Answers coarse questions: *"Is the user logged in? Is the account active? Does the user have the 'Teacher' role?"*
- **Policies:** Operates on specific domain models and runtime parameters. Answers granular, contextual questions: *"Is this specific teacher authorized to enter marks for Class 9, Section B, in Mathematics for the Mid-Term Exam?"*

---

## 12. Policies & Authorization Structure (`app/Policies/`)

Laravel Policies implement fine-grained, contextual authorization rules:

```
app/Policies/
├── AcademicYearPolicy.php        # Controls lifecycle transition & reopening
├── AssessmentPolicy.php          # Controls assessment setup & applicability
├── AttendancePolicy.php          # Controls attendance submission authority
├── AuditLogPolicy.php            # Restricts audit viewing strictly to Administrator
├── ClassSubjectPolicy.php        # Restricts curriculum mutations
├── MarkPolicy.php                # Validates teacher scope, open year, & correction
├── ReportPolicy.php              # Controls generation & revision downloads
├── SchoolSettingPolicy.php       # Restricts institutional settings updates
├── StudentPolicy.php             # Governs enrollment, transfers, and electives
└── UserPolicy.php                # Governs user account creation and deactivation
```

---

## 13. Services Structure (`app/Services/`)

Application Services encapsulate complex business operations, multi-table coordination, database transactions, and audit trail writing.

### 13.1 Service Inventory & Canonical Naming
To eliminate ambiguity identified between Phase 6.2 references, the canonical service names are formally established below:

| Canonical Service Name | Domain Boundary & Primary Responsibilities |
|---|---|
| **`MarkEntryService`** | Validates mark bounds per applicability, ensures academic year is open, executes batch saves inside database transactions, logs audit changes. |
| **`CalculationService`** | Executes Method 1 (Average Percentage) and Method 2 (Combined Marks), applies the Term Exam participation rule, prevents division by zero, rounds to 2 decimal places. |
| **`StudentPlacementService`** | Manages academic placements and transfers. Closes prior placement records (`status = 'internal_transfer'`, sets `effective_to`), opens new placement records, and leaves historical marks untouched on the prior placement. *(Canonical name for what was occasionally referenced as StudentEnrollmentService).* |
| **`StudentSubjectService`** | Handles student subject allocations. Enforces the **elective lockout rule**: strictly blocks elective modifications if marks already exist for that student in that subject. |
| **`ReportGenerationService`** | Coordinates report compilation, validates mark completeness, triggers PDF generation via `ReportGeneratorContract`, increments `revision_number`, stores PDF to private storage, records `generated_reports` row. |
| **`TeacherAssignmentService`** | Resolves complex, multi-assignment teacher authorization scopes across academic years, classes, sections, and subjects. |
| **`AuditLogService`** | Centralized, append-only service responsible for writing structured `before_data` and `after_data` JSONB entries to `audit_logs`. |

```
app/Services/
├── AuditLogService.php
├── CalculationService.php
├── MarkEntryService.php
├── ReportGenerationService.php
├── StudentPlacementService.php
├── StudentSubjectService.php
└── TeacherAssignmentService.php
```

---

## 14. Enums Structure (`app/Enums/`)

PHP 8.4 Backed Enums map 1:1 with the validated PostgreSQL ENUM types defined in `schema.sql`:

```
app/Enums/
├── AcademicYearStatus.php        # 'open', 'closed'
├── AssessmentStatus.php          # 'active', 'inactive'
├── CalculationMethod.php         # 'average_percentage', 'combined_marks'
├── MarkResultStatus.php          # 'blank', 'numeric', 'absent'
├── ReportType.php                # 'exam', 'term', 'final'
├── StudentPlacementStatus.php    # 'active', 'internal_transfer', 'withdrawn', 'transferred_out'
├── SubjectCategory.php           # 'main', 'elective'
└── TeacherAssignmentType.php     # 'subject_teacher', 'class_teacher'
```

All enums implement string backing (e.g. `enum MarkResultStatus: string`) to allow seamless Eloquent model casting.

---

## 15. Casts & Value Objects Structure (`app/Casts/`, `app/ValueObjects/`)

To preserve the critical business rule that **`blank != 0.00`**, dedicated casting and value object components are established:

```
app/Casts/
└── MarkValueCast.php             # Custom cast preserving explicit SQL NULL vs 0.00

app/ValueObjects/
└── MarkResult.php                # Immutable value object combining value & status
```

### Purpose of `MarkValueCast` & `MarkResult`
- Standard Eloquent casting to `decimal:2` or float can inadvertently cast `NULL` to `0.00` in PHP memory.
- `MarkValueCast` ensures that when `result_status = 'blank'` or `'absent'`, `mark_value` remains strictly `null`.
- `MarkResult` encapsulates the triplet: `$status` (`MarkResultStatus`), `$numericValue` (`?float`), and `$displayString` (`""`, `"0.00"`, `"A"`), guaranteeing consistent presentation across Blade templates and PDF generators.

---

## 16. Contracts / Interfaces Structure (`app/Contracts/`)

In alignment with the minimal dependency principle, interfaces are **not** created for everyday models or services. Only one legitimate contract is established:

```
app/Contracts/
└── ReportGeneratorContract.php   # Interface for swappable PDF generation engine
```

### Rationale for `ReportGeneratorContract`
Phase 6.1 intentionally deferred the final choice of PDF rendering technology (e.g. `dompdf` vs headless Chrome / `browsershot`). `ReportGeneratorContract` allows `ReportGenerationService` to compile and render PDF documents without binding the application domain directly to a specific third-party library.

---

## 17. Exceptions Structure (`app/Exceptions/`)

Domain-specific exceptions represent business rule violations and prevent corrupt state transitions:

```
app/Exceptions/
├── AcademicYearClosedException.php        # Attempted edit on closed academic year
├── ElectiveModificationLockedException.php # Attempted elective change after marks exist
├── IncompleteStudentReportException.php   # Attempted report generation for incomplete student
├── InvalidTransferPlacementException.php  # Invalid roll number or transfer configuration
├── MarkExceedsMaximumException.php        # Mark entered higher than assessment applicability
└── UnauthorizedTeacherScopeException.php  # Teacher accessed unauthorized class/subject
```

These exceptions are caught by the HTTP layer or Laravel's exception handler and converted into user-friendly flash error messages or HTTP 403/422 responses.

---

## 18. Providers Structure (`app/Providers/`)

Service providers configure Laravel's service container and register application services:

```
app/Providers/
├── AppServiceProvider.php        # Core service configurations and strict Eloquent mode
└── AuthServiceProvider.php       # Registers Policies and custom Gate definitions
```

---

## 19. Blade / `resources/views/` Structure

The frontend is completely server-rendered using Laravel Blade. Views are organized by application domain into dedicated folders:

```
resources/views/
├── layouts/
│   ├── app.blade.php             # Main authenticated layout (sidebar, topbar, alerts)
│   ├── auth.blade.php            # Guest layout for login screen
│   └── print.blade.php           # Clean print/PDF layout for report cards
├── components/                   # Reusable Blade UI components (see Section 20)
├── auth/
│   └── login.blade.php           # Login form
├── dashboard/
│   └── index.blade.php           # Role-tailored dashboard landing
├── academic/
│   ├── years/index.blade.php
│   ├── terms/index.blade.php
│   ├── classes/index.blade.php
│   ├── sections/index.blade.php
│   ├── subjects/index.blade.php
│   └── class-subjects/index.blade.php
├── students/
│   ├── index.blade.php           # Student directory
│   ├── show.blade.php            # Academic history & profile
│   ├── transfer.blade.php        # Internal section transfer form
│   └── electives.blade.php       # Student elective allocation editor
├── teachers/
│   └── assignments/index.blade.php # Teacher assignment matrix
├── assessments/
│   ├── types/index.blade.php
│   ├── index.blade.php           # Assessment milestones
│   └── applicability/index.blade.php # Per-subject max marks configuration
├── marks/
│   ├── entry.blade.php           # Primary spreadsheet-like mark entry grid
│   └── review.blade.php          # Class mark sheet review & verification
├── attendance/
│   └── entry.blade.php           # Term attendance batch entry grid
├── calculations/
│   └── settings.blade.php        # Calculation formula selector
├── reports/
│   ├── configurations/index.blade.php # Report schema builder
│   ├── generate.blade.php        # Student report card generation & revision interface
│   ├── preview.blade.php         # Browser-rendered report card preview
│   └── templates/
│       ├── exam-report.blade.php # Single assessment report card
│       ├── term-report.blade.php # Term exam report card
│       └── final-report.blade.php # Annual comprehensive report card
├── settings/
│   └── index.blade.php           # School details, logo upload, pass mark
└── admin/
    ├── users/index.blade.php     # Staff account management
    └── audit-logs/index.blade.php # Immutable activity history viewer
```

---

## 20. Blade Component Architecture (`resources/views/components/`)

Reusable UI elements are organized into component categories to maintain visual and functional consistency:

```
resources/views/components/
├── layouts/
│   ├── sidebar.blade.php         # Navigation sidebar with role-aware links
│   └── topbar.blade.php          # User profile display & logout button
├── ui/
│   ├── alert.blade.php           # Flash notification banners (success, error, warning)
│   ├── badge.blade.php           # Status pills (Active, Closed, Absent, Transfer)
│   ├── button.blade.php          # Primary, secondary, danger, and icon buttons
│   ├── card.blade.php            # Surface container with header & body
│   ├── modal.blade.php           # Accessible dialog window
│   └── table.blade.php           # Standardized data table wrapper
├── forms/
│   ├── input.blade.php           # Text/number input with validation error display
│   ├── select.blade.php          # Standard select dropdown
│   └── checkbox.blade.php        # Checkbox toggle
└── marks/
    ├── mark-cell.blade.php       # Grid cell input handling numeric, blank, 'A'
    └── mark-status-legend.blade.php # Visual guide for blank vs zero vs absent
```

---

## 21. JavaScript / `resources/js/` Structure

JavaScript is implemented strictly as **Vanilla JS modules**. There is no client-side framework (No React, Vue, or Inertia). JavaScript serves exclusively for micro-interactions, spreadsheet navigation, and client-side usability:

```
resources/js/
├── app.js                        # Primary Vite entry point (loads shared modules)
├── modules/
│   ├── mark-entry-grid.js        # Keyboard navigation (arrows/enter), dirty-state tracking
│   ├── attendance-grid.js        # Days attended <= working days client validation
│   ├── cascading-dropdowns.js    # Class -> Section -> Subject dynamic filtering
│   └── modal-controller.js       # Accessible modal show/hide handlers
└── utilities/
    └── dom.js                    # Minimal DOM helper utilities (querySelector, events)
```

---

## 22. CSS / `resources/css/` Structure

Styling is implemented in tailored, plain CSS utilizing CSS custom properties (design tokens). No Tailwind or Bootstrap frameworks are used:

```
resources/css/
├── app.css                       # Primary Vite entry point (imports partials)
├── tokens.css                    # Color palette, spacing, typography, shadow tokens
├── base.css                      # Modern CSS reset and typography foundation
├── layout.css                    # App shell, responsive sidebar, main container
├── components.css                # Cards, buttons, tables, badges, modals, forms
└── modules/
    ├── mark-grid.css             # Dense spreadsheet styles, cell focus rings, dirty state
    └── report-print.css          # Print stylesheets, page break rules, PDF layout
```

---

## 23. Vite Asset Structure

Vite is utilized strictly as an **offline build-time asset compiler**. It is never an application server:

```javascript
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
});
```

- **Development:** Vite runs locally (`npm run dev`) providing instant Hot Module Replacement (HMR) for CSS and Blade files.
- **Production:** Vite compiles and hashes assets into `public/build/` (`npm run build`).

---

## 24. `routes/` Structure

The application is a standard server-rendered web application:

```
routes/
├── web.php                       # All application web routes with middleware groupings
└── console.php                   # Artisan console command definitions
```

### Route Design Principles
- **No `api.php`:** The application is not a headless API or SPA. Form submissions use standard session-backed POST/PUT/DELETE requests protected by CSRF tokens.
- **Grouped & Named Routes:** Routes are grouped logically by feature, guarded by `auth` and `EnsureUserIsActive` middleware, and given standardized dot-notation names (e.g. `marks.entry`, `reports.generate`).

---

## 25. `database/` Structure

The physical database structure accommodates the existing, validated PostgreSQL 18 schema without conflicts:

```
database/
├── factories/                    # Model factories for testing
├── migrations/                   # Laravel migrations mirroring the 23-table schema
└── seeders/                      # Database seeders (roles, default school settings)
```

### Coexistence with the Validated PostgreSQL Baseline
1. The validated SQL files in `database/Postgres/` (`schema.sql`, `seed.sql`, `validation/`) remain the authoritative historical database baseline.
2. In subsequent implementation phases, Laravel migrations in `database/migrations/` will be authored to represent the approved 23-table schema faithfully, enabling standard Laravel test runners (`RefreshDatabase`) to execute against clean test databases.

---

## 26. `tests/` Structure

The testing directory accommodates automated feature and unit testing:

```
tests/
├── TestCase.php                  # Base test case setting up database & environment
├── Unit/
│   ├── CalculationServiceTest.php # Method 1 & 2 formula verification
│   ├── MarkValueCastTest.php     # Null vs 0.00 vs 'A' casting verification
│   └── AttendanceCalculationTest.php # 0/0 safe N/A handling verification
└── Feature/
    ├── Auth/
    │   ├── LoginTest.php
    │   └── DeactivatedUserTest.php
    ├── Academic/
    │   ├── AcademicYearLifecycleTest.php
    │   └── DynamicTermsTest.php
    ├── Marks/
    │   ├── MarkEntryValidationTest.php
    │   ├── MarkAuditLogTest.php
    │   └── ClosedYearMarkLockTest.php
    ├── Students/
    │   ├── StudentTransferHistoryTest.php
    │   └── ElectiveLockoutTest.php
    ├── Teachers/
    │   ├── MultiAssignmentScopeTest.php
    │   └── ClassTeacherScopeTest.php
    └── Reports/
        ├── ReportGenerationTest.php
        └── ImmutableRevisionTest.php
```

---

## 27. `storage/` Structure

Storage is strictly partitioned between public assets and secure, private PDF archives:

```
storage/
├── app/
│   ├── public/                   # Publicly symlinked storage (`php artisan storage:link`)
│   │   └── school/               # School logo & institutional branding images
│   └── private/                  # Non-public, secured application storage
│       └── reports/              # Generated PDF report card revisions
│           └── {year_id}/
│               └── {class_id}/
│                   └── {section_id}/
│                       └── {student_id}_rev_{revision}.pdf
├── framework/
│   ├── cache/
│   ├── sessions/
│   └── views/
└── logs/
    └── laravel.log               # Application error & security logs
```

### Report Security & Revision Preservation Rule
Report card PDFs are stored under `storage/app/private/reports/`. They are **never** accessible directly via a public URL. Access is mediated exclusively through `GeneratedReportDownloadController`, which verifies user authorization before streaming the file.

> [!IMPORTANT]
> **Domain Revision Immutability vs. Filesystem Storage:**  
> Immutability is enforced at the domain/application layer: regenerated reports create a new revision record (`revision_number = MAX + 1`) with a distinct file path (e.g. `..._rev_2.pdf`). Existing revision files are **never overwritten or deleted** by application workflows. The filesystem directory itself provides private isolation, not operating-system-level write-blocking.

---

## 28. `public/` Structure

The public web root contains only the entry point and compiled static assets:

```
public/
├── build/                        # Vite compiled, versioned CSS and JS assets
├── favicon.ico
├── index.php                     # Laravel application front controller
├── robots.txt
└── storage/                      # Symlink pointing to storage/app/public/
```

---

## 29. Configuration Structure (`config/`)

Application configuration files remain standard, reading environment-specific secrets from `.env`:

```
config/
├── app.php                       # Timezone ('UTC'), locale, application key
├── auth.php                      # Web guard configuration, User model provider
├── database.php                  # PostgreSQL connection parameters ('pgsql')
├── filesystems.php               # Disks: 'public' and 'reports_private'
├── logging.php                   # Daily rotated log channel configuration
└── session.php                   # Database/file session configuration
```

---

## 30. Documentation Structure (`docs/`)

Authoritative project specifications remain strictly isolated from implementation code:

```
docs/
└── architecture/
    ├── PHASE_6_1_TECHNOLOGY_BASELINE.md
    ├── PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md
    └── PHASE_6_3_LARAVEL_PROJECT_FOLDER_STRUCTURE.md
```

All existing historical documents (`BRD/`, `DB design/`, `database/Postgres/docs/`) remain in their permanent locations.

---

## 31. Namespace & Autoloading Conventions

All application code conforms to PSR-4 autoloading defined in `composer.json`:

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    }
}
```

### Namespace Hierarchy
- Models: `App\Models`
- Services: `App\Services`
- Controllers: `App\Http\Controllers` (and sub-namespaces like `App\Http\Controllers\Marks`)
- Form Requests: `App\Http\Requests` (and sub-namespaces like `App\Http\Requests\Marks`)
- Middleware: `App\Http\Middleware`
- Policies: `App\Policies`
- Enums: `App\Enums`
- Casts: `App\Casts`
- Value Objects: `App\ValueObjects`
- Contracts: `App\Contracts`
- Exceptions: `App\Exceptions`

---

## 32. Dependency Direction

To prevent spaghetti architecture and circular dependencies, code interactions must strictly observe this downward dependency direction:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         PERMITTED DEPENDENCY FLOW                           │
├─────────────────────────────────────────────────────────────────────────────┤
│ 1. HTTP Layer (Controllers, Requests, Middleware)                           │
│    May depend on: Application Services, Models, Policies, Enums, Exceptions │
│    Must NOT depend on: Database queries directly (for complex business logic)│
├─────────────────────────────────────────────────────────────────────────────┤
│ 2. Application Services Layer                                               │
│    May depend on: Eloquent Models, Contracts, Enums, Value Objects, DB      │
│    Must NOT depend on: Controllers, Form Requests, HTTP context, Blade      │
├─────────────────────────────────────────────────────────────────────────────┤
│ 3. Domain Models (Eloquent)                                                 │
│    May depend on: Casts, Enums, Value Objects, other Eloquent Models        │
│    Must NOT depend on: Services, Controllers, HTTP requests, Views          │
├─────────────────────────────────────────────────────────────────────────────┤
│ 4. Views (Blade Templates)                                                  │
│    May consume: Prepared ViewModel data, ViewModels, Enums, Component props │
│    Must NOT execute: Database queries, business calculations, mutations     │
├─────────────────────────────────────────────────────────────────────────────┤
│ 5. Frontend Scripts (Vanilla JS)                                            │
│    Operates on: DOM nodes, data-attributes, input events                    │
│    Must NOT: Assume authority over validation or calculation truth          │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 33. Architectural Boundary Rules

The following core rules govern code placement across boundaries:

1. **Mark Entry Boundary:**
   - Persistence: `Mark` model.
   - Business Logic & Transactions: `MarkEntryService`.
   - Authorization: `MarkPolicy`.
   - Input Validation: `BatchSaveMarksRequest`.
2. **Report Generation Boundary:**
   - Persistence: `GeneratedReport` model (append-only).
   - Coordination: `ReportGenerationService`.
   - Rendering Engine Abstraction: `ReportGeneratorContract`.
   - Authorization: `ReportPolicy`.
3. **Student Placement Boundary:**
   - Persistence: `StudentAcademicRecord` model.
   - Transfer Coordination: `StudentPlacementService`.
   - Input Validation: `InternalTransferStudentRequest`.
4. **Elective Allocation Boundary:**
   - Persistence: `StudentSubjectAllocation` model.
   - Lockout Enforcement: `StudentSubjectService`.
5. **Teacher Authorization Boundary:**
   - Persistence: `TeacherAssignment` model.
   - Contextual Scope Resolution: `TeacherAssignmentService`.
   - HTTP Gating: Laravel Policies (`MarkPolicy`, `AttendancePolicy`).

---

## 34. Naming Conventions

To maintain absolute uniformity across the codebase, the following naming conventions are enforced:

| Artifact Type | Naming Convention | Example |
|---|---|---|
| **Eloquent Models** | Singular PascalCase | `AcademicYear`, `SchoolClass`, `Mark` |
| **Database Tables** | Plural snake_case | `academic_years`, `classes`, `marks` |
| **Controllers** | Singular PascalCase + `Controller` | `MarkEntryController`, `StudentController` |
| **Form Requests** | Verb + Entity + `Request` | `BatchSaveMarksRequest`, `StoreUserRequest` |
| **Services** | Noun/Verb + `Service` | `MarkEntryService`, `CalculationService` |
| **Policies** | Entity + `Policy` | `MarkPolicy`, `StudentPolicy` |
| **Middleware** | VerbPhrase PascalCase | `EnsureUserIsActive`, `CheckRole` |
| **Enums** | Singular PascalCase | `MarkResultStatus`, `AcademicYearStatus` |
| **Exceptions** | DomainViolation + `Exception` | `ElectiveModificationLockedException` |
| **Contracts** | Noun + `Contract` | `ReportGeneratorContract` |
| **Blade Views** | kebab-case `.blade.php` | `entry.blade.php`, `class-subjects.blade.php` |
| **Blade Components**| kebab-case `.blade.php` | `mark-cell.blade.php`, `sidebar.blade.php` |
| **JavaScript Modules**| kebab-case `.js` | `mark-entry-grid.js`, `dom.js` |
| **CSS Files** | kebab-case `.css` | `mark-grid.css`, `tokens.css` |
| **Named Routes** | dot.notation | `marks.entry`, `students.transfer` |
| **Tests** | Feature/Unit + `Test` | `CalculationServiceTest`, `MarkEntryValidationTest` |

---

## 35. Prohibited Structural Patterns

The following patterns are strictly prohibited from entering the project structure:

- ❌ **Empty Repository Layers:** No `UserRepositoryInterface`, `MarkRepository`, or artificial wrapper layers forwarding calls directly to Eloquent.
- ❌ **Single-Page Application (SPA) Frameworks:** No React, Vue, Next.js, Nuxt, or Inertia modules.
- ❌ **Multi-Tenancy Constructs:** No `TenantMiddleware`, `TenantScope`, `tenant_id` columns, or multi-school directories.
- ❌ **Generic Helper Dumping Grounds:** No `app/Helpers/functions.php` or `app/Utils/helpers.php` containing loose, untyped global functions.
- ❌ **Public Report Card Storage:** Report PDFs must never be written to `storage/app/public/` or `public/reports/`.
- ❌ **API-First Routing:** No `routes/api.php` or token-based authentication endpoints.
- ❌ **Fat Controllers:** Controllers must not exceed 100–150 lines or contain raw SQL, multi-step transaction blocks, or business calculation math.
- ❌ **Querying Models in Blade:** Blade templates must never call `Model::query()`, `Model::all()`, or trigger lazy-loading queries.

---

## 36. Proposed Complete Project Tree

Below is the definitive, comprehensive project tree approved for Phase 6:

```
school-report-card/
├── app/
│   ├── Casts/
│   │   └── MarkValueCast.php
│   ├── Console/
│   │   └── Commands/
│   │       └── CloseAcademicYearCommand.php
│   ├── Contracts/
│   │   └── ReportGeneratorContract.php
│   ├── Enums/
│   │   ├── AcademicYearStatus.php
│   │   ├── AssessmentStatus.php
│   │   ├── CalculationMethod.php
│   │   ├── MarkResultStatus.php
│   │   ├── ReportType.php
│   │   ├── StudentPlacementStatus.php
│   │   ├── SubjectCategory.php
│   │   └── TeacherAssignmentType.php
│   ├── Exceptions/
│   │   ├── AcademicYearClosedException.php
│   │   ├── ElectiveModificationLockedException.php
│   │   ├── IncompleteStudentReportException.php
│   │   ├── InvalidTransferPlacementException.php
│   │   ├── MarkExceedsMaximumException.php
│   │   └── UnauthorizedTeacherScopeException.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Controller.php
│   │   │   ├── Academic/
│   │   │   │   ├── AcademicYearController.php
│   │   │   │   ├── ClassSubjectController.php
│   │   │   │   ├── SchoolClassController.php
│   │   │   │   ├── SectionController.php
│   │   │   │   ├── SubjectController.php
│   │   │   │   └── TermController.php
│   │   │   ├── Admin/
│   │   │   │   ├── AuditLogController.php
│   │   │   │   └── UserController.php
│   │   │   ├── Assessments/
│   │   │   │   ├── AssessmentApplicabilityController.php
│   │   │   │   ├── AssessmentController.php
│   │   │   │   └── AssessmentTypeController.php
│   │   │   ├── Attendance/
│   │   │   │   └── AttendanceController.php
│   │   │   ├── Auth/
│   │   │   │   ├── LoginController.php
│   │   │   │   └── LogoutController.php
│   │   │   ├── Calculations/
│   │   │   │   └── CalculationSettingController.php
│   │   │   ├── Dashboard/
│   │   │   │   └── DashboardController.php
│   │   │   ├── Marks/
│   │   │   │   ├── MarkCorrectionController.php
│   │   │   │   └── MarkEntryController.php
│   │   │   ├── Reports/
│   │   │   │   ├── GeneratedReportDownloadController.php
│   │   │   │   ├── ReportConfigurationController.php
│   │   │   │   └── ReportGenerationController.php
│   │   │   ├── Settings/
│   │   │   │   └── SchoolSettingController.php
│   │   │   ├── Students/
│   │   │   │   ├── StudentController.php
│   │   │   │   ├── StudentPlacementController.php
│   │   │   │   └── StudentSubjectAllocationController.php
│   │   │   └── Teachers/
│   │   │       └── TeacherAssignmentController.php
│   │   ├── Middleware/
│   │   │   ├── CheckRole.php
│   │   │   ├── EnsureUserIsActive.php
│   │   │   └── PreventRequestsWhenYearClosed.php
│   │   └── Requests/
│   │       ├── Academic/
│   │       ├── Admin/
│   │       ├── Assessments/
│   │       ├── Attendance/
│   │       ├── Auth/
│   │       ├── Calculations/
│   │       ├── Marks/
│   │       ├── Reports/
│   │       ├── Settings/
│   │       ├── Students/
│   │       └── Teachers/
│   ├── Models/
│   │   ├── AcademicYear.php
│   │   ├── Assessment.php
│   │   ├── AssessmentApplicability.php
│   │   ├── AssessmentType.php
│   │   ├── Attendance.php
│   │   ├── AuditLog.php
│   │   ├── CalculationSetting.php
│   │   ├── ClassSubject.php
│   │   ├── GeneratedReport.php
│   │   ├── Mark.php
│   │   ├── ReportAssessmentSelection.php
│   │   ├── ReportConfiguration.php
│   │   ├── Role.php
│   │   ├── SchoolClass.php
│   │   ├── SchoolSetting.php
│   │   ├── Section.php
│   │   ├── Student.php
│   │   ├── StudentAcademicRecord.php
│   │   ├── StudentSubjectAllocation.php
│   │   ├── Subject.php
│   │   ├── TeacherAssignment.php
│   │   ├── Term.php
│   │   └── User.php
│   ├── Policies/
│   │   ├── AcademicYearPolicy.php
│   │   ├── AssessmentPolicy.php
│   │   ├── AttendancePolicy.php
│   │   ├── AuditLogPolicy.php
│   │   ├── ClassSubjectPolicy.php
│   │   ├── MarkPolicy.php
│   │   ├── ReportPolicy.php
│   │   ├── SchoolSettingPolicy.php
│   │   ├── StudentPolicy.php
│   │   └── UserPolicy.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   └── AuthServiceProvider.php
│   ├── Services/
│   │   ├── AuditLogService.php
│   │   ├── CalculationService.php
│   │   ├── MarkEntryService.php
│   │   ├── ReportGenerationService.php
│   │   ├── StudentPlacementService.php
│   │   ├── StudentSubjectService.php
│   │   └── TeacherAssignmentService.php
│   ├── Support/
│   └── ValueObjects/
│       └── MarkResult.php
├── bootstrap/
│   └── app.php
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── database.php
│   ├── filesystems.php
│   ├── logging.php
│   └── session.php
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── docs/
│   └── architecture/
│       ├── PHASE_6_1_TECHNOLOGY_BASELINE.md
│       ├── PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md
│       └── PHASE_6_3_LARAVEL_PROJECT_FOLDER_STRUCTURE.md
├── public/
│   ├── build/
│   ├── favicon.ico
│   ├── index.php
│   ├── robots.txt
│   └── storage/
├── resources/
│   ├── css/
│   │   ├── app.css
│   │   ├── base.css
│   │   ├── components.css
│   │   ├── layout.css
│   │   ├── tokens.css
│   │   └── modules/
│   │       ├── mark-grid.css
│   │       └── report-print.css
│   ├── js/
│   │   ├── app.js
│   │   ├── modules/
│   │   │   ├── attendance-grid.js
│   │   │   ├── cascading-dropdowns.js
│   │   │   ├── mark-entry-grid.js
│   │   │   └── modal-controller.js
│   │   └── utilities/
│   │       └── dom.js
│   └── views/
│       ├── academic/
│       ├── admin/
│       ├── assessments/
│       ├── attendance/
│       ├── auth/
│       ├── calculations/
│       ├── components/
│       │   ├── forms/
│       │   ├── layouts/
│       │   ├── marks/
│       │   └── ui/
│       ├── dashboard/
│       ├── layouts/
│       ├── marks/
│       ├── reports/
│       ├── settings/
│       ├── students/
│       └── teachers/
├── routes/
│   ├── console.php
│   └── web.php
├── storage/
│   ├── app/
│   │   ├── private/
│   │   │   └── reports/
│   │   └── public/
│   │       └── school/
│   ├── framework/
│   │   ├── cache/
│   │   ├── sessions/
│   │   └── views/
│   └── logs/
│       └── laravel.log
├── tests/
│   ├── Feature/
│   │   ├── Academic/
│   │   ├── Auth/
│   │   ├── Marks/
│   │   ├── Reports/
│   │   ├── Students/
│   │   └── Teachers/
│   ├── TestCase.php
│   └── Unit/
├── .env.example
├── artisan
├── composer.json
├── package.json
├── phpunit.xml
└── vite.config.js
```

---

## 37. Traceability Matrix

| Structural Decision | Requirement / Architectural Source | Consequence & Impact |
|---|---|---|
| Model named `SchoolClass` | Approved 23-table schema (`classes`) | Eliminates PHP reserved keyword collision while maintaining Eloquent mapping to `classes`. |
| Dedicated `app/Services/` | Phase 6.1 Architecture Principle D & G | Keeps controllers thin; encapsulates transactions, calculation engines, and audit writing. |
| Canonical `StudentPlacementService` | Phase 6.2 Section 12 & Handover Decisions | Resolves naming consistency; directly models student academic placements and transfer histories. |
| Canonical `StudentSubjectService` | Phase 6.2 Section 13 (Elective Lockout) | Houses the elective modification rule (blocks changes once marks exist for that elective). |
| `app/Enums/` with 8 Backed Enums | PostgreSQL schema ENUM definitions | Provides strict, type-safe casting between PostgreSQL ENUMs and PHP domain models. |
| `app/Casts/MarkValueCast.php` | BRD V1.3 & Business Rule: Blank != 0.00 | Prevents PHP from converting SQL `NULL` marks to numeric `0.00`. |
| `ReportGeneratorContract.php` | Phase 6.1 Section 8 & Deferred Decisions | Decouples report business logic from the specific PDF engine implementation. |
| `EnsureUserIsActive.php` | Phase 6.1 Section 9 (Authentication Principle) | Immediately revokes session access upon account deactivation while preserving audit history. |
| Feature-grouped `resources/views/` | Server-rendered Blade architecture | Organizes templates cleanly by administrative domain without fragmenting components. |
| Private storage for generated reports | BRD V1.3 & Handover Rules (Immutable PDFs)| Ensures historical report card PDFs cannot be accessed publicly or accidentally overwritten. |
| Pure Vanilla JS modules | Phase 6.1 Section 7 (Frontend Principle) | Provides fast, spreadsheet-like mark entry interactions without SPA framework overhead. |
| Exclusion of repository interfaces | Phase 6.1 Section 12 & Phase 6.2 Section 24 | Eliminates redundant boilerplate; relies directly on Eloquent as the persistence mapper. |
| Single-school configuration | BRD V1.3 & Finalized Business Rules | Eliminates unnecessary multi-tenancy code, columns, middleware, and architectural debt. |

---

## 38. Phase 6.3 Decisions & Deferred Decisions

### 38.1 Approved Phase 6.3 Structural Decisions
1. **Flat Model Directory:** Confirmed all 23 Eloquent models reside directly in `app/Models/`.
2. **Canonical Service Naming:** Resolved service naming ambiguity by formally establishing `StudentPlacementService` (placements and transfers) and `StudentSubjectService` (elective allocations and lockout checks).
3. **Dedicated Type Casting:** Established `app/Casts/MarkValueCast.php` and `app/ValueObjects/MarkResult.php` to safeguard discrete mark states (`blank`, `numeric`, `absent`).
4. **Single Legitimate Contract:** Established `ReportGeneratorContract` as the only interface in `app/Contracts/` to encapsulate the interchangeable PDF engine.
5. **Private Report Storage Hierarchy:** Established `storage/app/private/reports/{year_id}/{class_id}/{section_id}/` for immutable, revision-controlled report cards.
6. **Strict Dependency Flow:** Formally defined the downward dependency direction: `HTTP -> Services -> Models -> Database`.

### 38.2 Deferred Decisions (Phases 6.4 – 6.9)
- **Phase 6.4:** Method signatures for `TeacherAssignmentService`, exact policy authorization logic, and session timeout parameters.
- **Phase 6.5:** Concrete HTTP route definitions, URL paths, Form Request validation rule sets, and controller response payloads.
- **Phase 6.6:** Exact Blade component HTML markup, CSS token values, and JavaScript keyboard navigation event listeners.
- **Phase 6.7:** Final PDF generation engine selection (`barryvdh/laravel-dompdf` vs `spatie/browsershot`).
- **Phase 6.8:** Test method implementations, database seeding data for tests, and test assertions.
- **Phase 6.9:** Server deployment scripts, production environment variables, and caching policies.

---

## 39. Phase 6.3 Completion Checklist

- [x] BRD V1.3 inspected and verified.
- [x] Finalized business rules and handover decisions verified.
- [x] Approved 23-table database relationship design verified.
- [x] Current PostgreSQL implementation inspected.
- [x] Phase 6.1 Technology Baseline verified.
- [x] Phase 6.2 Eloquent Architecture verified.
- [x] Laravel server-rendered web application architecture confirmed.
- [x] Node.js and Vite confirmed as build tooling only.
- [x] Blade confirmed as primary frontend view technology.
- [x] Vanilla JS confirmed as micro-interaction technology.
- [x] Tailored plain CSS confirmed as styling direction.
- [x] Exactly 23 models accounted for in `app/Models/`.
- [x] Service layer location and canonical service names established.
- [x] Policy and authorization structure established.
- [x] Form Request structure established.
- [x] Custom middleware structure (`EnsureUserIsActive`) established.
- [x] Enum structure (8 PostgreSQL-backed enums) established.
- [x] Cast and value object strategy established (`MarkValueCast`, `MarkResult`).
- [x] Domain exception hierarchy established.
- [x] Swappable PDF contract (`ReportGeneratorContract`) established.
- [x] Feature-grouped Blade view structure established.
- [x] Blade UI component categories established.
- [x] Vanilla JS module structure established.
- [x] Plain CSS module structure established.
- [x] Vite asset entry points confirmed.
- [x] Web route grouping strategy confirmed (no `api.php`).
- [x] Database migration and seeder folder strategy established.
- [x] Feature and Unit test structure established.
- [x] Private, immutable report PDF storage hierarchy established.
- [x] Public institutional branding asset storage established.
- [x] Documentation directory preserved.
- [x] Downward dependency direction and boundary rules defined.
- [x] Empty repository layers, SPA frameworks, and multi-tenancy strictly excluded.
- [x] Complete proposed project tree documented.
- [x] Traceability matrix documented.
- [x] No application or migration code was generated.
- [x] Documentation saved to `docs/architecture/PHASE_6_3_LARAVEL_PROJECT_FOLDER_STRUCTURE.md`.
