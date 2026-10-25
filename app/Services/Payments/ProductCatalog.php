<?php

namespace App\Services\Payments;

use App\Models\Product;

/** What a sellable product does (config('payments.catalog')). Unknown codes are not sold. */
final class ProductCatalog
{
    public const BUMP = 'bump';

    public const BUMP_PACK = 'bump_pack';

    public const PROMOTION = 'promotion';

    public const SLOTS = 'slots';

    public const FIRM_PACKAGE = 'firm_package';

    public const PLUS_SELLER = 'plus_seller';

    /** @return array<string, mixed>|null */
    public static function definition(Product|string $product): ?array
    {
        $code = $product instanceof Product ? $product->code : $product;
        $definition = config('payments.catalog.'.$code);

        return is_array($definition) ? $definition : null;
    }

    public static function isSellable(Product|string $product): bool
    {
        return self::definition($product) !== null;
    }

    public static function type(Product|string $product): ?string
    {
        return self::definition($product)['type'] ?? null;
    }

    /** Bump and promotions are bought for one listing. */
    public static function needsListing(Product|string $product): bool
    {
        return in_array(self::type($product), [self::BUMP, self::PROMOTION], true);
    }

    /** Monthly firm credit type (FirmCredits) that covers this product, or null. */
    public static function credit(Product|string $product): ?string
    {
        return self::definition($product)['credit'] ?? null;
    }

    /** Days the product lasts: products.duration_days (admin), else the catalog default. */
    public static function days(Product $product): int
    {
        return (int) ($product->duration_days ?: (self::definition($product)['days'] ?? 0));
    }

    /** Extra listing slots: products.listing_limit (admin), else the catalog default. */
    public static function slots(Product $product): int
    {
        return (int) ($product->listing_limit ?: (self::definition($product)['slots'] ?? 0));
    }

    /** @return list<string> codes of a type */
    public static function codesOfType(string $type): array
    {
        return array_keys(array_filter((array) config('payments.catalog'), fn ($d) => ($d['type'] ?? null) === $type));
    }
}
