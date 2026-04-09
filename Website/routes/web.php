<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\PairingCodeController;
use Symfony\Component\HttpFoundation\Request;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function (Request $request) {
    // use this later when requesting graphs from a particular device
    $deviceUid = $request->query('device'); // this works even though the error check says its wrong


    return Inertia::render('Dashboard', [
        'user' => Auth::user(),
        'devices' => Auth::user()->devices()->get()->map(function ($device) {
            return [
                'name' => $device->name,
                'device_uid' => $device->device_uid,
                'created_at' => $device->created_at->toDateTimeString(),
            ];
        }),
        'pairingCode' => null
    ]);
})->middleware(['auth'/*, 'verified'*/])->name('dashboard');

Route::get('/pairing-code', function (PairingCodeController $controller) {
    $pairingCode = $controller->createPairingCode(request());
    return Inertia::render('Dashboard', [
        'user' => Auth::user(),
        'devices' => Auth::user()->devices()->get()->map(function ($device) {
            return [
                'name' => $device->name,
                'device_uid' => $device->device_uid,
                'created_at' => $device->created_at->toDateTimeString(),
            ];
        }),
        'pairingCode' => $pairingCode,
    ]);
})->middleware('auth');


require __DIR__.'/settings.php';
