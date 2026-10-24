<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\Admin\AdminStats;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Saved searches, search alerts and app devices: numbers only, nothing to edit. Admin only. */
class NotificationStats extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Pretrage i obaveštenja';

    protected static ?string $title = 'Sačuvane pretrage i obaveštenja';

    protected static ?string $slug = 'notifications';

    protected string $view = 'admin.pages.notification-stats';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public function getSubheading(): ?string
    {
        return 'Brojke se osvežavaju na 10 minuta.';
    }

    protected function getViewData(): array
    {
        return ['stats' => app(AdminStats::class)->notifications()];
    }
}
