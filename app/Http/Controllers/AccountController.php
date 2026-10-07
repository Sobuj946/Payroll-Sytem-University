<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function editPassword()
    {
        return view('account.password');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.required' => 'Please enter your current password.',
            'current_password.current_password' => 'The current password is not correct.',
            'password.required' => 'Please enter a new password.',
            'password.confirmed' => 'The new password and its confirmation do not match.',
            'password.min' => 'The new password must be at least 8 characters.',
            'password.letters' => 'The new password must contain at least one letter.',
            'password.numbers' => 'The new password must contain at least one number.',
        ]);

        $user = $request->user();
        $user->update(['password' => $validated['password']]); // hashed by the model cast

        AuditService::log('password_changed', 'auth', "{$user->name} changed their password", $user->id);

        return redirect()->route('account.password')->with('success', 'Your password has been changed.');
    }
}
