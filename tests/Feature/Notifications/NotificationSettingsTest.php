<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/moj-nalog/obavestenja/podesavanja/')->assertRedirect(url_slash('login-registracija'));
        $this->put('/moj-nalog/obavestenja/podesavanja/', [])->assertRedirect(url_slash('login-registracija'));
    }

    public function test_page_shows_defaults_with_newsletter_off(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/moj-nalog/obavestenja/podesavanja/')->assertOk()
            ->assertSee('<h1>Podešavanja obaveštenja</h1>', false)
            ->assertSee('Nove poruke')->assertSee('Novi oglasi za sačuvane pretrage')->assertSee('Istek oglasa')
            ->assertSee('Novosti sa loglasi.rs')
            ->assertSee('noindex', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/name="messages" value="1" checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="newsletter" value="1" checked/', $html);
        $this->assertFalse(NotificationPreferences::allows($user, 'newsletter'));
        $this->assertTrue(NotificationPreferences::allows($user, 'saved_searches'));
    }

    public function test_saving_keeps_the_api_keys(): void
    {
        $user = User::factory()->create(['email' => 'pera@example.com']);
        $user->forceFill(['notification_settings' => ['payments' => false, 'listing_status' => true]])->save();

        $this->actingAs($user)->put('/moj-nalog/obavestenja/podesavanja/', ['messages' => '1', 'newsletter' => '1'])
            ->assertRedirect(url_slash('moj-nalog/obavestenja/podesavanja'))->assertSessionHas('status');

        $user->refresh();
        $this->assertSame([
            'messages' => true, 'saved_searches' => false, 'expiring' => false, 'newsletter' => true,
        ], NotificationPreferences::all($user));
        $this->assertFalse($user->notification_settings['payments']);
        $this->assertFalse($user->notificationSettings()['expiring']); // same key as the app
    }

    public function test_invalid_values_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put('/moj-nalog/obavestenja/podesavanja/', ['messages' => 'možda'])->assertSessionHasErrors('messages');
    }

    public function test_unsubscribe_link_asks_then_turns_the_category_off(): void
    {
        $user = User::factory()->create();
        $url = NotificationPreferences::unsubscribeUrl($user, 'saved_searches');

        // GET only confirms (link scanners open links), nothing changes.
        $this->get($url)->assertOk()->assertSee('Odjavi me')->assertSee('noindex', false);
        $this->assertTrue(NotificationPreferences::allows($user->fresh(), 'saved_searches'));

        // Works without signing in, as the one-click POST of a mail client (RFC 8058).
        $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertOk()->assertSee('Više vam ne šaljemo obaveštenja');
        $this->assertFalse(NotificationPreferences::allows($user->fresh(), 'saved_searches'));
        $this->assertTrue(NotificationPreferences::allows($user->fresh(), 'messages'));

        $this->get($url)->assertOk()->assertDontSee('Odjavi me');
    }

    public function test_one_click_post_does_not_need_a_csrf_token(): void
    {
        $route = app('router')->getRoutes()->match(Request::create('/moj-nalog/obavestenja/odjava/1/messages', 'POST'));

        $this->assertContains(ValidateCsrfToken::class, $route->excludedMiddleware());
        $this->assertContains('signed', $route->gatherMiddleware());
    }

    public function test_unsubscribe_link_must_be_signed(): void
    {
        $user = User::factory()->create();
        $url = NotificationPreferences::unsubscribeUrl($user, 'messages');
        $other = User::factory()->create();

        $this->get(str_replace('/'.$user->id.'/', '/'.$other->id.'/', $url))->assertForbidden();
        $this->post(str_replace('/messages', '/newsletter', $url))->assertForbidden();
        $this->post('/moj-nalog/obavestenja/odjava/'.$user->id.'/messages')->assertForbidden();
        $this->assertTrue(NotificationPreferences::allows($user->fresh(), 'messages'));
    }

    public function test_unknown_category_is_refused(): void
    {
        $user = User::factory()->create();
        $this->expectException(\InvalidArgumentException::class);
        NotificationPreferences::unsubscribeUrl($user, 'everything');
    }
}
