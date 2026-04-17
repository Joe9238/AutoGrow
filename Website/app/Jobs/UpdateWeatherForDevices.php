<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Device;
use PhpMqtt\Client\Facades\MQTT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class UpdateWeatherForDevices implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
        foreach (Device::all() as $device) {
            $rainToday = $this->checkRain($device->latitude, $device->longitude);

            MQTT::publish("device/{$device->device_uid}/config", json_encode([
                'rain_today' => $rainToday,
                'yellow_threshold' => $device->yellow_threshold,
                'red_threshold' => $device->red_threshold,
            ]));
        }
    }

    public function checkRain($lat, $lng)
    {
        // check lat and long are valid
        if (!is_numeric($lat) || !is_numeric($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return false; // allow watering
        }

        $rainThreshold = env('RAIN_MM_THRESHOLD', 0.5); // mm, default 0.5

        $url = "https://api.open-meteo.com/v1/forecast"
            . "?latitude={$lat}&longitude={$lng}"
            . "&hourly=rain"
            . "&forecast_days=2";

        $response = Http::get($url);

        if (!$response->successful()) {
            return false; // allow watering
        }

        $rainData = $response['hourly']['rain'] ?? [];
        $now = now();
        $hoursToCheck = 6;
        $hourIndex = 0;
        // Try to get the current hour index from the API if available
        if (isset($response['hourly']['time']) && is_array($response['hourly']['time'])) {
            $times = $response['hourly']['time'];
            foreach ($times as $i => $timeStr) {
                if (substr($timeStr, 0, 13) === $now->format('Y-m-d H')) {
                    $hourIndex = $i;
                    break;
                }
            }
        }
        // Check rain for the next 6 hours (including current)
        for ($i = $hourIndex; $i < $hourIndex + $hoursToCheck && $i < count($rainData); $i++) {
            if ($rainData[$i] >= $rainThreshold) {
                return true;
            }
        }

        return false;
    }
}
