<?php

namespace App\Support;

/**
 * Bootstrap and the icon font normally come from the project's own folder (public/assets/vendor),
 * so the system works without internet. Until they are installed, the pages fall back to a CDN.
 * Install them once with: php artisan pms:install-assets
 */
class Assets
{
    public const FILES = [
        'bootstrap/bootstrap.min.css',
        'bootstrap/bootstrap.bundle.min.js',
        'bootstrap-icons/bootstrap-icons.min.css',
        'bootstrap-icons/fonts/bootstrap-icons.woff2',
    ];

    public static function local(): bool
    {
        foreach (self::FILES as $file) {
            if (! is_file(public_path('assets/vendor/' . $file))) {
                return false;
            }
        }

        return true;
    }
}
