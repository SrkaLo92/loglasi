<?php

namespace Tests\Feature\Notifications;

use App\Mail\Admin\ListingStatusMail;
use App\Models\Device;
use App\Models\Listing;
use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\InAppPushNotification;
use App\Services\Admin\ListingModeration;
use App\Services\Notifications\InAppNotifications;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InAppPushTest extends TestCase
{
    private function ownerWithPhone(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        Device::query()->create(['user_id' => $user->id, 'token' => 'tok-'.$user->id, 'platform' => 'android']);

        return $user;
    }

    public function test_approval_pushes_to_owner_with_listing_deep_link(): void
    {
        Notification::fake();
        Mail::fake();
        $owner = $this->ownerWithPhone();
        $listing = Listing::factory()->pending()->for($owner)->create(['title' => 'Golf 4']);

        app(ListingModeration::class)->approve($listing);

        Notification::assertSentTo($owner, InAppPushNotification::class, function (InAppPushNotification $n) use ($owner, $listing) {
            $push = $n->toPush($owner);

            return $n->via($owner) !== []
                && $push->title === 'Vaš oglas je objavljen'
                && $push->body === '„Golf 4”'
                && $push->stringData()['type'] === InAppNotifications::LISTING_APPROVED
                && $push->stringData()['listing_id'] === (string) $listing->id;
        });
        Mail::assertSent(ListingStatusMail::class);
    }

    public function test_rejection_push_has_reason_and_respects_app_setting(): void
    {
        Notification::fake();
        Mail::fake();
        $owner = $this->ownerWithPhone(['notification_settings' => ['listing_status' => false]]);
        $listing = Listing::factory()->pending()->for($owner)->create(['title' => 'Stan']);

        app(ListingModeration::class)->reject($listing, 'Nije iz Loznice.');

        Notification::assertNotSentTo($owner, InAppPushNotification::class);

        $item = UserNotification::query()->where('user_id', $owner->id)->sole();
        $push = (new InAppPushNotification($item))->toPush($owner);
        $this->assertSame('Vaš oglas nije odobren', $push->title);
        $this->assertStringContainsString('Nije iz Loznice.', $push->body);
    }

    public function test_favorite_pushes_once(): void
    {
        Notification::fake();
        $owner = $this->ownerWithPhone();
        $listing = Listing::factory()->for($owner)->create();
        $saver = User::factory()->create();

        app(InAppNotifications::class)->favorited($listing, $saver);
        app(InAppNotifications::class)->favorited($listing, $saver);

        Notification::assertSentToTimes($owner, InAppPushNotification::class, 1);
    }

    public function test_no_phone_no_push_channel(): void
    {
        Notification::fake();
        Mail::fake();
        $owner = User::factory()->create();
        $listing = Listing::factory()->pending()->for($owner)->create();

        app(ListingModeration::class)->approve($listing);

        Notification::assertNotSentTo($owner, InAppPushNotification::class);
    }
}
