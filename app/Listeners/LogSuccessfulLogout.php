<?php

namespace App\Listeners;

use App\Services\AuditService;
use Illuminate\Auth\Events\Logout;

class LogSuccessfulLogout
{
    public function handle(Logout $event): void
    {
        $user = $event->user;

        if ($user) {
            AuditService::log('logout', 'auth', "{$user->name} signed out", $user->id, $user->id);
        }
    }
}
