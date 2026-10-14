<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\LeaveTypeSeeder;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SalaryComponentSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Throwable;

/**
 * php artisan pms:install
 * First-time setup on a new computer: creates the tables, loads the basic data (roles, permissions,
 * settings, leave types, salary components, departments) and creates the first Administrator.
 * Add --demo to also load the demonstration employees.
 */
class InstallSystem extends Command
{
    protected $signature = 'pms:install
                            {--name= : Administrator name}
                            {--email= : Administrator email}
                            {--password= : Administrator password}
                            {--company= : Organization name}
                            {--demo : Also load the demonstration employees, attendance and leave}';

    protected $description = 'First-time setup: tables, basic data and the first administrator';

    public function handle(): int
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->error('The database cannot be reached. Check the DB_ lines in the .env file and that MySQL is running.');

            return self::FAILURE;
        }

        if (Schema::hasTable('users') && User::count() > 0) {
            $this->error('The system is already installed (user accounts exist). Nothing was changed.');

            return self::FAILURE;
        }

        // Ask first, so that a typing mistake does not leave a half-installed system.
        $admin = $this->askForAdministrator();
        if ($admin === null) {
            return self::FAILURE;
        }

        $this->info('Creating the tables ...');
        $this->call('migrate', ['--force' => true]);

        $this->info('Loading the basic data ...');
        foreach ([RolePermissionSeeder::class, SettingSeeder::class, OrganizationSeeder::class, LeaveTypeSeeder::class, SalaryComponentSeeder::class] as $seeder) {
            $this->callSilent('db:seed', ['--class' => $seeder, '--force' => true]);
        }

        User::create([
            'name' => $admin['name'],
            'email' => $admin['email'],
            'password' => $admin['password'],
            'role_id' => Role::where('name', 'admin')->value('id'),
            'status' => 'active',
        ]);

        $company = $this->option('company') ?: ($this->input->isInteractive() ? $this->ask('Organization name', 'My Organization') : null);
        if ($company) {
            Setting::put('company_name', $company);
        }

        File::ensureDirectoryExists(public_path('uploads/employees'));

        if ($this->option('demo')) {
            $this->info('Loading the demonstration data ...');
            $this->callSilent('db:seed', ['--class' => DemoDataSeeder::class, '--force' => true]);
            $this->warn('The demonstration accounts all use the password Password@123. Delete or change them before real use.');
        }

        $this->newLine();
        $this->info("Installed. Sign in as {$admin['email']}.");
        $this->line('Next:');
        $this->line('  1. php artisan pms:install-assets      (once, makes the system work without internet)');
        $this->line('  2. Open Settings and check the company details, holidays and the tax slabs.');
        $this->warn('     The tax slabs that come with the system are SAMPLE rates. Replace them with the rules your organization follows.');
        $this->line('  3. php artisan pms:security-check');

        return self::SUCCESS;
    }

    /** Reads the administrator's details from the options or by asking; null if they are not valid. */
    private function askForAdministrator(): ?array
    {
        $interactive = $this->input->isInteractive();

        $name = $this->option('name') ?: ($interactive ? $this->ask('Administrator name') : null);
        $email = $this->option('email') ?: ($interactive ? $this->ask('Administrator email (used to sign in)') : null);
        $password = $this->option('password');

        if (! $password && $interactive) {
            $password = $this->secret('Password (at least 8 characters, letters and numbers)');

            if ($password !== $this->secret('Type the password again')) {
                $this->error('The two passwords do not match.');

                return null;
            }
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:120'],
                'password' => ['required', Password::min(8)->letters()->numbers()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return null;
        }

        return ['name' => $name, 'email' => $email, 'password' => $password];
    }
}
