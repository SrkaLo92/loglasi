<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Report extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => ReportStatus::class, 'resolved_at' => 'datetime'];
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Files a report; a repeat from the same user while the first one is still open is ignored. */
    public static function fileOnce(?User $reporter, Model $target, string $reason, ?string $details): void
    {
        $open = $reporter !== null && static::query()
            ->where('reporter_id', $reporter->id)
            ->where('reportable_type', $target->getMorphClass())
            ->where('reportable_id', $target->getKey())
            ->where('status', ReportStatus::Open)
            ->exists();

        if (! $open) {
            static::create([
                'reporter_id' => $reporter?->id,
                'reportable_type' => $target->getMorphClass(),
                'reportable_id' => $target->getKey(),
                'reason' => $reason,
                'details' => trim(strip_tags((string) $details)) ?: null,
                'status' => ReportStatus::Open,
            ]);
        }
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
