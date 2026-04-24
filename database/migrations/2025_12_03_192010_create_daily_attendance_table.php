<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('daily_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('arrival_time')->nullable();
            $table->time('departure_time')->nullable();
            $table->integer('total_work_minutes')->default(0);
            $table->integer('late_minutes')->default(0);
            $table->integer('early_leave_minutes')->default(0);
            $table->enum('status', [
                'normal','retard','depart_anticipe',
                'absence','conge','permission'
            ])->default('normal');
            $table->string('observation')->nullable();
            $table->timestamps();
        });
    }

    public function down() {
        Schema::dropIfExists('daily_attendance');
    }
};
