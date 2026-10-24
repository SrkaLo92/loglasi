<?php

namespace Tests\Feature\Search;

use App\Enums\Currency;
use App\Enums\FieldFilter;
use App\Enums\FieldType;
use App\Models\Field;
use App\Models\Firm;
use App\Models\User;
use App\Services\Catalog;

class ListingApiTest extends SearchTestCase
{
    public function test_shape_and_headers_follow_the_contract(): void
    {
        $listing = $this->priced('Golf 4 dizel', 3500);

        $response = $this->getJson('/api/v1/listings')->assertOk();

        $response->assertJsonStructure(['items' => [['id', 'title', 'price', 'currency', 'price_label', 'location', 'category', 'image', 'featured', 'seller_type', 'status', 'published_at', 'url']], 'page', 'total']);
        $response->assertJsonPath('page', 1)->assertJsonPath('total', 1);
        $response->assertJsonPath('items.0.id', $listing->id);
        $response->assertJsonPath('items.0.price', 3500);
        $response->assertJsonPath('items.0.price_label', '3.500 €');
        $response->assertJsonPath('items.0.seller_type', 'private');
        $response->assertHeader('X-WP-Total', '1');
        $response->assertHeader('X-WP-TotalPages', '1');
        $this->assertStringContainsString('max-age=60', $response->headers->get('Cache-Control'));

        $etag = $response->headers->get('ETag');
        $this->assertNotEmpty($etag);
        $this->get('/api/v1/listings', ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_only_active_listings_are_listed(): void
    {
        $this->priced('Aktivan', 100);
        $this->listing(['title' => 'Na čekanju'])->forceFill(['status' => 'pending'])->save();
        $this->listing(['title' => 'Istekao', 'status' => 'expired', 'expires_at' => now()->subDay()]);
        $this->listing(['title' => 'Obrisan'])->delete();

        $this->assertSame(['Aktivan'], $this->apiTitles(''));
    }

    public function test_category_includes_descendants_and_location_filters(): void
    {
        $this->priced('Auto', 100);
        $this->listing(['title' => 'Prikolica', 'category_id' => $this->vehicles->id, 'location_id' => $this->banja->id]);
        $this->listing(['title' => 'Stan', 'category_id' => $this->homes->id]);

        $this->assertEqualsCanonicalizing(['Auto', 'Prikolica'], $this->apiTitles('category='.$this->vehicles->id));
        $this->assertSame(['Auto'], $this->apiTitles('category='.$this->cars->id));
        $this->assertSame(['Prikolica'], $this->apiTitles('location='.$this->banja->id));
        $this->assertSame(['Prikolica'], $this->apiTitles('category='.$this->vehicles->id.'&location='.$this->banja->id));
        $this->assertSame([], $this->apiTitles('category=99999'));
    }

    public function test_currency_examples(): void
    {
        $this->priced('A 1.000 €', 1000);
        $this->priced('B 199.990 din', 199990, Currency::RSD);
        $this->priced('C 2.000 €', 2000);
        $this->priced('D 12.500 din', 12500, Currency::RSD);
        $this->priced('E 58.600 din', 58600, Currency::RSD);
        $this->priced('F 4.500 din', 4500, Currency::RSD);
        $this->priced('G 350.000 din', 350000, Currency::RSD);
        $this->priced('H 4.000 €', 4000);
        $this->priced('I Dogovor', null);

        $expected = ['A 1.000 €', 'B 199.990 din', 'C 2.000 €'];
        $this->assertSame($expected, $this->apiTitles('price_min=1000&price_max=2000&sort=price_asc'));
        $this->assertSame($expected, $this->apiTitles('price_min=117200&price_max=234400&currency=RSD&sort=price_asc'));
        $this->assertSame(['D 12.500 din'], $this->apiTitles('price_min=12500&price_max=12500&currency=RSD'));
        $this->assertSame(['F 4.500 din', 'D 12.500 din', 'E 58.600 din'], $this->apiTitles('price_max=60000&currency=RSD&sort=price_asc'));

        $this->assertSame(
            ['F 4.500 din', 'D 12.500 din', 'E 58.600 din', 'A 1.000 €', 'B 199.990 din', 'C 2.000 €', 'G 350.000 din', 'H 4.000 €', 'I Dogovor'],
            $this->apiTitles('sort=price_asc'),
        );
        $this->assertSame('I Dogovor', last($this->apiTitles('sort=price_desc')));
        $this->assertSame('H 4.000 €', $this->apiTitles('sort=price_desc')[0]);
    }

    public function test_featured_come_first_and_newest_uses_bump_time(): void
    {
        $this->listing(['title' => 'Star', 'bumped_at' => now()->subDays(3)]);
        $this->listing(['title' => 'Podignut', 'bumped_at' => now()->subMinute()]);
        $this->listing(['title' => 'Istaknut', 'bumped_at' => now()->subDays(9), 'featured_until' => now()->addDay()]);
        $this->listing(['title' => 'Istekao istaknut', 'bumped_at' => now()->subDays(2), 'featured_until' => now()->subDay()]);

        $this->assertSame(['Istaknut', 'Podignut', 'Istekao istaknut', 'Star'], $this->apiTitles(''));
        $this->assertSame('Istaknut', $this->apiTitles('sort=price_asc')[0]);
        $this->getJson('/api/v1/listings')->assertJsonPath('items.0.featured', true);
    }

    public function test_text_search_is_diacritics_insensitive_and_finds_short_words(): void
    {
        $this->listing(['title' => 'Ćevapi i pljeskavica', 'description' => 'Roštilj u Lešnici']);
        $this->listing(['title' => 'Audi A4 Avant', 'description' => 'Đak prodaje']);
        $this->listing(['title' => 'Golf 5', 'description' => 'Dizel']);

        $this->assertSame(['Ćevapi i pljeskavica'], $this->apiTitles('q=cevapi'));
        $this->assertSame(['Ćevapi i pljeskavica'], $this->apiTitles('q=ćevapi'));
        $this->assertSame(['Ćevapi i pljeskavica'], $this->apiTitles('q=lesnici'));
        $this->assertSame(['Ćevapi i pljeskavica'], $this->apiTitles('q=ćev'));
        $this->assertSame(['Audi A4 Avant'], $this->apiTitles('q=djak'));
        $this->assertSame(['Audi A4 Avant'], $this->apiTitles('q=a4'));
        $this->assertSame(['Audi A4 Avant'], $this->apiTitles('q=audi+a4'));
        $this->assertSame([], $this->apiTitles('q=audi+golf'));
        $this->assertSame(['Golf 5'], $this->apiTitles('q=golf&sort=relevance'));
    }

    public function test_seller_type_and_firm(): void
    {
        $this->priced('Privatni', 100);
        $firmUser = User::factory()->firm()->create();
        $this->listing(['title' => 'Firmin', 'user_id' => $firmUser->id]);
        $firm = Firm::where('user_id', $firmUser->id)->first();

        $this->assertSame(['Privatni'], $this->apiTitles('seller_type=private'));
        $this->assertSame(['Firmin'], $this->apiTitles('seller_type=firm'));
        $this->assertSame(['Firmin'], $this->apiTitles('seller_type=business'));
        $this->assertSame(['Firmin'], $this->apiTitles('firm='.$firm->id));
        $this->getJson('/api/v1/listings?seller_type=firm')->assertJsonPath('items.0.seller_type', 'firm');

        // A firm that becomes a private account leaves the firm filter at once.
        $firmUser->update(['account_type' => 'private']);
        $this->assertSame([], $this->apiTitles('seller_type=firm'));
    }

    public function test_field_filters(): void
    {
        $marka = Field::create(['key' => 'marka', 'label' => 'Marka', 'type' => FieldType::Select, 'filter' => FieldFilter::Multi,
            'options' => [['value' => 'audi', 'label' => 'Audi'], ['value' => 'bmw', 'label' => 'BMW']]]);
        $godiste = Field::create(['key' => 'godiste', 'label' => 'Godište', 'type' => FieldType::Year, 'filter' => FieldFilter::Range]);
        $this->cars->fields()->attach([$marka->id => ['sort' => 1], $godiste->id => ['sort' => 2]]);
        app(Catalog::class)->flush();

        $audi = $this->listing(['title' => 'Audi 2010']);
        $audi->fieldValues()->createMany([['field_id' => $marka->id, 'value' => 'audi'], ['field_id' => $godiste->id, 'value' => '2010', 'value_num' => 2010]]);
        $bmw = $this->listing(['title' => 'BMW 2002']);
        $bmw->fieldValues()->createMany([['field_id' => $marka->id, 'value' => 'bmw'], ['field_id' => $godiste->id, 'value' => '2002', 'value_num' => 2002]]);

        $c = 'category='.$this->cars->id;
        $this->assertSame(['Audi 2010'], $this->apiTitles($c.'&f[marka]=audi'));
        $this->assertEqualsCanonicalizing(['Audi 2010', 'BMW 2002'], $this->apiTitles($c.'&f[marka]=audi,bmw'));
        $this->assertSame(['Audi 2010'], $this->apiTitles($c.'&f[godiste_od]=2005'));
        $this->assertSame(['BMW 2002'], $this->apiTitles($c.'&f[marka]=audi,bmw&f[godiste_do]=2005'));
    }

    public function test_pagination(): void
    {
        foreach (range(1, 5) as $i) {
            $this->listing(['title' => 'Oglas '.$i, 'bumped_at' => now()->subMinutes($i)]);
        }

        $response = $this->getJson('/api/v1/listings?per_page=2&page=3')->assertOk();
        $this->assertSame(['Oglas 5'], array_column($response->json('items'), 'title'));
        $response->assertJsonPath('page', 3)->assertJsonPath('total', 5);
        $response->assertHeader('X-WP-Total', '5')->assertHeader('X-WP-TotalPages', '3');

        $this->assertSame([], $this->apiTitles('per_page=2&page=9'));
        $this->assertCount(5, $this->apiTitles('per_page=50'));
    }

    public function test_invalid_params_return_400_with_serbian_messages(): void
    {
        $this->getJson('/api/v1/listings?per_page=51&page=0&sort=cena&currency=USD&seller_type=x&price_min=-1&category=abc')
            ->assertStatus(400)
            ->assertJsonPath('code', 'invalid_param')
            ->assertJsonPath('fields.per_page', 'Broj oglasa po stranici mora biti od 1 do 50.')
            ->assertJsonPath('fields.page', 'Stranica mora biti ceo broj, 1 ili veći.')
            ->assertJsonPath('fields.sort', 'Dozvoljene vrednosti su newest, price_asc, price_desc i relevance.')
            ->assertJsonPath('fields.currency', 'Dozvoljene vrednosti su EUR i RSD.')
            ->assertJsonPath('fields.seller_type', 'Dozvoljene vrednosti su private i firm.')
            ->assertJsonPath('fields.price_min', 'Cena mora biti ceo broj, nula ili veći.')
            ->assertJsonPath('fields.category', 'Mora biti pozitivan ceo broj.');

        $this->getJson('/api/v1/listings?price_min=500&price_max=100')->assertStatus(400)
            ->assertJsonPath('fields.price_min', 'Najniža cena ne može biti veća od najviše.');
        $this->getJson('/api/v1/listings?q='.str_repeat('a', 101))->assertStatus(400)
            ->assertJsonPath('fields.q', 'Pojam za pretragu može imati najviše 100 znakova.');
        $this->getJson('/api/v1/listings?per_page=0')->assertStatus(400);
    }
}
