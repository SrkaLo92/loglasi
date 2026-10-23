<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Payment instructions for a new order (same data as the instructions page, with the IPS QR code). */
class OrderPlaced extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Uputstvo za uplatu: narudžbina '.$this->order->reference);
    }

    public function content(): Content
    {
        $this->order->loadMissing('product', 'listing', 'user');

        return new Content(
            html: 'payments.mail.order-placed',
            text: 'payments.mail.order-placed-text',
        );
    }
}
