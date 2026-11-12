<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Vignette d'une moto pour une année, délivrée par la mairie qui l'a enregistrée (BelongsToMairie) :
     * en attente de paiement → payée → carte retirée (App\Enums\DemandeVgtStatus).
     */
    public function up(): void
    {
        Schema::create('demandes_vgt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mairie_id')->constrained('mairies')->restrictOnDelete();
            $table->foreignId('moto_id')->constrained('motos')->restrictOnDelete();
            $table->unsignedSmallInteger('vgt_year');
            // Pas d'enum SQL (MariaDB 10.4) : chaîne courte + enum PHP (App\Enums\DemandeVgtStatus).
            $table->string('status', 20)->default('en_attente');
            // Tarif + majoration éventuelle (arriéré), figés au moment de la demande : un changement ultérieur
            // du tarif ne doit pas modifier rétroactivement une vignette déjà enregistrée.
            $table->unsignedInteger('base_amount');
            $table->boolean('is_late')->default(false);
            $table->unsignedInteger('surcharge_amount')->default(0);
            $table->date('payment_confirmed_at')->nullable();
            $table->date('retrait_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('mairie_id');
            $table->index('moto_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demandes_vgt');
    }
};
