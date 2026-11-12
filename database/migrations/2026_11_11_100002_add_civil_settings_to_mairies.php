<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Réglages d'état civil de la mairie, saisis une fois : en-tête de l'extrait (région, cercle, arrondissement,
     * commune, centre), officier signataire (nom, qualité) et localité de naissance proposée par défaut.
     */
    public function up(): void
    {
        Schema::table('mairies', function (Blueprint $table) {
            $table->string('civil_region', 100)->nullable();
            $table->string('civil_cercle', 100)->nullable();
            $table->string('civil_arrondissement', 100)->nullable();
            $table->string('civil_commune', 100)->nullable();
            $table->string('civil_centre_type', 20)->nullable();
            $table->string('civil_centre_name', 100)->nullable();
            $table->string('civil_officer_name', 150)->nullable();
            $table->string('civil_officer_title', 150)->nullable();
            $table->string('civil_birth_place', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mairies', fn (Blueprint $table) => $table->dropColumn([
            'civil_region', 'civil_cercle', 'civil_arrondissement', 'civil_commune', 'civil_centre_type',
            'civil_centre_name', 'civil_officer_name', 'civil_officer_title', 'civil_birth_place',
        ]));
    }
};
