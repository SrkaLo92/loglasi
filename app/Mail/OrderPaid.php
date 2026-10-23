<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Confirmation that the payment arrived and the feature is active. */
class OrderPaid extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Uplata je primljena: '.$this->order->product?->name);
    }

    public function content(): Content
    {
        $this->order->loadMissing('product', 'listing', 'user');

        return new Content(text: 'payments.mail.order-paid');
    }
}
