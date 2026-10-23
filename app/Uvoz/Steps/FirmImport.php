<?php

namespace App\Uvoz\Steps;

use App\Support\Settings;
use App\Uvoz\Text;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Profili firmi za naloge „business” (polja iz loglasi-core, inc/profil). */
class FirmImport extends Step
{
    public function run(): void
    {
        $keys = config('uvoz.usermeta');

        // Svi poslovni nalozi po datumu registracije; prvih N su „osnivačke” firme (besplatno).
        $firmIds = $this->wp->table('users as u')
            ->join('usermeta as m', 'm.user_id', '=', 'u.ID')
            ->where('m.meta_key', $keys['account_type'])
            ->where('m.meta_value', $keys['account_business'])
            ->whereNotIn('u.ID', $this->ctx->spamUsers ?: [0])
            ->orderBy('u.user_registered')->orderBy('u.ID')
            ->pluck('u.ID')->map(fn ($id) => (int) $id)->all();

        $founding = array_slice($firmIds, 0, (int) Settings::get('founding_firms_total'));
        $users = $this->ctx->map('users', true);
        $meta = $this->wp->userMeta($firmIds);
        $names = $this->wp->table('users')->whereIn('ID', $firmIds ?: [0])->pluck('display_name', 'ID');
        $locations = DB::table('locations')->get(['id', 'name', 'slug']);

        foreach ($firmIds as $wpId) {
            $this->report->wp('firms');
            $userId = $users[$wpId] ?? null;
            if ($userId === null) {
                $this->report->error('firms', "#{$wpId}: nalog nije uvezen");

                continue;
            }

            $m = $meta[$wpId] ?? [];
            $get = fn (string $key) => $keys[$key] !== '' ? trim((string) (is_scalar($v = Text::unserialize($m[$keys[$key]][0] ?? '')) ? $v : '')) : '';
            $existing = DB::table('firms')->where('user_id', $userId)->first();

            $description = $get('firm_description');
            $activity = $get('firm_activity');

            $city = $get('firm_city');
            $locationId = null;
            if ($city !== '') {
                $slug = Str::slug($city);
                $locationId = $locations->first(fn ($l) => mb_strtolower($l->name) === mb_strtolower($city) || $l->slug === $slug)?->id;
            }

            $values = [
                'name' => Str::limit(Text::title((string) ($names[$wpId] ?? '')) ?: 'Firma '.$wpId, 250, ''),
                'description' => $description !== '' ? $description : ($activity !== '' ? $activity : null),
                'address' => $get('firm_address') ?: null,
                'location_id' => $locationId,
                'website' => $get('firm_website') ?: null,
                'facebook' => $get('firm_facebook') ?: null,
                'instagram' => $get('firm_instagram') ?: null,
                'working_hours' => $this->hours($m[$keys['firm_hours']][0] ?? null),
                'verified_at' => $get('firm_verified') === '1' ? ($existing->verified_at ?? now()) : null,
                'founding_free' => in_array($wpId, $founding, true),
                'updated_at' => now(),
            ];

            $logoId = (int) $get('firm_logo') ?: (int) $get('avatar');
            if ($logoId > 0) {
                $values['logo_path'] = $this->ctx->copyAttachment($logoId, 'firme/'.$userId, 'firms', "#{$wpId} logo");
            }
            if (($coverId = (int) $get('firm_cover')) > 0) {
                $values['cover_path'] = $this->ctx->copyAttachment($coverId, 'firme/'.$userId, 'firms', "#{$wpId} naslovna");
            }

            if ($keys['biznis_until'] !== '' && ($until = $get('biznis_until')) !== '') {
                $values['biznis_until'] = is_numeric($until) ? Carbon::createFromTimestamp((int) $until) : Carbon::parse($until);
            }

            if ($activity !== '' && $description !== '') {
                $this->report->note('firms', "#{$wpId}: delatnost „{$activity}” nema svoje polje u novoj šemi");
            }

            if ($existing) {
                DB::table('firms')->where('id', $existing->id)->update($values);
            } else {
                DB::table('firms')->insert($values + ['user_id' => $userId, 'created_at' => now()]);
            }
            $this->report->imported('firms');
        }
    }

    /** Radno vreme {1..7: tekst}; null kad ništa nije upisano. */
    private function hours(mixed $raw): ?string
    {
        $saved = Text::unserialize($raw);
        if (! is_array($saved)) {
            return null;
        }

        $hours = [];
        foreach (range(1, 7) as $day) {
            $hours[(string) $day] = isset($saved[$day]) && is_scalar($saved[$day]) ? trim((string) $saved[$day]) : '';
        }

        return implode('', $hours) === '' ? null : json_encode($hours, JSON_UNESCAPED_UNICODE);
    }
}
