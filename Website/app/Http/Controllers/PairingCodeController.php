<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Models\PairingCode;

class PairingCodeController extends Controller
{
    public function createPairingCode(Request $request)
    {
        $this->deletePairingCode($request); // delete any existing codes for this user
        $code = strtoupper(Str::random(8));

        PairingCode::create([
            'code' => $code,
            'user_id' => Auth::id(),
            'expires_at' => now()->addMinutes(10)
        ]);

        return $code;
    }

    public function deletePairingCode(Request $request)
    {
        $userId = Auth::id();
        PairingCode::where('user_id', $userId)->delete();

        return response()->json([]);
    }
}
