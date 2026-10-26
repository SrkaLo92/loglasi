<?php

namespace App\WordPressImport\Steps;

use App\Enums\AccountType;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\WordPressImport\Phone;
use App\WordPressImport\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Korisnici sa istim ID-jem i slugom (user_nicename). Heš lozinke se prenosi
 * neizmenjen ($P$… phpass ili $wp$2y$…), mimo „hashed” cast-a modela.
 */
class UserImport extends Step
{
    public function run(): void
    {
        $keys = config('wordpress_import.usermeta');
        $capKey = $this->wp->prefix().'capabilities';
        $metaKeys = array_values(array_filter([
            $capKey, $keys['account_type'], $keys['phone'], $keys['phone_country'],
            $keys['phone_verified'], $keys['avatar'], $keys['firm_phone'],
        ]));

        $query = $this->wp->table('users')->orderBy('ID');
        if ($since = $this->ctx->sinceUtc()) {
            $this->restrictToDelta($query, $since);
        }

        $query->chunk(500, function ($users) use ($metaKeys, $keys, $capKey) {
            $meta = $this->wp->userMeta($users->pluck('ID')->map(fn ($id) => (int) $id)->all(), $metaKeys);
            foreach ($users as $user) {
                $this->import($user, $meta[(int) $user->ID] ?? [], $keys, $capKey);
            }
        });

        $this->ctx->forget('users');
    }

    /** Za --since: novi nalozi i autori izmenjenih oglasa i poruka. */
    private function restrictToDelta($query, string $since): void
    {
        $postType = config('wordpress_import.post_type');
        $authors = $this->wp->table('posts')->where('post_type', $postType)
            ->where(fn ($q) => $q->where('post_modified_gmt', '>=', $since)->orWhere('post_date_gmt', '>=', $since))
            ->distinct()->pluck('post_author')->all();

        $participants = [];
        $table = config('wordpress_import.messages.table');
        if ($table && $this->wp->hasTable($table)) {
            $c = config('wordpress_import.messages.columns');
            $rows = $this->wp->table($table)->where($c['created_at'], '>=', $since)->get([$c['from'], $c['to']]);
            foreach ($rows as $row) {
                $participants[] = $row->{$c['from']};
                $participants[] = $row->{$c['to']};
            }
        }

        $ids = array_values(array_unique(array_map('intval', array_merge($authors, $participants))));
        $query->where(fn ($q) => $q->where('user_registered', '>=', $since)->orWhereIn('ID', $ids ?: [0]));
    }

    /** @param  array<string, array<int, string|null>>  $meta */
    private function import(object $user, array $meta, array $keys, string $capKey): void
    {
        $wpId = (int) $user->ID;
        $this->report->wp('users');
        $label = "#{$wpId} {$user->user_login}";

        if ($this->ctx->isSpam($wpId)) {
            $this->report->skipped('users', "{$label}: spam nalog");

            return;
        }

        $email = Str::lower(trim((string) $user->user_email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->report->error('users', "{$label}: neispravna e-pošta „{$user->user_email}”");

            return;
        }

        $existing = DB::table('users')->where('wp_id', $wpId)->first();

        if (DB::table('users')->where('email', $email)->where(fn ($q) => $q->whereNull('wp_id')->orWhere('wp_id', '!=', $wpId))->exists()) {
            $this->report->error('users', "{$label}: e-pošta {$email} već postoji kod drugog naloga");

            return;
        }

        $slug = trim(rawurldecode((string) $user->user_nicename));
        if ($slug === '') {
            $slug = Str::slug((string) $user->user_login) ?: 'korisnik-'.$wpId;
        }
        if (DB::table('users')->where('slug', $slug)->where(fn ($q) => $q->whereNull('wp_id')->orWhere('wp_id', '!=', $wpId))->exists()) {
            $newSlug = $slug.'-'.$wpId;
            $this->report->note('users', "{$label}: slug „{$slug}” je zauzet, upisan „{$newSlug}” (dodati 301)");
            $slug = $newSlug;
        }

        $first = fn (string $key) => $key !== '' ? trim((string) (Text::unserialize($meta[$key][0] ?? '') ?: '')) : '';

        $caps = Text::unserialize($meta[$capKey][0] ?? '');
        $caps = is_array($caps) ? array_keys(array_filter($caps)) : [];
        $role = in_array('administrator', $caps, true) ? Role::Admin : Role::User;

        $isFirm = $first($keys['account_type']) === $keys['account_business'];

        $rawPhone = $first($keys['phone']);
        $phone = Phone::join($rawPhone, $first($keys['phone_country']));
        if ($phone === '' && $isFirm) {
            $phone = Phone::normalize($first($keys['firm_phone']));
        }
        if ($phone === '' && $rawPhone !== '') {
            $this->report->note('users', "{$label}: neispravan telefon „{$rawPhone}”, nije prenet");
        }

        // user_registered je u UTC
        $registered = $this->ctx->date((string) $user->user_registered) ?? now();

        $avatar = null;
        $avatarId = (int) $first($keys['avatar']);
        if ($avatarId > 0) {
            $avatar = $this->ctx->copyAttachment($avatarId, 'avatari/'.$wpId, 'users', $label);
        }

        $name = Text::title((string) $user->display_name) ?: (string) $user->user_login;

        $values = [
            'name' => Str::limit($name, 250, ''),
            'email' => $email,
            'password' => (string) $user->user_pass,
            'slug' => $slug,
            'account_type' => ($isFirm ? AccountType::Firm : AccountType::Private)->value,
            // Uloga dodeljena u novom adminu ostaje, osim kad je nalog administrator u WordPress-u.
            'role' => $role === Role::Admin ? $role->value : ($existing->role ?? Role::User->value),
            'phone' => $phone !== '' ? $phone : null,
            'phone_verified_at' => $first($keys['phone_verified']) === '1' ? ($existing->phone_verified_at ?? now()) : null,
            'avatar_path' => $avatar ?? ($existing->avatar_path ?? null),
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('users')->where('id', $existing->id)->update($values + ['deleted_at' => null]);
        } else {
            $values += [
                'wp_id' => $wpId,
                'status' => UserStatus::Active->value,
                'email_verified_at' => $registered,
                'created_at' => $registered,
            ];
            // Isti ID kao u WordPress-u, ako je slobodan.
            if (! DB::table('users')->where('id', $wpId)->exists()) {
                $values['id'] = $wpId;
            } else {
                $this->report->note('users', "{$label}: ID {$wpId} je zauzet, nalog dobija novi ID");
            }
            DB::table('users')->insert($values);
        }

        $this->report->imported('users');
    }
}
