<?php

namespace App\Http\Middleware;

use App\Support\Assets;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds browser-side protections to every response:
 *  - the site cannot be shown inside another site's frame (clickjacking),
 *  - the browser must not guess file types,
 *  - pages of signed-in users are not kept in the browser cache, so the Back button
 *    after signing out does not show salary data,
 *  - a content security policy: the page may only load scripts, styles, fonts and images from
 *    this site, and no inline script may run. Even if somebody managed to inject script into
 *    a page, the browser would refuse to run it.
 *
 * The policy is switched on only when Bootstrap is installed locally (php artisan pms:install-assets)
 * and debug mode is off, because the CDN fallback and Laravel's debug pages need more freedom.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        if ($request->user()) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        if (! config('app.debug') && Assets::local()) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self'",
                "style-src 'self' 'unsafe-inline'",   // inline style="..." attributes (progress bars, widths)
                "img-src 'self' data:",               // data: is used for the logo on salary slips
                "font-src 'self'",
                "connect-src 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
                "base-uri 'self'",
                "object-src 'none'",
            ]));
        }

        return $response;
    }
}
