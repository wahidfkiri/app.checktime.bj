<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tâche planifiée (rapport / sync) d'un client. Le Kernel construit l'expression
 * cron et exécute la commande avec --client={client_id}.
 */
class ScheduledNotification extends Model
{
    protected $fillable = [
        'client_id',
        'command',
        'label',
        'description',
        'supports_recipients',
        'is_active',
        'frequency',
        'time',
        'day_of_week',
        'day_of_month',
        'cron_expression',
        'run_at',
        'recipients',
        'last_run_at',
        'last_status',
    ];

    protected $casts = [
        'supports_recipients' => 'boolean',
        'is_active'           => 'boolean',
        'recipients'          => 'array',
        'day_of_week'         => 'integer',
        'day_of_month'        => 'integer',
        'run_at'              => 'datetime',
        'last_run_at'         => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Définitions des tâches par défaut proposées à chaque client. */
    public static function defaultDefinitions(): array
    {
        return [
            [
                'command' => 'attendance:send-weekly-reports',
                'label' => 'Rapport de présence hebdomadaire (employés)',
                'frequency' => 'weekly', 'day_of_week' => 6, 'time' => '09:00',
                'supports_recipients' => false,
            ],
            [
                'command' => 'attendance:send-weekly-rh-reports',
                'label' => 'Rapport RH hebdomadaire',
                'frequency' => 'weekly', 'day_of_week' => 6, 'time' => '09:00',
                'supports_recipients' => true,
            ],
            [
                'command' => 'reports:send-monthly-rh',
                'label' => 'Rapport RH mensuel',
                'frequency' => 'monthly', 'day_of_month' => 31, 'time' => '09:00',
                'supports_recipients' => true,
            ],
            [
                'command' => 'attendance:send-weekly-sms',
                'label' => 'Récapitulatif SMS hebdomadaire',
                'frequency' => 'weekly', 'day_of_week' => 6, 'time' => '09:00',
                'supports_recipients' => false,
            ],
        ];
    }

    /** Crée les tâches par défaut manquantes pour un client (idempotent). */
    public static function ensureDefaultsForClient(int $clientId): void
    {
        foreach (self::defaultDefinitions() as $def) {
            static::firstOrCreate(
                ['client_id' => $clientId, 'command' => $def['command']],
                array_merge($def, ['is_active' => true])
            );
        }
    }

    /** Construit l'expression cron correspondant à la configuration. */
    public function cronExpression(): string
    {
        if ($this->frequency === 'custom' && !empty($this->cron_expression)) {
            return trim($this->cron_expression);
        }

        if ($this->frequency === 'once') {
            if (!$this->run_at) {
                return '0 0 31 2 *'; // 31 février : ne se déclenche jamais
            }
            return sprintf('%d %d %d %d *', $this->run_at->minute, $this->run_at->hour, $this->run_at->day, $this->run_at->month);
        }

        [$hour, $minute] = array_pad(explode(':', $this->time ?: '09:00'), 2, '00');
        $hour = (int) $hour;
        $minute = (int) $minute;

        switch ($this->frequency) {
            case 'daily':
                return "{$minute} {$hour} * * *";

            case 'monthly':
                $dom = $this->day_of_month ?: 1;
                $domField = $dom >= 29 ? '28-31' : (string) $dom;
                return "{$minute} {$hour} {$domField} * *";

            case 'weekly':
            default:
                $iso = $this->day_of_week ?: 1;
                $cron = $iso === 7 ? 0 : $iso;
                return "{$minute} {$hour} * * {$cron}";
        }
    }

    /** Pour une tâche mensuelle : le jour configuré est-il dû aujourd'hui ? */
    public function isMonthlyDayDueToday(\Carbon\Carbon $now): bool
    {
        $configured = $this->day_of_month ?: 1;
        $effectiveDom = min($configured, $now->daysInMonth);

        return $now->day === $effectiveDom;
    }

    /** Libellé lisible de la planification (UI). */
    public function scheduleLabel(): string
    {
        if ($this->frequency === 'custom') {
            return 'Cron : ' . ($this->cron_expression ?: '—');
        }
        if ($this->frequency === 'once') {
            return $this->run_at ? 'Le ' . $this->run_at->format('d/m/Y à H:i') . ' (une fois)' : 'Date non définie';
        }

        $days = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

        switch ($this->frequency) {
            case 'daily':
                return "Tous les jours à {$this->time}";
            case 'monthly':
                $dom = $this->day_of_month ?: 1;
                return $dom >= 29 ? "Le dernier jour de chaque mois à {$this->time}" : "Le {$dom} de chaque mois à {$this->time}";
            case 'weekly':
            default:
                $day = $days[$this->day_of_week] ?? 'Lundi';
                return "Chaque {$day} à {$this->time}";
        }
    }
}
