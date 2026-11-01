<?php

namespace Tests\Feature\Info;

use App\Models\InfoAd;
use App\Models\InfoAdInquiry;
use App\Models\InfoBusiness;
use App\Models\InfoPost;
use App\Support\Settings;
use Tests\TestCase;

class AdvertisingTest extends TestCase
{
    private function info(string $path = ''): string
    {
        return 'http://'.config('info.domain').'/'.ltrim($path, '/');
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['info.enabled' => true]);
    }

    public function test_price_list_shows_prices_and_admin_can_change_them(): void
    {
        $this->get($this->info('oglasavanje/'))->assertOk()->assertSee('490 din')->assertSee('6.990 din')->assertSee('69.900 din');

        Settings::set('info_price_featured_month', 590);
        $this->get($this->info('oglasavanje/'))->assertOk()->assertSee('590 din');
    }

    public function test_inquiry_is_stored(): void
    {
        $this->post($this->info('oglasavanje/'), ['company' => 'Pekara Klas', 'phone' => '064 111 222', 'interest' => 'featured'])
            ->assertRedirect();

        $this->assertSame('Pekara Klas', InfoAdInquiry::query()->sole()->company);
    }

    public function test_inquiry_needs_phone_or_email(): void
    {
        $this->post($this->info('oglasavanje/'), ['company' => 'Pekara Klas', 'interest' => 'featured'])->assertSessionHasErrors('phone');
        $this->assertSame(0, InfoAdInquiry::query()->count());
    }

    public function test_running_ad_shows_with_label_and_counts_clicks(): void
    {
        $business = InfoBusiness::query()->create(['name' => 'Pekara Klas', 'status' => 'listed']);
        $ad = InfoAd::query()->create(['slot' => 'home_middle', 'info_business_id' => $business->id, 'title' => 'Topao burek od 5 ujutru', 'starts_on' => today()]);
        InfoAd::query()->create(['slot' => 'home_middle', 'title' => 'Istekla reklama', 'starts_on' => today()->subMonth(), 'ends_on' => today()->subDay()]);
        InfoPost::query()->create(['type' => 'news', 'status' => 'published', 'origin' => 'manual', 'title' => 'Vest dana', 'summary' => 'Kratak tekst.']);

        $this->get($this->info())->assertOk()->assertSee('Topao burek od 5 ujutru')->assertSee('Oglas')->assertDontSee('Istekla reklama');

        $this->get($this->info('klik/'.$ad->id.'/'))->assertRedirect($business->url());
        $this->assertSame(1, $ad->fresh()->clicks);
    }

    public function test_featured_business_goes_first(): void
    {
        InfoBusiness::query()->create(['name' => 'Aaa servis', 'category' => 'Servisi', 'status' => 'listed']);
        InfoBusiness::query()->create(['name' => 'Zzz servis', 'category' => 'Servisi', 'status' => 'listed', 'featured_until' => now()->addMonth()]);

        $this->get($this->info('firme/'))->assertOk()->assertSeeInOrder(['Zzz servis', 'Aaa servis'])->assertSee('Istaknuta firma');
    }

    public function test_search_finds_posts_and_businesses(): void
    {
        InfoBusiness::query()->create(['name' => 'Vulkanizer Kod Makse', 'status' => 'listed']);
        InfoPost::query()->create(['type' => 'news', 'status' => 'published', 'origin' => 'manual', 'title' => 'Zimske gume i vulkanizeri', 'summary' => 'Kratak tekst.']);

        $this->get($this->info('pretraga/?q=vulkanizer'))->assertOk()->assertSee('Vulkanizer Kod Makse')->assertSee('Zimske gume i vulkanizeri');
    }
}
