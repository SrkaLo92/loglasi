<?php

namespace App\Models;

use App\Support\Firms\FirmPackage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Firm extends Model
{
    use HasFactory;

    // Package, free year and verification are set only by the admin or the payments module.
    protected $guarded = [
        'id', 'user_id', 'founding_free', 'verified_at', 'package', 'package_period', 'package_starts_at',
        'package_ends_at', 'free_program', 'package_reminder_days', 'package_reminder_for',
    ];

    protected function casts(): array
    {
        return [
            'working_hours' => 'array',
            'founding_free' => 'boolean',
            'package_starts_at' => 'datetime',
            'package_ends_at' => 'datetime',
            'package_reminder_for' => 'datetime',
            'free_program' => 'boolean',
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

    /** Active Business package or free first year (App\Support\Firms\FirmPackage). */
    public function hasPackage(): bool
    {
        return FirmPackage::isActive($this);
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
