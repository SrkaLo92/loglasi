<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInfoCity;
use Illuminate\Database\Eloquent\Model;

/** The pharmacy on duty in Loznica for one day (entered by the editor from the monthly schedule). */
class InfoPharmacyDuty extends Model
{
    use BelongsToInfoCity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public static function today(): ?self
    {
        return self::query()->whereDate('date', today())->first();
    }
}
