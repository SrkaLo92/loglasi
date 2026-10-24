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

/** The firm profile of a firm account and its package status box. */
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
     * Package status box: state (active | expiring | expired | none), plan, term end, credits left, limits.
     *
     * @return array{state: string, code: ?string, label: ?string, period: ?string, free_program: bool, until: ?CarbonInterface, days_left: ?int, verified: bool, credits: array, pending_order: ?Order, packages: list<array{code: string, label: string, monthly: ?int, yearly: ?int}>, renew_url: string, limit: array, benefits: list<string>}
     */
    public static function status(User $user, Firm $firm): array
    {
        $benefits = app(FirmBenefits::class);
        $has = $benefits->hasPackage($firm);
        $daysLeft = $benefits->daysLeft($firm);
        $code = $benefits->packageCode($firm);
        $soon = $code === null ? 0 : (int) max((array) config($firm->package_period === 'monthly' ? 'firms.reminder_days_monthly' : 'firms.reminder_days', [14]));

        $state = match (true) {
            $has && $daysLeft !== null && $daysLeft <= $soon => 'expiring',
            $has => 'active',
            $firm->package_ends_at !== null => 'expired',
            default => 'none',
        };

        $codes = [];
        foreach (FirmPackage::PACKAGES as $package) {
            foreach ((array) config('firms.packages.'.$package.'.products') as $product) {
                $codes[] = $product;
            }
        }
        $products = Product::query()->whereIn('code', $codes)->get(['id', 'code', 'price_rsd', 'is_active', 'display_only'])->keyBy('code');
        $packages = [];
        foreach (FirmPackage::PACKAGES as $package) {
            $definition = config('firms.packages.'.$package);
            $price = fn (string $period) => ($p = $products->get($definition['products'][$period] ?? '')) && ($p->is_active || $p->display_only) ? (int) $p->price_rsd : null;
            $packages[] = ['code' => $package, 'label' => $definition['label'], 'monthly' => $price('monthly'), 'yearly' => $price('yearly')];
        }

        $pending = Order::query()
            ->where('user_id', $user->id)
            ->where('status', OrderStatus::Pending)
            ->whereIn('product_id', $products->pluck('id'))
            ->latest('id')
            ->first();

        return [
            'state' => $state,
            'code' => $code,
            'label' => $benefits->packageLabel($firm) ?? (FirmPackage::definition($firm->onFreeYear() ? FirmPackage::FREE : $firm->package)['label'] ?? null),
            'period' => $firm->onFreeYear() ? null : $firm->package_period,
            'free_program' => $firm->onFreeYear(),
            'until' => $firm->package_ends_at,
            'days_left' => $daysLeft,
            'verified' => $firm->verified_at !== null,
            'credits' => $has ? FirmCredits::remaining(FirmAccount::ensureSaved($firm)) : null,
            'credits_granted' => FirmPackage::monthlyCredits($firm),
            'pending_order' => $pending,
            'packages' => $packages,
            'renew_url' => url_slash(trim((string) parse_url((string) config('firms.renew_url', '/cenovnik/'), PHP_URL_PATH), '/')).(($f = parse_url((string) config('firms.renew_url'), PHP_URL_FRAGMENT)) ? '#'.$f : ''),
            'limit' => ListingLimits::for($user),
            'benefits' => FirmBenefits::benefitLabels(),
        ];
    }
}
