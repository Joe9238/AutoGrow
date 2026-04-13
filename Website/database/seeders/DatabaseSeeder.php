<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // add default user
        $user = \App\Models\User::factory()->create([
            'name' => 'Generic User',
            'email' => 'email@user.com',
            'password' => '$2y$12$rlZVSSA7OG0k7pckEvC6a.dZpEqp6KsDfSJ40qjRr.oeZFQhqAQom' // password
        ]);

        // add fake devices
        \App\Models\Device::create([
            'id' => 1,
            'name' => 'Fake Device',
            'device_uid' => 'FakeDeviceMacAddress',
            'user_id' => $user->id,
            'mqtt_username' => 'FakeDeviceUsername',
            'mqtt_password' => 'password',
            'created_at' => '2026-04-09 18:52:43',
            'updated_at' => now(),
        ]);
        \App\Models\Device::create([
            'id' => 2,
            'name' => 'Fake Device 2',
            'device_uid' => 'FakeDeviceMacAddress2',
            'user_id' => $user->id,
            'mqtt_username' => 'FakeDeviceUsername2',
            'mqtt_password' => 'password',
            'created_at' => '2026-04-09 18:52:43',
            'updated_at' => now(),
        ]);
    }
}
