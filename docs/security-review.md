# Security Review: Payroll Management System

Phase 14 of the project. This document records what was checked, what was found and what was done about it.

## 1. Scope and method

- **Scope:** all application code written in phases 3 to 13 (about 150 files): routes, controllers, services, form requests, models, Blade views, migrations, seeders, upload handling and configuration.
- **Method:** a line-by-line reading of the code, plus scripted checks over the whole code base:
  - every route used in a view or controller exists;
  - every route has a permission check (except the dashboard and "change my password", which are open to any signed-in user by design);
  - every form that changes data contains a CSRF token;
  - the only place that prints unescaped HTML was found and removed;
  - every raw SQL fragment uses bound parameters;
  - Blade directives and PHP brackets are balanced in every file.
- **Not done:** the checks above were made on the source code. The application was not attacked with an automated penetration-testing tool. Phase 15 adds automated tests that prove the permission rules.

## 2. Controls in place

| Area | Control | Where |
|---|---|---|
| Authentication | Passwords hashed with Laravel's hasher (bcrypt); never stored or logged in plain text | `User` model cast, `AccountController`, `UserController` |
| | Login throttling: 5 tries per email and address, plus 20 per minute per address | Breeze `LoginRequest`, `routes/auth.php` |
| | Session id replaced at sign-in; sessions destroyed at sign-out | Breeze controllers |
| | Deactivated accounts are signed out on their next request | `EnsureAccountActive` |
| | One password rule everywhere: 8+ characters with letters and numbers | `AppServiceProvider` (`Password::defaults`) |
| | Password reset by token, throttled; administrators can set a new password and end the person's sessions | `routes/auth.php`, `UserController::resetPassword` |
| Authorization | Every module route sits behind `permission:` middleware, checked on the server | `routes/web.php`, `CheckPermission` |
| | "Own data only" for employees is enforced in the controller, not by hiding links | `PayslipController::own`, `MyLeaveController::cancel`, `AttendanceController::mine` |
| | Four-eyes rules: nobody decides their own leave request; payroll approval is Admin only; salary changes need `salary.manage` | `LeaveService`, `PayrollController`, `EmployeeController::update` |
| | Privilege escalation closed: user, settings and backup permissions cannot be given to other roles; an administrator cannot demote or deactivate themselves or the last administrator | `RoleController::RESERVED`, `UserController::guard` |
| Input | Server-side validation for every form with friendly messages; client-side checks are only a convenience | Form requests and controllers |
| | Bangladesh mobile number, NID (10/13/17 digits), bank account and age checks | `EmployeeRequest`, `PaymentController` |
| | Duplicates stopped in the application and by database unique keys (employee ID/email/NID, attendance per day, payroll per period and employee) | migrations |
| Injection | Eloquent and bound parameters everywhere; the few raw fragments are constants or bound | scan result in section 1 |
| XSS | Blade escapes all output; no unescaped output remains | scan result |
| CSRF | `@csrf` on every state-changing form; state changes use POST/PUT/PATCH/DELETE only | all views |
| CSV injection | Cells that start with `=`, `+`, `-` or `@` are prefixed so Excel does not run them | `ReportController::csvCell` |
| Uploads | Photo and logo: image types only (PNG/JPG), size limited, random file name, extension taken from the real file type, old file deleted; the upload folder cannot run scripts | `EmployeeController`, `SettingsController`, `public/uploads/.htaccess` |
| Data integrity | Payroll, leave approval, payments and backups run inside database transactions with row locks; approved payroll is locked; corrections need a reason and are logged | `PayrollService`, `LeaveService`, `PaymentService` |
| Privacy | Bank account numbers are masked on slips and reports; employees see only their own slips | `PayslipService`, `ReportService` |
| Audit | Sign-in, sign-out, failed sign-in, every create/update, salary and permission changes, approvals, payments, exports and backups are logged with user, time and address. The application has no screen or route that edits or deletes a log entry | `AuditService`, `AuditLogController` |
| Errors | Friendly 403, 404, 419, 429, 500 and 503 pages; business-rule errors show a plain message; technical errors go to `storage/logs` | `resources/views/errors`, `BusinessRuleException` |
| Browser | Headers against framing and file-type guessing; signed-in pages are not cached; content security policy (scripts only from this site, no inline scripts, forms post only to this site) | `SecurityHeaders`, no inline scripts in any view |
| Rate limits | Password change, administrator password reset, backup and exports are throttled | `routes/web.php` |

## 3. Findings

| # | Severity | Finding | Status |
|---|---|---|---|
| 1 | Medium | The Roles page could give `users.manage`, `settings.manage` or `backup.manage` to a non-administrator role, which would let that role raise its own access | **Fixed.** These permissions are reserved for the Administrator and the server ignores any attempt to grant them |
| 2 | Medium | (Found in phase 8, fixed then) HR could change a salary through the employee edit form, against the permission matrix | **Fixed** in phase 8 |
| 3 | Low | The "forgot password" screen used the framework's default password rule (8 characters only), weaker than the rest of the system | **Fixed.** One rule everywhere |
| 4 | Low | No limit per address on sign-in attempts (only per account), which does not stop one address trying many accounts | **Fixed.** 20 per minute per address |
| 5 | Low | Changing a password, resetting a password, backups and exports were not throttled | **Fixed.** Throttles added |
| 6 | Low | Pages of signed-in users could be kept in the browser cache and shown again with the Back button after signing out | **Fixed.** `Cache-Control: no-store` for signed-in users |
| 7 | Low | No headers against framing or file-type guessing | **Fixed.** `SecurityHeaders` |
| 8 | Low | On an Apache server a script placed in the upload folder could be run | **Fixed.** `.htaccess` in `public/uploads` (also needed for the folder to not list its files) |
| 9 | Info | One view printed HTML unescaped (constant text only, no user data) | **Fixed.** Replaced with normal Blade |
| 10 | Info | No pages for HTTP 429 and 503 | **Fixed.** Added |
| 10b | Low | No content security policy, because pages used a CDN and inline scripts | **Fixed in phase 16.** Assets installed locally, every inline script and handler moved to `app.js`, policy sent |
| 11 | Configuration | With `APP_DEBUG=true` Laravel shows technical details to the user | **Check added:** `php artisan pms:security-check` reports it |
| 12 | Configuration | The demo accounts share one known password | **Check added:** the same command lists accounts that still use it |

## 4. Accepted risks and limits

- **The content security policy needs the local asset files.** It is sent only when Bootstrap is installed in the project (`php artisan pms:install-assets`) and debug mode is off. Before that the pages use a CDN and no policy is sent. Styles still allow inline `style="..."` attributes (progress bars and widths); scripts do not allow anything inline. *(Changed in phase 16; this was an open item before.)*
- **The audit log is protected by the application only.** For stronger protection on a real server, give the database user used by the application only `INSERT` and `SELECT` rights on `audit_logs`.
- **Backups hold everything**, including bank details and password hashes. Only the Administrator can download one, each download is logged, and the page warns about storage.
- **Session storage** is the database. Setting `SESSION_ENCRYPT=true` and a short `SESSION_LIFETIME` is recommended (the check command says so).
- **Search boxes** do not escape `%` and `_` in the search text. A person can therefore match more rows than typed, but cannot reach data they are not allowed to see, because the permission check is separate.
- **Some limits are fixed in code:** leave can be requested up to 30 days back, an employee must be 18 or older, uploads are limited to 2 MB (photo) and 1 MB (logo). They are not legal rules, and can be moved to Settings if required.
- **Payroll limits** (no final settlement for people who leave mid-month, no history of basic salary) are described in the payroll documentation, not here.

## 5. Before a real deployment

1. Set `APP_ENV=production` and `APP_DEBUG=false` in `.env`.
2. Set `SESSION_LIFETIME=30`, `SESSION_ENCRYPT=true`, and `SESSION_SECURE_COOKIE=true` if the system is served over HTTPS.
3. Change the password of every demo account, or deactivate it.
4. Run `php artisan pms:security-check` and clear every warning.
5. Run `composer audit` to check the installed packages for known problems, and update them.
6. Keep `.env` and `storage/` out of version control and out of any web-served folder.
7. Make a backup and test restoring it on a spare database.
