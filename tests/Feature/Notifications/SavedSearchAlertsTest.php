<?php

namespace Tests\Feature\Notifications;

use App\Console\Commands\SendSavedSearchAlerts;
use App\Enums\UserStatus;
use App\Models\Device;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\SavedSearchMatch;
use App\Models\User;
use App\Notifications\SavedSearchDigestNotification;
use App\Support\Notifications\NotificationPreferences;
use App\Support\Settings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SavedSearchAlertsTest extends TestCase
{
    use BuildsCatalog, FakesFcm;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildCatalog();
        $this->user = User::factory()->create();
        Settings::set(SendSavedSearchAlerts::WATERMARK, now()->subHour()->toIso8601String());
    }

    private function search(array $query = ['kategorija' => 'vozila'], array $attributes = []): SavedSearch
    {
        return SavedSearch::create($attributes + ['user_id' => $this->user->id, 'name' => 'Automobili', 'query' => $query, 'notify' => true]);
    }

    private function runAlerts(): void
    {
        $this->artisan('notifications:saved-searches')->assertSuccessful();
    }

    public function test_command_is_scheduled_hourly(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'notifications:saved-searches'));
        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
    }

    public function test_digest_mail_for_new_matching_listings(): void
    {
        $search = $this->search();
        $car = $this->car(['title' => 'Golf 7', 'price' => 8900]);
        $this->car(['category_id' => $this->nekretnine->id]); // other category
        $this->car(['published_at' => now()->subDays(2)]); // before the window

        $this->runAlerts();

        $this->assertSame(1, app('mailer')->getSymfonyTransport()->messages()->count());
        $email = app('mailer')->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $this->assertSame('1 novi oglas za pretragu „Automobili”', $email->getSubject());
        $this->assertStringContainsString('Golf 7', $email->getHtmlBody());
        $this->assertStringContainsString('8.900 €', $email->getHtmlBody());
        $this->assertStringContainsString($car->url(), $email->getHtmlBody());
        $this->assertStringContainsString('/moj-nalog/obavestenja/odjava/'.$this->user->id.'/saved_searches', $email->getHeaders()->get('List-Unsubscribe')->getBodyAsString());
        $this->assertStringContainsString('Golf 7', (string) $email->getTextBody());

        $this->assertNotNull($search->fresh()->last_notified_at);
        $this->assertNotNull(SavedSearchMatch::sole()->notified_at);
    }

    public function test_one_digest_per_user_for_several_searches(): void
    {
        Notification::fake();
        $this->search(['kategorija' => 'vozila']);
        $this->search(['mesto' => 'loznica'], ['name' => 'Loznica']);
        $this->car();
        $this->car();

        $this->runAlerts();

        Notification::assertSentToTimes($this->user, SavedSearchDigestNotification::class, 1);
        Notification::assertSentTo($this->user, SavedSearchDigestNotification::class, function ($n) {
            $this->assertSame(4, $n->total());
            $this->assertSame('4 nova oglasa za vaše sačuvane pretrage', $n->toMail($this->user)->subject);

            return count($n->searches) === 2;
        });
    }

    public function test_at_most_one_alert_per_search_per_day(): void
    {
        Notification::fake();
        $search = $this->search();

        $this->car();
        $this->runAlerts();
        Notification::assertSentToTimes($this->user, SavedSearchDigestNotification::class, 1);

        // An hour later: new listing waits, the search was notified less than 24 h ago.
        $this->travel(1)->hours();
        $later = $this->car();
        $this->runAlerts();
        Notification::assertSentToTimes($this->user, SavedSearchDigestNotification::class, 1);
        $this->assertNull(SavedSearchMatch::where('listing_id', $later->id)->value('notified_at'));

        // A day later the waiting listing goes out, without the one already sent.
        $this->travel(24)->hours();
        $this->runAlerts();
        Notification::assertSentToTimes($this->user, SavedSearchDigestNotification::class, 2);
        Notification::assertSentTo($this->user, SavedSearchDigestNotification::class, fn ($n) => $n->total() === 1
            && $n->searches[0]['listings'][0]['url'] === $later->url());
        $this->assertTrue($search->fresh()->last_notified_at->isToday());
    }

    public function test_a_listing_is_announced_once_even_in_overlapping_windows(): void
    {
        Notification::fake();
        $this->search();
        $this->car();

        $this->runAlerts();
        $this->travel(25)->hours();
        Settings::set(SendSavedSearchAlerts::WATERMARK, now()->subDays(3)->toIso8601String());
        $this->runAlerts();

        Notification::assertSentToTimes($this->user, SavedSearchDigestNotification::class, 1);
    }

    public function test_nothing_for_disabled_alerts_inactive_listings_or_blocked_users(): void
    {
        Notification::fake();

        $this->search(['kategorija' => 'vozila'], ['notify' => false]);
        $this->runAlerts();

        $prefOff = User::factory()->create();
        NotificationPreferences::set($prefOff, ['saved_searches' => false]);
        SavedSearch::create(['user_id' => $prefOff->id, 'query' => ['kategorija' => 'vozila'], 'notify' => true]);

        $blocked = User::factory()->create();
        $blocked->forceFill(['status' => UserStatus::Blocked])->save();
        SavedSearch::create(['user_id' => $blocked->id, 'query' => ['kategorija' => 'vozila'], 'notify' => true]);

        $this->car(['status' => 'pending', 'published_at' => now()]);
        $this->car(['expires_at' => now()->subMinute()]);
        $gone = $this->car();
        $this->runAlerts(); // matched for prefOff only, then it is skipped

        $gone->delete();
        Notification::assertNothingSent();
        $this->assertSame(0, SavedSearchMatch::whereNull('notified_at')->count(), 'nothing piles up');
    }

    public function test_listing_removed_before_the_digest_is_not_sent(): void
    {
        Notification::fake();
        $this->search();
        $this->car();
        $this->runAlerts();

        $this->travel(2)->hours();
        $later = $this->car();
        $this->runAlerts(); // in cooldown: waits
        $later->update(['status' => 'expired']);

        $this->travel(23)->hours();
        $this->runAlerts();

        Notification::assertSentToTimes($this->user, SavedSearchDigestNotification::class, 1);
    }

    public function test_unverified_email_gets_no_mail_but_push(): void
    {
        $this->configureFcm();
        $this->user->forceFill(['email_verified_at' => null])->save();
        Device::create(['user_id' => $this->user->id, 'token' => str_repeat('t', 40), 'platform' => 'android']);
        $search = $this->search();
        $this->car(['title' => 'Golf']);

        $this->runAlerts();

        $this->assertSame(0, app('mailer')->getSymfonyTransport()->messages()->count());
        $sends = Http::recorded(fn (Request $r) => str_contains($r->url(), 'fcm.googleapis.com'));
        $this->assertCount(1, $sends);
        [$send] = $sends->first();
        $this->assertSame('Novi oglasi za vaše pretrage', $send['message']['notification']['title']);
        $this->assertSame('„Automobili”: 1 novi oglas', $send['message']['notification']['body']);
        $this->assertSame(['type' => 'saved_search', 'saved_search_id' => (string) $search->id, 'url' => $search->url()], (array) $send['message']['data']);
    }

    public function test_first_run_without_watermark_looks_back_one_hour(): void
    {
        Notification::fake();
        Settings::set(SendSavedSearchAlerts::WATERMARK, null);
        $this->search();
        $this->car(['published_at' => now()->subMinutes(30)]);
        $this->car(['published_at' => now()->subHours(3)]);

        $this->runAlerts();

        Notification::assertSentTo($this->user, SavedSearchDigestNotification::class, fn ($n) => $n->total() === 1);
        $this->assertNotNull(Settings::get(SendSavedSearchAlerts::WATERMARK));
    }

    public function test_listings_are_matched_in_chunks(): void
    {
        Notification::fake();
        config(['notifications.saved_searches.chunk' => 2]);
        $this->search();
        foreach (range(1, 5) as $i) {
            $this->car();
        }

        $this->artisan('notifications:saved-searches')->expectsOutputToContain('3 match jobs queued')->assertSuccessful();

        Notification::assertSentTo($this->user, SavedSearchDigestNotification::class, fn ($n) => $n->total() === 5
            && count($n->searches[0]['listings']) === 5);
    }

    public function test_old_sent_matches_are_pruned(): void
    {
        $search = $this->search();
        $listing = Listing::factory()->create();
        SavedSearchMatch::create(['saved_search_id' => $search->id, 'listing_id' => $listing->id, 'created_at' => now()->subDays(40), 'notified_at' => now()->subDays(40)]);

        $this->runAlerts();

        $this->assertSame(0, SavedSearchMatch::count());
    }

    public function test_plural_forms(): void
    {
        $this->assertSame('1 novi oglas', SavedSearchDigestNotification::newListings(1));
        $this->assertSame('3 nova oglasa', SavedSearchDigestNotification::newListings(3));
        $this->assertSame('5 novih oglasa', SavedSearchDigestNotification::newListings(5));
        $this->assertSame('11 novih oglasa', SavedSearchDigestNotification::newListings(11));
        $this->assertSame('21 novi oglas', SavedSearchDigestNotification::newListings(21));
        $this->assertSame('12 novih oglasa', SavedSearchDigestNotification::newListings(12));
        $this->assertSame('22 nova oglasa', SavedSearchDigestNotification::newListings(22));
    }
}
