<?php

namespace Tests\Feature\Posting;

use App\Enums\Currency;
use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Support\Settings;

class WizardTest extends PostingTestCase
{
    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/postavi-oglas/')->assertRedirect(url_slash('prijava'));
        $this->post('/postavi-oglas/', ['category_id' => $this->services->id])->assertRedirect(url_slash('prijava'));
    }

    public function test_start_page_shows_root_categories_and_drills_down_without_js(): void
    {
        $user = $this->user();

        $page = $this->actingAs($user)->get('/postavi-oglas/')->assertOk();
        $page->assertSee('<h1>Postavite oglas</h1>', false)
            ->assertSee('?grupa='.$this->vehicles->id, false)
            ->assertSee('name="category_id" value="'.$this->services->id.'"', false)
            ->assertSee('noindex', false);

        $this->get('/postavi-oglas/?grupa='.$this->vehicles->id)->assertOk()
            ->assertSee('name="category_id" value="'.$this->cars->id.'"', false);
    }

    public function test_draft_is_created_only_in_a_category_without_subcategories(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/postavi-oglas/', ['category_id' => $this->vehicles->id])->assertSessionHasErrors('category_id');
        $this->assertSame(0, Listing::count());

        $response = $this->post('/postavi-oglas/', ['category_id' => $this->cars->id]);
        $listing = Listing::sole();
        $response->assertRedirect(url_slash('postavi-oglas/'.$listing->id.'/osnovno'));
        $this->assertSame(ListingStatus::Draft, $listing->status);
        $this->assertStringStartsWith('draft-', $listing->slug);
    }

    public function test_full_wizard_publishes_listing_with_slug_and_expiry(): void
    {
        $user = $this->user(['phone' => '+381641112233']);
        $this->actingAs($user)->post('/postavi-oglas/', ['category_id' => $this->cars->id]);
        $listing = Listing::sole();
        $base = '/postavi-oglas/'.$listing->id;

        $this->get($base.'/osnovno/')->assertOk()->assertSee('Osnovni podaci');
        $this->put($base.'/osnovno/', [
            'title' => 'Golf 4 1.9 TDI, očuvan',
            'description' => "Prodajem Golf 4.\nRedovno servisiran.",
            'price_type' => 'fixed', 'price' => '3.450', 'currency' => 'EUR',
            'location_id' => $this->loznica->id,
        ])->assertRedirect(url_slash('postavi-oglas/'.$listing->id.'/detalji'));

        $this->get($base.'/detalji/')->assertOk()->assertSee('Godište *');
        $this->put($base.'/detalji/', ['fields' => ['stanje' => 'polovno']])->assertSessionHasErrors('fields.godiste');
        $this->put($base.'/detalji/', ['fields' => ['godiste' => '2003', 'stanje' => 'polovno']])
            ->assertRedirect(url_slash('postavi-oglas/'.$listing->id.'/slike'));

        $this->get($base.'/slike/')->assertOk()->assertSee('Izaberite slike');
        $this->post($base.'/slike/', ['images' => [$this->image('stripes.jpg'), $this->image('photo.webp')]])
            ->assertRedirect(url_slash('postavi-oglas/'.$listing->id.'/slike'))->assertSessionHas('status', 'Dodato slika: 2.');

        $this->get($base.'/kontakt/')->assertOk()->assertSee('064 111 2233');
        $this->put($base.'/kontakt/', ['contact_phone' => '065/541-5128'])->assertRedirect(url_slash('postavi-oglas/'.$listing->id.'/pregled'));

        $this->get($base.'/pregled/')->assertOk()->assertSee('Golf 4 1.9 TDI, očuvan')->assertSee('3.450 €')->assertSee('2003.')->assertSee('065 541 5128')->assertSee('Objavi oglas');
        $this->post($base.'/objavi/')->assertRedirect(url_slash('moj-nalog/oglasi').'?status=aktivni')->assertSessionHas('status', 'Oglas je objavljen.');

        $listing->refresh();
        $this->assertSame(ListingStatus::Published, $listing->status);
        $this->assertSame('golf-4-1-9-tdi-ocuvan', $listing->slug);
        $this->assertSame(3450.0, (float) $listing->price);
        $this->assertSame(Currency::EUR, $listing->currency);
        $this->assertSame('+381655415128', $listing->contact_phone);
        $this->assertNotNull($listing->published_at);
        $this->assertTrue($listing->expires_at->between(now()->addDays(30)->subMinute(), now()->addDays(30)->addMinute()));
        $this->assertSame(2, $listing->images()->count());
        $this->assertSame("Prodajem Golf 4.\nRedovno servisiran.", $listing->description);
    }

    public function test_price_negotiable_and_rsd_and_validation(): void
    {
        $user = $this->user();
        $listing = $this->draft($user);
        $url = '/postavi-oglas/'.$listing->id.'/osnovno/';
        $valid = ['title' => 'Krečenje', 'description' => 'Krečim stanove povoljno.', 'location_id' => $this->loznica->id];

        $this->actingAs($user)->put($url, $valid + ['price_type' => 'fixed', 'price' => ''])->assertSessionHasErrors(['price' => 'Upišite cenu ili izaberite „Dogovor”.']);
        $this->put($url, $valid + ['price_type' => 'fixed', 'price' => 'mnogo'])->assertSessionHasErrors('price');
        $this->put($url, ['title' => 'ab', 'description' => 'kratko', 'location_id' => 999, 'price_type' => 'negotiable'])
            ->assertSessionHasErrors(['title', 'description', 'location_id']);

        $this->put($url, $valid + ['price_type' => 'negotiable', 'price' => '100'])->assertSessionHasNoErrors();
        $this->assertNull($listing->refresh()->price);
        $this->assertSame('Dogovor', $listing->priceLabel());

        $this->put($url, $valid + ['price_type' => 'fixed', 'price' => '12 500', 'currency' => 'RSD'])->assertSessionHasNoErrors();
        $listing->refresh();
        $this->assertSame('12.500 din', $listing->priceLabel());
        $this->assertNotNull($listing->price_eur);

        $this->put($url, $valid + ['price_type' => 'fixed', 'price' => '1.250,50'])->assertSessionHasNoErrors();
        $this->assertSame(1250.5, (float) $listing->refresh()->price);
    }

    public function test_html_is_removed_from_title_and_description(): void
    {
        $user = $this->user();
        $listing = $this->draft($user);
        $this->actingAs($user)->put('/postavi-oglas/'.$listing->id.'/osnovno/', [
            'title' => '<b>Bicikl</b> <script>x</script>', 'description' => '<p>Dobar bicikl</p> za decu', 'location_id' => $this->loznica->id, 'price_type' => 'negotiable',
        ])->assertSessionHasNoErrors();

        $listing->refresh();
        $this->assertSame('Bicikl x', $listing->title);
        $this->assertSame('Dobar bicikl za decu', $listing->description);
    }

    public function test_steps_need_owner_or_staff(): void
    {
        $listing = $this->draft($this->user());

        $this->actingAs($this->user())->get('/postavi-oglas/'.$listing->id.'/osnovno/')->assertForbidden();
        $this->put('/postavi-oglas/'.$listing->id.'/osnovno/', ['title' => 'Tuđi'])->assertForbidden();
        $this->post('/postavi-oglas/'.$listing->id.'/objavi/')->assertForbidden();

        $this->actingAs($this->user(['role' => 'moderator']))->get('/postavi-oglas/'.$listing->id.'/osnovno/')->assertOk();
    }

    public function test_image_of_another_listing_cannot_be_used_in_url(): void
    {
        $user = $this->user();
        $mine = $this->draft($user);
        $other = $this->draft($user, ['title' => 'Drugi oglas']);
        $this->actingAs($user)->post('/postavi-oglas/'.$other->id.'/slike/', ['images' => [$this->image()]]);
        $image = $other->images()->first();

        $this->delete('/postavi-oglas/'.$mine->id.'/slike/'.$image->id.'/')->assertNotFound();
        $this->assertModelExists($image);
    }

    public function test_image_errors_are_shown_and_json_answer_for_script(): void
    {
        $user = $this->user();
        $listing = $this->draft($user);
        $url = '/postavi-oglas/'.$listing->id.'/slike/';

        $this->actingAs($user)->post($url, ['images' => [$this->image('iphone.heic')]])->assertSessionHasErrors('images');
        $this->followingRedirects()->post($url, ['images' => [$this->image('iphone.heic')]])->assertOk()->assertSee('iphone.heic: HEIC slike');

        $this->postJson($url, ['images' => [$this->image('photo.webp')]])->assertOk()->assertJsonCount(1, 'images')->assertJsonPath('errors', []);
        $this->postJson($url, ['images' => [$this->image('not-image.jpg')]])->assertStatus(422)->assertJsonPath('errors.0', 'not-image.jpg: Fajl nije slika u formatu JPG, PNG ili WebP.');
    }

    public function test_image_tools_without_js(): void
    {
        $user = $this->user();
        $listing = $this->draft($user);
        $url = '/postavi-oglas/'.$listing->id.'/slike/';
        $this->actingAs($user)->post($url, ['images' => [$this->image('stripes.jpg'), $this->image('photo.webp'), $this->image('alpha.png')]]);
        [$a, $b, $c] = $listing->images()->pluck('id')->all();

        $this->post($url.$c.'/naslovna/')->assertRedirect();
        $this->assertSame([$c, $a, $b], $listing->images()->pluck('id')->all());
        $this->post($url.$b.'/pomeri/', ['direction' => 'up']);
        $this->assertSame([$c, $b, $a], $listing->images()->pluck('id')->all());
        $this->post($url.'redosled/', ['order' => [$a, $b, $c]]);
        $this->assertSame([$a, $b, $c], $listing->images()->pluck('id')->all());
        $this->delete($url.$b.'/');
        $this->assertSame([$a, $c], $listing->images()->pluck('id')->all());
    }

    public function test_changing_category_keeps_common_fields(): void
    {
        $user = $this->user();
        $listing = $this->draft($user, ['category_id' => $this->cars->id]);
        $this->actingAs($user)->put('/postavi-oglas/'.$listing->id.'/detalji/', ['fields' => ['godiste' => '2010', 'stanje' => 'polovno']]);

        $this->get('/postavi-oglas/'.$listing->id.'/kategorija/')->assertOk()->assertSee('Vozila › Automobili');
        $this->post('/postavi-oglas/'.$listing->id.'/kategorija/', ['category_id' => $this->services->id])
            ->assertRedirect(url_slash('postavi-oglas/'.$listing->id.'/osnovno'));

        $this->assertSame($this->services->id, $listing->refresh()->category_id);
        $this->assertSame(0, $listing->fieldValues()->count());
        // no fields: the details step is skipped
        $this->get('/postavi-oglas/'.$listing->id.'/detalji/')->assertRedirect(url_slash('postavi-oglas/'.$listing->id.'/slike'));
    }

    public function test_preview_lists_what_is_missing(): void
    {
        $user = $this->user();
        $listing = $this->draft($user, ['title' => '', 'category_id' => $this->cars->id]);

        $this->actingAs($user)->get('/postavi-oglas/'.$listing->id.'/pregled/')->assertOk()
            ->assertSee('Pre objave dopunite')->assertSee('Upišite naslov, opis i mesto.')->assertSee('Popunite obavezna polja kategorije.');
        $this->post('/postavi-oglas/'.$listing->id.'/objavi/')->assertRedirect(url_slash('postavi-oglas/'.$listing->id.'/pregled'))->assertSessionHasErrors(['basics', 'details']);
        $this->assertSame(ListingStatus::Draft, $listing->refresh()->status);
    }

    public function test_new_condition_only_for_firms_when_setting_is_on(): void
    {
        $user = $this->user();
        $listing = $this->draft($user, ['category_id' => $this->cars->id]);
        $url = '/postavi-oglas/'.$listing->id.'/detalji/';

        // off by default: the current site has no such rule
        $this->actingAs($user)->put($url, ['fields' => ['godiste' => '2010', 'stanje' => 'novo']])->assertSessionHasNoErrors();

        Settings::set('new_condition_firms_only', true);
        $this->put($url, ['fields' => ['godiste' => '2010', 'stanje' => 'novo']])->assertSessionHasErrors('fields.stanje');

        $firm = $this->user(['account_type' => 'firm']);
        $firmListing = $this->draft($firm, ['category_id' => $this->cars->id]);
        $this->actingAs($firm)->put('/postavi-oglas/'.$firmListing->id.'/detalji/', ['fields' => ['godiste' => '2010', 'stanje' => 'novo']])->assertSessionHasNoErrors();
    }

    public function test_editing_a_published_listing_never_changes_the_slug(): void
    {
        $user = $this->user();
        $listing = $this->published($user, ['title' => 'Stari naslov', 'slug' => 'stari-naslov']);

        $this->actingAs($user)->get('/moj-nalog/oglasi/'.$listing->id.'/izmena/')->assertRedirect(url_slash('postavi-oglas/'.$listing->id.'/osnovno'));
        $this->get('/postavi-oglas/'.$listing->id.'/osnovno/')->assertOk()->assertSee('<h1>Izmena oglasa</h1>', false);
        $this->put('/postavi-oglas/'.$listing->id.'/osnovno/', ['title' => 'Potpuno novi naslov', 'description' => 'Novi opis oglasa.', 'location_id' => $this->loznica->id, 'price_type' => 'negotiable'])
            ->assertSessionHasNoErrors();

        $listing->refresh();
        $this->assertSame('Potpuno novi naslov', $listing->title);
        $this->assertSame('stari-naslov', $listing->slug);
        $this->assertSame(ListingStatus::Published, $listing->status);
        $this->get('/postavi-oglas/'.$listing->id.'/pregled/')->assertSee('Gotovo')->assertDontSee('Objavi oglas');
    }

    public function test_substantial_edit_goes_back_to_review_for_moderated_users(): void
    {
        Settings::set('moderation', 'new_users');
        $user = $this->user(['phone_verified_at' => null]);
        $listing = $this->published($user);

        $this->actingAs($user)->put('/postavi-oglas/'.$listing->id.'/osnovno/', ['title' => $listing->title, 'description' => $listing->description, 'location_id' => $this->loznica->id, 'price_type' => 'fixed', 'price' => '99']);
        $this->assertSame(ListingStatus::Published, $listing->refresh()->status, 'price change is not substantial');

        $this->put('/postavi-oglas/'.$listing->id.'/osnovno/', ['title' => 'Novi naslov oglasa', 'description' => $listing->description, 'location_id' => $this->loznica->id, 'price_type' => 'fixed', 'price' => '99']);
        $this->assertSame(ListingStatus::Pending, $listing->refresh()->status);
    }

    public function test_rejected_listing_can_be_sent_again(): void
    {
        Settings::set('moderation', 'all');
        $user = $this->user();
        $listing = $this->published($user, ['status' => ListingStatus::Rejected, 'rejection_reason' => 'Nejasan opis.', 'slug' => 'odbijen-oglas']);

        $this->actingAs($user)->get('/postavi-oglas/'.$listing->id.'/pregled/')->assertSee('Nejasan opis.')->assertSee('Pošalji ponovo na proveru');
        $this->post('/postavi-oglas/'.$listing->id.'/objavi/')->assertRedirect(url_slash('moj-nalog/oglasi').'?status=na-cekanju');

        $listing->refresh();
        $this->assertSame(ListingStatus::Pending, $listing->status);
        $this->assertNull($listing->rejection_reason);
        $this->assertSame('odbijen-oglas', $listing->slug);
    }

    public function test_too_many_drafts(): void
    {
        $user = $this->user();
        for ($i = 0; $i < 5; $i++) {
            $this->draft($user, ['title' => 'Nacrt '.$i]);
        }

        $this->actingAs($user)->get('/postavi-oglas/')->assertSee('Nastavite započeti oglas');
        $this->post('/postavi-oglas/', ['category_id' => $this->services->id])->assertSessionHasErrors('category_id');
        $this->assertSame(5, Listing::count());
    }
}
