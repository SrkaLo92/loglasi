<?php

namespace Tests\Feature\Search;

use App\Models\Category;
use App\Support\Search\CategoryIcons;
use Tests\TestCase;

class CategoryIconsTest extends TestCase
{
    private function icon(string $slug): string
    {
        return CategoryIcons::nameFor(Category::factory()->create(['slug' => $slug, 'parent_id' => null]));
    }

    public function test_short_keys_only_match_the_start_of_a_slug_word(): void
    {
        $this->assertSame('gift', $this->icon('besplatne-stvari'));
        $this->assertSame('tv', $this->icon('tv-i-video'));
        $this->assertSame('tv', $this->icon('audio-tv'));
    }

    public function test_specific_icons(): void
    {
        $this->assertSame('flame', $this->icon('ogrev'));
        $this->assertSame('brick', $this->icon('gradjevinarstvo'));
        $this->assertSame('gear', $this->icon('auto-delovi-i-oprema'));
        $this->assertSame('car', $this->icon('automobili'));
        $this->assertSame('bike', $this->icon('bicikli-delovi-i-oprema'));
    }
}
