<?php

namespace Tests\Feature;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    /** TC-AUTH-001: Employee attempts to open Admin pages */
    public function test_employee_cannot_open_admin_pages(): void
    {
        $user = $this->userWithRole('employee', $this->makeEmployee());

        foreach (['/users', '/roles', '/settings', '/audit-logs', '/backup'] as $path) {
            $this->actingAs($user)->get($path)->assertForbidden();
        }
    }

    /** TC-AUTH-002: Sign in with correct details */
    public function test_sign_in_with_correct_details(): void
    {
        $user = $this->userWithRole('admin');

        $this->post('/login', ['email' => $user->email, 'password' => 'Password1'])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'login', 'user_id' => $user->id]);
    }

    /** TC-AUTH-003: Sign in with a wrong password */
    public function test_sign_in_with_wrong_password_fails(): void
    {
        $user = $this->userWithRole('admin');

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'login_failed']);
    }

    /** TC-AUTH-004: Deactivated account cannot use the system */
    public function test_deactivated_account_is_signed_out(): void
    {
        $user = $this->userWithRole('admin');
        $user->update(['status' => 'inactive']);

        $this->post('/login', ['email' => $user->email, 'password' => 'Password1']);

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /** TC-AUTH-005: An account deactivated during an open session is signed out on the next request */
    public function test_deactivation_ends_an_open_session(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->update(['status' => 'inactive']);

        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    /** TC-AUTH-006: Repeated wrong passwords lock the sign-in for a while */
    public function test_sign_in_is_blocked_after_repeated_failures(): void
    {
        $user = $this->userWithRole('admin');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        // Even the right password is refused while the lock is on.
        $this->post('/login', ['email' => $user->email, 'password' => 'Password1']);

        $this->assertGuest();
    }

    /** TC-AUTH-007: A visitor who is not signed in is sent to the sign-in page */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/employees')->assertRedirect('/login');
    }

    /** TC-AUTH-008: There is no public registration */
    public function test_registration_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'Someone', 'email' => 'someone@example.test', 'password' => 'Password1'])->assertStatus(404);
    }

    /** TC-AUTH-009: Sign out ends the session and is recorded */
    public function test_sign_out(): void
    {
        $user = $this->userWithRole('admin');

        $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'logout', 'user_id' => $user->id]);
    }

    /** TC-AUTH-010: Change own password with the correct current password */
    public function test_change_own_password(): void
    {
        $user = $this->userWithRole('hr_manager');

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'Password1',
            'password' => 'NewPassword9',
            'password_confirmation' => 'NewPassword9',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewPassword9', $user->fresh()->password));
    }

    /** TC-AUTH-011: Password change is refused for a wrong current password or a weak new password */
    public function test_password_change_is_validated(): void
    {
        $user = $this->userWithRole('hr_manager');

        $this->actingAs($user)->from('/account/password')->put('/account/password', [
            'current_password' => 'not-my-password',
            'password' => 'NewPassword9',
            'password_confirmation' => 'NewPassword9',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->from('/account/password')->put('/account/password', [
            'current_password' => 'Password1',
            'password' => 'onlyletters',
            'password_confirmation' => 'onlyletters',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('Password1', $user->fresh()->password));
    }

    /** TC-AUTH-012: Password reset link is sent to a known email */
    public function test_password_reset_link_is_sent(): void
    {
        Notification::fake();
        $user = $this->userWithRole('employee', $this->makeEmployee());

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    /** TC-AUTH-013: Passwords are stored hashed, never as plain text */
    public function test_password_is_hashed(): void
    {
        $user = $this->userWithRole('admin');

        $this->assertNotSame('Password1', $user->fresh()->password);
        $this->assertTrue(Hash::check('Password1', $user->fresh()->password));
    }
}
