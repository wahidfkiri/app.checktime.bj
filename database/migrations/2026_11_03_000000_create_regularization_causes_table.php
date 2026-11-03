<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Causes de régularisation de pointage (coupure d'électricité, oubli de
     * pointage, …), gérables par client. `is_protected` = cause par défaut
     * non supprimable.
     */
    public function up(): void
    {
        Schema::create('regularization_causes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->boolean('is_protected')->default(false);
            $table->timestamps();

            $table->unique(['client_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regularization_causes');
    }
};
