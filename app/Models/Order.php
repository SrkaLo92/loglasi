<?php

namespace App\Models;

use App\Enums\BuyerType;
use App\Enums\OrderStatus;
use App\Enums\ReceiptType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'wp_id'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'paid_at' => 'datetime',
            // Payments module
            'buyer_type' => BuyerType::class,
            'receipt_type' => ReceiptType::class,
            'terms_accepted_at' => 'datetime',
            'consent_at' => 'datetime',
            'is_test' => 'boolean',
            'activated_at' => 'datetime',
            'service_starts_at' => 'datetime',
            'service_ends_at' => 'datetime',
            'receipt_issued_at' => 'datetime',
            'advance_receipt_issued_at' => 'datetime',
            'refunded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    // Payments: who marked it paid or cancelled it, status helpers

    public function markedPaidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_paid_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function isPending(): bool
    {
        return $this->status === OrderStatus::Pending;
    }
}
