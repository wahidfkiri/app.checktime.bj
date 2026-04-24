<?php
// database/migrations/YYYY_MM_DD_HHMMSS_create_settings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('email');
            $table->boolean('email_employees_is_active')->default(true);
            $table->boolean('email_is_active')->default(true);
            $table->boolean('sms_is_active')->default(true);
            $table->integer('sms_credit')->default(0);
            $table->timestamps();
            
            // Ensure one setting per client
            $table->unique('client_id');
            
            // Index for email lookup
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};