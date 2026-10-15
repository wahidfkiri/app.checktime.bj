<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Socle du contrôle d'accès fin par menu (multi-tenant).
 *
 * - Catalogue de permissions GLOBAL (`menu.*`) : une permission = une capacité,
 *   indépendante du client.
 * - Rôle `client` = socle commun : reçoit l'ensemble des permissions de menu
 *   (tout administrateur de client y a accès par défaut). Les restrictions fines
 *   par sous-compte se feront plus tard via des permissions DIRECTES par
 *   utilisateur (isolation naturelle entre clients, sans activer les Teams).
 * - Rôle `super-admin` : n'a besoin d'aucune permission explicite — il satisfait
 *   tout grâce au Gate::before défini dans AuthServiceProvider.
 *
 * Idempotent (firstOrCreate / givePermissionTo).
 */
return new class extends Migration
{
    /**
     * Une permission par entrée de menu. Le libellé décrit l'écran concerné
     * (utile dans un futur formulaire d'attribution des droits).
     */
    public static function menuPermissions(): array
    {
        return [
            // Employés
            'menu.employees'            => 'Employés — Liste des employés',
            'menu.departments'          => 'Employés — Départements',
            'menu.areas'                => 'Employés — Zones',
            // Plannings
            'menu.work-hours'           => 'Plannings — Types d\'horaires',
            'menu.employee-schedules'   => 'Plannings — Plannings employés',
            'menu.schedules'            => 'Plannings — Calendrier',
            // Autorisations
            'menu.employee-permissions' => 'Autorisations — Permissions',
            'menu.missions'             => 'Autorisations — Missions',
            'menu.leaves'               => 'Autorisations — Congés',
            'menu.leave-types'          => 'Autorisations — Types de congés',
            // Pointages / rapports
            'menu.daily-attendance'     => 'Historique des pointages',
            'menu.reports'              => 'Rapports des présences',
            // Référentiels / administration de client
            'menu.holidays'             => 'Jours fériés',
            'menu.devices'              => 'Appareils',
            'menu.users'                => 'Utilisateurs du client',
            'menu.settings'             => 'Paramètres',
            'menu.email-templates'      => 'Paramètres — Modèles d\'e-mails',
            'menu.report-templates'     => 'Paramètres — Modèles de rapports',
            'menu.journal'              => 'Journal d\'activité',
        ];
    }

    public function up(): void
    {
        $names = array_keys(self::menuPermissions());

        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Rôles garantis
        $client = Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        // Socle : le rôle `client` reçoit tout le catalogue de menu.
        $client->givePermissionTo($names);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', array_keys(self::menuPermissions()))
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
