<?php

namespace Tests\Feature\Messages;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use App\Services\Messages\Messenger;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlockReportEventTest extends TestCase
{
    private function conversation(): Conversation
    {
        $listing = Listing::factory()->create();
        app(Messenger::class)->start(User::factory()->create(), $listing, 'Pitanje');

        return Conversation::query()->sole();
    }

    public function test_message_sent_event_is_fired_and_no_mail_is_sent_here(): void
    {
        Mail::fake();
        Event::fake([MessageSent::class]);
        $listing = Listing::factory()->create();
        $buyer = User::factory()->create();

        $message = app(Messenger::class)->start($buyer, $listing, 'Zdravo');

        Event::assertDispatched(MessageSent::class, fn (MessageSent $e) => $e->message->is($message)
            && $e->message->relationLoaded('conversation')
            && $e->message->conversation->seller_id === $listing->user_id);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_block_stops_messages_both_ways_until_unblocked(): void
    {
        $conversation = $this->conversation();
        $seller = $conversation->seller;
        $buyer = $conversation->buyer;

        $this->actingAs($seller)->post('/moj-nalog/poruke/'.$conversation->id.'/blokiraj/')
            ->assertRedirect(url_slash('moj-nalog/poruke/'.$conversation->id))
            ->assertSessionHas('status', 'Korisnik je blokiran. Više ne možete da razmenjujete poruke.');
        $this->assertDatabaseHas('user_blocks', ['blocker_id' => $seller->id, 'blocked_id' => $buyer->id]);

        $this->actingAs($seller)->get('/moj-nalog/poruke/'.$conversation->id.'/')
            ->assertSee('Blokirali ste ovog korisnika.')
            ->assertSee('Odblokiraj korisnika')
            ->assertDontSee('name="body"', false);
        $this->actingAs($buyer)->get('/moj-nalog/poruke/'.$conversation->id.'/')
            ->assertSee('Ovaj korisnik ne prima vaše poruke.')
            ->assertDontSee('Odblokiraj korisnika');

        $this->actingAs($buyer)->post('/moj-nalog/poruke/'.$conversation->id.'/', ['body' => 'Halo?'])
            ->assertSessionHasErrors(['body' => 'Ovaj korisnik ne prima vaše poruke.']);
        $this->actingAs($seller)->post('/moj-nalog/poruke/'.$conversation->id.'/', ['body' => 'Ipak'])
            ->assertSessionHasErrors(['body' => 'Blokirali ste ovog korisnika. Odblokirajte ga da biste mu pisali.']);

        // The buyer cannot open a new conversation on another listing of the same seller either.
        $other = Listing::factory()->for($seller)->create();
        $this->actingAs($buyer)->post('/moj-nalog/poruke/novo/', ['oglas' => $other->id, 'body' => 'Drugi oglas'])->assertSessionHasErrors('body');

        Sanctum::actingAs($buyer);
        $this->postJson('/api/v1/conversations/'.$conversation->id.'/messages', ['body' => 'Halo?'])
            ->assertStatus(403)->assertJsonPath('code', 'user_blocked');

        $this->assertDatabaseCount('messages', 1);

        $this->actingAs($seller)->delete('/moj-nalog/poruke/'.$conversation->id.'/blokiraj/')
            ->assertSessionHas('status', 'Korisnik je odblokiran.');
        $this->assertDatabaseCount('user_blocks', 0);
        $this->actingAs($buyer)->post('/moj-nalog/poruke/'.$conversation->id.'/', ['body' => 'Sad može'])->assertSessionHasNoErrors();
    }

    public function test_only_participants_can_block_or_report(): void
    {
        $conversation = $this->conversation();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->post('/moj-nalog/poruke/'.$conversation->id.'/blokiraj/')->assertNotFound();
        $this->actingAs($stranger)->post('/moj-nalog/poruke/'.$conversation->id.'/prijava/', ['reason' => 'prevara'])->assertNotFound();
        $this->assertDatabaseCount('user_blocks', 0);
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_report_the_other_user(): void
    {
        $conversation = $this->conversation();
        $seller = $conversation->seller;

        $this->actingAs($seller)->get('/moj-nalog/poruke/'.$conversation->id.'/')->assertSee('Prijavi korisnika')->assertSee('value="prevara"', false);

        $this->actingAs($seller)->post('/moj-nalog/poruke/'.$conversation->id.'/prijava/', ['reason' => 'nepostojeci'])
            ->assertSessionHasErrors(['reason' => 'Izaberite razlog prijave.'], null, 'report');

        $this->actingAs($seller)->post('/moj-nalog/poruke/'.$conversation->id.'/prijava/', ['reason' => 'prevara', 'details' => 'Traži <b>uplatu</b> unapred'])
            ->assertRedirect(url_slash('moj-nalog/poruke/'.$conversation->id))
            ->assertSessionHas('status', 'Hvala, prijava je poslata moderatorima.');

        $report = Report::query()->sole();
        $this->assertSame($conversation->buyer_id, $report->reportable_id);
        $this->assertSame((new User)->getMorphClass(), $report->reportable_type);
        $this->assertSame($seller->id, $report->reporter_id);
        $this->assertSame('prevara', $report->reason);
        $this->assertSame('Razgovor #'.$conversation->id.': Traži uplatu unapred', $report->details);

        // An open report is not duplicated.
        $this->actingAs($seller)->post('/moj-nalog/poruke/'.$conversation->id.'/prijava/', ['reason' => 'drugo']);
        $this->assertDatabaseCount('reports', 1);
    }
}
