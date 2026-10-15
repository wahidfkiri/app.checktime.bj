<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crée (ou met à jour) le compte administrateur « Wahid Fkiri ».
 *
 * Dans ce projet, le rôle administrateur est "super-admin" (routes /admin,
 * middleware role:super-admin) : c'est lui qui est attribué ici. Il n'existe
 * pas de rôle nommé "admin".
 *
 * Idempotent. Usage :
 *   php artisan db:seed --class=AdminUserSeeder
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // S'assurer que le rôle existe (au cas où RoleSeeder n'a pas été joué).
        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $user = User::updateOrCreate(
            ['email' => 'wahidfkiri5@gmail.com'],
            [
                'name' => 'Wahid Fkiri',
                'password' => Hash::make('12345678'),
                'email_verified_at' => now(),
            ]
        );

        if (!$user->hasRole($role->name)) {
            $user->assignRole($role->name);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Compte administrateur prêt : wahidfkiri5@gmail.com (rôle super-admin).');
    }
}
