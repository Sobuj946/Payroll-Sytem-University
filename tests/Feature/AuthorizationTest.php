<?php

namespace Tests\Feature;

use App\Models\Payment;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    /** Which roles may open each page (GET). Everything not listed for a role must answer 403. */
    private const PAGES = [
        '/employees' => ['hr_manager', 'payroll_officer', 'admin'],
        '/departments' => ['hr_manager', 'payroll_officer', 'admin'],
        '/designations' => ['hr_manager', 'payroll_officer', 'admin'],
        '/attendance' => ['hr_manager', 'payroll_officer', 'admin'],
        '/leave' => ['hr_manager', 'payroll_officer', 'admin'],
        '/leave-types' => ['hr_manager', 'admin'],
        '/salary' => ['hr_manager', 'payroll_officer', 'admin'],
        '/salary/components' => ['hr_manager', 'payroll_officer', 'admin'],
        '/salary/adjustments' => ['hr_manager', 'payroll_officer', 'admin'],
        '/payroll' => ['hr_manager', 'payroll_officer', 'admin'],
        '/payments' => ['payroll_officer', 'admin'],
        '/payslips' => ['hr_manager', 'payroll_officer', 'admin'],
        '/reports' => ['hr_manager', 'payroll_officer', 'admin'],
        '/users' => ['admin'],
        '/roles' => ['admin'],
        '/audit-logs' => ['admin'],
        '/settings' => ['admin'],
        '/backup' => ['admin'],
    ];

    /** TC-SEC-001: Every role sees exactly the pages its permissions allow (server-side) */
    public function test_each_role_can_open_only_its_pages(): void
    {
        $this->failOnServerError = false;   // a 500 is reported below together with its reason
        $problems = [];

        foreach (['employee', 'hr_manager', 'payroll_officer', 'admin'] as $role) {
            $user = $this->userWithRole($role, $role === 'employee' ? $this->makeEmployee() : null);

            foreach (self::PAGES as $path => $allowed) {
                $expected = in_array($role, $allowed, true) ? 200 : 403;
                $response = $this->actingAs($user)->get($path);

                if ($response->getStatusCode() !== $expected) {
                    $why = $response->baseResponse->exception ?? null;
                    $problems[] = "{$role} opening {$path}: expected {$expected}, got {$response->getStatusCode()}"
                        . ($why ? ' (' . get_class($why) . ': ' . $why->getMessage() . ' at ' . basename($why->getFile()) . ':' . $why->getLine() . ')' : '');
                }
            }
        }

        $this->assertSame([], $problems, "\n" . implode("\n", $problems));
    }

    /** TC-SEC-002: "My ..." pages work for a user linked to an employee and refuse everyone else */
    public function test_my_pages_need_a_linked_employee(): void
    {
        $linked = $this->userWithRole('employee', $this->makeEmployee());
        $notLinked = $this->userWithRole('admin');

        foreach (['/my/attendance', '/my/leave', '/my/leave/create', '/my/payslips'] as $path) {
            $this->actingAs($linked)->get($path)->assertOk();
            $this->actingAs($notLinked)->get($path)->assertForbidden();
        }
    }

    /** TC-SEC-003: An employee cannot create employees, leave types or users by sending the request directly */
    public function test_employee_cannot_send_management_requests(): void
    {
        $user = $this->userWithRole('employee', $this->makeEmployee());

        $this->actingAs($user)->post('/employees', [])->assertForbidden();
        $this->actingAs($user)->post('/leave-types', [])->assertForbidden();
        $this->actingAs($user)->post('/users', [])->assertForbidden();
        $this->actingAs($user)->post('/attendance', [])->assertForbidden();
    }

    /** TC-SEC-004: HR can look at payroll but cannot process, review or approve it */
    public function test_hr_cannot_run_payroll(): void
    {
        [$admin, , $period] = $this->payrollScenario();
        $hr = $this->userWithRole('hr_manager');

        $this->actingAs($hr)->post(route('payroll.process', $period))->assertForbidden();
        $this->actingAs($hr)->post(route('payroll.review', $period))->assertForbidden();
        $this->actingAs($hr)->post(route('payroll.approve', $period))->assertForbidden();
        $this->actingAs($hr)->post(route('payroll.periods.store'), ['month' => now()->format('Y-m')])->assertForbidden();
    }

    /** TC-SEC-005: The payroll officer cannot approve, reopen or close payroll (admin only) */
    public function test_payroll_officer_cannot_approve(): void
    {
        [$admin, , $period] = $this->payrollScenario();
        $officer = $this->userWithRole('payroll_officer');
        $this->processPayroll($period, $admin);

        $this->actingAs($officer)->post(route('payroll.approve', $period))->assertForbidden();
        $this->actingAs($officer)->post(route('payroll.reopen', $period), ['reason' => 'trying it'])->assertForbidden();
        $this->actingAs($officer)->post(route('payroll.close', $period))->assertForbidden();
    }

    /** TC-SEC-006: HR cannot record salary payments */
    public function test_hr_cannot_record_payments(): void
    {
        [$admin, , $period] = $this->payrollScenario();
        $this->approvePayroll($period, $admin);
        $hr = $this->userWithRole('hr_manager');

        $this->actingAs($hr)->post(route('payments.generate', $period))->assertForbidden();
        $this->actingAs($hr)->post(route('payments.bulk'), [])->assertForbidden();
        $this->assertSame(0, Payment::count());
    }

    /** TC-SEC-007: Payroll officer cannot manage users, roles, settings or backups */
    public function test_payroll_officer_cannot_administer(): void
    {
        $officer = $this->userWithRole('payroll_officer');

        $this->actingAs($officer)->post('/users', [])->assertForbidden();
        $this->actingAs($officer)->post('/roles', [])->assertForbidden();
        $this->actingAs($officer)->post('/settings/payroll', [])->assertForbidden();
        $this->actingAs($officer)->post('/backup/download')->assertForbidden();
    }

    /** TC-SEC-008: Responses carry the browser protection headers and signed-in pages are not cached */
    public function test_security_headers_are_sent(): void
    {
        $response = $this->actingAs($this->userWithRole('admin'))->get('/dashboard');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    /** TC-SEC-009: An unknown page shows the friendly 404 page, not technical details */
    public function test_unknown_page_is_a_plain_404(): void
    {
        $response = $this->actingAs($this->userWithRole('admin'))->get('/this-page-does-not-exist');

        $response->assertNotFound();
        $response->assertDontSee('Stack trace');
        $response->assertSee('Page not found');
    }
}
