<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A local outlet or institution whose news the portal summarises (always with a link back). */
class InfoSource extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'consent' => 'boolean'];
    }
}
