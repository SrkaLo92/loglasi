<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus;
use App\Filament\Resources\Listings\Pages\EditListing;
use App\Filament\Resources\Listings\Pages\ListListings;
use App\Mail\Admin\ListingStatusMail;
use App\Models\AuditLog;
use App\Models\Field;
use App\Models\Listing;
use App\Services\Catalog;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\ActsAsStaff;
use Tests\TestCase;

class ListingAdminTest extends TestCase
{
    use ActsAsStaff;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_moderator_approves_listing_owner_is_mailed_and_action_is_logged(): void
    {
        $listing = Listing::factory()->pending()->create(['bumped_at' => null, 'expires_at' => null]);
        $this->signIn($mod = $this->moderator());

        Livewire::test(ListListings::class)->callTableAction('approve', $listing)->assertHasNoTableActionErrors();

        $listing->refresh();
        $this->assertSame(ListingStatus::Published, $listing->status);
        $this->assertNotNull($listing->published_at);
        $this->assertNotNull($listing->bumped_at);
        $this->assertTrue($listing->expires_at->between(now()->addDays(29), now()->addDays(31)));
        Mail::assertQueued(ListingStatusMail::class, fn ($m) => $m->approved && $m->hasTo($listing->user->email));
        $this->assertDatabaseHas('audit_logs', ['action' => 'listing.approve', 'subject_id' => $listing->id, 'user_id' => $mod->id]);
    }

    public function test_reject_needs_reason_and_sends_it_to_owner(): void
    {
        $listing = Listing::factory()->pending()->create();
        $this->signIn($this->admin());

        Livewire::test(ListListings::class)
            ->callTableAction('reject', $listing, ['reason' => ''])
            ->assertHasTableActionErrors(['reason' => 'required']);

        Livewire::test(ListListings::class)
            ->callTableAction('reject', $listing, ['reason' => 'Fotografije nisu vaše.'])
            ->assertHasNoTableActionErrors();

        $listing->refresh();
        $this->assertSame(ListingStatus::Rejected, $listing->status);
        $this->assertSame('Fotografije nisu vaše.', $listing->rejection_reason);
        Mail::assertQueued(ListingStatusMail::class, fn ($m) => ! $m->approved && $m->reason === 'Fotografije nisu vaše.');
        $this->assertSame(['status' => ['pending', 'rejected'], 'reason' => 'Fotografije nisu vaše.'], AuditLog::where('action', 'listing.reject')->first()->changes);
    }

    public function test_feature_for_n_days_is_free_and_extends_current_end(): void
    {
        $listing = Listing::factory()->create(['featured_until' => now()->addDays(3)]);
        $this->signIn($this->admin());

        Livewire::test(ListListings::class)->callTableAction('feature', $listing, ['days' => 10])->assertHasNoTableActionErrors();

        $this->assertTrue($listing->refresh()->featured_until->between(now()->addDays(13)->subMinute(), now()->addDays(13)->addMinute()));
        $log = AuditLog::where('action', 'listing.feature')->first();
        $this->assertSame(10, $log->changes['days']);
        $this->assertTrue($log->changes['free']);

        Livewire::test(ListListings::class)->callTableAction('feature', $listing, ['days' => 0])->assertHasTableActionErrors(['days']);
    }

    public function test_unfeature_and_bump(): void
    {
        $listing = Listing::factory()->featured()->create(['bumped_at' => now()->subWeek()]);
        $this->signIn($this->admin());

        Livewire::test(ListListings::class)->callTableAction('bump', $listing);
        $this->assertTrue($listing->refresh()->bumped_at->isAfter(now()->subMinute()));

        Livewire::test(ListListings::class)->callTableAction('unfeature', $listing);
        $this->assertNull($listing->refresh()->featured_until);

        $this->assertDatabaseHas('audit_logs', ['action' => 'listing.bump', 'subject_id' => $listing->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'listing.unfeature', 'subject_id' => $listing->id]);
    }

    public function test_bulk_approve_reject_and_delete(): void
    {
        $a = Listing::factory()->pending()->count(2)->create();
        $b = Listing::factory()->pending()->count(2)->create();
        $c = Listing::factory()->count(2)->create();
        $this->signIn($this->admin());

        Livewire::test(ListListings::class)->callTableBulkAction('approve', $a);
        Livewire::test(ListListings::class)->callTableBulkAction('reject', $b, ['reason' => 'Duplikat.']);
        Livewire::test(ListListings::class)->callTableBulkAction('delete', $c);

        $a->each(fn ($l) => $this->assertSame(ListingStatus::Published, $l->refresh()->status));
        $b->each(fn ($l) => $this->assertSame(ListingStatus::Rejected, $l->refresh()->status));
        $c->each(fn ($l) => $this->assertSoftDeleted($l));
        $this->assertSame(2, AuditLog::where('action', 'listing.approve')->count());
        $this->assertSame(2, AuditLog::where('action', 'listing.reject')->count());
        $this->assertSame(2, AuditLog::where('action', 'listing.delete')->count());
        Mail::assertQueued(ListingStatusMail::class, 4);
    }

    public function test_edit_all_fields_and_extra_fields(): void
    {
        $listing = Listing::factory()->create();
        $field = Field::create(['key' => 'kilometraza', 'label' => 'Kilometraža', 'type' => 'number', 'unit' => 'km']);
        $listing->category->fields()->attach($field->id, ['required' => true, 'sort' => 0]);
        app(Catalog::class)->flush();
        $this->signIn($this->admin());

        Livewire::test(EditListing::class, ['record' => $listing->getRouteKey()])
            ->fillForm(['title' => 'Golf 4 1.9 TDI', 'price' => 2500, 'currency' => 'EUR', 'contact_phone' => '065 123 4567', 'fields' => ['kilometraza' => '']])
            ->call('save')
            ->assertHasFormErrors(['fields.kilometraza']);

        Livewire::test(EditListing::class, ['record' => $listing->getRouteKey()])
            ->fillForm(['title' => 'Golf 4 1.9 TDI', 'price' => 2500, 'currency' => 'EUR', 'contact_phone' => '065 123 4567', 'fields' => ['kilometraza' => '180000']])
            ->call('save')
            ->assertHasNoFormErrors();

        $listing->refresh();
        $this->assertSame('Golf 4 1.9 TDI', $listing->title);
        $this->assertSame('+381651234567', $listing->contact_phone);
        $this->assertDatabaseHas('listing_field_values', ['listing_id' => $listing->id, 'field_id' => $field->id, 'value' => '180000']);
        $log = AuditLog::where('action', 'listing.update')->first();
        $this->assertSame('Golf 4 1.9 TDI', $log->changes['title'][1]);
        $this->assertSame(['kilometraza' => '180000'], $log->changes['fields'][1]);
    }

    public function test_filters_tabs_and_public_link(): void
    {
        $pending = Listing::factory()->pending()->create();
        $published = Listing::factory()->create();
        $featured = Listing::factory()->featured()->create();
        $this->signIn($this->admin());

        Livewire::test(ListListings::class)
            ->filterTable('featured', true)
            ->assertCanSeeTableRecords([$featured])
            ->assertCanNotSeeTableRecords([$published]);

        Livewire::test(ListListings::class)
            ->searchTable((string) $published->id)
            ->assertCanSeeTableRecords([$published])
            ->assertCanNotSeeTableRecords([$pending]);

        Livewire::test(ListListings::class)
            ->assertTableActionHasUrl('open', $published->url(), $published);

        Livewire::withQueryParams(['tab' => 'pending'])->test(ListListings::class)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$published]);
    }
}
