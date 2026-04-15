<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SensorReadings extends Model
{
    protected $table = 'sensor_readings';
    public $timestamps = false;

    protected $fillable = [
        'device_id',
        'moisture',
        'temperature',
        'recorded_at',
    ];

    protected $casts = [
        'moisture' => 'float',
        'temperature' => 'float',
        'recorded_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
