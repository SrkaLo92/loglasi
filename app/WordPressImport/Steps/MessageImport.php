<?php

namespace App\WordPressImport\Steps;

use Illuminate\Support\Facades\DB;

/**
 * Messages from the Listivo table (config wordpress_import.messages). Conversation = listing + buyer,
 * and a pair of users for messages without a listing. Old messages are marked read.
 */
class MessageImport extends Step
{
    /** @var array<int, true> */
    private array $touched = [];

    public function run(): void
    {
        $table = (string) config('wordpress_import.messages.table');
        if ($table === '' || ! $this->wp->hasTable($table)) {
            $this->report->note('messages', "tabela {$this->wp->prefix()}{$table} ne postoji, poruke nisu prenete");

            return;
        }

        $c = config('wordpress_import.messages.columns');
        $users = $this->ctx->map('users');
        $listings = $this->ctx->map('listings');
        $sellers = DB::table('listings')->pluck('user_id', 'id')->all();

        $query = $this->wp->table($table)->orderBy($c['id']);
        if ($this->ctx->since !== null) {
            // The message date is in the site's local time
            $query->where($c['created_at'], '>=', $this->ctx->since->copy()->setTimezone('Europe/Belgrade')->format('Y-m-d H:i:s'));
        }

        $query->chunk(500, function ($rows) use ($c, $users, $listings, $sellers) {
            foreach ($rows as $row) {
                $this->import($row, $c, $users, $listings, $sellers);
            }
        });

        foreach (array_keys($this->touched) as $conversationId) {
            $last = DB::table('messages')->where('conversation_id', $conversationId)->max('created_at');
            DB::table('conversations')->where('id', $conversationId)->update([
                'last_message_at' => $last,
                'buyer_read_at' => $last,
                'seller_read_at' => $last,
                'updated_at' => $last,
            ]);
        }
    }

    private function import(object $row, array $c, array $users, array $listings, array $sellers): void
    {
        $wpId = (int) $row->{$c['id']};
        $from = (int) $row->{$c['from']};
        $to = (int) $row->{$c['to']};
        $this->report->wp('messages');

        if ($this->ctx->isSpam($from) || $this->ctx->isSpam($to)) {
            $this->report->skipped('messages', "#{$wpId}: poruka spam naloga");

            return;
        }

        $sender = $users[$from] ?? null;
        $recipient = $users[$to] ?? null;
        if ($sender === null || $recipient === null) {
            $this->report->skipped('messages', "#{$wpId}: korisnik #".($sender === null ? $from : $to).' nije uvezen');

            return;
        }

        $body = trim(html_entity_decode(strip_tags((string) $row->{$c['body']}), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($body === '') {
            $this->report->skipped('messages', "#{$wpId}: prazna poruka");

            return;
        }

        $wpListing = isset($c['listing'], $row->{$c['listing']}) ? (int) $row->{$c['listing']} : 0;
        $listingId = $wpListing > 0 ? ($listings[$wpListing] ?? null) : null;
        if ($wpListing > 0 && $listingId === null) {
            $this->report->note('messages', "#{$wpId}: oglas #{$wpListing} nije uvezen, razgovor je bez oglasa");
        }

        $date = $this->ctx->date(null, (string) $row->{$c['created_at']}) ?? now();
        $conversationId = $this->conversation($listingId, $sender, $recipient, $sellers, $date);

        $values = ['conversation_id' => $conversationId, 'sender_id' => $sender, 'body' => $body, 'created_at' => $date, 'updated_at' => $date];
        if (! DB::table('messages')->where('wp_id', $wpId)->update($values)) {
            if (! DB::table('messages')->where('wp_id', $wpId)->exists()) {
                DB::table('messages')->insert($values + ['wp_id' => $wpId]);
            }
        }

        if (! isset($this->touched[$conversationId])) {
            $this->report->wp('conversations');
            $this->report->imported('conversations');
        }
        $this->touched[$conversationId] = true;
        $this->report->imported('messages');
    }

    private function conversation(?int $listingId, int $sender, int $recipient, array $sellers, $date): int
    {
        if ($listingId !== null) {
            $seller = (int) ($sellers[$listingId] ?? $recipient);
            $buyer = $sender === $seller ? $recipient : $sender;
            $key = "l{$listingId}-u{$buyer}";
        } else {
            [$seller, $buyer] = [$recipient, $sender];
            $key = 'u'.min($sender, $recipient).'-'.max($sender, $recipient);
        }

        $existing = DB::table('conversations')->where('wp_key', $key)->value('id');
        if ($existing === null && $listingId !== null) {
            $existing = DB::table('conversations')->where('listing_id', $listingId)->where('buyer_id', $buyer)->value('id');
        }
        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('conversations')->insertGetId([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'wp_key' => $key,
            'created_at' => $date,
            'updated_at' => $date,
        ]);
    }
}
