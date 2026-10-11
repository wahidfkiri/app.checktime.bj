<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Compte super-admin.
 *
 * Valeurs lues dans .env, jamais inscrites dans le dépôt :
 *   SUPER_ADMIN_NAME
 *   SUPER_ADMIN_EMAIL
 *   SUPER_ADMIN_PASSWORD
 *
 * Idempotent : ne remplace jamais le mot de passe d'un compte existant.
 *
 * Usage : php artisan db:seed --class=SuperAdminSeeder
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL');
        $password = env('SUPER_ADMIN_PASSWORD');

        if (!$email) {
            $this->command?->error('SUPER_ADMIN_EMAIL doit être défini dans .env.');

            return;
        }

        $this->call(RoleSeeder::class);

        $user = User::where('email', $email)->first();

        if (!$user) {
            if (!$password) {
                $this->command?->error('SUPER_ADMIN_PASSWORD est requis pour créer le compte.');

                return;
            }

            $user = User::create([
                'name' => env('SUPER_ADMIN_NAME', 'Super administrateur'),
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);

            $this->command?->info("Compte super-admin créé : {$email}");
        } else {
            $this->command?->warn("Compte déjà présent : {$email} (mot de passe inchangé)");
        }

        if (!$user->hasRole('super-admin')) {
            $user->assignRole('super-admin');
        }

        // Un super-admin n'est rattaché à aucun client : il les choisit.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Rôle super-admin attribué.');
    }
}
