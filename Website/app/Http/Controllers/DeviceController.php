<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Device;
use App\Models\PairingCode;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Jobs\UpdateWeatherForDevices;
use PhpMqtt\Client\Facades\MQTT;


class DeviceController extends Controller
{
    // ----------------------------
    // Register a device using pairing code
    // ----------------------------
    public function register(Request $request)
    {
        $request->validate([
            'uid'  => 'required|string',
            'code' => 'required|string'
        ]);

        $uid  = strtoupper($request->input('uid'));
        $code = strtoupper($request->input('code'));

        // Check pairing code
        $pair = PairingCode::where('code', $code)
            ->where('expires_at', '>', now()) // make sure pairing code is not expired
            ->first();

        if (!$pair) { // no valid pairing code found
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired pairing code'
            ], 400);
        }

        // Check if device already exists
        $device = Device::where('device_uid', $uid)->first();

        if (!$device) {
            $device = Device::create([
                'device_uid'     => $uid,
                'user_id'        => $pair->user_id,
                'name'           => 'AutoGrow Device',
                'mqtt_username'  => env('DEVICE_MQTT_USERNAME', ''),
                'mqtt_password'  => env('DEVICE_MQTT_PASSWORD', ''),
                'yellow_threshold' => env('DEFAULT_YELLOW_THRESHOLD', 50),
                'red_threshold' => env('DEFAULT_RED_THRESHOLD', 30),
            ]);
        } else {
            // If device exists but not assigned, assign it
            if (!$device->user_id || $device->user_id !== $pair->user_id) {
                $device->user_id = $pair->user_id;
                $device->save();
            }
        }

        // Delete used pairing code
        // Standards expect that the function is moved to a trait but doing this is easier and sufficiently clear
        app('App\Http\Controllers\PairingCodeController')->deletePairingCodes(request());

        // Return MQTT config
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


    // ----------------------------
    // Update device postcode, coordinates, and then weather info for the device
    // ----------------------------
    public function updatePostcode(Request $request)
    {
        $request->validate([
            'device_uid' => 'required|exists:devices,device_uid',
            'postcode' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $device = Device::where('device_uid', $request->device_uid)
            ->where('user_id', Auth::id()) // prevents users from updating devices that aren't theirs
            ->firstOrFail();

        $device->postcode = $request->postcode;
        $device->latitude = $request->latitude;
        $device->longitude = $request->longitude;
        $device->save();

        // Update weather for this device
        $this->updateWeatherForDevice($device);
        return redirect()->back()->with('success', 'Postcode updated!');
    }

    // ----------------------------
    // Update device yellow/red thresholds
    // ----------------------------
    public function updateThresholds(Request $request)
    {
        $request->validate([
            'device_uid' => 'required|exists:devices,device_uid',
            'yellow_threshold' => 'required|integer|min:0|max:100',
            'red_threshold' => 'required|integer|min:0|max:100',
        ]);

        if ($request->red_threshold > $request->yellow_threshold) {
            return back()->withErrors(['red_threshold' => 'Red threshold cannot be above yellow threshold.']);
        }

        $device = Device::where('device_uid', $request->device_uid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $device->yellow_threshold = $request->yellow_threshold;
        $device->red_threshold = $request->red_threshold;
        $device->save();

        // Update weather for this device
        $this->updateWeatherForDevice($device);
        return back()->with('success', 'Thresholds updated!');
    }
    
    // ----------------------------
    // Update weather for a single device 
    // ----------------------------
    private function updateWeatherForDevice($device)
    {
        // Create and run a single job to update weather for this device instead of waiting for the scheduled job to run
        $job = new class($device) extends UpdateWeatherForDevices { // create a new class that extends UpdateWeatherForDevices so we can reuse the checkRain function
            public function __construct($device) { $this->device = $device; } 
            public function handle(): void { // override the handle function to only update weather for the single device passed in the constructor
                $rainToday = $this->checkRain($this->device->latitude, $this->device->longitude);
                MQTT::publish("device/{$this->device->device_uid}/config", json_encode([
                    'rain_today' => $rainToday,
                    'yellow_threshold' => $this->device->yellow_threshold,
                    'red_threshold' => $this->device->red_threshold,
                ]));
            }
        };
        $job->handle(); 
    }
    
    // ----------------------------
    // Rename device by device_uid 
    // ----------------------------
    public function renameDevice(Request $request)
    {
        $request->validate([
            'device_uid' => 'required|exists:devices,device_uid',
            'name' => 'required|string|max:255',
        ]);
        $device = Device::where('device_uid', $request->device_uid)
            ->where('user_id', Auth::id()) // prevents users from renaming devices that aren't theirs
            ->firstOrFail(); 
        $device->name = $request->name;
        $device->save();
        return redirect()->back()->with('success', 'Device renamed!');
    }

    // ----------------------------
    // Delete device by device_uid
    // ----------------------------
    public function deleteDevice(Request $request)
    {
        $request->validate([
            'device_uid' => 'required|exists:devices,device_uid',
        ]);
        $device = Device::where('device_uid', $request->device_uid)
            ->where('user_id', Auth::id()) // prevents users from deleting devices that aren't theirs
            ->firstOrFail();
        $device->delete();
        return redirect('/dashboard'); // reload dashboard to update device list and exit the deleted device's page 
    }
}
