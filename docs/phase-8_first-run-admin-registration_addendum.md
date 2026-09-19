# Phase 8 Addendum — First-Run Administrator Registration

## Purpose
Extend Phase 8 Authentication, Roles & Authorization with a controlled first-run bootstrap mechanism. This is NOT public self-registration. It exists only when the application has no users, so the first Administrator account can be created through the UI.

## Authoritative Constraints
- BRD V1.3, final handover, approved business rules, approved 23-table schema, PostgreSQL schema/validation, and current Phase 8 architecture remain authoritative.
- No schema redesign. Do not add registration/setup/permission/tenant tables or columns.
- Four roles remain exactly: Administrator, Office Staff, Subject Teacher, Class Teacher.
- Native Laravel session authentication only.
- No JWT, Sanctum, Passport, OAuth, API-token authentication, or third-party permission package.
- Existing Policies, UserPolicy, AuditService, middleware pipeline, and TeacherAuthorizationService remain authoritative.
- No hard deletion of users.
- Do not start the next phase.

## PHP-Only Runtime
Node.js/npm must NOT be required to run the installed application. Vite/npm may remain a development/build-time asset tool. The application must run with compiled assets using PHP/Laravel/PostgreSQL; `npm run dev` must not be required at runtime.

## First-Run Lifecycle
Fresh installation → zero users → login page shows “Create Administrator Account” → first Administrator is created → account is authenticated → registration option disappears → normal login flow.

This must be an initial Administrator bootstrap, never an open/public registration system.

## Registration Availability
Determine availability server-side from the existing users table:
- zero users: setup available
- one or more users: setup permanently unavailable

Do NOT make registration available again merely because all users are later deactivated.

Do not add `registration_enabled`, `setup_completed`, `installation_status`, tenant/school fields, permission tables, or other schema structures.

## Initial Role
The first account created through setup must always be Administrator. Never accept or trust a client-supplied role_id. Resolve the existing Administrator role server-side and assign it explicitly.

## UI
When zero users exist, the login page may show:
- Username
- Password
- Login
- Create Administrator Account

After initialization, the registration option must not be rendered. Hiding the option is not the security control; the server must enforce the rule.

The setup page must clearly state that it creates the initial Administrator and is available only during first-run initialization.

## Routes
Add a dedicated setup flow, preferably:
- GET /setup
- POST /setup

GET /setup is available only while zero users exist. Once initialized, redirect to /login or return an appropriate non-success response.

POST /setup creates the first Administrator only while zero users exist. If another request has already created a user, reject safely and do not create another account.

## Form Fields
Use only fields supported by the existing users schema/authentication requirements, such as:
- username
- display name
- password
- password confirmation

Derive final field names/validation from the existing implementation. Do not invent prohibited domain fields such as DOB, gender, parent/guardian, address, admission/registration number, photo, school/tenant fields, etc.

## Password Security
- Never store plaintext passwords.
- Hash using Laravel's configured password hashing mechanism.
- Store only the resulting hash in users.password_hash.
- password_hash remains hidden and non-fillable.
- Never log passwords, hashes, confirmation values, or credentials.
- Do not accept a client-supplied password hash.
- Validate password confirmation server-side.

## Mass Assignment
Never accept these as trusted request fields:
- role_id
- is_active
- password_hash
- last_login_at
- actor/user IDs
- audit/system-controlled fields

Assign system-controlled values explicitly on the server.

## Concurrency / Race Condition
Do NOT implement unsafe logic equivalent to:
`if (User::count() === 0) { User::create(...); }`

Two simultaneous setup requests must not create two initial Administrators.

Use a PostgreSQL-safe transactional/concurrency strategy compatible with the existing closed schema. Do not modify the schema to solve this.

Add an automated test covering the relevant first-user concurrency/race protection as far as practical with the existing PostgreSQL test architecture.

## Transaction
Initial account creation must be transactional:
1. Safely establish first-user eligibility.
2. Resolve the existing Administrator role.
3. Create the user.
4. Persist required audit information appropriately.
5. Commit only after required operations succeed.

Failures must not leave a partially created account.

## Authentication After Setup
After successful creation:
1. Authenticate the new Administrator with the existing native Laravel session guard.
2. Regenerate the session ID.
3. Do not expose credentials.
4. Redirect to /dashboard.
5. Subsequent requests must use the existing auth → active middleware flow.

Do not bypass existing authentication architecture.

## Audit
Use the existing centralized AuditService. Do not create another audit system.

Record the initial Administrator creation as a security/domain event using only non-sensitive data supported by the audit schema. Never store plaintext passwords, password hashes, confirmation values, submitted credentials, or secrets.

For the first account, actor_id may legitimately be null because no authenticated Administrator exists yet; follow the existing audit schema/business rules.

## Middleware
Preserve:
web → auth → active → role → Form Request → Policy/Gate → TeacherAuthorizationService → controller/application service

EnsureUserIsActive must execute AFTER auth.

The unauthenticated setup route has its own server-side zero-user eligibility check.

## CSRF
POST /setup must use Laravel's normal CSRF protection. Do not create custom CSRF logic and do not make account creation GET-only.

## Post-Initialization Security
Once any user exists:
- GET /setup must not expose the form.
- POST /setup must reject creation.
- Changing username, role IDs, or other submitted values must not bypass the restriction.
- Replaying setup requests must not create another initial account.

## Relationship to User Management
This feature creates only the initial Administrator. It does not replace future Administrator-controlled User Management.

After initialization:
Initial Administrator → Administrator login → User Management → create/deactivate/reset subsequent users.

Do not create public self-registration for teachers or staff. Do not let users choose their own role.

## Tests
Use the existing PostgreSQL test database, not SQLite.

At minimum test:
1. GET /setup works with zero users.
2. Login page shows registration option with zero users.
3. GET /setup is unavailable after first user exists.
4. Login page hides registration option after initialization.
5. Valid setup creates exactly one user.
6. First user receives Administrator role.
7. Account is active according to existing rules.
8. Password is securely hashed in password_hash.
9. Plaintext password is not stored.
10. Password hash is not exposed in session/serialization.
11. Successful setup authenticates the new Administrator.
12. Session regenerates after setup.
13. Successful setup redirects to /dashboard.
14. Client-supplied role_id cannot alter the Administrator role.
15. Client-supplied is_active cannot manipulate activation.
16. Client-supplied password_hash cannot be used.
17. CSRF is required.
18. Invalid setup input is rejected.
19. Setup cannot create a second user after initialization.
20. Replay cannot create another Administrator.
21. Audit record contains no password/hash/credentials.
22. Relevant concurrent first-user protection is tested.

## Regression
After implementation run:
- `php artisan test`
- authoritative `database/Postgres/validation/run_all.sql`
- `npm run build` only as build-time asset verification if required

Confirm:
- 23 business tables remain unchanged
- no schema drift
- existing authentication tests pass
- existing role tests pass
- teacher scope/multi-assignment tests pass
- closed-year rules pass
- IDOR protections pass
- active-user revocation passes
- PostgreSQL validation remains passing
- application runs with `php artisan serve` without Node/npm runtime dependency

## Do Not Implement
Do not add:
- public/general registration
- email verification
- email activation
- email password reset
- OAuth/social login
- JWT/Sanctum/Passport/API tokens
- permission packages
- permission/scope/tenant/registration tables
- schema columns solely for this feature
- role selection during initial setup
- teacher assignment creation during setup
- student/mark/attendance/report CRUD
- any next-phase business modules

## Implementation Boundary
This is a Phase 8 addendum only. Implement only:
- first-run Administrator bootstrap
- login-page registration availability
- setup GET/POST flow
- secure first-user creation
- initial authentication
- audit integration
- validation
- tests
- regression verification

Do not redesign the existing Phase 8 authentication architecture.

## Final Report
Report actual evidence, not assumptions:
1. files created/modified
2. routes added
3. registration availability logic
4. concurrency mechanism
5. password handling
6. role assignment
7. audit behavior
8. authentication behavior
9. tests and results
10. PostgreSQL validation results
11. build result
12. confirmation of unchanged 23-table schema
13. confirmation that Node/npm is not required at application runtime
14. findings/limitations

Do not claim 100% verification unless commands/tests were actually executed.

## Acceptance Criteria
- Fresh installation with zero users exposes initial Administrator registration.
- It is not public self-registration.
- First account is always Administrator.
- Role cannot be client-manipulated.
- Only the first account can use setup.
- Registration never reappears after users have existed, even if all are deactivated.
- Setup is server-side protected.
- POST setup requires CSRF.
- Password is securely hashed.
- Password/hash never enters audit records.
- Initial account is authenticated after setup.
- Session regenerates.
- AuditService records initialization.
- Concurrent first-user creation is safely controlled.
- Four-role architecture remains unchanged.
- UserPolicy remains authoritative for post-installation account management.
- No new schema tables/columns are introduced.
- 23-table schema remains unchanged.
- Existing authentication/authorization/PostgreSQL validation remains passing.
- Application runtime does not require Node/npm.
- No next-phase functionality is implemented.

## Core Principle
Bootstrap the first Administrator securely, then permanently close the public bootstrap path and rely on Administrator-controlled User Management for all subsequent accounts.
