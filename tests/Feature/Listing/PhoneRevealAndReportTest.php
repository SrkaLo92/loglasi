<?php

namespace Tests\Feature\Listing;

use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use Tests\TestCase;

class PhoneRevealAndReportTest extends TestCase
{
    private function listing(array $sellerState = ['phone' => '+381641234567']): Listing
    {
        return Listing::factory()->for(User::factory()->state($sellerState))->create(['phone_reveals' => 0]);
    }

    public function test_reveal_returns_json_and_counts_once_per_session(): void
    {
        $listing = $this->listing();

        $this->postJson('/oglas/'.$listing->slug.'/telefon/')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-cache, private')
            ->assertJson(['phone' => '+381641234567', 'tel' => 'tel:+381641234567', 'viber' => 'viber://chat?number=%2B381641234567'])
            ->assertJsonPath('display', fn ($v) => str_contains($v, '64'));
        $this->postJson('/oglas/'.$listing->slug.'/telefon/')->assertOk();

        $this->assertSame(1, (int) $listing->fresh()->phone_reveals);
        $this->assertDatabaseHas('listing_daily_stats', ['listing_id' => $listing->id, 'phone_reveals' => 1]);
    }

    public function test_reveal_without_js_redirects_and_page_shows_number(): void
    {
        $listing = $this->listing();

        $this->post('/oglas/'.$listing->slug.'/telefon/', ['next' => 'whatsapp'])
            ->assertRedirect('/oglas/'.$listing->slug.'/#kontakt-whatsapp');

        $this->get('/oglas/'.$listing->slug.'/')
            ->assertOk()
            ->assertSee('tel:+381641234567', false)
            ->assertSee('https://wa.me/381641234567', false);
    }

    public function test_reveal_by_owner_is_not_counted(): void
    {
        $listing = $this->listing();

        $this->actingAs($listing->user)->postJson('/oglas/'.$listing->slug.'/telefon/')->assertOk();

        $this->assertSame(0, (int) $listing->fresh()->phone_reveals);
    }

    public function test_reveal_without_phone_returns_null(): void
    {
        $listing = $this->listing(['phone' => null]);

        $this->postJson('/oglas/'.$listing->slug.'/telefon/')->assertOk()->assertExactJson(['phone' => null]);
        $this->assertSame(0, (int) $listing->fresh()->phone_reveals);
    }

    public function test_reveal_on_expired_listing_is_404(): void
    {
        $listing = Listing::factory()->expired()->create();

        $this->postJson('/oglas/'.$listing->slug.'/telefon/')->assertNotFound();
    }

    public function test_reveal_is_rate_limited(): void
    {
        $listing = $this->listing();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/oglas/'.$listing->slug.'/telefon/')->assertOk();
        }
        $this->postJson('/oglas/'.$listing->slug.'/telefon/')->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
    }

    public function test_report_creates_open_report(): void
    {
        $listing = $this->listing();
        $reporter = User::factory()->create();

        $this->actingAs($reporter)
            ->post('/oglas/'.$listing->slug.'/prijava/', ['reason' => 'prevara', 'details' => 'Traži <b>avans</b>'])
            ->assertRedirect('/oglas/'.$listing->slug.'/#prijava')
            ->assertSessionHas('report_sent');

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $reporter->id,
            'reportable_type' => $listing->getMorphClass(),
            'reportable_id' => $listing->id,
            'reason' => 'prevara',
            'details' => 'Traži avans',
            'status' => 'open',
        ]);
    }

    public function test_report_validation_errors_go_to_report_bag(): void
    {
        $listing = $this->listing();

        $this->post('/oglas/'.$listing->slug.'/prijava/', ['reason' => 'nesto', 'details' => str_repeat('a', 1001)])
            ->assertSessionHasErrorsIn('report', ['reason', 'details']);
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_report_honeypot_and_duplicates_save_nothing_new(): void
    {
        $listing = $this->listing();
        $reporter = User::factory()->create();

        $this->post('/oglas/'.$listing->slug.'/prijava/', ['reason' => 'prevara', 'website' => 'http://spam'])->assertSessionHas('report_sent');
        $this->assertDatabaseCount('reports', 0);

        $this->actingAs($reporter)->post('/oglas/'.$listing->slug.'/prijava/', ['reason' => 'prevara']);
        $this->actingAs($reporter)->post('/oglas/'.$listing->slug.'/prijava/', ['reason' => 'duplikat']);
        $this->assertSame(1, Report::count());
    }

    public function test_owner_cannot_report_own_listing(): void
    {
        $listing = $this->listing();

        $this->actingAs($listing->user)->post('/oglas/'.$listing->slug.'/prijava/', ['reason' => 'prevara'])->assertNotFound();
    }

    public function test_report_is_rate_limited(): void
    {
        $listing = $this->listing();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/oglas/'.$listing->slug.'/prijava/', ['reason' => 'drugo'])->assertRedirect();
        }
        $this->post('/oglas/'.$listing->slug.'/prijava/', ['reason' => 'drugo'])
            ->assertRedirect()
            ->assertSessionHas('listing_error');
        $this->assertSame(5, Report::count());
    }
}
