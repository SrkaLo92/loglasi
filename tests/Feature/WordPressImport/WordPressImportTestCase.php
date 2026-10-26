<?php

namespace Tests\Feature\WordPressImport;

use App\WordPressImport\WordPress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;
use Throwable;

/**
 * The import tests run against a sample WordPress database (tests/Fixtures/wordpress/wordpress.sql)
 * which is loaded into a separate database before every test: WP_DB_TEST_DATABASE, or the test database name
 * with "_wp" (lb_uvoz_test → lb_uvoz_wp_test).
 */
abstract class WordPressImportTestCase extends TestCase
{
    protected const FIXTURES = __DIR__.'/../../Fixtures/wordpress';

    protected string $reportDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        config(['wordpress_import.gone_file' => '']); // the real list of live URLs is not part of the fixture
        config(['wordpress_import.redirect_file' => '']);
        config(['wordpress_import.catalog_definition' => false]); // the fixture checks the import itself; CatalogDefinitionTest covers the rest

        $main = (string) config('database.connections.'.config('database.default').'.database');
        $database = env('WP_DB_TEST_DATABASE') ?: (str_ends_with($main, '_test') ? substr($main, 0, -5).'_wp_test' : $main.'_wp');

        $base = config('database.connections.'.config('database.default'));
        config([
            'database.connections.'.WordPress::CONNECTION => array_merge($base, ['database' => $database, 'prefix' => 'wp_', 'strict' => false]),
            // A separate connection without a database: CREATE DATABASE on the main connection would break the test transaction.
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
        config(['wordpress_import.report_dir' => $this->reportDir]);
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
        return $this->artisan('import:wordpress', array_merge(['--uploads' => self::FIXTURES.'/uploads'], $options));
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
