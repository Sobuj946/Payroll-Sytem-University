<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * php artisan pms:security-check
 * Looks at the settings that matter before the system is shown to others or used for real.
 */
class SecurityCheck extends Command
{
    /** The password the demo accounts were created with. */
    private const DEMO_PASSWORD = 'Password@123';

    protected $signature = 'pms:security-check';

    protected $description = 'Check the settings that matter before the system is shown or used for real';

    public function handle(): int
    {
        $rows = [];
        $warnings = 0;

        $check = function (string $name, bool $ok, string $advice = '') use (&$rows, &$warnings) {
            $rows[] = [$name, $ok ? 'OK' : 'WARNING', $ok ? '' : $advice];
            $warnings += $ok ? 0 : 1;
        };

        $check('APP_DEBUG is off', ! config('app.debug'), 'Error pages show technical details. Set APP_DEBUG=false in .env.');
        $check('Environment is "production"', app()->isProduction(), 'It is "' . app()->environment() . '". In "local" the login page lists the demo accounts.');
        $check('APP_KEY is set', filled(config('app.key')), 'Run: php artisan key:generate');

        $lifetime = (int) config('session.lifetime');
        $check('Sessions end after 60 minutes or less', $lifetime <= 60, "It is {$lifetime} minutes. Set SESSION_LIFETIME=30 in .env.");
        $check('Session data is encrypted', (bool) config('session.encrypt'), 'Set SESSION_ENCRYPT=true in .env.');
        $check('Session cookie is HTTP-only', (bool) config('session.http_only'), 'Set SESSION_HTTP_ONLY=true.');
        $check('Uploads folder cannot run scripts', is_file(public_path('uploads/.htaccess')), 'public/uploads/.htaccess is missing.');
        $check('Logs can be written', is_writable(storage_path('logs')), 'Make storage/logs writable.');

        $demo = User::all()->filter(fn ($user) => Hash::check(self::DEMO_PASSWORD, $user->password));
        $check('No account still uses the demo password', $demo->isEmpty(), 'Change the password of: ' . $demo->pluck('email')->implode(', '));

        $admins = User::where('status', 'active')->whereHas('role', fn ($q) => $q->where('name', 'admin'))->count();
        $check('At least one active administrator', $admins > 0, 'There is no active administrator.');

        $this->table(['Check', 'Result', 'What to do'], $rows);
        $this->newLine();

        if ($warnings === 0) {
            $this->info('Everything looks fine.');

            return self::SUCCESS;
        }

        $this->warn("{$warnings} item(s) need attention. For a demonstration on your own computer, the first four are optional.");

        return self::FAILURE;
    }
}
