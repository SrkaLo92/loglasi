<?php

namespace Tests\Feature\Pages;

use App\Models\Category;
use App\Models\Listing;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\ProductSeeder;
use Tests\TestCase;

/** Every page type builds canonical, og:url and JSON-LD URLs on the one canonical domain (seo.base_url). */
class CanonicalHostTest extends TestCase
{
    public function test_all_page_types_use_the_canonical_host(): void
    {
        $this->seed(ProductSeeder::class);
        config(['app.url' => 'http://loglasi-backend.test']);
        $category = Category::create(['name' => 'Automobili', 'slug' => 'automobili']);
        $user = User::factory()->create();
        $listing = Listing::factory()->create(['category_id' => $category->id, 'location_id' => Location::factory(), 'user_id' => $user->id]);

        $pages = ['/', '/oglasi/', '/oglasi/automobili/', '/oglas/'.$listing->slug.'/', '/faq/', '/o-nama/', '/kontakt/',
            '/cenovnik/', '/za-firme/', '/uslovi-koriscenja/', '/politika-privatnosti/'];
        $host = parse_url((string) config('seo.base_url'), PHP_URL_HOST);

        foreach ($pages as $page) {
            $html = $this->get($page)->assertOk()->getContent();

            $this->assertMatchesRegularExpression('~<link rel="canonical" href="https://'.preg_quote($host, '~').'/~', $html, "canonical on {$page}");
            $this->assertMatchesRegularExpression('~<meta property="og:url" content="https://'.preg_quote($host, '~').'/~', $html, "og:url on {$page}");
            $this->assertStringNotContainsString('loglasi-backend.test', $this->jsonLd($html), "local domain in JSON-LD of {$page}");
            $this->assertStringNotContainsString('loglasi-backend.test', $this->metaUrls($html), "local domain in SEO tags of {$page}");
        }
    }

    private function jsonLd(string $html): string
    {
        preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);

        return implode("\n", $m[1]);
    }

    private function metaUrls(string $html): string
    {
        preg_match_all('~<(?:link rel="(?:canonical|alternate)"|meta (?:property|name)="(?:og|twitter):[a-z:]+")[^>]*>~', $html, $m);

        return implode("\n", $m[0]);
    }
}
