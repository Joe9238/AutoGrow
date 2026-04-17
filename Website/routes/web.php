<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\PairingCodeController;
use Symfony\Component\HttpFoundation\Request;
use App\Models\Device;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\NotificationController;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function (Request $request) {
    // get device from query string and fetch last 100readings for that device
    $deviceUid = $request->query('device'); // this works even though the error check says its wrong
    $device = Device::where('device_uid', $deviceUid)->first();
    $deviceReadings = $device ? $device->readings()->orderBy('recorded_at', 'desc')->limit(100)->get() : collect();
    $cleanDeviceReadings = $deviceReadings->map(function ($reading) {
        return [
            'moisture' => $reading->moisture,
            'temperature' => $reading->temperature,
            'recorded_at' => $reading->recorded_at->toDateTimeString(),
        ];
    });

    return Inertia::render('Dashboard', [
        'user' => Auth::user(),
        'devices' => Auth::user()->devices()->orderBy('name')->get()->map(function ($device) {
            return [
                'name' => $device->name,
                'device_uid' => $device->device_uid,
                'created_at' => $device->created_at->toDateTimeString(),
                'postcode' => $device->postcode,
                'latitude' => $device->latitude,
                'longitude' => $device->longitude,
                'yellow_threshold' => $device->yellow_threshold,
                'red_threshold' => $device->red_threshold,
            ];
        }),
        'pairingCode' => null,
        'deviceReadings' => $cleanDeviceReadings,
    ]);
})->middleware(['auth'/*, 'verified'*/])->name('dashboard');

Route::get('/pairing-code', function (PairingCodeController $controller, Request $request) {
    $pairingCode = $controller->createPairingCode(request());
    return Inertia::render('Dashboard', [
        'user' => Auth::user(),
        'devices' => Auth::user()->devices()->orderBy('name')->get()->map(function ($device) {
            return [
                'name' => $device->name,
                'device_uid' => $device->device_uid,
                'created_at' => $device->created_at->toDateTimeString(),
            ];
        }),
        'pairingCode' => $pairingCode,
        'deviceReadings' => null,
    ]);
})->middleware('auth');



Route::post('/devices/update-postcode', [DeviceController::class, 'updatePostcode'])->middleware('auth');
Route::post('/devices/update-thresholds', [DeviceController::class, 'updateThresholds'])->middleware('auth');
Route::post('/devices/delete', [DeviceController::class, 'deleteDevice'])->middleware('auth');
Route::post('/devices/rename', [DeviceController::class, 'renameDevice'])->middleware('auth');

Route::get('/notifications/list', [NotificationController::class, 'list'])->middleware('auth');
Route::post('/notifications/delete/{id}', [NotificationController::class, 'delete'])->middleware('auth');


require __DIR__.'/settings.php';
