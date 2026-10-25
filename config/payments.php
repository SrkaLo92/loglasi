<?php

// Paid features of the price list of 24 Oct 2026, paid by bank transfer (IPS QR). See docs/modules/payments.md.
// Prices live in `products` (admin). Payee data and switches are admin settings (payment_*, payments.*), never code.

return [
    // Pending orders older than this are cancelled by `orders:cancel-stale` (daily).
    'pending_days' => 7,

    // TEST MODE, on until the fiscal register works: the whole flow runs (order, pro-forma,
    // IPS QR with purpose "TEST ...", mark paid, activation, refund), receipts are fake
    // (TEST-0001) and e-mails are marked TEST. Admin setting "payments.test_mode" overrides it.
    'test_mode' => true,

    // Real payments by private persons need fiscal receipts: off until the register works.
    // Admin setting "payments.private_payments_enabled" overrides it. Test mode does not turn it on.
    'private_payments_enabled' => false,

    // A listing can be bumped at most once in this many hours.
    'bump_cooldown_hours' => 24,

    // Maximum number of unpaid orders per user (protects against spam).
    'max_pending_per_user' => 10,

    // Model for "poziv na broj": control digits (ISO 7064 MOD 97-10) + the order ID.
    'reference_model' => '97',

    // The order ID in a reference is padded with zeros to this many digits.
    'reference_digits' => 6,

    // NBS IPS QR: payment code (SF). 221 = trade in goods and services, final consumption.
    // Admin setting payment_code overrides it.
    'ips_payment_code' => '221',

    // Premium XL: at most one bigger card per this many listings in a list (price list: 1 per 8–12).
    'premium_xl_every' => 10,

    // Smallest order (price list of 25 Oct 2026); cheaper products are used from credits or packs. Admin setting payments.min_order_rsd overrides it.
    'min_order_rsd' => 149,

    /*
     * What each sellable product does (codes from `products`; days from products.duration_days,
     * slots from products.listing_limit). type:
     * - bump: listing bumped_at = now (once per 24 h)
     * - bump_pack: N bumps to use later, one at a time
     * - promotion: listing columns extended from max(now, current) by the product days
     * - slots: extra active listings on top of private_active_limit for the product days
     * - firm_package: FirmPackage::grant(package, period)
     * - plus_seller: Plus prodavac for the product days (PlusSeller), with bumps per started 30 days
     * A promotion with 'bumps' also grants that many bumps to use later (Brza prodaja).
     * Retired products (featured_30, listing_slots_30) stay here for their old orders; their rows are inactive.
     * credit: the firm's monthly included credit (FirmCredits) used instead of buying, when one is left.
     */
    'catalog' => [
        'bump' => ['type' => 'bump', 'credit' => 'bumps'],
        'bump_10' => ['type' => 'bump_pack', 'bumps' => 10],
        'featured_7' => ['type' => 'promotion', 'columns' => ['featured_until'], 'days' => 7, 'credit' => 'highlights'],
        'featured_30' => ['type' => 'promotion', 'columns' => ['featured_until'], 'days' => 30],
        'quick_sale_7' => ['type' => 'promotion', 'columns' => ['featured_until'], 'days' => 7, 'bumps' => 3],
        'priority_3' => ['type' => 'promotion', 'columns' => ['priority_until'], 'days' => 3],
        'top_7' => ['type' => 'promotion', 'columns' => ['top_until'], 'days' => 7],
        'premium_xl_7' => ['type' => 'promotion', 'columns' => ['premium_xl_until'], 'days' => 7, 'credit' => 'premium_xl'],
        'premium_xl_home_7' => ['type' => 'promotion', 'columns' => ['premium_xl_until', 'premium_home_until'], 'days' => 7],
        'listing_slots_3' => ['type' => 'slots', 'slots' => 3, 'days' => 30],
        'listing_slots_10' => ['type' => 'slots', 'slots' => 10, 'days' => 30],
        'listing_slots_30' => ['type' => 'slots', 'slots' => 30, 'days' => 30],
        'plus_seller_30' => ['type' => 'plus_seller', 'days' => 30, 'bumps_per_month' => 2],
        'plus_seller_90' => ['type' => 'plus_seller', 'days' => 90, 'bumps_per_month' => 2],
        'plus_seller_365' => ['type' => 'plus_seller', 'days' => 365, 'bumps_per_month' => 2],
        'business_vizitka_monthly' => ['type' => 'firm_package', 'package' => 'vizitka', 'period' => 'monthly'],
        'business_vizitka_yearly' => ['type' => 'firm_package', 'package' => 'vizitka', 'period' => 'yearly'],
        'business_start_monthly' => ['type' => 'firm_package', 'package' => 'start', 'period' => 'monthly'],
        'business_start_yearly' => ['type' => 'firm_package', 'package' => 'start', 'period' => 'yearly'],
        'business_pro_monthly' => ['type' => 'firm_package', 'package' => 'pro', 'period' => 'monthly'],
        'business_pro_yearly' => ['type' => 'firm_package', 'package' => 'pro', 'period' => 'yearly'],
        'business_max_monthly' => ['type' => 'firm_package', 'package' => 'max', 'period' => 'monthly'],
        'business_max_yearly' => ['type' => 'firm_package', 'package' => 'max', 'period' => 'yearly'],
    ],
];
