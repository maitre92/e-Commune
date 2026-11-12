<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registre des actes de mariage d'une mairie (BelongsToMairie). Les champs suivent, dans l'ordre, les rubriques
 * numérotées de l'extrait d'acte de mariage malien (1 à 24) et son en-tête. Comme pour la naissance, l'en-tête et
 * l'officier sont copiés sur l'acte, et chaque extrait délivré est conservé (extraits_mariage).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actes_mariage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mairie_id')->constrained('mairies')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();

            $table->string('region', 100)->nullable();
            $table->string('cercle', 100)->nullable();
            $table->string('arrondissement', 100)->nullable();
            $table->string('commune', 100)->nullable();
            $table->string('centre_type', 20);
            $table->string('centre_name', 100);
            $table->string('act_number', 20);
            $table->unsignedSmallInteger('act_year');

            // 1 à 7 : l'époux (le sexe, rubrique 5, est toujours « masculin »).
            $table->string('husband_first_names', 150);
            $table->string('husband_last_name', 100);
            $table->date('husband_birth_date');
            $table->string('husband_birth_place', 150);
            $table->string('husband_profession', 100)->nullable();
            $table->string('husband_domicile', 150)->nullable();

            // 8 à 14 : l'épouse (rubrique 12 toujours « féminin »).
            $table->string('wife_first_names', 150);
            $table->string('wife_last_name', 100);
            $table->date('wife_birth_date');
            $table->string('wife_birth_place', 150);
            $table->string('wife_profession', 100)->nullable();
            $table->string('wife_domicile', 150)->nullable();

            // 15 et 16 : filiation de l'époux puis de l'épouse.
            $table->string('husband_father_first_names', 150)->nullable();
            $table->string('husband_father_last_name', 100)->nullable();
            $table->string('husband_mother_first_names', 150)->nullable();
            $table->string('husband_mother_last_name', 100)->nullable();
            $table->string('wife_father_first_names', 150)->nullable();
            $table->string('wife_father_last_name', 100)->nullable();
            $table->string('wife_mother_first_names', 150)->nullable();
            $table->string('wife_mother_last_name', 100)->nullable();

            // 17 à 24.
            $table->date('declared_at');
            $table->date('celebrated_at');
            $table->string('marriage_place', 150);
            // Pas d'enum SQL (MariaDB 10.4) : App\Enums\RegimeMatrimonial, App\Enums\OptionMatrimoniale.
            $table->string('regime', 30);
            $table->string('option', 20);
            $table->string('officer_name', 150);
            $table->string('officer_title', 150);
            $table->date('drawn_up_at');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['mairie_id', 'centre_type', 'centre_name', 'act_year', 'act_number'], 'actes_mariage_number_unique');
            $table->index(['husband_last_name', 'husband_first_names']);
            $table->index(['wife_last_name', 'wife_first_names']);
            $table->index('user_id');
        });

        Schema::create('extraits_mariage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mairie_id')->constrained('mairies')->restrictOnDelete();
            $table->foreignId('acte_mariage_id')->constrained('actes_mariage')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('officer_name', 150);
            $table->string('officer_title', 150);
            $table->string('centre_type', 20);
            $table->string('centre_name', 100);
            $table->date('delivered_at');
            $table->timestamps();

            $table->index('acte_mariage_id');
            $table->index(['mairie_id', 'delivered_at']);
        });

        Schema::table('mairies', function (Blueprint $table) {
            $table->unsignedInteger('civil_marriage_extract_price')->default(0)->after('civil_extract_price');
        });
    }

    public function down(): void
    {
        Schema::table('mairies', fn (Blueprint $table) => $table->dropColumn('civil_marriage_extract_price'));
        Schema::dropIfExists('extraits_mariage');
        Schema::dropIfExists('actes_mariage');
    }
};
