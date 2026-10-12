<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** User accounts. Only an Admin can reach these pages (users.manage). */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('role', 'employee')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . trim($request->q) . '%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->filled('role_id'), fn ($q) => $q->where('role_id', $request->role_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('users.index', ['users' => $users, 'roles' => Role::orderBy('label')->get()]);
    }

    public function create()
    {
        return view('users.form', $this->formData(new User(['status' => 'active'])));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules() + [
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], $this->messages());

        $this->checkEmployeeLink($data);

        $user = User::create($data);

        AuditService::log('created', 'users', "User account {$user->email} was created with the role {$user->role->label}", $user->id);

        return redirect()->route('users.index')->with('success', "The account for {$user->name} has been created. Please give them the password and ask them to change it.");
    }

    public function edit(User $user)
    {
        return view('users.form', $this->formData($user));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate($this->rules($user), $this->messages());

        $this->checkEmployeeLink($data);
        $this->guard($user, $data, $request->user());

        $oldRole = $user->role->label;
        $user->update($data);
        $user->load('role');

        AuditService::log('updated', 'users', "User account {$user->email} was updated", $user->id);

        if ($oldRole !== $user->role->label) {
            AuditService::log('role_changed', 'users', "Role of {$user->email} changed from {$oldRole} to {$user->role->label}", $user->id);
        }

        return redirect()->route('users.index')->with('success', "The account for {$user->name} has been updated.");
    }

    public function toggleStatus(Request $request, User $user)
    {
        $new = $user->status === 'active' ? 'inactive' : 'active';
        $this->guard($user, ['role_id' => $user->role_id, 'status' => $new], $request->user());

        $user->update(['status' => $new]);
        $word = $new === 'active' ? 'activated' : 'deactivated';

        if ($new === 'inactive') {
            $this->endSessions($user);
        }

        AuditService::log($word, 'users', "User account {$user->email} was {$word}", $user->id);

        return back()->with('success', "The account for {$user->name} has been {$word}.");
    }

    /** An Admin sets a new password for someone who forgot theirs. Their open sessions are ended. */
    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'password.required' => 'Please enter the new password.',
            'password.confirmed' => 'The new password and its confirmation do not match.',
            'password.min' => 'The new password must be at least 8 characters.',
            'password.letters' => 'The new password must contain at least one letter.',
            'password.numbers' => 'The new password must contain at least one number.',
        ]);

        $user->update(['password' => $data['password']]);
        $this->endSessions($user);

        AuditService::log('password_reset', 'users', "The password of {$user->email} was reset by an administrator", $user->id);

        return back()->with('success', "A new password has been set for {$user->name}. They are signed out everywhere.");
    }

    // ------------------------------------------------------------------

    private function rules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user?->id)],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id', Rule::unique('users', 'employee_id')->ignore($user?->id)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Please enter the name.',
            'email.required' => 'Please enter an email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'Another account already uses this email address.',
            'role_id.required' => 'Please choose a role.',
            'employee_id.unique' => 'This employee already has a user account.',
            'password.required' => 'Please enter a password.',
            'password.confirmed' => 'The password and its confirmation do not match.',
            'password.min' => 'The password must be at least 8 characters.',
            'password.letters' => 'The password must contain at least one letter.',
            'password.numbers' => 'The password must contain at least one number.',
        ];
    }

    /** An account with the Employee role must belong to an employee, or its "My ..." pages would be empty. */
    private function checkEmployeeLink(array $data): void
    {
        $role = Role::find($data['role_id']);

        if ($role?->name === 'employee' && empty($data['employee_id'])) {
            throw new BusinessRuleException('An account with the Employee role must be linked to an employee record.');
        }
    }

    /** Stops an Admin from locking everybody out. */
    private function guard(User $target, array $new, User $actor): void
    {
        if ($target->id === $actor->id && ($new['status'] !== 'active' || (int) $new['role_id'] !== $target->role_id)) {
            throw new BusinessRuleException('You cannot change your own role or deactivate your own account. Ask another administrator.');
        }

        $adminRoleId = Role::where('name', 'admin')->value('id');
        $losesAdmin = $target->role_id === $adminRoleId && $target->isActive()
            && ((int) $new['role_id'] !== $adminRoleId || $new['status'] !== 'active');

        if ($losesAdmin && User::where('role_id', $adminRoleId)->where('status', 'active')->where('id', '!=', $target->id)->doesntExist()) {
            throw new BusinessRuleException('At least one active administrator must remain.');
        }
    }

    private function endSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }

    private function formData(User $user): array
    {
        return [
            'user' => $user,
            'roles' => Role::orderBy('label')->get(),
            // Only employees who do not have an account yet, plus the one already linked to this user.
            'employees' => Employee::where(fn ($q) => $q->whereDoesntHave('user')->orWhere('id', $user->employee_id))
                ->orderBy('employee_code')->get(),
        ];
    }
}
