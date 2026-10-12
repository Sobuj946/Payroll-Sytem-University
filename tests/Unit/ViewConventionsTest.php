<?php

namespace Tests\Unit;

use Tests\TestCase;

class ViewConventionsTest extends TestCase
{
    /**
     * TC-UNT-019: A view that uses an <x-...> tag must not have its own variable called $component.
     * Blade uses that name inside its component tags, and the clash broke the salary components page.
     */
    public function test_views_do_not_clash_with_blade_component_variables(): void
    {
        $problems = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $code = file_get_contents($file->getPathname());

            if (str_contains($code, '<x-') && preg_match('/\$component\b/', $code)) {
                $problems[] = str_replace(resource_path('views') . '/', '', $file->getPathname());
            }
        }

        $this->assertSame([], $problems, 'These views use $component together with <x-...> tags: ' . implode(', ', $problems));
    }
}
