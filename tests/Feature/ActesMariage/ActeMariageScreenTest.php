<?php

namespace Tests\Feature\ActesMariage;

use App\Enums\OptionMatrimoniale;
use App\Enums\RecetteType;
use App\Enums\RegimeMatrimonial;
use App\Enums\RoleName;
use App\Models\ActeMariage;
use App\Models\ActivityLog;
use App\Models\ExtraitMariage;
use App\Models\Mairie;
use App\Models\Recette;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Actes de mariage (rubriques 1 à 24 de l'extrait) : saisie minimale sur le modèle, délivrance d'extraits en un
 * clic, aperçu sans délivrance, caisse, cloisonnement par mairie, Web et application mobile.
 */
class ActeMariageScreenTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private Mairie $mairie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->mairie = Mairie::factory()->create([
            'civil_region' => 'Kayes', 'civil_cercle' => 'Kayes', 'civil_commune' => 'Samé Diongoma',
            'civil_centre_type' => 'principal', 'civil_centre_name' => 'Samé Ouolof',
            'civil_officer_name' => 'Boubacar Layé Diakité', 'civil_officer_title' => 'Maire', 'civil_birth_place' => 'Samé Ouolof',
            'civil_marriage_extract_price' => 1000,
        ]);
    }

    private function agent(?Mairie $mairie = null, RoleName $role = RoleName::AgentEtatCivil): User
    {
        return User::factory()->state(['mairie_id' => ($mairie ?? $this->mairie)->id])->withRole($role)->create();
    }

    /** Ce que l'agent tape vraiment : les époux, leurs parents, le régime et l'option. */
    private function minimal(array $overrides = []): array
    {
        return $overrides + [
            'husband_first_names' => 'Moustapha', 'husband_last_name' => 'Barry', 'husband_birth_date' => '1990-07-10',
            'husband_birth_place' => 'Bamako', 'husband_profession' => 'Enseignant', 'husband_domicile' => 'Samé Ouolof',
            'wife_first_names' => 'Adama', 'wife_last_name' => 'Diallo', 'wife_birth_date' => '2005-02-11',
            'wife_birth_place' => 'Kati', 'wife_profession' => 'Ménagère', 'wife_domicile' => 'Kayes',
            'husband_father_first_names' => 'Abdoul Karim', 'husband_mother_first_names' => 'Madioula', 'husband_mother_last_name' => 'Diallo',
            'wife_father_first_names' => 'Amadou', 'wife_mother_first_names' => 'Maman Salé', 'wife_mother_last_name' => 'Diallo',
            'celebrated_at' => '2020-09-10', 'declared_at' => '2020-08-26',
            'regime' => 'separation_biens', 'option' => 'polygamie',
        ];
    }

    public function test_the_screen_requires_the_view_permission_and_follows_the_theme(): void
    {
        $this->get('/actes-mariage')->assertRedirect('/login');
        $this->actingAs($this->agent(role: RoleName::AgentVignette))->get('/actes-mariage')->assertForbidden();

        $this->actingAs($this->agent())->get('/actes-mariage')->assertOk()
            ->assertSee('card-header card-header-brand', false)
            ->assertSee('EXTRAIT D\'ACTE DE MARIAGE', false)
            ->assertSee('20 Régime Matrimonial')
            ->assertSee('SÉPARATION DES BIENS')
            ->assertSee('POLYGAMIE')
            ->assertSee('Enregistrer et imprimer');
    }

    public function test_only_the_spouses_and_parents_are_typed_the_rest_is_filled_and_the_extract_is_paid(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)->post('/actes-mariage', $this->minimal(['print' => '1']))
            ->assertSessionHasNoErrors()->assertSessionHas('print_extrait');

        $acte = ActeMariage::sole();
        $this->assertSame('1', $acte->act_number);
        $this->assertSame((int) date('Y'), $acte->act_year);
        $this->assertSame('Samé Ouolof', $acte->centre_name);
        $this->assertSame('Samé Ouolof', $acte->marriage_place, 'Localité par défaut de la mairie.');
        $this->assertSame('Boubacar Layé Diakité', $acte->officer_name);
        $this->assertSame('Barry', $acte->husband_father_last_name, 'Le père porte le nom de l\'époux.');
        $this->assertSame('Diallo', $acte->wife_father_last_name, 'Le père porte le nom de l\'épouse.');
        $this->assertSame(RegimeMatrimonial::SeparationBiens, $acte->regime);
        $this->assertSame(OptionMatrimoniale::Polygamie, $acte->option);
        $this->assertSame($agent->id, $acte->user_id);
        $this->assertTrue(ActivityLog::where('action', 'actes-mariage.created')->exists());
        $this->assertTrue(ActivityLog::where('action', 'actes-mariage.extract_delivered')->exists());

        $extrait = ExtraitMariage::sole();
        $this->assertSame('Boubacar Layé Diakité', $extrait->officer_name);
        $recette = Recette::sole();
        $this->assertSame(RecetteType::ExtraitMariage, $recette->type);
        $this->assertSame(1000, $recette->amount);
        $this->assertSame($agent->id, $recette->user_id);

        // Numéro suivant, déclaration = célébration quand elle n'est pas saisie.
        $this->actingAs($agent)->post('/actes-mariage', $this->minimal(['husband_last_name' => 'Keita', 'declared_at' => '']))->assertSessionHasNoErrors();
        $second = ActeMariage::latest('id')->first();
        $this->assertSame('2', $second->act_number);
        $this->assertSame('2020-09-10', $second->declared_at->toDateString());
    }

    public function test_regime_option_and_dates_are_validated(): void
    {
        $agent = $this->agent();

        $this->actingAs($agent)->from('/actes-mariage')->post('/actes-mariage', $this->minimal(['regime' => '', 'option' => 'autre']))
            ->assertSessionHasErrors(['regime', 'option']);
        $this->actingAs($agent)->from('/actes-mariage')->post('/actes-mariage', $this->minimal(['wife_birth_date' => '2021-01-01']))
            ->assertSessionHasErrors('wife_birth_date');
        $this->actingAs($agent)->from('/actes-mariage')->post('/actes-mariage', $this->minimal(['celebrated_at' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('celebrated_at');
        $this->assertSame(0, ActeMariage::count());
    }

    public function test_the_extract_follows_the_official_layout_on_one_page(): void
    {
        $agent = $this->agent();
        $this->actingAs($agent)->post('/actes-mariage', $this->minimal(['print' => '1']));

        $this->actingAs($agent)->get(route('actes-mariage.extrait', ExtraitMariage::sole()))->assertOk()
            ->assertSee('EXTRAIT D\'ACTE DE MARIAGE N°', false)
            ->assertSee('FILIATION')
            ->assertSee('MOUSTAPHA')
            ->assertSee('ABDOUL KARIM')
            ->assertSee('SÉPARATION DES BIENS')
            ->assertSee('POLYGAMIE')
            ->assertSee('10-09-2020')
            ->assertSee('Special Elite')
            ->assertSee('size: A4 portrait', false);
        $this->assertTrue(ActivityLog::where('action', 'actes-mariage.extract_printed')->exists());
    }

    public function test_the_preview_delivers_nothing_and_cashes_nothing(): void
    {
        $agent = $this->agent();
        $acte = ActeMariage::factory()->create(['mairie_id' => $this->mairie->id, 'user_id' => $agent->id]);

        $this->actingAs($agent)->get(route('actes-mariage.preview', $acte))->assertOk()->assertSee('Aperçu — extrait pas encore délivré');
        $this->actingAs($agent)->get('/actes-mariage')->assertSee('nv-preview', false);
        $this->assertSame(0, ExtraitMariage::count());
        $this->assertSame(0, Recette::count());
    }

    public function test_an_existing_act_prints_its_extract_in_one_click(): void
    {
        $agent = $this->agent();
        $acte = ActeMariage::factory()->create(['mairie_id' => $this->mairie->id, 'husband_last_name' => 'Traoré']);

        $this->actingAs($agent)->get('/actes-mariage?q=traor')->assertSee($acte->couple());
        $this->actingAs($agent)->post(route('actes-mariage.deliver', $acte))->assertSessionHas('print_extrait');
        $this->assertSame(1, ExtraitMariage::count());
    }

    public function test_an_agent_lists_only_their_own_acts_but_the_maire_lists_all(): void
    {
        $a = $this->agent();
        $b = $this->agent();
        $maire = $this->agent(role: RoleName::Maire);
        ActeMariage::factory()->create(['mairie_id' => $this->mairie->id, 'user_id' => $a->id, 'husband_last_name' => 'Acteura']);
        ActeMariage::factory()->create(['mairie_id' => $this->mairie->id, 'user_id' => $b->id, 'husband_last_name' => 'Acteurb']);

        $this->actingAs($a)->get('/actes-mariage')->assertSee('Acteura')->assertDontSee('Acteurb');
        $this->actingAs($maire)->get('/actes-mariage')->assertSee('Acteura')->assertSee('Acteurb');
    }

    public function test_another_mairie_never_sees_nor_prints_the_act(): void
    {
        $acte = ActeMariage::factory()->create(['mairie_id' => $this->mairie->id]);
        $other = $this->agent(Mairie::factory()->create(['civil_centre_name' => 'Ailleurs', 'civil_officer_name' => 'X', 'civil_officer_title' => 'Maire']));

        $this->actingAs($other)->get(route('actes-mariage.preview', $acte))->assertNotFound();
        $this->actingAs($other)->post(route('actes-mariage.deliver', $acte))->assertNotFound();
        $this->actingAs($other)->get('/actes-mariage?q='.$acte->husband_last_name)->assertDontSee($acte->couple());
    }

    public function test_the_admin_national_reads_but_never_writes(): void
    {
        $admin = User::factory()->adminNational()->create();

        $this->actingAs($admin)->get('/actes-mariage')->assertOk()->assertDontSee('Enregistrer et imprimer');
        $this->actingAs($admin)->post('/actes-mariage', $this->minimal())->assertForbidden();
    }

    public function test_the_marriage_extract_price_is_set_in_the_shared_civil_settings(): void
    {
        $maire = $this->agent(role: RoleName::Maire);

        $this->actingAs($maire)->get('/actes-mariage')->assertSee('Prix de l\'extrait de mariage', false);
        $this->actingAs($maire)->put('/actes-mariage/reglages', [
            'civil_centre_type' => 'principal', 'civil_centre_name' => 'Samé Ouolof', 'civil_officer_name' => 'Boubacar Layé Diakité',
            'civil_officer_title' => 'Maire', 'civil_extract_price' => 500, 'civil_marriage_extract_price' => 2500,
        ])->assertRedirect('/actes-mariage');

        $this->assertSame(2500, $this->mairie->fresh()->civil_marriage_extract_price);
        $this->assertSame(500, $this->mairie->fresh()->civil_extract_price);
    }

    public function test_the_mobile_app_saves_previews_and_delivers_a_marriage_extract(): void
    {
        $agent = $this->agent();
        Sanctum::actingAs($agent);

        $response = $this->postJson('/api/v1/mariages', $this->minimal(['print' => true]))->assertCreated()
            ->assertJsonPath('data.husband_last_name', 'Barry')->assertJsonPath('data.regime', 'separation_biens');
        $this->get($response->json('extrait_url'))->assertOk()->assertSee('EXTRAIT D\'ACTE DE MARIAGE', false);

        $id = $response->json('data.id');
        $this->getJson('/api/v1/mariages')->assertOk()->assertJsonPath('data.0.epoux', 'Moustapha Barry');
        $this->get("/api/v1/mariages/{$id}/apercu")->assertOk()->assertSee('Aperçu — extrait pas encore délivré');
        $this->postJson("/api/v1/mariages/{$id}/extrait")->assertCreated()->assertJsonStructure(['extrait_url']);

        $this->assertSame(2, ExtraitMariage::count());
        $this->assertSame(2000, (int) Recette::sum('amount'));
        $this->getJson('/api/v1/caisse')->assertOk()->assertJsonPath('aujourd_hui.extraits_mariage', 2);
    }
}
