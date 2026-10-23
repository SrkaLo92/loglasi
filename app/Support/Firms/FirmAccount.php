<?php

namespace App\Support\Firms;

use App\Enums\OrderStatus;
use App\Models\Firm;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\FirmBenefits;
use App\Support\ListingLimits;
use Carbon\CarbonInterface;

/** The firm profile of a firm account and its Biznis status box (web and API). */
class FirmAccount
{
    /** Saved firm row, or an unsaved one named after the account (the admin may not have filled it yet). */
    public static function firmFor(User $user): Firm
    {
        $firm = $user->firm;
        if ($firm === null) {
            $firm = new Firm(['name' => $user->name]);
            $firm->forceFill(['user_id' => $user->id]);
            $firm->setRelation('user', $user);
        }

        return $firm;
    }

    /** Creates the firm row on first save. */
    public static function ensureSaved(Firm $firm): Firm
    {
        if (! $firm->exists) {
            $firm->save();
        }

        return $firm;
    }

    /**
     * @return array{state: string, until: ?CarbonInterface, days_left: ?int, founding: bool, verified: bool, pending_order: ?Order, price_rsd: ?int, renew_url: string, limit: array, benefits: list<string>}
     */
    public static function status(User $user, Firm $firm): array
    {
        $benefits = app(FirmBenefits::class);
        $has = $benefits->hasBiznis($firm);
        $daysLeft = $benefits->daysLeft($firm);

        $state = match (true) {
            $has && $daysLeft !== null && $daysLeft <= (int) max(config('firms.reminder_days', [14])) => 'expiring',
            $has => 'active',
            $firm->biznis_until !== null => 'expired',
            default => 'none',
        };

        $product = Product::query()->where('code', Product::BIZNIS_12M)->first(['id', 'price_rsd', 'is_active']);
        $pending = $product === null ? null : Order::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('status', OrderStatus::Pending)
            ->latest('id')
            ->first();

        return [
            'state' => $state,
            'until' => $firm->biznis_until,
            'days_left' => $daysLeft,
            'founding' => (bool) $firm->founding_free,
            'verified' => $firm->verified_at !== null,
            'pending_order' => $pending,
            'price_rsd' => $product?->is_active ? (int) $product->price_rsd : null,
            'renew_url' => url_slash(trim((string) config('firms.renew_url', '/cenovnik/'), '/')),
            'limit' => ListingLimits::for($user),
            'benefits' => FirmBenefits::benefitLabels(),
        ];
    }
}
