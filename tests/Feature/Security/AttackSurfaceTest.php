<?php

namespace Tests\Feature\Security;

use App\Enums\Role;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use App\Services\Messages\Messenger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Admin\Concerns\ActsAsStaff;
use Tests\TestCase;

/**
 * The attacks a marketplace sees most: XSS in user text, other people's data by changing an id (IDOR),
 * code disguised as an image, password guessing, privilege escalation, admin access, framing, headers.
 * Each test describes what an attacker tries and what must happen.
 */
class AttackSurfaceTest extends TestCase
{
    use ActsAsStaff;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
    }

    public function test_html_pages_send_security_headers(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $csp = (string) $response->headers->get('Content-Security-Policy');
        foreach (["frame-ancestors 'self'", "object-src 'none'", "base-uri 'self'", "form-action 'self'"] as $directive) {
            $this->assertStringContainsString($directive, $csp);
        }
        $this->assertFalse($response->headers->has('Strict-Transport-Security'), 'HSTS only over HTTPS');

        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        $this->getJson('/api/v1/categories')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_script_in_listing_title_and_description_is_escaped(): void
    {
        $payload = '<script>alert("xss")</script><img src=x onerror=alert(1)>';
        $listing = Listing::factory()->create(['title' => 'Bicikl '.$payload, 'description' => 'Opis '.$payload]);

        $html = $this->get(url_slash('oglas/'.$listing->slug))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror', $html);
    }

    public function test_script_in_a_message_is_escaped(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = Listing::factory()->for($seller)->create();
        $conversation = app(Messenger::class)->start($buyer, $listing, '<script>alert(1)</script> Zdravo')->conversation;

        $this->actingAs($seller)->get(url_slash('moj-nalog/poruke/'.$conversation->id))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_changing_the_id_never_opens_someone_elses_data(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $listing = Listing::factory()->for($owner)->create();
        $conversation = Conversation::factory()->create(['seller_id' => $owner->id, 'listing_id' => $listing->id]);

        $this->actingAs($attacker);
        $this->get(url_slash('postavi-oglas/'.$listing->id.'/osnovno'))->assertStatus(403);
        $this->put(url_slash('postavi-oglas/'.$listing->id.'/osnovno'), ['title' => 'Preoteto'])->assertStatus(403);
        $this->get(url_slash('moj-nalog/poruke/'.$conversation->id))->assertNotFound();

        Sanctum::actingAs($attacker);
        $denied = fn ($response) => $this->assertContains($response->getStatusCode(), [403, 404]);
        $denied($this->getJson('/api/v1/me/listings/'.$listing->id));
        $denied($this->patchJson('/api/v1/me/listings/'.$listing->id, ['title' => 'Preoteto']));
        $denied($this->deleteJson('/api/v1/me/listings/'.$listing->id));
        $this->getJson('/api/v1/conversations/'.$conversation->id.'/messages')->assertNotFound();

        $this->assertNotSame('Preoteto', $listing->fresh()->title);
        $this->assertNull($listing->fresh()->deleted_at);
    }

    public function test_php_disguised_as_an_image_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $php = UploadedFile::fake()->createWithContent('slika.jpg', "<?php system(\$_GET['c']); ?>");
        $polyglot = UploadedFile::fake()->createWithContent('slika.php.jpg', "\xFF\xD8\xFF\xE0<?php echo 1; ?>");

        $this->post('/api/v1/media', ['file' => $php], ['Accept' => 'application/json'])->assertStatus(422);
        $this->post('/api/v1/media', ['file' => $polyglot], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertSame(0, ListingImage::query()->count());
    }

    public function test_stored_images_are_re_encoded_webp_with_random_names(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $file = UploadedFile::fake()->image('../../../evil.php.png', 900, 700);

        $id = $this->post('/api/v1/media', ['file' => $file], ['Accept' => 'application/json'])->assertCreated()->json('id');

        $path = ListingImage::query()->findOrFail($id)->path;
        $this->assertMatchesRegularExpression('#^oglasi/[0-9a-z/_-]+/[a-z0-9]{20}\.webp$#', $path);
        $this->assertStringNotContainsString('evil', $path);
        $this->assertStringNotContainsString('..', $path);
    }

    public function test_password_guessing_is_throttled(): void
    {
        $user = User::factory()->create(['email' => 'zrtva@example.com']);
        for ($i = 0; $i < 5; $i++) {
            $this->post(url_slash('prijava'), ['email' => 'zrtva@example.com', 'password' => 'pogresna'.$i]);
        }

        // The site answers with the form and "Previše pokušaja" (a 302 back), the API with 429.
        $this->post(url_slash('prijava'), ['email' => 'zrtva@example.com', 'password' => 'pogresna9'])
            ->assertSessionHasErrors()->assertSessionHasErrorsIn('default');
        $this->assertStringContainsString('Previše pokušaja', (string) session('errors')->first());
        $this->assertGuest();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'zrtva2@example.com', 'password' => 'x'.$i]);
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'zrtva2@example.com', 'password' => 'x'])->assertStatus(429);
        $this->assertNotNull($user->fresh());
    }

    public function test_registration_cannot_grant_a_role_or_verified_status(): void
    {
        $this->post(url_slash('registracija'), [
            'name' => 'Napadač', 'email' => 'napadac@example.com', 'password' => 'tajna12345', 'password_confirmation' => 'tajna12345',
            'phone' => '0655415128', 'terms' => '1', 'role' => 'admin', 'status' => 'active',
            'email_verified_at' => now()->toDateTimeString(), 'phone_verified_at' => now()->toDateTimeString(),
        ]);

        $user = User::query()->where('email', 'napadac@example.com')->first();
        if ($user !== null) {
            $this->assertNotSame(Role::Admin, $user->role);
            $this->assertNull($user->email_verified_at);
            $this->assertNull($user->phone_verified_at);
        }
        $this->assertSame(0, User::query()->where('role', Role::Admin)->count());
    }

    public function test_profile_update_cannot_change_role_email_or_verification(): void
    {
        $user = User::factory()->create(['email' => 'moj@example.com']);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/me', ['name' => 'Novo Ime', 'role' => 'admin', 'email' => 'tudji@example.com', 'phone_verified_at' => now()->toIso8601String()]);

        $user->refresh();
        $this->assertNotSame(Role::Admin, $user->role);
        $this->assertSame('moj@example.com', $user->email);
        $this->assertNull($user->phone_verified_at);
    }

    public function test_admin_panel_is_for_staff_with_two_factor_only(): void
    {
        $this->get('/admin')->assertRedirect();
        $this->signIn(User::factory()->create())->get('/admin')->assertForbidden();
        $this->signIn($this->moderator())->get('/admin/settings')->assertForbidden();
        $this->signIn($this->admin())->get('/admin')->assertOk();
    }

    public function test_api_never_exposes_email_password_or_phone_of_others(): void
    {
        $seller = User::factory()->create(['email' => 'tajni@example.com', 'phone' => '+381655415128']);
        $listing = Listing::factory()->for($seller)->create();

        $json = $this->getJson('/api/v1/listings/'.$listing->id)->assertOk()->getContent();

        $this->assertStringNotContainsString('tajni@example.com', $json);
        $this->assertStringNotContainsString('5415128', $json);
        $this->assertStringNotContainsString('password', $json);
    }

    public function test_hidden_files_and_framework_paths_are_not_served(): void
    {
        foreach (['/.env', '/.git/config', '/composer.json', '/storage/logs/laravel.log', '/vendor/autoload.php'] as $path) {
            $this->assertContains($this->followingRedirects()->get($path)->getStatusCode(), [403, 404, 410], $path);
        }
    }
}
