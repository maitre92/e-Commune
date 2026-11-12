<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guichet vignette simplifié : seul le numéro de châssis (CH) est indispensable. Le propriétaire, le genre et
     * la couleur d'une moto deviennent facultatifs ; le nom du détenteur se note (facultatif) sur la vignette.
     * Un seul modèle de carte par année (`vignette_years`), figé dès la première vignette de l'année.
     */
    public function up(): void
    {
        Schema::table('motos', function (Blueprint $table) {
            $table->foreignId('proprietaire_id')->nullable()->change();
            $table->string('type_or_brand', 100)->nullable()->change();
            $table->string('color', 50)->nullable()->change();
        });

        Schema::table('demandes_vgt', function (Blueprint $table) {
            $table->string('holder_name', 150)->nullable()->after('vgt_year');
        });

        Schema::create('vignette_years', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            // App\Enums\VgtCardTemplate : le même modèle pour toutes les vignettes de l'année.
            $table->string('card_template', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vignette_years');
        Schema::table('demandes_vgt', fn (Blueprint $table) => $table->dropColumn('holder_name'));
    }
};
