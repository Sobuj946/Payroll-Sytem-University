<?php

namespace Tests\Unit;

use Tests\TestCase;

class ViewConventionsTest extends TestCase
{
    /** All Blade files of the project as [relative path => code]. */
    private function views(): array
    {
        $views = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $views[str_replace(resource_path('views') . '/', '', $file->getPathname())] = file_get_contents($file->getPathname());
            }
        }

        return $views;
    }

    /**
     * TC-UNT-019: A view that uses an <x-...> tag must not have its own variable called $component.
     * Blade uses that name inside its component tags, and the clash broke the salary components page.
     */
    public function test_views_do_not_clash_with_blade_component_variables(): void
    {
        $problems = [];

        foreach ($this->views() as $name => $code) {
            if (str_contains($code, '<x-') && preg_match('/\$component\b/', $code)) {
                $problems[] = $name;
            }
        }

        $this->assertSame([], $problems, 'These views use $component together with <x-...> tags: ' . implode(', ', $problems));
    }

    /**
     * TC-UNT-020: No view contains an inline script, an inline event handler or a javascript: link,
     * because the content security policy forbids them. Behaviour belongs in public/assets/js/app.js.
     */
    public function test_views_have_no_inline_scripts(): void
    {
        $problems = [];

        foreach ($this->views() as $name => $code) {
            $found = [];

            if (preg_match('/<script(?![^>]*\ssrc=)/i', $code)) {
                $found[] = 'inline <script>';
            }
            if (preg_match('/\son(click|change|submit|load|input|focus|blur|keyup|keydown|mouseover)\s*=/i', $code)) {
                $found[] = 'inline event handler';
            }
            if (stripos($code, 'javascript:') !== false) {
                $found[] = 'javascript: link';
            }

            if ($found) {
                $problems[] = $name . ' (' . implode(', ', $found) . ')';
            }
        }

        $this->assertSame([], $problems, "Not allowed by the content security policy:\n" . implode("\n", $problems));
    }
}
