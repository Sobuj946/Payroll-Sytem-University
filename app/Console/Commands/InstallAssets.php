<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * php artisan pms:install-assets
 * Downloads Bootstrap and Bootstrap Icons once into public/assets/vendor, so that the
 * system no longer needs the internet and a strict content security policy can be used.
 */
class InstallAssets extends Command
{
    protected $signature = 'pms:install-assets
                            {--path= : Folder to save into (default: public/assets/vendor)}
                            {--force : Download again even if the files already exist}';

    protected $description = 'Download Bootstrap and the icon font into the project so it works without internet';

    private const BOOTSTRAP = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist';
    private const ICONS = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font';

    public function handle(): int
    {
        $base = rtrim($this->option('path') ?: public_path('assets/vendor'), '/\\');

        $files = [
            'bootstrap/bootstrap.min.css' => self::BOOTSTRAP . '/css/bootstrap.min.css',
            'bootstrap/bootstrap.bundle.min.js' => self::BOOTSTRAP . '/js/bootstrap.bundle.min.js',
            'bootstrap-icons/bootstrap-icons.min.css' => self::ICONS . '/bootstrap-icons.min.css',
            'bootstrap-icons/fonts/bootstrap-icons.woff2' => self::ICONS . '/fonts/bootstrap-icons.woff2',
            'bootstrap-icons/fonts/bootstrap-icons.woff' => self::ICONS . '/fonts/bootstrap-icons.woff',
        ];

        foreach ($files as $name => $url) {
            $target = $base . '/' . $name;

            if (is_file($target) && ! $this->option('force')) {
                $this->line("Already there: {$name}");
                continue;
            }

            $this->line("Downloading {$name} ...");

            try {
                $response = Http::timeout(60)->get($url);
            } catch (\Throwable $e) {
                $this->error('Could not reach the download server. Check the internet connection and run the command again.');

                return self::FAILURE;
            }

            $body = $response->body();
            $isText = str_ends_with($name, '.css') || str_ends_with($name, '.js');

            // A failed download often returns a small HTML error page instead of the file.
            if (! $response->successful() || strlen($body) < 1000 || ($isText && str_starts_with(ltrim($body), '<'))) {
                $this->error("The download of {$name} did not return a proper file (HTTP {$response->status()}).");

                return self::FAILURE;
            }

            File::ensureDirectoryExists(dirname($target));
            File::put($target, $body);
        }

        $this->info('Done. The system now works without internet.');

        return self::SUCCESS;
    }
}
