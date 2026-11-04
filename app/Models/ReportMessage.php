<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One message about a report: a question from the staff or the reporter's answer. */
class ReportMessage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['from_staff' => 'boolean'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
