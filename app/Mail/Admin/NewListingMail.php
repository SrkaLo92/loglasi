<?php

namespace App\Mail\Admin;

use App\Enums\ListingStatus;
use App\Filament\Resources\Listings\ListingResource;
use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells an admin that a new listing arrived (plain text, queued; StaffAlerts). */
class NewListingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Listing $listing)
    {
        $this->afterCommit();
    }

    private function pending(): bool
    {
        return $this->listing->status === ListingStatus::Pending;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->pending() ? 'Novi oglas čeka odobrenje: ' : 'Novi oglas: ').mb_strimwidth((string) $this->listing->title, 0, 80, '…'));
    }

    public function content(): Content
    {
        $listing = $this->listing->loadMissing('user', 'category', 'location');

        return new Content(text: 'admin.mail.new-listing', with: [
            'pending' => $this->pending(),
            'title' => $listing->title,
            'number' => $listing->id,
            'category' => $listing->category?->name,
            'location' => $listing->location?->name,
            'owner' => $listing->user ? $listing->user->name.' ('.$listing->user->email.')' : '—',
            'adminUrl' => ListingResource::getUrl('edit', ['record' => $listing], panel: 'admin'),
            'url' => $this->pending() ? null : $listing->url(),
        ]);
    }
}
