# PHASE 6.8 — PDF GENERATION & FILE STORAGE ARCHITECTURE
## Server-Side PDF Compilation, Failure-Safe Two-Phase Storage Protocols, Concurrency-Safe Revisions & Secure Streaming Delivery

**Project:** School Examination Marks and Report Card Management System  
**Document Version:** 1.1 (Reconciled)  
**Phase:** 6.8 — PDF Generation & File Storage Architecture  
**Status:** Reconciled Architectural Specification (Pending Test Verification)  

---

## 1. Purpose

This document establishes the definitive architectural blueprint for **Phase 6.8 — PDF Generation & File Storage Architecture** for the **School Examination Marks and Report Card Management System**. It specifies the end-to-end technical mechanics for compiling, persisting, archiving, and securely streaming institutional student report cards.

Specifically, this specification defines:
1. **Report Generation Lifecycle:** Pre-generation authorization, mandatory completion validation, dynamic view model composition, and failure-safe persistence.
2. **PDF Rendering Engine Selection:** The architectural rationale for choosing Browsershot (Headless Chromium) behind the swappable `ReportGeneratorContract`.
3. **Dedicated PDF Template & Print CSS Architecture:** An isolated rendering canvas (`resources/views/reports/pdf/`) completely separated from the web application shell, supporting dynamic assessment columns, multi-term layouts, and A4 print pagination.
4. **Concurrency-Safe Revision Allocation:** A deadlock-free, row-level locking strategy on student academic placements guaranteeing strictly monotonic revision numbering (`Revision 1`, `Revision 2`, ...) without database schema alterations.
5. **Two-Phase Failure-Safe Persistence Protocol:** A staging and promotion protocol with compensating cleanup and storage reconciliation across failure windows A through I, preventing partially written PDF artifacts, unreferenced records, or historical report destruction.
6. **Private Storage & Zero Public Exposure:** File storage organization under `storage/app/private/` with relative subdirectories `temp/reports/` and `reports/`, and controller-mediated binary streaming adhering to the project's role authorization boundaries.
7. **Traceability & Business Rule Alignment:** Strict adherence to BRD V1.3, the validated 23-table PostgreSQL schema, and authoritative decisions (DEC-001 through DEC-056).

---

## 2. Relationship to Previous Phases

Phase 6.8 integrates directly with all approved architectural layers:

```
Phase 6.1: Technology Baseline & Architecture Principles (Approved)
  - PHP 8.4+, Laravel 13, PostgreSQL 18.4, Blade, Vanilla JS, Plain CSS, Vite.
  - Swappable ReportGeneratorContract established.
      ↓
Phase 6.2: Domain Model & Eloquent Architecture (Approved)
  - GeneratedReport, StudentAcademicRecord, SchoolSetting, ReportConfiguration models.
      ↓
Phase 6.3: Laravel Project / Folder Structure (Approved)
  - storage/app/private/ hierarchy (temp/reports/, reports/) and app/Services/Report/ naming standards.
      ↓
Phase 6.4: PostgreSQL + Eloquent Integration Architecture & Decision Ledger (Approved)
  - 23 tables, uk_gr_revision_identity index, DEC-001 to DEC-030.
      ↓
Phase 6.5: Authentication, Authorization & Security Architecture (Approved)
  - ReportPolicy: Admin, Staff, and Scoped Class Teachers authorized; Subject Teachers blocked.
      ↓
Phase 6.6: HTTP Layer, Routes, Form Requests & Controller Contracts (Approved)
  - POST /reports/generate, POST /reports/preview, GET /reports/download/{id}.
      ↓
Phase 6.7: Blade / UI / Vanilla JavaScript Integration Architecture (Approved & Audited)
  - Light design tokens, mark entry spreadsheet grid, revision history UI.
      ↓
Phase 6.8: PDF Generation & File Storage Architecture (Current Phase)
  - Browsershot engine, print CSS, revision concurrency locks, atomic file commits.
      ↓
Phase 6.9: Application Testing & Quality Gate Strategy
  - PDF rendering tests, concurrency collision tests, filesystem rollback tests.
      ↓
Phase 6.10: Environment Configuration & Deployment Readiness
  - Chromium binary paths, puppeteer dependencies, storage directory permissions.
```

---

## 3. Authoritative Sources & Precedence

All requirements, constraints, and operational workflows specified herein derive strictly from the authoritative source hierarchy:

1. **BRD V1.3** (`BRD/School_Examination_Marksheet_Requirements_v1_3.docx`):
   - Mark permissions and latest saved value: BR-001 to BR-008
   - Mark ternary states (`blank`, `numeric`, `absent`): BR-009 to BR-014
   - Dynamic assessment names, types, and applicability max marks: BR-015 to BR-024
   - Dual calculation formulas and 2-decimal percentages: BR-025 to BR-031
   - Dynamic term count and flexible names: BR-032 to BR-036
   - Student continuity, historical placements, and frozen subject snapshots: BR-037 to BR-047
   - Report-card attendance (0/0 division-by-zero defense): BR-048 to BR-053, CL-010
   - Academic year closure rules: BR-054 to BR-057, CL-003
   - Report card behavior, revisions, school logo, pass/fail, and exclusions: BR-058 to BR-068
   - Audit logging of report generation: BR-076 to BR-078
   - Marksheet completion invariants (blank is incomplete, A is complete): BR-080 to BR-084
   - Clarifications: CL-004 (revision identity), CL-006 (final report config), CL-007 (display vs applicability), CL-011 (signatures deferred).
2. **Finalized Business Rules & Project Handover Decisions**: Authoritative resolution of Class Teacher report generation and download rights within active classroom scope; Subject Teacher denial; single-school architecture.
3. **Approved 23-Table Relational Schema** (`database/Postgres/schema.sql`, `DB design/DB-table-definitons.txt`):
   - `generated_reports`: Immutable historical PDF records. Functional unique index `uk_gr_revision_identity` on `(student_academic_record_id, report_type, COALESCE(term_id, 0), COALESCE(assessment_id, 0), revision_number)`. Zero hash, status, soft-delete, or `updated_at` columns.
   - `school_settings`: Singleton configuration (`school_name`, `school_logo_path`, `pass_mark`).
   - `report_configurations` & `report_assessment_selections`: Display order and visibility controls. Zero `contributes_to_calculation` column.
4. **Project Decision Ledger** (`docs/decisions.md`): DEC-001 through DEC-048.

---

## 4. Absolute Scope Boundaries & Anti-Patterns

To prevent architectural drift and scope creep, the following boundaries are permanently enforced:

- ❌ **NO Database Modifications:** Exactly 23 tables exist. No new columns (such as `file_hash`, `file_size`, `status`, `deleted_at`) may be added to `generated_reports` or any other table.
- ❌ **NO Student Admission Numbers (Pre-Phase 10 Baseline):** The pre-Phase 10 baseline prohibited admission numbers. Under DEC-072 in Phase 10, `admission_number` was approved as the unique business identifier on `students`. However, storage paths and generated report revisions continue to rely on database relational PKs and structured tokens to avoid path instability. Filenames and report headers remain consistent with approved report templates.
- ❌ **NO Invented Demographics:** No date of birth, gender, parent names, address, or student photos may be added to report cards.
- ❌ **NO Batch PDF Generation:** The system does NOT support batch classroom PDF generation, bulk zip archives, or school-wide export queues (explicitly deferred in BRD Section 4). Report generation is strictly per-student.
- ❌ **NO Unapproved Metrics:** No overall annual percentages (BR-064), student rankings (BR-065), GPA scores, grade-band classifications (BR-067), or automatic promotion decisions.
- ❌ **NO Public Report Storage:** PDFs must never be written to `public/`, `storage/app/public/`, or exposed via public symlinks. All downloads must remain mediated by `GeneratedReportDownloadController`.
- ❌ **NO Overwriting Existing PDFs:** Historical generated PDFs are immutable revisions. An existing PDF file on disk is never overwritten, replaced, or deleted during standard operations.
- ❌ **NO Client-Side Calculations:** JavaScript must never calculate official report percentages, pass/fail states, or attendance ratios. The server is the sole calculation authority.

---

## 5. Report Types & Scope Definition

The system supports exactly three report card types governed by `report_type_enum` in the database schema:

```
                  +-----------------------------------+
                  |        report_type_enum           |
                  +-----------------------------------+
                                    |
            +-----------------------+-----------------------+
            |                                               |
            v                                               v
    +---------------+                               +---------------+
    |     exam      |                               |     term      |
    +---------------+                               +---------------+
    | Specific single                               | Aggregated term
    | assessment instance                           | report across all
    | (e.g. Unit Test 1).                           | displayed tests
    | Requires:                                     | in that term.
    | assessment_id != NULL                         | Requires:
    | term_id = NULL                                | term_id != NULL
    +---------------+                               | assessment_id = NULL
            |                                       +---------------+
            +-----------------------+-----------------------+
                                    |
                                    v
                            +---------------+
                            |     final     |
                            +---------------+
                            | Comprehensive
                            | end-of-year card
                            | spanning all
                            | configured terms.
                            | Requires:
                            | term_id = NULL
                            | assessment_id = NULL
                            +---------------+
```

### 5.1 Exam Report (`report_type = 'exam'`)
- Evaluates a single, specific assessment instance (e.g. *Mid-Term Exam 2025* or *Unit Test 1*).
- Contextual Invariant: Strictly requires `assessment_id != NULL` and `term_id = NULL`.
- Evaluates student marks against the applicable subject maximum marks defined in `assessment_applicability.maximum_marks`.

### 5.2 Term Report (`report_type = 'term'`)
- Evaluates student performance across all assessments configured for a specific term (e.g. *Term 1*).
- Contextual Invariant: Strictly requires `term_id != NULL` and `assessment_id = NULL`.
- **Decoupling of Display Selection vs. Calculation Input:**
  - **Display Selection:** Controlled strictly by `report_assessment_selections` (ordered by `display_order`). Controls which assessment columns are physically rendered on the marksheet.
  - **Calculation Rules:** Governed strictly by the class calculation setting (`class_calculation_settings.calculation_method`, BR-025 to BR-027). Under current approved rules (BR-028), only the Term Exam contributes to the term percentage calculation.
  - **Invariant:** A displayed assessment does NOT automatically become a calculation input. Assessments that are displayed may contribute or not contribute to calculation based on their assessment type; an assessment not displayed still follows calculation rules if applicable. Zero new database fields are introduced.

### 5.3 Final Report (`report_type = 'final'`)
- Comprehensive annual marksheet synthesizing student performance across all configured terms in the academic year.
- Contextual Invariant: Strictly requires both `term_id = NULL` and `assessment_id = NULL`. Examples where `report_type = 'final'` with `term_id` populated are strictly invalid.
- Dynamically adapts to the number of configured terms ($1, 2, 3, \dots, N$) in ascending sequence order (BR-033, BR-035, BR-062).
- Displays term summaries and, if configured in `report_configurations.configuration_data`, individual assessment breakdowns.
- Excludes annual overall percentage (BR-064) and class rank (BR-065). Displays term-level percentages and overall pass/fail status based on `school_settings.pass_mark` (BR-066).

---

## 6. Authoritative Report Data Preparation Pipeline

Before any PDF compilation begins, the application service prepares a sanitized, fully resolved Data Transfer Object (`ReportDataPayload`). The rendering engine never queries Eloquent models or executes business calculations.

```
+----------------------------------------------------------------------------------------------------+
|                                    APPLICATION SERVICE LAYER                                       |
|                                                                                                    |
|  [StudentAcademicRecord] ──> Fetch Historical Placement (Class, Section, Roll No)                  |
|  [ClassSubject]          ──> Fetch Historical Subject Names (subject_name_snapshot)                |
|  [Marks]                 ──> Fetch Latest Saved Marks (result_status, mark_value)                  |
|  [Attendance]            ──> Fetch Term Attendance (days_attended, total_working_days)             |
|  [CalculationService]    ──> Compute Term Percentages (Method 1 / Method 2 to 2 Decimals)          |
|  [SchoolSetting]         ──> Resolve Pass Threshold (pass_mark) & School Logo Path                 |
|                                                     │                                              |
|                                                     ▼                                              |
|                                        +──────────────────────────+                                |
|                                        |    ReportDataPayload     |                                |
|                                        |  (Immutable View Model)  |                                |
|                                        +──────────────────────────+                                |
+-----------------------------------------------------│----------------------------------------------+
                                                      │
                                                      ▼
                                       +──────────────────────────────+
                                       |   ReportGeneratorContract    |
                                       |  (Renders Blade Canvas)      |
                                       +──────────────────────────────+
```

### 6.1 Historical Academic Placement Resolution
The view model binds strictly to the historical `student_academic_records` row tied to the report request:
- `student_name`: Resolved from `students.student_name`.
- `class_name`: Resolved from historical `classes.name`.
- `section_name`: Resolved from historical `sections.name`.
- `roll_number`: Resolved from historical `student_academic_records.roll_number`.
- `academic_year_name`: Resolved from `academic_years.name`.
- If the student was transferred in a later period, historical reports retain the placement active during that assessment period (BR-039, BR-046).

### 6.2 Frozen Subject Snapshot Resolution
Subject titles rendered in the PDF table are resolved from `class_subjects.subject_name_snapshot` (BR-047). If a subject is renamed in a subsequent academic year, historical PDF compilations continue to render the exact snapshot name recorded when the curriculum was configured.

---

## 7. Marksheet Completion Validation Contract

In accordance with BR-080 through BR-084, a final report card can **only** be generated when every mandatory applicable subject has an entered, completed evaluation.

```
                   +--------------------------------------------------+
                   |  ReportGenerationService::validateCompletion()   |
                   +--------------------------------------------------+
                                             │
                   ┌─────────────────────────┴────────────────────────┐
                   ▼                                                  ▼
     [Any Mandatory Subject Blank?]                     [All Mandatory Subjects Evaluated]
                   │                                                  │
                   ▼                                                  ▼
    THROW IncompleteMarksException                     ALLOW PDF Compilation
    - Abort before filesystem access                   - Proceeds to Revision Lock
    - Return detailed unentered list                   - Renders complete official card
```

### 7.1 Completion Evaluation Invariants
1. **Numeric Mark ($0.00 \le m \le \text{max\_marks}$):** Valid completed evaluation. A mark of `0.00` is a valid score and is complete (BR-010).
2. **Absent (`A`):** Valid completed evaluation (BR-011, BR-012). Contributes $0.00$ to obtained marks.
3. **Blank (`NULL`):** Incomplete (BR-009, BR-082). Indicates that no result has been entered.
4. **Rejection Enforcement:** If any required subject for the student has `result_status = 'blank'`, `ReportGenerationService` throws an `IncompleteMarksheetException` containing the array of missing subject names. PDF generation is aborted immediately; no temporary file is created, and no database row is written.
5. **Student Independence:** Incomplete marks for one student never block report generation for another student whose marks are complete (BR-084).

---

## 8. PDF Engine Evaluation & Final Selection

The project baseline established a swappable `ReportGeneratorContract`. Phase 6.8 evaluates the concrete rendering engines to finalize the primary implementation.

### 8.1 Comparative Engine Evaluation Matrix

| Architectural Evaluation Criteria | Option A: Dompdf (`barryvdh/laravel-dompdf`) | Option B: Browsershot / Chromium (`spatie/browsershot`) |
| :--- | :--- | :--- |
| **CSS Capabilities** | Primitive CSS 2.1 parser. No CSS Grid, no modern Flexbox. Highly fragile box model. | Complete modern CSS3 engine (Blink). Full Flexbox, CSS Grid, custom properties, and subpixel rendering. |
| **Dynamic Table Pagination** | Severe bugs with multi-page tables. Table headers (`<thead>`) frequently overlap rows or clip borders on break. | Native Chromium print layout. Predictable `thead { display: table-header-group }` repeating across all pages. |
| **Dynamic Assessment Columns** | Collapses or clips when table width exceeds page boundaries. Poor column auto-distribution. | Superior subpixel font measurement and table auto-layout. Scales large assessment matrices reliably. |
| **Print CSS Specifications** | Partial `@page` margin support. Unreliable page-break controls (`break-inside: avoid`). | Modern print layout engine (`@page` dimensions, `break-inside: avoid`, `page-break-after`). Header/footer page numbering managed via Puppeteer templates. |
| **Visual Fidelity & Typography**| Inconsistent system font metrics. Requires pre-compiling font definitions. | High visual fidelity matching desktop Chromium; consistent Figtree font rendering. |
| **Local Windows Dev (PHP 8.4)**| Pure PHP library. Zero external OS binary dependencies. | Requires local Node.js 22.x and Puppeteer / Chromium browser binary. |
| **Production Server Overhead** | Low memory footprint ($15\text{MB}-30\text{MB}$ per render). Slower parsing for large tables. | Higher memory footprint ($80\text{MB}-150\text{MB}$ per Chromium process). Faster compilation for complex DOMs. |
| **Security Surface** | PHP-level image include vulnerabilities if chroot is misconfigured. | Process isolation. Requires flags (`--no-sandbox`, `--disable-web-security=false`) and network blocking. |

### 8.2 Final Architectural Decision: Browsershot (Chromium)
**Browsershot (Spatie / Puppeteer / Headless Chromium) is selected as the primary PDF rendering engine.**

#### Rationale
Institutional report cards are complex, information-dense documents featuring dynamic assessment columns, variable numbers of terms, multi-subject tables, and precise branding requirements. Dompdf's archaic rendering engine frequently fails on dynamic multi-page tables, clipping cell borders, misaligning headers, and destroying print layouts when column counts shift. Browsershot provides the rendering fidelity of modern desktop Chromium, ensuring that complex A4 tables paginate with repeated headers and controlled page breaks under objective testing.

#### Concrete Engine Binding
The concrete implementation `BrowsershotReportGenerator` implements `ReportGeneratorContract`:
```php
namespace App\Services\Report\Generators;

use App\Contracts\ReportGeneratorContract;
use Spatie\Browsershot\Browsershot;

class BrowsershotReportGenerator implements ReportGeneratorContract
{
    public function generatePdfFromHtml(string $html): string
    {
        return Browsershot::html($html)
            ->format('A4')
            ->landscape(false)
            ->margins(15, 12, 15, 12)
            ->showBackground()
            ->setOption('args', [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
            ])
            ->waitUntilNetworkIdle()
            ->pdf();
    }
}
```

---

## 9. Dedicated PDF Blade & View Architecture

The PDF rendering layer utilizes a dedicated, clean Blade template hierarchy located in `resources/views/reports/pdf/`. It does NOT inherit from the administrative web application shell (`layouts/app.blade.php`), ensuring zero JavaScript, sidebar, topbar, or web interaction markup is compiled into the document.

### 9.1 PDF View Directory Tree
```
resources/views/reports/pdf/
├── layouts/
│   └── report-canvas.blade.php      /* Master A4 canvas, print metadata, font imports */
├── partials/
│   ├── header.blade.php             /* School crest logo, institutional name, report title */
│   ├── student-meta.blade.php       /* Student name, class, section, roll number, academic year */
│   ├── marks-table.blade.php        /* Dynamic assessment matrix, subjects, obtained/max marks */
│   ├── attendance-box.blade.php     /* Days attended, total working days, derived ratio */
│   ├── summary-footer.blade.php     /* Calculation method note, pass/fail result, revision notice */
│   └── page-footer.blade.php        /* Running footer, timestamp, page numbers */
├── templates/
│   ├── exam-report.blade.php        /* Single assessment report layout */
│   ├── term-report.blade.php        /* Term report with dynamic assessment columns */
│   └── final-report.blade.php       /* Multi-term annual report layout */
```

### 9.2 Master Canvas Template (`layouts/report-canvas.blade.php`)
```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{{ $payload->reportTitle }}</title>
  <style>
    {!! $printCssContent !!}
  </style>
</head>
<body class="report-body">
  <div class="report-page">
    @yield('content')
  </div>
</body>
</html>
```

---

## 10. PDF Print CSS Architecture

All styling for PDF documents is centralized in `resources/css/modules/report-print.css` and injected directly into the template `<style>` tag as raw CSS text. This eliminates external HTTP requests during Headless Chromium execution.

### 10.1 Key Print CSS Specifications
```css
/* Page Setup & Paged Media Standard */
@page {
  size: A4 portrait;
  margin: 15mm 12mm 15mm 12mm;
}

/* 
 * NOTE ON CHROMIUM PAGINATION CAPABILITIES:
 * Headless Chromium (Blink) does NOT support CSS Paged Media Level 3 margin box counters 
 * (@bottom-right { content: counter(page); }).
 * Instead, Browsershot / Puppeteer utilizes Chromium's native template injection:
 * ->showBrowserHeaderAndFooter()
 * ->footerHtml('<div style="font-size: 8pt; width: 100%; text-align: right; padding-right: 12mm; color: #64748B; font-family: Figtree, sans-serif;">Page <span class="pageNumber"></span> of <span class="totalPages"></span></div>')
 */

/* Typography Baseline */
body.report-body {
  font-family: 'Figtree', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  font-size: 9pt;
  line-height: 1.4;
  color: #0F172A;
  background-color: #FFFFFF;
  margin: 0;
  padding: 0;
}

/* Table Architecture & Multi-Page Pagination */
table.report-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 12px;
  page-break-inside: auto;
}

table.report-table thead {
  display: table-header-group; /* Repeats table header across subsequent pages */
}

table.report-table tr {
  page-break-inside: avoid;    /* Prevents a single row from being sliced across pages */
  page-break-after: auto;
}

table.report-table th {
  background-color: #F1F5F9;
  color: #0F172A;
  font-weight: 600;
  font-size: 8pt;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  border: 1px solid #CBD5E1;
  padding: 6px 8px;
}

table.report-table td {
  border: 1px solid #CBD5E1;
  padding: 5px 8px;
  font-size: 8.5pt;
}

/* Tabular Numeric Data Alignment */
td.cell-numeric {
  text-align: right;
  font-family: 'JetBrains Mono', monospace;
  font-variant-numeric: tabular-nums;
}

td.cell-center {
  text-align: center;
}

/* Avoid Breaking Key Sections */
.avoid-break {
  page-break-inside: avoid;
}

/* Absent State Display */
span.absent-pill {
  font-weight: 700;
  color: #DC2626;
}
```

---

## 11. Dynamic Multi-Term & Assessment Matrix Layouts

The PDF rendering engine dynamically compiles columns and term sections based strictly on the authoritative database configuration.

### 11.1 Dynamic Assessment Columns (Term Reports)
1. **Resolution:** For a term report, the service queries `report_assessment_selections` for the active `report_configuration_id`, filtering by `is_displayed = TRUE` and sorting by `display_order ASC`.
2. **Table Header Compilation:** A dynamic column is rendered for each displayed assessment, showing the assessment name and its applicability maximum marks for that subject:
   ```html
   <th>Mathematics</th>
   <th>Unit Test 1 (25.00)</th>
   <th>Mid-Term (50.00)</th>
   <th>Term Exam (100.00)</th>
   <th>Term % (Calc)</th>
   ```
3. **Calculation Decoupling:** In strict adherence to BR-023 and BR-028, displayed assessments do not automatically participate in term calculations. The column `Term %` displays the exact calculation produced by `CalculationService` (currently derived exclusively from the Term Exam).

### 11.2 Multi-Term Matrix (Final Reports)
For final reports, the template dynamically iterates through all configured terms in ascending sequence order (`terms.sequence_no ASC`, BR-033, BR-035):
- Renders term performance blocks side-by-side or stacked based on configured column thresholds.
- Supports arbitrary term counts ($1, 2, 3, 4, \dots$). The template never hardcodes variables like `$term1`, `$term2`, or `$term3`.
- Displays individual term percentages alongside the overall Pass/Fail designation.

---

## 12. School Branding & Logo File Access

In accordance with BR-068, institutional report cards feature the school's crest logo configured in `school_settings.school_logo_path`.

### 12.1 Logo Resolution Architecture
1. **Private Local Resolution:** To eliminate SSRF (Server-Side Request Forgery) risks and prevent Chromium from dispatching outbound HTTP network requests during rendering, the logo is resolved locally from disk.
2. **Dynamic Base64 Inline Injection:** If `school_logo_path` is populated and exists on the `public_branding` disk, the service reads the raw image bytes, dynamically determines the MIME type (`File::mimeType($logoPath)`, supporting PNG, JPEG, SVG, and WebP), and injects an inline base64 data URI:
   ```php
   $mimeType = File::mimeType($logoAbsolutePath);
   $base64Data = base64_encode(File::get($logoAbsolutePath));
   $logoDataUri = "data:{$mimeType};base64,{$base64Data}";
   ```
   ```html
   <img class="school-logo" src="{{ $logoDataUri }}" alt="School Crest">
   ```
3. **Fallback State:** If no logo is configured or the file is missing, the template gracefully renders a clean institutional typographic header featuring `school_settings.school_name`.
4. **Header Restraint:** The header displays strictly `school_name` and the logo. No unapproved fields (registration numbers, affiliation boards, phone numbers, addresses, or principal signatures) are rendered.

---

## 13. Concurrency-Safe Revision Allocation Protocol

In accordance with BR-059 and BR-060, generated reports are immutable historical revisions (`Revision 1`, `Revision 2`, ...). Concurrency race conditions must be eliminated to prevent duplicate revisions or overwritten files.

```
                           CONCURRENCY CONTROL TIMELINE
                           
       Request A (User 1)                              Request B (User 2)
              │                                               │
              ▼                                               ▼
    [Phase 1: Temp PDF Render]                      [Phase 1: Temp PDF Render]
    (Renders storage/app/private/                   (Renders storage/app/private/
     temp/reports/uuid-a.pdf)                        temp/reports/uuid-b.pdf)
              │                                               │
              ▼                                               ▼
    [BEGIN DB TRANSACTION]                          [BEGIN DB TRANSACTION]
              │                                               │
    [SELECT ... FOR UPDATE]                         [SELECT ... FOR UPDATE]
    (Acquires Row Lock on                           (BLOCKED - Waits for
     StudentAcademicRecord #101)                     Request A to commit)
              │                                               ┆
    Compute Max Revision:                                     ┆
    Current Max = 1 -> Next = 2                               ┆
              │                                               ┆
    Promote Temp PDF -> Final:                                ┆
    .../revision-2.pdf                                        ┆
              │                                               ┆
    INSERT generated_reports                                  ┆
    (revision_number = 2)                                     ┆
              │                                               ┆
    [COMMIT DB TRANSACTION] ──────────────────────────────────┘
    (Releases Lock; <10ms hold)                       │
                                                      ▼
                                            (Acquires Row Lock on
                                             StudentAcademicRecord #101)
                                                      │
                                            Compute Max Revision:
                                            Current Max = 2 -> Next = 3
                                                      │
                                            Promote Temp PDF -> Final:
                                            .../revision-3.pdf
                                                      │
                                            INSERT generated_reports
                                            (revision_number = 3)
                                                      │
                                            [COMMIT DB TRANSACTION]
```

### 13.1 Row-Level Locking Mechanics & Transaction Decoupling
1. **Decoupled Heavy Rendering:** Heavy Headless Chromium compilation ($500\text{ms} - 2\text{s}$) executes **outside** database transactions in a temporary staging path. Holding a PostgreSQL row lock during browser rendering would cause thread starvation and catastrophic lock contention.
2. **Target Entity Row Lock:** When promoting the staged PDF, the service opens a database transaction and initiates an exclusive pessimistic row lock on the parent placement record:
   ```sql
   SELECT id FROM student_academic_records WHERE id = :record_id FOR UPDATE;
   ```
3. **Revision Number Computation:** While holding the exclusive lock ($< 10\text{ms}$ duration), the service queries the current maximum revision for that exact contextual identity:
   ```sql
   SELECT COALESCE(MAX(revision_number), 0) + 1 AS next_revision
   FROM generated_reports
   WHERE student_academic_record_id = :record_id
     AND report_type = :report_type
     AND (term_id = :term_id OR (term_id IS NULL AND :term_id IS NULL))
     AND (assessment_id = :assessment_id OR (assessment_id IS NULL AND :assessment_id IS NULL));
   ```
4. **Database Uniqueness Safety:** The PostgreSQL functional unique index `uk_gr_revision_identity` acts as the final relational backstop. If an un-lockable edge collision occurs, the database throws `UniqueConstraintViolationException`, the transaction aborts cleanly, and the client receives a structured concurrency retry prompt.

---

## 14. Two-Phase Failure-Safe Persistence Protocol & Compensating Rollback

A relational database transaction cannot automatically roll back a committed file write on disk, nor does a filesystem rename encompass SQL transactions. Therefore, the system does NOT claim literal ACID atomicity across PostgreSQL and the filesystem. Instead, report generation follows a **Two-Phase Failure-Safe Persistence Protocol with Compensating Cleanup and Reconciliation**.

```
+──────────────────────────────────────────────────────────────────────────────────────────────────+
|                 TWO-PHASE FAILURE-SAFE PERSISTENCE & COMPENSATING RECONCILIATION                 |
+──────────────────────────────────────────────────────────────────────────────────────────────────+
  Phase 1: Pre-Validation & Staging (Zero Database Locks Held)
    1. Authenticate user & verify contextual authorization (ReportPolicy@generate).
    2. Validate marksheet completion (ReportCompletionService). Reject if any required mark is blank.
    3. Render PDF via Browsershot to private staging path on 'private_reports' disk:
       temp/reports/{uuid}.pdf (storage/app/private/temp/reports/{uuid}.pdf)
    4. Validate staged artifact: File exists, file size > 0 bytes, header matches %PDF-.
    
  Phase 2: Relational Lock, File Promotion & Transaction Commit
    5. DB::beginTransaction()
    6. Lock StudentAcademicRecord row FOR UPDATE (serializes concurrent requests for placement).
    7. Resolve $nextRevision safely via COALESCE(MAX(revision_number), 0) + 1.
    8. Resolve canonical destination relative path on 'private_reports' disk:
       reports/{year_id}/{class_id}/{sec_id}/{record_id}/{report_type}/revision-{next_revision}.pdf
    9. Verify target final path does NOT exist (defensive collision check).
   10. Promote staged PDF to final destination path:
       Storage::disk('private_reports')->move($tempStagingPath, $finalDestinationPath)
       - Local same-filesystem move: Performs atomic filesystem rename.
       - Cross-partition fallback: Handles stream copy + unlink with source verification.
   11. INSERT into generated_reports table with relative file_path and revision_number.
   12. INSERT into audit_logs table (action = 'report_generated').
   13. DB::commit() (Releases row lock).
+──────────────────────────────────────────────────────────────────────────────────────────────────+
```

### 14.1 Crash Boundaries & Failure Windows (A through I)

The persistence protocol explicitly defines system states, compensating actions, and reconciliation mechanisms across all potential failure windows without introducing unapproved schema columns:

| Window | Point of Failure | Database State | Filesystem State | Immediate Cleanup Action | Recovery / Reconciliation Action |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **A** | **Rendering Fails** (Chromium crash, template syntax error, OOM in Node) | No transaction started. Zero rows altered. | No temp file created, or partial 0-byte file left in staging directory. | Unlink `temp/reports/{uuid}.pdf` in catch block if exists. | Abort request. Return structured HTTP 500 error. Scheduled background command (`reports:clean-temp`) purges transient files > 60 min old. |
| **B** | **Temporary Validation Fails** (0-byte file, missing `%PDF-` magic bytes) | No transaction started. Zero rows altered. | Corrupted/empty file at `temp/reports/{uuid}.pdf`. | Explicit unlink of `temp/reports/{uuid}.pdf`. | Abort before acquiring database locks. Return HTTP 422 validation failure. Scheduled temp cleaner purges orphans. |
| **C** | **DB Transaction Fails Before Promotion** (`FOR UPDATE` lock acquisition fails or deadlocks) | Transaction aborted / rolled back. Zero rows altered. | Valid staged PDF remains in `temp/reports/{uuid}.pdf`. | Catch block unlinks `temp/reports/{uuid}.pdf`. | Return HTTP 409 / 503 concurrency retry prompt to client. Temporary disk cleaner removes unlinked files. |
| **D** | **File Promotion Fails** (Disk write error, storage permission error, disk full) | Row lock held on `StudentAcademicRecord`. Zero rows inserted. | Staged file remains at `temp/reports/{uuid}.pdf`; destination path not created. | `DB::rollBack()`; delete `temp/reports/{uuid}.pdf`. | Row lock released cleanly. Return HTTP 500 disk I/O error to user. Zero orphaned permanent files. |
| **E** | **INSERT `generated_reports` Fails After Promotion** (Unique constraint violation on `uk_gr_revision_identity` or DB error) | Uncommitted transaction; insert failed. | Promoted file exists at canonical `reports/.../revision-{n}.pdf`. | `DB::rollBack()`; compensating file delete: `Storage::disk('private_reports')->delete($finalDestinationPath)`. | Row lock released. Client receives retry response. File deletion removes unreferenced physical PDF. Existing revisions (1 ... n-1) untouched. |
| **F** | **`audit_logs` Insert Fails** (Constraint violation or connection failure) | Uncommitted transaction with `generated_reports` row pending. | Promoted file exists at `reports/.../revision-{n}.pdf`. | `DB::rollBack()`; compensating file delete: `Storage::disk('private_reports')->delete($finalDestinationPath)`. | Entire SQL transaction rolls back (no report record, no audit log). Compensating action removes physical file. |
| **G** | **DB Commit Fails** (Database connection drop during commit handshake) | PostgreSQL rolls back transaction automatically; zero rows persisted. | Promoted file exists at `reports/.../revision-{n}.pdf`. | Catch block invokes compensating file delete: `Storage::disk('private_reports')->delete($finalDestinationPath)`. | If catch block executes, disk is clean. If process terminates abruptly, Window H reconciliation protocol handles it. |
| **H** | **Process Crashes After Promotion But Before Commit** (Kernel SIGKILL, OS crash, sudden power failure) | PostgreSQL aborts connection; uncommitted transaction automatically rolled back by DB engine. | Promoted file exists at `reports/.../revision-{n}.pdf` without matching DB row. | No immediate PHP catch block executes due to abrupt process death. | **Storage Reconciliation:** `php artisan reports:reconcile-storage` scans disk for unindexed PDFs and flags/quarantines them. Next generation request for that revision safely overwrites or archives the unindexed file. |
| **I** | **Process Crashes After Commit** (Post-commit logging or response dispatch crashes) | Transaction committed successfully; `generated_reports` and `audit_logs` exist. | File permanently exists at canonical destination `reports/.../revision-{n}.pdf`. | Staging temp file was already moved (no temp orphan). No compensating file delete needed. | Report generation succeeded authoritatively. Client or worker retries/downloads existing report record without duplicate generation. |

---

## 15. Private Storage Architecture & Path Conventions

In strict compliance with the Technology Baseline (Phase 6.1) and Project Folder Structure (Phase 6.3), all report cards reside on private storage outside the web document root.

### 15.1 Canonical Filesystem Disk Definition
To prevent path-nesting inconsistencies (e.g. `reports/reports/...`), the private disk root is bound canonically to `storage_path('app/private')` in `config/filesystems.php`:

```php
'private_reports' => [
    'driver' => 'local',
    'root' => storage_path('app/private'),
    'visibility' => 'private',
    'throw' => true,
],
```

### 15.2 Deterministic Path Convention
Both temporary staging and permanent reports reside on the same `private_reports` disk root, ensuring atomic filesystem moves during promotion:

1. **Temporary Staging Path:**
   $$\text{Staging Path} = \texttt{temp/reports/}\{\text{uuid}\}\texttt{.pdf}$$
   *Absolute Path:* `storage/app/private/temp/reports/{uuid}.pdf`

2. **Permanent Historical Report Path (stored in `generated_reports.file_path`):**
   $$\text{File Path} = \texttt{reports/}\{year\_id\}\texttt{/}\{class\_id\}\texttt{/}\{section\_id\}\texttt{/}\{record\_id\}\texttt{/}\{report\_type\}\texttt{/revision-}\{revision\_number\}\texttt{.pdf}$$
   *Absolute Path:* `storage/app/private/reports/{year_id}/{class_id}/{section_id}/{record_id}/{report_type}/revision-{revision_number}.pdf`

#### Example Storage Hierarchy on Disk
```text
storage/app/private/
├── temp/
│   └── reports/
│       └── 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d.pdf  (transient staging)
└── reports/
    └── 1/                                             (academic_year_id)
        └── 2/                                         (class_id)
            └── 3/                                     (section_id)
                └── 101/                               (student_academic_record_id)
                    └── final/                         (report_type)
                        ├── revision-1.pdf             (immutable historical file)
                        └── revision-2.pdf             (immutable current file)
```
- Completely eliminates duplicate `reports/reports` directory nesting.
- Matches `generated_reports.file_path` values stored in the database.
- Zero human-facing admission numbers in path structure.
- Completely impervious to path traversal attacks.
- Completely impervious to path traversal attacks.

---

## 16. Secure Download Streaming Integration

Direct filesystem URLs (e.g. `https://school.test/storage/reports/card.pdf`) do NOT exist. All report downloads are mediated by `GeneratedReportDownloadController@download` on `GET /reports/download/{generatedReport}`.

### 16.1 Authorization Verification Pipeline
When a download is requested:
1. **Policy Gate:** The controller triggers `Gate::authorize('download', $generatedReport)`.
   - `Administrator`: Unconditionally authorized.
   - `Office Staff`: Unconditionally authorized.
   - `Class Teacher`: Authorized **strictly if** `$generatedReport->studentAcademicRecord` matches the teacher's active assignment (`academic_year_id`, `class_id`, `section_id`).
   - `Subject Teacher`: **Denied (HTTP 403 Forbidden).**
2. **Deactivated User Resilience:** If the user who generated the report (`generated_by_user_id`) is subsequently deactivated, the historical report remains accessible to currently authorized active staff (DEC-032).
3. **File Existence Validation:** Controller checks `Storage::disk('private_reports')->exists($generatedReport->file_path)`. If missing, aborts with HTTP 404 without exposing internal server paths.

### 16.2 Sanitized Download Filename Strategy
The browser-facing filename is dynamically compiled and sanitized to ensure clean client downloads while remaining completely detached from internal storage keys:
```php
$studentName = Str::slug($generatedReport->studentAcademicRecord->student->student_name);
$className   = Str::slug($generatedReport->studentAcademicRecord->schoolClass->name);
$sectionName = Str::slug($generatedReport->studentAcademicRecord->section->name);
$rollNumber  = $generatedReport->studentAcademicRecord->roll_number;
$reportType  = $generatedReport->report_type;
$rev         = $generatedReport->revision_number;

$downloadFilename = sprintf(
    'Report_%s_%s-%s_Roll-%02d_%s_Rev%d.pdf',
    $studentName,
    $className,
    $sectionName,
    $rollNumber,
    ucfirst($reportType),
    $rev
);
```
- Example Download Filename: `Report_aarav-sharma_8-a_Roll-01_Final_Rev2.pdf`
- Zero raw user input; completely sanitized against ASCII header injection attacks.

### 16.3 Secure HTTP Response Headers
The binary stream returns with strict security headers:
```http
HTTP/1.1 200 OK
Content-Type: application/pdf
Content-Disposition: attachment; filename="Report_aarav-sharma_8-a_Roll-01_Final_Rev2.pdf"
Content-Length: 148920
Cache-Control: private, no-cache, no-store, must-revalidate
Pragma: no-cache
Expires: 0
X-Content-Type-Options: nosniff
```

---

## 17. Security Threat Model & Defense Matrix

| Threat ID | Vulnerability / Attack Vector | Architectural Mitigation Strategy |
| :--- | :--- | :--- |
| **TH-PDF-01** | **Direct Static File Access** | Reports reside outside web root (`storage/app/private/`). Web server denies public URL requests. |
| **TH-PDF-02** | **IDOR Report Download** | `ReportPolicy@download` verifies student academic placement against active `teacher_assignments`. |
| **TH-PDF-03** | **SSRF via Chromium Renderer**| Logo resolved locally via disk / base64 inline. Renderer launched with network access restricted. |
| **TH-PDF-04** | **HTML / Script Injection** | All database strings (`student_name`, `subject_name`) escaped via Blade `{{ }}`. Zero raw user HTML. |
| **TH-PDF-05** | **Path Traversal in Filenames**| Storage paths constructed strictly from cast integers (`year_id`, `class_id`, `record_id`). |
| **TH-PDF-06** | **Concurrent Overwrite** | Exclusive row-level locking on `StudentAcademicRecord` + unique index `uk_gr_revision_identity`. |
| **TH-PDF-07** | **Incomplete Report Generation**| `validateCompletion()` aborts generation if any required mark is `blank`. |
| **TH-PDF-08** | **Stale Authorization on Closed Years**| Class Teachers can generate/download reports for assigned class; mark mutation remains blocked. |

---

## 18. Architectural Traceability Matrix

| BRD / Decision Rule | Domain Requirement Summary | Phase 6.8 Enforcement Component | Concrete Storage / Rendering Consequence |
| :--- | :--- | :--- | :--- |
| **BR-008** | Newly generated reports use latest saved mark | `ReportDataPreparationService` | Queries latest active marks; never caches stale mark data. |
| **BR-011, BR-012**| Absent displays as `A`, contributes 0 | `marks-table.blade.php` | Renders `<span class="absent-pill">A</span>`; calculation treats as 0. |
| **BR-018, BR-019**| Contextual maximum marks per assessment | `ReportDataPreparationService` | Resolves max marks from `assessment_applicability`; no 100-mark cap. |
| **BR-023, BR-028**| Display selection != calculation participation | `ReportConfigurationService` | Displays selected tests; calculates term % using Term Exam only. |
| **BR-030, BR-031**| Percentages to 2 decimals, decimal marks | `CalculationService` | Formats all numeric marks and term percentages to 2 decimal places. |
| **BR-037 to 047**| Historical placement & subject snapshots | `StudentAcademicRecord`, `ClassSubject` | Uses historical roll number, class, section, and `subject_name_snapshot`. |
| **BR-048 to 053**| Term attendance, 0/0 division-by-zero defense| `attendance-box.blade.php` | If total working days = 0, displays `"N/A"` (CL-010). |
| **BR-058 to 068**| Immutable revisions, school logo, pass/fail | `ReportGenerationService`, `school_settings` | Increments `revision_number`; inlines base64 logo; evaluates pass mark. |
| **BR-064, 065** | No annual overall percentage, no rank | PDF templates | Excludes annual percentage and rank columns entirely. |
| **BR-080 to 084**| Marksheet completion invariants | `ReportGenerationService::validateCompletion` | Rejects generation if any required mark is `blank`. |
| **CL-004** | Historical PDFs retained by revision | `generated_reports` | Revision identity uniqueness; existing files never overwritten. |
| **CL-011** | Signatures deferred to future milestone | PDF templates | Excludes teacher/headmaster signature fields. |
| **DEC-038** | Scoped Class Teacher report download | `ReportPolicy@download` | Class Teacher restricted strictly to assigned classroom. |
| **DEC-049** | Browsershot / Chromium PDF Engine | `BrowsershotReportGenerator` | Compiles modern A4 CSS, handles dynamic multi-page tables. |
| **DEC-050** | Concurrency row-locking revision allocation | `ReportGenerationService` | `SELECT ... FOR UPDATE` eliminates duplicate revision numbers. |
| **DEC-051** | Two-phase failure-safe persistence protocol | `ReportGenerationService` | Staging to temporary path before file promotion and DB commit. |
| **DEC-052** | Dedicated PDF Blade canvas & print CSS | `resources/views/reports/pdf/` | Isolated canvas completely detached from web application shell. |

---

## 19. Decision Ledger Additions (Phase 6.8)

The following architectural decisions are established for Phase 6.8 and recorded in `docs/decisions.md`:

- **DEC-049 — Browsershot / Chromium Server-Side PDF Rendering Engine Selection**  
  Standardizes report card compilation on Spatie Browsershot (Headless Chromium) implementing `ReportGeneratorContract`. Provides modern Chromium layout and pagination capabilities, avoiding Dompdf table clipping, with testable verification against dynamic assessment columns and print page breaks.
- **DEC-050 — Concurrency-Safe Student Placement Row-Locking Revision Allocation**  
  Enforces serialized revision allocation via `SELECT id FROM student_academic_records WHERE id = ? FOR UPDATE`. Prevents revision number collisions and race conditions during simultaneous generation requests without modifying the database schema.
- **DEC-051 — Two-Phase Failure-Safe Filesystem-to-Database PDF Storage Protocol**  
  Mandates that PDFs are compiled into private temporary storage (`temp/reports/{uuid}.pdf`), validated for non-zero file size and valid `%PDF-` headers, and promoted to final storage (`reports/.../revision-{n}.pdf`) before committing the database transaction. Defines compensating cleanup and storage reconciliation across failure windows A through I.
- **DEC-052 — Dedicated PDF Blade Rendering Canvas & Inline Print CSS Architecture**  
  Establishes an isolated Blade template hierarchy (`resources/views/reports/pdf/`) completely detached from the web shell. Inlines raw print CSS (`report-print.css`) and base64 logo assets to eliminate external HTTP lookups and SSRF attack vectors.

---

## 20. Specification Reconciliation & Pre-Verification Checklist

The Phase 6.8 architectural specification has been reconciled against all authoritative project constraints:

- [x] **No Database Schema Changes:** Relational 23-table schema strictly preserved. Zero columns added to `generated_reports` or `school_settings`.
- [x] **No Admission Number (Pre-Phase 10 Baseline):** Admission number was completely excluded from Phase 6 PDF architecture; DEC-072 in Phase 10 introduced `admission_number` on `students` without altering report file storage paths.
- [x] **Strict Ternary Mark Representation:** Blank rejects generation; Numeric is bounded by contextual max marks; Absent displays as `A` and contributes $0.00$.
- [x] **Dynamic Columns & Terms Supported:** Dynamic assessment columns rendered via `report_assessment_selections`; dynamic terms ($1 \dots N$) supported without hardcoding.
- [x] **Immutable Revisions Preserved:** Revisions increment monotonically (`Revision 1`, `Revision 2`); existing PDFs are never overwritten or deleted.
- [x] **Concurrency & Race Conditions Handled:** Pessimistic row locking on `StudentAcademicRecord` prevents revision collisions.
- [x] **Two-Phase Failure-Safe Persistence Protocol Specified:** Two-phase staging, compensating cleanup, and storage reconciliation protocol handles failure windows A through I.
- [x] **Private Storage & Role Security Enforced:** Files stored under `storage/app/private/` with relative subdirectories `temp/reports/` and `reports/`; downloads mediated by `GeneratedReportDownloadController` and `ReportPolicy`.
- [x] **No Batch PDF Generation:** Generation is strictly per-student.
- [x] **No Unapproved Metrics:** Annual percentage, student rank, and grade bands strictly excluded.
- [x] **Branding & Pass/Fail Validated:** Inlines school crest logo with dynamic MIME detection; resolves pass threshold from `school_settings.pass_mark`.
- [x] **Signatures Explicitly Deferred:** Teacher and headmaster signatures marked as deferred per CL-011.
