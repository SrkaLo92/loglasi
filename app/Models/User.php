<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\Role;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'slug', 'account_type', 'phone', 'avatar_path',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'account_type' => AccountType::class,
            'role' => Role::class,
            'status' => UserStatus::class,
            'notification_settings' => 'array',
        ];
    }

    /** A name typed all in lowercase ("suncica perisic") is shown with capitals; the stored value stays. */
    protected function name(): Attribute
    {
        return Attribute::make(get: fn (?string $value) => $value !== null && $value !== '' && mb_strtolower($value) === $value && preg_match('/\p{L}/u', $value)
            ? mb_convert_case($value, MB_CASE_TITLE, 'UTF-8')
            : $value);
    }

    public function firm(): HasOne
    {
        return $this->hasOne(Firm::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'favorites')->withPivot('created_at');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isFirm(): bool
    {
        return $this->account_type === AccountType::Firm;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isStaff(): bool
    {
        return in_array($this->role, [Role::Admin, Role::Moderator], true);
    }

    public function isPhoneVerified(): bool
    {
        return $this->phone_verified_at !== null;
    }

    // Nalozi: tokeni, mesto, slika, obaveštenja

    /** Podrazumevana obaveštenja u aplikaciji (NotificationSettings iz ugovora). */
    public const NOTIFICATION_DEFAULTS = [
        'messages' => true,
        'listing_status' => true,
        'payments' => true,
        'expiring' => true,
        'admin_queue' => true,
    ];

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function isBlocked(): bool
    {
        return $this->status === UserStatus::Blocked;
    }

    /** Uloga za mobilnu aplikaciju: private, firm ili admin (admin i moderator). */
    public function apiRole(): string
    {
        return match (true) {
            $this->isStaff() => 'admin',
            $this->isFirm() => 'firm',
            default => 'private',
        };
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    /** Inicijali za krug bez slike: "Marko Petrović" → "MP". */
    public function initials(): string
    {
        $words = preg_split('/\s+/u', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
        $letters = array_map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)), array_slice($words, 0, 2));

        return implode('', $letters);
    }

    /** @return array<string, bool> */
    public function notificationSettings(): array
    {
        $settings = array_merge(self::NOTIFICATION_DEFAULTS, array_intersect_key((array) $this->notification_settings, self::NOTIFICATION_DEFAULTS));
        if (! $this->isStaff()) {
            unset($settings['admin_queue']);
        }

        return array_map(fn ($v) => (bool) $v, $settings);
    }

    // Admin panel: access, TOTP (two_factor_* columns), reports

    /** Only active staff (admin, moderator) can open the admin panel. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isStaff() && ! $this->isBlocked() && ! $this->trashed();
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->two_factor_secret;
    }

    public function saveAppAuthenticationSecret(#[\SensitiveParameter] ?string $secret): void
    {
        $this->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => $secret === null ? null : now(),
        ])->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /** @return ?array<string> */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->two_factor_recovery_codes;
    }

    /** @param  ?array<string>  $codes */
    public function saveAppAuthenticationRecoveryCodes(#[\SensitiveParameter] ?array $codes): void
    {
        $this->forceFill(['two_factor_recovery_codes' => $codes])->save();
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    // Notifications: saved searches, push

    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class);
    }

    /** FCM tokens for App\Notifications\Channels\PushChannel. */
    public function routeNotificationForPush(): array
    {
        return $this->devices()->pluck('token')->all();
    }

    // Listing page module

    /** Public profile: /korisnik/{slug}/ */
    public function profileUrl(): string
    {
        return url_slash('korisnik/'.$this->slug);
    }
}
