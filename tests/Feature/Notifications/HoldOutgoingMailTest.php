<?php

namespace Tests\Feature\Notifications;

use App\Mail\Admin\ListingStatusMail;
use App\Mail\ListingExpiringSoon;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HoldOutgoingMailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'array', 'notifications.hold' => true, 'notifications.allow' => ['office@loglasi.rs']]);
    }

    private function sent(): array
    {
        return array_map(fn ($m) => $m->getOriginalMessage(), iterator_to_array(app('mailer')->getSymfonyTransport()->messages()));
    }

    public function test_on_hold_a_password_reset_is_held_unless_requested_mails_are_allowed(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->post('/zaboravljena-lozinka/', ['email' => 'ana@example.com'])->assertSessionHas('status');
        $this->assertCount(0, $this->sent());

        config(['notifications.allow_requested' => true]);
        $this->travel(2)->minutes(); // the broker throttles a second link for a minute
        $this->post('/zaboravljena-lozinka/', ['email' => 'ana@example.com'])->assertSessionHas('status');

        $sent = $this->sent();
        $this->assertCount(1, $sent);
        $this->assertSame('ana@example.com', $sent[0]->getTo()[0]->getAddress());
        $this->assertFalse($sent[0]->getHeaders()->has('X-Loglasi-Requested'));
    }

    public function test_mails_the_site_sends_by_itself_stay_held_even_when_requested_mails_are_allowed(): void
    {
        config(['notifications.allow_requested' => true]);
        $listing = Listing::factory()->create();

        Mail::to('ana@example.com')->send(new ListingExpiringSoon($listing));
        Mail::to('office@loglasi.rs')->send(new ListingExpiringSoon($listing));

        $sent = $this->sent();
        $this->assertCount(1, $sent);
        $this->assertSame('office@loglasi.rs', $sent[0]->getTo()[0]->getAddress());
    }

    public function test_approval_mail_passes_for_a_listing_posted_here_but_not_for_an_imported_one(): void
    {
        config(['notifications.allow_requested' => true]);
        $own = Listing::factory()->create();
        $imported = Listing::factory()->create(['wp_id' => 12345]);

        Mail::to('ana@example.com')->send(new ListingStatusMail($own, true));
        Mail::to('pera@example.com')->send(new ListingStatusMail($imported, true));

        $sent = $this->sent();
        $this->assertCount(1, $sent);
        $this->assertSame('ana@example.com', $sent[0]->getTo()[0]->getAddress());
    }
}
