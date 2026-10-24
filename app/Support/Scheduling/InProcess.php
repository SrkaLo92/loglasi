<?php

namespace App\Support\Scheduling;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

/**
 * Schedules an artisan command inside the schedule:run process.
 *
 * Schedule::command() starts a child process through proc_open, which
 * shared hosting (Hostinger) disables, so every task runs in process.
 */
final class InProcess
{
    public static function command(Schedule $schedule, string $command): CallbackEvent
    {
        $name = class_exists($command) ? app($command)->getName() : $command;

        return $schedule->call(fn () => Artisan::call($name))->name($name);
    }
}
