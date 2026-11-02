<?php

namespace App\Models\Concerns;

use App\Support\Info\InfoCity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Info portal content that belongs to one city: new rows get the current city, queries show only it. */
trait BelongsToInfoCity
{
    public static function bootBelongsToInfoCity(): void
    {
        static::creating(function (Model $model) {
            $model->city ??= InfoCity::current();
        });

        // Off while the portal has one city; the admin always sees every city.
        static::addGlobalScope('info_city', function (Builder $query) {
            if (InfoCity::filtering() && ! request()->is('admin', 'admin/*')) {
                $query->where($query->getModel()->getTable().'.city', InfoCity::current());
            }
        });
    }

    public function scopeInCity(Builder $query, string $city): Builder
    {
        return $query->withoutGlobalScope('info_city')->where($this->getTable().'.city', $city);
    }
}
