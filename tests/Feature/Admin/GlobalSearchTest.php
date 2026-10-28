<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus;
use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Listing;
use App\Models\User;
use Tests\Feature\Admin\Concerns\ActsAsStaff;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use ActsAsStaff;

    public function test_finds_users_by_id_phone_in_any_spelling_and_email(): void
    {
        $this->signIn($this->admin());
        $user = User::factory()->create(['email' => 'pera.peric@gmail.com', 'phone' => '+381641234567']);
        User::factory()->create(['phone' => '+381659999999']);

        foreach ([(string) $user->id, '#'.$user->id, '064 123 4567', '+381 64 1234567', 'pera.peric'] as $search) {
            $titles = UserResource::getGlobalSearchResults($search)->pluck('title')->all();
            $this->assertSame([$user->name], $titles, $search);
        }

        $result = UserResource::getGlobalSearchResults('pera.peric')->first();
        $this->assertSame('pera.peric@gmail.com', $result->details['Mejl']);
        $this->assertSame('Profil na sajtu', $result->actions[0]->getLabel());
    }

    public function test_finds_listing_by_number(): void
    {
        $this->signIn($this->admin());
        $listing = Listing::factory()->create(['status' => ListingStatus::Published]);

        $result = ListingResource::getGlobalSearchResults('#'.$listing->id)->sole();
        $this->assertSame((string) $listing->id, $result->details['Br.']);
        $this->assertSame($listing->url(), $result->actions[0]->getUrl());
    }
}
