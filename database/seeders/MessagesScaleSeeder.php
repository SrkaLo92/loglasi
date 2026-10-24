<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Performance data for the messages module (local only, never in production):
 * 5,000 users, 200,000 listings, 60,000 conversations, 400,000 messages, 200,000 favourites.
 * User 1 is a heavy seller (2,000 conversations, 1,000 favourites) for worst-case EXPLAIN.
 *
 *   DB_DATABASE=lb_messages php artisan db:seed --class=MessagesScaleSeeder
 */
class MessagesScaleSeeder extends Seeder
{
    private const USERS = 5000;

    private const LISTINGS = 200000;

    private const CONVERSATIONS = 60000;

    private const MESSAGES_PER_CONVERSATION = 7; // about 400k in total, varied below

    private const FAVORITES = 200000;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('MessagesScaleSeeder must not run in production.');
        }

        mt_srand(42);
        DB::disableQueryLog();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['favorites', 'messages', 'conversations', 'listing_daily_stats', 'listing_images', 'listings', 'users', 'categories', 'locations'] as $table) {
            DB::table($table)->truncate();
        }

        $now = now();
        $this->bulk('categories', 20, fn ($i) => ['id' => $i, 'name' => 'Kategorija '.$i, 'slug' => 'kategorija-'.$i, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->bulk('locations', 19, fn ($i) => ['id' => $i, 'name' => 'Mesto '.$i, 'slug' => 'mesto-'.$i, 'created_at' => $now, 'updated_at' => $now]);

        $password = bcrypt('lozinka123');
        $this->bulk('users', self::USERS, fn ($i) => [
            'id' => $i, 'name' => 'Korisnik '.$i, 'email' => "korisnik{$i}@example.test", 'email_verified_at' => $now,
            'password' => $password, 'slug' => 'korisnik-'.$i, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->bulk('listings', self::LISTINGS, fn ($i) => [
            'id' => $i, 'user_id' => $i <= 3000 ? 1 : mt_rand(2, self::USERS), 'category_id' => mt_rand(1, 20), 'location_id' => mt_rand(1, 19),
            'title' => 'Oglas '.$i, 'slug' => 'oglas-'.$i, 'price' => mt_rand(10, 20000), 'price_eur' => mt_rand(10, 20000), 'currency' => 'EUR',
            'status' => mt_rand(1, 10) === 1 ? 'expired' : 'published', 'published_at' => $now, 'bumped_at' => $now,
            'expires_at' => $now->copy()->addDays(30), 'created_at' => $now, 'updated_at' => $now,
        ]);

        // Conversations: the first 2,000 are on user 1's listings; the rest random.
        $start = $now->copy()->subDays(180);
        $seen = [];
        $this->bulk('conversations', self::CONVERSATIONS, function ($i) use ($start, &$seen) {
            do {
                $listing = $i <= 2000 ? mt_rand(1, 3000) : mt_rand(3001, self::LISTINGS);
                $buyer = mt_rand(2, self::USERS);
            } while (isset($seen[$listing.'-'.$buyer]));
            $seen[$listing.'-'.$buyer] = true;
            $at = $start->copy()->addSeconds(mt_rand(0, 180 * 86400));

            return [
                'id' => $i, 'listing_id' => $listing, 'buyer_id' => $buyer, 'seller_id' => $listing <= 3000 ? 1 : 0,
                'last_message_at' => $at, 'buyer_read_at' => $at, 'seller_read_at' => $at,
                'buyer_unread' => mt_rand(0, 3) === 0 ? mt_rand(1, 3) : 0, 'seller_unread' => mt_rand(0, 3) === 0 ? mt_rand(1, 3) : 0,
                'created_at' => $at, 'updated_at' => $at,
            ];
        });
        DB::statement('UPDATE conversations c JOIN listings l ON l.id = c.listing_id SET c.seller_id = l.user_id WHERE c.seller_id = 0');

        // Messages: 1 to 13 per conversation, alternating sides.
        $rows = [];
        $id = 0;
        DB::table('conversations')->select('id', 'buyer_id', 'seller_id', 'last_message_at')->orderBy('id')->chunk(5000, function ($conversations) use (&$rows, &$id) {
            foreach ($conversations as $c) {
                $count = mt_rand(1, self::MESSAGES_PER_CONVERSATION * 2 - 1);
                $at = strtotime($c->last_message_at) - $count * 600;
                for ($k = 0; $k < $count; $k++) {
                    $at += 600;
                    $rows[] = ['id' => ++$id, 'conversation_id' => $c->id, 'sender_id' => $k % 2 ? $c->seller_id : $c->buyer_id,
                        'body' => 'Poruka broj '.$id.' u razgovoru', 'created_at' => date('Y-m-d H:i:s', $at), 'updated_at' => date('Y-m-d H:i:s', $at)];
                    if (count($rows) === 5000) {
                        DB::table('messages')->insert($rows);
                        $rows = [];
                    }
                }
            }
        });
        if ($rows) {
            DB::table('messages')->insert($rows);
        }

        // Favourites: user 1 has 1,000; the rest random, unique pairs.
        $seen = [];
        $this->bulk('favorites', self::FAVORITES, function ($i) use ($start, &$seen) {
            do {
                $user = $i <= 1000 ? 1 : mt_rand(2, self::USERS);
                $listing = mt_rand(1, self::LISTINGS);
            } while (isset($seen[$user.'-'.$listing]));
            $seen[$user.'-'.$listing] = true;

            return ['user_id' => $user, 'listing_id' => $listing, 'created_at' => $start->copy()->addSeconds(mt_rand(0, 180 * 86400))];
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        foreach (['users', 'listings', 'conversations', 'messages', 'favorites'] as $table) {
            DB::statement('ANALYZE TABLE '.$table);
        }
    }

    private function bulk(string $table, int $count, callable $row, int $chunk = 2000): void
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = $row($i);
            if (count($rows) === $chunk) {
                DB::table($table)->insert($rows);
                $rows = [];
            }
        }
        if ($rows) {
            DB::table($table)->insert($rows);
        }
    }
}
