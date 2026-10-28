<?php

namespace App\Services\Admin;

use App\Enums\AccountType;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\Product;
use App\Models\User;
use App\Services\Payments\Effects;
use App\Services\Payments\ProductCatalog;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Account actions for the admin panel and the admin API. Permissions are checked by the caller (UserPolicy). */
class UserAdministration
{
    public function verifyPhone(User $user): User
    {
        if (blank($user->phone)) {
            throw new DomainException('Korisnik nema unet broj telefona.');
        }

        if ($user->phone_verified_at === null) {
            $user->forceFill(['phone_verified_at' => now()])->save();
            AuditLog::record('user.verify_phone', $user, ['phone' => $user->phone]);
        }

        return $user;
    }

    public function unverifyPhone(User $user): User
    {
        $user->forceFill(['phone_verified_at' => null])->save();
        AuditLog::record('user.unverify_phone', $user, ['phone' => $user->phone]);

        return $user;
    }

    /** Blocks the account and signs it out of the app (tokens are revoked). */
    public function block(User $user): User
    {
        DB::transaction(function () use ($user) {
            $user->forceFill(['status' => UserStatus::Blocked])->save();
            $user->tokens()->delete();
            $user->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        });

        AuditLog::record('user.block', $user);

        return $user;
    }

    public function unblock(User $user): User
    {
        $user->forceFill(['status' => UserStatus::Active])->save();
        AuditLog::record('user.unblock', $user);

        return $user;
    }

    /** Firm account: creates the firms row when it does not exist yet. */
    public function makeFirm(User $user, ?string $firmName = null): Firm
    {
        return DB::transaction(function () use ($user, $firmName) {
            $firm = $user->firm;

            if ($firm === null) {
                $firm = new Firm(['name' => trim((string) $firmName) !== '' ? trim($firmName) : $user->name]);
                $firm->user()->associate($user);
                $firm->save();
                $user->setRelation('firm', $firm);
            }

            $user->forceFill(['account_type' => AccountType::Firm])->save();
            AuditLog::record('user.make_firm', $user, ['firm_id' => $firm->id]);

            return $firm;
        });
    }

    /** Back to a private account. The firm profile stays saved (hidden while the account is private). */
    public function makePrivate(User $user): User
    {
        $user->forceFill(['account_type' => AccountType::Private])->save();
        AuditLog::record('user.make_private', $user);

        return $user;
    }

    public function changeRole(User $user, Role $role): User
    {
        $before = $user->role;

        if ($before === Role::Admin && $role !== Role::Admin && User::query()->where('role', Role::Admin)->count() <= 1) {
            throw new DomainException('Sajt mora da ima bar jednog administratora.');
        }

        $user->forceFill(['role' => $role])->save();
        AuditLog::record('user.change_role', $user, ['role' => [$before?->value, $role->value]]);

        return $user;
    }

    /** Products an admin can give a private account for free: extra listings and Plus prodavac, as on the price list. */
    public const GIFT_TYPES = [ProductCatalog::SLOTS, ProductCatalog::PLUS_SELLER];

    /** @return array<string, string> code => name, active products in price list order */
    public static function giftOptions(): array
    {
        return Product::query()->where('is_active', true)->orderBy('sort')->get()
            ->filter(fn (Product $p) => in_array(ProductCatalog::type($p), self::GIFT_TYPES, true))
            ->mapWithKeys(fn (Product $p) => [$p->code => $p->name])
            ->all();
    }

    /**
     * Gives a package for free, with the same effect as a paid order (no order, no invoice, no receipt).
     * Firm packages are given on the firm page.
     */
    public function giftPackage(User $user, string $code): array
    {
        if ($user->isFirm()) {
            throw new DomainException('Firmi se paket dodeljuje na strani firme („Dodeli paket”).');
        }
        $product = Product::query()->where('code', $code)->where('is_active', true)->first();
        if ($product === null || ! in_array(ProductCatalog::type($product), self::GIFT_TYPES, true)) {
            throw new DomainException('Ovaj paket ne može da se dodeli.');
        }

        $applied = DB::transaction(fn () => Effects::apply($product, $user, null));
        AuditLog::record('user.gift_package', $user, ['product' => $code, 'effect' => $applied['effect']]);

        return $applied;
    }

    /** Personal active-listing limit; null returns the account to the normal limit. */
    public function setListingLimit(User $user, ?int $limit): void
    {
        $old = $user->listing_limit;
        $user->forceFill(['listing_limit' => $limit])->save();
        AuditLog::record('user.listing_limit', $user, ['old' => $old, 'new' => $limit]);
    }

    /** Running packages given by an admin (Plus prodavac terms and extra listings without an order). */
    public static function runningGifts(User $user): array
    {
        $now = now();

        return [
            'plus_until' => ($ends = DB::table('plus_seller_terms')->where('user_id', $user->getKey())->whereNull('order_id')->where('ends_at', '>', $now)->max('ends_at')) ? Carbon::parse($ends) : null,
            'slots' => (int) DB::table('listing_slot_grants')->where('user_id', $user->getKey())->whereNull('order_id')->where('starts_at', '<=', $now)->where('ends_at', '>', $now)->sum('slots'),
        ];
    }

    /** Ends the packages an admin gave (paid ones stay). */
    public function endGifts(User $user): void
    {
        $now = now();
        DB::transaction(function () use ($user, $now) {
            DB::table('plus_seller_terms')->where('user_id', $user->getKey())->whereNull('order_id')->where('ends_at', '>', $now)
                ->update(['ends_at' => DB::raw('GREATEST(starts_at, '.DB::getPdo()->quote($now->toDateTimeString()).')'), 'updated_at' => $now]);
            DB::table('listing_slot_grants')->where('user_id', $user->getKey())->whereNull('order_id')->where('ends_at', '>', $now)
                ->update(['ends_at' => DB::raw('GREATEST(starts_at, '.DB::getPdo()->quote($now->toDateTimeString()).')'), 'updated_at' => $now]);
        });
        AuditLog::record('user.end_gifts', $user);
    }
}
