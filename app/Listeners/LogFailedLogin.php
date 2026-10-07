<?php

namespace App\Listeners;

use App\Services\AuditService;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        $email = $event->credentials['email'] ?? 'unknown';

        AuditService::log('login_failed', 'auth', "Failed sign-in attempt for {$email}", null, $event->user?->id);
    }
}
