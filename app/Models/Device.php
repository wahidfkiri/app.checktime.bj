<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    protected $fillable = ['client_id','device_sn','ip','alias','terminal_name','area_name','last_sync','last_synced_at'];
 
    protected $casts = [
        'alias' => 'encrypted',
        'terminal_name' => 'encrypted',
        'area_name' => 'encrypted',
        'ip' => 'encrypted',
    ];
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
