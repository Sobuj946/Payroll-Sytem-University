<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Thrown when a request is valid input but breaks a business rule
 * (e.g. approving a request that was already rejected). The user gets a friendly message,
 * never a stack trace.
 */
class BusinessRuleException extends Exception
{
    public function render(Request $request): RedirectResponse
    {
        return redirect()->back()->with('error', $this->getMessage());
    }
}
