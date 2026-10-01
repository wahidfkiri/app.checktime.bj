<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Responsable rattaché à un poste (une personne : nom complet + fonction).
 */
class Signataire extends Model
{
    protected $table = 'signataires';

    protected $fillable = [
        'client_id',
        'poste_id',
        'full_name',
        'fonction',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function poste(): BelongsTo
    {
        return $this->belongsTo(SignatairePoste::class, 'poste_id');
    }
}
