<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use app\Models\Device;
use App\Models\PairingCode;
use Illuminate\Support\Str;

class DeviceController extends Controller
{
    /**
     * Register a device using pairing code
     */
    public function register(Request $request)
    {
        $request->validate([
            'uid'  => 'required|string',
            'code' => 'required|string'
        ]);

        $uid  = strtoupper($request->input('uid'));
        $code = strtoupper($request->input('code'));

        // ----------------------------
        // Check pairing code
        // ----------------------------
        $pair = PairingCode::where('code', $code)
            ->where('expires_at', '>', now())
            ->first();

        if (!$pair) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired pairing code'
            ], 400);
        }

        // ----------------------------
        // Check if device already exists
        // ----------------------------
        $device = Device::where('device_uid', $uid)->first();

        if (!$device) {
            $device = Device::create([
                'device_uid'     => $uid,
                'user_id'        => $pair->user_id,
                'name'           => 'AutoGrow Device',
                'mqtt_username'  => 'dev_' . Str::random(8),
                'mqtt_password'  => Str::random(16),
            ]);
        } else {
            // If device exists but not assigned, assign it
            if (!$device->user_id) {
                $device->user_id = $pair->user_id;
                $device->save();
            }
        }

        // Delete used pairing code
        app('App\Http\Controllers\PairingCodeController')->deletePairingCodes(request());

        // ----------------------------
        // Return MQTT config
        // ----------------------------
        return response()->json([
            'success' => true,
            'mqtt' => [
                'host' => env('MQTT_HOST'),
                'port' => env('MQTT_PORT', 1883),
                'username' => $device->mqtt_username,
                'password' => $device->mqtt_password,
                'topic_base' => "device/{$device->device_uid}"
            ]
        ]);
    }

    /**
     * Get device info 
     */
    public function show(Device $device)
    {
        return response()->json([
            'id' => $device->id,
            'name' => $device->name,
            'uid' => $device->device_uid,
            'created_at' => $device->created_at,
        ]);
    }

    /**
     * Rename device
     */
    public function update(Request $request, Device $device)
    {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $device->name = $request->name;
        $device->save();

        return response()->json([
            'success' => true,
            'name' => $device->name
        ]);
    }

    /**
     * Delete device
     */
    public function destroy(Device $device)
    {
        $device->delete();

        return response()->json([
            'success' => true
        ]);
    }
}
