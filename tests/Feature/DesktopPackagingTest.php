<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Assets;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DesktopPackagingTest extends TestCase
{
    /** Files this test put in place and must remove again. */
    private array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    /** Puts stand-in asset files in place, unless the real ones are already installed. */
    private function withLocalAssets(): void
    {
        foreach (Assets::FILES as $file) {
            $path = public_path('assets/vendor/' . $file);

            if (! is_file($path)) {
                File::ensureDirectoryExists(dirname($path));
                file_put_contents($path, '/* stand-in for a test */');
                $this->created[] = $path;
            }
        }
    }

    private function install(array $overrides = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('pms:install', array_merge([
            '--name' => 'Site Administrator',
            '--email' => 'admin@school.example',
            '--password' => 'Admin12345',
            '--company' => 'Dhaka Model School',
        ], $overrides));
    }

    /** TC-PKG-001: First-time install creates the administrator, the basic data and the company name */
    public function test_install_creates_the_administrator(): void
    {
        $this->install()->assertExitCode(0);

        $admin = User::where('email', 'admin@school.example')->firstOrFail();
        $this->assertSame('admin', $admin->role->name);
        $this->assertSame('active', $admin->status);
        $this->assertSame('Dhaka Model School', Setting::get('company_name'));
        $this->assertGreaterThan(0, Role::count());
    }

    /** TC-PKG-002: Install refuses to run on a system that already has users */
    public function test_install_runs_only_once(): void
    {
        $this->install()->assertExitCode(0);

        $this->install(['--email' => 'second@school.example'])->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'second@school.example']);
    }

    /** TC-PKG-003: A weak password or a bad email stops the install and nothing is created */
    public function test_install_checks_the_administrator_details(): void
    {
        $this->install(['--password' => 'short'])->assertExitCode(1);
        $this->install(['--email' => 'not-an-email'])->assertExitCode(1);

        $this->assertSame(0, User::count());
    }

    /** TC-PKG-004: With local assets and debug off, a strict content security policy is sent: scripts only from this site */
    public function test_strict_policy_when_assets_are_local(): void
    {
        $this->withLocalAssets();
        config(['app.debug' => false]);

        $response = $this->get('/login');

        $policy = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($policy);

        $directives = array_map('trim', explode(';', $policy));
        $this->assertContains("script-src 'self'", $directives);
        $this->assertContains("object-src 'none'", $directives);
        $this->assertContains("frame-ancestors 'self'", $directives);
        $this->assertContains("form-action 'self'", $directives);
    }

    /** TC-PKG-005: Pages load Bootstrap from the project's own files, not from the internet */
    public function test_pages_use_local_files(): void
    {
        $this->withLocalAssets();

        $this->get('/login')
            ->assertOk()
            ->assertSee('assets/vendor/bootstrap/bootstrap.min.css', false)
            ->assertSee('assets/vendor/bootstrap/bootstrap.bundle.min.js', false)
            ->assertDontSee('cdn.jsdelivr.net', false);
    }

    /** TC-PKG-006: Until the files are installed, the pages use the CDN and no policy is sent (so the page is not broken) */
    public function test_fallback_to_cdn_without_local_files(): void
    {
        if (Assets::local()) {
            $this->markTestSkipped('The local asset files are installed on this computer, so the fallback cannot be tested.');
        }

        config(['app.debug' => false]);

        $this->get('/login')->assertOk()->assertSee('cdn.jsdelivr.net', false)->assertHeaderMissing('Content-Security-Policy');
    }

    /** TC-PKG-007: In debug mode no policy is sent, so Laravel's own error pages keep working */
    public function test_no_policy_in_debug_mode(): void
    {
        $this->withLocalAssets();
        config(['app.debug' => true]);

        $this->get('/login')->assertHeaderMissing('Content-Security-Policy');
    }

    /** TC-PKG-008: The asset command downloads every file into the chosen folder */
    public function test_asset_command_downloads_the_files(): void
    {
        Http::fake(['*' => Http::response(str_repeat('a', 1500), 200)]);
        $folder = storage_path('framework/testing/vendor-' . uniqid());

        try {
            $this->artisan('pms:install-assets', ['--path' => $folder])->assertExitCode(0);

            foreach (['bootstrap/bootstrap.min.css', 'bootstrap/bootstrap.bundle.min.js', 'bootstrap-icons/bootstrap-icons.min.css', 'bootstrap-icons/fonts/bootstrap-icons.woff2', 'bootstrap-icons/fonts/bootstrap-icons.woff'] as $file) {
                $this->assertFileExists($folder . '/' . $file);
            }
        } finally {
            File::deleteDirectory($folder);
        }
    }

    /** TC-PKG-009: A failed download (an error page instead of the file) is refused and nothing is saved */
    public function test_asset_command_rejects_a_bad_download(): void
    {
        Http::fake(['*' => Http::response('<html>Not found</html>' . str_repeat(' ', 1500), 404)]);
        $folder = storage_path('framework/testing/vendor-' . uniqid());

        try {
            $this->artisan('pms:install-assets', ['--path' => $folder])->assertExitCode(1);

            $this->assertFileDoesNotExist($folder . '/bootstrap/bootstrap.min.css');
        } finally {
            File::deleteDirectory($folder);
        }
    }

    /** TC-PKG-010: The launchers, the icon and the production settings template are part of the project */
    public function test_launcher_files_exist(): void
    {
        foreach ([
            'desktop/windows/launch.ps1', 'desktop/windows/Start-Payroll.bat', 'desktop/windows/Install-Shortcut.ps1',
            'desktop/linux/launch.sh', 'desktop/linux/install.sh',
            'desktop/icons/payroll.ico', 'desktop/icons/payroll.png', 'desktop/README.md',
        ] as $file) {
            $this->assertFileExists(base_path($file));
        }

        $env = file_get_contents(base_path('.env.desktop.example'));
        $this->assertStringContainsString('APP_ENV=production', $env);
        $this->assertStringContainsString('APP_DEBUG=false', $env);
        $this->assertStringContainsString('SESSION_ENCRYPT=true', $env);
    }
}
