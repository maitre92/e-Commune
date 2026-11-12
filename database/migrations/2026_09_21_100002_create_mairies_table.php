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
        Schema::create('mairies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('code', 30)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            // Carte de vignette : modèle présélectionné (App\Enums\VgtCardTemplate), logo de la commune et image
            // du monument imprimés sur la carte (null = dessins par défaut).
            $table->string('card_template', 20)->default('officiel');
            $table->string('logo_path')->nullable();
            $table->string('monument_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mairies');
    }
};
