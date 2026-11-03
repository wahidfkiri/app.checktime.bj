<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal d'une régularisation de pointage appliquée à un employé pour une
 * date donnée (coupure d'électricité, oubli de pointage…). L'application écrit
 * la ligne daily_attendances correspondante, lue par tous les rapports.
 */
class AttendanceRegularization extends Model
{
    protected $fillable = [
        'client_id', 'cause_id', 'employee_id', 'date', 'check_in', 'check_out', 'created_by',
    ];

    protected $casts = ['date' => 'date'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function cause(): BelongsTo
    {
        return $this->belongsTo(RegularizationCause::class, 'cause_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
