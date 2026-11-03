<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cause d'une régularisation de pointage (coupure d'électricité, oubli de
 * pointage, ou cause personnalisée), scopée par client.
 */
class RegularizationCause extends Model
{
    protected $fillable = ['client_id', 'label', 'is_protected'];

    protected $casts = ['is_protected' => 'boolean'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function regularizations(): HasMany
    {
        return $this->hasMany(AttendanceRegularization::class, 'cause_id');
    }
}
