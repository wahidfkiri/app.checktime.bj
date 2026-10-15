<?php

namespace App\Models;

use App\Support\CurrentClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jour férié, scopé par client (multi-tenant).
 *
 * Les helpers statiques (isHoliday / isNonWorkingHoliday / getHolidayForDate)
 * se limitent au client courant (App\Support\CurrentClient) afin de rester
 * utilisables tels quels dans les calculs de présence, sans fuite inter-client.
 * Un $clientId explicite peut être fourni (contexte CLI / jobs).
 */
class Holiday extends Model
{
    protected $fillable = [
        'client_id',
        'holiday_date',
        'name',
        'description',
        'is_recurring',
        'is_working_day',
    ];

    protected $casts = [
        'holiday_date'   => 'date',
        'is_recurring'   => 'boolean',
        'is_working_day' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Scope : jours fériés d'un client donné (ou du client courant). */
    public function scopeForClient($query, ?int $clientId = null)
    {
        return $query->where('client_id', $clientId ?? CurrentClient::id());
    }

    public function scopeForYear($query, $year)
    {
        return $query->whereYear('holiday_date', $year);
    }

    /** Condition « cette date est fériée » (date exacte OU récurrent jour/mois). */
    private static function dateMatch($query, $date)
    {
        return $query->where(function ($q) use ($date) {
            $q->where('holiday_date', $date)
              ->orWhere(function ($q2) use ($date) {
                  $q2->where('is_recurring', true)
                     ->whereMonth('holiday_date', date('m', strtotime($date)))
                     ->whereDay('holiday_date', date('d', strtotime($date)));
              });
        });
    }

    public static function isHoliday($date, ?int $clientId = null): bool
    {
        $clientId = $clientId ?? CurrentClient::id();
        if (!$clientId) {
            return false;
        }

        return self::dateMatch(static::where('client_id', $clientId), $date)->exists();
    }

    public static function isNonWorkingHoliday($date, ?int $clientId = null): bool
    {
        $clientId = $clientId ?? CurrentClient::id();
        if (!$clientId) {
            return false;
        }

        return self::dateMatch(
            static::where('client_id', $clientId)->where('is_working_day', false),
            $date
        )->exists();
    }

    public static function getHolidayForDate($date, ?int $clientId = null): ?self
    {
        $clientId = $clientId ?? CurrentClient::id();
        if (!$clientId) {
            return null;
        }

        return self::dateMatch(static::where('client_id', $clientId), $date)->first();
    }
}
