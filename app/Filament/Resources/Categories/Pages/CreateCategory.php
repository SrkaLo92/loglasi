<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Support\AuditsPageWrites;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    use AuditsPageWrites;

    protected static string $resource = CategoryResource::class;

    /** "Nova potkategorija" opens this page with ?parent=ID. */
    protected function afterFill(): void
    {
        $parent = request()->query('parent');

        if (is_numeric($parent)) {
            $this->data['parent_id'] = (int) $parent;
        }
    }
}
