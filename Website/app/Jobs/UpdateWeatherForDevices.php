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

    // empty constructor
    public function __construct()
    {
        //
    }

    // ----------------------------
    // Execute the job.
    // ----------------------------
    public function handle(): void
    {
        // loop through all devices saved on the database
        foreach (Device::all() as $device) {
            $rainToday = $this->checkRain($device->latitude, $device->longitude);

            // send config and weather update to the device via HiveMQ
            MQTT::publish("device/{$device->device_uid}/config", json_encode([
                'rain_today' => $rainToday,
                'yellow_threshold' => $device->yellow_threshold,
                'red_threshold' => $device->red_threshold,
            ]));
        }
    }

    // ----------------------------
    // Check if rain is expected today at the given latitude and longitude using the Open-Meteo API
    // ----------------------------
    public function checkRain($lat, $lng)
    {
        // check lat and long are valid
        if (!is_numeric($lat) || !is_numeric($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return false; // coordinates are not properly set so allow watering
        }

        $rainThreshold = env('RAIN_MM_THRESHOLD', 0.5); // rain expected in mm, default 0.5mm

        // build and send api request to Open-Meteo
        $url = "https://api.open-meteo.com/v1/forecast"
            . "?latitude={$lat}&longitude={$lng}"
            . "&hourly=rain"
            . "&forecast_days=2"; // get the next 2 days since it will only return the current day, not the next 24 hours

        $response = Http::get($url);

        if (!$response->successful()) {
            return false; // allow watering
        }

        $rainData = $response['hourly']['rain'] ?? [];
        $now = now();
        $hoursToCheck = 6;
        $hourIndex = 0;
        // Try to get the current hour's index from the API if available
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
                return true; // rain is expected in the next 6 hours, prevent watering
            }
        }

        return false; // rain is not expected in the next 6 hours, allow watering
    }
}
