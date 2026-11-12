<?php

namespace Tests\Feature\Caisse;

use App\Enums\RecetteType;
use App\Enums\RoleName;
use App\Models\ActeNaissance;
use App\Models\Mairie;
use App\Models\Recette;
use App\Models\User;
use App\Models\Versement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Caisse de la mairie : l'agent qui délivre encaisse (recette numérotée à son nom) ; chaque agent ne voit que ses
 * recettes ; le maire, le secrétaire général et le caissier voient toute la caisse et reçoivent les versements.
 */
class CaisseTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private Mairie $mairie;

    protected function setUp(): void
    {
        parent::setUp();
        // Avant la date limite (31 mars par défaut) : prix de base, sans pénalité de retard.
        $this->travelTo(now()->setDate(now()->year, 2, 15)->setTime(10, 0));
        $this->syncPermissions();
        $this->mairie = Mairie::factory()->create([
            'civil_centre_type' => 'principal', 'civil_centre_name' => 'Sebougou', 'civil_officer_name' => 'Bakary Samaké',
            'civil_officer_title' => 'Officier', 'civil_extract_price' => 500,
        ]);
    }

    private function user(RoleName $role): User
    {
        return User::factory()->state(['mairie_id' => $this->mairie->id])->withRole($role)->create();
    }

    public function test_selling_a_vignette_records_a_numbered_receipt_for_the_agent(): void
    {
        $vendeur = $this->user(RoleName::AgentVignette);

        $this->actingAs($vendeur)->post('/demandes-vgt/guichet', ['chassis_number' => 'CH1', 'type_or_brand' => '51 à 125 cm³']);
        $this->actingAs($vendeur)->post('/demandes-vgt/guichet', ['chassis_number' => 'CH2', 'type_or_brand' => '51 à 125 cm³']);

        $recettes = Recette::orderBy('id')->get();
        $this->assertCount(2, $recettes);
        $this->assertSame([1, 2], $recettes->pluck('numero')->all());
        $this->assertSame('Q'.date('Y').'-000001', $recettes->first()->quittance());
        $this->assertSame(RecetteType::Vignette, $recettes->first()->type);
        $this->assertSame(6000, $recettes->first()->amount);
        $this->assertSame($vendeur->id, $recettes->first()->user_id);
    }

    public function test_a_paid_extract_records_a_receipt_and_a_free_one_does_not(): void
    {
        $agent = $this->user(RoleName::AgentEtatCivil);
        $acte = ActeNaissance::factory()->create(['mairie_id' => $this->mairie->id]);

        $this->actingAs($agent)->post("/actes-naissance/{$acte->id}/extrait")->assertSessionHas('print_extrait');
        $this->assertSame(500, Recette::sole()->amount);
        $this->assertSame(RecetteType::Extrait, Recette::sole()->type);

        $this->mairie->update(['civil_extract_price' => 0]);
        $this->actingAs($agent->fresh())->post("/actes-naissance/{$acte->id}/extrait");
        $this->assertSame(1, Recette::count(), 'Extrait gratuit : rien en caisse.');
    }

    public function test_each_agent_only_sees_their_own_receipts(): void
    {
        $a = $this->user(RoleName::AgentVignette);
        $b = $this->user(RoleName::Mairie);
        $this->actingAs($a)->post('/demandes-vgt/guichet', ['chassis_number' => 'CHA', 'type_or_brand' => '51 à 125 cm³']);
        $this->actingAs($b)->post('/demandes-vgt/guichet', ['chassis_number' => 'CHB', 'type_or_brand' => '51 à 125 cm³']);

        $this->actingAs($a)->get('/caisse')->assertOk()->assertSee('Ma caisse')->assertSee('N° Engin CHA')->assertDontSee('N° Engin CHB')
            ->assertDontSee('name="agent_id"', false);
        $this->actingAs($a)->get('/caisse/etat')->assertForbidden();
    }

    public function test_the_maire_the_secretary_and_the_cashier_see_the_whole_cash_register(): void
    {
        $a = $this->user(RoleName::AgentVignette);
        $b = $this->user(RoleName::Mairie);
        $this->actingAs($a)->post('/demandes-vgt/guichet', ['chassis_number' => 'CHA', 'type_or_brand' => '51 à 125 cm³']);
        $this->actingAs($b)->post('/demandes-vgt/guichet', ['chassis_number' => 'CHB', 'type_or_brand' => '51 à 125 cm³']);

        foreach ([RoleName::Maire, RoleName::SecretaireGeneral, RoleName::Caissier] as $role) {
            $this->actingAs($this->user($role))->get('/caisse')->assertOk()
                ->assertSee('Caisse de la mairie')->assertSee('N° Engin CHA')->assertSee('N° Engin CHB')->assertSee('name="agent_id"', false)
                ->assertSee('12 000 FCFA');
        }
    }

    public function test_another_mairie_never_sees_this_cash_register(): void
    {
        $this->actingAs($this->user(RoleName::AgentVignette))->post('/demandes-vgt/guichet', ['chassis_number' => 'CHA', 'type_or_brand' => '51 à 125 cm³']);

        $otherMaire = User::factory()->state(['mairie_id' => Mairie::factory()->create()->id])->withRole(RoleName::Maire)->create();
        $this->actingAs($otherMaire)->get('/caisse')->assertOk()->assertDontSee('N° Engin CHA');
    }

    public function test_the_cashier_receives_an_agents_money(): void
    {
        $agent = $this->user(RoleName::AgentVignette);
        $caissier = $this->user(RoleName::Caissier);
        $this->actingAs($agent)->post('/demandes-vgt/guichet', ['chassis_number' => 'CH1', 'type_or_brand' => '51 à 125 cm³']);
        $this->actingAs($agent)->post('/demandes-vgt/guichet', ['chassis_number' => 'CH2', 'type_or_brand' => '51 à 125 cm³']);

        $this->actingAs($caissier)->post('/caisse/versements', ['agent_id' => $agent->id])->assertSessionHas('status');

        $versement = Versement::sole();
        $this->assertSame(12000, $versement->amount);
        $this->assertSame(2, $versement->recettes_count);
        $this->assertSame($caissier->id, $versement->received_by);
        $this->assertSame(0, Recette::nonVersees()->count());

        $this->actingAs($caissier)->post('/caisse/versements', ['agent_id' => $agent->id])->assertSessionHasErrors('agent');
        $this->actingAs($agent)->post('/caisse/versements', ['agent_id' => $agent->id])->assertForbidden();
    }

    public function test_the_daily_statement_prints_totals_per_agent(): void
    {
        $agent = $this->user(RoleName::AgentVignette);
        $this->actingAs($agent)->post('/demandes-vgt/guichet', ['chassis_number' => 'CH1', 'type_or_brand' => '51 à 125 cm³']);

        $this->actingAs($this->user(RoleName::Maire))->get('/caisse/etat')->assertOk()
            ->assertSee('ÉTAT JOURNALIER DE CAISSE')->assertSee($agent->name)->assertSee('6 000 FCFA')->assertSee('Le Maire');
    }

    public function test_the_whole_cash_view_can_be_granted_to_an_agent_as_an_exception(): void
    {
        $agent = $this->user(RoleName::Mairie);
        $this->actingAs($agent)->get('/caisse')->assertSee('Ma caisse');

        $this->actingAs($this->superadmin())->put("/users/{$agent->id}/permissions", ['permissions' => ['caisse.view']])->assertSessionHasNoErrors();
        $this->actingAs($agent->fresh())->get('/caisse')->assertSee('Caisse de la mairie');
    }
}
