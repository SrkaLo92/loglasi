<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'buyer_read_at' => 'datetime',
            'seller_read_at' => 'datetime',
            'buyer_unread' => 'integer',
            'seller_unread' => 'integer',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function involves(User $user): bool
    {
        return $user->id === $this->buyer_id || $user->id === $this->seller_id;
    }

    // Messages module: last message, sides, unread counters, read receipts

    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function isBuyer(User $user): bool
    {
        return $user->id === $this->buyer_id;
    }

    /** The other participant's id, seen from $user. */
    public function otherId(User $user): int
    {
        return $this->isBuyer($user) ? (int) $this->seller_id : (int) $this->buyer_id;
    }

    /** The other participant (buyer or seller relation must be loaded for lists). */
    public function otherUser(User $user): ?User
    {
        return $this->isBuyer($user) ? $this->seller : $this->buyer;
    }

    public function unreadFor(User $user): int
    {
        return (int) ($this->isBuyer($user) ? $this->buyer_unread : $this->seller_unread);
    }

    /** When $user last read the conversation. */
    public function readAtFor(User $user): ?CarbonInterface
    {
        return $this->isBuyer($user) ? $this->buyer_read_at : $this->seller_read_at;
    }

    /** When the other participant last read the conversation (read receipts for $user's messages). */
    public function otherReadAt(User $user): ?CarbonInterface
    {
        return $this->isBuyer($user) ? $this->seller_read_at : $this->buyer_read_at;
    }
}
