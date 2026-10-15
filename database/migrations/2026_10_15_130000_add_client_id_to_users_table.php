<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Rattache chaque compte utilisateur à un client (multi-tenant) et crée le
     * rôle `client-user` (sous-compte restreint).
     *
     * - `users.client_id` nullable (null = super-admin, sans client).
     * - Backfill : les administrateurs principaux (clients.user_id) reçoivent
     *   le client_id correspondant.
     * - Rôle `client-user` : marqueur permettant la connexion et l'accès au
     *   groupe métier, SANS permission de menu — l'accès fin se fait par
     *   permissions directes par utilisateur.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'client_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('client_id')->nullable()->after('id')
                    ->constrained('clients')->nullOnDelete();
            });
        }

        // Backfill depuis clients.user_id (admin principal de chaque client).
        DB::statement('UPDATE users u JOIN clients c ON c.user_id = u.id SET u.client_id = c.id WHERE u.client_id IS NULL');

        // Rôle sous-compte restreint.
        Role::firstOrCreate(['name' => 'client-user', 'guard_name' => 'web']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'client_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['client_id']);
                $table->dropColumn('client_id');
            });
        }
    }
};
