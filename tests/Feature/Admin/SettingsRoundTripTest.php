<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Services\Admin\SettingsSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsRoundTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_the_untouched_form_stores_no_page_texts(): void
    {
        $changes = SettingsSchema::save(SettingsSchema::load());

        $this->assertSame([], array_values(array_filter(array_keys($changes), fn ($k) => str_starts_with($k, 'page_text.'))));
        $this->assertSame(0, Setting::query()->where('key', 'like', 'page_text.%')->count());
    }
}
