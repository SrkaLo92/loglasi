<?php

namespace App\Models;

use App\Enums\FieldFilter;
use App\Enums\FieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Field extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => FieldType::class,
            'filter' => FieldFilter::class,
            'options' => 'array',
        ];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withPivot(['required', 'sort']);
    }
}
