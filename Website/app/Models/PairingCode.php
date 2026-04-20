<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class PairingCode extends Model
{

    protected $table = 'pairing_codes';
    protected $fillable = ['code', 'user_id', 'expires_at'];
    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
