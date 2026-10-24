<?php

namespace App\Filament\Support;

use App\Models\AuditLog;
use App\Support\Seo\Sitemap;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/** "Rebuild sitemap" button (same as `php artisan seo:sitemap`). */
class SitemapAction
{
    public static function make(): Action
    {
        return Action::make('rebuildSitemap')
            ->label('Ponovo napravi sitemap')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Sitemap se inače pravi sam svakog sata. Ovo ga pravi odmah.')
            ->action(function () {
                $sitemap = app(Sitemap::class);
                $count = $sitemap->rebuild();
                AuditLog::record('seo.sitemap_rebuild', null, ['urls' => $count, 'parts' => count($sitemap->parts())]);
                Notification::make()->title('Sitemap je napravljen: '.number_format($count, 0, ',', '.').' adresa.')->success()->send();
            });
    }
}
