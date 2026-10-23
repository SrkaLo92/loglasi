<?php

namespace Tests\Feature\Messages;

use App\Jobs\SendNewMessageMail;
use App\Mail\NewMessageMail;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\User;
use App\Services\Messages\Messenger;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MessageMailTest extends TestCase
{
    public function test_job_is_queued_with_a_delay(): void
    {
        Queue::fake();
        $listing = Listing::factory()->create();

        app(Messenger::class)->start(User::factory()->create(), $listing, 'Zdravo');

        Queue::assertPushed(SendNewMessageMail::class, fn ($job) => $job->recipientId === $listing->user_id && $job->delay !== null);
    }

    public function test_recipient_gets_one_mail_per_conversation_in_the_window(): void
    {
        Mail::fake();
        $seller = User::factory()->create(['name' => 'Petar Prodavac']);
        $listing = Listing::factory()->for($seller)->create(['title' => 'Traktor IMT 539']);
        $buyer = User::factory()->create(['name' => 'Jovan Kupac']);
        $messenger = app(Messenger::class);

        $messenger->start($buyer, $listing, 'Prva poruka');
        $messenger->start($buyer, $listing, 'Druga poruka');

        Mail::assertSent(NewMessageMail::class, 1);
        Mail::assertSent(NewMessageMail::class, function (NewMessageMail $mail) use ($seller) {
            $text = $mail->render();

            return $mail->hasTo($seller->email)
                && $mail->envelope()->subject === 'Nova poruka od korisnika Jovan Kupac na loglasi.rs'
                && str_contains($text, 'Zdravo, Petar Prodavac!')
                && str_contains($text, 'Traktor IMT 539')
                && str_contains($text, url_slash('moj-nalog/poruke/'.$mail->conversation->id));
        });

        // After the window a new message sends a new mail.
        $this->travel(16)->minutes();
        $messenger->start($buyer, $listing, 'Treća poruka');
        Mail::assertSent(NewMessageMail::class, 2);

        // Another conversation has its own window.
        $messenger->start(User::factory()->create(), $listing, 'Drugi kupac');
        Mail::assertSent(NewMessageMail::class, 3);
    }

    public function test_reply_mails_the_buyer(): void
    {
        Mail::fake();
        $listing = Listing::factory()->create();
        $buyer = User::factory()->create();
        $messenger = app(Messenger::class);
        $messenger->start($buyer, $listing, 'Pitanje');
        $messenger->reply($listing->user, Conversation::query()->sole(), 'Odgovor');

        Mail::assertSent(NewMessageMail::class, fn ($mail) => $mail->hasTo($buyer->email));
    }

    public function test_no_mail_when_notifications_are_off(): void
    {
        Mail::fake();
        $seller = User::factory()->create();
        $seller->forceFill(['notification_settings' => ['messages' => false]])->save();
        $listing = Listing::factory()->for($seller)->create();

        app(Messenger::class)->start(User::factory()->create(), $listing, 'Zdravo');

        Mail::assertNothingSent();
    }

    public function test_no_mail_when_read_before_the_job_runs(): void
    {
        Mail::fake();
        Queue::fake();
        $listing = Listing::factory()->create();
        app(Messenger::class)->start(User::factory()->create(), $listing, 'Zdravo');
        $conversation = Conversation::query()->sole();

        app(Messenger::class)->markRead($listing->user, $conversation);
        (new SendNewMessageMail($conversation->id, $listing->user_id))->handle();

        Mail::assertNothingSent();
    }

    public function test_mail_counts_unread_messages(): void
    {
        Mail::fake();
        Queue::fake();
        $listing = Listing::factory()->create();
        $buyer = User::factory()->create();
        app(Messenger::class)->start($buyer, $listing, 'Prva');
        app(Messenger::class)->start($buyer, $listing, 'Druga');

        (new SendNewMessageMail(Conversation::query()->sole()->id, $listing->user_id))->handle();

        Mail::assertSent(NewMessageMail::class, fn ($mail) => $mail->unread === 2 && str_contains($mail->render(), 'Imate 2 nove poruke') && str_contains($mail->render(), 'Druga'));
    }
}
