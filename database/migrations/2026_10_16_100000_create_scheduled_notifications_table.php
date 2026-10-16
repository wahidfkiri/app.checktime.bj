<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tâches planifiées (rapports / sync) configurables PAR CLIENT.
     * Le planificateur (Kernel) itère sur les tâches actives et exécute la
     * commande associée avec --client={client_id}.
     */
    public function up(): void
    {
        Schema::create('scheduled_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('command');                 // ex: attendance:send-weekly-rh-reports
            $table->string('label');
            $table->string('description')->nullable();
            $table->boolean('supports_recipients')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('frequency')->default('weekly'); // daily|weekly|monthly|custom|once
            $table->string('time', 5)->default('09:00');    // HH:MM
            $table->unsignedTinyInteger('day_of_week')->nullable();  // 1..7 (ISO)
            $table->unsignedTinyInteger('day_of_month')->nullable(); // 1..31
            $table->string('cron_expression', 100)->nullable();
            $table->dateTime('run_at')->nullable();         // fréquence "once"
            $table->json('recipients')->nullable();
            $table->dateTime('last_run_at')->nullable();
            $table->string('last_status')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'is_active']);
            $table->unique(['client_id', 'command']); // une config par commande et par client
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_notifications');
    }
};
