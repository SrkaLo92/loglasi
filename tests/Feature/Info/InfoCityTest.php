<?php

namespace Tests\Feature\Info;

use App\Models\InfoPost;
use App\Support\Info\InfoCity;
use Tests\TestCase;

class InfoCityTest extends TestCase
{
    protected function tearDown(): void
    {
        InfoCity::set(null);
        parent::tearDown();
    }

    public function test_new_content_gets_the_default_city_and_one_city_means_no_filter(): void
    {
        $post = InfoPost::query()->create(['type' => 'news', 'status' => 'published', 'title' => 'Vest', 'summary' => 'Tekst.']);

        $this->assertSame('loznica', $post->city);
        $this->assertFalse(InfoCity::filtering());
        $this->assertStringContainsString('/img/info/teme/sport.webp', InfoPost::themeImageUrl('sport', 'news'));
        $this->assertStringContainsString('/img/info/teme/event.webp', InfoPost::themeImageUrl(null, 'event'));
        $this->assertStringContainsString('/img/info/teme/sport.webp', $post->fill(['topic' => 'sport'])->imageUrl());
    }

    public function test_with_more_cities_queries_show_only_the_current_city(): void
    {
        config(['info.cities.sabac' => ['name' => 'Šabac']]);
        InfoPost::query()->create(['type' => 'news', 'status' => 'published', 'title' => 'Loznica vest', 'summary' => 'Tekst.']);
        InfoCity::set('sabac');
        InfoPost::query()->create(['type' => 'news', 'status' => 'published', 'title' => 'Šabac vest', 'summary' => 'Tekst.']);

        $this->assertSame(['Šabac vest'], InfoPost::query()->pluck('title')->all());
        $this->assertSame(['Loznica vest'], InfoPost::query()->inCity('loznica')->pluck('title')->all());
        $this->assertSame('Šabac', InfoCity::name());
    }
}
