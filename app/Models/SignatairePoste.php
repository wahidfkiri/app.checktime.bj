<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Poste du cartouche de signatures (colonne du tableau final du rapport).
 * Ex : Rédacteur, Vérificateur, Approbateur. Chaque poste a plusieurs responsables.
 */
class SignatairePoste extends Model
{
    protected $table = 'signataire_postes';

    protected $fillable = [
        'client_id',
        'name',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function signataires(): HasMany
    {
        return $this->hasMany(Signataire::class, 'poste_id')->orderBy('position');
    }
}
