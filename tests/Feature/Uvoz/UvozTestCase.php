<?php

namespace Tests\Feature\Uvoz;

use App\Uvoz\WordPress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;
use Throwable;

/**
 * Testovi uvoza rade nad primerom WordPress baze (tests/Fixtures/wordpress/wordpress.sql)
 * koja se pre svakog testa učitava u posebnu bazu: WP_DB_TEST_DATABASE, ili ime test baze
 * sa „_wp” (lb_uvoz_test → lb_uvoz_wp_test).
 */
abstract class UvozTestCase extends TestCase
{
    protected const FIXTURES = __DIR__.'/../../Fixtures/wordpress';

    protected string $reportDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        config(['uvoz.gone_file' => '']); // the real list of live URLs is not part of the fixture
        config(['uvoz.catalog_definition' => false]); // the fixture checks the import itself; CatalogDefinitionTest covers the rest

        $main = (string) config('database.connections.'.config('database.default').'.database');
        $database = env('WP_DB_TEST_DATABASE') ?: (str_ends_with($main, '_test') ? substr($main, 0, -5).'_wp_test' : $main.'_wp');

        $base = config('database.connections.'.config('database.default'));
        config([
            'database.connections.'.WordPress::CONNECTION => array_merge($base, ['database' => $database, 'prefix' => 'wp_', 'strict' => false]),
            // Posebna veza bez baze: CREATE DATABASE na glavnoj vezi bi prekinuo transakciju testa.
            'database.connections.uvoz_server' => array_merge($base, ['database' => '']),
        ]);
        DB::purge(WordPress::CONNECTION);

        try {
            DB::connection('uvoz_server')->statement('CREATE DATABASE IF NOT EXISTS `'.str_replace('`', '', $database).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            DB::connection(WordPress::CONNECTION)->unprepared((string) file_get_contents(self::FIXTURES.'/wordpress.sql'));
        } catch (Throwable $e) {
            $this->markTestSkipped("WordPress test baza {$database} nije dostupna: ".$e->getMessage());
        }

        Storage::fake('public');

        $this->reportDir = sys_get_temp_dir().'/uvoz-izvestaj-'.getmypid();
        config(['uvoz.report_dir' => $this->reportDir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->reportDir);

        parent::tearDown();
    }

    protected function wp()
    {
        return DB::connection(WordPress::CONNECTION);
    }

    /** @param  array<string, mixed>  $options */
    protected function import(array $options = []): PendingCommand
    {
        return $this->artisan('uvoz:wordpress', array_merge(['--uploads' => self::FIXTURES.'/uploads'], $options));
    }

    /** @return array<string, int> */
    protected function counts(): array
    {
        $out = [];
        foreach (['users', 'firms', 'categories', 'locations', 'fields', 'category_field', 'listings', 'listing_field_values', 'listing_images', 'favorites', 'conversations', 'messages', 'gone_urls'] as $table) {
            $out[$table] = DB::table($table)->count();
        }

        return $out;
    }
}
