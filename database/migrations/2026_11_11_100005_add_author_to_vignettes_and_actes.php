<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auteur de chaque vignette (l'agent qui l'a délivrée) et de chaque acte (l'agent qui l'a saisi) : un agent ne
     * voit que son propre travail ; le maire, le secrétaire général et le caissier voient tout.
     */
    public function up(): void
    {
        Schema::table('demandes_vgt', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('mairie_id')->constrained('users')->restrictOnDelete();
        });
        Schema::table('actes_naissance', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('mairie_id')->constrained('users')->restrictOnDelete();
        });

        // Vignettes déjà délivrées : l'auteur est l'agent qui a encaissé (recette de la caisse).
        foreach (DB::table('recettes')->where('subject_type', 'App\\Models\\DemandeVgt')->get(['subject_id', 'user_id']) as $recette) {
            DB::table('demandes_vgt')->where('id', $recette->subject_id)->whereNull('user_id')->update(['user_id' => $recette->user_id]);
        }
    }

    public function down(): void
    {
        Schema::table('actes_naissance', fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
        Schema::table('demandes_vgt', fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
    }
};
