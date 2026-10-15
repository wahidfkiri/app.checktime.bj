<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jours fériés, scopés par client (multi-tenant).
     * Unicité composite (client_id, holiday_date) : deux clients peuvent avoir
     * la même date, mais pas de doublon au sein d'un même client.
     */
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('name');
            $table->string('description', 1000)->nullable();
            $table->boolean('is_recurring')->default(false);   // chaque année (même jour/mois)
            $table->boolean('is_working_day')->default(false); // férié mais travaillé ?
            $table->timestamps();

            $table->unique(['client_id', 'holiday_date']);
            $table->index(['client_id', 'is_recurring']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
