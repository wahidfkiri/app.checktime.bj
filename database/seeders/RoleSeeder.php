<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rôles de l'application.
 *
 *   super-admin : administre toutes les fonctionnalités, tous clients
 *                 confondus. Il travaille dans le contexte d'un client
 *                 qu'il sélectionne (voir App\Support\CurrentClient).
 *   client      : administrateur d'un client (comportement historique).
 *
 * Idempotent.
 */
class RoleSeeder extends Seeder
{
    public const ROLES = [
        'super-admin' => 'Administrateur général',
        'client' => 'Administrateur du client',
    ];

    public function run(): void
    {
        foreach (array_keys(self::ROLES) as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Rôles disponibles : ' . implode(', ', array_keys(self::ROLES)));
    }
}
