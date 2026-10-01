# PHASE 6.7 — BLADE / UI / VANILLA JAVASCRIPT INTEGRATION ARCHITECTURE
## Modern Light-Themed Design System, Blade Component Hierarchy & Vanilla JS Micro-Interactions

**Project:** School Examination Marks and Report Card Management System  
**Document Version:** 1.1 (Audit & Domain-Reconciliation Update)  
**Phase:** 6.7 — Blade / UI / Vanilla JavaScript Integration Architecture  
**Status:** Approved & Authoritative Architectural Specification  

---

## 1. Purpose

This document establishes the authoritative frontend presentation architecture for the **School Examination Marks and Report Card Management System**. It specifies the complete user interface and client integration layer, bridging the authoritative server-rendered Laravel backend (Phases 6.1–6.6) with a modern, high-density, accessible, and responsive user experience.

Specifically, this blueprint establishes:
1. **Application Shell & Navigation Architecture:** A cohesive desktop-first administrative layout featuring an identity-branded sidebar, context-aware topbar, and strictly role-aware navigation.
2. **Light-Themed Design System:** A curated, high-contrast light color palette, typography scales, spacing units, and elevation tokens tailored for administrative productivity.
3. **Blade Layout & Component Architecture:** A composable, atomic Blade component hierarchy (`layouts/`, `ui/`, `forms/`, `marks/`) providing strict visual consistency without framework bloat.
4. **View Hierarchy:** A feature-grouped view catalog reflecting all 23 database entities and domain workflows.
5. **Spreadsheet-Style Mark Entry Grid:** A specialized, high-performance DOM grid supporting full ternary mark representation (`blank`, `numeric`, `absent`), keyboard traversal, dirty tracking, and atomic batch submissions.
6. **Attendance & Operational UIs:** Dedicated interfaces for attendance logging, student academic placement history, CSV batch imports, dynamic academic configurations, and immutable audit logs.
7. **Role-Aware Report Generation & History UX:** Contextual report generation and PDF download interfaces adhering strictly to teacher assignment boundaries and immutable revision histories.
8. **Classification of Computed & Derived Values:** Clear architectural separation between server-authoritative calculations, presentation-only client derivations, and visual formatting.
9. **Vanilla JavaScript Progressive Enhancement:** A modular client runtime (`modules/`, `utilities/`) operating strictly as an ergonomic UX layer without claiming security or calculation authority.
10. **Accessibility & Security Conformance:** Comprehensive adherence to WCAG 2.1 AA standards and CSP-compatible, zero-inline script execution.

---

## 2. Relationship to Previous Phases

Phase 6.7 operates strictly within the boundaries and contracts defined in all preceding phases:

```
Phase 6.1: Technology Baseline & Architecture Principles (Approved)
  - PHP 8.4+, Laravel 13, PostgreSQL 18.4, Blade, Vanilla JS, Plain CSS, Vite.
  - Zero framework dependencies (no Tailwind, Bootstrap, React, Vue, Livewire, Alpine).
      ↓
Phase 6.2: Domain Model & Eloquent Architecture (Approved)
  - 23 Eloquent models, 85 relationships, mutability tiers, historical placement integrity.
      ↓
Phase 6.3: Laravel Project / Folder Structure (Approved)
  - Standard directory layout: resources/views/, resources/css/, resources/js/.
      ↓
Phase 6.4: PostgreSQL + Eloquent Integration Architecture & Decision Ledger (Approved)
  - 23 tables, native PostgreSQL types, MarkValueCast, DEC-001 to DEC-030.
      ↓
Phase 6.5: Authentication, Authorization & Security Architecture (Approved)
  - Session auth, EnsureUserIsActive, multi-assignment resolution, DEC-031 to DEC-040.
  - Class Teacher scoped report generation/download; Subject Teacher denied.
      ↓
Phase 6.6: HTTP Layer, Routes, Form Requests & Controller Contracts (Approved)
  - RESTful routes, Form Requests, thin controllers, batch mark contracts, DEC-041 to DEC-044.
      ↓
Phase 6.7: Blade / UI / Vanilla JavaScript Integration Architecture (Current Phase)
  - Light design tokens, atomic Blade components, Mark Grid UX, Vanilla JS modules.
  - Full domain reconciliation against the approved 23-table schema (DEC-045 to DEC-048).
      ↓
Phase 6.8: PDF Generation & File Storage Blueprint (Subsequent Phase)
  - PDF rendering engine, print stylesheet layout, filesystem atomicity, streaming delivery.
      ↓
Phase 6.9: Application Testing & Quality Gate Strategy
  - Unit, feature, authorization, browser/DOM interaction, and visual regression suites.
      ↓
Phase 6.10: Environment Configuration & Deployment Readiness
  - Production asset compilation, cache headers, CSP policy headers, deployment checklist.
```

---

## 3. Authoritative Sources & Precedence

Every design token, component interface, and client script specified herein derives strictly from the project's authoritative source hierarchy:

1. **BRD V1.3** (`BRD/School_Examination_Marksheet_Requirements_v1_3.docx`) — Foundational functional rules:
   - Users and mark permissions: BR-001 to BR-008
   - Mark ternary states: BR-009 to BR-014
   - Assessment definitions, applicability, and max marks: BR-015 to BR-024
   - Term calculation formulas: BR-025 to BR-031
   - Dynamic terms configuration: BR-032 to BR-036
   - Student continuity, transfers, and historical placements: BR-037 to BR-047
   - Report-card attendance: BR-048 to BR-053
   - Academic-year lifecycle and closed-year rules: BR-054 to BR-057
   - Report cards, revisions, pass/fail, and exclusions: BR-058 to BR-068
   - Student import, user accounts, and immutable audit logs: BR-069 to BR-079
   - Marksheet completion invariants: BR-080 to BR-084
   - Clarifications: CL-001 to CL-011 (CL-009 explicit re-import warning, CL-010 zero working days attendance division defense).
2. **Finalized Business Rules & Project Handover Decisions** — Multi-assignment teacher resolution, single-school architectural boundary, closed-year operational rules, and elective subject lock invariants.
3. **Approved 23-Table Relational Schema** (`DB design/DB-table-definitons.txt`, `database/Postgres/schema.sql`) — 23 tables, 43 foreign keys, 7 CHECK constraints, and functional unique indexes.
4. **Approved ERD** (`DB design/mermaid-diagram.png`) — Relational cardinalities and placement topology.
5. **Phase 6.1: Technology Baseline** (`docs/architecture/PHASE_6_1_TECHNOLOGY_BASELINE.md`) — Plain CSS tokens, Blade rendering, Vanilla JS modules, Vite bundling.
6. **Phase 6.5: Security Architecture** (`docs/architecture/PHASE_6_5_AUTHENTICATION_AUTHORIZATION_SECURITY.md`) — Role capabilities, Class Teacher assigned classroom scope, inactive account termination.
7. **Phase 6.6: HTTP Layer & Controller Contracts** (`docs/architecture/PHASE_6_6_HTTP_LAYER_ROUTES_FORM_REQUESTS_CONTROLLERS.md`) — Route names, Form Requests, thin controller endpoints, batch mark JSON schema.
8. **Project Decision Ledger** (`docs/decisions.md`, DEC-001 through DEC-048).

---

## 4. Fundamental Frontend Architecture Principles

The frontend architecture is governed by ten unyielding principles:

1. **Server-Rendered Blade Authority:** Blade templates render complete, semantically correct HTML on the server. The client browser never receives an empty container to mount an SPA.
2. **Zero External CSS/JS Frameworks:** No CSS frameworks (Tailwind, Bootstrap, Bulma, Foundation) and no JavaScript UI libraries (React, Vue, Angular, Svelte, Livewire, Alpine.js, jQuery). All styling uses standard Vanilla CSS with custom properties; all interactivity uses modern, modular Vanilla JavaScript (ES2024).
3. **Modern 2026 Light-Themed SaaS Aesthetic:** A refined, clean, high-density, calm, and professional light appearance. The interface avoids dark modes, heavy gamified gradients, cartoonish illustrations, oversized hero banners, and excessive glassmorphism.
4. **Progressive Enhancement & Ergonomics:** Forms and navigation function using standard HTML POST/GET. Vanilla JavaScript enhances the user experience with asynchronous batch saving, spreadsheet keyboard navigation, dependent dropdown fetching, and modal dialogs.
5. **Absolute Server-Side Security Boundary:** The UI is purely a presentation layer. JavaScript and Blade hiding (`@can`, `@if`) provide user guidance, never authorization. Every HTTP submission is authenticated, authorized, and validated independently on the server.
6. **Strict Ternary Mark Representation:** The UI strictly differentiates `blank` (no result has been entered, incomplete), `numeric` ($0.00 \le \text{mark} \le \text{contextual } \text{max\_marks}$, valid score), and `absent` (`A`, complete, contributes $0.00$ to calculations). Blank is never coerced to zero.
7. **Domain Model Integrity (No Invented Fields):** The UI strictly represents only data attributes defined in the approved schema. The UI must never invent or display unsupported fields (such as student dates of birth, student gender, parent names, registration numbers, or unapproved demographic metadata). *(Note: `admission_number` was approved as the unique student business identifier under DEC-072 in Phase 10).*
8. **Historical Placement Integrity:** Student academic placements (`student_academic_records`) represent immutable historical reality. The UI displays historical classroom allocations chronologically and never gives the impression that past records are overwritten by transfers.
9. **WCAG 2.1 AA Accessibility:** High contrast text ratios ($\ge 4.5:1$ for normal body text, $\ge 3:1$ for large text and UI components), visible focus rings, aria-describedby form errors, and keyboard operable data grids.
10. **Strict CSP & Zero Inline Scripts:** In conformance with Content Security Policy level 3 directives, zero inline scripts (`onclick=`, `<script>alert()</script>`) are permitted. All events are attached via DOM event listeners and data attributes.

---

## 5. Light Theme Color System & Design Tokens

The application employs a curated light palette designed for long administrative working sessions. It utilizes clean neutral slates for surfaces and borders, an authoritative royal blue for primary interactions, and restrained semantic hues for status indicators.

### 5.1 Color Palette Specifications

| Token Name | Hex Value | Role / Usage Context |
| :--- | :--- | :--- |
| `--color-primary` | `#2563EB` | Primary action buttons, active navigation items, active tab underlines, focused input rings. |
| `--color-primary-hover` | `#1D4ED8` | Hover state for primary buttons and interactive brand elements. |
| `--color-primary-active` | `#1E40AF` | Pressed / active state for primary buttons. |
| `--color-primary-soft` | `#EFF6FF` | Soft brand backgrounds, selected table row highlights, active sidebar item backgrounds. |
| `--color-page-bg` | `#F8FAFC` | Global viewport background behind cards and content containers. |
| `--color-surface` | `#FFFFFF` | Primary card surfaces, modal dialogs, data table backgrounds, input backgrounds. |
| `--color-surface-muted` | `#F1F5F9` | Table header rows, disabled input backgrounds, secondary action buttons, sidebar background. |
| `--color-border` | `#E2E8F0` | Structural borders, card boundaries, table cell dividers, input borders. |
| `--color-border-hover` | `#CBD5E1` | Input hover border, card hover outline. |
| `--color-text` | `#0F172A` | Primary heading and high-contrast body typography. |
| `--color-text-secondary`| `#475569` | Subheadings, table headers, form field labels, secondary navigation labels. |
| `--color-text-muted` | `#64748B` | Helper text, metadata captions, timestamps, disabled input text. |
| `--color-success` | `#16A34A` | Success alerts, completed mark indicators, pass status badges, active record badges. |
| `--color-success-bg` | `#F0FDF4` | Background for success banners, positive toast notifications, and complete status pills. |
| `--color-success-border`| `#BBF7D0` | Border for success alerts and badges. |
| `--color-warning` | `#D97706` | Incomplete status badges, pending mark notices, cautionary modal headers. |
| `--color-warning-bg` | `#FFFBEB` | Background for warning alerts and incomplete calculation notices. |
| `--color-warning-border`| `#FDE68A` | Border for warning alerts and badges. |
| `--color-danger` | `#DC2626` | Destructive action buttons, validation error text, absent status pills, 403 notices. |
| `--color-danger-bg` | `#FEF2F2` | Background for error banners, invalid input highlights, absent cell indicators. |
| `--color-danger-border` | `#FECACA` | Border for danger alerts, error badges, and invalid form controls. |
| `--color-info` | `#0284C7` | Information notices, calculation method explanations, revision badges. |
| `--color-info-bg` | `#F0F9FF` | Background for informative callouts and guidance tooltips. |
| `--color-info-border` | `#BAE6FD` | Border for informational alerts. |

---

## 6. Typography & Scaling System

The typography system uses `Figtree` as the primary sans-serif typeface, paired with system fallbacks to guarantee zero layout shift. Monospace scales are applied to numeric mark values and roll numbers for tabular alignment.

### 6.1 Font Stack
```css
--font-sans: 'Figtree', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
--font-mono: 'JetBrains Mono', 'SFMono-Regular', Menlo, Monaco, Consolas, monospace;
```

### 6.2 Typographic Hierarchy Table

| Scale Step | Size | Line Height | Weight | Letter Spacing | Element / Role Mapping |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Display** | $32\text{px}$ ($2.0\text{rem}$) | $1.25$ | 700 (Bold) | $-0.025\text{em}$ | Dashboard greeting, public auth screens. |
| **Page Title** | $28\text{px}$ ($1.75\text{rem}$) | $1.3$ | 700 (Bold) | $-0.02\text{em}$ | Primary page `<h1>` headers. |
| **Section Title**| $20\text{px}$ ($1.25\text{rem}$) | $1.4$ | 600 (SemiBold)| $-0.01\text{em}$ | Card titles, modal headers, major section `<h2>`.|
| **Subsection** | $16\text{px}$ ($1.0\text{rem}$) | $1.4$ | 600 (SemiBold)| $0\text{em}$ | Form fieldset legends, table group headers `<h3>`.|
| **Body (Normal)**| $14\text{px}$ ($0.875\text{rem}$)| $1.5$ | 400 (Regular) | $0\text{em}$ | Primary data rows, standard body copy, labels. |
| **Body (Medium)**| $14\text{px}$ ($0.875\text{rem}$)| $1.5$ | 500 (Medium) | $0\text{em}$ | Navigation labels, table cell emphasis, button text.|
| **Table / Dense**| $13\text{px}$ ($0.8125\text{rem}$)| $1.4$ | 400 / 500 | $0\text{em}$ | Compact mark grid cells, dense roster rows. |
| **Small / Meta** | $12\text{px}$ ($0.75\text{rem}$) | $1.4$ | 400 (Regular) | $+0.01\text{em}$ | Helper text, breadcrumbs, timestamp metadata. |
| **Badge / Label**| $11\text{px}$ ($0.6875\text{rem}$)| $1.2$ | 600 (SemiBold)| $+0.04\text{em}$ | Status pill text, uppercase table column tags. |

---

## 7. Spacing, Elevation & Layout Tokens

### 7.1 Spacing Scale
All margins, paddings, and flex/grid gaps follow a standardized $4\text{px}$ / $8\text{px}$ stepping scale:

```css
--space-1: 4px;    /* 0.25rem - Micro spacing, badge internal padding */
--space-2: 8px;    /* 0.5rem  - Compact button padding, input internal padding */
--space-3: 12px;   /* 0.75rem - Standard table cell padding, form group gap */
--space-4: 16px;   /* 1.0rem  - Card internal padding, standard layout gap */
--space-5: 20px;   /* 1.25rem - Section padding, topbar horizontal padding */
--space-6: 24px;   /* 1.5rem  - Card header padding, modal internal body */
--space-8: 32px;   /* 2.0rem  - Page container horizontal padding */
--space-10: 40px;  /* 2.5rem  - Major section vertical margins */
--space-12: 48px;  /* 3.0rem  - Empty state container vertical padding */
--space-16: 64px;  /* 4.0rem  - Dashboard module separation */
```

### 7.2 Border Radius Tokens
```css
--radius-sm: 4px;   /* Compact badges, mark grid inputs, micro tags */
--radius-md: 6px;   /* Standard inputs, dropdown selects, primary buttons */
--radius-lg: 8px;   /* Cards, container panels, alert callouts */
--radius-xl: 12px;  /* Modal dialogs, floating dropdown panels */
--radius-full: 9999px; /* Status pills, circular avatar containers */
```

### 7.3 Box Shadows & Elevation Tokens
To ensure a calm, modern SaaS aesthetic, shadows are restrained and use cool slate alpha channels:
```css
--shadow-sm: 0 1px 2px 0 rgba(15, 23, 42, 0.05);
--shadow-card: 0 1px 3px 0 rgba(15, 23, 42, 0.08), 0 1px 2px -1px rgba(15, 23, 42, 0.04);
--shadow-elevated: 0 4px 6px -1px rgba(15, 23, 42, 0.08), 0 2px 4px -2px rgba(15, 23, 42, 0.04);
--shadow-modal: 0 20px 25px -5px rgba(15, 23, 42, 0.1), 0 8px 10px -6px rgba(15, 23, 42, 0.05);
--shadow-focus: 0 0 0 3px rgba(37, 99, 235, 0.2);
--shadow-focus-danger: 0 0 0 3px rgba(220, 38, 38, 0.2);
```

### 7.4 Responsive Viewport Breakpoints
```css
--bp-mobile: 480px;   /* Small mobile screens */
--bp-tablet: 768px;   /* Tablets, iPads, small horizontal viewports */
--bp-desktop: 1024px; /* Standard laptops, collapsed sidebar mode */
--bp-wide: 1280px;    /* Standard desktop workstations */
--bp-ultra: 1440px;   /* High-density administrative widescreen displays */
```

---

## 8. Global Application Shell Architecture

The application implements a persistent, desktop-optimized two-column administrative layout consisting of an authorized navigation sidebar, a global context topbar, and a scrollable main content viewport.

```
+-----------------------------------------------------------------------------------------+
| [LOGO] School Portal      | Active Year: 2025-26 | Role: Class Teacher | User: J. Smith [v]|
+---------------------------+-------------------------------------------------------------+
| SIDEBAR NAVIGATION        | BREADCRUMBS: Home / Marks / Class 8-A / Mathematics         |
|                           |-------------------------------------------------------------|
| * Dashboard               | PAGE HEADER: Mark Entry — Mathematics (Unit Test 1)         |
| * Academic Management [v] | Actions: [View Corrections] [Save All Changes]              |
|   - Years & Terms         |-------------------------------------------------------------|
|   - Classes & Sections    | CONTEXT SELECTOR FILTER BAR                                 |
|   - Subjects              | [ Year: 2025-26 v ] [ Class: 8 v ] [ Sec: A v ] [ Sub: Math]|
| * Students                |-------------------------------------------------------------|
| * Teacher Assignments     | SPREADSHEET MARK ENTRY GRID                                 |
| * Assessments             |                                                             |
| * Mark Entry (Active)     | # | Roll | Student Name | Mark / 50.00 | Status | Prev Save|
| * Attendance              | 1 | 101  | Aarav Sharma | [  45.50   ] | Valid  | 2 mins ago|
| * Reports                 | 2 | 102  | Bob Baker    | [    A     ] | Absent | Stored    |
| * Settings                | 3 | 103  | Charlie Cox  | [          ] | Blank  | Incomplete|
|                           |-------------------------------------------------------------|
| [Collapse Sidebar]        | FOOTER / STATUS: 32 Students | 1 Unsaved Change | Online    |
+-----------------------------------------------------------------------------------------+
```

### 8.1 Desktop Structure ($\ge 1024\text{px}$)
- **Sidebar Width:** Fixed at $260\text{px}$ in expanded state; collapsible to $68\text{px}$ compact icon mode via a local storage preference.
- **Topbar Height:** Fixed at $64\text{px}$ (`--space-16`), pinned to the viewport top with `position: sticky; top: 0; z-index: 40`.
- **Content Viewport:** `flex: 1`, with maximum container width capped at $1440\text{px}$ to prevent extreme typographic stretching on ultra-wide monitors.

### 8.2 Tablet Structure ($768\text{px} - 1023\text{px}$)
- **Sidebar:** Persistent compact icon mode ($68\text{px}$ width) to maintain workspace visibility on tablets while preserving horizontal space for data tables.
- **Topbar:** Height $64\text{px}$, matching desktop height with simplified navigation indicators.

### 8.3 Mobile Structure ($< 768\text{px}$)
- **Sidebar:** Hidden off-canvas (`transform: translateX(-100%)`). Toggled via a hamburger trigger button in the topbar, rendering as an accessible drawer over a backdrop overlay (`z-index: 50`).
- **Topbar:** Compact $56\text{px}$ height displaying current school initials, active title, and user dropdown avatar.

---

## 9. Role-Aware Navigation Architecture

Navigation links are strictly evaluated against the authenticated user's role and operational assignments. 

> [!IMPORTANT]
> Blade `@can` and `@if` directives control navigation link visibility to prevent user confusion, but they carry **zero security authority**. Server-side middleware and policies independently authorize every destination URL.

### 9.1 Role Navigation Matrix

| Navigation Module | Route Destination | Administrator | Office Staff | Class Teacher | Subject Teacher |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Dashboard** | `/dashboard` | Visible | Visible | Visible | Visible |
| **Academic Years & Terms** | `/academic-years` | Visible (Full) | Visible (Read) | Hidden | Hidden |
| **Classes & Sections** | `/classes` | Visible (Full) | Visible (Read) | Hidden | Hidden |
| **Subjects & Class-Subjects**| `/subjects` | Visible (Full) | Visible (Read) | Hidden | Hidden |
| **Students Directory** | `/students` | Visible (Full) | Visible (Full) | Visible (Scoped)| Hidden |
| **Student Transfers** | `/students/transfers` | Visible (Full) | Visible (Full) | Hidden | Hidden |
| **Student CSV Import** | `/students/import` | Visible (Full) | Visible (Full) | Hidden | Hidden |
| **Teacher Assignments** | `/teacher-assignments`| Visible (Full) | Visible (Read) | Hidden | Hidden |
| **Assessments** | `/assessments` | Visible (Full) | Visible (Read) | Hidden | Hidden |
| **Mark Entry Grid** | `/marks` | Visible (Full) | Visible (Full) | Visible (Scoped)| Visible (Scoped)|
| **Mark Corrections** | `/marks/corrections` | Visible (Full) | Visible (Full) | Hidden | Hidden |
| **Attendance Roster** | `/attendance` | Visible (Full) | Visible (Full) | Visible (Scoped)| Hidden |
| **Report Generation** | `/reports` | Visible (Full) | Visible (Full) | Visible (Scoped)| Hidden |
| **Report Layout Settings** | `/reports/configurations`| Visible (Full)| Visible (Read) | Hidden | Hidden |
| **Calculation Settings** | `/calculation-settings`| Visible (Full)| Visible (Read) | Hidden | Hidden |
| **School Settings** | `/school-settings` | Visible (Full) | Hidden | Hidden | Hidden |
| **User Administration** | `/admin/users` | Visible (Full) | Hidden | Hidden | Hidden |
| **Audit Logs** | `/admin/audit-logs` | Visible (Full) | Hidden | Hidden | Hidden |

### 9.2 Class Teacher Scoped Visibility Rule
- For `Class Teacher`, navigation items for **Students**, **Mark Entry**, **Attendance**, and **Reports** render with contextual badges indicating their active assigned classroom (e.g. `Class 8-A`).
- If a Class Teacher holds no active assignment for the selected academic year, operational links display an empty-state guidance badge.

### 9.3 Subject Teacher Scoped Visibility Rule
- For `Subject Teacher`, navigation is strictly confined to **Dashboard** and **Mark Entry**.
- The navigation explicitly hides **Attendance**, **Reports**, **Student Transfers**, and **Administrative Configuration**.

---

## 10. Closed Academic Year UI Behavior

In strict compliance with BRD rules BR-054, BR-055, CL-003, and DEC-035, when an academic year has `status = 'closed'`, the UI dynamically adapts to communicate immutability while preserving authorized operational pathways.

### 10.1 Global State Banner
When viewing any screen under a closed academic year, a persistent non-dismissible warning callout is rendered beneath the page header:
```
+-------------------------------------------------------------------------------------------------+
| [!] Academic Year 2024–2025 is CLOSED.                                                          |
| Marks and attendance are finalized and read-only for teaching staff. Formal corrections require |
| administrative authorization with documented audit justifications.                              |
+-------------------------------------------------------------------------------------------------+
```

### 10.2 Role-Specific Operational Behaviors

| Feature Area | Teacher Experience (Class & Subject) | Administrator / Office Staff Experience |
| :--- | :--- | :--- |
| **Mark Entry Grid** | Input fields replaced with plain text or disabled inputs (`disabled tabindex="-1"`). Save button hidden. | Input fields display locked icon. Clicking opens the **Mark Correction Reason Modal** (`POST /marks/{mark}/correct`). |
| **Attendance Grid** | Inputs disabled. Update button hidden. Read-only summary displayed. | Attendance inputs disabled. Modifications require administrative intervention. |
| **Report Generation** | **Fully Enabled for Class Teachers** for their assigned classroom scope. Report generation and downloading remain authorized. | **Fully Enabled** across institutional scope. |
| **Report Downloads** | **Fully Enabled for Class Teachers** for generated reports matching their classroom scope. | **Fully Enabled** for all historical revisions. |
| **Student Transfers**| Prohibited and hidden for teachers. | **Authorized for Administrator and Office Staff** for operational placement management. |

---

## 11. Core Screen Architecture: Spreadsheet Mark Entry Grid

The Mark Entry interface (`/marks`) is the operational epicenter of the application. It is engineered to provide spreadsheet-class speed, error prevention, and unambiguous ternary state presentation.

```
+-------------------------------------------------------------------------------------------------+
| SPREADSHEET MARK ENTRY GRID — CONTEXT BAR                                                       |
| Academic Year: [ 2025-2026 v ] Class: [ Class 8 v ] Section: [ A v ] Subject: [ Mathematics v ] |
| Assessment: [ Unit Test 1 (Max: 50.00) v ]                                                      |
+-------------------------------------------------------------------------------------------------+
| [Legend: Blank = Incomplete | A = Absent (0 pts) | 0.00 = Valid Zero | Unsaved = Yellow Dot]    |
+-------------------------------------------------------------------------------------------------+
| #   | Roll No | Student Full Name        | Mark Value (/ 50.00) | Status Badge | Audit History  |
+-----+---------+--------------------------+----------------------+--------------+----------------+
| 1   | 101     | Aarav Sharma             | [  48.50           ] | [ Numeric  ] | Saved 10:45 AM |
| 2   | 102     | Ananya Patel             | [  A               ] | [ Absent   ] | Saved 10:45 AM |
| 3   | 103     | Devansh Gupta            | [  0.00            ] | [ Zero     ] | Saved 10:46 AM |
| 4   | 104     | Ishita Verma             | [                  ] | [ Blank    ] | Not entered    |
| 5   | 105     | Rohan Mehra              | [  55.00 !ERR      ] | [ Invalid  ] | > Max (50.00)  |
+-----+---------+--------------------------+----------------------+--------------+----------------+
| Actions: [ Discard Changes ]               [ Save All (Ctrl+S) ] | Status: 1 Error, 1 Unsaved   |
+-------------------------------------------------------------------------------------------------+
```

### 11.1 Ternary Mark Visual & Value Contract
In accordance with BR-009 to BR-014 and DEC-020, mark values are represented in three distinct states:

1. **Blank (`result_status = 'blank'`):**
   - **DOM Representation:** Empty input field (`value=""`).
   - **Semantic Meaning:** No result has been entered. Result is incomplete.
   - **Calculation Impact:** Incomplete. Blocks final report card generation (BR-082, BR-083).
   - **Visual Styling:** Neutral white background, subtle border, placeholder displaying `"—"`.
2. **Numeric (`result_status = 'numeric'`):**
   - **DOM Representation:** Formatted decimal string (e.g. `"45.50"`, `"0.00"`).
   - **Semantic Meaning:** Student completed the assessment and scored the specified points.
   - **Range Constraint:** $0.00 \le \text{mark\_value} \le \text{authoritative } \text{maximum\_marks}$ (supplied contextually by `assessment_applicability.maximum_marks`, BR-018, BR-019).
   - **Calculation Impact:** Contributes exact numeric value to sums and percentages.
   - **Visual Styling:** High-contrast text, right-aligned monospace font, green checkmark icon when saved.
   - **Zero Invariant:** A mark of `0` is a valid score and displays explicitly as `"0"` or `"0.00"`. It is never represented as blank (BR-010).
3. **Absent (`result_status = 'absent'`):**
   - **DOM Representation:** Explicit character `"A"` or `"a"`.
   - **Semantic Meaning:** Student was absent for the assessment.
   - **Calculation Impact:** Completed status. Contributes $0.00$ to calculations (BR-011, BR-012). The frontend passes `result_status: 'absent'` with `mark_value: null` to the server; the backend calculation service handles arithmetic participation.
   - **Visual Styling:** Centered text, subtle amber/red tinted pill background (`--color-danger-bg`), text colored `--color-danger`.

### 11.2 Keyboard Navigation Protocol
The mark grid module (`mark-entry-grid.js`) implements full spreadsheet keyboard mechanics via standard event listeners:

- **ArrowDown / Enter:** Commits the current cell's visual state, moves focus to the mark input in the immediately subsequent student row, and selects all text.
- **ArrowUp:** Moves focus to the mark input in the immediately preceding student row.
- **Tab / Shift+Tab:** Follows natural document tab order, moving forward or backward across interactive elements.
- **Shortcut `Ctrl+S` / `Cmd+S`:** Intercepts default browser save dialog and triggers the atomic batch save handler (`submitBatch()`).
- **Escape:** If a cell has unsaved modifications, reverts the cell to its last persisted value.

### 11.3 Contextual Maximum Marks & Batch Save Flow
1. **Contextual Maximum Marks:** Maximum marks are bound to `assessment_applicability`, not assessments globally (BR-018, BR-019). The grid wrapper receives the applicable maximum marks via `data-max-marks` from the server for the selected academic year, class, section, subject, and assessment.
2. **Change Tracking:** When the user alters a cell, a `data-dirty="true"` attribute is set on the row and input. A subtle yellow indicator dot appears on the left margin of the row.
3. **Client-Side Visual Validation (Non-Authoritative):** Upon blur, the script checks if the numeric value exceeds the contextual `data-max-marks`. If exceeded, the cell receives `aria-invalid="true"`, turns light red, and displays an inline warning. This is purely an immediate UX aid; the server remains the sole authority.
4. **Atomic Payload Construction:** Clicking **Save All** or pressing `Ctrl+S` aggregates only modified or active rows into the exact Phase 6.6 contract:
   ```json
   {
     "academic_year_id": 1,
     "class_id": 2,
     "section_id": 3,
     "subject_id": 5,
     "assessment_id": 4,
     "marks": [
       { "student_academic_record_id": 101, "result_status": "numeric", "mark_value": "48.50" },
       { "student_academic_record_id": 102, "result_status": "absent", "mark_value": null },
       { "student_academic_record_id": 104, "result_status": "blank", "mark_value": null }
     ]
   }
   ```
5. **Asynchronous Execution:** Submitted via `fetch()` to `POST /marks/batch-save` with `X-CSRF-TOKEN` and `Accept: application/json`.
6. **UI State Transitions:**
   - **Saving:** Save button transitions to disabled state with a spinner. Inputs temporarily set to `readonly`.
   - **Success (HTTP 200):** Dirty indicators clear. Inputs flash subtle green for $1200\text{ms}$. Toast alert displays *"Marks saved successfully."*
   - **Validation Error (HTTP 422):** Server returns specific row-level validation errors. Errant rows highlight in red; error messages render inline beneath the cell.

---

## 12. Attendance Grid Architecture

The attendance management interface (`/attendance`) captures classroom attendance totals required for term and final report cards (BR-048 to BR-053).

### 12.1 Attendance Table Columns
- **Roll Number:** Sorted in ascending order (`student_academic_records.roll_number`).
- **Student Full Name:** Master profile name (`students.student_name`).
- **Total Working Days:** Numeric integer input ($\ge 0$, `attendance.total_working_days`).
- **Days Attended:** Numeric integer input ($\ge 0$, `attendance.days_attended`).
- **Attendance Percentage:** Real-time calculated display column.

### 12.2 Zero Working Days Division Defense (CL-010)
To eliminate `NaN`, `Infinity`, or division-by-zero crashes:
$$\text{Display Percentage} = \begin{cases} \text{"N/A"} & \text{if } \text{Total Working Days} = 0 \\ \left( \frac{\text{Days Attended}}{\text{Total Working Days}} \times 100 \right)\% & \text{if } \text{Total Working Days} > 0 \end{cases}$$

### 12.3 Invariant Enforcement
- `Days Attended` cannot exceed `Total Working Days` (BR-052). If an input violates this rule, client validation instantly flags the cell with an error message (*"Days attended cannot exceed total working days"*), disabling the save button until rectified.
- **Role Scopes:** Class Teachers manage attendance for their assigned classroom (BR-049). Office Staff and Administrators retain broader operational access. Subject Teachers have zero attendance access.
- **No Invented Health Classifications:** The attendance UI presents only factual attendance figures; it does not invent subjective "health" classifications (such as Good, Poor, At Risk).

---

## 13. Student Management & CSV Import Architecture

### 13.1 Master Identity vs. Academic Placement Separation
The student detail view (`/students/{student}`) visually bifurcates immutable identity attributes from academic placement history in strict accordance with the database schema:

```
+-------------------------------------------------------------------------------------------------+
| STUDENT PROFILE                                                                                 |
| Student Full Name: Aarav Sharma                                                                 |
+-------------------------------------------------------------------------------------------------+
| ACADEMIC PLACEMENT HISTORY (Chronological Placement Records)                                    |
| Academic Year | Class | Section | Roll No | Placement Status    | Effective From | Effective To |
| 2024–2025     | 7     | A       | 102     | [ Internal Transfer]| 05-Jun-2024    | 14-Nov-2024  |
| 2024–2025     | 7     | B       | 115     | [ Active            ]| 15-Nov-2024    | 31-Mar-2025  |
| 2025–2026     | 8     | A       | 101     | [ Active            ]| 02-Jun-2025    | —            |
+-------------------------------------------------------------------------------------------------+
```

- **Master Student Identity (`students` table):** Contains `student_name` and `admission_number` (approved under DEC-072 as the unique student business identifier). Internal database primary key (`id`) is used solely for relational FK targeting and routing; `admission_number` is displayed as the primary user-facing business identifier.
- **Prohibition of Demographic Fields:** The UI strictly avoids displaying or collecting unsupported demographic attributes (e.g. date of birth, gender, parent/guardian names, admission date, address, phone, email, blood group, photo).
- **Academic Placement History (`student_academic_records` table):** Displays historical classroom allocations chronologically.
  - Statuses reflect the placement status enum: `active`, `internal_transfer`, `withdrawn`, `transferred_out` (BR-042).
  - Transfers between classes or sections close the previous record (`effective_to`) and create a new placement record from `effective_from` (BR-037). Historical marks remain permanently bound to the prior academic record (BR-038).

### 13.2 Student CSV Import Workflow
The import interface (`/students/import`) conforms to BR-069–072 as reconciled by DEC-072:

1. **Context & Matching Protocol (DEC-072):** The upload form captures Academic Year, Class, and Section context. CSV contains `admission_number,student_name,roll_number`.
   - New admission numbers create master students and classroom placement.
   - Existing admission numbers reuse the student master (without creating duplicates) and associate placement.
   - Name mismatches on existing admission numbers are rejected with row-level errors.
2. **Supported CSV Columns:** `admission_number`, `student_name`, `roll_number`. (Superseded by DEC-072; previous baseline omitted admission numbers).
3. **Execution & Partial Import Reporting (BR-071, BR-072):**
   - Partial imports are permitted: valid rows are inserted, while invalid rows are rejected.
   - Summary statistics display: Total Processed Rows, Successfully Created Rows, and Rejected Rows Count.
   - Detailed Rejection Table lists: CSV Row Number, Student Name, and exact relational reason (e.g. *"Row 14: Class '10-C' does not exist in academic year 2025-26"*, *"Row 22: Roll number 101 already exists in Class 8-A for academic year 2025-26"*).

---

## 14. Report Generation & Historical Revision Architecture

The report card module (`/reports`) is structured around student academic records, configuration templates, and immutable historical PDF revisions (BR-058 to BR-068, BR-080 to BR-084).

### 14.1 Contextual Report Action Matrix
When viewing the student report roster for a classroom, available actions reflect mark completion:

```
+-------------------------------------------------------------------------------------------------+
| REPORT CARD ROSTER: Class 8-A (Academic Year 2025–2026)                                         |
| Report Type: [ Final Report Card v ]                                                            |
+-----+---------+-------------------+--------------------+-----------------+----------------------+
| #   | Roll No | Student Name      | Mark Entry Status  | Active Revision | Available Actions    |
+-----+---------+-------------------+--------------------+-----------------+----------------------+
| 1   | 101     | Aarav Sharma      | [ Complete ]       | Revision 2      | [Preview] [Gen Rev]  |
| 2   | 102     | Ananya Patel      | [ Complete ]       | None (Pending)  | [Preview] [Generate] |
| 3   | 103     | Devansh Gupta     | [ Incomplete ]     | None (Blocked)  | [View Missing Marks] |
+-----+---------+-------------------+--------------------+-----------------+----------------------+
```

- **Completion Terminology:** Completion is labeled as `Complete` (or `5 / 5 Results Entered`) and `Incomplete` (or `4 / 5 Results Entered`). It is **never** presented as `100%` to prevent confusion with academic calculation percentages.
- **Prohibited Metrics:** The UI does not display overall annual percentages (BR-064), student rankings (BR-065), or grade bands (BR-067).

### 14.2 Missing Marks Guidance Modal
Clicking **[View Missing Marks]** opens an accessible modal detailing exactly which subjects or assessments remain blank (BR-080, BR-082):
```
+-------------------------------------------------------------------------------------------------+
| Cannot Generate Final Report Card for Devansh Gupta                                             |
+-------------------------------------------------------------------------------------------------+
| The final report card cannot be compiled because the following mandatory marks are missing:     |
|                                                                                                 |
| * Social Studies — Final Examination: Mark not entered (Blank)                                  |
|                                                                                                 |
| Note: If the student was absent for this examination, enter 'A' in the mark roster.              |
| Blank marks cannot be treated as zero.                                                          |
+-------------------------------------------------------------------------------------------------+
| [Close Dialog]                                                     [Go to Mark Entry: Soc Sci]  |
+-------------------------------------------------------------------------------------------------+
```

### 14.3 Immutable Historical Revision Viewer
Selecting **View Revisions** displays the append-only history of generated reports (`generated_reports` table, BR-059, BR-060):
```
+-------------------------------------------------------------------------------------------------+
| REPORT REVISION HISTORY: Aarav Sharma (Class 8-A — Final Report Card)                           |
+----------+-----------------------+---------------------+----------------------------------------+
| Revision | Generated Timestamp   | Generated By Staff  | Action                                 |
+----------+-----------------------+---------------------+----------------------------------------+
| Revision 1| 12-Mar-2026 14:22:01 | J. Smith (Teacher)  | [Download PDF]                         |
| Revision 2| 15-Mar-2026 09:15:30 | A. Davis (Admin)    | [Download PDF]                         |
+----------+-----------------------+---------------------+----------------------------------------+
```
- **Revision Invariant:** Regenerating creates a new row with an incremented `revision_number` (e.g. `Revision 3`).
- **No Premature PDF Metadata:** The UI displays factual revision data (`revision_number`, `generated_at`, `generated_by_user_id` mapped to user display name). It does not display premature file hashes (SHA-256) or file sizes, leaving storage implementation details to Phase 6.8.
- **Zero Modification Controls:** The UI provides no controls to edit, replace, or delete historical report revisions.

---

## 15. System Administration & Configuration Interfaces

### 15.1 Academic Terms Configuration
- Terms are completely dynamic (BR-032 to BR-036). The UI does not hardcode `"Term 1"`, `"Term 2"`, or `"Term 3"`.
- The configuration table allows creating arbitrary terms (e.g. *"Autumn Term"*, *"Spring Term"*, *"Annual Term"*) ordered by a numeric sequence input (`sequence_no`).

### 15.2 Calculation Settings Interface
The calculation settings screen (`/calculation-settings`) exposes only the two approved calculation algorithms (BR-025 to BR-027):
- **Method 1: Equal Average of Percentages**  
  $$\text{Final } \% = \frac{1}{N} \sum_{i=1}^N \left( \frac{\text{Obtained}_i}{\text{Max}_i} \times 100 \right)$$
- **Method 2: Aggregated Marks Ratio**  
  $$\text{Final } \% = \frac{\sum_{i=1}^N \text{Obtained}_i}{\sum_{i=1}^N \text{Max}_i} \times 100$$
- Arbitrary custom formula builders, manual weight distributions, ranking algorithms, and GPA grade engine configurations are strictly prohibited.

### 15.3 School Settings Interface
Exposes only attributes supported by the `school_settings` table and BRD:
- **School Name:** Text input (`school_settings.school_name`).
- **School Crest / Logo:** Image upload dropzone with client-side preview (`school_settings.school_logo_path`, BR-068).
- **Minimum Pass Mark:** Numeric decimal input representing the institutional pass threshold score (`school_settings.pass_mark`, default $0.00$, BR-066).
- **Prohibition of Unsupported Fields:** No registration number, affiliation board, address, phone, contact email, or mandatory image aspect ratio rules are imposed.

### 15.4 User Account Management Interface (Administrator Only)
Structured strictly around the `users` table:
- **Columns Displayed:** `Username` (`users.username`), `Display Name` (`users.display_name`), `Email` (`users.email`), `Role` (`roles.name`), `Status` (Active / Deactivated via `users.is_active`), and `Last Login` (`users.last_login_at`).
- **Destructive Action Safety:** Deactivation and password reset buttons trigger confirmation modals. Deactivation terminates active sessions immediately (DEC-032) while preserving all historical marks, audit trails, and generated reports intact. Hard delete controls do not exist.

### 15.5 Audit Log Inspection Interface (Administrator Only)
Structured strictly around the `audit_logs` table (BR-076 to BR-078):
- **Columns Displayed:** `Timestamp` (`audit_logs.created_at`), `Actor` (`users.display_name`), `Action` (`audit_logs.action`), `Entity Type` (`audit_logs.entity_type`), `Entity ID` (`audit_logs.entity_id`), `Description` (`audit_logs.description`), `IP Address` (`audit_logs.ip_address`), and Expandable JSON Diff (`before_data` vs `after_data`).
- **Action Scope:** Reflects full system activity (account changes, teacher assignments, student imports/edits, examination setup, school/class settings, calculation changes, mark changes, mark corrections, report/PDF generation, academic-year open/close, student transfers, login/logout). No artificial 4-verb taxonomy is enforced.
- **Immutability:** Zero edit, delete, or clear logs controls are provided (BR-078).

---

## 16. Classification of Computed & Derived Values

To maintain absolute architectural boundaries, every calculated value is strictly categorized:

| Value / Metric | Classification | Authoritative Source | Presentation Behavior |
| :--- | :--- | :--- | :--- |
| **Term Percentage** | Server Authoritative | `CalculationService` (Method 1 or Method 2) | Displayed to 2 decimal places (BR-030). JavaScript never calculates official percentages. |
| **Pass / Fail Status** | Server Authoritative | `CalculationService` vs `school_settings.pass_mark` | Displayed via semantic status badge. |
| **Applicable Max Marks** | Server Authoritative | `assessment_applicability.maximum_marks` | Transmitted via `data-max-marks` for immediate visual range feedback. |
| **Marksheet Completion** | Server Authoritative | `ReportGenerationService` (BR-080 to BR-084) | Displayed as Complete / Incomplete. Blocks PDF generation if incomplete. |
| **Attendance Percentage** | Presentation Derivation | Formatted on client/server from raw days | If working days $= 0$, displays `"N/A"` (CL-010). Otherwise $\frac{\text{attended}}{\text{total}} \times 100\%$. |
| **Client Range Feedback**| Non-Authoritative UX | JavaScript comparison against `data-max-marks` | Highlights cell in red if exceeded. Server re-validates independently upon save. |
| **Unsaved Row Indicator**| Presentation Derivation | DOM event listener (`input`) | Renders yellow dot on modified row margin. |

---

## 17. Blade Layout & Component Architecture

To guarantee code maintainability, frontend consistency, and rapid server rendering, UI elements are organized into an atomic Blade component catalog.

### 17.1 Layout Catalog (`resources/views/layouts/`)
- `app.blade.php`: The primary administrative shell layout containing HTML5 `<head>`, CSS links, Vite asset bundle directives, responsive topbar, sidebar component, main slot, toast alert mount point, and Vanilla JS initialization scripts (referenced via `<x-layouts.app>` or `@extends('layouts.app')`).
- `auth.blade.php`: Focused layout for login and mandatory first-login password reset pages, centered on screen with minimal branding (referenced via `<x-layouts.auth>` or `@extends('layouts.auth')`).
- `partials/sidebar.blade.php`: Multi-level navigation container executing role-aware link rendering via `@can` and active route highlights.
- `partials/topbar.blade.php`: Persistent header containing sidebar hamburger toggle, current academic year indicator, user profile trigger, and secure POST logout form.

### 17.2 UI System Components (`resources/views/components/ui/`)
- `<x-ui.button>`: Flexible button component supporting variants (`primary`, `secondary`, `danger`, `ghost`, `link`), sizes (`sm`, `md`, `lg`), loading state spinner, and icon slots.
- `<x-ui.card>`: Container surface with `<x-slot:header>`, body slot, and optional `<x-slot:footer>`.
- `<x-ui.table>`: Standardized responsive table wrapper with styled `<thead>`, alternating row hover states, and empty state fallback.
- `<x-ui.badge>`: Status pill component supporting semantic variants (`success`, `warning`, `danger`, `info`, `neutral`).
- `<x-ui.alert>`: Flash message container rendering dismissible banners with appropriate SVG status icons.
- `<x-ui.modal>`: Accessible dialog component with backdrop, keyboard focus trap, Escape listener, title slot, and action buttons.
- `<x-ui.empty-state>`: Centered callout rendering a descriptive SVG icon, heading, explanatory message, and optional primary action button.
- `<x-ui.page-header>`: Standardized page title header with breadcrumb trail and action button container.
- `<x-ui.loading-skeleton>`: Lightweight animated shimmer placeholder for data loading transitions.

### 17.3 Form System Components (`resources/views/components/forms/`)
- `<x-forms.input>`: Text, numeric, and date input component with attached `<label>`, required asterisk indicator, helper text, and automated `@error` message rendering.
- `<x-forms.select>`: Stylized select dropdown supporting optgroups and dependent cascading hooks.
- `<x-forms.checkbox>`: Accessible checkbox control with adjacent label and description text.
- `<x-forms.textarea>`: Multi-line text field for audit reasons and administrative notes.
- `<x-forms.file-input>`: Drag-and-drop file upload control for CSV rosters and school crest images.

### 17.4 Mark System Components (`resources/views/components/marks/`)
- `<x-marks.mark-cell>`: Specialized grid input cell embedding student academic record ID, roll number, student name, max marks, and current ternary state attributes.
- `<x-marks.status-legend>`: Informational bar explaining the visual distinction between Blank, Numeric 0, and Absent.

---

## 18. View Directory Catalog

All Blade views conform to the feature-grouped directory hierarchy defined in Phase 6.3:

```
resources/views/
├── layouts/
│   ├── app.blade.php
│   └── auth.blade.php
├── auth/
│   └── login.blade.php
├── dashboard/
│   ├── index.blade.php
│   ├── partials/admin-summary.blade.php
│   └── partials/teacher-summary.blade.php
├── academic/
│   ├── years/
│   │   ├── index.blade.php
│   │   └── create.blade.php
│   ├── terms/
│   │   └── index.blade.php
│   ├── classes/
│   │   └── index.blade.php
│   ├── sections/
│   │   └── index.blade.php
│   ├── subjects/
│   │   └── index.blade.php
│   └── class-subjects/
│       └── index.blade.php
├── students/
│   ├── index.blade.php
│   ├── show.blade.php
│   ├── create.blade.php
│   ├── edit.blade.php
│   ├── import.blade.php
│   └── transfers/
│       └── create.blade.php
├── teachers/
│   ├── assignments/
│   │   └── index.blade.php
│   └── my-classes/
│       └── index.blade.php
├── assessments/
│   ├── index.blade.php
│   └── applicability/
│       └── index.blade.php
├── marks/
│   ├── index.blade.php
│   ├── corrections/
│   │   └── index.blade.php
│   └── partials/
│       ├── grid-table.blade.php
│       └── correction-modal.blade.php
├── attendance/
│   └── index.blade.php
├── calculations/
│   └── index.blade.php
├── reports/
│   ├── index.blade.php
│   ├── preview.blade.php
│   ├── configurations/
│   │   └── index.blade.php
│   └── revisions/
│       └── index.blade.php
├── settings/
│   └── school.blade.php
├── admin/
│   ├── users/
│   │   ├── index.blade.php
│   │   └── create.blade.php
│   └── audit-logs/
│       └── index.blade.php
└── errors/
    ├── 403.blade.php
    ├── 404.blade.php
    ├── 419.blade.php
    ├── 422.blade.php
    ├── 429.blade.php
    └── 500.blade.php
```

---

## 19. Plain CSS Modular Architecture

Styling is organized into modular CSS files located in `resources/css/`. Vite compiles and minifies these files into a single optimized stylesheet with zero external framework dependencies.

```
resources/css/
├── app.css                 /* Master entrypoint importing tokens, base, layout, components */
├── tokens.css              /* Design tokens (colors, typography, spacing, shadows, radius) */
├── base.css                /* CSS reset, element defaults, print base */
├── layout.css              /* Grid shell, topbar, sidebar, page wrappers */
├── components.css          /* Buttons, forms, tables, cards, badges, alerts, modals */
└── modules/
    ├── mark-grid.css       /* Specialized spreadsheet grid styling & cell animations */
    └── report-preview.css  /* Screen preview styling for report cards */
```

### 19.1 Master Entrypoint (`resources/css/app.css`)
```css
@import './tokens.css';
@import './base.css';
@import './layout.css';
@import './components.css';
@import './modules/mark-grid.css';
@import './modules/report-preview.css';
```

### 19.2 Design Tokens Definition (`resources/css/tokens.css`)
```css
:root {
  /* Brand Colors */
  --color-primary: #2563EB;
  --color-primary-hover: #1D4ED8;
  --color-primary-active: #1E40AF;
  --color-primary-soft: #EFF6FF;

  /* Neutrals & Surfaces */
  --color-page-bg: #F8FAFC;
  --color-surface: #FFFFFF;
  --color-surface-muted: #F1F5F9;
  --color-border: #E2E8F0;
  --color-border-hover: #CBD5E1;

  /* Typography */
  --color-text: #0F172A;
  --color-text-secondary: #475569;
  --color-text-muted: #64748B;
  --font-sans: 'Figtree', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  --font-mono: 'JetBrains Mono', 'SFMono-Regular', Menlo, Monaco, Consolas, monospace;

  /* Semantic Feedback */
  --color-success: #16A34A;
  --color-success-bg: #F0FDF4;
  --color-success-border: #BBF7D0;
  --color-warning: #D97706;
  --color-warning-bg: #FFFBEB;
  --color-warning-border: #FDE68A;
  --color-danger: #DC2626;
  --color-danger-bg: #FEF2F2;
  --color-danger-border: #FECACA;
  --color-info: #0284C7;
  --color-info-bg: #F0F9FF;
  --color-info-border: #BAE6FD;

  /* Spacing Scale */
  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-5: 20px;
  --space-6: 24px;
  --space-8: 32px;
  --space-10: 40px;
  --space-12: 48px;

  /* Radii & Shadows */
  --radius-sm: 4px;
  --radius-md: 6px;
  --radius-lg: 8px;
  --radius-xl: 12px;
  --radius-full: 9999px;
  --shadow-sm: 0 1px 2px 0 rgba(15, 23, 42, 0.05);
  --shadow-card: 0 1px 3px 0 rgba(15, 23, 42, 0.08), 0 1px 2px -1px rgba(15, 23, 42, 0.04);
  --shadow-elevated: 0 4px 6px -1px rgba(15, 23, 42, 0.08), 0 2px 4px -2px rgba(15, 23, 42, 0.04);
  --shadow-modal: 0 20px 25px -5px rgba(15, 23, 42, 0.1), 0 8px 10px -6px rgba(15, 23, 42, 0.05);
  --shadow-focus: 0 0 0 3px rgba(37, 99, 235, 0.2);
}
```

---

## 20. Vanilla JavaScript Progressive Enhancement Architecture

Client-side behavior is structured into isolated, native ES modules located in `resources/js/`.

```
resources/js/
├── app.js                          /* Master script bootstrapping modules via data attributes */
├── modules/
│   ├── mark-entry-grid.js          /* Keyboard navigation, dirty tracking, batch fetch */
│   ├── attendance-grid.js          /* Ratio calculation, 0/0 N/A defense, bounds */
│   ├── cascading-dropdowns.js      /* Year -> Class -> Section -> Subject -> Assessment */
│   ├── modal-controller.js         /* Keyboard trap, accessible dialog show/hide */
│   ├── report-progress.js          /* Generation trigger, spinner, download redirection */
│   └── student-importer.js         /* File validation, CSV preview parsing */
└── utilities/
    ├── dom.js                      /* Element selection, event delegation helpers */
    └── csrf.js                     /* Automated CSRF token extraction */
```

### 20.1 Master Bootstrapper (`resources/js/app.js`)
```javascript
import { initMarkEntryGrid } from './modules/mark-entry-grid.js';
import { initAttendanceGrid } from './modules/attendance-grid.js';
import { initCascadingDropdowns } from './modules/cascading-dropdowns.js';
import { initModalController } from './modules/modal-controller.js';
import { initReportProgress } from './modules/report-progress.js';

document.addEventListener('DOMContentLoaded', () => {
  if (document.querySelector('[data-mark-grid]')) {
    initMarkEntryGrid();
  }
  if (document.querySelector('[data-attendance-grid]')) {
    initAttendanceGrid();
  }
  if (document.querySelector('[data-cascading-select]')) {
    initCascadingDropdowns();
  }
  if (document.querySelector('[data-modal]')) {
    initModalController();
  }
  if (document.querySelector('[data-report-generation]')) {
    initReportProgress();
  }
});
```

### 20.2 Mark Entry Grid Module (`resources/js/modules/mark-entry-grid.js`)
Handles keyboard traversal, dirty state tracking, and atomic batch payload submission. JavaScript preserves decimal strings and never acts as an authoritative calculation engine:

```javascript
import { getCsrfToken } from '../utilities/csrf.js';

export function initMarkEntryGrid() {
  const grid = document.querySelector('[data-mark-grid]');
  if (!grid) return;

  const saveButton = document.querySelector('[data-save-marks]');
  const inputs = Array.from(grid.querySelectorAll('input[data-mark-input]'));

  // Keyboard navigation
  grid.addEventListener('keydown', (e) => {
    if (!e.target.matches('input[data-mark-input]')) return;
    const currentIndex = inputs.indexOf(e.target);

    if (e.key === 'ArrowDown' || e.key === 'Enter') {
      e.preventDefault();
      if (currentIndex < inputs.length - 1) {
        inputs[currentIndex + 1].focus();
        inputs[currentIndex + 1].select();
      }
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      if (currentIndex > 0) {
        inputs[currentIndex - 1].focus();
        inputs[currentIndex - 1].select();
      }
    } else if ((e.ctrlKey || e.metaKey) && e.key === 's') {
      e.preventDefault();
      submitBatch();
    }
  });

  // Cell state change & visual validation (strictly non-authoritative client feedback)
  grid.addEventListener('input', (e) => {
    const input = e.target;
    if (!input.matches('input[data-mark-input]')) return;

    input.dataset.dirty = 'true';
    input.closest('tr').dataset.dirty = 'true';

    // 1. Trim input
    const val = input.value.trim();
    const upperVal = val.toUpperCase();

    // 2. Recognize A/a as absent
    if (upperVal === 'A') {
      input.value = 'A';
      updateCellStatus(input, 'absent');
    // 3. Recognize empty input as blank
    } else if (val === '') {
      updateCellStatus(input, 'blank');
    } else {
      // 4 & 5. Validate numeric strings strictly lexically: non-empty digits with optional decimal point and up to 2 places.
      // Rejects malformed strings: "45abc", "12.3abc", "1e5", "+", "-", ".", "1.", ".5"
      const decimalRegex = /^\d+(?:\.\d{1,2})?$/;
      if (!decimalRegex.test(val)) {
        updateCellStatus(input, 'invalid');
      } else {
        // 6, 7 & 8. Exact decimal string preserved; never convert to floating-point number.
        // The server remains the sole authority for mark validity, max marks bounds, calculations, completion, and persistence.
        updateCellStatus(input, 'numeric');
      }
    }
  });

  async function submitBatch() {
    const dirtyInputs = inputs.filter(i => i.dataset.dirty === 'true');
    if (dirtyInputs.length === 0) return;

    const payload = {
      academic_year_id: parseInt(grid.dataset.academicYearId),
      class_id: parseInt(grid.dataset.classId),
      section_id: parseInt(grid.dataset.sectionId),
      subject_id: parseInt(grid.dataset.subjectId),
      assessment_id: parseInt(grid.dataset.assessmentId),
      marks: dirtyInputs.map(i => {
        const val = i.value.trim().toUpperCase();
        if (val === 'A') {
          return { student_academic_record_id: parseInt(i.dataset.recordId), result_status: 'absent', mark_value: null };
        } else if (val === '') {
          return { student_academic_record_id: parseInt(i.dataset.recordId), result_status: 'blank', mark_value: null };
        } else {
          // Preserved as validated decimal string; backend remains authoritative for numeric casting and bounds
          return { student_academic_record_id: parseInt(i.dataset.recordId), result_status: 'numeric', mark_value: val };
        }
      })
    };

    saveButton.disabled = true;
    saveButton.textContent = 'Saving...';

    try {
      const response = await fetch('/marks/batch-save', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      });

      if (response.ok) {
        dirtyInputs.forEach(i => {
          i.dataset.dirty = 'false';
          i.closest('tr').dataset.dirty = 'false';
        });
        showToast('Marks saved successfully.', 'success');
      } else {
        const err = await response.json();
        showToast(err.message || 'Error saving marks.', 'danger');
      }
    } catch (e) {
      showToast('Network error while saving marks.', 'danger');
    } finally {
      saveButton.disabled = false;
      saveButton.textContent = 'Save All';
    }
  }

  saveButton?.addEventListener('click', submitBatch);
}
```

---

## 21. Modal Dialog Controller & Accessibility Architecture

Modals are managed by a centralized, accessible controller (`modal-controller.js`).

### 21.1 Accessibility Requirements
1. **Focus Trap:** When a modal opens, focus moves automatically to the first interactive element inside the dialog. Pressing `Tab` cycles focus strictly within the modal container.
2. **Keyboard Dismissal:** Pressing `Escape` closes the modal and returns focus to the trigger element that initiated the opening.
3. **ARIA Attributes:** Modals contain `role="dialog"`, `aria-modal="true"`, and `aria-labelledby="modal-title-id"`.
4. **Body Scroll Lock:** The document body receives `overflow: hidden` while a modal is active to prevent background scrolling.

---

## 22. Cascading Context Selectors Architecture

For workflows requiring dependent context selections (`Academic Year` $\rightarrow$ `Class` $\rightarrow$ `Section` $\rightarrow$ `Subject` $\rightarrow$ `Assessment`), `cascading-dropdowns.js` provides dynamic option population.

### 22.1 Client-Server Contract
- Dropdown elements declare target dependencies via data attributes:
  ```html
  <select data-cascading-select data-target="#class-select" data-endpoint="/academic-years/{id}/classes">
  ```
- Upon selection change, the script dispatches a `fetch()` request to the endpoint.
- While fetching, child dropdowns are disabled and display a *"Loading..."* placeholder.
- Upon receiving options, child selects are populated and re-enabled.

> [!CAUTION]
> Populating options in a client dropdown **does not confer authorization**. When the user submits the chosen context, the backend `TeacherAssignmentService` and Form Requests rigorously verify that the teacher has an active assignment for that specific class, section, and subject.

---

## 23. Responsive Breakpoint & Mobile Adaptation Strategy

Administrative tasks are desktop-first, but all interfaces adapt seamlessly down to mobile viewports:

| Breakpoint | Target Viewports | Layout & Component Adaptation |
| :--- | :--- | :--- |
| **Desktop / Wide** ($\ge 1024\text{px}$) | $1440\text{px}$, $1280\text{px}$, $1024\text{px}$ | Persistent expanded sidebar ($260\text{px}$), multi-column card layouts, high-density data tables with full inline actions. |
| **Tablet** ($768\text{px} - 1023\text{px}$) | $768\text{px}$, $820\text{px}$ | Compact sidebar icon mode ($68\text{px}$), horizontally scrollable data grids with pinned left columns (`Roll No`, `Student Name`), 2-column form grids. |
| **Mobile** ($< 768\text{px}$) | $480\text{px}$, $390\text{px}$, $360\text{px}$ | Off-canvas drawer sidebar with backdrop, stacked single-column form controls, horizontally scrollable mark tables with responsive viewport indicators, touch-friendly tap targets ($\ge 44\text{px}$). |

---

## 24. Accessibility & WCAG 2.1 AA Compliance

The Blade and CSS architecture implements rigorous accessibility standards:

1. **Color Contrast Verification Standards:**
   - All normal body copy and table text must satisfy the WCAG 2.1 AA requirement of $\ge 4.5:1$ contrast against surface backgrounds.
   - Large text ($\ge 18\text{pt}$ or bold $\ge 14\text{pt}$) and active user interface components (such as form control borders and button focus rings) must satisfy $\ge 3:1$ contrast.
   - The design palette token definitions must be verified using automated accessibility linters during the asset build step to ensure compliance across all interactive states.
2. **Visible Keyboard Focus:**
   - Custom CSS reset applies an accessible focus ring across all interactive controls:
     ```css
     :focus-visible {
       outline: 2px solid var(--color-primary);
       outline-offset: 2px;
       box-shadow: var(--shadow-focus);
     }
     ```
3. **Non-Color State Indicators:**
   - Statuses are never conveyed solely by color. The mark entry grid supplements color cues with explicit text badges (`Numeric`, `Absent`, `Blank`), text values (`A`, `0.00`), and SVG icons.
4. **Accessible Form Feedback:**
   - Errant inputs link to error strings via `aria-describedby="field-error-id"`, ensuring screen readers announce validation failures upon focus.

---

## 25. UX Writing & Professional Microcopy Standards

All user-facing copy maintains a calm, professional, and clear institutional tone:

| Context | Prohibited Casual Phrasing | Approved Professional Microcopy |
| :--- | :--- | :--- |
| **Marks Saved** | *"Awesome! Marks saved!"* | *"Marks saved successfully."* |
| **Missing Marks**| *"Oops! You forgot some marks!"* | *"Report card cannot be generated. Required marks are incomplete."* |
| **Closed Year** | *"Locked out! Year is closed."* | *"Academic Year 2024–2025 is closed. Marks and attendance are read-only."* |
| **Absent State** | *"Student skipped test"* | *"Absent (A) — Recorded with 0 points."* |
| **403 Forbidden**| *"No way! You can't see this."* | *"You do not have permission to access this resource."* |
| **Account Reset**| *"Boom! Password wiped."* | *"Staff password reset successfully. Password change required upon first login."* |

---

## 26. Prohibited Frontend Approaches & Framework Drift

To prevent architectural degradation, the following practices are strictly prohibited:

- ❌ **NO CSS Frameworks:** Do NOT introduce Tailwind CSS, Bootstrap, Bulma, Foundation, or any CSS utility framework.
- ❌ **NO JavaScript UI Frameworks:** Do NOT introduce React, Vue, Angular, Svelte, Inertia.js, Livewire, Alpine.js, or jQuery.
- ❌ **NO SPA Architecture:** Do NOT convert routes into client-side virtual routing or client-rendered shells.
- ❌ **NO Inline JavaScript:** Do NOT write inline `onclick=`, `onchange=`, or `<script>` tags in Blade templates. All behavior must reside in modular scripts using event listeners and data attributes.
- ❌ **NO Frontend Calculation Authority:** JavaScript must never be treated as the final authority for term percentages, attendance ratios, or pass/fail determinations. Server-side services remain authoritative.
- ❌ **NO Batch PDF Generation UI:** Do NOT build UI controls for batch zip downloads or classroom PDF compilation (deferred to Phase 6.8). Report generation is strictly per-student.
- ❌ **NO Unapproved Domain Features:** Do NOT invent ranking toggles, student GPA calculators, parent portals, or payment gateways.
- ❌ **NO Admission Numbers or Demographic Inventions (Pre-Phase 10):** Do NOT invent dates of birth, parent names, or student demographic fields. *(Note: `admission_number` was approved as the student business identifier under DEC-072 in Phase 10).*

---

## 27. Architectural Traceability Matrix

| BRD Requirement / Handover Rule | Domain Meaning & Scope | Architectural Blueprint Section | Enforcing Component / File | Server-Side Authority |
| :--- | :--- | :--- | :--- | :--- |
| **BR-001 to BR-008** | Teacher & Staff mark permissions; latest saved mark authoritative | Section 9, 11 | `<x-layouts.sidebar>`, `<x-marks.mark-cell>` | `MarkPolicy`, `MarkEntryService` |
| **BR-009 to BR-014** | Ternary mark states: `blank` (incomplete), `numeric` ($0 \le m \le \text{max}$), `absent` (`A`, 0 pts) | Section 11.1, 16 | `<x-marks.mark-cell>`, `<x-marks.status-legend>` | `chk_marks_result_consistency`, `MarkValueCast` |
| **BR-015 to BR-024** | Dynamic assessments, flexible names, applicability max marks, display vs calculation | Section 11, 15.1 | `resources/views/assessments/` | `AssessmentApplicabilityService` |
| **BR-025 to BR-031** | Calculation Method 1 & Method 2, 2 decimal places, decimal marks allowed | Section 15.2, 16 | `resources/views/calculations/` | `CalculationService` |
| **BR-032 to BR-036** | Dynamic terms count, flexible names, ascending sequence | Section 15.1 | `resources/views/academic/terms/` | `TermService`, `uk_terms_year_seq` |
| **BR-037 to BR-047** | Student placement history, transfers create new placements, roll number unique | Section 13.1 | `resources/views/students/show.blade.php` | `StudentPlacementService`, `uk_sar_year_class_section_roll` |
| **BR-048 to BR-053** | Attendance (days attended $\le$ total working days, 1 entry/term) | Section 12 | `resources/views/attendance/` | `AttendanceService`, `chk_attendance_days_within_total` |
| **BR-054 to BR-057** | Academic year lifecycle; Teachers read-only on closure; Admin/Staff corrections | Section 10 | `components/layouts/app.blade.php` | `AcademicYearPolicy`, `MarkCorrectionService` |
| **BR-058 to BR-068** | Report cards, revisions, school logo, pass/fail, no annual %, no rank | Section 14, 15.3 | `resources/views/reports/` | `ReportGenerationService`, `ReportPolicy` |
| **BR-069 to BR-072** | CSV import: no auto-matching, partial import reporting, accepted/rejected rows | Section 13.2 | `resources/views/students/import.blade.php` | `StudentImportService` |
| **BR-073 to BR-075** | Deactivation terminates sessions; Administrator-only account management | Section 15.4 | `resources/views/admin/users/` | `EnsureUserIsActive`, `UserPolicy` |
| **BR-076 to BR-078** | Activity / audit log across all major events; Administrator-only; immutable | Section 15.5 | `resources/views/admin/audit-logs/` | `AuditLogPolicy`, immutable PostgreSQL |
| **BR-080 to BR-084** | Marksheet completion: all required valid results, A is complete, blank incomplete | Section 14.1, 14.2 | `resources/views/reports/index.blade.php` | `ReportGenerationService::validateCompletion` |
| **CL-007, CL-008** | Closed year teacher read-only; Class Teacher report generation preserved | Section 10.2 | `views/marks/`, `views/reports/` | `ReportPolicy`, `MarkPolicy` |
| **CL-009** | Explicit UI warning that re-import is not an update/matching operation | Section 13.2 | `resources/views/students/import.blade.php` | `StudentImportService` |
| **CL-010** | Zero working days attendance division defense: display `"N/A"` | Section 12.2, 16 | `resources/views/attendance/`, `attendance-grid.js` | `AttendanceService` |
| **DEC-020** | Mark ternary state model (`blank`, `numeric`, `absent`) | Section 11.1 | `mark-entry-grid.js`, `mark-grid.css` | `MarkValueCast` |
| **DEC-035** | Role-aware closed academic year authorization matrix | Section 10.2 | `app.blade.php`, `grid-table.blade.php` | `MarkPolicy`, `AttendancePolicy` |
| **DEC-038** | Report card generation & download scoped authority (Admin, Staff, Class Teacher) | Section 14 | `resources/views/reports/index.blade.php` | `ReportPolicy@generate`, `ReportPolicy@download` |
| **DEC-043** | Atomic batch mark save protocol | Section 11.3 | `mark-entry-grid.js`, `MarkController` | `MarkEntryService::saveBatch` (DB::transaction) |
| **DEC-045** | Modern light-themed SaaS design token architecture (`tokens.css`) | Section 5, 19.2 | `resources/css/tokens.css` | Vite asset compilation |
| **DEC-046** | Modular Plain CSS & zero framework styling architecture | Section 19 | `resources/css/app.css` | Vite CSS bundling |
| **DEC-047** | Atomic Blade component tree & layout composition standard | Section 17 | `resources/views/components/` | Blade compiler |
| **DEC-048** | Vanilla JavaScript progressive enhancement & event-driven architecture | Section 20 | `resources/js/app.js` | Client ES2024 runtime |
| **DEC-072** | Student admission number unique business identifier & CSV import identity reconciliation | Section 13 | `Student`, `StudentImportService` | `students.admission_number`, `uk_students_admission_number` |

---

## 28. Completion Verification & Sign-Off Checklist

Before proceeding to Phase 6.8, the frontend integration architecture has been audited and verified against all project constraints:

- [x] **No Database Schema Changes:** Relational 23-table schema remains strictly untouched. Zero UI tables, permission columns, or role flags introduced.
- [x] **No Admission Number (Pre-Phase 10 Baseline):** Admission number was excluded from Phase 6 baseline; formally superseded by DEC-072 in Phase 10 with `students.admission_number` (`VARCHAR(50) NOT NULL UNIQUE`).
- [x] **Student Master Profile Reflects Backend:** Master profile represents supported student identity attributes (`students.student_name`, `students.admission_number`). Internal ID is not treated as a business identifier.
- [x] **Student Academic Placement History Accurately Modeled:** Placement records map 1-to-1 to `student_academic_records` (`academic_year_id`, `class_id`, `section_id`, `roll_number`, `status`, `effective_from`, `effective_to`).
- [x] **Student Import Aligned with Business Rules:** Re-import creates new student records with zero automatic matching; prominent warning callout included; accepted/rejected rows reported with reasons.
- [x] **Numeric Marks Bounded by Contextual Max Marks:** Removed universal 100-mark assumption; max marks are contextual per `assessment_applicability` ($0.00 \le \text{mark} \le \text{max\_marks}$).
- [x] **Ternary Mark Representation Upheld:** Blank, Numeric 0, and Absent states visually differentiated without coercing blanks to zero. Frontend does not define calculation arithmetic.
- [x] **Attendance UI Free of Inventions:** Removed invented "attendance health" classifications and "Apply to all" batch-fill features. Implemented division-by-zero defense ($0/0 \rightarrow \text{"N/A"}$).
- [x] **Report Roster Distinguishes Completion from Percentages:** Replaced confusing "100%" completion label with "Complete". Excluded annual percentage, rank, and grade bands.
- [x] **Report Revisions Treated as Immutable:** Labeled as "Revision 1", "Revision 2", "Generate New Revision", and "Download". Zero edit, replace, or delete controls exist.
- [x] **Premature PDF Metadata Removed:** File hashes (SHA-256) and file-size requirements excluded from UI specification.
- [x] **School Settings Matches Schema:** Limited strictly to `school_name`, `school_logo_path`, and `pass_mark`. Removed registration number, affiliation board, address, phone, and email.
- [x] **User Management Matches Schema:** Limited to `username`, `display_name`, `email`, `role_id`, `is_active`, and `last_login_at`.
- [x] **Audit Log Matches Schema:** Removed invented 4-verb taxonomy; displays full activity event scope per BR-076. Immutable with zero delete/edit controls.
- [x] **Role-Aware Navigation & Permissions Preserved:** Administrator and Office Staff retain operational access; Class Teachers scoped to assigned classrooms; Subject Teachers denied report generation/download and attendance.
- [x] **Closed Academic Year State Respected:** Teachers read-only for marks and attendance; Class Teachers retain report generation and download for assigned classrooms; Admin/Staff corrections supported.
- [x] **Dynamic Terms & Configurations Supported:** Dynamic term count, flexible names, sequence ordering, and contextual max marks supported.
- [x] **Zero Framework Drift:** Plain Vanilla CSS and Vanilla JavaScript specified. Zero Tailwind, Bootstrap, React, Vue, Livewire, or Alpine dependencies.
- [x] **Light Theme Enforced:** Comprehensive light theme palette documented with zero dark mode styles.
- [x] **Server Authority Preserved:** Blade renders complete HTML; JavaScript acts strictly as an ergonomic micro-interaction layer with zero calculation or security authority.
- [x] **WCAG 2.1 AA Conformance & CSP Level 3 Compatibility:** Contrast ratios, visible focus indicators, keyboard traps, aria descriptions, and zero inline scripts enforced.
