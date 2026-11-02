<?php

namespace App\Support\Info;

use Illuminate\Support\Facades\Http;

/**
 * Weather in Loznica from Open-Meteo (free, no key). Fetched by the scheduler and kept in a small file
 * (not the cache, so cache:clear on a deploy does not empty the weather until the next fetch).
 */
final class Weather
{
    private static ?array $loaded = null;

    public static function file(): string
    {
        return storage_path('app/info-weather.json');
    }

    /** Open-Meteo WMO weather codes to short Serbian labels. */
    private const LABELS = [
        0 => 'vedro', 1 => 'pretežno sunčano', 2 => 'delimično oblačno', 3 => 'oblačno',
        45 => 'magla', 48 => 'magla',
        51 => 'rosulja', 53 => 'rosulja', 55 => 'rosulja', 56 => 'ledena rosulja', 57 => 'ledena rosulja',
        61 => 'slaba kiša', 63 => 'kiša', 65 => 'jaka kiša', 66 => 'ledena kiša', 67 => 'ledena kiša',
        71 => 'slab sneg', 73 => 'sneg', 75 => 'jak sneg', 77 => 'sneg',
        80 => 'pljuskovi', 81 => 'pljuskovi', 82 => 'jaki pljuskovi',
        85 => 'snežni pljuskovi', 86 => 'snežni pljuskovi',
        95 => 'grmljavina', 96 => 'grmljavina sa gradom', 99 => 'grmljavina sa gradom',
    ];

    public static function label(int $code): string
    {
        return self::LABELS[$code] ?? 'promenljivo';
    }

    /** @return array{temp: int, label: string, today_min: int, today_max: int, tomorrow: string, at: int}|null */
    public static function current(): ?array
    {
        $data = self::$loaded ??= (is_file(self::file()) ? json_decode((string) file_get_contents(self::file()), true) : null) ?: [];

        // Older than 6 hours: better nothing than a stale forecast.
        return is_array($data) && ($data['at'] ?? 0) > time() - 6 * 3600 ? $data : null;
    }

    /** Fetches and caches the forecast; returns false when the service did not answer usefully. */
    public static function refresh(): bool
    {
        $config = config('info.weather');
        $response = Http::timeout((int) config('info.http_timeout'))->acceptJson()->get($config['url'], [
            'latitude' => $config['latitude'],
            'longitude' => $config['longitude'],
            'current' => 'temperature_2m,weather_code',
            'daily' => 'weather_code,temperature_2m_max,temperature_2m_min',
            'timezone' => 'Europe/Belgrade',
            'forecast_days' => 2,
        ]);

        $json = $response->successful() ? $response->json() : null;
        if (! is_array($json) || ! isset($json['current']['temperature_2m'], $json['daily']['temperature_2m_max'][0])) {
            return false;
        }

        self::$loaded = [
            'temp' => (int) round($json['current']['temperature_2m']),
            'label' => self::label((int) ($json['current']['weather_code'] ?? -1)),
            'today_min' => (int) round($json['daily']['temperature_2m_min'][0]),
            'today_max' => (int) round($json['daily']['temperature_2m_max'][0]),
            'tomorrow' => self::label((int) ($json['daily']['weather_code'][1] ?? -1)),
            'at' => time(),
        ];
        file_put_contents(self::file(), json_encode(self::$loaded), LOCK_EX);

        return true;
    }
}
