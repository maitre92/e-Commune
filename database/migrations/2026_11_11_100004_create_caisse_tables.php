<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Caisse de la mairie. Une recette par encaissement (vignette vendue, extrait payant), numérotée par mairie et
     * par année (quittance), au nom de l'agent qui a encaissé. Un versement regroupe les recettes qu'un agent remet
     * au caissier (ou au maire) ; une recette versée ne bouge plus.
     */
    public function up(): void
    {
        Schema::table('mairies', function (Blueprint $table) {
            // Prix de l'extrait d'acte de naissance, fixé par le maire (0 : gratuit).
            $table->unsignedInteger('civil_extract_price')->default(0);
        });

        Schema::create('versements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mairie_id')->constrained('mairies')->restrictOnDelete();
            $table->foreignId('agent_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->unsignedInteger('recettes_count');
            $table->dateTime('received_at');
            $table->timestamps();

            $table->index(['mairie_id', 'received_at']);
        });

        Schema::create('recettes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mairie_id')->constrained('mairies')->restrictOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->unsignedInteger('numero');
            // Pas d'enum SQL : « vignette » ou « extrait » (App\Enums\RecetteType).
            $table->string('type', 20);
            $table->unsignedInteger('amount');
            $table->string('label', 255);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('versement_id')->nullable()->constrained('versements')->restrictOnDelete();
            $table->dateTime('encaissee_at');
            $table->timestamps();

            $table->unique(['mairie_id', 'annee', 'numero']);
            $table->index(['mairie_id', 'encaissee_at']);
            $table->index(['user_id', 'versement_id']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recettes');
        Schema::dropIfExists('versements');
        Schema::table('mairies', fn (Blueprint $table) => $table->dropColumn('civil_extract_price'));
    }
};
