<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Reads and writes go through App\Support\Settings (cached). */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
