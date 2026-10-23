<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Čitanje i upis ide preko App\Support\Settings (keš). */
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
