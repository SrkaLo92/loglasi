<?php

namespace Tests\Feature\Messages;

use App\Enums\ListingStatus;
use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use App\Services\Messages\Messenger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WebMessagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function send(User $buyer, Listing $listing, string $body = 'Da li je još aktuelno?')
    {
        return $this->actingAs($buyer)->post('/moj-nalog/poruke/novo/', ['oglas' => $listing->id, 'body' => $body]);
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/moj-nalog/poruke/')->assertRedirect(url_slash('login-registracija'));
        $this->get('/moj-nalog/poruke/novo/?oglas=1')->assertRedirect(url_slash('login-registracija'));
        $this->get('/moj-nalog/omiljeni/')->assertRedirect(url_slash('login-registracija'));
    }

    public function test_unverified_user_cannot_start_a_conversation(): void
    {
        $listing = Listing::factory()->create();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/moj-nalog/poruke/novo/?oglas='.$listing->id)->assertRedirect();
        $this->send($user, $listing)->assertRedirect();
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_create_page_shows_the_listing_and_form(): void
    {
        $listing = Listing::factory()->create(['title' => 'Golf 4 dizel']);

        $this->actingAs(User::factory()->create())->get('/moj-nalog/poruke/novo/?oglas='.$listing->id)
            ->assertOk()
            ->assertSee('Poruka prodavcu')
            ->assertSee('Golf 4 dizel')
            ->assertSee('name="oglas" value="'.$listing->id.'"', false)
            ->assertSee('noindex', false);
    }

    public function test_create_page_for_unavailable_listing_is_404(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/moj-nalog/poruke/novo/?oglas=999999')->assertNotFound();
        $this->actingAs($user)->get('/moj-nalog/poruke/novo/?oglas=abc')->assertNotFound();
        $this->actingAs($user)->get('/moj-nalog/poruke/novo/?oglas='.Listing::factory()->pending()->create()->id)->assertNotFound();
        $this->actingAs($user)->get('/moj-nalog/poruke/novo/?oglas='.Listing::factory()->expired()->create()->id)->assertNotFound();
    }

    public function test_cannot_message_own_listing(): void
    {
        $listing = Listing::factory()->create();

        $this->actingAs($listing->user)->get('/moj-nalog/poruke/novo/?oglas='.$listing->id)->assertRedirect($listing->url());
        $this->send($listing->user, $listing)->assertSessionHasErrors(['body' => 'Ne možete poslati poruku na svoj oglas.']);
        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_first_message_creates_conversation_counts_stats_and_redirects(): void
    {
        $listing = Listing::factory()->create();
        $buyer = User::factory()->create();

        $response = $this->send($buyer, $listing, 'Zdravo, <b>da li</b> je <script>alert(1)</script>aktuelno?');

        $conversation = Conversation::query()->sole();
        $message = Message::query()->sole();
        $response->assertRedirect(url_slash('moj-nalog/poruke/'.$conversation->id).'#poruka-'.$message->id);

        $this->assertSame($listing->user_id, $conversation->seller_id);
        $this->assertSame($buyer->id, $conversation->buyer_id);
        $this->assertSame('Zdravo, da li je aktuelno?', $message->body);
        $this->assertSame(1, $conversation->seller_unread);
        $this->assertSame(0, $conversation->buyer_unread);
        $this->assertNotNull($conversation->buyer_read_at);
        $this->assertSame(1, (int) DB::table('listing_daily_stats')->where('listing_id', $listing->id)->value('messages'));
    }

    public function test_second_start_continues_the_same_conversation(): void
    {
        $listing = Listing::factory()->create();
        $buyer = User::factory()->create();

        $this->send($buyer, $listing, 'Prva');
        $this->send($buyer, $listing, 'Druga');

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 2);
        $this->assertSame(2, Conversation::query()->sole()->seller_unread);

        $this->actingAs($buyer)->get('/moj-nalog/poruke/novo/?oglas='.$listing->id)
            ->assertRedirect(url_slash('moj-nalog/poruke/'.Conversation::query()->sole()->id));
    }

    public function test_body_validation(): void
    {
        $listing = Listing::factory()->create();
        $buyer = User::factory()->create();

        $this->send($buyer, $listing, '   ')->assertSessionHasErrors(['body' => 'Napišite poruku.']);
        $this->send($buyer, $listing, '<p></p>')->assertSessionHasErrors('body');
        $this->send($buyer, $listing, str_repeat('a', 2001))->assertSessionHasErrors(['body' => 'Poruka može da ima najviše 2000 znakova.']);
        $this->send($buyer, $listing, str_repeat('š', 2000))->assertSessionHasNoErrors();
    }

    public function test_cannot_message_a_blocked_or_deleted_seller(): void
    {
        $buyer = User::factory()->create();
        $blocked = Listing::factory()->for(User::factory()->state(['status' => UserStatus::Blocked]))->create();
        $this->send($buyer, $blocked)->assertSessionHasErrors(['body' => 'Ovaj korisnik više ne prima poruke.']);

        $deletedSeller = User::factory()->create();
        $listing = Listing::factory()->for($deletedSeller)->create();
        $deletedSeller->delete();
        $this->send($buyer, $listing)->assertSessionHasErrors('body');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_blocked_user_cannot_send(): void
    {
        $conversation = Conversation::factory()->create();
        $buyer = $conversation->buyer;
        $buyer->forceFill(['status' => UserStatus::Blocked])->save();

        // The web group logs a blocked user out before anything else.
        $this->actingAs($buyer)->post('/moj-nalog/poruke/'.$conversation->id.'/', ['body' => 'Zdravo'])->assertRedirect();
        $this->assertDatabaseCount('messages', 0);

        // And the service refuses on its own (API tokens, jobs).
        $this->expectExceptionMessage('blokiran');
        app(Messenger::class)->reply($buyer->fresh(), $conversation, 'Zdravo');
    }

    public function test_old_listing_link_redirects_to_new_form(): void
    {
        $this->actingAs(User::factory()->create())->get('/moj-nalog/poruke/?korisnik=5&oglas=12')
            ->assertRedirect(url_slash('moj-nalog/poruke/novo').'?oglas=12');
    }

    public function test_conversation_view_marks_read_and_renders_safe_links(): void
    {
        $listing = Listing::factory()->create(['title' => 'Bicikl']);
        $buyer = User::factory()->create(['name' => 'Marko Kupac']);
        $this->send($buyer, $listing, "Pogledaj https://example.com/slika.jpg.\n<img src=x onerror=alert(1)> a < b");

        $conversation = Conversation::query()->sole();
        $this->assertSame(1, app(Messenger::class)->unreadTotal($listing->user));

        $this->actingAs($listing->user)->get('/moj-nalog/poruke/'.$conversation->id.'/')
            ->assertOk()
            ->assertSee('Marko Kupac')
            ->assertSee('Zainteresovan kupac')
            ->assertSee('Bicikl')
            ->assertSee('<a href="https://example.com/slika.jpg" rel="nofollow ugc noopener" target="_blank">https://example.com/slika.jpg</a>.', false)
            ->assertSee('a &lt; b', false)
            ->assertDontSee('onerror', false)
            ->assertSee('noindex', false);

        $conversation->refresh();
        $this->assertSame(0, $conversation->seller_unread);
        $this->assertNotNull($conversation->seller_read_at);
        $this->assertSame(0, app(Messenger::class)->unreadTotal($listing->user));
    }

    public function test_read_receipt_is_shown_to_the_sender(): void
    {
        $listing = Listing::factory()->create();
        $buyer = User::factory()->create();
        $this->send($buyer, $listing);
        $conversation = Conversation::query()->sole();

        $this->actingAs($buyer)->get('/moj-nalog/poruke/'.$conversation->id.'/')->assertSee('id="lgm-seen" hidden', false);

        $this->travel(1)->seconds();
        $this->actingAs($listing->user)->get('/moj-nalog/poruke/'.$conversation->id.'/')->assertOk();

        $this->actingAs($buyer)->get('/moj-nalog/poruke/'.$conversation->id.'/')->assertDontSee('id="lgm-seen" hidden', false)->assertSee('Pročitano');
    }

    public function test_read_receipt_is_hidden_when_the_other_side_wrote_last(): void
    {
        $listing = Listing::factory()->create();
        $buyer = User::factory()->create();
        $this->send($buyer, $listing);
        $conversation = Conversation::query()->sole();

        $this->travel(1)->seconds();
        $this->actingAs($listing->user)->post('/moj-nalog/poruke/'.$conversation->id.'/', ['body' => 'Dostupno je.'])->assertRedirect();
        $this->travel(1)->seconds();

        // The buyer reads the reply: his own message was read, but the last one is not his.
        $this->actingAs($buyer)->get('/moj-nalog/poruke/'.$conversation->id.'/')->assertSee('id="lgm-seen" hidden', false);
    }

    public function test_other_users_cannot_see_or_answer_a_conversation(): void
    {
        $conversation = Conversation::factory()->create();
        Message::factory()->for($conversation)->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get('/moj-nalog/poruke/'.$conversation->id.'/')->assertNotFound();
        $this->actingAs($stranger)->post('/moj-nalog/poruke/'.$conversation->id.'/', ['body' => 'Upad'])->assertNotFound();
        $this->actingAs($stranger)->get('/moj-nalog/poruke/'.$conversation->id.'/nove/')->assertNotFound();
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_seller_reply_reaches_buyer(): void
    {
        $listing = Listing::factory()->create();
        $buyer = User::factory()->create();
        $this->send($buyer, $listing);
        $conversation = Conversation::query()->sole();

        $this->actingAs($listing->user)->post('/moj-nalog/poruke/'.$conversation->id.'/', ['body' => 'Jeste, aktuelno je.'])
            ->assertRedirect();

        $conversation->refresh();
        $this->assertSame(1, $conversation->buyer_unread);
        $this->assertSame(0, $conversation->seller_unread);
        // Seller replies do not count as interest in the listing.
        $this->assertSame(1, (int) DB::table('listing_daily_stats')->where('listing_id', $listing->id)->value('messages'));
        $this->assertSame(1, app(Messenger::class)->unreadTotal($buyer));
    }

    public function test_reply_still_works_after_listing_expired_or_deleted(): void
    {
        $listing = Listing::factory()->create();
        $buyer = User::factory()->create();
        $this->send($buyer, $listing);
        $conversation = Conversation::query()->sole();

        $listing->update(['status' => ListingStatus::Expired]);
        $this->actingAs($listing->user)->post('/moj-nalog/poruke/'.$conversation->id.'/', ['body' => 'Prodato, hvala.'])->assertSessionHasNoErrors();

        $listing->delete();
        $this->actingAs($buyer)->get('/moj-nalog/poruke/'.$conversation->id.'/')->assertOk()->assertSee('Oglas je obrisan');
        $this->actingAs($buyer)->post('/moj-nalog/poruke/'.$conversation->id.'/', ['body' => 'Šteta.'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('messages', 3);
    }

    public function test_poll_returns_new_messages_and_marks_read(): void
    {
        $listing = Listing::factory()->create();
        $buyer = User::factory()->create();
        $this->send($buyer, $listing, 'Prva');
        $conversation = Conversation::query()->sole();
        $first = Message::query()->sole();

        $this->actingAs($listing->user)->post('/moj-nalog/poruke/'.$conversation->id.'/', ['body' => 'Odgovor <i>prodavca</i> www.primer.rs']);
        $reply = Message::query()->latest('id')->first();

        $this->actingAs($buyer)->getJson('/moj-nalog/poruke/'.$conversation->id.'/nove/?after='.$first->id)
            ->assertOk()
            ->assertJsonPath('last_id', $reply->id)
            ->assertJson(fn ($json) => $json->where('html', fn ($html) => str_contains($html, 'poruka-'.$reply->id)
                && str_contains($html, 'Odgovor prodavca')
                && str_contains($html, 'href="https://www.primer.rs" rel="nofollow ugc noopener"'))
                ->etc());

        $this->assertSame(0, $conversation->fresh()->buyer_unread);

        $this->actingAs($buyer)->getJson('/moj-nalog/poruke/'.$conversation->id.'/nove/?after='.$reply->id)
            ->assertOk()->assertJsonPath('html', '')->assertJsonPath('last_id', $reply->id);
    }

    public function test_conversation_page_loads_poll_script_and_older_messages_link(): void
    {
        config(['messages.messages_per_page' => 3]);
        $conversation = Conversation::factory()->create();
        $messages = Message::factory()->count(5)->for($conversation)->create(['sender_id' => $conversation->buyer_id]);

        $this->actingAs($conversation->seller)->get('/moj-nalog/poruke/'.$conversation->id.'/')
            ->assertOk()
            ->assertSee('js/messages.js', false)
            ->assertSee('data-poll-seconds="20"', false)
            ->assertSee('?before='.$messages[2]->id, false)
            ->assertDontSee('poruka-'.$messages[1]->id, false);

        $this->actingAs($conversation->seller)->get('/moj-nalog/poruke/'.$conversation->id.'/?before='.$messages[2]->id)
            ->assertSee('poruka-'.$messages[0]->id, false)
            ->assertSee('poruka-'.$messages[1]->id, false)
            ->assertDontSee('poruka-'.$messages[3]->id, false);
    }

    public function test_old_profile_link_goes_to_the_newest_active_listing(): void
    {
        $seller = User::factory()->create();
        Listing::factory()->for($seller)->create(['bumped_at' => now()->subDays(3)]);
        $newest = Listing::factory()->for($seller)->create(['bumped_at' => now()]);

        $this->actingAs(User::factory()->create())->get('/moj-nalog/poruke/novo/?korisnik='.$seller->id)
            ->assertRedirect(url_slash('moj-nalog/poruke/novo').'?oglas='.$newest->id);
    }

    public function test_profile_link_without_active_listings_explains_instead_of_404(): void
    {
        $seller = User::factory()->create();
        Listing::factory()->for($seller)->create(['status' => ListingStatus::Expired]);

        $this->actingAs(User::factory()->create())->get('/moj-nalog/poruke/novo/?korisnik='.$seller->id)
            ->assertRedirect(url_slash('moj-nalog/poruke'))
            ->assertSessionHas('status', 'Ovaj korisnik trenutno nema aktivnih oglasa, pa mu ne možete poslati poruku.');
    }

    public function test_profile_without_active_listings_has_no_message_button(): void
    {
        $seller = User::factory()->create();

        $this->get('/korisnik/'.$seller->slug.'/')->assertOk()->assertDontSee('poruke/novo', false);
    }
}
