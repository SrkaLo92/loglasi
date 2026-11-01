<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** "Pošaljite upit" on the advertising page. */
class InfoAdInquiry extends Model
{
    public const INTERESTS = [
        'package' => 'Paket',
        'featured' => 'Istaknuta firma u imeniku',
        'banner' => 'Baner',
        'promo' => 'Promo tekst ili video',
        'unsure' => 'Nisam siguran, predložite mi',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }
}
