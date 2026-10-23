<?php

namespace App\Mail;

use App\Models\User;
use App\Notifications\Messages\ListUnsubscribe;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Base for mailables sent to a user: queued, branded layout, plain-text part,
 * List-Unsubscribe when the mail belongs to a notification category.
 *
 *   class ListingExpiresSoon extends BrandedMailable
 *   {
 *       protected ?string $category = NotificationPreferences::EXPIRING;
 *       public function __construct(User $user, public Listing $listing) { parent::__construct($user); }
 *       public function envelope(): Envelope { return new Envelope(subject: '...'); }
 *       public function content(): Content { return $this->markdownContent('listings.mail.expires'); }
 *   }
 *
 * The view starts with <x-mail::message :unsubscribe="$unsubscribeUrl">.
 */
abstract class BrandedMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** NotificationPreferences category, null for account mail that cannot be turned off. */
    protected ?string $category = null;

    public function __construct(public User $user)
    {
        $this->to($user->email, $user->name);
    }

    public function unsubscribeUrl(): ?string
    {
        return $this->category === null ? null : NotificationPreferences::unsubscribeUrl($this->user, $this->category);
    }

    public function headers(): Headers
    {
        $url = $this->unsubscribeUrl();

        return new Headers(text: $url === null ? [] : ListUnsubscribe::headers($url));
    }

    /** @param  array<string, mixed>  $with */
    protected function markdownContent(string $view, array $with = []): Content
    {
        return new Content(markdown: $view, with: $with + ['unsubscribeUrl' => $this->unsubscribeUrl()]);
    }
}
