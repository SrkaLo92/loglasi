<?php

namespace App\Uvoz\Steps;

/**
 * Narudžbine se ne prenose: Listivo/WooCommerce paketi nemaju pouzdanu vezu sa
 * proizvodima nove šeme. Ovde se samo prebrojavaju za izveštaj.
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
