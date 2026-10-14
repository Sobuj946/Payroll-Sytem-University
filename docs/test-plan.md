# Test plan: Payroll Management System

## 1. Purpose and scope

This plan describes how the system is tested before it is handed over: what is tested, at which level, with which data, and when testing is finished.
It covers every module: authentication, authorization, employees, departments, attendance, leave, salary, payroll, payments, salary slips, reports and administration.

## 2. Test levels

| Level | What it checks | How | Where |
|---|---|---|---|
| **Unit testing** | One rule at a time: working hours, late status, leave days, salary structure maths, report filters | Automated (PHPUnit), no browser | `tests/Unit` (20 cases) |
| **Integration testing** | Several parts working together: a request goes through the permission check, validation, service, database and audit log | Automated (PHPUnit, real MySQL test database) | `tests/Feature` (170 cases) |
| **System testing** | The finished application used the way an organization uses it, month-end from start to finish, in a browser | Manual script (section 6) | browser |
| **User acceptance testing (UAT)** | People in each role confirm the system fits their work | Manual scenarios (section 7), signed off by the users | browser |

All 190 automated cases are listed with Scenario, Preconditions, Input and Expected Result in `docs/test-cases.md` (and `docs/test-cases.csv` for Excel).

## 3. Test environment

- Same stack as the application: PHP 8.2+, Laravel, MySQL 8.
- A separate database named `payroll_pms_test`. The test run empties and rebuilds it, so the tests refuse to run against any database whose name does not end in `_test`.
- Roles, permissions, settings, departments, leave types and salary components are seeded fresh for every test, as in a new installation.
- No internet connection is needed except for the PDF test, which needs the PDF package installed.

Run:

```
php artisan test
php artisan test --log-junit storage/logs/junit.xml
python3 docs/fill_results.py          # fills Actual Result and Status in docs/test-results.csv and .md
```

## 4. Test data

- **September 2026** is used for payroll because it has 30 days and 4 Fridays (the weekly off day), so 26 working days. With basic 50,000, House Rent 20% and Medical 5,000 the gross is 65,000 and one day is worth exactly 2,500, so every expected amount can be checked by hand.
- Leave tests fix the date to Monday 12 October 2026 so that results do not depend on the day the tests are run.
- Tax slabs are removed in the payroll tests (to keep the figures simple) and added back in the tax tests with their own slabs. The slabs in the tests are examples, not official rates.

## 5. Coverage by module

| Module | Automated cases |
|---|---|
| Authentication | 13 |
| Security and authorization | 9 |
| Employees | 13 |
| Departments | 4 |
| Attendance | 10 |
| Leave | 17 |
| Salary | 9 |
| Payroll | 31 |
| Payments | 16 |
| Salary slips | 7 |
| Reports | 11 |
| Administration | 20 |
| Desktop packaging | 10 |
| Unit tests | 20 |
| **Total** | **190** |

What the automated cases deliberately include: the permission matrix for every role and page, duplicate prevention at both application and database level, boundary values (late by one minute, a 150% rate, a future date), transaction rollback, locked payroll, audit entries, and the rule that no figure is invented (no slab means no tax).

What they do not cover, and the manual checks that replace them:

| Not covered by automation | Manual check |
|---|---|
| How pages look: colour themes, dark mode, print layout of slips and reports | Open each page in the light and dark theme and print one slip and one report |
| PDF layout (the automated test only checks that a PDF is produced) | Open a salary slip PDF and the all-slips PDF and read them |
| Browser behaviour: confirmation dialogs, collapsing rows, tick-all box, reject dialog | Walk through section 6 |
| Two people acting at the same moment (row locks) | Approve one leave request from two browsers at once; it is approved only once |
| Restoring a backup | Restore a downloaded backup into a spare database and open the system on it |

## 6. System test: a month-end walkthrough

Use the demo data. Sign in as the person named in each step.

| # | Who | Step | Expected |
|---|---|---|---|
| 1 | HR Manager | Record attendance for one day for everyone; mark one person late and one absent | Saved; the monthly view shows L and A |
| 2 | Employee | Request two days of casual leave | Appears as Pending in My leave; balance shows days waiting |
| 3 | HR Manager | Approve it | Employee's balance drops by two days |
| 4 | Payroll Officer | Open Overtime, bonus and other items; create the month; add an overtime item | Item listed |
| 5 | Payroll Officer | Open Payroll, check the attendance warning, then Process | Totals appear; open one employee and check the lines by hand |
| 6 | Payroll Officer | Mark as reviewed; try to add an item | Item refused: payroll is locked |
| 7 | Payroll Officer | Try to approve | Not possible (no button, and 403 if the address is typed) |
| 8 | Administrator | Approve; open the audit log | "approved" entry with the administrator's name |
| 9 | Payroll Officer | Prepare payments; record one bank payment and bulk-pay the rest | Period becomes Paid |
| 10 | Employee | Open My salary slips, view and download the slip | Amounts match step 5 |
| 11 | HR Manager | Open the monthly payroll report and the payroll CSV | Totals equal the payroll page |
| 12 | Administrator | Download a backup | SQL file downloads and is listed in the audit log |

## 7. User acceptance testing

Each role works through its own scenarios. Result is Pass or Fail with a comment; the person who tested signs the sheet.

**HR Manager**

| ID | Scenario | Acceptance criterion | Result |
|---|---|---|---|
| UAT-HR-01 | Add a new employee with photo and bank details | The form is clear, errors are understandable, the profile shows everything entered | |
| UAT-HR-02 | Find an employee by name, then by department | The list is easy to filter and read | |
| UAT-HR-03 | Record a day's attendance for 10 people | Takes only a few minutes and corrections are easy | |
| UAT-HR-04 | Approve one request and reject one with a reason | The employee sees the outcome and the reason | |
| UAT-HR-05 | Open a payroll report and print it | Readable on paper; I cannot change salaries | |

**Payroll Officer**

| ID | Scenario | Acceptance criterion | Result |
|---|---|---|---|
| UAT-PO-01 | Set up a new allowance and give it to two employees | Easy to find and change; history is kept | |
| UAT-PO-02 | Enter overtime and a loan instalment for the month | Clear what is added and what is deducted | |
| UAT-PO-03 | Process payroll and check one salary against my own calculation | The figures match | |
| UAT-PO-04 | Record payments, one failed and retried | The status is always clear | |
| UAT-PO-05 | Produce and download salary slips | They look like a real salary statement | |

**Employee**

| ID | Scenario | Acceptance criterion | Result |
|---|---|---|---|
| UAT-EM-01 | Check my attendance for the month | I see only my own records | |
| UAT-EM-02 | Request leave and later cancel it | I understand my balance and the status | |
| UAT-EM-03 | Download my salary slip | I can find it without help | |
| UAT-EM-04 | Change my password | The rules are explained and it works | |

**Administrator**

| ID | Scenario | Acceptance criterion | Result |
|---|---|---|---|
| UAT-AD-01 | Create a user, link them to an employee, deactivate them later | The person is blocked straight away | |
| UAT-AD-02 | Change a role's permissions | The change shows in what that role can open | |
| UAT-AD-03 | Update company name, logo, holidays and tax slabs | The change shows on the next salary slip | |
| UAT-AD-04 | Find who approved last month's payroll | The audit log answers within a minute | |

| Tester | Role | Date | Overall result | Signature |
|---|---|---|---|---|
| | | | | |

## 8. When testing is finished

- All automated cases pass (skipped cases are explained, for example the PDF case when the PDF package is not installed).
- The system test in section 6 and the manual checks in section 5 pass.
- UAT is signed off by at least one person in each role.
- No open finding of High or Medium severity in the security review.
- Failures found in testing are fixed, and the affected cases run again.
