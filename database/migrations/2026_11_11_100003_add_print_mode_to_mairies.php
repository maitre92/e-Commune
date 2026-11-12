<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Impression des extraits : sur feuille blanche (modèle complet) ou sur le formulaire officiel pré-imprimé
     * (valeurs seules, comme la machine à écrire), avec un décalage en millimètres pour l'aligner sur le papier.
     */
    public function up(): void
    {
        Schema::table('mairies', function (Blueprint $table) {
            $table->string('civil_print_mode', 20)->default('blanc');
            $table->decimal('civil_print_offset_x', 4, 1)->default(0);
            $table->decimal('civil_print_offset_y', 4, 1)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('mairies', fn (Blueprint $table) => $table->dropColumn(['civil_print_mode', 'civil_print_offset_x', 'civil_print_offset_y']));
    }
};
