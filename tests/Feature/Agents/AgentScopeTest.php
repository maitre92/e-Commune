<?php

namespace Tests\Feature\Agents;

use App\Enums\RoleName;
use App\Models\ActeNaissance;
use App\Models\DemandeVgt;
use App\Models\Mairie;
use App\Models\Moto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Chaque agent ne voit que son travail (vignettes vendues, actes saisis) ; le maire voit tout. Une vignette mal
 * saisie (CH) se corrige puis se réimprime. Le vendeur de vignettes ne voit pas de ligne « extraits » en caisse.
 */
class AgentScopeTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private Mairie $mairie;

    protected function setUp(): void
    {
        parent::setUp();
        // Avant la date limite (31 mars par défaut) : prix de base, sans pénalité de retard.
        $this->travelTo(now()->setDate(now()->year, 2, 15)->setTime(10, 0));
        $this->syncPermissions();
        $this->mairie = Mairie::factory()->create();
    }

    private function user(RoleName $role): User
    {
        return User::factory()->state(['mairie_id' => $this->mairie->id])->withRole($role)->create();
    }

    public function test_an_agent_only_lists_the_vignettes_he_sold_and_the_maire_lists_all(): void
    {
        $a = $this->user(RoleName::AgentVignette);
        $b = $this->user(RoleName::AgentVignette);
        $this->actingAs($a)->post('/demandes-vgt/guichet', ['chassis_number' => 'CHDEA', 'type_or_brand' => '51 à 125 cm³']);
        $this->actingAs($b)->post('/demandes-vgt/guichet', ['chassis_number' => 'CHDEB', 'type_or_brand' => '51 à 125 cm³']);
        $vignetteB = DemandeVgt::where('user_id', $b->id)->sole();

        $this->flushSession();
        $this->actingAs($a)->get('/demandes-vgt')->assertSee('CHDEA')->assertDontSee('CHDEB');
        $this->actingAs($a)->get("/demandes-vgt/{$vignetteB->id}/carte")->assertNotFound();
        $this->actingAs($this->user(RoleName::Maire))->get('/demandes-vgt')->assertSee('CHDEA')->assertSee('CHDEB')->assertSee('AGENT');
    }

    public function test_an_agent_lists_his_own_acts_but_searches_the_whole_register(): void
    {
        $a = $this->user(RoleName::AgentEtatCivil);
        $b = $this->user(RoleName::AgentEtatCivil);
        ActeNaissance::factory()->create(['mairie_id' => $this->mairie->id, 'user_id' => $a->id, 'last_name' => 'Mienacte']);
        ActeNaissance::factory()->create(['mairie_id' => $this->mairie->id, 'user_id' => $b->id, 'last_name' => 'Collegue']);

        $this->actingAs($a)->get('/actes-naissance')->assertSee('Mienacte')->assertDontSee('Collegue');
        $this->actingAs($a)->get('/actes-naissance?q=Collegue')->assertSee('Collegue');
        $this->actingAs($this->user(RoleName::Maire))->get('/actes-naissance')->assertSee('Mienacte')->assertSee('Collegue');
    }

    public function test_a_mistyped_chassis_number_is_corrected_and_the_card_reprinted(): void
    {
        $agent = $this->user(RoleName::AgentVignette);
        $this->actingAs($agent)->post('/demandes-vgt/guichet', ['chassis_number' => 'LX12SABC', 'type_or_brand' => '51 à 125 cm³']);
        $demande = DemandeVgt::sole();

        $this->actingAs($agent)->put("/demandes-vgt/{$demande->id}/correction", ['chassis_number' => 'lx125abc', 'holder_name' => 'Awa'])
            ->assertSessionHas('print_card', route('demandes-vgt.card', $demande->id));

        $this->assertSame('LX125ABC', $demande->fresh()->moto->chassis_number);
        $this->assertSame('Awa', $demande->fresh()->holder_name);
        $this->assertSame(1, Moto::count(), 'La moto a été corrigée, pas dupliquée.');
        $this->assertSame(6000, $demande->fresh()->base_amount, 'Le montant ne change pas.');
    }

    public function test_correcting_to_a_known_moto_moves_the_vignette_and_drops_the_wrong_one(): void
    {
        $agent = $this->user(RoleName::AgentVignette);
        $known = Moto::factory()->create(['mairie_id' => $this->mairie->id, 'chassis_number' => 'BONCH', 'proprietaire_id' => null, 'vgt_year' => null]);
        $this->actingAs($agent)->post('/demandes-vgt/guichet', ['chassis_number' => 'MAUVAISCH', 'type_or_brand' => '51 à 125 cm³']);
        $demande = DemandeVgt::sole();

        $this->actingAs($agent)->put("/demandes-vgt/{$demande->id}/correction", ['chassis_number' => 'BONCH'])->assertSessionHasNoErrors();

        $this->assertSame($known->id, $demande->fresh()->moto_id);
        $this->assertSame((int) date('Y'), $known->fresh()->vgt_year);
        $this->assertSoftDeleted('motos', ['chassis_number' => 'MAUVAISCH']);
    }

    public function test_an_agent_cannot_correct_a_colleagues_vignette(): void
    {
        $a = $this->user(RoleName::AgentVignette);
        $this->actingAs($a)->post('/demandes-vgt/guichet', ['chassis_number' => 'CHA', 'type_or_brand' => '51 à 125 cm³']);
        $demande = DemandeVgt::sole();

        $this->actingAs($this->user(RoleName::AgentVignette))->put("/demandes-vgt/{$demande->id}/correction", ['chassis_number' => 'CHX'])->assertForbidden();
        $this->actingAs($this->user(RoleName::Maire))->put("/demandes-vgt/{$demande->id}/correction", ['chassis_number' => 'CHX'])->assertSessionHasNoErrors();
    }

    public function test_a_vignette_seller_does_not_see_an_extracts_line_in_his_cash(): void
    {
        $vendeur = $this->user(RoleName::AgentVignette);
        $this->actingAs($vendeur)->get('/caisse')->assertOk()->assertSee('Vignettes')->assertDontSee('Extraits d&#039;acte', false)->assertDontSee("Extraits d'acte");
        $this->actingAs($this->user(RoleName::Maire))->get('/caisse')->assertSee('Vignettes')->assertSee("Extraits d'acte");
    }
}
