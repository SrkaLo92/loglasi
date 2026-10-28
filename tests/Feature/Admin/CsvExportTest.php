<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Listings\Pages\ListListings;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\AuditLog;
use App\Models\Listing;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\ActsAsStaff;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use ActsAsStaff;

    public function test_admin_exports_listings_and_users_as_csv(): void
    {
        Listing::factory()->create(['title' => '=Golf 4']);
        $this->signIn($this->admin());

        Livewire::test(ListListings::class)->callTableAction('export')->assertFileDownloaded();
        Livewire::test(ListUsers::class)->callTableAction('export')->assertFileDownloaded();

        $this->assertSame(2, AuditLog::query()->where('action', 'like', 'export.%')->count());
    }

    public function test_moderator_cannot_export(): void
    {
        $this->signIn($this->moderator());

        Livewire::test(ListUsers::class)->assertTableActionHidden('export');
    }
}
