<?php

namespace Tests\Feature\Posting;

use App\Enums\ListingStatus;
use App\Mail\ListingExpiringSoon;
use App\Models\Listing;
use App\Services\Catalog;
use App\Services\ListingImages;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class MyListingsTest extends PostingTestCase
{
    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/moj-nalog/oglasi/')->assertRedirect(url_slash('login-registracija'));
    }

    public function test_tabs_show_listings_by_status(): void
    {
        $user = $this->user();
        $this->published($user, ['title' => 'Aktivan oglas']);
        $this->published($user, ['title' => 'Čeka oglas', 'status' => ListingStatus::Pending]);
        $this->published($user, ['title' => 'Istekao oglas', 'status' => ListingStatus::Expired, 'expires_at' => now()->subDay()]);
        $this->published($user, ['title' => 'Rok prošao', 'expires_at' => now()->subHour()]);
        $this->published($user, ['title' => 'Prodat oglas', 'status' => ListingStatus::Sold]);
        $this->published($user, ['title' => 'Odbijen oglas', 'status' => ListingStatus::Rejected, 'rejection_reason' => 'Slike nisu vaše.']);
        $this->published($this->user(), ['title' => 'Tuđi oglas']);

        $this->actingAs($user)->get('/moj-nalog/oglasi/')->assertOk()
            ->assertSee('<h1>Moji oglasi</h1>', false)
            ->assertSee('Aktivan oglas')->assertDontSee('Čeka oglas')->assertDontSee('Tuđi oglas')->assertDontSee('Rok prošao')
            ->assertSee('noindex', false)
            ->assertDontSee('Nacrti');
        $this->get('/moj-nalog/oglasi/?status=na-cekanju')->assertSee('Čeka oglas')->assertDontSee('Aktivan oglas');
        $this->get('/moj-nalog/oglasi/?status=istekli')->assertSee('Istekao oglas')->assertSee('Rok prošao')->assertSee('Prodat oglas')->assertSee('Obnovi besplatno');
        $this->get('/moj-nalog/oglasi/?status=odbijeni')->assertSee('Odbijen oglas')->assertSee('Slike nisu vaše.');
        $this->get('/moj-nalog/oglasi/?status=bilo-sta')->assertOk()->assertSee('Aktivan oglas');
    }

    public function test_listing_card_shows_stats(): void
    {
        $user = $this->user();
        $listing = $this->published($user, ['title' => 'Sa statistikom']);
        DB::table('listings')->where('id', $listing->id)->update(['views' => 1234, 'phone_reveals' => 7]);
        DB::table('listing_daily_stats')->insert([
            ['listing_id' => $listing->id, 'date' => now()->toDateString(), 'views' => 20, 'phone_reveals' => 2, 'messages' => 1, 'favorites' => 0],
            ['listing_id' => $listing->id, 'date' => now()->subDays(40)->toDateString(), 'views' => 500, 'phone_reveals' => 5, 'messages' => 0, 'favorites' => 0],
        ]);

        $this->actingAs($user)->get('/moj-nalog/oglasi/')->assertSee('1.234 pregleda')->assertSee('7 prikaza broja')->assertSee('poslednjih 30 dana: 20 / 2');
        $this->get('/moj-nalog/oglasi/'.$listing->id.'/statistika/')->assertOk()->assertSee('Statistika oglasa')->assertSee('1.234');
        $this->actingAs($this->user())->get('/moj-nalog/oglasi/'.$listing->id.'/statistika/')->assertForbidden();
    }

    public function test_listing_card_stats_use_serbian_plurals(): void
    {
        $user = $this->user();
        $listing = $this->published($user);
        DB::table('listings')->where('id', $listing->id)->update(['views' => 21, 'phone_reveals' => 1]);

        $this->actingAs($user)->get('/moj-nalog/oglasi/')->assertSee('21 pregled<', false)->assertSee('1 prikaz broja');
    }

    public function test_renew_expired_listing_for_free(): void
    {
        $user = $this->user();
        $listing = $this->published($user, ['status' => ListingStatus::Expired, 'expires_at' => now()->subDays(5), 'expiry_notified_at' => now()->subDays(8)]);

        $this->actingAs($user)->get('/moj-nalog/oglasi/'.$listing->id.'/obnova/')->assertOk()->assertSee('Obnova je besplatna.');
        $this->post('/moj-nalog/oglasi/'.$listing->id.'/obnova/')->assertRedirect(url_slash('moj-nalog/oglasi').'?status=aktivni');

        $listing->refresh();
        $this->assertSame(ListingStatus::Published, $listing->status);
        $this->assertTrue($listing->expires_at->between(now()->addDays(30)->subMinute(), now()->addDays(30)->addMinute()));
        $this->assertNull($listing->expiry_notified_at);
    }

    public function test_active_listing_renews_only_in_the_last_three_days_and_keeps_remaining_time(): void
    {
        $user = $this->user();
        $far = $this->published($user, ['expires_at' => now()->addDays(10)]);
        $this->actingAs($user)->get('/moj-nalog/oglasi/'.$far->id.'/obnova/')->assertRedirect(url_slash('moj-nalog/oglasi'));
        $this->post('/moj-nalog/oglasi/'.$far->id.'/obnova/')->assertSessionHas('status', 'Ovaj oglas još ne može da se obnovi.');
        $this->assertTrue($far->refresh()->expires_at->lt(now()->addDays(11)));

        $soon = $this->published($user, ['expires_at' => now()->addDays(2)]);
        $this->post('/moj-nalog/oglasi/'.$soon->id.'/obnova/');
        $this->assertTrue($soon->refresh()->expires_at->between(now()->addDays(32)->subMinute(), now()->addDays(32)->addMinute()));
    }

    public function test_mark_sold_and_deactivate(): void
    {
        $user = $this->user();
        $sold = $this->published($user);
        $off = $this->published($user, ['title' => 'Drugi']);

        $this->actingAs($user)->post('/moj-nalog/oglasi/'.$sold->id.'/prodato/')->assertRedirect(url_slash('moj-nalog/oglasi').'?status=istekli');
        $this->assertSame(ListingStatus::Sold, $sold->refresh()->status);

        $this->post('/moj-nalog/oglasi/'.$off->id.'/deaktivacija/');
        $off->refresh();
        $this->assertSame(ListingStatus::Expired, $off->status);
        $this->assertTrue($off->expires_at->lte(now()));

        // sold and deactivated can be renewed
        $this->post('/moj-nalog/oglasi/'.$sold->id.'/obnova/');
        $this->assertSame(ListingStatus::Published, $sold->refresh()->status);
        // only active ones can be marked sold
        $this->post('/moj-nalog/oglasi/'.$off->id.'/prodato/')->assertSessionHas('status', 'Samo aktivan oglas može da se označi kao prodat.');
    }

    public function test_delete_is_soft_and_a_draft_is_removed_with_images(): void
    {
        $user = $this->user();
        $listing = $this->published($user);
        $this->actingAs($user)->get('/moj-nalog/oglasi/'.$listing->id.'/brisanje/')->assertOk()->assertSee('Obriši oglas');
        $this->delete('/moj-nalog/oglasi/'.$listing->id.'/')->assertRedirect(url_slash('moj-nalog/oglasi'));
        $this->assertSoftDeleted($listing);

        $draft = $this->draft($user);
        $image = app(ListingImages::class)->upload($this->image(), $user, $draft);
        $this->delete('/moj-nalog/oglasi/'.$draft->id.'/');
        $this->assertNull(Listing::withTrashed()->find($draft->id));
        Storage::disk('public')->assertMissing($image->path);
    }

    public function test_only_owner_or_staff_can_change(): void
    {
        $listing = $this->published($this->user());
        $other = $this->user();

        foreach (['obnova', 'prodato', 'deaktivacija'] as $action) {
            $this->actingAs($other)->post('/moj-nalog/oglasi/'.$listing->id.'/'.$action.'/')->assertForbidden();
        }
        $this->delete('/moj-nalog/oglasi/'.$listing->id.'/')->assertForbidden();
        $this->get('/moj-nalog/oglasi/'.$listing->id.'/izmena/')->assertForbidden();
        $this->assertNotSoftDeleted($listing);

        $this->actingAs($this->user(['role' => 'admin']))->post('/moj-nalog/oglasi/'.$listing->id.'/prodato/');
        $this->assertSame(ListingStatus::Sold, $listing->refresh()->status);
    }

    // ---------------------------------------------------------------- scheduled expiry

    public function test_expire_command_expires_and_flushes_counts(): void
    {
        Mail::fake();
        $user = $this->user();
        $old = $this->published($user, ['expires_at' => now()->subMinute()]);
        $active = $this->published($user, ['expires_at' => now()->addDays(20)]);
        $catalog = app(Catalog::class);
        $this->assertSame(2, $catalog->count($this->services));

        $this->artisan('listings:expire')->assertSuccessful();

        $this->assertSame(ListingStatus::Expired, $old->refresh()->status);
        $this->assertSame(ListingStatus::Published, $active->refresh()->status);
        $this->assertSame(1, $catalog->count($this->services));
    }

    public function test_expire_command_mails_three_days_before_once(): void
    {
        Mail::fake();
        $user = $this->user();
        $soon = $this->published($user, ['title' => 'Uskoro ističe', 'expires_at' => now()->addDays(2)]);
        $this->published($user, ['expires_at' => now()->addDays(5)]);
        $muted = $this->user(['notification_settings' => ['expiring' => false]]);
        $this->published($muted, ['expires_at' => now()->addDays(1)]);

        $this->artisan('listings:expire')->assertSuccessful();
        $this->artisan('listings:expire')->assertSuccessful();

        Mail::assertQueued(ListingExpiringSoon::class, 1);
        Mail::assertQueued(ListingExpiringSoon::class, fn ($mail) => $mail->hasTo($user->email) && $mail->listing->is($soon));
        $this->assertNotNull($soon->refresh()->expiry_notified_at);
    }

    public function test_expiry_mail_text_and_renew_link(): void
    {
        $user = $this->user(['name' => 'Marko']);
        $listing = $this->published($user, ['title' => 'Bicikl', 'expires_at' => now()->addDays(3)]);

        $mail = new ListingExpiringSoon($listing);
        $mail->assertHasSubject('Vaš oglas uskoro ističe: Bicikl');
        $mail->assertSeeInHtml('Zdravo, Marko!');
        $mail->assertSeeInHtml(url_slash('moj-nalog/oglasi/'.$listing->id.'/obnova'));
        $mail->assertSeeInText('Obnova je besplatna');
    }

    public function test_old_drafts_are_pruned(): void
    {
        $user = $this->user();
        $old = $this->draft($user);
        DB::table('listings')->where('id', $old->id)->update(['updated_at' => now()->subDays(31)]);
        $fresh = $this->draft($user, ['title' => 'Nov']);

        $this->artisan('listings:expire', ['--no-mail' => true])->assertSuccessful();

        $this->assertNull(Listing::withTrashed()->find($old->id));
        $this->assertModelExists($fresh);
    }

    public function test_duration_comes_from_settings(): void
    {
        Settings::set('listing_duration_days', 15);
        $user = $this->user();
        $listing = $this->published($user, ['status' => ListingStatus::Expired, 'expires_at' => now()->subDay()]);

        $this->actingAs($user)->post('/moj-nalog/oglasi/'.$listing->id.'/obnova/');
        $this->assertTrue($listing->refresh()->expires_at->between(now()->addDays(15)->subMinute(), now()->addDays(15)->addMinute()));
    }

    public function test_account_page_links_to_my_listings(): void
    {
        $this->actingAs($this->user())->get('/moj-nalog/')->assertOk()->assertSee('href="'.url_slash('moj-nalog/oglasi').'"', false);
    }

    public function test_creating_drafts_is_rate_limited(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/postavi-oglas/', ['category_id' => $this->services->id]);
        }
        $this->post('/postavi-oglas/', ['category_id' => $this->services->id])->assertSessionHasErrors(['limit' => 'Previše pokušaja. Sačekajte malo pa probajte ponovo.']);
    }
}
