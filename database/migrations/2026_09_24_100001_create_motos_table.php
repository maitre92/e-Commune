<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('motos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mairie_id')->constrained('mairies')->restrictOnDelete();
            $table->foreignId('proprietaire_id')->constrained('proprietaires')->restrictOnDelete();
            // Numéro de cadre (châssis) : ce que le citoyen donne au guichet, identifiant unique de la moto sur
            // toute la base. Le matricule (plaque) est facultatif : beaucoup de motos n'en ont pas.
            $table->string('chassis_number', 50)->unique();
            $table->string('plate_number', 20)->nullable()->unique();
            $table->string('color', 50);
            $table->string('type_or_brand', 100);
            // Dernière année couverte par une vignette (null : jamais eu de vignette) ; avancée au paiement.
            $table->unsignedSmallInteger('vgt_year')->nullable();
            // Attestation de vente : vendeur et témoin sont chacun un seul enregistrement imbriqué (pas une liste).
            $table->boolean('has_sale_certificate')->default(false);
            $table->string('seller_first_name', 100)->nullable();
            $table->string('seller_last_name', 100)->nullable();
            $table->string('seller_phone', 20)->nullable();
            $table->string('seller_address', 255)->nullable();
            $table->boolean('has_witness')->default(false);
            $table->string('witness_first_name', 100)->nullable();
            $table->string('witness_last_name', 100)->nullable();
            $table->string('witness_phone', 20)->nullable();
            $table->string('witness_address', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('mairie_id');
            $table->index('proprietaire_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('motos');
    }
};
