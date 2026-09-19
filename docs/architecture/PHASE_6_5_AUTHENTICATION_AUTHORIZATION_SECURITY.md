# PHASE 6.5 — AUTHENTICATION, AUTHORIZATION & SECURITY ARCHITECTURE
## Comprehensive Access Control, Teacher Scope Resolution & Threat Model Blueprint

**Project:** School Examination Marks and Report Card Management System  
**Document Version:** 1.0  
**Phase:** 6.5 — Authentication, Authorization & Security Architecture  
**Status:** Approved & Authoritative Architectural Specification  

---

## 1. Purpose

This document defines the authoritative authentication, contextual authorization, and system-wide security architecture for the **School Examination Marks and Report Card Management System**.

The primary objective of Phase 6.5 is to establish a rock-solid, multi-layered security model that guarantees:
- Secure, session-based web authentication for all system actors without external or client-side token overhead.
- An immediate access revocation mechanism for deactivated user accounts that preserves historical records and audit integrity.
- A strict separation between **coarse role membership** and **contextual teacher authorization scope**.
- A deterministic, multi-assignment scope resolution engine that evaluates the complete active assignment set for teachers holding multiple classroom responsibilities.
- Server-side authorization gating for all academic operations (marks entry, mark corrections, attendance, report generation, report downloads, curriculum changes, user management, and audit inspection).
- A role-aware security policy governing open versus closed academic year lifecycles.
- Comprehensive defense against web vulnerabilities, including IDOR, privilege escalation, mass assignment, brute-force credential stuffing, CSRF, and session hijacking.
- Complete adherence to the principle that **client-side controls (hidden buttons, disabled fields, frontend checks) are purely for user experience and hold zero security authority**.

---

## 2. Relationship to Previous Phases

Phase 6 progresses through sequential, disciplined architecture blueprints:

```
Phase 6.1: Technology Baseline & Architecture Principles (Approved)
  - PHP 8.4+, Laravel 13, PostgreSQL 18.4, Blade, Vanilla JS, Plain CSS, Vite.
  - Session authentication, thin controllers, immutable audit logs.
      ↓
Phase 6.2: Domain Model & Eloquent Architecture (Approved)
  - 23 Eloquent models, 85 relationships, mutability tiers, historical placement.
      ↓
Phase 6.3: Laravel Project / Folder Structure (Approved & Corrected)
  - Physical directory tree, canonical services, custom casts, view hierarchy.
      ↓
Phase 6.4: PostgreSQL + Eloquent Integration Architecture & Decision Ledger (Approved)
  - Data types, PDO connection, constraint responsibilities, transactions, drift prevention.
      ↓
Phase 6.5: Authentication, Authorization & Security Architecture (Current Step)
  - Session lifecycle, active user guards, multi-assignment teacher scope algorithms, Policies.
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

Phase 6.5 defines the security contracts and authorization rules that will be bound to routes and controllers in Phase 6.6.

---

## 3. Authoritative Sources

All security specifications in this blueprint strictly derive from the project's source-of-truth hierarchy:

1. **BRD V1.3** (`BRD/School_Examination_Marksheet_Requirements_v1_3.docx`) — Foundational role definitions (BR-030 to BR-034), mark editing rules (BR-010 to BR-016), and clarification notes (CL-001 to CL-011).
2. **Finalized Business Rules & Handover Decisions** — Authoritative clarifications on role scopes, multi-assignment teachers, and closed-year permissions.
3. **Approved 23-Table Relational Design** (`DB design/DB-table-definitons.txt`) — Schema entities for `roles`, `users`, and `teacher_assignments`.
4. **Approved ERD** (`DB design/mermaid-diagram.png`) — Relational cardinalities governing access control.
5. **Validated PostgreSQL 18 Implementation** (`database/Postgres/schema.sql`, `seed.sql`) — Tables `roles`, `users`, `teacher_assignments`, and their CHECK constraints.
6. **Technology Baseline** (`docs/architecture/PHASE_6_1_TECHNOLOGY_BASELINE.md`)
7. **Domain & Eloquent Architecture** (`docs/architecture/PHASE_6_2_LARAVEL_APPLICATION_ARCHITECTURE.md`)
8. **Project Folder Structure** (`docs/architecture/PHASE_6_3_LARAVEL_PROJECT_FOLDER_STRUCTURE.md`)
9. **PostgreSQL + Eloquent Integration Architecture** (`docs/architecture/PHASE_6_4_POSTGRESQL_ELOQUENT_INTEGRATION_ARCHITECTURE.md`)
10. **Project Decision Ledger** (`docs/decisions.md`, DEC-001 through DEC-030)

---

## 4. Fundamental Security Principles

The application architecture enforces nine mandatory security principles:

1. **Client-Side Controls Are Not Security Controls:**  
   Disabled inputs, hidden fields, conditional Blade rendering, and JavaScript checks exist solely to guide the user. Every protected action must be independently authorized on the server.
2. **Role Is Not Scope:**  
   A role identifies a user's broad system classification (e.g. `Teacher`). It does **not** grant access to any specific classroom or subject. Teacher permissions are resolved dynamically from active `teacher_assignments` records.
3. **Server-Side Contextual Authorization:**  
   Every state-changing mutation and sensitive view request must verify that the authenticated actor possesses legitimate authority over the exact `academic_year_id`, `class_id`, `section_id`, `subject_id`, and `assessment_id` identified in the request.
4. **Immediate Deactivation Access Revocation:**  
   Setting `users.is_active = FALSE` must instantly terminate the user's access across all active browser sessions while preserving historical data and audit trails.
5. **No Cascading Deletion of Historical Records:**  
   User accounts are never hard-deleted. Historical marks, audit trails, and report generation metadata remain permanently linked to the historical user ID.
6. **Object-Level IDOR Defense:**  
   Route parameters (e.g. `/marks/{id}`) must never be trusted without verifying the entity's complete relational path against the user's authorized scope.
7. **Immutable Audit Trails for Security Events:**  
   All administrative actions, authentication attempts, mark modifications, and configuration changes write append-only records to `audit_logs`.
8. **Least-Privilege Administrative Access:**  
   Office Staff receive broad academic operational access but are strictly barred from user account administration, password resets, and audit log inspection.
9. **Defense in Depth:**  
   Security is enforced across four distinct layers: Network/Headers $\rightarrow$ HTTP Middleware $\rightarrow$ Form Requests $\rightarrow$ Laravel Policies/Gates $\rightarrow$ Service-Layer Invariant Checks.

---

## 5. Authentication Architecture

The application implements a native, stateful, session-based web authentication model adhering to standard Laravel conventions (`web` guard).

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           AUTHENTICATION FLOW                               │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
                                       ▼
                     HTTP POST /login (username, password)
                                       │
                                       ▼
                   [App\Http\Requests\Auth\LoginRequest]
                   - Rate limiting check (5 attempts / min)
                   - Required field validation
                                       │
                                       ▼
                       Authenticate Against Database
                   SELECT * FROM users WHERE LOWER(username) = LOWER(?)
                                       │
                     ┌─────────────────┴─────────────────┐
                     │ Found & Valid Hash?               │
                     ▼                                   ▼
                    YES                                  NO
                     │                                   │
                     ▼                                   ▼
          Check user.is_active === true            Log Failed Attempt
                     │                             Throttle IP / Username
          ┌──────────┴──────────┐                  Redirect Back with Generic Error
          ▼                     ▼                  "These credentials do not match our records."
        ACTIVE              DEACTIVATED
          │                     │
          ▼                     ▼
- Regenerate Session ID   Authentication Rejected
- Update last_login_at    "Account is deactivated. Contact Administrator."
- Write AuditLog (login)  Log Security Event
- Redirect to Dashboard
```

### Architectural Specifications:
- **Guard:** Standard Laravel `web` guard backed by session cookies.
- **User Provider:** Eloquent user provider querying `App\Models\User`.
- **Credential Fields:** `username` (case-insensitive via `LOWER(username)`) and `password`.
- **Session ID Regeneration:** Upon successful authentication, `session()->regenerate()` is invoked immediately to prevent session fixation attacks.
- **Single-School Web System:** No API token authentication (Sanctum/Passport), OAuth, or external social identity providers are used (DEC-001, DEC-006).

---

## 6. User Account Lifecycle

User accounts progress through strict operational states governed by institutional oversight:

```
       [Administrator Creates Account]
                      │
                      ▼
               Status: ACTIVE (is_active = true)
                      │
            ┌─────────┴─────────┐
            ▼                   ▼
     [Normal Usage]    [Administrator Deactivates]
            │                   │
            │                   ▼
            │          Status: DEACTIVATED (is_active = false)
            │                   │
            │         ┌─────────┴─────────┐
            │         ▼                   ▼
            │   [Access Denied]  [Administrator Re-activates]
            │   (Sessions Killed)         │
            │         │                   ▼
            │         │          Status: ACTIVE (is_active = true)
            ▼         ▼
     [Historical Data Remains Intact Forever]
     (Marks, Audits, Generated Reports Preserved)
```

### Lifecycle Rules:
1. **Account Creation:** Only an authenticated user holding the `Administrator` role can create user accounts.
2. **Account Deactivation:** Administrators may deactivate accounts by setting `is_active = FALSE`. Deactivation is non-destructive.
3. **Account Deletion Prohibited:** The application strictly prohibits `DELETE` operations on `users`. Database foreign keys enforce `ON DELETE RESTRICT` on `marks`, `attendance`, `teacher_assignments`, `generated_reports`, and `audit_logs`.
4. **Historical Attribution:** Marks entered or updated by a deactivated user retain the user's ID in `entered_by_user_id` or `updated_by_user_id`. Reports generated by the user retain `generated_by_user_id`.

---

## 7. Password Security

Password management conforms to modern cryptographic standards:

1. **Hashing Algorithm:** Uses PHP's native `password_hash()` via Laravel's `Hash` facade, defaulting to **Argon2id** (or bcrypt with a minimum work factor of 12).
2. **No Plaintext Storage:** Passwords are never stored, logged, or cached in plaintext.
3. **Hidden from Serialization:** The `User` model defines `protected $hidden = ['password_hash', 'remember_token'];` to prevent accidental exposure in JSON responses or debug dumps.
4. **Password Complexity Policy:**
   - Minimum length: 8 characters (12 characters recommended for Administrators).
   - Must contain mixed case, numbers, and symbols.
   - Enforced server-side via `Illuminate\Validation\Rules\Password`.
5. **Reset & Recovery Boundary:**
   - Self-service email password reset is **disabled** in the baseline single-school system.
   - Password resets are executed exclusively by the `Administrator` through `UserController@resetPassword`.
   - Temporary/reset passwords require an immediate change upon first login.
6. **Session Invalidation on Password Change:** Changing a user's password immediately invalidates all active sessions except the current session using `Auth::logoutOtherDevices()`.

---

## 8. Session Security Lifecycle

Session state represents the authenticated user's identity. To prevent hijacking, fixation, and session leakage:

1. **Cookie Security Attributes:**
   - `HttpOnly = true`: Blocks client-side JavaScript access to the session cookie, mitigating Cross-Site Scripting (XSS) cookie theft.
   - `SameSite = 'lax'`: Protects against Cross-Site Request Forgery (CSRF) by preventing the cookie from being sent on cross-origin requests.
   - `Secure = true`: Mandated in staging and production to ensure the session cookie is transmitted strictly over TLS/HTTPS.
2. **Session ID Regeneration:**
   - Executed on every successful login: `$request->session()->regenerate()`.
   - Executed on privilege changes or password updates.
3. **Session Expiration & Idle Timeout:**
   - Standard session lifetime: 120 minutes of inactivity (`config/session.php` $\rightarrow$ `lifetime => 120`).
   - `expire_on_close => true` may be configured to terminate sessions upon browser termination.
4. **Logout Behavior:**
   - Invokes `Auth::logout()`.
   - Invalidates session data: `$request->session()->invalidate()`.
   - Regenerates the CSRF token: `$request->session()->regenerateToken()`.

---

## 9. CSRF Protection Architecture

Because the application is a server-rendered web application processing HTTP forms:

1. **Universal Enforcement:** All state-changing HTTP requests (`POST`, `PUT`, `PATCH`, `DELETE`) are verified by Laravel's `VerifyCsrfToken` middleware.
2. **Blade Integration:** Every Blade form includes the directive:
   ```html
   <form method="POST" action="/marks">
       @csrf
       <!-- Form Fields -->
   </form>
   ```
3. **Vanilla JS Header Integration:** For asynchronous Vanilla JS requests (e.g. spreadsheet auto-saving or modal actions), the CSRF token is read from the layout `<meta name="csrf-token" content="{{ csrf_token() }}">` and passed in the `X-CSRF-TOKEN` header:
   ```javascript
   fetch('/marks/batch', {
       method: 'POST',
       headers: {
           'Content-Type': 'application/json',
           'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
           'Accept': 'application/json'
       },
       body: JSON.stringify(payload)
   });
   ```
4. **Rejection Handling:** Requests with missing or mismatched CSRF tokens trigger an HTTP 419 Page Expired response, preventing cross-origin execution.

---

## 10. Role Model Architecture

The system recognizes four distinct roles defined in the validated `roles` table:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            SYSTEM ROLES CATALOG                             │
├─────────────────┬───────────────────────────────────────────────────────────┤
│ Role Name       │ Primary System Responsibility                             │
├─────────────────┼───────────────────────────────────────────────────────────┤
│ Administrator   │ Institutional configuration, user accounts, audit viewing,│
│                 │ academic year lifecycle, and override corrections.        │
├─────────────────┼───────────────────────────────────────────────────────────┤
│ Office Staff    │ Operational academic administration, enrollment, student  │
│                 │ placement transfers, attendance, and report generation.   │
├─────────────────┼───────────────────────────────────────────────────────────┤
│ Class Teacher   │ Complete academic and pastoral oversight for an assigned  │
│                 │ classroom section (all subjects in that section).         │
├─────────────────┼───────────────────────────────────────────────────────────┤
│ Subject Teacher │ Academic mark entry strictly restricted to assigned       │
│                 │ classroom, section, and subject.                          │
└─────────────────┴───────────────────────────────────────────────────────────┘
```

### Architectural Role Rules:
1. **Roles Are Fixed:** The four system roles are seeded and immutable. Creating ad-hoc roles is out of scope and rejected (DEC-007).
2. **Single Primary Role per User Account:** Table `users` contains `role_id BIGINT NOT NULL` referencing `roles.id`. A user account holds exactly one primary system role.
3. **Role vs. Scope Disambiguation:**
   - A user with role `Administrator` or `Office Staff` has broad institutional operational privileges.
   - A user with role `Subject Teacher` or `Class Teacher` **has zero access** to academic grading until an explicit row is created in `teacher_assignments`.

---

## 11. Authorization Architecture (Policies & Gates)

Authorization operates as an independent, server-side gateway between the HTTP layer and the Application Services layer:

```
[Incoming Request] ──► [Route Middleware] ──► [Form Request Validation]
                                                     │
                                                     ▼
                                          [Controller Orchestration]
                                                     │
                                                     ▼
                                         [Laravel Policy / Gate]
                                                     │
                                      ┌──────────────┴──────────────┐
                                      ▼                             ▼
                                 [AUTHORIZED]                    [DENIED]
                                      │                             │
                                      ▼                             ▼
                         [Application Service Execution]   HTTP 403 Forbidden
```

### Policy Registry:
- `MarkPolicy`: Authorizes mark grid viewing, batch mark saving, and post-closure mark corrections.
- `StudentPolicy`: Authorizes student enrollment, academic placement transfers, and elective modifications.
- `ReportPolicy`: Authorizes report card compilation, preview, and PDF download streaming.
- `AttendancePolicy`: Authorizes term-level attendance submission and updates.
- `AcademicYearPolicy`: Authorizes academic year lifecycle transitions (`open` $\rightarrow$ `closed` $\rightarrow$ `reopen`).
- `UserPolicy`: Authorizes staff account creation, activation toggling, and password resets.
- `AuditLogPolicy`: Restricts audit log inspection exclusively to `Administrator`.
- `SchoolSettingPolicy`: Authorizes institutional branding and pass-mark modifications.

---

## 12. Teacher Assignment Scope Model

The `teacher_assignments` table bridges teacher user accounts to the academic hierarchy. Every row represents an explicit grant of authority:

```
                             teacher_assignments
┌─────────────────────────────────────────────────────────────────────────────┐
│ user_id          : BIGINT (Foreign Key to users.id)                         │
│ academic_year_id : BIGINT (Foreign Key to academic_years.id)                │
│ class_id         : BIGINT (Foreign Key to classes.id)                       │
│ section_id       : BIGINT (Foreign Key to sections.id)                      │
│ subject_id       : BIGINT NULLABLE (Foreign Key to subjects.id)             │
│ assignment_type  : ENUM ('class_teacher', 'subject_teacher')                │
│ effective_from   : DATE NOT NULL                                            │
│ effective_to     : DATE NULLABLE                                            │
│ is_active        : BOOLEAN NOT NULL DEFAULT TRUE                            │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Database Scope Invariant:
Table `teacher_assignments` enforces constraint `chk_ta_assignment_subject_consistency`:
```sql
CHECK (
    (assignment_type = 'class_teacher' AND subject_id IS NULL)
    OR
    (assignment_type = 'subject_teacher' AND subject_id IS NOT NULL)
)
```

---

## 13. Class Teacher Authorization

A Class Teacher assignment confers broad academic authority over an entire physical classroom:

1. **Database Representation:**
   - `assignment_type = 'class_teacher'`
   - `subject_id = NULL`
2. **Conferred Scope:**
   - Grants authority over **every applicable subject** (`class_subjects`) taught in that assigned class and section for that academic year.
   - No individual subject assignment rows are required.
3. **Privileges Granted:**
   - View mark sheets for all subjects in the classroom.
   - Enter and edit marks for all subjects in the classroom.
   - Correct marks previously submitted by Subject Teachers.
   - Enter and edit term attendance for students placed in that classroom.
4. **Policy Resolution Rule:**
   ```php
   // If the teacher holds an active class_teacher assignment for this section,
   // access is granted to all subjects in the section.
   $isClassTeacher = TeacherAssignment::query()
       ->where('user_id', $user->id)
       ->where('academic_year_id', $academicYearId)
       ->where('class_id', $classId)
       ->where('section_id', $sectionId)
       ->where('assignment_type', TeacherAssignmentType::ClassTeacher)
       ->where('is_active', true)
       ->where('effective_from', '<=', now())
       ->where(fn($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()))
       ->exists();
   ```

---

## 14. Subject Teacher Authorization

A Subject Teacher assignment confers narrow, specialized academic authority:

1. **Database Representation:**
   - `assignment_type = 'subject_teacher'`
   - `subject_id = [Target Subject ID]` (non-null)
2. **Conferred Scope:**
   - Grants authority **exclusively** over the specified `subject_id` within that assigned class and section for that academic year.
   - Grants zero authority over other subjects in that same section.
3. **Privileges Granted:**
   - View mark sheets for the assigned subject only.
   - Enter and edit marks for the assigned subject only.
   - Zero authority over term attendance.
4. **Policy Resolution Rule:**
   ```php
   // Access granted only if user holds an active assignment for this exact subject
   $isSubjectTeacher = TeacherAssignment::query()
       ->where('user_id', $user->id)
       ->where('academic_year_id', $academicYearId)
       ->where('class_id', $classId)
       ->where('section_id', $sectionId)
       ->where('subject_id', $subjectId)
       ->where('assignment_type', TeacherAssignmentType::SubjectTeacher)
       ->where('is_active', true)
       ->where('effective_from', '<=', now())
       ->where(fn($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()))
       ->exists();
   ```

---

## 15. Multi-Assignment Scope Resolution Algorithm

In real-world schools, teachers hold multiple assignments simultaneously. The authorization engine evaluates the **composite set** of active assignments:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    TEACHER SCOPE RESOLUTION ALGORITHM                       │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
     INPUT: (user, academicYearId, classId, sectionId, subjectId, assessmentId)
                                       │
                                       ▼
                  [Step 1: Check Global Roles & Status]
                  - Is user active? If NO ──────────────► [DENY: 403]
                  - Is user Administrator or Staff? ────► [AUTHORIZE]
                                       │
                                       ▼
                  [Step 2: Check Academic Year Lifecycle]
                  - Is year closed?
                    - If YES: Teachers are read-only ───► [DENY MUTATION: 403]
                                       │
                                       ▼
                  [Step 3: Query Active Teacher Assignments]
                  SELECT * FROM teacher_assignments
                  WHERE user_id = :userId
                    AND academic_year_id = :yearId
                    AND class_id = :classId
                    AND section_id = :sectionId
                    AND is_active = TRUE
                    AND effective_from <= CURRENT_DATE
                    AND (effective_to IS NULL OR effective_to >= CURRENT_DATE)
                                       │
                                       ▼
                  [Step 4: Check Class Teacher Match]
                  Does any assignment row have:
                  assignment_type == 'class_teacher' AND subject_id IS NULL?
                    - If YES ───────────────────────────► [AUTHORIZE]
                                       │
                                       ▼
                  [Step 5: Check Subject Teacher Match]
                  Does any assignment row have:
                  assignment_type == 'subject_teacher' AND subject_id == :subjectId?
                    - If YES ───────────────────────────► [AUTHORIZE]
                                       │
                                       ▼
                  [Step 6: No Applicable Assignment Found]
                  ──────────────────────────────────────► [DENY: 403]
```

### Concrete Teacher Assignment Resolution Examples:

| Teacher Scenario | Active Database Assignments | Requested Academic Context | Authorization Verdict & Architectural Rationale |
|---|---|---|---|
| **Teacher A** | `class_teacher` $\rightarrow$ Class 8-A | Class 8-A, Math | **AUTHORIZED:** Class Teacher holds authority over all subjects in 8-A. |
| **Teacher A** | `class_teacher` $\rightarrow$ Class 8-A | Class 8-A, Science | **AUTHORIZED:** Class Teacher holds authority over all subjects in 8-A. |
| **Teacher A** | `class_teacher` $\rightarrow$ Class 8-A | Class 8-B, Math | **DENIED (403):** Teacher A holds no assignment for Section 8-B. |
| **Teacher B** | `subject_teacher` $\rightarrow$ Class 8-A, Math | Class 8-A, Math | **AUTHORIZED:** Matches exact subject assignment. |
| **Teacher B** | `subject_teacher` $\rightarrow$ Class 8-A, Math | Class 8-A, Science | **DENIED (403):** Subject Teacher restricted strictly to Mathematics. |
| **Teacher C** | 1. `class_teacher` $\rightarrow$ Class 8-A<br>2. `subject_teacher` $\rightarrow$ Class 9-B, Math<br>3. `subject_teacher` $\rightarrow$ Class 10-A, Science | Class 8-A, History | **AUTHORIZED:** Class Teacher for 8-A covers History. |
| **Teacher C** | *(Same assignments as above)* | Class 9-B, Math | **AUTHORIZED:** Subject Teacher for Math in 9-B. |
| **Teacher C** | *(Same assignments as above)* | Class 9-B, English | **DENIED (403):** Only Math is authorized in 9-B. |
| **Teacher C** | *(Same assignments as above)* | Class 10-A, Science | **AUTHORIZED:** Subject Teacher for Science in 10-A. |
| **Teacher C** | *(Same assignments as above)* | Class 10-A, Math | **DENIED (403):** Only Science is authorized in 10-A. |

---

## 16. Administrator Authorization

The `Administrator` role represents institutional administrative authority:

- **Broad Scope:** Full access to view, create, edit, and manage all academic records, curriculum offerings, and student placements.
- **Exclusive Privileges:**
  - Staff user account creation, deactivation, and password resets (`UserPolicy`).
  - Read-only inspection of the immutable activity history (`AuditLogPolicy`).
  - Academic year lifecycle transitions (`AcademicYearPolicy`).
  - Institutional settings and branding configuration (`SchoolSettingPolicy`).
- **Closed-Year Correction Access:** Authorized to perform legitimate post-closure mark corrections accompanied by mandatory audit tracking.

---

## 17. Office Staff Authorization

The `Office Staff` role represents operational school administration:

- **Operational Scope:** Broad academic management privileges (creating classes, configuring sections, managing student enrollment, executing student section transfers, managing elective subject allocations, and generating report cards).
- **Mark Entry & Correction:** Authorized to enter marks and perform corrections across all classes and sections.
- **Closed-Year Correction Access:** Authorized to perform post-closure mark corrections accompanied by mandatory audit tracking.
- **Strictly Prohibited Privileges:**
  - ❌ **Zero User Administration:** Cannot create user accounts, edit credentials, or deactivate users.
  - ❌ **Zero Audit Log Inspection:** Cannot access or view `/admin/audit-logs`.
  - ❌ **Zero School Settings Modification:** Cannot alter school branding or passing mark thresholds.

---

## 18. Closed Academic Year Authorization

The academic year status (`academic_years.status`: `'open'` vs. `'closed'`) alters the authorization matrix dynamically:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                 ACADEMIC YEAR CLOSURE AUTHORIZATION MATRIX                  │
├───────────────────────────────┬──────────────────────┬──────────────────────┤
│ System Role / Actor           │ Year Status = OPEN   │ Year Status = CLOSED │
├───────────────────────────────┼──────────────────────┼──────────────────────┤
│ Administrator                 │ Full Access          │ Full Access (Audited)│
├───────────────────────────────┼──────────────────────┼──────────────────────┤
│ Office Staff                  │ Full Access          │ Full Access (Audited)│
├───────────────────────────────┼──────────────────────┼──────────────────────┤
│ Class Teacher                 │ Authorized Scope     │ READ-ONLY (Locked)   │
├───────────────────────────────┼──────────────────────┼──────────────────────┤
│ Subject Teacher               │ Authorized Scope     │ READ-ONLY (Locked)   │
└───────────────────────────────┴──────────────────────┴──────────────────────┘
```

### Critical Rules (CL-007, CL-008, DEC-027):
1. **Teachers Become Read-Only for Marks and Attendance:** When a year transitions to `closed`, all mark and attendance mutation endpoints return HTTP 403 Forbidden for teachers.
2. **Report Generation and Download Independence:** Report card generation and PDF downloading are reporting operations, not data mutations. Class Teachers retain the ability to generate and download report cards for their assigned classroom scope in closed years, provided required marks are finalized. This privilege does **not** grant Class Teachers permission to modify marks or attendance in a closed academic year.
3. **Admin/Staff Correction Access Preserved:** Administrators and Office Staff retain write access on closed years to perform approved corrections accompanied by mandatory audit logging.
4. **Reopening Restriction:** If an academic year is reopened (`closed` $\rightarrow$ `open`), teacher mark editing is **not automatically restored**. Teacher access remains locked unless explicitly enabled by an administrative configuration flag.

---

## 19. Mark Authorization Architecture

Mark editing is the core academic workflow and requires strict gating:

```
                                    HTTP POST /marks
                                           │
                                           ▼
                                    [MarkPolicy@update]
                                           │
             ┌─────────────────────────────┴─────────────────────────────┐
             ▼                                                           ▼
       [Role Check]                                                [Year Check]
  - Is Admin or Office Staff?                                 - Is Academic Year Open?
    - If YES ───────────► [AUTHORIZED]                          - If NO and User is Teacher ──► [DENIED: 403]
                                                                         │
                                                                         ▼
                                                            [Teacher Scope Check]
                                                            - Does User hold active
                                                              Class Teacher assignment
                                                              for target class/section?
                                                              - If YES ──► [AUTHORIZED]
                                                                         │
                                                                         ▼
                                                            - Does User hold active
                                                              Subject Teacher assignment
                                                              for target subject?
                                                              - If YES ──► [AUTHORIZED]
                                                              - If NO  ──► [DENIED: 403]
```

### Auditability Rule:
Every mark update, whether performed by a Subject Teacher, Class Teacher, Office Staff, or Administrator, writes an immutable record to `audit_logs` capturing `user_id`, `before_data` (`mark_value`, `result_status`), and `after_data`.

---

## 20. Attendance Authorization Architecture

Attendance is recorded on a term basis per student academic placement:

- **Class Teachers:** Authorized to enter and edit attendance for students placed within their assigned class and section.
- **Office Staff & Administrators:** Authorized to enter and edit attendance across all classes.
- **Subject Teachers:** **Zero attendance authority.** Subject teachers cannot submit or edit attendance records.
- **Bounds Validation:** Server-side Form Request enforces `chk_attendance_days_within_total` (`days_attended <= total_working_days`).

---

## 21. Report Authorization Architecture

Report generation and download workflows handle sensitive academic records:

1. **Generation Privileges:**
   - **Administrator:** May generate final report cards (broad institutional scope).
   - **Office Staff:** May generate final report cards (broad operational academic scope).
   - **Class Teacher:** May generate final report cards **only for students belonging to their currently authorized classroom scope** (academic year + class + section). Class Teacher authorization is resolved dynamically through the existing `teacher_assignments` model (`assignment_type = 'class_teacher'`, `subject_id = NULL`, `is_active = TRUE`, and effective date boundaries).
   - **Subject Teacher:** **Cannot generate final report cards.** Subject teachers possess subject-level mark-entry authority only.

2. **Download Privileges (DEC-023, DEC-026, DEC-038):**
   - **Administrator:** May download generated report card PDFs (broad institutional scope).
   - **Office Staff:** May download generated report card PDFs (broad operational academic scope).
   - **Class Teacher:** May download generated report card PDFs **only for reports belonging to their assigned classroom**. Download authorization requires verifying that the target `GeneratedReport`'s associated `StudentAcademicRecord` matches the teacher's active assigned academic year, class, and section.
   - **Subject Teacher:** **Cannot download final report card PDFs.** Requests return HTTP 403 Forbidden.

3. **ReportPolicy Specification:**
   - `ReportPolicy@generate($user, StudentAcademicRecord $placement)`:
     - Authorizes `Administrator` and `Office Staff` unconditionally.
     - Authorizes `Class Teacher` **only if** the target student's `academic_year_id`, `class_id`, and `section_id` match an active `teacher_assignments` record where `assignment_type = 'class_teacher'`, `subject_id = NULL`, `is_active = TRUE`, and current date falls within `effective_from` and `effective_to`.
     - Denies `Subject Teacher` (returns `false` / HTTP 403 Forbidden).
   - `ReportPolicy@download($user, GeneratedReport $report)`:
     - Authorizes `Administrator` and `Office Staff` unconditionally.
     - Authorizes `Class Teacher` **only if** the target report's student academic placement (`$report->studentAcademicRecord`) belongs to the teacher's active assigned classroom scope (`academic_year_id`, `class_id`, `section_id`).
     - Denies `Subject Teacher` (returns `false` / HTTP 403 Forbidden).

4. **Direct File URL Protection:**
   - PDF files are stored under `storage/app/private/reports/`.
   - Web server configurations block direct HTTP access to the private directory.
   - Downloads are mediated exclusively via `GeneratedReportDownloadController@download` after executing `Gate::authorize('download', $generatedReport)`.
   - Class Teacher authorization is never granted based on role name alone; relational classroom scope verification is mandatory.

---

## 22. Audit Log Authorization Architecture

System activity history in `audit_logs` contains sensitive security and operational trails:

1. **Administrator-Only Access:** Only users with role `Administrator` are authorized to access `/admin/audit-logs` (`AuditLogPolicy@viewAny`).
2. **Office Staff Blocked:** Office Staff attempting to access audit logs receive HTTP 403 Forbidden.
3. **Teachers Blocked:** Teachers attempting to access audit logs receive HTTP 403 Forbidden.
4. **Immutability Enforcement:** The `AuditLogPolicy` returns `false` for `create()`, `update()`, and `delete()`. Audit logs cannot be modified or purged through the web interface (DEC-025).

---

## 23. School / System Settings Authorization Architecture

Institutional branding and academic parameters in `school_settings` are strictly protected:

- **Administrator:** Full authority to update school name, upload institutional logos, and modify pass mark thresholds (`SchoolSettingPolicy@update`).
- **Office Staff:** Read-only access to view school settings. Modification requests receive HTTP 403 Forbidden.
- **Teachers:** Read-only access (consumed via Blade layouts for headers and branding).

---

## 24. Deactivated User Handling & Session Invalidation

A critical security requirement is ensuring that deactivating a user account immediately revokes access across all active sessions (DEC-032).

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    ACTIVE USER MIDDLEWARE ENFORCEMENT                       │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
                        Every Authenticated Request
                                       │
                                       ▼
                   [App\Http\Middleware\EnsureUserIsActive]
                                       │
                                       ▼
                        Evaluate Auth::user()->is_active
                                       │
                     ┌─────────────────┴─────────────────┐
                     ▼                                   ▼
                 TRUE (Active)                   FALSE (Deactivated)
                     │                                   │
                     ▼                                   ▼
             Proceed to Next Layer              1. Auth::logout()
             (Controller / Policy)              2. session()->invalidate()
                                                3. session()->regenerateToken()
                                                4. Redirect to /login with flash:
                                                   "Your account has been deactivated."
```

### Architectural Safeguard:
Because `EnsureUserIsActive` executes on **every single web request** within the `auth` middleware group, deactivating a user in the database terminates their access on their very next click, eliminating session lingering risks.

---

## 25. Privilege Escalation Prevention

To prevent malicious actors from elevating their permissions:

| Attack Vector | Vulnerability Description | Server-Side Mitigation & Architectural Control |
|---|---|---|
| **Role Tampering** | User modifies hidden `<input name="role_id">` in profile update. | Form Requests use strict whitelist; `role_id` is never mass-assignable on `User`. |
| **Actor Spoofing** | Attacker injects `entered_by_user_id` in mark entry POST payload. | Controller/Service forces actor ID from `Auth::id()`; payload values are ignored. |
| **Admin Route Guessing** | Non-admin browses directly to `/admin/users` or `/admin/audit-logs`. | Guarded by `CheckRole:Administrator` middleware and `UserPolicy`/`AuditLogPolicy`. |
| **Hidden Field Modification**| Teacher unhides and submits disabled mark fields for unauthorized subjects. | Policy independently evaluates the composite scope of every mark submitted in the payload. |

---

## 26. Insecure Direct Object Reference (IDOR) Prevention

IDOR occurs when an application exposes a direct database reference (e.g. `/marks/4582`) without verifying ownership:

### Anti-Pattern:
```php
// VULNERABLE: Only verifies user is a teacher, not that mark belongs to their scope
public function update(Request $request, Mark $mark) {
    if (Auth::user()->isTeacher()) {
        $mark->update($request->all());
    }
}
```

### Architectural Standard:
```php
// SECURE: Authorizes against complete relational hierarchy
public function update(UpdateMarkRequest $request, Mark $mark) {
    Gate::authorize('update', $mark); // Executes MarkPolicy@update
    $this->markEntryService->updateMark($mark, $request->validated(), Auth::id());
}
```

Inside `MarkPolicy@update($user, $mark)`:
```php
// Traverses the relational graph to resolve the authoritative scope
$placement = $mark->studentAcademicRecord;
$applicability = $mark->assessmentApplicability;
$classSubject = $applicability->classSubject;

return $this->teacherAssignmentService->isAuthorized(
    $user,
    $placement->academic_year_id,
    $placement->class_id,
    $placement->section_id,
    $classSubject->subject_id
);
```

---

## 27. Login Abuse & Credential Stuffing Protection

To protect authentication endpoints against brute-force attacks:

1. **IP & Username Rate Limiting:**
   - Managed via Laravel's built-in `RateLimiter` inside `LoginRequest`.
   - Limits: Maximum **5 attempts per minute** per `[username|client_ip]` throttle key.
   - Lockout duration: 60 seconds exponential backoff upon repeated lockouts.
2. **Generic Error Messages:**
   - Failed authentication returns: *"These credentials do not match our records."*
   - Never discloses whether the username exists or whether the password was incorrect, preventing username enumeration.
3. **Timing Attack Defense:**
   - If a username does not exist, a dummy password hash check (`Hash::check('dummy', '$2y$12$...')`) is executed to maintain constant response timing.

---

## 28. Security Headers Architecture

Web security headers mitigate client-side exploitation (clickjacking, MIME-sniffing, XSS):

```
Header Name                     Configured Value                    Security Purpose
─────────────────────────────────────────────────────────────────────────────────────────────
X-Frame-Options                 DENY                                Prevents clickjacking (framing)
X-Content-Type-Options          nosniff                             Blocks MIME-type sniffing
Referrer-Policy                 strict-origin-when-cross-origin     Prevents URL leak on navigation
Permissions-Policy              camera=(), microphone=(), geolocation=() Disables unused browser APIs
Content-Security-Policy (CSP)   default-src 'self';                 Restricts executable scripts
                                script-src 'self' 'nonce-...';      to local assets and nonces
```

*(Note: CSP header values are drafted for server-rendered Blade assets and will be validated during Phase 6.7 UI testing).*

---

## 29. Secrets Management

All security credentials and infrastructure secrets conform to strict isolation rules:

1. **Zero Hard-Coded Secrets:** Passwords, database credentials, application keys, and encryption secrets are **never** committed to version control.
2. **Environment Blueprint (`.env.example`):** Documents required environment variable names with dummy placeholders.
3. **Local `.env` Isolation:** Ignored via `.gitignore`.
4. **Logging Redaction:** The Laravel exception handler (`app/Exceptions/Handler.php`) redacts sensitive fields (`password`, `password_confirmation`, `password_hash`, `credit_card`) from application error logs.

---

## 30. Database Least-Privilege Security

Database interaction conforms to defense-in-depth principles:

1. **Dedicated Application User:** The application connects to PostgreSQL using a dedicated database role (`school_app_user`) possessing `SELECT`, `INSERT`, `UPDATE`, and `DELETE` on tables in the `public` schema.
2. **No DDL Privileges in Web Context:** The web runtime database role **cannot** execute `DROP TABLE`, `ALTER TABLE`, or `CREATE TABLE`. DDL is restricted strictly to command-line migration runners.
3. **No Superuser Deployment:** Running web application queries as the PostgreSQL `postgres` superuser is strictly prohibited in production environments.

---

## 31. Private File Access Architecture

Generated report card PDFs represent sensitive student performance data:

1. **Storage Isolation:** Files reside outside the public document root at:
   `storage/app/private/reports/{year_id}/{class_id}/{section_id}/{filename}.pdf`
2. **Controller-Mediated Streaming:**
   ```php
   public function download(GeneratedReport $report)
   {
       Gate::authorize('download', $report);

       if (!Storage::disk('private_reports')->exists($report->file_path)) {
           abort(404, 'Report file not found.');
       }

       return Storage::disk('private_reports')->download(
           $report->file_path,
           $report->getDownloadFilename()
       );
   }
   ```
3. **Predictable URL Guessing Mitigation & Relational Scope Verification:**
   - Download routes accept database IDs (`/reports/download/{id}`) and invoke `ReportPolicy@download` before reading from disk. Raw filesystem paths are never exposed in HTML or client payloads.
   - **Role-Independent Private File Protection:** Every download request must pass `GeneratedReport` download authorization regardless of user role.
   - For a `Class Teacher`, download authorization strictly requires verifying that the target report's student academic placement belongs to the teacher's active assigned classroom scope (`academic_year_id`, `class_id`, `section_id`).
   - A `Class Teacher` is **never authorized based on role name alone**, and can never access reports belonging to another classroom or section.
   - Report files are never accessible through public URLs or web server directory listings.

---

## 32. Security Event Auditability

All security-relevant events write structured entries to `audit_logs`:

| Security Event | Trigger Condition | Audited Data (`before_data` / `after_data`) |
|---|---|---|
| **User Login** | Successful authentication | IP address, user agent, login timestamp. |
| **Failed Login** | Authentication failure | Targeted username, IP address, failure reason. |
| **User Deactivated** | Admin toggles `is_active` to false | `user_id`, `actor_id`, `is_active: false`. |
| **Password Reset** | Admin resets staff password | `user_id`, `actor_id`, timestamp (no passwords logged). |
| **Teacher Assignment** | Assignment granted or revoked | `user_id`, `class_id`, `section_id`, `subject_id`, `type`. |
| **Mark Modification** | Teacher or Admin updates mark | `student_id`, `subject_id`, `old_mark`, `new_mark`, `reason`. |
| **Year Closure** | Year transitions to `closed` | `academic_year_id`, `status: closed`, actor timestamp. |
| **Report Generated** | Report card compiled & saved | `student_id`, `report_type`, `revision_number`, `file_path`. |

---

## 33. Architectural Boundaries: Middleware vs. Policies vs. Services

To eliminate duplicated authorization logic across controllers, services, and policies:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          AUTHORIZATION RESPONSIBILITY                       │
├─────────────────────┬───────────────────────────────────────────────────────┤
│ Layer               │ Specific Responsibility & Bounds                      │
├─────────────────────┼───────────────────────────────────────────────────────┤
│ **HTTP Middleware** │ Broad request-level perimeter checks:                 │
│                     │ - Is the user authenticated? (`auth`)                 │
│                     │ - Is the account active? (`EnsureUserIsActive`)       │
│                     │ - Does the user possess basic role access? (`CheckRole`)│
├─────────────────────┼───────────────────────────────────────────────────────┤
│ **Laravel Policies**│ Resource-level and contextual authorization:          │
│                     │ - Does this teacher hold assignment authority for this│
│                     │   exact class, section, and subject? (`MarkPolicy`)   │
│                     │ - Is this user authorized to download this report?    │
├─────────────────────┼───────────────────────────────────────────────────────┤
│ **App Services**    │ Business rule invariants & database transactions:     │
│                     │ - Is mark <= maximum marks?                           │
│                     │ - Is elective locked because marks exist?             │
│                     │ - Atomic database updates and audit logging.          │
├─────────────────────┼───────────────────────────────────────────────────────┤
│ **Blade / Views**   │ User experience only:                                 │
│                     │ - Conditionally rendering action buttons or menus.    │
│                     │ - Zero security authority.                            │
└─────────────────────┴───────────────────────────────────────────────────────┘
```

---

## 34. Comprehensive Security Threat Matrix

| Threat ID | Threat Vector | Potential Impact | Architectural Countermeasure | Verification Phase |
|---|---|---|---|---|
| **TH-01** | Unauthorized URL Browsing | Non-staff accesses admin panels. | Route middleware (`auth`, `EnsureUserIsActive`, `CheckRole:Administrator`). | Phase 6.9 |
| **TH-02** | Insecure Direct Object Reference (IDOR) | Teacher edits marks of another classroom. | Object-level Policies (`MarkPolicy@update`) traversing full relational scope. | Phase 6.9 |
| **TH-03** | Parameter Tampering (Payload) | Teacher changes `subject_id` in POST request. | Form Request validation + Policy verifying user assignment for injected subject. | Phase 6.9 |
| **TH-04** | Role Tampering / Escalation | Staff attempts to grant themselves Admin role. | `role_id` excluded from mass-assignment `$fillable`; Admin-only route for user updates. | Phase 6.9 |
| **TH-05** | Deactivated User Lingering Session | Deactivated teacher continues editing marks. | `EnsureUserIsActive` middleware checks `is_active` on every single request. | Phase 6.9 |
| **TH-06** | Cross-Site Request Forgery (CSRF) | Malicious site triggers mark edits on active session. | Universal CSRF token validation on all state-changing endpoints. | Phase 6.9 |
| **TH-07** | Session Fixation | Attacker pre-sets session ID to hijack account. | `$request->session()->regenerate()` executed immediately upon login. | Phase 6.9 |
| **TH-08** | Brute-Force Credential Stuffing | Automated script guesses passwords. | Rate limiting (5 attempts/min per IP/username) via Laravel `RateLimiter`. | Phase 6.9 |
| **TH-09** | Username Enumeration | Attacker maps valid school usernames. | Generic error messages for failed login; dummy hash check to equalize timing. | Phase 6.9 |
| **TH-10** | Unauthorized Report Download | Subject Teacher attempts download; Class Teacher attempts download outside assigned classroom; or user guesses `GeneratedReport` ID outside authorized scope. | `ReportPolicy@download` verifies role and traverses relational scope (`GeneratedReport` $\rightarrow$ `StudentAcademicRecord`) against active `teacher_assignments`. | Phase 6.9 |
| **TH-11** | Unauthorized Audit Log Access | Staff views security and mark change logs. | `AuditLogPolicy@viewAny` restricts viewing strictly to Administrator. | Phase 6.9 |
| **TH-12** | Mass-Assignment Injection | Attacker overwrites `entered_by_user_id`. | Actor IDs forced server-side from `Auth::id()`; excluded from Form Requests. | Phase 6.9 |
| **TH-13** | Stale Assignment Permissions | Teacher reassigned but retains old access. | Assignments queried live from PostgreSQL; no permanent assignment caching in session. | Phase 6.9 |
| **TH-14** | Closed-Year Mark Tampering | Teacher edits marks after academic year closes. | Policy and middleware verify `academic_years.status`; teachers locked to read-only. | Phase 6.9 |
| **TH-15** | Client-Side Security Bypass | Attacker submits form with disabled fields unhidden. | Server-side Policies re-verify 100% of permissions regardless of DOM state. | Phase 6.9 |
| **TH-16** | Unauthenticated File Access | Direct web access to report PDFs. | Files stored in private disk outside web root; streamed only via authorized controller. | Phase 6.9 |
| **TH-17** | Password Exposure in Logs | Passwords leak in stack traces or audits. | Password fields listed in `$hidden` and `$dontFlash`; excluded from audit JSON. | Phase 6.9 |

---

## 35. Complete Authorization Decision Matrix

| Application Operation | Administrator | Office Staff | Class Teacher | Subject Teacher |
|---|---|---|---|---|
| **System Authentication (Login)** | Allowed | Allowed | Allowed | Allowed |
| **User Account Creation & Deactivation**| Allowed | Denied (403) | Denied (403) | Denied (403) |
| **Password Reset** | Allowed | Denied (403) | Denied (403) | Denied (403) |
| **View Mark Sheet (Open Year)** | Allowed (All) | Allowed (All) | Allowed (Assigned Class) | Allowed (Assigned Subject) |
| **Enter / Edit Marks (Open Year)** | Allowed (All) | Allowed (All) | Allowed (Assigned Class) | Allowed (Assigned Subject) |
| **Correct Marks (Closed Year)** | Allowed (Audited)| Allowed (Audited)| Denied (Read-Only) | Denied (Read-Only) |
| **Enter / Edit Attendance** | Allowed (All) | Allowed (All) | Allowed (Assigned Class) | Denied (403) |
| **Student Enrollment & Master Profile** | Allowed | Allowed | View Only | View Only |
| **Student Placement Transfer** | Allowed | Allowed | Denied (403) | Denied (403) |
| **Elective Allocation Modification** | Allowed (Pre-marks)| Allowed (Pre-marks)| Denied (403) | Denied (403) |
| **Teacher Assignment Management** | Allowed | Allowed | Denied (403) | Denied (403) |
| **Academic Year Lifecycle Management** | Allowed | Denied (403) | Denied (403) | Denied (403) |
| **Curriculum Subject Configuration** | Allowed | Allowed | Denied (403) | Denied (403) |
| **Generate Report Card Preview** | Allowed | Allowed | Allowed (Assigned Class) | Denied (403) |
| **Generate Final Report Card** | Allowed | Allowed | Allowed (Assigned Class) | Denied (403) |
| **Download Generated Report PDF** | Allowed | Allowed | Allowed (Assigned Class) | Denied (403) |
| **View Audit Logs** | Allowed | Denied (403) | Denied (403) | Denied (403) |
| **Modify School Settings / Branding** | Allowed | Denied (403) | Denied (403) | Denied (403) |

---

## 36. Traceability Matrix

| Security Specification | Authoritative Requirement | Impact & Implementation Consequence |
|---|---|---|
| **Role != Teacher Scope** | BRD V1.3 (BR-030 to BR-034) | System evaluates dynamic `teacher_assignments`; never checks `role == teacher` alone. |
| **Class Teacher All-Subject Scope**| BRD V1.3 & Schema (`chk_ta_assignment_subject_consistency`)| Class teacher assignment grants access to all subjects in section without extra rows. |
| **Subject Teacher Contextual Scope**| BRD V1.3 (BR-032) | Subject teacher restricted strictly to assigned class, section, and subject. |
| **Immediate Session Revocation** | Phase 6.1 (Auth Principle) | `EnsureUserIsActive` middleware intercepts every request and kills inactive sessions. |
| **Closed-Year Role Access** | BRD V1.3 (CL-007, CL-008) | Teachers become read-only; Administrator/Office Staff retain correction access. |
| **Restricted Report Downloads** | BRD V1.3 & Handover Rules | Only Administrator, Office Staff, and Class Teachers may download generated report PDFs. For Class Teachers, authorization is strictly limited to reports whose student academic placement belongs to the teacher's active assigned classroom scope. Subject Teachers remain blocked. |
| **Administrator-Only Audits** | BRD V1.3 (BR-080, BR-081) | Audit logs viewable exclusively by Administrator; modification/deletion blocked. |
| **IDOR Defense via Relational Graph**| Security Standard | Policies traverse `Mark` $\rightarrow$ `ClassSubject` $\rightarrow$ `TeacherAssignment` before granting edit rights. |
| **Private PDF Storage** | Phase 6.1 & Phase 6.4 (DEC-023) | Report files stored in private disk; streamed only after policy authorization. |

---

## 37. Decision Ledger Integration

The following architectural decisions governing Phase 6.5 are formally recorded in `docs/decisions.md`:

- **DEC-031:** Native Laravel Session-Based Web Authentication Architecture
- **DEC-032:** Immediate Access Revocation on User Deactivation via Active State Middleware
- **DEC-033:** Multi-Assignment Composite Scope Resolution for Teacher Authorization
- **DEC-034:** Class Teacher Broad-Scope Authorization Resolution
- **DEC-035:** Role-Aware Closed Academic Year Authorization Matrix
- **DEC-036:** Restricting User Account Administration & Password Resets to Administrator
- **DEC-037:** Restricting Audit Log Inspection Exclusively to Administrator
- **DEC-038:** Authorizing Report Card PDF Downloads for Administrator, Office Staff, and Class Teachers (Restricted to Assigned Classroom Scope)
- **DEC-039:** Server-Side Contextual Authorization Gateways (Client-Side Presentation Only)
- **DEC-040:** Relational IDOR Defense via Mandatory Scope Graph Verification

---

## 38. Carry-Forward Corrections from Phase 6.4

The following technical points from Phase 6.4 are explicitly reaffirmed and carried forward:
1. **Academic Calculation Precision:** `MarkValueCast` provides the scalar interface for persistence mapping, but all mathematical averages and percentages inside `CalculationService` must preserve decimal string / BCMath precision to avoid IEEE 754 float rounding errors.
2. **File Atomicity Boundary:** Database transactions do not automatically roll back disk operations. Atomic PDF storage (generating to temp paths and committing to final storage) will be designed in Phase 6.8.
3. **Connection Pooling Status:** Connection pooling is not yet implemented; production connection pool sizing is a deployment configuration concern deferred to Phase 6.10.

---

## 39. Deferred Decisions (Phases 6.6 – 6.10)

The following implementation details are intentionally deferred to subsequent blueprint phases:
- **Phase 6.6:** Exact Form Request validation rule syntax, controller action method signatures, and route parameter names.
- **Phase 6.7:** Exact CSP nonce generation implementation and Blade layout conditional rendering helpers (`@can`).
- **Phase 6.8:** Exact PDF rendering library choice (`dompdf` vs `browsershot`) and PDF controller streaming headers.
- **Phase 6.9:** Concrete PHPUnit authorization test cases, mock teacher scenarios, and test assertion matrices.
- **Phase 6.10:** Production session storage driver (PostgreSQL vs Redis), cookie domain parameters, and TLS termination.

---

## 40. Prohibited Approaches

The following patterns are strictly forbidden in the security architecture:

- ❌ **No Client-Side Authorization:** Never rely on hiding buttons or disabled inputs as an authorization boundary.
- ❌ **No Role-Only Teacher Checks:** Never write `if ($user->role->name === 'Teacher') allow();`. Scope must always be evaluated.
- ❌ **No Role-Only Class Teacher Report Authorization:** Never authorize a Class Teacher to generate or download report cards solely by checking their role name. Always verify the `GeneratedReport`'s associated student academic placement (`StudentAcademicRecord`) against the teacher's active `teacher_assignments` record (`academic_year_id`, `class_id`, `section_id`).
- ❌ **No Session-Cached Teacher Permissions:** Never store a teacher's assigned classes/subjects permanently in session. Query PostgreSQL live.
- ❌ **No External Permission Packages:** Do not install `spatie/laravel-permission` or similar packages. Use native Policies and the validated schema.
- ❌ **No Token/JWT Architecture:** No API token authentication (Sanctum/Passport).
- ❌ **No Hard Deletion of Users:** User accounts are deactivated, never deleted.
- ❌ **No Public Report URLs:** Report PDFs must never be stored in or served directly from `public/`.
- ❌ **No Blanket Closed-Year Write Blocks:** Do not block Administrator or Office Staff from performing authorized corrections on closed years.

---

## 41. Phase 6.5 Completion Checklist

- [x] BRD V1.3 inspected and verified.
- [x] Finalized business rules and handover decisions verified.
- [x] Approved 23-table relational design verified.
- [x] Current PostgreSQL 18 implementation and seed data verified.
- [x] Phases 6.1 through 6.4 blueprints verified.
- [x] Decision ledger (`docs/decisions.md`) reviewed and updated with DEC-031 through DEC-040.
- [x] Native session-based web authentication architecture established.
- [x] User account lifecycle and non-destructive deactivation established.
- [x] Immediate session invalidation for deactivated users defined (`EnsureUserIsActive`).
- [x] Password complexity, hashing (Argon2id/bcrypt), and admin reset rules defined.
- [x] Session security (HttpOnly, SameSite, Secure, regeneration) defined.
- [x] Universal CSRF protection for Blade and Vanilla JS defined.
- [x] Clear architectural separation between Role and Teacher Assignment Scope established.
- [x] Class Teacher broad-scope access (all subjects in section) established.
- [x] Subject Teacher narrow-scope access (assigned subject only) established.
- [x] Multi-assignment composite scope resolution algorithm documented with concrete examples.
- [x] Administrator privileges defined (including exclusive user management and audit viewing).
- [x] Office Staff operational scope defined (academic management; barred from user admin and audit logs).
- [x] Role-aware closed academic year lifecycle defined (teachers read-only, admin/staff correct).
- [x] Attendance authorization restricted to Class Teachers, Office Staff, and Administrators.
- [x] Final report generation authorized for Administrator, Office Staff, and Class Teachers (restricted strictly to assigned classroom scope); Subject Teachers prohibited.
- [x] Generated PDF report download authorized for Administrator, Office Staff, and Class Teachers (restricted strictly to assigned classroom scope); Subject Teachers prohibited.
- [x] Comprehensive threat matrix covering 17 security risks established.
- [x] Complete authorization decision matrix covering all primary operations established.
- [x] IDOR defense via relational scope graph traversal defined.
- [x] Security headers and secrets management established.
- [x] Private, authenticated report file streaming defined.
- [x] Testing requirements for authentication, authorization, and security defined.
- [x] Phase 6.4 carry-forward corrections explicitly respected.
- [x] Zero application or migration code was generated.
- [x] Documentation saved to `docs/architecture/PHASE_6_5_AUTHENTICATION_AUTHORIZATION_SECURITY.md`.
