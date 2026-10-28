<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Admin\SystemHealth as Health;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/** Cron, queue, failed jobs, recent errors and the settings that matter, on one page. Admin only. */
class SystemHealth extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Stanje sistema';

    protected static ?string $title = 'Stanje sistema';

    protected static ?string $slug = 'system';

    protected string $view = 'admin.pages.system-health';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public function getSubheading(): ?string
    {
        return 'Stanje u ovom trenutku. Osvežite stranu za nove brojke.';
    }

    public function retryJob(string $uuid): void
    {
        if (! DB::table('failed_jobs')->where('uuid', $uuid)->exists()) {
            return;
        }
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        AuditLog::record('queue.retry', null, ['uuid' => $uuid]);
        Notification::make()->title('Posao je vraćen u red, ide u roku od minut.')->success()->send();
    }

    public function forgetJob(string $uuid): void
    {
        if (DB::table('failed_jobs')->where('uuid', $uuid)->delete() > 0) {
            AuditLog::record('queue.forget', null, ['uuid' => $uuid]);
            Notification::make()->title('Neuspeli posao je obrisan.')->success()->send();
        }
    }

    protected function getViewData(): array
    {
        $health = app(Health::class);

        return [
            'scheduler' => $health->scheduler(),
            'queue' => $health->queue(),
            'failed' => $health->failedJobs(),
            'logErrors' => $health->logErrors(),
            'settings' => $health->settings(),
            'announcements' => $health->announcements(),
        ];
    }
}
