<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le modèle de carte de l'année devient propre à chaque mairie (choisi par son maire) : toujours un seul modèle
 * par année et par mairie, figé dès sa première vignette de l'année. Les modèles déjà réglés pour toutes les
 * mairies sont recopiés pour chacune.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vignette_years', function (Blueprint $table) {
            $table->foreignId('mairie_id')->nullable()->after('id')->constrained('mairies')->restrictOnDelete();
            $table->dropUnique(['year']);
            $table->unique(['mairie_id', 'year']);
        });

        $now = now();
        $global = DB::table('vignette_years')->whereNull('mairie_id')->get();
        foreach (DB::table('mairies')->pluck('id') as $mairieId) {
            foreach ($global as $row) {
                DB::table('vignette_years')->insert(['mairie_id' => $mairieId, 'year' => $row->year, 'card_template' => $row->card_template, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        DB::table('vignette_years')->whereNull('mairie_id')->delete();
    }

    public function down(): void
    {
        DB::table('vignette_years')->delete();
        Schema::table('vignette_years', function (Blueprint $table) {
            $table->dropUnique(['mairie_id', 'year']);
            $table->dropConstrainedForeignId('mairie_id');
            $table->unique('year');
        });
    }
};
