<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Models\PairingCode;

class PairingCodeController extends Controller
{
    // ----------------------------
    // Create a new pairing code for the authenticated user
    // ----------------------------
    public function createPairingCode(Request $request)
    {
        $this->deletePairingCodes($request); // delete any existing codes for this user
        $code = strtoupper(Str::random(8)); // ramdom 8 character code, uppercase for better readability

        PairingCode::create([
            'code' => $code,
            'user_id' => Auth::id(),
            'expires_at' => now()->addMinutes(10)
        ]);

        return $code;
    }

    // ----------------------------
    // Delete all pairing codes for the authenticated user
    // ----------------------------
    public function deletePairingCodes(Request $request)
    {
        $userId = Auth::id();
        PairingCode::where('user_id', $userId)->delete();

        return response()->json([]);
    }
}
