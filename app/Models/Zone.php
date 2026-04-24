<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    protected $fillable = ['client_id', 'code', 'name','area_id'];
    
    // protected $casts = [
    //     'name' => 'encrypted',
    // ];
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
