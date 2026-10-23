<?php

namespace Tests\Feature\Notifications;

use App\Models\SavedSearch;
use App\Models\User;
use App\Support\Notifications\NotificationPreferences;
use Tests\TestCase;

class SavedSearchPagesTest extends TestCase
{
    use BuildsCatalog;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildCatalog();
        $this->user = User::factory()->create();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/moj-nalog/pretrage/')->assertRedirect(url_slash('prijava'));
        $this->post('/moj-nalog/pretrage/', ['keyword' => 'golf'])->assertRedirect(url_slash('prijava'));
        $this->assertSame(0, SavedSearch::count());
    }

    public function test_saving_normalizes_the_query_of_the_listings_page(): void
    {
        $this->actingAs($this->user)->post('/moj-nalog/pretrage/', [
            'keyword' => '  golf   7 ',
            'kategorija' => 'automobili',
            'mesto' => 'loznica',
            'price_max' => '5.000',
            'f' => ['marka' => 'skoda', 'kilometraza_do' => '150000', 'kilometraza_od' => ''],
            'sort' => 'newest', // not part of a saved search
        ])->assertRedirect(url_slash('moj-nalog/pretrage'))->assertSessionHas('status');

        $search = SavedSearch::sole();
        $this->assertSame($this->user->id, $search->user_id);
        $this->assertSame([
            'keyword' => 'golf 7',
            'kategorija' => 'automobili',
            'mesto' => 'loznica',
            'price_max' => 5000,
            'currency' => 'EUR',
            'f' => ['kilometraza_do' => '150000', 'marka' => 'skoda'],
        ], $search->query);
        $this->assertTrue($search->notify);
        $this->assertSame('„golf 7” · Automobili · Loznica · do 5.000 € · 2 filtera', $search->name);
        $this->assertStringStartsWith(url_slash('oglasi').'?keyword=golf%207&kategorija=automobili', $search->url());
    }

    public function test_the_same_search_is_saved_once(): void
    {
        $this->actingAs($this->user);
        $this->post('/moj-nalog/pretrage/', ['keyword' => 'golf', 'kategorija' => 'automobili']);
        $this->post('/moj-nalog/pretrage/', ['kategorija' => 'automobili', 'keyword' => ' golf '])->assertSessionHas('status', 'Ova pretraga je već sačuvana.');

        $this->assertSame(1, SavedSearch::count());
    }

    public function test_json_request_gets_the_search(): void
    {
        $this->actingAs($this->user)->postJson('/moj-nalog/pretrage/', ['keyword' => 'stan', 'name' => 'Stan u centru'])
            ->assertCreated()->assertJsonPath('name', 'Stan u centru')->assertJsonPath('notify', true);
        $this->actingAs($this->user)->postJson('/moj-nalog/pretrage/', ['keyword' => 'stan'])->assertOk();
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->actingAs($this->user);

        $this->post('/moj-nalog/pretrage/', [])->assertRedirect(url_slash('moj-nalog/pretrage'))->assertSessionHasErrors('keyword');
        $this->post('/moj-nalog/pretrage/', ['kategorija' => 'ne-postoji'])->assertSessionHasErrors('kategorija');
        $this->post('/moj-nalog/pretrage/', ['mesto' => 'ne-postoji'])->assertSessionHasErrors('mesto');
        $this->post('/moj-nalog/pretrage/', ['price_min' => 'abc'])->assertSessionHasErrors('price_min');
        $this->post('/moj-nalog/pretrage/', ['price_min' => 10, 'price_max' => 5])->assertSessionHasErrors('price_min');
        $this->post('/moj-nalog/pretrage/', ['price_min' => 10, 'currency' => 'USD'])->assertSessionHasErrors('currency');
        $this->post('/moj-nalog/pretrage/', ['keyword' => str_repeat('a', 101)])->assertSessionHasErrors('keyword');
        $this->post('/moj-nalog/pretrage/', ['kategorija' => 'automobili', 'f' => ['boja' => 'crvena']])->assertSessionHasErrors('f');
        $this->post('/moj-nalog/pretrage/', ['kategorija' => 'automobili', 'f' => ['marka' => 'fiat']])->assertSessionHasErrors('f');
        $this->post('/moj-nalog/pretrage/', ['keyword' => ['niz']])->assertSessionHasErrors('keyword');
        $this->postJson('/moj-nalog/pretrage/', [])->assertUnprocessable()->assertJsonValidationErrors('keyword');

        $this->assertSame(0, SavedSearch::count());
    }

    public function test_limit_per_user(): void
    {
        config(['notifications.saved_searches.max_per_user' => 2]);
        $this->actingAs($this->user);
        $this->post('/moj-nalog/pretrage/', ['keyword' => 'jedan']);
        $this->post('/moj-nalog/pretrage/', ['keyword' => 'dva']);
        $this->post('/moj-nalog/pretrage/', ['keyword' => 'tri'])->assertSessionHasErrors('keyword');

        $this->assertSame(2, SavedSearch::count());
    }

    public function test_list_page(): void
    {
        $this->actingAs($this->user)->get('/moj-nalog/pretrage/')->assertOk()->assertSee('Još nemate sačuvanih pretraga');

        SavedSearch::create(['user_id' => $this->user->id, 'name' => 'Golf <b>7</b>', 'query' => ['keyword' => 'golf'], 'notify' => false]);
        SavedSearch::create(['user_id' => User::factory()->create()->id, 'name' => 'Tuđa pretraga', 'query' => ['keyword' => 'x']]);

        $this->get('/moj-nalog/pretrage/')->assertOk()
            ->assertSee('<h1>Sačuvane pretrage</h1>', false)
            ->assertSee('Golf &lt;b&gt;7&lt;/b&gt;', false)
            ->assertSee('obaveštenja isključena')
            ->assertSee('Uključi obaveštenja')
            ->assertDontSee('Tuđa pretraga')
            ->assertSee('noindex', false);
    }

    public function test_list_page_warns_when_alerts_are_off(): void
    {
        NotificationPreferences::set($this->user, ['saved_searches' => false]);
        $this->actingAs($this->user)->get('/moj-nalog/pretrage/')->assertSee('Obaveštenja za sačuvane pretrage su isključena');
    }

    public function test_rename_toggle_and_delete(): void
    {
        $search = SavedSearch::create(['user_id' => $this->user->id, 'name' => 'Staro', 'query' => ['keyword' => 'golf']]);
        $this->actingAs($this->user);

        $this->patch('/moj-nalog/pretrage/'.$search->id.'/', ['name' => '  Novi   naziv '])->assertRedirect(url_slash('moj-nalog/pretrage'));
        $this->assertSame('Novi naziv', $search->fresh()->name);

        $this->patch('/moj-nalog/pretrage/'.$search->id.'/', ['name' => ''])->assertRedirect();
        $this->assertSame('„golf”', $search->fresh()->name); // empty name falls back to the description

        $this->patch('/moj-nalog/pretrage/'.$search->id.'/', ['notify' => '0']);
        $this->assertFalse($search->fresh()->notify);
        $this->patch('/moj-nalog/pretrage/'.$search->id.'/', ['notify' => '1']);
        $this->assertTrue($search->fresh()->notify);

        $this->patch('/moj-nalog/pretrage/'.$search->id.'/', ['name' => str_repeat('x', 81)])->assertSessionHasErrors('name');

        $this->delete('/moj-nalog/pretrage/'.$search->id.'/')->assertRedirect(url_slash('moj-nalog/pretrage'));
        $this->assertModelMissing($search);
    }

    public function test_searches_of_others_are_forbidden(): void
    {
        $search = SavedSearch::create(['user_id' => User::factory()->create()->id, 'name' => 'Tuđa', 'query' => ['keyword' => 'x']]);
        $this->actingAs($this->user);

        $this->patch('/moj-nalog/pretrage/'.$search->id.'/', ['name' => 'Moja'])->assertForbidden();
        $this->delete('/moj-nalog/pretrage/'.$search->id.'/')->assertForbidden();
        $this->delete('/moj-nalog/pretrage/999999/')->assertNotFound();
        $this->assertSame('Tuđa', $search->fresh()->name);
    }

    public function test_account_page_links_here(): void
    {
        $this->actingAs($this->user)->get('/moj-nalog/')->assertOk()
            ->assertSee(url_slash('moj-nalog/pretrage'), false)
            ->assertSee(url_slash('moj-nalog/obavestenja'), false);
    }
}
