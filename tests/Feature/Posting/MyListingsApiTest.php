<?php

namespace Tests\Feature\Posting;

use App\Enums\Currency;
use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use App\Services\ListingFields;
use App\Services\ListingImages;
use App\Support\Settings;
use Laravel\Sanctum\Sanctum;

class MyListingsApiTest extends PostingTestCase
{
    private function input(array $extra = []): array
    {
        return $extra + [
            'title' => 'Golf 4 na prodaju',
            'category_id' => $this->cars->id,
            'location_id' => $this->loznica->id,
            'description' => 'Očuvan, redovno servisiran.',
            'price' => 3500,
            'fields' => ['godiste' => '2004'],
        ];
    }

    private function as(?User $user = null): User
    {
        $user ??= $this->user();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_routes_need_a_token(): void
    {
        $this->postJson('/api/v1/media')->assertUnauthorized()->assertJsonPath('code', 'unauthorized');
        $this->getJson('/api/v1/me/listings')->assertUnauthorized();
        $this->postJson('/api/v1/me/listings', $this->input())->assertUnauthorized();
    }

    public function test_media_upload_returns_image(): void
    {
        $this->as();

        $response = $this->post('/api/v1/media', ['file' => $this->image('large.jpg')], ['Accept' => 'application/json'])->assertCreated();
        $response->assertJsonStructure(['id', 'thumb', 'medium', 'large', 'width', 'height'])
            ->assertJsonPath('width', 1800)->assertJsonPath('height', 1200);
        $this->assertStringEndsWith('-sm.webp', $response->json('thumb'));
        $this->assertNull(ListingImage::find($response->json('id'))->listing_id);
    }

    public function test_media_errors(): void
    {
        $this->as();
        $this->post('/api/v1/media', [], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('code', 'validation_failed')->assertJsonPath('fields.file', 'Pošaljite sliku u polju „file”.');
        $this->post('/api/v1/media', ['file' => $this->image('iphone.heic')], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('fields.file', fn ($m) => str_contains($m, 'HEIC'));
    }

    public function test_create_listing_with_images_and_condition(): void
    {
        $user = $this->as();
        $a = app(ListingImages::class)->upload($this->image('stripes.jpg'), $user);
        $b = app(ListingImages::class)->upload($this->image('photo.webp'), $user);

        $response = $this->postJson('/api/v1/me/listings', $this->input([
            'image_ids' => [$b->id, $a->id], 'condition' => 'polovno', 'currency' => 'RSD', 'price' => 350000,
        ]))->assertCreated();

        $response->assertJsonPath('status', 'active')
            ->assertJsonPath('currency', 'RSD')
            ->assertJsonPath('price', 350000)
            ->assertJsonPath('price_label', '350.000 din')
            ->assertJsonPath('condition', 'polovno')
            ->assertJsonPath('images.0.id', $b->id)
            ->assertJsonPath('image.id', $b->id)
            ->assertJsonPath('is_mine', true)
            ->assertJsonPath('category.id', $this->cars->id)
            ->assertJsonPath('url', url_slash('oglas/golf-4-na-prodaju'));
        $listing = Listing::findOrFail($response->json('id'));
        $this->assertSame('golf-4-na-prodaju', $listing->slug);
        $this->assertStringStartsWith('oglasi/'.$listing->id.'/', $a->refresh()->path);
    }

    public function test_currency_defaults_to_eur_and_negotiable(): void
    {
        $this->as();
        $this->postJson('/api/v1/me/listings', $this->input(['price_negotiable' => true]))->assertCreated()
            ->assertJsonPath('currency', 'EUR')->assertJsonPath('price', null)->assertJsonPath('price_label', 'Dogovor');
        $this->assertSame(Currency::EUR, Listing::sole()->currency);
    }

    public function test_create_goes_to_review_when_moderated(): void
    {
        Settings::set('moderation', 'all');
        $this->as();
        $this->postJson('/api/v1/me/listings', $this->input())->assertCreated()->assertJsonPath('status', 'pending');
    }

    public function test_validation_errors(): void
    {
        $this->as();
        $this->postJson('/api/v1/me/listings', [])->assertStatus(422)->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['fields' => ['title', 'category_id', 'location_id', 'description']]);
        $this->postJson('/api/v1/me/listings', $this->input(['category_id' => $this->vehicles->id]))->assertStatus(422)->assertJsonPath('fields.category_id', 'Izaberite kategoriju sa spiska (poslednji nivo).');
        $this->postJson('/api/v1/me/listings', $this->input(['fields' => []]))->assertStatus(422)->assertJsonFragment(['fields.godiste' => 'Polje „Godište” je obavezno.']);
        $this->postJson('/api/v1/me/listings', $this->input(['currency' => 'USD', 'condition' => 'lose']))->assertStatus(422)->assertJsonStructure(['fields' => ['currency', 'condition']]);
        $this->postJson('/api/v1/me/listings', $this->input(['title' => str_repeat('a', 91)]))->assertStatus(422)->assertJsonStructure(['fields' => ['title']]);
        $this->assertSame(0, Listing::count());
    }

    public function test_image_of_another_user_is_refused(): void
    {
        $this->as();
        $foreign = app(ListingImages::class)->upload($this->image(), $this->user());

        $this->postJson('/api/v1/me/listings', $this->input(['image_ids' => [$foreign->id]]))->assertStatus(422)->assertJsonPath('fields.image_ids', 'Slika #'.$foreign->id.' ne postoji ili nije vaša.');
        $this->assertSame(0, Listing::count());
    }

    public function test_limit_reached_is_403(): void
    {
        Settings::set('private_active_limit', 1);
        $user = $this->as();
        $this->published($user);

        $this->postJson('/api/v1/me/listings', $this->input())->assertForbidden()->assertJsonPath('code', 'limit_reached')
            ->assertJsonPath('message', fn (string $message) => str_starts_with($message, 'Imate već 1 aktivan oglas, koliko je besplatno'));
    }

    public function test_duplicates_are_422(): void
    {
        $user = $this->as();
        $this->published($user, ['title' => 'Golf 4 na prodaju', 'category_id' => $this->cars->id]);
        $this->postJson('/api/v1/me/listings', $this->input())->assertStatus(422)->assertJsonPath('fields.title', fn ($m) => str_contains($m, 'Već ste postavili sličan oglas'));

        $first = Listing::sole();
        $used = app(ListingImages::class)->upload($this->image('stripes.jpg'), $user, $first);
        $again = app(ListingImages::class)->upload($this->image('stripes.jpg'), $user);
        $this->postJson('/api/v1/me/listings', $this->input(['title' => 'Nešto sasvim drugo', 'image_ids' => [$again->id]]))->assertStatus(422)->assertJsonStructure(['fields' => ['image_ids']]);
        $this->assertSame(1, Listing::count());
        $this->assertModelExists($used);
    }

    public function test_list_by_status_and_show(): void
    {
        $user = $this->as();
        $active = $this->published($user, ['title' => 'Aktivan']);
        $this->published($user, ['title' => 'Čeka', 'status' => ListingStatus::Pending]);
        $this->published($user, ['title' => 'Prodat', 'status' => ListingStatus::Sold]);
        $this->published($user, ['title' => 'Istekao', 'expires_at' => now()->subDay()]);
        $this->draft($user);
        $this->published($this->user());

        $this->getJson('/api/v1/me/listings')->assertOk()->assertJsonPath('total', 4)->assertJsonPath('page', 1)->assertHeader('Cache-Control', 'no-cache, private');
        $this->getJson('/api/v1/me/listings?status=active')->assertJsonPath('total', 1)->assertJsonPath('items.0.id', $active->id)->assertJsonPath('items.0.status', 'active');
        $this->getJson('/api/v1/me/listings?status=expired')->assertJsonPath('total', 1)->assertJsonPath('items.0.title', 'Istekao');
        $this->getJson('/api/v1/me/listings?status=sold')->assertJsonPath('items.0.status', 'sold');
        $this->getJson('/api/v1/me/listings?status=pending')->assertJsonPath('items.0.title', 'Čeka');
        $this->getJson('/api/v1/me/listings?status=draft')->assertStatus(400)->assertJsonPath('code', 'invalid_param');
        $this->getJson('/api/v1/me/listings?page=2')->assertJsonPath('items', []);

        $this->getJson('/api/v1/me/listings/'.$active->id)->assertOk()->assertJsonPath('id', $active->id)->assertJsonPath('is_mine', true)->assertJsonStructure(['description', 'images', 'fields', 'seller', 'expires_at', 'reject_reason']);
    }

    public function test_other_users_listing_is_forbidden(): void
    {
        $listing = $this->published($this->user());
        $this->as();

        $this->getJson('/api/v1/me/listings/'.$listing->id)->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->patchJson('/api/v1/me/listings/'.$listing->id, ['title' => 'Moje'])->assertForbidden();
        $this->deleteJson('/api/v1/me/listings/'.$listing->id)->assertForbidden();
        $this->postJson('/api/v1/me/listings/'.$listing->id.'/renew')->assertForbidden();
        $this->getJson('/api/v1/me/listings/999999')->assertNotFound();
    }

    public function test_patch_updates_and_keeps_slug(): void
    {
        $user = $this->as();
        $listing = $this->published($user, ['title' => 'Stari', 'slug' => 'stari', 'category_id' => $this->cars->id]);

        $this->patchJson('/api/v1/me/listings/'.$listing->id, ['title' => 'Novi naslov', 'price' => 100, 'currency' => 'RSD', 'fields' => ['godiste' => '2010'], 'condition' => 'polovno'])
            ->assertOk()->assertJsonPath('title', 'Novi naslov')->assertJsonPath('price_label', '100 din')->assertJsonPath('condition', 'polovno');
        $this->assertSame('stari', $listing->refresh()->slug);

        $this->patchJson('/api/v1/me/listings/'.$listing->id, ['price_negotiable' => true])->assertOk()->assertJsonPath('price', null);
        // "novo" is for firms only
        $this->patchJson('/api/v1/me/listings/'.$listing->id, ['condition' => 'novo'])->assertStatus(422);
        $this->assertSame('2010', app(ListingFields::class)->value('godiste', $listing->fresh()));
    }

    public function test_patch_substantial_change_returns_to_review(): void
    {
        Settings::set('moderation', 'new_users');
        $user = $this->as($this->user(['phone_verified_at' => null]));
        $listing = $this->published($user);

        $this->patchJson('/api/v1/me/listings/'.$listing->id, ['price' => 10])->assertJsonPath('status', 'active');
        $image = app(ListingImages::class)->upload($this->image(), $user);
        $this->patchJson('/api/v1/me/listings/'.$listing->id, ['image_ids' => [$image->id]])->assertJsonPath('status', 'pending')->assertJsonPath('images.0.id', $image->id);
    }

    public function test_patch_rejected_listing_resubmits(): void
    {
        Settings::set('moderation', 'all');
        $user = $this->as();
        $listing = $this->published($user, ['status' => ListingStatus::Rejected, 'rejection_reason' => 'Loše slike']);

        $this->getJson('/api/v1/me/listings/'.$listing->id)->assertJsonPath('status', 'rejected')->assertJsonPath('reject_reason', 'Loše slike');
        $this->patchJson('/api/v1/me/listings/'.$listing->id, ['description' => 'Novi, bolji opis oglasa.'])->assertOk()->assertJsonPath('status', 'pending')->assertJsonPath('reject_reason', null);
    }

    public function test_delete_and_renew(): void
    {
        $user = $this->as();
        $listing = $this->published($user);
        $this->postJson('/api/v1/me/listings/'.$listing->id.'/renew')->assertStatus(409)->assertJsonPath('code', 'not_renewable');

        $expired = $this->published($user, ['status' => ListingStatus::Expired, 'expires_at' => now()->subDay()]);
        $this->postJson('/api/v1/me/listings/'.$expired->id.'/renew')->assertOk()->assertJsonPath('status', 'active');
        $this->assertTrue($expired->refresh()->expires_at->gt(now()->addDays(29)));

        $this->deleteJson('/api/v1/me/listings/'.$listing->id)->assertNoContent();
        $this->assertSoftDeleted($listing);
        $this->getJson('/api/v1/me/listings/'.$listing->id)->assertNotFound();
    }
}
