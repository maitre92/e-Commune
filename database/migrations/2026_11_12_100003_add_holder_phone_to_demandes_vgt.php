<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Numéro du détenteur saisi au guichet (facultatif), imprimé au verso de la carte avec son nom. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demandes_vgt', fn (Blueprint $table) => $table->string('holder_phone', 20)->nullable()->after('holder_name'));
    }

    public function down(): void
    {
        Schema::table('demandes_vgt', fn (Blueprint $table) => $table->dropColumn('holder_phone'));
    }
};
