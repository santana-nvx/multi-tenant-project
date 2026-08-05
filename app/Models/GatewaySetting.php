<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class GatewaySetting extends Model
{
    use HasUlids;

    protected $fillable = [
        'gateway_name',
        'environment',
        'username',
        'base_url',
        'api_key',
        'api_secret',
        'is_active',
    ];

    protected $casts = [
        'api_secret' => 'encrypted',
        'api_key' => 'encrypted',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'api_secret',
        'api_key',
    ];
}
