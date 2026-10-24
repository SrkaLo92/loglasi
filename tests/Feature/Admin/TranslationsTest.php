<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Arr;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * The panel runs in sr_Latn with "sr" as fallback; lines missing from Filament's
 * translation would show as raw keys ("filament-tables::table.loading").
 * The missing ones are in lang/vendor/{namespace}/sr_Latn.
 */
class TranslationsTest extends TestCase
{
    /** Package folder => translation namespace. */
    private const NAMESPACES = [
        'actions' => 'filament-actions',
        'filament' => 'filament-panels',
        'forms' => 'filament-forms',
        'infolists' => 'filament-infolists',
        'notifications' => 'filament-notifications',
        'schemas' => 'filament-schemas',
        'support' => 'filament',
        'tables' => 'filament-tables',
        'widgets' => 'filament-widgets',
    ];

    public function test_filament_lines_have_a_serbian_translation(): void
    {
        app()->setLocale('sr_Latn');
        app('translator')->setFallback('sr');

        $missing = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('vendor/filament')));
        foreach ($files as $file) {
            if (! preg_match('#/vendor/filament/([^/]+)/resources/lang/en/(.+)\.php$#', $file->getPathname(), $m) || ! isset(self::NAMESPACES[$m[1]])) {
                continue;
            }
            foreach (array_keys(Arr::dot(require $file->getPathname())) as $key) {
                // The rich editor and the builder are not used in this panel.
                if ($m[2] === 'components' && preg_match('/^(rich_editor|builder)\./', $key)) {
                    continue;
                }
                $line = self::NAMESPACES[$m[1]].'::'.$m[2].'.'.$key;
                if (__($line) === $line) {
                    $missing[] = $line;
                }
            }
        }

        $this->assertSame([], $missing);
        $this->assertSame('Učitavanje...', __('filament-tables::table.loading'));
        $this->assertSame('Rezultata: 5', trans_choice('filament-tables::table.result_count', 5));
    }
}
