# PHASE 6.10 — ENVIRONMENT CONFIGURATION & DEPLOYMENT READINESS
## Master Operational Blueprint, Production Hardening, Environment Contract & Deployment Runbook

**Project:** School Examination Marks and Report Card Management System  
**Project Root:** `D:\Projects\School report card`  
**Document Version:** 1.0 (Master Specification)  
**Phase:** 6.10 — Environment Configuration & Deployment Readiness  
**Status:** Specification Complete / Implementation Ready (Pending Execution)  

---

## 1. Purpose & Core Operational Objectives

This document establishes the authoritative, implementation-ready operational blueprint and deployment specification for the **School Examination Marks and Report Card Management System**. It translates the domain models, security architecture, HTTP contracts, user interfaces, PDF compilation engines, and test strategies developed across Phases 6.1 through 6.9 into a production-grade operational configuration.

### 1.1 Core Objectives
1. **Deterministic Environment Configuration:** Define the exact runtime requirements, environment variable contracts (`.env`), and secret isolation protocols for local development, automated testing, and production environments.
2. **Zero-Trust Security Hardening:** Enforce strict browser security headers, a zero-inline-script Content Security Policy (CSP), encrypted session handling, and database least privilege (DML-only runtime access).
3. **Dual-Domain Persistence & Private Storage Protection:** Safeguard student academic report cards under canonical private storage (`storage/app/private/`), enforcing failure-safe two-phase promotion and dual-domain backup procedures (PostgreSQL relational dump + private PDF archives).
4. **Headless Chromium Operational Readiness:** Operationalize Spatie Browsershot and Headless Chromium for server-side PDF generation, establishing process isolation, resource boundaries, and temporary staging directory lifecycles.
5. **Deterministic Deployment Runbook:** Specify ordered, idempotent deployment and rollback procedures, Laravel cache compilation routines, and comprehensive post-deployment smoke verification.
6. **Honest Quality Gate Alignment:** Explicitly preserve Phase 6.9's status as `Draft / Pending Verification` (Specification Reconciled; Tests Pending Execution) without conflating deployment readiness with application test execution.

---

## 2. Scope & Boundaries

### 2.1 In-Scope Operational Domains
- Production `.env` contract, variable definitions, and `.env.example` maintenance.
- Secret management and version-control exclusion rules (`.gitignore`).
- PHP 8.4+ runtime configuration, OPcache tuning, and required PHP extension inventory.
- PostgreSQL 18.x connection parameters, connection pooling boundaries, and application role least privilege.
- Node.js / npm build-time asset compilation via Vite.
- Laravel production cache strategy (`config:cache`, `route:cache`, `view:cache`, `event:cache`).
- Session, cookie, HTTPS, and trusted reverse-proxy configurations.
- Content Security Policy (CSP) and HTTP response security headers.
- Static asset caching vs. private, uncacheable student data protection.
- Canonical private storage disk configuration, directory permissions, and temp cleanup runbooks.
- Operational prerequisites for Headless Chromium / Puppeteer.
- Production error handling, custom error views (403, 404, 419, 422, 429, 500), and sensitive log redaction.
- Dual-domain database and file backup/restore readiness.
- Deployment ordering, pre-flight checks, rollback procedures, and post-deployment smoke tests.
- Deployment readiness verification checklist (ENV-001 through ENV-025).

### 2.2 Explicit Out-of-Scope Activities & Non-Goals
- **DO NOT START Phase 7** or any future feature development.
- **DO NOT modify the approved database model:** The schema remains fixed at **EXACTLY 23 tables** with zero added columns, tables, or unapproved relationships.
- **DO NOT alter business rules or mark semantics:** Mark ternary separation (`blank` $\ne$ `numeric 0.00` $\ne$ `absent A`), contextual maximum marks, Term Exam exclusive calculation, and attendance bounds (`days_attended <= total_working_days`, `0/0 -> "N/A"`) remain strictly invariant.
- **DO NOT introduce unapproved student fields:** Admission numbers, registration codes, student codes, dates of birth, gender, parent/guardian details, student photos, or addresses remain strictly prohibited.
- **DO NOT introduce unapproved metrics:** Student rankings, grade bands, GPA scales, annual overall percentages, and promotion workflows remain prohibited.
- **DO NOT expand infrastructure arbitrarily:** Do NOT introduce Redis, Celery/Horizon queues, PgBouncer, Docker, Kubernetes, Supervisor daemons, cloud object stores (S3/Azure/GCS), or CI/CD pipelines unless explicitly mandated as optional considerations.
- **DO NOT claim deployment occurred:** No actual server provisioning, DNS configuration, SSL issuance, or production server deployment is claimed or executed.

---

## 3. Authoritative Source Precedence Hierarchy

All environment and deployment configurations must trace strictly to the project's authoritative source hierarchy:

```
1. BRD V1.3 (BRD/School_Examination_Marksheet_Requirements_v1_3.docx)
   - Mark semantics (BR-001 to BR-014), dynamic assessments (BR-015 to BR-024)
   - Term calculations (BR-025 to BR-032), report cards (BR-058 to BR-068)
   - Completion requirements (BR-080 to BR-084)
2. Finalized Business Rules & Handover Decisions (School_Report_Card_Project_Handover_v1.md)
3. Approved 23-Table Relational Model (DB design/DB-table-definitons.txt)
4. Approved Entity-Relationship Diagram (DB design/mermaid-diagram.png)
5. Current Validated PostgreSQL Implementation (database/Postgres/schema.sql)
6. Phase 6.1 — Technology Baseline & Architecture Principles
7. Phase 6.2 — Domain Model & Eloquent Architecture
8. Phase 6.3 — Laravel Project & Folder Structure
9. Phase 6.4 — PostgreSQL + Eloquent Integration Architecture
10. Phase 6.5 — Authentication, Authorization & Security Architecture
11. Phase 6.6 — HTTP Layer, Routes, Form Requests & Controller Contracts
12. Phase 6.7 — Blade UI & Vanilla JavaScript Integration Architecture
13. Phase 6.8 — PDF Generation & File Storage Architecture (DEC-049 to DEC-055)
14. Phase 6.9 — Application Testing & Quality Gate Strategy (DEC-056)
15. Architectural Decision Ledger (docs/decisions.md: DEC-001 to DEC-059)
```

> [!CAUTION]
> **Historical Handover Notice:** Earlier handover notes mentioning Node.js, Express, MySQL, or EJS represent deprecated legacy explorations. The active, approved architecture is strictly **PHP 8.4+ / Laravel 13 / PostgreSQL 18.x / Blade / Vanilla JavaScript / Plain CSS / Vite**.

---

## 4. Approved Technology Baseline

The operational environment is constrained to the following proven baseline:

| Technology Component | Approved Specification | Operational Role & Boundary | Forbidden Substitutions / Prohibitions |
| :--- | :--- | :--- | :--- |
| **Language Runtime** | **PHP 8.4+** | Application backend runtime (PHP-FPM) | Node.js backend, Python, Go |
| **Framework** | **Laravel 13** | HTTP transport, ORM, routing, policy auth | Express.js, Nest.js, Django |
| **Database** | **PostgreSQL 18.x** | Authoritative relational persistence (23 tables) | MySQL, SQLite, MongoDB |
| **Template Canvas** | **Laravel Blade** | Server-rendered HTML & isolated PDF canvas | EJS, Handlebars, Blade-replacements |
| **Client Scripting** | **Vanilla JavaScript** | Native ES modules for UX enhancement | React, Vue, Angular, Svelte, Livewire, Alpine, jQuery |
| **Styling** | **Vanilla Plain CSS** | Dedicated light design system tokens | Tailwind CSS, Bootstrap, Sass/Less |
| **Build Tooling** | **Vite + Node 22.x** | Build-time asset bundling only | Webpack, Mix, runtime Node server |
| **PDF Compilation** | **Browsershot / Chromium**| Server-side headless A4 print engine | Dompdf, wkhtmltopdf, TCPDF |
| **Storage Driver** | **Local Filesystem** | Canonical private disk (`storage/app/private/`) | S3, public web directory, Azure Blob |

---

## 5. Multi-Tier Environment Matrix

The application operates across four distinct environments with strictly enforced configuration boundaries:

```
+──────────────────+      +──────────────────+      +──────────────────+      +──────────────────+
|      LOCAL       |      |     TESTING      |      |     STAGING      |      |    PRODUCTION    |
|   DEVELOPMENT    |      |    AUTOMATION    |      |  PRE-PRODUCTION  |      |   OPERATIONAL    |
+──────────────────+      +──────────────────+      +──────────────────+      +──────────────────+
| APP_ENV=local    |      | APP_ENV=testing  |      | APP_ENV=staging  |      | APP_ENV=production
| APP_DEBUG=true   |      | APP_DEBUG=false  |      | APP_DEBUG=false  |      | APP_DEBUG=false  |
| DB: Local PG     |      | DB: Dedicated PG |      | DB: PG Replica   |      | DB: Dedicated PG |
| Vite: Dev Server |      | Vite: Prebuilt   |      | Vite: Prebuilt   |      | Vite: Prebuilt   |
| Mail: Log driver |      | Mail: Array      |      | Mail: Log driver |      | Mail: Unused     |
| Chromium: Local  |      | Chromium: CI Bin |      | Chromium: Pinned |      | Chromium: Pinned |
+──────────────────+      +──────────────────+      +──────────────────+      +──────────────────+
```

### 5.1 Environment Invariant Rules
1. **`APP_DEBUG=false` Everywhere Outside Local:** Debug mode is strictly forbidden in testing, staging, and production.
2. **Deterministic Prebuilt Assets:** Staging and production never run `vite dev`; assets must be pre-compiled and served from `public/build/`.
3. **Isolated Test Database:** Testing executes against a dedicated PostgreSQL database instance, running full migrations and triggers directly against PostgreSQL 18.x without mocking.
4. **Zero Cross-Environment Data Pollution:** Production student academic records and generated PDF cards must never be copied into non-production environments without sanitization.

---

## 6. PHP 8.4+ Runtime & Extension Requirements

### 6.1 Required PHP Extensions

The production PHP 8.4 environment must compile and enable the following mandatory extensions:

| Extension Name | Architectural Purpose | Authoritative Justification |
| :--- | :--- | :--- |
| `pdo_pgsql` | Relational database driver | Communicates with PostgreSQL 18.x using native PDO interface. |
| `pgsql` | PostgreSQL low-level driver | Required for low-level connection diagnostics and schema inspection. |
| `bcmath` | Exact decimal mathematics | Authoritative calculation of mark percentages and sums to 2 decimal places. |
| `mbstring` | Multibyte string processing | UTF-8 encoding support for student names, subject titles, and report labels. |
| `intl` | Internationalization & formatting | Number and currency formatting for institutional report headers. |
| `openssl` | Cryptographic primitives | Session encryption, secure token generation, and TLS communication. |
| `sodium` | Modern cryptography | Native password hashing (Argon2id) and session data encryption. |
| `curl` | HTTP transport client | Internal service health probes and Browsershot IPC communication. |
| `zip` | Compressed archive handling | CSV bulk import compression and backup package verification. |
| `dom` / `libxml` | XML and DOM manipulation | Browsershot HTML canvas manipulation and SVG logo validation. |
| `fileinfo` | MIME detection | Dynamic, trusted MIME detection for uploaded school logos (`File::mimeType()`). |

### 6.2 PHP Configuration Directives (`php.ini`)

Production runtime settings must enforce strict execution limits and security boundaries:

```ini
; ------------------------------------------------------------------------------
; PHP 8.4 Production Hardening Directives
; ------------------------------------------------------------------------------
expose_php = Off
display_errors = Off
display_startup_errors = Off
log_errors = On
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
memory_limit = 256M
max_execution_time = 60
max_input_time = 60
post_max_size = 10M
upload_max_filesize = 5M
default_socket_timeout = 60

; Session Security Settings
session.use_strict_mode = 1
session.use_cookies = 1
session.use_only_cookies = 1
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = "Lax"
session.gc_maxlifetime = 7200

; OPcache Performance Tuning
opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0
opcache.save_comments = 1
opcache.fast_shutdown = 1
```

---

## 7. PostgreSQL 18.x Configuration & Least Privilege

### 7.1 Database Connection Configuration
The PostgreSQL configuration must enforce connection durability and strict transaction isolation:

```php
// config/database.php
'pgsql' => [
    'driver' => 'pgsql',
    'url' => env('DB_URL'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'school_marks_db'),
    'username' => env('DB_USERNAME', 'school_app_user'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'search_path' => 'public',
    'sslmode' => env('DB_SSLMODE', 'prefer'),
    'options' => [
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ],
],
```

### 7.2 Database Least Privilege Architecture (DEC-057)

The production web runtime MUST NOT connect as PostgreSQL `postgres` superuser or any account with DDL capabilities. Production database security is split into two distinct roles:

```
                        ┌────────────────────────────────────────────────────────┐
                        │              POSTGRESQL CLUSTER ROLES                  │
                        ├──────────────────────────┬─────────────────────────────┤
                        │ school_deployer (DDL)    │ school_app_user (DML Only)  │
                        │ - Used by CI/CD pipeline │ - Used by Laravel Web App   │
                        │ - Runs migrations        │ - SELECT on all 23 tables   │
                        │ - Alters schema          │ - INSERT, UPDATE (Scoped)   │
                        │ - Manages indexes        │ - DELETE restricted         │
                        │ - Manages triggers       │ - Zero DDL permissions      │
                        └──────────────────────────┴─────────────────────────────┘
```

#### SQL Privilege Provisioning Script:
```sql
-- 1. Create Dedicated Application Role (Non-Superuser)
CREATE ROLE school_app_user WITH LOGIN PASSWORD 'STRONG_RANDOMLY_GENERATED_SECRET' NOSUPERUSER NOCREATEDB NOCREATEROLE;

-- 2. Grant Schema Usage
GRANT USAGE ON SCHEMA public TO school_app_user;

-- 3. Grant DML Permissions on All 23 Tables
GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA public TO school_app_user;

-- 4. Restrict Deletions to Operational Entities (Zero Deletes on Audit Logs or Master Tables)
REVOKE DELETE ON audit_logs, generated_reports, students, student_academic_records, marks FROM school_app_user;
GRANT DELETE ON sessions TO school_app_user;

-- 5. Grant Sequence Usage (for auto-increment IDs)
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO school_app_user;

-- 6. Lock Down Default Privileges
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE ON TABLES TO school_app_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO school_app_user;
```

---

## 8. Environment Variable Contract (`.env.example`)

The production environment variable contract is defined in [.env.example](file:///d:/Projects/School%20report%20card/.env.example) and documented below with classification of security sensitivity:

```ini
# ==============================================================================
# APPLICATION CORE CONFIGURATION
# ==============================================================================
# Sensitivity: Public / Operational
APP_NAME="School Examination Marks and Report Card Management System"
APP_ENV=production
APP_KEY=                          # Generate via: php artisan key:generate --show
APP_DEBUG=false                   # MUST BE false IN PRODUCTION
APP_URL=https://school.example.com

# ==============================================================================
# LOGGING & OBSERVABILITY
# ==============================================================================
# Sensitivity: Operational
LOG_CHANNEL=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning                 # debug, info, notice, warning, error, critical

# ==============================================================================
# DATABASE CONFIGURATION (POSTGRESQL 18.x)
# ==============================================================================
# Sensitivity: HIGH (DB_PASSWORD is a confidential credential)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=school_marks_db
DB_USERNAME=school_app_user       # Least-privilege DML role
DB_PASSWORD=                      # Provided via secure vault / deployment injection

# ==============================================================================
# SESSION & COOKIE SECURITY
# ==============================================================================
# Sensitivity: Operational / Security
SESSION_DRIVER=file               # Supported: file, database
SESSION_LIFETIME=120              # Inactivity timeout in minutes
SESSION_ENCRYPT=true              # Encrypt payload with APP_KEY
SESSION_PATH=/
SESSION_DOMAIN=null               # Set to canonical host domain if needed
SESSION_SECURE_COOKIE=true        # Enforce HTTPS-only transmission
SESSION_HTTP_ONLY=true            # Block JavaScript document.cookie access
SESSION_SAME_SITE=lax             # Strict CSRF cross-origin defense

# ==============================================================================
# CACHE & QUEUE CONFIGURATION
# ==============================================================================
# Sensitivity: Operational
CACHE_STORE=file                  # Baseline file cache
QUEUE_CONNECTION=sync             # Synchronous execution

# ==============================================================================
# FILESYSTEM & PRIVATE REPORT STORAGE
# ==============================================================================
# Sensitivity: Operational
FILESYSTEM_DISK=local
# PRIVATE_REPORTS_DISK_ROOT=      # Defaults to storage_path('app/private')

# ==============================================================================
# PDF COMPILATION (BROWSERSHOT / CHROMIUM)
# ==============================================================================
# Sensitivity: Operational
BROWSERSHOT_NODE_PATH=            # Optional custom Node binary path
BROWSERSHOT_NPM_PATH=             # Optional custom NPM binary path
BROWSERSHOT_CHROME_PATH=          # Optional custom Chromium binary path
BROWSERSHOT_TIMEOUT=60            # Max compilation duration in seconds

# ==============================================================================
# ASSET COMPILATION (VITE)
# ==============================================================================
# Sensitivity: Public
VITE_APP_NAME="${APP_NAME}"
```

### 8.1 Secret Management Principles (DEC-057)
1. **Zero Repository Secrets:** The `.env` file, database passwords, and `APP_KEY` must **never** be committed to Git. The repository [.gitignore](file:///d:/Projects/School%20report%20card/.gitignore) strictly blocks all `.env*` files except `!.env.example`.
2. **Deployment-Time Secret Injection:** Production secrets must be injected into the server filesystem at deploy time via environment variables, automated pipeline secrets, or host secret vaults.
3. **Log Sanitization:** Sensitive environment variables (`APP_KEY`, `DB_PASSWORD`) must be redacted from all log handlers and exception traces.
4. **Zero HTTP Configuration Endpoints:** No routes (e.g. `/phpinfo`, `/debug-env`) may exist that expose server configuration.

---

## 9. Content Security Policy & Security Response Headers (DEC-058)

### 9.1 Content Security Policy (CSP) Directives

The production CSP strictly conforms to the Phase 6.7 UI architecture, which eliminated all inline scripts and inline event handlers:

```http
Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none';
```

#### Directive Evaluation Matrix:
| CSP Directive | Approved Value | Architectural Rationale |
| :--- | :--- | :--- |
| `default-src` | `'self'` | Denies loading of any resource from third-party origins by default. |
| `script-src` | `'self'` | Permits only versioned, compiled ES modules from `public/build/assets/`. Zero `'unsafe-inline'`. |
| `style-src` | `'self'` | Loads compiled plain CSS bundles. Inlined print styles exist only inside the isolated Chromium PDF sandbox. |
| `img-src` | `'self' data:` | Allows local UI icons and local base64-encoded school crest data URIs (`data:image/...`). |
| `font-src` | `'self'` | Loads bundled Figtree web fonts from local server assets. Zero external Google Fonts HTTP lookups. |
| `connect-src` | `'self'` | Restricts asynchronous Fetch/XHR mark batch saves to the application's origin (`/marks/batch-save`). |
| `frame-src` | `'self'` | Allows the optional embedded PDF preview dialog to display the report streaming endpoint. |
| `object-src` | `'none'` | Completely disables Flash, Java, and browser plugins. |
| `base-uri` | `'self'` | Prevents malicious `<base>` tag injection from altering relative URL resolutions. |
| `form-action` | `'self'` | Restricts Form submissions strictly to the application origin. |
| `frame-ancestors`| `'none'` | Completely disables framing of the application inside iframes on external domains (anti-clickjacking). |

### 9.2 Complete Security Response Header Suite

All HTTP responses emitted by the application or reverse proxy must include the following headers:

```http
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), screen-wake-lock=()
Cross-Origin-Opener-Policy: same-origin
Cross-Origin-Resource-Policy: same-origin
```

---

## 10. HTTP Cache & Authenticated Data Protection

Production caching boundaries must strictly separate public static assets from confidential student data:

```
                               ┌────────────────────────────────────────────────────────┐
                               │                 HTTP CACHE CONTROL                     │
                               ├──────────────────────────┬─────────────────────────────┤
                               │ Public Versioned Assets  │ Authenticated Student Data  │
                               │ (/build/assets/*)        │ (/marks, /reports, /admin)  │
                               ├──────────────────────────┼─────────────────────────────┤
                               │ Cache-Control: public,   │ Cache-Control: private,     │
                               │   max-age=31536000,      │   no-cache, no-store,       │
                               │   immutable              │   must-revalidate           │
                               │ Content hashed by Vite   │ Pragma: no-cache            │
                               │ Safe for CDN & Proxies   │ Expires: 0                  │
                               │ Zero sensitive data      │ Zero intermediary caching   │
                               └──────────────────────────┴─────────────────────────────┘
```

> [!CRITICAL]
> **Zero Public Caching on Student Data:** Under no circumstances may `Cache-Control: public` be emitted on marksheet grids, student profiles, attendance summaries, audit logs, or generated PDF reports. The `GeneratedReportDownloadController` must explicitly inject `Cache-Control: private, no-cache, no-store, must-revalidate` on all streaming responses.

---

## 11. Canonical Private Storage & Permissions Architecture (DEC-059)

### 11.1 Storage Path Invariant
As established in DEC-053, all report cards reside strictly under `storage/app/private/`:

```text
storage/app/private/
├── temp/
│   └── reports/
│       └── {uuid}.pdf                                    (Transient rendering staging)
└── reports/
    └── {academic_year_id}/
        └── {class_id}/
            └── {section_id}/
                └── {student_academic_record_id}/
                    └── {report_type}/
                        ├── revision-1.pdf                (Immutable historical archive)
                        └── revision-2.pdf                (Immutable latest release)
```

- **Canonical Disk:** `private_reports` bound to `storage_path('app/private')`.
- **Database Stored Value:** `generated_reports.file_path` contains relative path `reports/{year}/{class}/{sec}/{record}/{type}/revision-{n}.pdf`.
- **No Path Nesting Bugs:** Zero duplicate `reports/reports` nesting.
- **Zero Public Symlinking:** `php artisan storage:link` MUST NOT expose `storage/app/private`.

### 11.2 Filesystem Permissions Specification

#### Linux Production Permissions:
```bash
# Set ownership to web server user (e.g. www-data or nginx)
chown -R www-data:www-data /var/www/school-marks-system

# Restrict general application directory permissions
find /var/www/school-marks-system -type f -exec chmod 0644 {} \;
find /var/www/school-marks-system -type d -exec chmod 0755 {} \;

# Restrict storage and bootstrap cache to web server only
chmod -R 0750 /var/www/school-marks-system/storage
chmod -R 0750 /var/www/school-marks-system/bootstrap/cache
```

#### Windows Development ACLs:
- The local development directory `D:\Projects\School report card` must grant Full Control to the active developer user account and read/write access to the local PHP execution process.

---

## 12. Headless Chromium / Browsershot Operational Prerequisites

### 12.1 Operational Architecture
PDF generation uses Spatie Browsershot wrapping Headless Chromium via Puppeteer. The process lifecycle runs outside the database transaction:

```
[ReportGenerationService]
          │
          ▼
1. Render Blade Template to HTML string in memory
          │
          ▼
2. Invoke Browsershot CLI Bridge
   (Node.js / Puppeteer spawns Headless Chromium child process)
          │
          ├── Flags: --disable-network, --no-sandbox, --disable-gpu, --disable-dev-shm-usage
          ├── Dynamic inline print CSS & Base64 school crest logo
          └── Puppeteer Header/Footer template: <span class="pageNumber"> of <span class="totalPages">
          │
          ▼
3. Write binary PDF output to staging: storage/app/private/temp/reports/{uuid}.pdf
          │
          ▼
4. Validate binary header (%PDF-) & non-zero byte size
          │
          ▼
5. Relational Lock & File Promotion (Phase 6.8 Two-Phase Failure-Safe Protocol)
```

### 12.2 Server Prerequisites
1. **Node.js Runtime:** Node.js 22.x LTS installed on the host system.
2. **Chromium Binary:** Stable Google Chrome or Chromium browser installed.
3. **Puppeteer Package:** Installed in the application project (`npm install puppeteer --save-dev` or global system module).
4. **Process Isolation & Memory Limits:**
   - Maximum compile timeout: 60 seconds (`BROWSERSHOT_TIMEOUT=60`).
   - Shared memory optimization: `--disable-dev-shm-usage` prevents Docker/container memory exhaustion.
   - Network restriction: `--disable-network` completely blocks outbound HTTP requests from the rendering engine.

---

## 13. Production Logging & Sensitive Data Protection

### 13.1 Daily Log Rotation
Production logging uses Laravel's `daily` channel, retaining 30 days of logs:
```php
// config/logging.php
'daily' => [
    'driver' => 'daily',
    'path' => storage_path('logs/laravel.log'),
    'level' => env('LOG_LEVEL', 'warning'),
    'days' => 30,
    'permission' => 0640,
],
```

### 13.2 Sensitive Parameter Redaction
To protect student privacy and credential confidentiality, Laravel's exception handler and log formatters must redact sensitive attributes:
```php
// config/logging.php redaction list
'redact_keys' => [
    'password',
    'password_confirmation',
    'password_hash',
    'token',
    'app_key',
    'db_password',
    'credit_card',
    'authorization',
    'cookie',
],
```

---

## 14. Dual-Domain Backup & Disaster Recovery Readiness (DEC-059)

> [!IMPORTANT]
> **Dual-Domain Persistence Principle:** The School Report Card System stores metadata in PostgreSQL 18.x and physical report artifacts in the filesystem. A database backup without report files loses historical marksheets; a filesystem backup without the database loses contextual relationships and revision tracking. Both domains must be backed up concurrently.

```
                    ┌────────────────────────────────────────────────────────┐
                    │               DUAL-DOMAIN BACKUP BUNDLE                │
                    ├──────────────────────────┬─────────────────────────────┤
                    │ Domain 1: Relational DB  │ Domain 2: Private Storage   │
                    ├──────────────────────────┼─────────────────────────────┤
                    │ Tool: pg_dump            │ Tool: tar / rsync           │
                    │ Output: school_db.dump   │ Output: reports_archive.tar │
                    │ Contents:                │ Contents:                   │
                    │ - Exactly 23 tables      │ - storage/app/private/      │
                    │ - Triggers & Constraints │   reports/... (All PDFs)    │
                    │ - Sequential identities  │ - Excludes: temp/           │
                    │ - Audit logs             │ - Preserves directory tree  │
                    └──────────────────────────┴─────────────────────────────┘
```

### 14.1 Backup Runbook (Conceptual CLI Commands)
```bash
# 1. Capture PostgreSQL Relational Dump
pg_dump -h 127.0.0.1 -U school_deployer -F c -b -v -f /backups/db/school_marks_$(date +%Y%m%d_%H%M%S).dump school_marks_db

# 2. Capture Private Report Cards Archive (Excluding Transient Temp Files)
tar -czvf /backups/files/reports_$(date +%Y%m%d_%H%M%S).tar.gz -C /var/www/school-marks-system/storage/app/private reports/
```

---

## 15. Operational Storage Reconciliation Commands

In accordance with Phase 6.8 Section 14, two administrative maintenance commands are specified to maintain storage hygiene:

| Command Signature | Operational Lifecycle Status | Function & Execution Boundary |
| :--- | :--- | :--- |
| `php artisan reports:clean-temp` | **Architecture Specified / Command Planned** | Scans `storage/app/private/temp/reports/` and unlinks orphaned `.pdf` files older than 60 minutes resulting from aborted renders. |
| `php artisan reports:reconcile-storage`| **Architecture Specified / Command Planned** | Traverses `storage/app/private/reports/` against `generated_reports.file_path`. Flags unindexed orphan files (e.g. from power loss in Window H) and alerts administrators. |

*Note: These commands represent planned architectural tooling to be implemented alongside application code in subsequent phases.*

---

## 16. Deterministic Production Deployment Runbook

Deployments must follow an ordered, idempotent sequence to ensure zero downtime and zero data corruption:

```
[PRE-FLIGHT VERIFICATION] 
       │ (Verify PHP 8.4, PG 18, extensions, disk space, .env permissions)
       ▼
[ENTER MAINTENANCE MODE] ──> php artisan down --render="errors.maintenance" --secret="DEPLOY_BYPASS"
       │
       ▼
[CODE & ASSET STAGING]
       ├── git pull origin main
       ├── composer install --no-dev --optimize-autoloader --no-interaction
       ├── npm ci
       └── npm run build
       │
       ▼
[DATABASE SYNCHRONIZATION] ──> php artisan migrate --force (Approved migrations only; 23 tables strictly)
       │
       ▼
[LARAVEL CACHE REBUILD]
       ├── php artisan config:cache
       ├── php artisan route:cache
       ├── php artisan view:cache
       └── php artisan event:cache
       │
       ▼
[STORAGE & ENGINE VERIFICATION] ──> Verify writable storage/app/private/ and Chromium binary
       │
       ▼
[EXIT MAINTENANCE MODE] ──> php artisan up
       │
       ▼
[POST-DEPLOYMENT SMOKE TESTS] (Execute SMOKE-001 through SMOKE-010)
```

---

## 17. Deterministic Rollback Procedures

If any critical failure occurs during deployment (database migration failure, asset compile error, or smoke test failure):

1. **Keep Maintenance Mode Active:** Retain `php artisan down` to protect end-users.
2. **Roll Back Code Revision:** `git reset --hard PREVIOUS_RELEASE_TAG`.
3. **Roll Back Database Migrations:** If a newly introduced migration failed, execute `php artisan migrate:rollback --step=1`.
4. **Rebuild Application Caches:**
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
5. **Re-compile Assets:** Execute `npm run build` from the rolled-back code state.
6. **Verify Storage Integrity:** Confirm that `storage/app/private/reports/` was untouched. Historical report PDFs are never deleted during rollback.
7. **Deactivate Maintenance Mode:** `php artisan up`.
8. **Document Incident:** Record the failure event and diagnostic trace in the release audit log.

---

## 18. Post-Deployment Smoke Test Protocol

Following release activation, the deployment engineer executes the smoke test suite:

| Test ID | Verification Description | Execution Method | Expected Result |
| :--- | :--- | :--- | :--- |
| `SMOKE-001` | Application Boots | `GET /login` | **HTTP 200 OK**, styled login view rendered. |
| `SMOKE-002` | Asset Loading | Inspect browser network tab | Compiled CSS and JS load with **HTTP 200** from `/build/assets/`. |
| `SMOKE-003` | CSP Enforcement | Inspect response headers on `/login` | Strict CSP header present; zero console CSP violations. |
| `SMOKE-004` | Security Headers | `curl -I https://school.example.com` | `HSTS`, `nosniff`, `DENY` headers present. |
| `SMOKE-005` | Database Read | Admin login authentication | Successful login redirect to `/dashboard`. |
| `SMOKE-006` | Mark Grid View | Class Teacher accesses mark entry | Mark grid renders active student roster; zero NaN/undefined cells. |
| `SMOKE-007` | Attendance Safe Zero | Class with 0 working days | Attendance percentage displays `"N/A"` without division exception. |
| `SMOKE-008` | PDF Compilation | Class Teacher generates final report | PDF compiles to `storage/app/private/reports/.../revision-1.pdf`. |
| `SMOKE-009` | Report Download | Stream generated report via browser | Binary PDF streams with correct sanitization headers. |
| `SMOKE-010` | Audit Log Insert | Query `audit_logs` table | Event `report_generated` recorded with correct timestamp and user ID. |

---

## 19. Deployment-Readiness Verification Checklist (ENV-001 to ENV-025)

The operational readiness of the environment is evaluated against 25 standardized criteria:

```text
[Specified / Ready for Execution] ENV-001  Production APP_ENV is explicitly set to 'production'.
[Specified / Ready for Execution] ENV-002  APP_DEBUG is explicitly set to false.
[Specified / Ready for Execution] ENV-003  APP_KEY is securely generated and populated in .env.
[Specified / Ready for Execution] ENV-004  PostgreSQL 18.x connection succeeds using dedicated credentials.
[Specified / Ready for Execution] ENV-005  All required PHP 8.4 extensions are enabled and validated.
[Specified / Ready for Execution] ENV-006  Composer production dependencies install cleanly with --no-dev.
[Specified / Ready for Execution] ENV-007  Vite production asset build completes with zero errors.
[Specified / Ready for Execution] ENV-008  Compiled CSS and JS assets load cleanly via Laravel @vite directives.
[Specified / Ready for Execution] ENV-009  Content Security Policy permits application assets without unsafe-inline.
[Specified / Ready for Execution] ENV-010  Strict security response headers (HSTS, nosniff, DENY) are emitted.
[Specified / Ready for Execution] ENV-011  Authenticated student and mark pages emit private, no-cache headers.
[Specified / Ready for Execution] ENV-012  Private report storage directory is completely outside web document root.
[Specified / Ready for Execution] ENV-013  Permanent private report directory is writable by PHP worker process.
[Specified / Ready for Execution] ENV-014  Temporary report staging directory (temp/reports/) is writable.
[Specified / Ready for Execution] ENV-015  Headless Chromium binary and Node.js bridge execute successfully.
[Specified / Ready for Execution] ENV-016  Daily log channel rotates and redacts passwords and application secrets.
[Specified / Ready for Execution] ENV-017  PostgreSQL application user has DML permissions and zero DDL permissions.
[Specified / Ready for Execution] ENV-018  Direct web access to .env and repository metadata is blocked (HTTP 404/403).
[Specified / Ready for Execution] ENV-019  Custom error pages (403, 404, 419, 500) render without stack traces.
[Specified / Ready for Execution] ENV-020  Session cookies are configured with Secure, HttpOnly, and SameSite=lax.
[Specified / Ready for Execution] ENV-021  Compiled Vite JavaScript and CSS bundles contain zero embedded secrets.
[Specified / Ready for Execution] ENV-022  Report download endpoint strictly enforces ReportPolicy classroom scope.
[Specified / Ready for Execution] ENV-023  Application boots cleanly through index.php entrypoint.
[Specified / Ready for Execution] ENV-024  Laravel cache commands (config:cache, route:cache, view:cache) succeed.
[Specified / Ready for Execution] ENV-025  Deployment rollback procedure is fully documented and reversible.
```

---

## 20. Zero-Tolerance Deployment Failure Conditions

The existence of **any single condition** below constitutes an immediate, unconditional deployment blocker:

1. ❌ `APP_DEBUG=true` in staging or production.
2. ❌ Real secrets or production credentials committed to Git.
3. ❌ `.env` file or `.git` directory publicly accessible via HTTP.
4. ❌ Web application connects to PostgreSQL as `postgres` superuser.
5. ❌ Direct static URL access exists for private report PDFs.
6. ❌ Public storage symlink (`storage:link`) exposes private report archives.
7. ❌ CSP header includes `'unsafe-inline'` for scripts.
8. ❌ Authenticated student marks or report pages emit `Cache-Control: public`.
9. ❌ Compiled frontend assets contain hardcoded secrets or internal API keys.
10. ❌ Browsershot cannot access `storage/app/private/temp/reports/`.
11. ❌ Browsershot uses fixed or predictable shared temporary filenames instead of UUIDs.
12. ❌ Unhandled exceptions render detailed stack traces, file paths, or SQL queries to users.
13. ❌ Deactivated users retain active session access across page refreshes.
14. ❌ Subject Teacher can access final report card preview, generation, or download.
15. ❌ Class Teacher can access or download report cards for an unassigned classroom.
16. ❌ Database migrations or deployment scripts modify the approved 23-table schema.
17. ❌ Database migrations add columns (`admission_number`, `status`, `file_hash`, `tenant_id`).
18. ❌ Mark state coercion occurs (`blank` converted to `0.00` or `A` converted to `0.00`).
19. ❌ Existing historical report revisions are overwritten or deleted on disk.
20. ❌ Private storage directory structure uses duplicate `reports/reports/` nesting.
21. ❌ Application backend is converted to Node.js or Express.
22. ❌ Audit logs can be modified or deleted via any HTTP endpoint.
23. ❌ Deployment documentation falsely claims unexecuted tests passed.
24. ❌ Phase 6.9 is marked approved without recorded automated test execution.

---

## 21. Operational Troubleshooting Boundaries

| Symptom / Error | Root Cause Analysis | Corrective Action Boundary |
| :--- | :--- | :--- |
| **HTTP 500 on Report Generation** | Browsershot failed to spawn Chromium (missing Node or browser binary). | Verify `BROWSERSHOT_NODE_PATH` and `BROWSERSHOT_CHROME_PATH`. Check host package installations. |
| **HTTP 419 Page Expired** | CSRF token mismatch or expired session cookie. | Verify `APP_URL`, `SESSION_DOMAIN`, and `SESSION_SECURE_COOKIE` settings against request protocol. |
| **Blank PDF Output** | Chromium execution timed out or failed to parse dynamic CSS. | Increase `BROWSERSHOT_TIMEOUT=120`. Verify local Figtree fonts are accessible. |
| **Duplicate Revision HTTP 409** | Concurrency race condition caught by PostgreSQL unique index `uk_gr_revision_identity`. | Working as designed. Retry operation; row lock serializes revision increment. |
| **Database Permission Denied** | Web application attempted DDL (`CREATE TABLE`, `ALTER TABLE`) or unauthorized `DELETE`. | Verify that migrations run under `school_deployer` and web runtime uses `school_app_user`. |
| **CSS/JS 404 in Browser** | Vite manifest missing or assets not compiled for production. | Execute `npm run build` and re-run `php artisan view:clear`. |

---

## 22. Phase 6.9 Relationship & Verification Status

### Phase 6.9 Status Preservation
In strict adherence to project governance rules:
- **Phase 6.9 Status:** **Draft / Pending Verification (Specification Reconciled; Tests Pending Execution)**.
- **Independence of Concerns:** Phase 6.10 establishes **deployment readiness and operational hardening**. It does **NOT** execute the 23 Quality Gates defined in Phase 6.9, nor does it certify application code correctness.
- **Authoritative Boundary:** Phase 6.9 Quality Gates (Gate A through Gate W) will transition to `PASSED` only when the concrete application implementation is tested against live PostgreSQL test suites and evidence is recorded.

---

## 23. Decision Ledger Traceability Matrix (Phase 6.10)

The operational specifications defined in this document trace directly to the architectural decisions in [decisions.md](file:///d:/Projects/School%20report%20card/docs/decisions.md):

| Decision ID | Decision Title | Operational Enforcement Area |
| :--- | :--- | :--- |
| **DEC-031** | Native Laravel Session Authentication | Production session configuration, encryption, and lifetime. |
| **DEC-033** | Zero-Trust IDOR Protection Architecture | Parameter casting, traversal authorization, and route security. |
| **DEC-045** | Server-Side Plain CSS Token Architecture | Static asset caching and CSP `style-src 'self'`. |
| **DEC-046** | Native Vanilla JavaScript Module Architecture | Strict CSP `script-src 'self'`, zero inline scripts. |
| **DEC-049** | Browsershot / Chromium Engine Selection | Server-side PDF compilation prerequisites and execution flags. |
| **DEC-050** | Concurrency Row-Locking Revision Allocation | Pessimistic row locking on placements during promotion. |
| **DEC-051** | Two-Phase Storage Protocol & Staging | Temporary staging under `temp/reports/` and final promotion. |
| **DEC-052** | Dedicated PDF Blade Canvas & Inline Print CSS | Sandboxed rendering canvas detached from web shell. |
| **DEC-053** | Canonical Private Storage Disk Root | `storage_path('app/private')` disk root; zero `reports/reports` nesting. |
| **DEC-054** | Two-Phase Persistence & Compensating Rollback | Explicit handling across failure Windows A through I. |
| **DEC-055** | Headless Chromium Template Pagination | Native Chromium header/footer page counters. |
| **DEC-056** | Unified Gate A–W Quality Gate Architecture | Standardized 23 release-blocking gates and zero-tolerance conditions. |
| **DEC-057** | Production Environment & Secret Isolation | `.env` contract, least-privilege role `school_app_user`, zero secrets in VCS. |
| **DEC-058** | Production CSP & Security Headers Architecture | Zero-inline-script CSP, HSTS, X-Frame-Options, Referrer-Policy. |
| **DEC-059** | Private Storage Permissions & Dual-Domain Backup | Filesystem ACLs, no public symlink, database + PDF dual-domain backups. |

---

## 24. Phase 6.10 Quality Sign-Off & Status

### Operational Readiness Status: **SPECIFICATION COMPLETE / IMPLEMENTATION READY**

> [!IMPORTANT]
> **ABSOLUTE BOUNDARY ENFORCEMENT:**  
> - **Phase 6.10 is specification-complete and implementation-ready.**  
> - **Phase 6.9 remains `Draft / Pending Verification`.**  
> - **Phase 7 has NOT been started.**  
> - **No production server provisioning or live deployment has been executed.**

```text
[x] 1. Production environment variable contract (.env.example) defined with zero hardcoded secrets.
[x] 2. Repository secret protection rules (.gitignore) configured and verified.
[x] 3. Database least-privilege architecture defined (school_app_user DML-only role vs deployer DDL).
[x] 4. PHP 8.4+ runtime, extension inventory, and production php.ini directives specified.
[x] 5. PostgreSQL 18.x connection parameters and schema immutability (exactly 23 tables) preserved.
[x] 6. Strict zero-inline-script Content Security Policy (CSP) and HTTP response headers specified.
[x] 7. Static asset caching vs uncacheable private student data boundaries enforced.
[x] 8. Canonical private storage disk root (storage/app/private/) operationalized without nesting bugs.
[x] 9. Browsershot / Chromium server prerequisites, flags, and temp file lifecycles defined.
[x] 10. Dual-domain disaster recovery backup strategy (PostgreSQL dump + private PDFs) specified.
[x] 11. Deterministic deployment sequence, cache compilation, and rollback procedures defined.
[x] 12. Deployment readiness checklist (ENV-001 through ENV-025) established.
[x] 13. 24 Zero-Tolerance deployment failure conditions enforced.
[x] 14. Architectural decisions DEC-057, DEC-058, and DEC-059 recorded in docs/decisions.md.
[x] 15. Phase 6.9 status honestly maintained as Draft / Pending Verification.
[x] 16. Absolute phase boundary respected: Phase 7 has NOT been started.
```

**Conclusion:** The environment configuration, deployment readiness, and operational hardening architecture is complete, internally consistent, and fully aligned with the project's authoritative source hierarchy.
