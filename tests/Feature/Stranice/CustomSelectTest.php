<?php

namespace Tests\Feature\Stranice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomSelectTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_loads_the_searchable_picker(): void
    {
        $this->get('/kontakt/')->assertOk()
            ->assertSee('css/pick.css', false)
            ->assertSee('js/pick.js', false)
            ->assertSee('<select id="k-tema" data-pick', false);
    }

    public function test_no_template_has_a_plain_select(): void
    {
        $plain = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            preg_match_all('/<select\b[^>]*>/', file_get_contents($file->getPathname()), $tags);
            foreach ($tags[0] as $tag) {
                if (! str_contains($tag, 'data-pick')) {
                    $plain[] = $file->getFilename().': '.$tag;
                }
            }
        }

        $this->assertSame([], $plain);
    }
}
