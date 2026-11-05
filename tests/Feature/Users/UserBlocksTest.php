<?php

namespace Tests\Feature\Users;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\User;
use App\Services\Messages\Messenger;
use App\Services\SearchIndex;
use App\Services\Users\UserBlocks;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserBlocksTest extends TestCase
{
    public function test_block_and_unblock_from_the_profile_and_the_settings_list(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create(['name' => 'Pera Perić']);

        $this->get('/korisnik/'.$other->slug.'/')->assertOk()->assertDontSee('Blokiraj korisnika');
        $this->post('/moj-nalog/blokirani/'.$other->id.'/')->assertRedirect();
        $this->actingAs($me)->get('/korisnik/'.$other->slug.'/')->assertOk()->assertSee('Blokiraj korisnika');
        $this->actingAs($me)->get('/korisnik/'.$me->slug.'/')->assertOk()->assertDontSee('Blokiraj korisnika');

        $this->actingAs($me)->from('/korisnik/'.$other->slug.'/')->post('/moj-nalog/blokirani/'.$other->id.'/')
            ->assertRedirect('/korisnik/'.$other->slug.'/')->assertSessionHas('status');
        $this->assertDatabaseHas('user_blocks', ['blocker_id' => $me->id, 'blocked_id' => $other->id]);
        $this->actingAs($me)->get('/korisnik/'.$other->slug.'/')->assertSee('Odblokiraj korisnika');

        $this->actingAs($me)->get('/moj-nalog/podesavanja/')->assertOk()->assertSee('Blokirani korisnici')->assertSee('Pera Perić');

        $this->actingAs($me)->delete('/moj-nalog/blokirani/'.$other->id.'/')->assertSessionHas('status', 'Korisnik je odblokiran.');
        $this->assertDatabaseCount('user_blocks', 0);

        $this->actingAs($me)->post('/moj-nalog/blokirani/'.$me->id.'/')->assertNotFound();
        $this->assertDatabaseCount('user_blocks', 0);
    }

    public function test_api_block_list_and_is_blocked_flags(): void
    {
        $me = User::factory()->create();
        $listing = Listing::factory()->create();
        $seller = $listing->user;
        app(Messenger::class)->start($me, $listing, 'Da li je dostupno?');
        $conversation = Conversation::query()->sole();

        $this->postJson('/api/v1/users/'.$seller->id.'/block')->assertUnauthorized();

        Sanctum::actingAs($me);
        $this->getJson('/api/v1/listings/'.$listing->id)->assertOk()->assertJsonPath('seller.is_blocked', false);

        $this->postJson('/api/v1/users/'.$seller->id.'/block')->assertNoContent();
        $this->postJson('/api/v1/users/'.$seller->id.'/block')->assertNoContent();
        $this->postJson('/api/v1/users/'.$me->id.'/block')->assertNotFound();
        $this->postJson('/api/v1/users/999999/block')->assertNotFound();

        $this->getJson('/api/v1/me/blocks')->assertOk()
            ->assertJsonPath('data.0.id', $seller->id)
            ->assertJsonPath('data.0.name', $seller->name)
            ->assertJsonStructure(['data' => [['id', 'name', 'blocked_at']]]);
        $this->getJson('/api/v1/listings/'.$listing->id)->assertOk()->assertJsonPath('seller.is_blocked', true);
        $this->getJson('/api/v1/conversations/'.$conversation->id.'/messages')->assertOk();

        $this->deleteJson('/api/v1/users/'.$seller->id.'/block')->assertNoContent();
        $this->getJson('/api/v1/me/blocks')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_blocked_users_conversations_leave_inbox_and_unread_count(): void
    {
        $listing = Listing::factory()->create();
        $seller = $listing->user;
        $buyer = User::factory()->create();
        app(Messenger::class)->start($buyer, $listing, 'Zdravo, da li je još dostupno?');

        Sanctum::actingAs($seller);
        $this->getJson('/api/v1/conversations')->assertOk()->assertJsonPath('0.other_user.is_blocked', false);
        $this->assertSame(1, app(Messenger::class)->unreadTotal($seller));

        $this->postJson('/api/v1/users/'.$buyer->id.'/block')->assertNoContent();
        $this->getJson('/api/v1/conversations')->assertOk()->assertJsonCount(0);
        $this->assertSame(0, app(Messenger::class)->unreadTotal($seller));

        // The blocked buyer still sees the conversation, without being told why messages are refused.
        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/conversations')->assertOk()->assertJsonCount(1);
        $this->postJson('/api/v1/conversations/'.Conversation::query()->sole()->id.'/messages', ['body' => 'Halo?'])
            ->assertStatus(403)->assertJsonPath('message', 'Ne možete poslati poruku ovom korisniku.');
    }

    public function test_blocked_sellers_listings_leave_search_and_home(): void
    {
        $me = User::factory()->create();
        $hidden = Listing::factory()->create(['title' => 'Skriveni bicikl']);
        $shown = Listing::factory()->create(['title' => 'Vidljivi frižider']);
        app(SearchIndex::class)->refresh();

        app(UserBlocks::class)->block($me, $hidden->user_id);

        Sanctum::actingAs($me);
        $ids = collect($this->getJson('/api/v1/listings')->assertOk()->json('items'))->pluck('id');
        $this->assertTrue($ids->contains($shown->id));
        $this->assertFalse($ids->contains($hidden->id));

        $this->actingAs($me)->get('/')->assertOk()->assertDontSee('Skriveni bicikl')->assertSee('Vidljivi frižider');
        $this->actingAs($me)->get('/oglasi/')->assertOk()->assertDontSee('Skriveni bicikl')->assertSee('Vidljivi frižider');

        // Others still see it.
        $this->app['auth']->forgetGuards();
        $guestIds = collect($this->getJson('/api/v1/listings')->assertOk()->json('items'))->pluck('id');
        $this->assertTrue($guestIds->contains($hidden->id));
    }
}
