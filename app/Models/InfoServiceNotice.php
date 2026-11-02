<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInfoCity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A planned outage or other service notice (power, water, roads) for Loznica and the places around it. */
class InfoServiceNotice extends Model
{
    use BelongsToInfoCity;

    public const KINDS = [
        'power' => 'Struja',
        'water' => 'Voda',
        'road' => 'Putevi',
        'other' => 'Ostalo',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published' => 'boolean',
        ];
    }

    /** Notices that are still ahead or going on now. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('published', true)
            ->where(fn ($q) => $q->where('ends_at', '>=', now())
                ->orWhere(fn ($q) => $q->whereNull('ends_at')->where('starts_at', '>=', now()->startOfDay())));
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    public function whenLabel(): string
    {
        $start = $this->starts_at;
        $day = match (true) {
            $start->isToday() => 'Danas',
            $start->isTomorrow() => 'Sutra',
            default => $start->translatedFormat('l, j. n.'),
        };
        $time = $start->format('H:i').($this->ends_at ? '–'.$this->ends_at->format('H:i') : '');

        return $day.', '.$time;
    }
}
