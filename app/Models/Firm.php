<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Firm extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'user_id', 'biznis_until', 'founding_free', 'verified_at'];

    protected function casts(): array
    {
        return [
            'working_hours' => 'array',
            'biznis_until' => 'datetime',
            'founding_free' => 'boolean',
            'verified_at' => 'datetime',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function hasBiznis(): bool
    {
        return $this->biznis_until !== null && $this->biznis_until->isFuture();
    }

    // Listing page module: images and contact preferences

    public function logoUrl(): ?string
    {
        return ListingImage::urlFor($this->logo_path);
    }

    public function coverUrl(): ?string
    {
        return ListingImage::urlFor($this->cover_path);
    }

    /** WhatsApp/Viber button: explicit choice, or automatic for mobile numbers. */
    public function messengerEnabled(string $app, string $e164): bool
    {
        $value = $this->getAttribute($app);
        if ($value !== null) {
            return (bool) $value;
        }

        return str_starts_with($e164, '+3816');
    }
}
