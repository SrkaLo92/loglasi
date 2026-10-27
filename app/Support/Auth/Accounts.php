<?php

namespace App\Support\Auth;

use App\Enums\AccountType;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Rules\PhoneNumber;
use App\Rules\PublicName;
use App\Support\Phone;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Registration and login checks, the same for the site and the mobile app. */
class Accounts
{
    public const PASSWORD_MIN = 8;

    public const PASSWORD_MAX = 200;

    public static function normalizeEmail(mixed $email): string
    {
        return Str::lower(trim((string) $email));
    }

    /** @return array<string, array<int, mixed>> */
    public static function registrationRules(bool $phoneRequired): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80', new PublicName],
            'email' => ['required', 'string', 'max:191', 'email:rfc'],
            'phone' => [$phoneRequired ? 'required' : 'nullable', 'string', 'max:40', new PhoneNumber],
            'password' => ['required', 'string', 'min:'.self::PASSWORD_MIN, 'max:'.self::PASSWORD_MAX],
            'accept_terms' => ['accepted'],
        ];
    }

    public static function emailTaken(string $email): bool
    {
        return User::withTrashed()->where('email', self::normalizeEmail($email))->exists();
    }

    /**
     * New private account. Only an administrator sets the firm type and roles.
     *
     * @param  array{name:string, email:string, phone?:?string, password:string}  $data
     */
    public static function register(array $data): User
    {
        $user = DB::transaction(function () use ($data) {
            $name = trim(preg_replace('/\s+/u', ' ', strip_tags($data['name'])));
            $phone = Phone::normalize($data['phone'] ?? null);

            $user = new User;
            $user->forceFill([
                'name' => $name,
                'email' => self::normalizeEmail($data['email']),
                'password' => Hash::make($data['password']),
                'slug' => self::uniqueSlug($name),
                'account_type' => AccountType::Private,
                'role' => Role::User,
                'status' => UserStatus::Active,
                'phone' => $phone !== '' ? $phone : null,
            ])->save();

            return $user;
        });

        event(new Registered($user));

        return $user;
    }

    /**
     * Checks the email and password. An old hash (WordPress, bcrypt) is changed to argon2id at once.
     *
     * @return array{0: ?User, 1: ?string} the user, or an error code (invalid_credentials, account_blocked)
     */
    public static function attempt(string $email, string $password): array
    {
        $user = User::where('email', self::normalizeEmail($email))->first();

        if ($user === null) {
            // Same response time when the account does not exist.
            Hash::check($password, self::dummyHash());

            return [null, 'invalid_credentials'];
        }

        if (! Hash::check($password, $user->getAuthPassword())) {
            return [null, 'invalid_credentials'];
        }

        if ($user->isBlocked()) {
            return [null, 'account_blocked'];
        }

        if (Hash::needsRehash($user->getAuthPassword())) {
            $user->forceFill(['password' => Hash::make($password)]);
        }
        $user->forceFill(['last_login_at' => now()])->save();

        return [$user, null];
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name, '-', 'sr') ?: 'korisnik'; // đ → dj, like WordPress for sr_RS
        $base = Str::limit($base, 60, '');
        $slug = $base;
        $i = 2;

        while (User::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private static function dummyHash(): string
    {
        static $hash;

        return $hash ??= Hash::make(Str::random(16));
    }
}
