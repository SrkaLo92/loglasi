<?php

namespace Tests\Feature\Posting;

use App\Enums\Role;
use App\Mail\Admin\NewListingMail;
use App\Services\Notifications\StaffAlerts;
use App\Services\Posting;
use App\Support\Settings;
use Illuminate\Support\Facades\Mail;

class NewListingMailTest extends PostingTestCase
{
    public function test_admins_get_a_mail_for_each_new_listing(): void
    {
        Mail::fake();
        $admin = $this->user(['role' => Role::Admin, 'email' => 'admin@gmail.com']);
        $this->user(['role' => Role::Moderator, 'email' => 'mod@gmail.com']);
        $user = $this->user();
        $draft = $this->draft($user, ['title' => 'Golf 4 dizel']);

        app(Posting::class)->submit($draft, $user);

        Mail::assertQueued(NewListingMail::class, 1);
        Mail::assertQueued(NewListingMail::class, function (NewListingMail $mail) use ($admin, $draft) {
            $text = $mail->render();

            return $mail->hasTo($admin->email) && str_contains($text, 'Golf 4 dizel')
                && str_contains($text, '/admin/listings/'.$draft->id.'/edit') && str_contains($mail->envelope()->subject, 'Golf 4 dizel');
        });
    }

    public function test_setting_turns_it_off(): void
    {
        Mail::fake();
        Settings::set(StaffAlerts::NEW_LISTING_SETTING, false);
        $this->user(['role' => Role::Admin, 'email' => 'admin@gmail.com']);
        $user = $this->user();

        app(Posting::class)->submit($this->draft($user), $user);

        Mail::assertNotQueued(NewListingMail::class);
    }
}
