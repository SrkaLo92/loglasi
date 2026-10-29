<?php

namespace Tests\Feature\Messages;

use App\Enums\AccountType;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use App\Services\Messages\Messenger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiMessagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_routes_need_a_token(): void
    {
        $this->getJson('/api/v1/conversations')->assertStatus(401)->assertJsonPath('code', 'unauthorized');
        $this->postJson('/api/v1/conversations', [])->assertStatus(401);
        $this->getJson('/api/v1/conversations/1/messages')->assertStatus(401);
        $this->postJson('/api/v1/conversations/1/messages', [])->assertStatus(401);
        $this->getJson('/api/v1/me/favorites')->assertStatus(401);
        $this->putJson('/api/v1/me/favorites/1')->assertStatus(401);
        $this->deleteJson('/api/v1/me/favorites/1')->assertStatus(401);
    }

    public function test_start_conversation_returns_contract_shape(): void
    {
        $seller = User::factory()->create(['name' => 'Firma Doo', 'account_type' => AccountType::Firm]);
        $listing = Listing::factory()->for($seller)->create(['title' => 'Stan 50 m²', 'price' => 52000]);
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/v1/conversations', ['listing_id' => $listing->id, 'body' => 'Da li je <b>stan</b> slobodan?'])
            ->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'listing' => ['id', 'title', 'price', 'currency', 'price_label', 'location', 'category', 'image', 'featured', 'premium_xl', 'seller_type', 'status', 'published_at', 'url'],
                'other_user' => ['id', 'name', 'type'],
                'last_message' => ['id', 'body', 'mine', 'sent_at', 'read'],
                'unread',
            ])
            ->assertJsonPath('listing.id', $listing->id)
            ->assertJsonPath('listing.status', 'active')
            ->assertJsonPath('other_user', ['id' => $seller->id, 'name' => 'Firma Doo', 'type' => 'firm'])
            ->assertJsonPath('last_message.body', 'Da li je stan slobodan?')
            ->assertJsonPath('last_message.mine', true)
            ->assertJsonPath('last_message.read', false)
            ->assertJsonPath('unread', 0);

        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $response->json('last_message.sent_at'));
        $this->assertSame(1, (int) DB::table('listing_daily_stats')->where('listing_id', $listing->id)->value('messages'));

        // Same listing again: existing conversation.
        $this->postJson('/api/v1/conversations', ['listing_id' => $listing->id, 'body' => 'Još jedno pitanje'])
            ->assertStatus(201)->assertJsonPath('id', $response->json('id'));
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_start_conversation_errors(): void
    {
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $listing = Listing::factory()->create();

        $this->postJson('/api/v1/conversations', ['listing_id' => $listing->id])
            ->assertStatus(422)->assertJsonPath('code', 'validation_failed')->assertJsonPath('fields.body', 'Napišite poruku.');
        $this->postJson('/api/v1/conversations', ['listing_id' => $listing->id, 'body' => str_repeat('x', 2001)])
            ->assertStatus(422)->assertJsonPath('fields.body', 'Poruka može da ima najviše 2000 znakova.');
        $this->postJson('/api/v1/conversations', ['body' => 'Zdravo'])
            ->assertStatus(422)->assertJsonPath('fields.listing_id', 'Izaberite oglas.');
        $this->postJson('/api/v1/conversations', ['listing_id' => 999999, 'body' => 'Zdravo'])
            ->assertStatus(404)->assertJsonPath('code', 'not_found');
        $this->postJson('/api/v1/conversations', ['listing_id' => Listing::factory()->pending()->create()->id, 'body' => 'Zdravo'])
            ->assertStatus(404)->assertJsonPath('message', 'Oglas ne postoji ili više nije aktivan.');

        Sanctum::actingAs($listing->user);
        $this->postJson('/api/v1/conversations', ['listing_id' => $listing->id, 'body' => 'Zdravo'])
            ->assertStatus(403)->assertJsonPath('code', 'own_listing');

        Sanctum::actingAs(User::factory()->unverified()->create());
        $this->postJson('/api/v1/conversations', ['listing_id' => $listing->id, 'body' => 'Zdravo'])
            ->assertStatus(403)->assertJsonPath('code', 'email_not_verified')->assertJsonPath('message', 'Potvrdite email adresu da biste slali poruke.');

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_list_conversations_newest_first_with_unread(): void
    {
        $user = User::factory()->create();
        $messenger = app(Messenger::class);
        $old = Listing::factory()->create();
        $mine = Listing::factory()->for($user)->create();

        $messenger->start($user, $old, 'Ja pitam');
        $this->travel(1)->minutes();
        $messenger->start(User::factory()->create(), $mine, 'Neko pita mene');
        $messenger->start(User::factory()->create(), $mine, 'Pitanje 1');
        $last = Conversation::query()->latest('id')->first();
        $messenger->reply($last->buyer, $last, 'Pitanje 2');

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-cache, private')
            ->assertJsonCount(3);

        $this->assertSame([$last->id], [$response->json('0.id')]);
        $this->assertSame(2, $response->json('0.unread'));
        $this->assertSame('Pitanje 2', $response->json('0.last_message.body'));
        $this->assertFalse($response->json('0.last_message.mine'));
        $this->assertSame(0, $response->json('2.unread'));
        $this->assertTrue($response->json('2.last_message.mine'));
    }

    public function test_conversation_pages_by_page_and_cursor(): void
    {
        $user = User::factory()->create();
        $ids = [];
        foreach (range(1, 5) as $i) {
            $ids[] = Conversation::factory()->create(['buyer_id' => $user->id, 'last_message_at' => now()->subMinutes(10 - $i)])->id;
        }
        Sanctum::actingAs($user);

        $first = $this->getJson('/api/v1/conversations?per_page=2')->assertOk()->assertHeader('X-Next-Cursor');
        $this->assertSame([$ids[4], $ids[3]], array_column($first->json(), 'id'));

        $this->assertSame([$ids[2], $ids[1]], array_column($this->getJson('/api/v1/conversations?per_page=2&page=2')->json(), 'id'));
        $byCursor = $this->getJson('/api/v1/conversations?per_page=2&cursor='.$first->headers->get('X-Next-Cursor'))->json();
        $this->assertSame([$ids[2], $ids[1]], array_column($byCursor, 'id'));

        $this->getJson('/api/v1/conversations?page=0')->assertStatus(400)->assertJsonPath('code', 'invalid_param');
    }

    public function test_messages_newest_last_mark_read_and_before(): void
    {
        config(['messages.messages_per_page' => 3]);
        $conversation = Conversation::factory()->create(['seller_unread' => 5]);
        $messages = Message::factory()->count(5)->for($conversation)->create(['sender_id' => $conversation->buyer_id]);
        $this->travel(1)->seconds();
        Sanctum::actingAs($conversation->seller);

        $page = $this->getJson('/api/v1/conversations/'.$conversation->id.'/messages')
            ->assertOk()->assertJsonCount(3)
            ->assertJsonPath('0.id', $messages[2]->id)
            ->assertJsonPath('2.id', $messages[4]->id)
            ->assertJsonPath('2.mine', false)
            ->assertJsonPath('2.read', true);
        $this->assertSame(0, $conversation->fresh()->seller_unread);
        $this->assertNotNull($conversation->fresh()->seller_read_at);

        $this->getJson('/api/v1/conversations/'.$conversation->id.'/messages?before='.$messages[2]->id)
            ->assertOk()->assertJsonCount(2)->assertJsonPath('0.id', $messages[0]->id);

        $this->getJson('/api/v1/conversations/'.$conversation->id.'/messages?before=abc')->assertStatus(400);

        // Buyer sees his messages read by the seller.
        Sanctum::actingAs($conversation->buyer);
        $this->getJson('/api/v1/conversations/'.$conversation->id.'/messages')->assertJsonPath('2.mine', true)->assertJsonPath('2.read', true);
    }

    public function test_polling_an_open_chat_gets_304_until_something_changes(): void
    {
        $conversation = Conversation::factory()->create(['seller_unread' => 1]);
        $message = Message::factory()->for($conversation)->create(['sender_id' => $conversation->buyer_id]);
        $conversation->forceFill(['last_message_at' => $message->created_at])->save();
        $this->travel(1)->seconds();
        $url = '/api/v1/conversations/'.$conversation->id.'/messages';

        Sanctum::actingAs($conversation->seller);
        $etag = $this->getJson($url)->assertOk()->headers->get('ETag');
        $readAt = $conversation->fresh()->seller_read_at;
        $this->travel(5)->seconds();

        $this->getJson($url, ['If-None-Match' => $etag])->assertStatus(304)->assertHeader('ETag', $etag);
        $this->assertEquals($readAt, $conversation->fresh()->seller_read_at); // nothing new: no write

        // The buyer answers: a new ETag and the new message.
        Sanctum::actingAs($conversation->buyer);
        $this->travel(1)->seconds();
        app(Messenger::class)->reply($conversation->buyer, $conversation->fresh(), 'Još je dostupno?');

        Sanctum::actingAs($conversation->seller);
        $this->getJson($url, ['If-None-Match' => $etag])->assertOk()->assertJsonCount(2);

        // The seller's own message turns "read" when the buyer opens the chat: the seller's ETag changes.
        $this->travel(1)->seconds();
        app(Messenger::class)->reply($conversation->seller, $conversation->fresh(), 'Jeste.');
        $mine = $this->getJson($url)->assertJsonPath('2.read', false)->headers->get('ETag');
        $this->travel(1)->seconds();
        Sanctum::actingAs($conversation->buyer);
        $this->getJson($url)->assertOk();
        Sanctum::actingAs($conversation->seller);
        $this->getJson($url, ['If-None-Match' => $mine])->assertOk()->assertJsonPath('2.read', true);
    }

    public function test_reply_and_access(): void
    {
        $listing = Listing::factory()->create();
        $buyer = User::factory()->create();
        app(Messenger::class)->start($buyer, $listing, 'Pitanje');
        $conversation = Conversation::query()->sole();

        Sanctum::actingAs($listing->user);
        $this->postJson('/api/v1/conversations/'.$conversation->id.'/messages', ['body' => 'Odgovor'])
            ->assertStatus(201)
            ->assertJsonPath('body', 'Odgovor')
            ->assertJsonPath('mine', true)
            ->assertJsonPath('read', false);
        $this->assertSame(1, $conversation->fresh()->buyer_unread);

        $this->postJson('/api/v1/conversations/'.$conversation->id.'/messages', ['body' => ''])->assertStatus(422);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/conversations/'.$conversation->id.'/messages')->assertStatus(404)->assertJsonPath('code', 'not_found');
        $this->postJson('/api/v1/conversations/'.$conversation->id.'/messages', ['body' => 'Upad'])->assertStatus(404);
        $this->getJson('/api/v1/conversations/999999/messages')->assertStatus(404);
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_api_rate_limit(): void
    {
        $conversation = Conversation::factory()->create();
        Sanctum::actingAs($conversation->buyer);

        for ($i = 1; $i <= 10; $i++) {
            $this->postJson('/api/v1/conversations/'.$conversation->id.'/messages', ['body' => 'Poruka '.$i])->assertStatus(201);
        }
        $this->postJson('/api/v1/conversations/'.$conversation->id.'/messages', ['body' => 'Previše'])
            ->assertStatus(429)->assertJsonPath('code', 'too_many_requests')->assertHeader('Retry-After');
    }

    public function test_spam_guard_on_api(): void
    {
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        foreach (Listing::factory()->count(3)->create() as $listing) {
            $this->postJson('/api/v1/conversations', ['listing_id' => $listing->id, 'body' => 'Isti tekst'])->assertStatus(201);
        }
        $this->postJson('/api/v1/conversations', ['listing_id' => Listing::factory()->create()->id, 'body' => 'Isti tekst'])
            ->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
    }

    public function test_conversation_with_deleted_listing_and_user(): void
    {
        $listing = Listing::factory()->create();
        $buyer = User::factory()->create();
        app(Messenger::class)->start($buyer, $listing, 'Pitanje');
        $listing->delete();
        $listing->user->delete();

        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/conversations')->assertOk()
            ->assertJsonPath('0.listing.id', $listing->id)
            ->assertJsonPath('0.other_user.name', 'Obrisan korisnik');
        $conversation = Conversation::query()->sole();
        $this->postJson('/api/v1/conversations/'.$conversation->id.'/messages', ['body' => 'Ima li koga?'])
            ->assertStatus(403)->assertJsonPath('code', 'recipient_unavailable');
    }
}
