<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tarifs des vignettes selon la cylindrée, réglés par chaque mairie (le prix varie d'une commune à l'autre :
 * 6 000 FCFA pour 51 à 125 cm³ à Tombouctou en 2026, 12 000 FCFA au-delà de 125 cm³ à Bamako), et règle de
 * retard propre à la mairie (date limite, prix doublé ou majoration fixe). La catégorie est figée sur chaque
 * vignette (imprimée sur la carte) comme son montant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarifs_vgt', function (Blueprint $table) {
            $table->foreignId('mairie_id')->nullable()->after('id')->constrained('mairies')->restrictOnDelete();
            $table->unsignedSmallInteger('cylinder_min')->nullable()->after('type_or_brand');
            $table->unsignedSmallInteger('cylinder_max')->nullable()->after('cylinder_min');
            $table->boolean('is_active')->default(true)->after('amount');
            $table->dropUnique(['type_or_brand']);
            $table->unique(['mairie_id', 'type_or_brand']);
        });

        Schema::table('mairies', function (Blueprint $table) {
            // Date limite de paiement de la vignette de l'année (MM-JJ) ; au-delà, la vignette est en retard.
            $table->string('vgt_deadline', 5)->default('03-31');
            // aucune | fixe | double (App\Enums\VgtLateMode).
            $table->string('vgt_late_mode', 10)->default('double');
            $table->unsignedInteger('vgt_late_amount')->default(2000);
        });

        Schema::table('demandes_vgt', function (Blueprint $table) {
            $table->string('category_label', 100)->nullable()->after('holder_name');
        });

        $this->seedCylinderTarifs();
    }

    /**
     * Grille de départ de chaque mairie (modifiable ensuite par le maire). Les anciens tarifs « par genre ou
     * marque », qui ne correspondent à aucune cylindrée, sont retirés ; leurs motos passent dans « 51 à 125 cm³ »,
     * la tranche de la quasi-totalité des motos du pays.
     */
    private function seedCylinderTarifs(): void
    {
        $now = now();
        $defaults = [
            ['type_or_brand' => '50 cm³ et moins', 'cylinder_min' => null, 'cylinder_max' => 50, 'amount' => 6000],
            ['type_or_brand' => '51 à 125 cm³', 'cylinder_min' => 51, 'cylinder_max' => 125, 'amount' => 6000],
            ['type_or_brand' => 'Plus de 125 cm³', 'cylinder_min' => 126, 'cylinder_max' => null, 'amount' => 12000],
        ];

        $oldLabels = DB::table('tarifs_vgt')->whereNull('mairie_id')->pluck('type_or_brand')->all();
        DB::table('tarifs_vgt')->whereNull('mairie_id')->delete();

        foreach (DB::table('mairies')->pluck('id') as $mairieId) {
            foreach ($defaults as $row) {
                DB::table('tarifs_vgt')->insert($row + ['mairie_id' => $mairieId, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        if ($oldLabels !== []) {
            DB::table('motos')->whereIn('type_or_brand', $oldLabels)->update(['type_or_brand' => '51 à 125 cm³']);
        }
        DB::table('demandes_vgt')->join('motos', 'motos.id', '=', 'demandes_vgt.moto_id')
            ->whereNull('demandes_vgt.category_label')
            ->update(['demandes_vgt.category_label' => DB::raw('motos.type_or_brand')]);
    }

    public function down(): void
    {
        Schema::table('demandes_vgt', fn (Blueprint $table) => $table->dropColumn('category_label'));
        Schema::table('mairies', fn (Blueprint $table) => $table->dropColumn(['vgt_deadline', 'vgt_late_mode', 'vgt_late_amount']));
        DB::table('tarifs_vgt')->delete();
        Schema::table('tarifs_vgt', function (Blueprint $table) {
            $table->dropUnique(['mairie_id', 'type_or_brand']);
            $table->dropConstrainedForeignId('mairie_id');
            $table->dropColumn(['cylinder_min', 'cylinder_max', 'is_active']);
            $table->unique('type_or_brand');
        });
    }
};
