<?php

namespace Tests\Feature\Messages;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use App\Services\Messages\Messenger;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LimitsAndInboxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_per_minute_rate_limit(): void
    {
        $conversation = Conversation::factory()->create();
        $buyer = $conversation->buyer;

        for ($i = 1; $i <= 10; $i++) {
            $this->actingAs($buyer)->post('/moj-nalog/poruke/'.$conversation->id.'/', ['body' => 'Poruka '.$i])->assertSessionHasNoErrors();
        }

        $this->actingAs($buyer)->post('/moj-nalog/poruke/'.$conversation->id.'/', ['body' => 'Jedanaesta'])
            ->assertSessionHasErrors(['body' => 'Poslali ste previše poruka za kratko vreme. Sačekajte malo pa probajte ponovo.']);
        $this->assertDatabaseCount('messages', 10);

        $this->travel(61)->seconds();
        $this->actingAs($buyer)->post('/moj-nalog/poruke/'.$conversation->id.'/', ['body' => 'Posle minuta'])->assertSessionHasNoErrors();
    }

    public function test_per_day_rate_limit(): void
    {
        config(['messages.per_day' => 3]);
        $conversation = Conversation::factory()->create();

        foreach (['a', 'b', 'c'] as $body) {
            $this->actingAs($conversation->buyer)->post('/moj-nalog/poruke/'.$conversation->id.'/', ['body' => $body])->assertSessionHasNoErrors();
        }
        $this->travel(2)->minutes();
        $this->actingAs($conversation->buyer)->post('/moj-nalog/poruke/'.$conversation->id.'/', ['body' => 'd'])->assertSessionHasErrors('body');
        $this->assertDatabaseCount('messages', 3);
    }

    public function test_same_text_to_many_sellers_is_refused(): void
    {
        $buyer = User::factory()->create();
        $listings = Listing::factory()->count(5)->create();
        $spam = 'Kupujem sve, javite se na 064 111 222';

        foreach ($listings->take(3) as $listing) {
            $this->actingAs($buyer)->post('/moj-nalog/poruke/novo/', ['oglas' => $listing->id, 'body' => $spam])->assertSessionHasNoErrors();
        }

        $this->actingAs($buyer)->post('/moj-nalog/poruke/novo/', ['oglas' => $listings[3]->id, 'body' => '  kupujem SVE, javite se na 064 111 222 '])
            ->assertSessionHasErrors(['body' => 'Istu poruku ste već poslali većem broju prodavaca. Napišite poruku za ovaj oglas ili pokušajte sutra.']);

        // A personal message still goes through, and the same text again in an existing conversation is fine.
        $this->actingAs($buyer)->post('/moj-nalog/poruke/novo/', ['oglas' => $listings[3]->id, 'body' => 'Da li je bicikl još tu?'])->assertSessionHasNoErrors();
        $first = Conversation::query()->where('listing_id', $listings[0]->id)->sole();
        $this->actingAs($buyer)->post('/moj-nalog/poruke/'.$first->id.'/', ['body' => $spam])->assertSessionHasNoErrors();

        $this->travel(25)->hours();
        $this->actingAs($buyer)->post('/moj-nalog/poruke/novo/', ['oglas' => $listings[4]->id, 'body' => $spam])->assertSessionHasNoErrors();
    }

    public function test_new_conversations_per_day_limit(): void
    {
        config(['messages.new_conversations_per_day' => 2]);
        $buyer = User::factory()->create();
        $listings = Listing::factory()->count(3)->create();

        $this->actingAs($buyer)->post('/moj-nalog/poruke/novo/', ['oglas' => $listings[0]->id, 'body' => 'Prvi oglas?'])->assertSessionHasNoErrors();
        $this->actingAs($buyer)->post('/moj-nalog/poruke/novo/', ['oglas' => $listings[1]->id, 'body' => 'Drugi oglas?'])->assertSessionHasNoErrors();
        $this->actingAs($buyer)->post('/moj-nalog/poruke/novo/', ['oglas' => $listings[2]->id, 'body' => 'Treći oglas?'])
            ->assertSessionHasErrors(['body' => 'Danas ste započeli mnogo razgovora. Pokušajte ponovo sutra.']);
        $this->assertDatabaseCount('conversations', 2);
    }

    public function test_inbox_groups_by_listing_with_last_message_and_unread(): void
    {
        $seller = User::factory()->create();
        $bike = Listing::factory()->for($seller)->create(['title' => 'Bicikl Capriolo']);
        $sofa = Listing::factory()->for($seller)->create(['title' => 'Kauč na razvlačenje']);
        $messenger = app(Messenger::class);

        $ana = User::factory()->create(['name' => 'Ana Anić']);
        $ivan = User::factory()->create(['name' => 'Ivan Ivić']);
        $messenger->start($ana, $bike, 'Da li je bicikl još tu?');
        $this->travel(1)->minutes();
        $messenger->start($ivan, $sofa, 'Koliko je širok kauč?');
        $this->travel(1)->minutes();
        $messenger->start($ivan, $bike, 'Može li zamena za bicikl?');
        $messenger->start($ivan, $bike, 'I još nešto.');

        $html = $this->actingAs($seller)->get('/moj-nalog/poruke/')
            ->assertOk()
            ->assertSee('<h1>Poruke</h1>', false)
            ->assertSee('Imate 4 nepročitane poruke.')
            ->assertSee('Ana Anić')
            ->assertSee('Ivan Ivić')
            ->assertSee('I još nešto.')
            ->assertSee('Koliko je širok kauč?')
            ->assertSee('kupac')
            ->getContent();

        // The bike group (newest activity) comes first and holds both buyers.
        $this->assertLessThan(strpos($html, 'Kauč na razvlačenje'), strpos($html, 'Bicikl Capriolo'));
        $this->assertSame(1, substr_count($html, 'Bicikl Capriolo</a>'));
        $this->assertLessThan(strpos($html, 'Ana Anić'), strpos($html, 'Ivan Ivić'));

        // The buyer sees his own last message with "Vi:".
        $this->actingAs($ivan)->get('/moj-nalog/poruke/')->assertSee('Vi:')->assertSee('prodavac');
    }

    public function test_inbox_keyset_pages(): void
    {
        config(['messages.inbox_per_page' => 2]);
        $user = User::factory()->create();
        $conversations = collect();
        foreach (range(1, 5) as $i) {
            $as = $i % 2 ? ['buyer_id' => $user->id] : ['seller_id' => $user->id];
            $conversations->push(Conversation::factory()->create($as + ['last_message_at' => now()->subMinutes(10 - $i)]));
        }

        $messenger = app(Messenger::class);
        $first = $messenger->inbox($user, null, 2);
        $this->assertSame([$conversations[4]->id, $conversations[3]->id], $first['items']->pluck('id')->all());
        $second = $messenger->inbox($user, $first['next'], 2);
        $this->assertSame([$conversations[2]->id, $conversations[1]->id], $second['items']->pluck('id')->all());
        $third = $messenger->inbox($user, $second['next'], 2);
        $this->assertSame([$conversations[0]->id], $third['items']->pluck('id')->all());
        $this->assertNull($third['next']);

        $this->actingAs($user)->get('/moj-nalog/poruke/')->assertSee('?cursor='.urlencode($first['next']), false)->assertSee('Stariji razgovori');
        $this->actingAs($user)->get('/moj-nalog/poruke/?cursor='.urlencode($second['next']))->assertOk()->assertSee('Najnoviji razgovori');
        $this->actingAs($user)->get('/moj-nalog/poruke/?cursor=%%%')->assertOk();
    }

    public function test_empty_inbox(): void
    {
        $this->actingAs(User::factory()->create())->get('/moj-nalog/poruke/')->assertOk()->assertSee('Još nemate poruka.');
    }

    public function test_bottom_nav_shows_unread_count(): void
    {
        $conversation = Conversation::factory()->create(['seller_unread' => 3]);

        $this->actingAs($conversation->seller)->get('/moj-nalog/poruke/')
            ->assertSee('lg-mnav__broj', false)
            ->assertSee('Poruke, 3 nepročitane', false);

        $this->actingAs($conversation->buyer)->get('/moj-nalog/poruke/')->assertDontSee('lg-mnav__broj', false);
    }

    public function test_unread_total_is_cached_and_reset_on_send_and_read(): void
    {
        $listing = Listing::factory()->create();
        $buyer = User::factory()->create();
        $messenger = app(Messenger::class);

        $this->assertSame(0, $messenger->unreadTotal($listing->user));
        $messenger->start($buyer, $listing, 'Zdravo');
        $this->assertSame(1, $messenger->unreadTotal($listing->user));

        $conversation = Conversation::query()->sole();
        $messenger->markRead($listing->user, $conversation);
        $this->assertSame(0, $messenger->unreadTotal($listing->user));

        $messenger->reply($listing->user, $conversation->fresh(), 'Zdravo i vama');
        $this->assertSame(1, $messenger->unreadTotal($buyer));
        $this->assertSame(1, Message::query()->where('sender_id', $listing->user_id)->count());
    }
}
