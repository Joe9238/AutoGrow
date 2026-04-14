<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeviceController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/device/register', [DeviceController::class, 'register']);

Route::get('/health', function () {
    return response()->json(['current time' => now()->toDateTimeString()]);
});