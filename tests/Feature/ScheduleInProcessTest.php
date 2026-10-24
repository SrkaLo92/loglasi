<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleInProcessTest extends TestCase
{
    public function test_every_task_runs_without_a_child_process(): void
    {
        $events = $this->app->make(Schedule::class)->events();

        $this->assertNotEmpty($events);
        foreach ($events as $event) {
            $this->assertInstanceOf(CallbackEvent::class, $event, (string) $event->command);
            $this->assertNotEmpty($event->description);
        }
    }

    public function test_schedule_list_names_every_task(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('search:refresh')
            ->expectsOutputToContain('listings:expire')
            ->expectsOutputToContain('notifications:saved-searches')
            ->assertSuccessful();
    }
}
