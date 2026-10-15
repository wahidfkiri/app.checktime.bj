<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute `is_active` à leave_types (le modèle LeaveType l'attend déjà dans
     * $fillable/$casts, mais la colonne n'existait pas). La table est déjà
     * scopée par client_id.
     */
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            if (!Schema::hasColumn('leave_types', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            if (Schema::hasColumn('leave_types', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
