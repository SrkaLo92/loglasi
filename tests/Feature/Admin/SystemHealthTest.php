<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\SystemHealth;
use App\Services\Admin\SystemHealth as Health;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\ActsAsStaff;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use ActsAsStaff;

    private function failedJob(): string
    {
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid, 'connection' => 'database', 'queue' => 'default',
            'payload' => json_encode(['uuid' => $uuid, 'displayName' => 'App\\Mail\\ListingStatusMail', 'job' => 'x', 'data' => []]),
            'exception' => "Symfony\\Component\\Mailer\\Exception\\TransportException: Connection refused\n#0 trace",
            'failed_at' => now(),
        ]);

        return $uuid;
    }

    public function test_scheduler_heartbeat(): void
    {
        $this->assertFalse(app(Health::class)->scheduler()['ok']);

        Cache::forever(Health::HEARTBEAT, time() - 30);
        $this->assertTrue(app(Health::class)->scheduler()['ok']);

        Cache::forever(Health::HEARTBEAT, time() - 600);
        $this->assertFalse(app(Health::class)->scheduler()['ok']);
    }

    public function test_admin_sees_failed_jobs_and_can_retry_or_delete(): void
    {
        $retry = $this->failedJob();
        $forget = $this->failedJob();
        $this->signIn($this->admin());

        $this->get('/admin/system')->assertOk()->assertSee('ListingStatusMail')->assertSee('Connection refused');

        Livewire::test(SystemHealth::class)->call('retryJob', $retry)->call('forgetJob', $forget);

        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(1, DB::table('jobs')->count());
    }

    public function test_moderator_has_no_access(): void
    {
        $this->signIn($this->moderator());

        $this->get('/admin/system')->assertForbidden();
    }
}
