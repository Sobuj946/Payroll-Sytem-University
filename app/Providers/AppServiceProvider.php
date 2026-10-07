<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
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

        // A permission key (e.g. "employees.manage") works as a Gate ability, so
        // @can('employees.manage') and $this->authorize(...) both use the role's permissions.
        // Abilities that are not permission keys fall through to the model policies.
        Gate::before(function ($user, string $ability) {
            return $user->hasPermission($ability) ? true : null;
        });

        // Company name for page titles. Error pages must never fail because of the database.
        $companyName = null;
        View::composer(['layouts.*', 'partials.*', 'auth.*', 'errors.*', 'account.*'], function ($view) use (&$companyName) {
            if ($companyName === null) {
                try {
                    $companyName = Setting::get('company_name', config('app.name'));
                } catch (Throwable) {
                    $companyName = config('app.name');
                }
            }
            $view->with('companyName', $companyName);
        });
    }
}
