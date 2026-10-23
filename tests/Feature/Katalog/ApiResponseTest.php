<?php

namespace Tests\Feature\Katalog;

use App\Http\Api\ApiError;
use App\Http\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api/v1/proba')->group(function () {
            Route::get('kvar', fn () => throw new RuntimeException('tajna poruka'));
            Route::get('greska', fn () => throw new ApiError('limit_reached', 'Potrošili ste mesečni limit oglasa.', 403, ['limit' => 'Najviše 10 oglasa.']));
            Route::get('provera', fn (Request $r) => $r->validate(['n' => ['required', 'integer']], ['n.required' => 'Obavezno polje.']));
            Route::post('provera', fn (Request $r) => $r->validate(['naslov' => ['required']], ['naslov.required' => 'Unesite naslov.']));
            Route::get('podaci', fn (Request $r) => ApiResponse::json($r, ['grad' => 'Loznica — Šabac', 'url' => 'https://loglasi.rs/oglasi/']));
            Route::get('privatno', fn (Request $r) => ApiResponse::json($r, ['a' => 1], ApiResponse::CACHE_PRIVATE));
            Route::post('upis', fn (Request $r) => ApiResponse::json($r, ['ok' => true], null, 201));
        });
    }

    public function test_unknown_route_and_method(): void
    {
        $this->getJson('/api/v1/ne-postoji')
            ->assertNotFound()
            ->assertExactJson(['code' => 'not_found', 'message' => 'Tražena adresa ne postoji.']);

        $this->deleteJson('/api/v1/locations')
            ->assertStatus(405)
            ->assertJsonPath('code', 'method_not_allowed');
    }

    public function test_server_error_hides_details(): void
    {
        $response = $this->getJson('/api/v1/proba/kvar')->assertStatus(500);

        $response->assertExactJson(['code' => 'server_error', 'message' => 'Došlo je do greške. Pokušajte ponovo.']);
        $this->assertStringNotContainsString('tajna', $response->getContent());
    }

    public function test_api_error_keeps_code_message_and_fields(): void
    {
        $this->get('/api/v1/proba/greska')
            ->assertStatus(403)
            ->assertExactJson(['code' => 'limit_reached', 'message' => 'Potrošili ste mesečni limit oglasa.', 'fields' => ['limit' => 'Najviše 10 oglasa.']]);
    }

    public function test_validation_is_400_for_query_and_422_for_body(): void
    {
        $this->getJson('/api/v1/proba/provera')
            ->assertStatus(400)
            ->assertExactJson(['code' => 'invalid_param', 'message' => 'Neispravni parametri.', 'fields' => ['n' => 'Obavezno polje.']]);

        $this->postJson('/api/v1/proba/provera')
            ->assertStatus(422)
            ->assertExactJson(['code' => 'validation_failed', 'message' => 'Unesite naslov.', 'fields' => ['naslov' => 'Unesite naslov.']]);
    }

    public function test_json_is_not_escaped_and_etag_rules(): void
    {
        $response = $this->get('/api/v1/proba/podaci')->assertOk();

        $this->assertSame('{"grad":"Loznica — Šabac","url":"https://loglasi.rs/oglasi/"}', $response->getContent());
        $this->assertStringContainsString('max-age=60', $response->headers->get('Cache-Control'));
        $etag = $response->headers->get('ETag');
        $this->assertSame(ApiResponse::etag($response->getContent()), $etag);

        $this->get('/api/v1/proba/podaci', ['If-None-Match' => '*'])->assertStatus(304);
        $this->get('/api/v1/proba/podaci', ['If-None-Match' => $etag])->assertStatus(304);
        $this->get('/api/v1/proba/podaci', ['If-None-Match' => '"x"'])->assertOk();
    }

    public function test_private_cache_and_non_get(): void
    {
        $this->assertStringContainsString('private', $this->get('/api/v1/proba/privatno')->headers->get('Cache-Control'));

        $created = $this->post('/api/v1/proba/upis')->assertCreated();
        $this->assertFalse($created->headers->has('ETag'));
    }

    public function test_etag_matching(): void
    {
        $this->assertTrue(ApiResponse::matches('W/"abc"', 'W/"abc"'));
        $this->assertTrue(ApiResponse::matches('"abc"', 'W/"abc"'));
        $this->assertTrue(ApiResponse::matches('"x", W/"abc"', 'W/"abc"'));
        $this->assertFalse(ApiResponse::matches('', 'W/"abc"'));
        $this->assertFalse(ApiResponse::matches('"abcd"', 'W/"abc"'));
    }
}
