<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('devices', function (Blueprint $table) {
            // Date de la dernière synchronisation réelle (moment où l'application
            // a récupéré/enregistré les données), distincte de last_sync qui
            // contient la dernière activité de l'appareil.
            $table->dateTime('last_synced_at')->nullable()->after('last_sync');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('last_synced_at');
        });
    }
};
