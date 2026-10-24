<?php

namespace Tests\Feature\Admin\Concerns;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;

/** Staff with TOTP set up, signed in through the panel login (session marker). */
trait ActsAsStaff
{
    protected function admin(array $attributes = []): User
    {
        return User::factory()->admin()->create($attributes + ['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);
    }

    protected function moderator(array $attributes = []): User
    {
        return User::factory()->moderator()->create($attributes + ['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);
    }

    protected function signIn(User $user): static
    {
        $this->actingAs($user)->withSession([Login::SESSION_KEY => $user->id]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $this;
    }
}
