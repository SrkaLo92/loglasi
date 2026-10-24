<?php

namespace Tests\Feature\Payments;

use App\Enums\BuyerType;
use App\Enums\OrderStatus;
use App\Mail\OrderPlaced;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\BumpCredits;
use App\Support\Firms\FirmCredits;
use App\Support\Settings;
use Illuminate\Support\Facades\Mail;

class OrderFlowTest extends PaymentsTestCase
{
    private function confirm(array $data): array
    {
        return $data + ['kupac' => 'private', 'odmah' => '1', 'uslovi' => '1'];
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/moj-nalog/narudzbine/')->assertRedirect();
        $this->get('/moj-nalog/narudzbine/nova/?usluga=top_7')->assertRedirect();
        $this->post('/moj-nalog/narudzbine/', [])->assertRedirect();
    }

    public function test_choose_listing_then_confirm_page_shows_amount_and_legal_texts(): void
    {
        $user = $this->seller();
        $listing = $this->listingOf($user, ['title' => 'Golf 4 dizel']);

        $this->actingAs($user)->get('/moj-nalog/narudzbine/nova/?usluga=top_7')
            ->assertOk()->assertSee('Izaberite oglas')->assertSee('Golf 4 dizel');

        $this->actingAs($user)->get('/moj-nalog/narudzbine/nova/?usluga=top_7&oglas='.$listing->id)
            ->assertOk()
            ->assertSee('399')
            ->assertSee('Saglasan/na sam da usluga počne odmah po evidentiranju uplate i razumem da time gubim pravo na odustanak od ugovora za izvršenu uslugu.')
            ->assertSee('<a href="'.url_slash('odustanak-od-ugovora').'">Odustanak od ugovora</a>', false)
            ->assertSee('Pročitao/la sam i prihvatam <a href="'.url_slash('uslovi-placanja').'">Uslove kupovine i plaćanja</a>', false)
            ->assertSee('href="'.url_slash('reklamacije').'"', false)
            ->assertSee('Probni režim');
    }

    public function test_benefit_text_uses_serbian_plurals(): void
    {
        $user = $this->seller();

        $this->actingAs($user)->get('/moj-nalog/narudzbine/nova/?usluga=listing_slots_3')
            ->assertOk()->assertSee('Još 3 aktivna oglasa, pored besplatnih, tokom 30 dana od uplate.');
    }

    public function test_unknown_or_display_only_product_is_404(): void
    {
        $user = $this->seller();
        $this->actingAs($user)->get('/moj-nalog/narudzbine/nova/?usluga=biznis_12m')->assertNotFound();
        $this->actingAs($user)->get('/moj-nalog/narudzbine/nova/?usluga=nepostoji')->assertNotFound();
    }

    public function test_store_creates_a_pending_order_and_queues_the_pro_forma(): void
    {
        $user = $this->seller();
        $listing = $this->listingOf($user);

        $response = $this->actingAs($user)->post('/moj-nalog/narudzbine/', $this->confirm(['usluga' => 'premium_xl_7', 'oglas' => $listing->id]));

        $order = Order::sole();
        $response->assertRedirect(url_slash('moj-nalog/narudzbine/'.$order->id));
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(599, $order->amount_rsd);
        $this->assertSame(BuyerType::Private, $order->buyer_type);
        $this->assertNotNull($order->consent_at);
        $this->assertNotNull($order->terms_accepted_at);
        $this->assertTrue($order->is_test);
        Mail::assertQueued(OrderPlaced::class, fn ($mail) => $mail->hasTo($user->email));

        $this->actingAs($user)->get('/moj-nalog/narudzbine/'.$order->id.'/')
            ->assertOk()
            ->assertSee($order->reference)
            ->assertSee(self::ACCOUNT)
            ->assertSee('<svg', false)
            ->assertSee('TEST');
    }

    public function test_double_submit_returns_the_same_order(): void
    {
        $user = $this->seller();
        $listing = $this->listingOf($user);
        $data = $this->confirm(['usluga' => 'top_7', 'oglas' => $listing->id]);

        $this->actingAs($user)->post('/moj-nalog/narudzbine/', $data);
        $this->actingAs($user)->post('/moj-nalog/narudzbine/', $data);

        $this->assertSame(1, Order::count());
        Mail::assertQueued(OrderPlaced::class, 1);
    }

    public function test_consent_and_terms_are_required(): void
    {
        $user = $this->seller();
        $this->actingAs($user)->post('/moj-nalog/narudzbine/', ['usluga' => 'listing_slots_3', 'kupac' => 'private'])
            ->assertSessionHasErrors(['odmah', 'uslovi']);
        $this->assertSame(0, Order::count());
    }

    public function test_private_buyer_without_consent_gets_an_error_and_no_order(): void
    {
        $user = $this->seller();
        $this->actingAs($user)->post('/moj-nalog/narudzbine/', ['usluga' => 'listing_slots_3', 'kupac' => 'private', 'uslovi' => '1'])
            ->assertSessionHasErrors(['odmah' => 'Potvrdite saglasnost da usluga počne odmah po evidentiranju uplate.']);
        $this->actingAs($user)->post('/moj-nalog/narudzbine/', ['usluga' => 'listing_slots_3', 'kupac' => 'private', 'odmah' => '1'])
            ->assertSessionHasErrors(['uslovi' => 'Potvrdite da prihvatate Uslove kupovine i plaćanja.']);
        $this->assertSame(0, Order::count());
    }

    public function test_consent_time_and_ip_are_stored_on_the_order(): void
    {
        $this->travelTo(now()->setTime(10, 15, 0));
        $user = $this->seller();

        $this->actingAs($user)->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->post('/moj-nalog/narudzbine/', $this->confirm(['usluga' => 'listing_slots_3']))->assertRedirect();

        $order = Order::sole();
        $this->assertTrue($order->consent_at->equalTo(now()));
        $this->assertTrue($order->terms_accepted_at->equalTo(now()));
        $this->assertSame('203.0.113.7', $order->consent_ip);
    }

    public function test_pro_forma_mail_links_the_legal_pages(): void
    {
        $user = $this->seller();
        $this->actingAs($user)->post('/moj-nalog/narudzbine/', $this->confirm(['usluga' => 'listing_slots_3']));

        Mail::assertQueued(OrderPlaced::class, function (OrderPlaced $mail) {
            $html = $mail->render();
            foreach (['uslovi-placanja', 'odustanak-od-ugovora', 'reklamacije'] as $path) {
                $this->assertStringContainsString('href="'.url_slash($path).'"', $html);
            }

            return true;
        });
    }

    public function test_firm_buyer_gives_invoice_data_and_needs_no_consent(): void
    {
        $user = $this->seller();
        $this->actingAs($user)->post('/moj-nalog/narudzbine/', ['usluga' => 'listing_slots_3', 'kupac' => 'firm', 'uslovi' => '1', 'pib' => '123'])
            ->assertSessionHasErrors(['naziv', 'pib', 'mb', 'adresa', 'mejl']);

        $this->actingAs($user)->post('/moj-nalog/narudzbine/', [
            'usluga' => 'listing_slots_3', 'kupac' => 'firm', 'uslovi' => '1', 'naziv' => 'Test DOO',
            'pib' => '100002887', 'mb' => '12345678', 'adresa' => 'Glavna 1, Loznica', 'mejl' => 'Racun@Example.com',
        ])->assertRedirect();

        $order = Order::sole();
        $this->assertSame(BuyerType::Firm, $order->buyer_type);
        $this->assertSame('racun@example.com', $order->buyer_email);
        $this->assertNull($order->consent_at);
        $this->assertNotNull($order->terms_accepted_at);
    }

    public function test_private_payments_need_the_switch_outside_test_mode(): void
    {
        Settings::set('payments.test_mode', false);
        $user = $this->seller();

        $this->actingAs($user)->post('/moj-nalog/narudzbine/', $this->confirm(['usluga' => 'listing_slots_3']))
            ->assertSessionHasErrors('order');
        $this->assertSame(0, Order::count());

        Settings::set('payments.private_payments_enabled', true);
        $this->actingAs($user)->post('/moj-nalog/narudzbine/', $this->confirm(['usluga' => 'listing_slots_3']))->assertRedirect();
        $this->assertFalse(Order::sole()->is_test);
    }

    public function test_only_own_active_listings(): void
    {
        $owner = $this->seller();
        $listing = $this->listingOf($owner);
        $other = $this->seller();

        $this->actingAs($other)->get('/moj-nalog/narudzbine/nova/?usluga=top_7&oglas='.$listing->id)->assertForbidden();
        $this->actingAs($other)->post('/moj-nalog/narudzbine/', $this->confirm(['usluga' => 'top_7', 'oglas' => $listing->id]))->assertForbidden();

        $expired = $this->listingOf($owner, ['expires_at' => now()->subDay()]);
        $this->actingAs($owner)->post('/moj-nalog/narudzbine/', $this->confirm(['usluga' => 'top_7', 'oglas' => $expired->id]))
            ->assertSessionHasErrors('order');
        $this->assertSame(0, Order::count());
    }

    public function test_bump_at_most_once_per_24_hours(): void
    {
        $user = $this->seller();
        $listing = $this->listingOf($user, ['bumped_at' => now()->subHours(3)]);

        $this->actingAs($user)->post('/moj-nalog/narudzbine/', $this->confirm(['usluga' => 'bump', 'oglas' => $listing->id]))
            ->assertSessionHasErrors('order');

        $this->travel(22)->hours();
        $this->actingAs($user)->post('/moj-nalog/narudzbine/', $this->confirm(['usluga' => 'bump', 'oglas' => $listing->id]))->assertRedirect();
        $this->assertSame(1, Order::count());
    }

    public function test_firm_packages_need_a_firm(): void
    {
        $this->actingAs($this->seller())->post('/moj-nalog/narudzbine/', $this->confirm(['usluga' => 'business_pro_monthly']))
            ->assertSessionHasErrors('order');

        $this->actingAs($this->firmUser())->post('/moj-nalog/narudzbine/', $this->confirm(['usluga' => 'business_pro_monthly']))->assertRedirect();
        $this->assertSame(1, Order::count());
    }

    public function test_firm_monthly_credit_is_used_before_buying(): void
    {
        $user = $this->firmUser('pro');
        $listing = $this->listingOf($user);

        $this->actingAs($user)->get('/moj-nalog/narudzbine/nova/?usluga=bump&oglas='.$listing->id)
            ->assertOk()->assertSee('Iskoristi uključenu uslugu');
        $this->actingAs($user)->post('/moj-nalog/narudzbine/', $this->confirm(['usluga' => 'bump', 'oglas' => $listing->id]))
            ->assertSessionHasErrors('order');

        $this->actingAs($user)->post('/moj-nalog/narudzbine/iskoristi/', ['usluga' => 'bump', 'oglas' => $listing->id])
            ->assertRedirect($listing->url());

        $this->assertSame(0, Order::count());
        $this->assertSame(9, FirmCredits::remaining($user->firm)['bumps']);
        $this->assertEqualsWithDelta(now()->timestamp, $listing->fresh()->bumped_at->timestamp, 5);
        $this->assertSame(1, AuditLog::where('action', 'promotion.credit_used')->count());

        // Highlights: 2 a month on Pro, then the promotion is bought.
        foreach ([1, 2] as $i) {
            $this->actingAs($user)->post('/moj-nalog/narudzbine/iskoristi/', ['usluga' => 'featured_7', 'oglas' => $listing->id]);
        }
        $this->assertSame(0, FirmCredits::remaining($user->firm)['highlights']);
        $this->assertSame(now()->addDays(14)->toDateString(), $listing->fresh()->featured_until->toDateString());
        $this->actingAs($user)->post('/moj-nalog/narudzbine/', $this->confirm(['usluga' => 'featured_7', 'oglas' => $listing->id]))->assertRedirect();
        $this->assertSame(1, Order::count());
    }

    public function test_bought_bump_pack_is_used_one_bump_at_a_time(): void
    {
        $user = $this->seller();
        $listing = $this->listingOf($user);
        BumpCredits::grant($user, 10);

        $this->actingAs($user)->post('/moj-nalog/narudzbine/iskoristi/', ['usluga' => 'bump', 'oglas' => $listing->id])->assertRedirect();
        $this->assertSame(9, BumpCredits::remaining($user));

        // The 24-hour rule still applies.
        $this->actingAs($user)->post('/moj-nalog/narudzbine/iskoristi/', ['usluga' => 'bump', 'oglas' => $listing->id])->assertSessionHasErrors('order');
        $this->assertSame(9, BumpCredits::remaining($user));
    }

    public function test_cancel_own_pending_order_only(): void
    {
        $user = $this->seller();
        $order = $this->order($user, 'listing_slots_3');

        $this->actingAs($this->seller())->post('/moj-nalog/narudzbine/'.$order->id.'/otkazi/')->assertForbidden();
        $this->actingAs($this->seller())->get('/moj-nalog/narudzbine/'.$order->id.'/')->assertForbidden();

        $this->actingAs($user)->post('/moj-nalog/narudzbine/'.$order->id.'/otkazi/')->assertRedirect();
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->actingAs($user)->post('/moj-nalog/narudzbine/'.$order->id.'/otkazi/')->assertSessionHasErrors('order');

        $this->actingAs(User::factory()->moderator()->create())->get('/moj-nalog/narudzbine/'.$order->id.'/')->assertOk();
    }

    public function test_unpaid_orders_are_cancelled_after_7_days(): void
    {
        $old = $this->order($this->seller(), 'listing_slots_3');
        $this->travel(6)->days();
        $fresh = $this->order($this->seller(), 'listing_slots_3');
        $this->travel(1)->days();
        $this->travel(1)->minutes();

        $this->artisan('orders:cancel-stale')->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, $old->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $fresh->fresh()->status);
        $this->assertSame(1, AuditLog::where('action', 'order.auto_cancelled')->count());
    }

    public function test_my_orders_page_lists_statuses(): void
    {
        $user = $this->seller();
        $pending = $this->order($user, 'listing_slots_3');
        $paid = $this->pay($this->order($user, 'listing_slots_10'));

        $this->actingAs($user)->get('/moj-nalog/narudzbine/')
            ->assertOk()
            ->assertSee($pending->reference)->assertSee('Čeka uplatu')
            ->assertSee($paid->reference)->assertSee('Aktivirano');
    }

    public function test_pending_limit_per_user(): void
    {
        config(['payments.max_pending_per_user' => 1]);
        $user = $this->seller();
        $this->order($user, 'listing_slots_3');

        $this->actingAs($user)->post('/moj-nalog/narudzbine/', $this->confirm(['usluga' => 'listing_slots_10']))->assertSessionHasErrors('order');
    }
}
