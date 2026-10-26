<?php

namespace App\Http\Api;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;

/** Contract shapes for the catalog: Category, Location, Price. Other API modules use them too. */
class CatalogFormatter
{
    /** Product code in the database => `product` from the contract. Products outside the list are not returned by the API. */
    public const PRODUCT_CODES = [
        Product::BUMP => 'bump',
        Product::FEATURED_7 => 'featured_7d',
        Product::FEATURED_30 => 'featured_30d',
        Product::BIZNIS_12M => 'business_year',
    ];

    /** Short category shape (listing card): id, name, slug. */
    public static function categoryRef(Category $category): array
    {
        return [
            'id' => (int) $category->id,
            'name' => html_entity_decode($category->name, ENT_QUOTES, 'UTF-8'),
            'slug' => $category->slug,
        ];
    }

    /**
     * Tree node from Catalog::tree().
     *
     * @param  array{category: Category, count: int, children: array}  $node
     */
    public static function categoryNode(array $node): array
    {
        return self::categoryRef($node['category']) + [
            'icon' => $node['category']->iconUrl(),
            'count' => (int) $node['count'],
            'children' => array_map([self::class, 'categoryNode'], $node['children']),
        ];
    }

    public static function locationRef(Location $location): array
    {
        return [
            'id' => (int) $location->id,
            'name' => html_entity_decode($location->name, ENT_QUOTES, 'UTF-8'),
            'slug' => $location->slug,
        ];
    }

    public static function productCode(Product|string $product): ?string
    {
        return self::PRODUCT_CODES[$product instanceof Product ? $product->code : $product] ?? null;
    }

    /** Price from the contract, or null when the product has no code in the contract. */
    public static function price(Product $product): ?array
    {
        $code = self::productCode($product);

        return $code === null ? null : [
            'product' => $code,
            'label' => $product->name,
            'amount' => (int) $product->price_rsd,
        ];
    }
}
