<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PrayerTimesService
{
    private const PRAYER_NAMES = ['Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'];

    private const CAMPUSES = [
        'Burnaby' => ['latitude' => 49.2781, 'longitude' => -122.9199],
        'Surrey' => ['latitude' => 49.1897, 'longitude' => -122.849],
        'Vancouver' => ['latitude' => 49.2847, 'longitude' => -123.1119],
    ];

    public function getPrayerTimesByCampus(): array
    {
        $cacheKey = 'website_prayer_times_'.now('America/Vancouver')->format('Y-m-d');

        return Cache::remember($cacheKey, 3600, function () {
            $times = [];

            foreach (self::CAMPUSES as $campus => $coordinates) {
                $campusTimes = $this->fetchCampusPrayerTimes($campus, $coordinates);
                if ($campusTimes !== null) {
                    $times[$campus] = $campusTimes;
                }
            }

            return $times;
        });
    }

    private function fetchCampusPrayerTimes(string $campus, array $coordinates): ?array
    {
        $date = now('America/Vancouver')->format('d-m-Y');

        $response = Http::timeout(10)->get("https://api.aladhan.com/v1/timings/{$date}", [
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
            'method' => 2,
            'school' => 1,
            'timezonestring' => 'America/Vancouver',
        ]);

        if (! $response->ok()) {
            Log::warning('Failed to fetch prayer times from Aladhan', [
                'campus' => $campus,
                'status' => $response->status(),
            ]);

            return null;
        }

        $timings = $response->json('data.timings') ?? [];

        return array_map(function (string $name) use ($timings) {
            return [
                'name' => $name,
                'time' => $this->formatPrayerTime($timings[$name] ?? 'Updating'),
            ];
        }, self::PRAYER_NAMES);
    }

    private function formatPrayerTime(string $value): string
    {
        if ($value === 'Updating') {
            return $value;
        }

        $cleanValue = explode(' ', $value)[0];
        $parts = explode(':', $cleanValue);

        if (count($parts) < 2) {
            return $value;
        }

        $hour = (int) $parts[0];
        $minute = (int) $parts[1];

        if (! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
            return $value;
        }

        $period = $hour >= 12 ? 'PM' : 'AM';
        $hour12 = $hour % 12 ?: 12;

        return sprintf('%d:%02d %s', $hour12, $minute, $period);
    }
}
