<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'raison_sociale',
        'sigle',
        'rccm',
        'ifu',
        'directeur',
        'email',
        'telephone',
        'adresse',
        'logo',
        'user_id',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'raison_sociale' => 'encrypted',
        'sigle' => 'encrypted',
        'rccm' => 'encrypted',
        'ifu' => 'encrypted',
        'directeur' => 'encrypted',
        'email' => 'encrypted',
        'telephone' => 'encrypted',
        'adresse' => 'encrypted',
    ];

    // =================== RELATIONS EXISTANTES ===================
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function users()
    {
        return $this->hasMany(ClientUser::class);
    }

    public function zones()
    {
        return $this->hasMany(Zone::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function accessConfigs()
    {
        return $this->hasMany(AccessConfig::class);
    }

    // =================== NOUVELLES RELATIONS ===================
    
    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function dailyAttendances()
    {
        return $this->hasMany(DailyAttendance::class);
    }

    public function workHourTypes()
{
    return $this->hasMany(WorkHourType::class);
}

public function employeeSchedules()
{
    return $this->hasMany(EmployeeSchedule::class);
}

public function scheduleRotations()
{
    return $this->hasMany(ScheduleRotation::class);
}

public function holidays()
{
    return $this->hasMany(Holiday::class);
}

    public function setting()
    {
        return $this->hasOne(Setting::class);
    }

public function dailyPlannings()
{
    return $this->hasMany(DailyPlanning::class);
}

    public function realTimeLogs()
    {
        return $this->hasMany(RealTimeLog::class);
    }

    public function leaveTypes()
    {
        return $this->hasMany(LeaveType::class);
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }

    public function reportSettings()
    {
        return $this->hasMany(ReportSetting::class);
    }

    public function emailLogs()
    {
        return $this->hasMany(EmailLog::class);
    }

    public function smsLogs()
    {
        return $this->hasMany(SmsLog::class);
    }

    // =================== SCOPES POUR DATATABLE ===================
    
    /**
     * Scope pour les clients actifs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope pour les clients inactifs
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope pour la recherche globale
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('raison_sociale', 'like', "%{$search}%")
              ->orWhere('sigle', 'like', "%{$search}%")
              ->orWhere('rccm', 'like', "%{$search}%")
              ->orWhere('ifu', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('directeur', 'like', "%{$search}%")
              ->orWhere('telephone', 'like', "%{$search}%")
              ->orWhere('adresse', 'like', "%{$search}%")
              ->orWhere('ville', 'like', "%{$search}%");
        });
    }

    /**
     * Scope pour trier par raison sociale
     */
    public function scopeOrderByRaisonSociale($query, $direction = 'asc')
    {
        return $query->orderBy('raison_sociale', $direction);
    }

    /**
     * Scope pour trier par date de création
     */
    public function scopeOrderByCreatedAt($query, $direction = 'desc')
    {
        return $query->orderBy('created_at', $direction);
    }

    /**
     * Scope pour les clients créés après une date
     */
    public function scopeCreatedAfter($query, $date)
    {
        return $query->where('created_at', '>=', $date);
    }

    /**
     * Scope pour les clients créés avant une date
     */
    public function scopeCreatedBefore($query, $date)
    {
        return $query->where('created_at', '<=', $date);
    }

    // =================== ACCESSORS POUR DATATABLE ===================
    
    /**
     * Nom complet avec sigle
     */
    public function getNomCompletAttribute()
    {
        $nom = $this->raison_sociale;
        if ($this->sigle) {
            $nom .= " ({$this->sigle})";
        }
        return $nom;
    }

    /**
     * Statut textuel
     */
    public function getStatusAttribute()
    {
        return $this->is_active ? 'Actif' : 'Inactif';
    }

    /**
     * Badge HTML pour le statut
     */
    public function getStatusBadgeAttribute()
    {
        if ($this->is_active) {
            return '<span class="badge bg-success">Actif</span>';
        } else {
            return '<span class="badge bg-danger">Inactif</span>';
        }
    }

    /**
     * Date de création formatée
     */
    public function getDateCreationFormattedAttribute()
    {
        return $this->created_at ? $this->created_at->format('d/m/Y') : '-';
    }

    /**
     * Date de création avec heure
     */
    public function getDateCreationCompleteAttribute()
    {
        return $this->created_at ? $this->created_at->format('d/m/Y H:i') : '-';
    }

    /**
     * RCCM formaté en majuscules
     */
    public function getRccmFormattedAttribute()
    {
        return strtoupper($this->rccm);
    }

    /**
     * Téléphone formaté
     */
    public function getTelephoneFormattedAttribute()
    {
        if (empty($this->telephone)) {
            return '-';
        }
        
        $phone = preg_replace('/[^0-9]/', '', $this->telephone);
        
        if (strlen($phone) === 8) {
            return substr($phone, 0, 2) . ' ' . substr($phone, 2, 2) . ' ' . 
                   substr($phone, 4, 2) . ' ' . substr($phone, 6, 2);
        }
        
        return $this->telephone;
    }

    /**
     * Email avec lien mailto
     */
    public function getEmailLinkedAttribute()
    {
        if (empty($this->email)) {
            return '-';
        }
        return '<a href="mailto:' . $this->email . '">' . $this->email . '</a>';
    }

    /**
     * Nombre d'employés
     */
    public function getNombreEmployesAttribute()
    {
        return $this->employees()->count();
    }

    /**
     * Nombre d'utilisateurs
     */
    public function getNombreUtilisateursAttribute()
    {
        return $this->users()->count();
    }

    // =================== MÉTHODES UTILITAIRES ===================
    
    /**
     * Activer le client
     */
    public function activate()
    {
        $this->update(['is_active' => true]);
        return $this;
    }

    /**
     * Désactiver le client
     */
    public function deactivate()
    {
        $this->update(['is_active' => false]);
        return $this;
    }

    /**
     * Changer le statut
     */
    public function toggleStatus()
    {
        $this->update(['is_active' => !$this->is_active]);
        return $this;
    }

    /**
     * Vérifier si le client est actif
     */
    public function isActive()
    {
        return $this->is_active === true;
    }

    /**
     * Vérifier si le client a des employés
     */
    public function hasEmployees()
    {
        return $this->employees()->exists();
    }

    /**
     * Vérifier si le client a des utilisateurs
     */
    public function hasUsers()
    {
        return $this->users()->exists();
    }

    /**
     * Obtenir l'adresse complète
     */
    public function getAdresseCompleteAttribute()
    {
        $adresse = $this->adresse ?? '';
        if ($this->ville) {
            $adresse .= $adresse ? ', ' . $this->ville : $this->ville;
        }
        return $adresse ?: '-';
    }

    // =================== MÉTHODES POUR EXPORT ===================
    
    /**
     * Données pour l'export CSV/Excel
     */
    public function toExportArray()
    {
        return [
            'ID' => $this->id,
            'Raison Sociale' => $this->raison_sociale,
            'Sigle' => $this->sigle ?? '-',
            'RCCM' => $this->rccm,
            'IFU' => $this->ifu ?? '-',
            'Directeur' => $this->directeur ?? '-',
            'Email' => $this->email,
            'Téléphone' => $this->telephone ?? '-',
            'Adresse' => $this->adresse ?? '-',
            'Ville' => $this->ville ?? '-',
            'Statut' => $this->status,
            'Date Création' => $this->date_creation_formatted,
            'Nombre Employés' => $this->nombre_employes,
            'Nombre Utilisateurs' => $this->nombre_utilisateurs,
        ];
    }

    /**
     * En-têtes pour l'export
     */
    public static function getExportHeaders()
    {
        return [
            'ID',
            'Raison Sociale',
            'Sigle',
            'RCCM',
            'IFU',
            'Directeur',
            'Email',
            'Téléphone',
            'Adresse',
            'Ville',
            'Statut',
            'Date Création',
            'Nombre Employés',
            'Nombre Utilisateurs',
        ];
    }

    // =================== VALIDATION RULES ===================
    
    /**
     * Règles de validation pour la création
     */
    public static function createRules()
    {
        return [
            'raison_sociale' => 'required|string|max:255',
            'sigle' => 'nullable|string|max:50',
            'rccm' => 'required|string|max:255|unique:clients',
            'ifu' => 'nullable|string|max:255',
            'directeur' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:clients',
            'telephone' => 'nullable|string|max:20',
            'adresse' => 'nullable|string|max:500',
            'ville' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Règles de validation pour la mise à jour
     */
    public static function updateRules($clientId)
    {
        return [
            'raison_sociale' => 'required|string|max:255',
            'sigle' => 'nullable|string|max:50',
            'rccm' => 'required|string|max:255|unique:clients,rccm,' . $clientId,
            'ifu' => 'nullable|string|max:255',
            'directeur' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:clients,email,' . $clientId,
            'telephone' => 'nullable|string|max:20',
            'adresse' => 'nullable|string|max:500',
            'ville' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ];
    }

    // =================== MÉTHODES STATIQUES ===================
    
    /**
     * Obtenir les statistiques des clients
     */
    public static function getStats()
    {
        return [
            'total' => self::count(),
            'actifs' => self::active()->count(),
            'inactifs' => self::inactive()->count(),
            'avec_employes' => self::whereHas('employees')->count(),
            'sans_employes' => self::whereDoesntHave('employees')->count(),
            'moyenne_employes' => self::has('employees')->withCount('employees')->get()->avg('employees_count'),
        ];
    }

    /**
     * Recherche rapide pour autocomplete
     */
    public static function searchForAutocomplete($search)
    {
        return self::select('id', 'raison_sociale', 'sigle', 'rccm', 'email')
            ->where('raison_sociale', 'like', "%{$search}%")
            ->orWhere('sigle', 'like', "%{$search}%")
            ->orWhere('rccm', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%")
            ->active()
            ->limit(10)
            ->get()
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'text' => $client->raison_sociale . ($client->sigle ? " ({$client->sigle})" : ''),
                    'rccm' => $client->rccm,
                    'email' => $client->email,
                ];
            });
    }
}