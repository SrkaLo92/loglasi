<?php

namespace App\WordPressImport\Steps;

/**
 * Orders are not carried over: Listivo/WooCommerce packages have no reliable link to the
 * products of the new schema. They are only counted here for the report.
 */
class OrderImport extends Step
{
    public function run(): void
    {
        $posts = $this->wp->table('posts')->where('post_type', 'shop_order')->count();
        $hpos = $this->wp->hasTable('wc_orders') ? $this->wp->table('wc_orders')->count() : 0;
        $total = max($posts, $hpos);

        if ($total === 0) {
            return;
        }

        $this->report->wp('orders', $total);
        $this->report->add('orders', 'skipped', $total);
        $this->report->note('orders', "WooCommerce narudžbine: {$posts} u wp_posts, {$hpos} u wc_orders. Nisu prenete; Biznis paket i istaknute oglase proveriti ručno.");
    }
}
