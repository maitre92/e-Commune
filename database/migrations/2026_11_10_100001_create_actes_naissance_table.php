<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Registre des actes de naissance d'une mairie (BelongsToMairie). Les champs reprennent, dans l'ordre, les
     * rubriques numérotées de l'extrait d'acte de naissance malien (1 à 16) et son en-tête (région, cercle,
     * arrondissement, commune, centre d'état civil). L'en-tête est copié sur l'acte (valeurs par défaut de la
     * mairie) pour qu'un changement de réglage ne modifie jamais un acte déjà enregistré.
     */
    public function up(): void
    {
        Schema::create('actes_naissance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mairie_id')->constrained('mairies')->restrictOnDelete();

            // En-tête : centre d'état civil où l'acte a été dressé.
            $table->string('region', 100)->nullable();
            $table->string('cercle', 100)->nullable();
            $table->string('arrondissement', 100)->nullable();
            $table->string('commune', 100)->nullable();
            $table->string('centre_type', 20);
            $table->string('centre_name', 100);

            // Numéro de l'acte dans le registre de l'année (numérotation reprise chaque année).
            $table->string('act_number', 20);
            $table->unsignedSmallInteger('act_year');
            $table->string('nina', 20)->nullable();

            // 1 à 5 : l'enfant.
            $table->string('first_names', 150);
            $table->string('last_name', 100);
            $table->date('birth_date');
            $table->string('birth_time', 5)->nullable();
            $table->string('birth_place', 150);
            // Pas d'enum SQL (MariaDB 10.4) : chaîne courte + enum PHP (App\Enums\Genre).
            $table->string('gender', 10);

            // 6 à 9 : le père (facultatif : père non déclaré).
            $table->string('father_first_names', 150)->nullable();
            $table->string('father_last_name', 100)->nullable();
            $table->string('father_profession', 100)->nullable();
            $table->string('father_domicile', 150)->nullable();

            // 10 à 13 : la mère.
            $table->string('mother_first_names', 150);
            $table->string('mother_last_name', 100);
            $table->string('mother_profession', 100)->nullable();
            $table->string('mother_domicile', 150)->nullable();

            // 14 à 16 : l'officier qui a dressé l'acte, et la date.
            $table->string('officer_name', 150);
            $table->string('officer_title', 150);
            $table->date('drawn_up_at');

            $table->timestamps();
            $table->softDeletes();

            // Un numéro par année et par centre d'une même mairie (les actes supprimés gardent le leur).
            $table->unique(['mairie_id', 'centre_type', 'centre_name', 'act_year', 'act_number'], 'actes_naissance_number_unique');
            $table->index(['last_name', 'first_names']);
            $table->index('birth_date');
        });

        Schema::create('extraits_naissance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mairie_id')->constrained('mairies')->restrictOnDelete();
            $table->foreignId('acte_naissance_id')->constrained('actes_naissance')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            // Bloc de certification de l'extrait : officier qui délivre, son centre, la date de délivrance.
            $table->string('officer_name', 150);
            $table->string('officer_title', 150);
            $table->string('centre_type', 20);
            $table->string('centre_name', 100);
            $table->date('delivered_at');
            $table->timestamps();

            $table->index('acte_naissance_id');
            $table->index(['mairie_id', 'delivered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extraits_naissance');
        Schema::dropIfExists('actes_naissance');
    }
};
