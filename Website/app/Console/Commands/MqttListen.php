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

        $mqtt->subscribe('device/+/data', function ($topic, $message) {

            echo "Received [$topic]: $message\n";

            preg_match('/device\/(.+)\/data/', $topic, $matches);
            $uid = $matches[1] ?? null;

            if (!$uid) return;

            $data = json_decode($message, true);
            if (!$data) return;

            $device = Device::where('device_uid', $uid)->first();
            if (!$device) return;

        }, 0);

        $mqtt->loop(true);
    }
}
