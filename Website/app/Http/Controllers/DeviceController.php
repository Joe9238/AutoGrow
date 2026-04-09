<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function registerDevice(Request $request)
    {
        // This method is intentionally left blank as device registration is handled via the pairing code mechanism.
        return response()->json(['message' => 'Device registration is handled via the pairing code mechanism.'], 400);
    }
}
