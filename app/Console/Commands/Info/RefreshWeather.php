<?php

namespace App\Console\Commands\Info;

use App\Support\Info\Weather;
use Illuminate\Console\Command;

class RefreshWeather extends Command
{
    protected $signature = 'info:weather';

    protected $description = 'Info portal: refresh the weather for Loznica (Open-Meteo)';

    public function handle(): int
    {
        if (! config('info.enabled')) {
            return self::SUCCESS;
        }
        try {
            $ok = Weather::refresh();
        } catch (\Throwable $e) {
            report($e);
            $ok = false;
        }
        $this->line($ok ? 'Weather refreshed.' : 'Weather service did not answer; the last forecast stays.');

        return self::SUCCESS;
    }
}
