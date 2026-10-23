<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    public const BUMP = 'bump';

    public const FEATURED_7 = 'featured_7';

    public const FEATURED_30 = 'featured_30';

    public const BIZNIS_12M = 'biznis_12m';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function byCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }
}
