<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use App\Models\Device;

class MqttListen extends Command
{
    protected $signature = 'mqtt:listen';
    protected $description = 'Listen to MQTT messages';

    public function handle()
    {
        $host = env('MQTT_HOST');
        $port = env('MQTT_PORT', 8883);

        $clientId = 'laravel-listener-' . uniqid();

        $mqtt = new MqttClient($host, $port, $clientId, MqttClient::MQTT_3_1_1);

        $connectionSettings = (new ConnectionSettings)
            ->setUsername(env('MQTT_AUTH_USERNAME'))
            ->setPassword(env('MQTT_AUTH_PASSWORD'))
            ->setUseTls(true)
            ->setTlsSelfSignedAllowed(false)
            ->setTlsVerifyPeer(true)
            ->setTlsVerifyPeerName(true)
            ->setTlsCertificateAuthorityFile(env('MQTT_TLS_CA_FILE'))
            ->setKeepAliveInterval(60);

        $mqtt->connect($connectionSettings, true);

        $this->info("Connected to MQTT");

         
        // ----------------------------
        // Subscribe to device data topic and handle incoming messages
        // ----------------------------
        $mqtt->subscribe('device/+/data', function ($topic, $message) {
            echo "Received [$topic]: $message\n";
            // check topic format and extract device UID
            preg_match('/device\/(.+)\/data/', $topic, $matches); 
            $uid = $matches[1] ?? null;
            if (!$uid) {
                return;
            }

            $data = json_decode($message, true);
            if (!$data) {
                return;
            }

            $device = Device::where('device_uid', $uid)->first();
            if (!$device) {
                return;
            }

            // create new reading to save to database
            $reading = $device->readings()->create([
                'moisture' => $data['moisture'] ?? null,
                'temperature' => $data['temperature'] ?? null,
                'recorded_at' => now(),
            ]);
            echo "Saved reading for device $uid\n";

            // ----------------------------
            // check if notifications should be generated 
            // ----------------------------
            $user = $device->user;
            if ($user) {
                // Frost warning
                if (isset($data['temperature']) && $data['temperature'] < 0) {
                    // check if a similar notification has been sent regarding this device
                    $existing = \App\Models\Notification::where('user_id', $user->id)
                        ->where('title', 'Frost Warning')
                        ->where('body', 'LIKE', "%{$device->name}%")
                        ->where('created_at', '>=', now()->subHours(6)) // ensure we don't spam with multiple notifications in a short time
                        ->first();
                    if (!$existing) {
                        \App\Models\Notification::create([
                            'user_id' => $user->id,
                            'title' => 'Frost Warning',
                            'body' => "Device '{$device->name}' is at risk of frost damage (temperature below 0°C)",
                        ]);
                    }
                }

                // Persistent low moisture warning
                if (isset($data['moisture']) && isset($device->red_threshold)) {
                    // Check last 100 readings for this device
                    $recent = $device->readings()->orderBy('recorded_at', 'desc')->limit(100)->get();
                    $lowCount = $recent->where('moisture', '<=', $device->red_threshold)->count();
                    if ($lowCount == 100) { // All 100 readings below threshold, water likely isnt getting through to the plant
                        // check if a similar notification has been sent regarding this device
                        $existing = \App\Models\Notification::where('user_id', $user->id)
                            ->where('title', 'Low Moisture Alert')
                            ->where('body', 'LIKE', "%{$device->name}%")
                            ->where('created_at', '>=', now()->subHours(2)) // ensure we don't spam with multiple notifications in a short time
                            ->first();
                        if (!$existing) {
                            \App\Models\Notification::create([
                                'user_id' => $user->id,
                                'title' => 'Low Moisture Alert',
                                'body' => "Device '{$device->name}' has had low soil moisture (≤ {$device->red_threshold}%) for an extended period.",
                            ]);
                        }
                    }
                }
            }
        }, 0);

        $mqtt->loop(true);
    }
}
