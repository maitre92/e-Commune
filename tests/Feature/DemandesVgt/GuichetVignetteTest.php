<?php

namespace Tests\Feature\DemandesVgt;

use App\Enums\DemandeVgtStatus;
use App\Enums\VgtCardTemplate;
use App\Models\DemandeVgt;
use App\Models\Moto;
use App\Models\Proprietaire;
use App\Models\TarifVgt;
use App\Models\User;
use App\Models\VignetteYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Guichet vignette, comme au guichet réel : l'agent recopie le CH de la moto, le citoyen paie, la vignette
 * s'imprime. Un seul formulaire ; le CH suffit.
 */
class GuichetVignetteTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        // Avant la date limite (31 mars par défaut) : prix de base, sans pénalité de retard.
        $this->travelTo(now()->setDate(now()->year, 2, 15)->setTime(10, 0));
        $this->syncPermissions();
        $this->agent = User::factory()->mairie()->create();
    }

    public function test_the_counter_is_a_single_form_with_the_chassis_number_first(): void
    {

        $this->actingAs($this->agent)->get('/demandes-vgt')->assertOk()
            ->assertSee('GUICHET — VIGNETTE '.date('Y'))
            ->assertSee('N° Engin')
            ->assertSee('Encaisser et imprimer')
            ->assertSee('Cylindrée de la moto')
            ->assertSee('51 à 125 cm³')
            ->assertSee('12 000 F')
            ->assertDontSee('Prénom')
            ->assertDontSee('Choisir le modèle');
    }

    public function test_the_chassis_number_alone_delivers_and_prints_a_vignette_for_an_unknown_moto(): void
    {

        $response = $this->actingAs($this->agent)->post('/demandes-vgt/guichet', ['chassis_number' => 'lx 125 abc 9001', 'type_or_brand' => '51 à 125 cm³']);

        $demande = DemandeVgt::sole();
        $response->assertRedirect('/demandes-vgt')->assertSessionHas('print_card', route('demandes-vgt.card', $demande->id));
        $this->assertSame(DemandeVgtStatus::Retiree, $demande->status);
        $this->assertSame((int) date('Y'), $demande->vgt_year);
        $this->assertSame(6000, $demande->base_amount);
        $this->assertSame('51 à 125 cm³', $demande->category_label);

        $moto = Moto::sole();
        $this->assertSame('LX125ABC9001', $moto->chassis_number);
        $this->assertSame('51 à 125 cm³', $moto->type_or_brand);
        $this->assertNull($moto->proprietaire_id);
        $this->assertSame((int) date('Y'), $moto->vgt_year);

        $this->actingAs($this->agent)->get('/demandes-vgt')->assertSee(route('demandes-vgt.card', $demande->id), false);
        $this->actingAs($this->agent)->get(route('demandes-vgt.card', $demande->id))->assertOk()->assertSee('LX125ABC9001');
    }

    public function test_a_known_moto_keeps_its_category_and_owner(): void
    {
        $owner = Proprietaire::factory()->create(['mairie_id' => $this->agent->mairie_id, 'first_name' => 'Awa', 'last_name' => 'Traoré']);
        Moto::factory()->create(['mairie_id' => $this->agent->mairie_id, 'proprietaire_id' => $owner->id, 'chassis_number' => 'CH1', 'type_or_brand' => 'Plus de 125 cm³']);

        // Aucune cylindrée choisie : celle déjà connue de la moto s'applique.
        $this->actingAs($this->agent)->post('/demandes-vgt/guichet', ['chassis_number' => 'CH1'])->assertSessionHasNoErrors();

        $demande = DemandeVgt::sole();
        $this->assertSame(12000, $demande->base_amount);
        $this->assertSame('Plus de 125 cm³', $demande->category_label);
        $this->assertSame('Awa Traoré', $demande->holderName());
        $this->actingAs($this->agent)->get(route('demandes-vgt.card', $demande->id))->assertSee('ENGINS A 2 ROUES — PLUS DE 125 CM³');
    }

    public function test_the_holder_name_and_number_are_printed_on_the_back(): void
    {
        $this->actingAs($this->agent)->get('/demandes-vgt')->assertSee('Numéro du détenteur');
        $this->actingAs($this->agent)->post('/demandes-vgt/guichet', [
            'chassis_number' => 'CHP', 'type_or_brand' => '51 à 125 cm³', 'holder_name' => 'Awa Traoré', 'holder_phone' => '70 00 12 34',
        ])->assertSessionHasNoErrors();

        $demande = DemandeVgt::sole();
        $this->assertSame('+22370001234', $demande->holder_phone);
        $this->actingAs($this->agent)->get(route('demandes-vgt.card', $demande->id))->assertSee('Awa Traoré')->assertSee('70 00 12 34');

        $this->actingAs($this->agent)->from('/demandes-vgt')->post('/demandes-vgt/guichet', [
            'chassis_number' => 'CHQ', 'type_or_brand' => '51 à 125 cm³', 'holder_phone' => 'abc',
        ])->assertSessionHasErrors('holder_phone');

        // Corrigé après coup (fenêtre de correction).
        $this->actingAs($this->agent)->put("/demandes-vgt/{$demande->id}/correction", ['chassis_number' => 'CHP', 'holder_name' => 'Awa Traoré', 'holder_phone' => '76 11 22 33'])
            ->assertSessionHas('print_card');
        $this->assertSame('+22376112233', $demande->fresh()->holder_phone);
    }

    public function test_the_cylinder_is_required_for_an_unknown_moto(): void
    {
        $this->actingAs($this->agent)->from('/demandes-vgt')->post('/demandes-vgt/guichet', ['chassis_number' => 'CHX'])
            ->assertSessionHasErrors(['type_or_brand' => 'Choisissez la cylindrée de la moto.']);
        $this->assertSame(0, DemandeVgt::count());
    }

    public function test_the_counter_lookup_preselects_the_known_cylinder(): void
    {
        Moto::factory()->create(['mairie_id' => $this->agent->mairie_id, 'chassis_number' => 'CH9', 'type_or_brand' => 'Plus de 125 cm³', 'vgt_year' => 2025]);

        $this->actingAs($this->agent)->getJson('/demandes-vgt/guichet/moto?cadre=ch9')
            ->assertOk()->assertJson(['connue' => true, 'categorie' => 'Plus de 125 cm³', 'derniere_vignette' => 2025]);
    }

    public function test_the_category_chosen_at_the_counter_prices_a_new_moto(): void
    {
        TarifVgt::gridFor($this->agent->mairie_id);
        TarifVgt::create(['mairie_id' => $this->agent->mairie_id, 'type_or_brand' => 'Tricycle', 'amount' => 25000]);

        $this->actingAs($this->agent)->get('/demandes-vgt')->assertSee('Tricycle')->assertSee('25 000 F');
        $this->actingAs($this->agent)->post('/demandes-vgt/guichet', ['chassis_number' => 'CH2', 'type_or_brand' => 'Tricycle', 'holder_name' => 'Moussa Diallo']);

        $demande = DemandeVgt::sole();
        $this->assertSame(25000, $demande->base_amount);
        $this->assertSame('Moussa Diallo', $demande->holder_name);
        $this->actingAs($this->agent)->get(route('demandes-vgt.card', $demande->id))->assertSee('Moussa Diallo');
    }

    public function test_an_inactive_category_is_not_offered(): void
    {
        TarifVgt::gridFor($this->agent->mairie_id)->firstWhere('type_or_brand', '50 cm³ et moins')->update(['is_active' => false]);

        $this->actingAs($this->agent)->from('/demandes-vgt')->post('/demandes-vgt/guichet', ['chassis_number' => 'CH5', 'type_or_brand' => '50 cm³ et moins'])
            ->assertSessionHasErrors('type_or_brand');
    }

    public function test_after_the_deadline_the_price_is_doubled_by_default(): void
    {
        $this->travelTo(now()->setDate(now()->year, 4, 2));

        $this->actingAs($this->agent)->post('/demandes-vgt/guichet', ['chassis_number' => 'CH6', 'type_or_brand' => '51 à 125 cm³']);

        $demande = DemandeVgt::sole();
        $this->assertTrue($demande->is_late);
        $this->assertSame(6000, $demande->surcharge_amount);
        $this->assertSame(12000, $demande->totalAmount());
    }

    public function test_a_fixed_surcharge_or_no_penalty_can_be_set_by_the_mairie(): void
    {
        $this->travelTo(now()->setDate(now()->year, 4, 2));
        $this->agent->mairie->update(['vgt_late_mode' => 'fixe', 'vgt_late_amount' => 1500]);
        $this->actingAs($this->agent)->post('/demandes-vgt/guichet', ['chassis_number' => 'CH7', 'type_or_brand' => '51 à 125 cm³']);
        $this->assertSame(1500, DemandeVgt::where('id', DemandeVgt::max('id'))->value('surcharge_amount'));

        $this->agent->mairie->update(['vgt_late_mode' => 'aucune']);
        $this->actingAs($this->agent)->post('/demandes-vgt/guichet', ['chassis_number' => 'CH8', 'type_or_brand' => '51 à 125 cm³']);
        $this->assertSame(0, DemandeVgt::where('id', DemandeVgt::max('id'))->value('surcharge_amount'));
    }

    public function test_the_chassis_number_is_required(): void
    {
        $this->actingAs($this->agent)->from('/demandes-vgt')->post('/demandes-vgt/guichet', ['chassis_number' => ''])
            ->assertSessionHasErrors('chassis_number');
        $this->assertSame(0, DemandeVgt::count());
    }

    public function test_the_same_year_is_never_delivered_twice(): void
    {
        $moto = Moto::factory()->create(['mairie_id' => $this->agent->mairie_id, 'chassis_number' => 'LX1']);
        DemandeVgt::factory()->create(['mairie_id' => $this->agent->mairie_id, 'moto_id' => $moto->id, 'vgt_year' => now()->year]);

        $this->actingAs($this->agent)->post('/demandes-vgt/guichet', ['chassis_number' => 'LX1', 'type_or_brand' => '51 à 125 cm³'])->assertSessionHas('error');
        $this->assertSame(1, DemandeVgt::count());
    }

    public function test_a_chassis_number_of_another_mairie_is_refused(): void
    {
        Moto::factory()->create(['chassis_number' => 'LX125ABC9001']); // autre mairie

        $this->actingAs($this->agent)->from('/demandes-vgt')->post('/demandes-vgt/guichet', ['chassis_number' => 'LX125ABC9001', 'type_or_brand' => '51 à 125 cm³'])->assertSessionHasErrors('chassis_number');
        $this->assertSame(0, DemandeVgt::query()->acrossMairies()->count());
    }

    public function test_a_past_year_is_an_arrear_with_surcharge(): void
    {
        $this->actingAs($this->agent)->post('/demandes-vgt/guichet', ['chassis_number' => 'CH3', 'type_or_brand' => '51 à 125 cm³', 'vgt_year' => now()->year - 1]);

        $demande = DemandeVgt::sole();
        $this->assertTrue($demande->is_late);
        $this->assertSame(6000, $demande->surcharge_amount, 'Année passée : prix doublé (règle par défaut).');
    }

    public function test_the_first_vignette_of_the_year_freezes_the_years_model(): void
    {
        $this->assertFalse(VignetteYear::query()->acrossMairies()->where('year', now()->year)->exists());

        $this->actingAs($this->agent)->post('/demandes-vgt/guichet', ['chassis_number' => 'CH4', 'type_or_brand' => '51 à 125 cm³']);

        $this->assertSame(VgtCardTemplate::Officiel, VignetteYear::query()->acrossMairies()->where('year', now()->year)->sole()->card_template);
        $this->assertTrue(VignetteYear::isLocked(now()->year, $this->agent->mairie_id));
    }

    public function test_the_admin_national_has_no_counter(): void
    {
        $admin = User::factory()->adminNational()->create();

        $this->actingAs($admin)->get('/demandes-vgt')->assertOk()->assertDontSee('GUICHET');
        $this->actingAs($admin)->post('/demandes-vgt/guichet', ['chassis_number' => 'X', 'type_or_brand' => '51 à 125 cm³'])->assertForbidden();
    }
}
