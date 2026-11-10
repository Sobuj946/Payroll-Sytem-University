<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // The same password rule everywhere, including the "forgot password" screen.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // A permission key (e.g. "employees.manage") works as a Gate ability, so
        // @can('employees.manage') and $this->authorize(...) both use the role's permissions.
        // Abilities that are not permission keys fall through to the model policies.
        Gate::before(function ($user, string $ability) {
            return $user->hasPermission($ability) ? true : null;
        });

        // Names for page titles, the sidebar and the login page. Error pages must never fail because of the database.
        $branding = null;
        View::composer(['layouts.*', 'partials.*', 'auth.*', 'errors.*', 'account.*'], function ($view) use (&$branding) {
            if ($branding === null) {
                try {
                    $name = Setting::get('company_name', config('app.name'));
                    $branding = [
                        'companyName' => $name,
                        'companyShort' => Setting::get('company_short_name') ?: $name,
                        'systemTitle' => Setting::get('system_title') ?: 'Payroll Management System',
                    ];
                } catch (Throwable) {
                    $branding = ['companyName' => config('app.name'), 'companyShort' => config('app.name'), 'systemTitle' => 'Payroll Management System'];
                }
            }
            $view->with($branding);
        });
    }
}
