<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\Role;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

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
        ];
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
}
