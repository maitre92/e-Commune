<?php

namespace Tests\Feature\ActesNaissance;

use App\Enums\CentreEtatCivilType;
use App\Enums\Genre;
use App\Models\ActeNaissance;
use App\Models\ActivityLog;
use App\Models\ExtraitNaissance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Écran Actes de naissance : registre de la mairie (rubriques 1 à 16 de l'extrait), délivrance d'extraits et
 * impression en frappe de machine à écrire. Cloisonné par mairie, tout est journalisé.
 */
class ActeNaissanceScreenTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
    }

    /** Données fictives d'un acte complet. */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'region' => 'District de Bamako', 'cercle' => 'Bamako', 'arrondissement' => '', 'commune' => 'Commune IV',
            'centre_type' => 'secondaire', 'centre_name' => 'Lafiabougou',
            'act_number' => '720', 'act_year' => 1990, 'nina' => '',
            'first_names' => 'Fatoumata', 'last_name' => 'Keita', 'birth_date' => '1990-07-10', 'birth_time' => '08:30',
            'birth_place' => 'Bamako', 'gender' => 'femme',
            'father_first_names' => 'Bakary', 'father_last_name' => 'Keita', 'father_profession' => '', 'father_domicile' => 'Lafiabougou',
            'mother_first_names' => 'Aminata', 'mother_last_name' => 'Coulibaly', 'mother_profession' => 'Ménagère', 'mother_domicile' => 'Lafiabougou',
            'officer_name' => 'Oumar Traoré', 'officer_title' => 'Officier de l\'état civil', 'drawn_up_at' => '1990-07-20',
        ];
    }

    /** « Enregistrer et imprimer » sur un acte existant : ses champs tels quels + le bloc de certification. */
    private function deliver(User $agent, ActeNaissance $acte, array $overrides = [])
    {
        $fields = collect($acte->getAttributes())->only(array_keys($this->payload()))
            ->map(fn ($value) => $value ?? '')->all();
        $fields['birth_date'] = $acte->birth_date->toDateString();
        $fields['drawn_up_at'] = $acte->drawn_up_at->toDateString();

        return $this->actingAs($agent)->put("/actes-naissance/{$acte->id}", $overrides + [
            'print' => '1', 'cert_officer_name' => 'Mariam Diarra', 'cert_officer_title' => 'Officier de l\'état civil',
            'cert_centre_type' => 'principal', 'cert_centre_name' => 'Commune IV', 'cert_delivered_at' => now()->toDateString(),
        ] + $fields);
    }

    public function test_the_screen_requires_the_view_permission(): void
    {
        $this->get('/actes-naissance')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/actes-naissance')->assertRedirect(); // sans rôle
        $this->actingAs(User::factory()->mairie()->create())->get('/actes-naissance')->assertOk()->assertSee('NOUVEL ACTE DE NAISSANCE');
        $this->actingAs(User::factory()->adminNational()->create())->get('/actes-naissance')->assertOk();
    }

    public function test_it_follows_the_theme_template(): void
    {
        $this->actingAs(User::factory()->mairie()->create())->get('/actes-naissance')
            ->assertSee('card-header card-header-brand', false)
            ->assertSee('act-sheet', false)
            ->assertSee('RETROUVER UN ACTE')
            ->assertSee('EXTRAIT D\'ACTE DE NAISSANCE', false)
            ->assertSee('5 Sexe', false)
            ->assertSee('remplis automatiquement')
            ->assertSee('Réglez une fois l\'état civil', false)
            ->assertSee('Enregistrer et imprimer');
    }

    public function test_save_and_print_in_one_click_from_the_template(): void
    {
        $agent = User::factory()->mairie()->create();

        $this->actingAs($agent)->post('/actes-naissance', $this->payload() + [
            'print' => '1', 'cert_officer_name' => 'Mariam Diarra', 'cert_officer_title' => 'Officier de l\'état civil',
            'cert_centre_type' => 'principal', 'cert_centre_name' => 'Commune IV', 'cert_delivered_at' => now()->toDateString(),
        ])->assertSessionHas('print_extrait');

        $this->assertSame(1, ActeNaissance::count());
        $this->assertSame(1, ExtraitNaissance::count());
    }

    public function test_an_agent_registers_an_act_in_their_mairie_and_it_is_audited(): void
    {
        $agent = User::factory()->mairie()->create();

        $this->actingAs($agent)->post('/actes-naissance', $this->payload())->assertSessionHas('status')->assertSessionMissing('print_extrait');

        $acte = ActeNaissance::sole();
        $this->assertSame($agent->mairie_id, $acte->mairie_id);
        $this->assertSame(CentreEtatCivilType::Secondaire, $acte->centre_type);
        $this->assertSame(Genre::Femme, $acte->gender);
        $this->assertSame('1990-07-10', $acte->birth_date->toDateString());
        $this->assertNull($acte->father_profession, 'Un champ vide est enregistré comme absent.');
        $this->assertNull($acte->nina);
        $this->assertSame(1, ActivityLog::where('action', 'actes-naissance.created')->count());
    }

    public function test_the_father_is_optional_but_the_mother_and_the_officer_are_required(): void
    {
        $agent = User::factory()->mairie()->create();

        $this->actingAs($agent)->post('/actes-naissance', $this->payload(['father_first_names' => '', 'father_last_name' => '', 'father_domicile' => '']))
            ->assertSessionHasNoErrors();

        $this->actingAs($agent)->from('/actes-naissance')->post('/actes-naissance', $this->payload([
            'act_number' => '721', 'mother_last_name' => '', 'officer_name' => '', 'first_names' => '', 'gender' => 'x',
        ]))->assertSessionHasErrors(['mother_last_name', 'officer_name', 'first_names', 'gender']);

        $this->assertSame(1, ActeNaissance::count());
    }

    public function test_dates_must_be_consistent(): void
    {
        $agent = User::factory()->mairie()->create();

        $this->actingAs($agent)->from('/actes-naissance')->post('/actes-naissance', $this->payload([
            'birth_date' => now()->addDay()->toDateString(), 'drawn_up_at' => now()->addDays(2)->toDateString(),
        ]))->assertSessionHasErrors(['birth_date', 'drawn_up_at']);

        $this->actingAs($agent)->from('/actes-naissance')->post('/actes-naissance', $this->payload(['drawn_up_at' => '1990-07-01']))
            ->assertSessionHasErrors('drawn_up_at');

        $this->actingAs($agent)->from('/actes-naissance')->post('/actes-naissance', $this->payload(['act_year' => 1989]))
            ->assertSessionHasErrors('act_year');

        $this->assertSame(0, ActeNaissance::count());
    }

    public function test_the_act_number_is_unique_per_centre_and_year_within_a_mairie(): void
    {
        $agent = User::factory()->mairie()->create();
        $other = User::factory()->mairie()->create();

        $this->actingAs($agent)->post('/actes-naissance', $this->payload())->assertSessionHasNoErrors();
        $this->actingAs($agent)->from('/actes-naissance')->post('/actes-naissance', $this->payload())->assertSessionHasErrors('act_number');
        // Même numéro : autre année, autre centre, ou autre mairie → accepté.
        $this->actingAs($agent)->post('/actes-naissance', $this->payload(['act_year' => 1991, 'birth_date' => '1991-01-02', 'drawn_up_at' => '1991-01-05']))->assertSessionHasNoErrors();
        $this->actingAs($agent)->post('/actes-naissance', $this->payload(['centre_name' => 'Sébénikoro']))->assertSessionHasNoErrors();
        $this->actingAs($other)->post('/actes-naissance', $this->payload())->assertSessionHasNoErrors();

        $this->assertSame(4, ActeNaissance::query()->acrossMairies()->count());
    }

    public function test_the_nina_is_normalised_and_validated(): void
    {
        $agent = User::factory()->mairie()->create();

        $this->actingAs($agent)->post('/actes-naissance', $this->payload(['nina' => '1234 5678 9012 34a']))->assertSessionHasNoErrors();
        $this->assertSame('12345678901234A', ActeNaissance::sole()->nina);

        $this->actingAs($agent)->from('/actes-naissance')->post('/actes-naissance', $this->payload(['act_number' => '9', 'nina' => '12-34']))
            ->assertSessionHasErrors('nina');
    }

    public function test_an_agent_only_sees_and_edits_the_acts_of_their_mairie(): void
    {
        $agent = User::factory()->mairie()->create();
        ActeNaissance::factory()->create(['mairie_id' => $agent->mairie_id, 'user_id' => $agent->id, 'last_name' => 'Visible']);
        $foreign = ActeNaissance::factory()->create(['last_name' => 'Invisible']);

        $this->actingAs($agent)->get('/actes-naissance')->assertSee('Visible')->assertDontSee('Invisible');
        $this->actingAs($agent)->get('/actes-naissance?q=Invisible')->assertSee('Aucun acte trouvé');
        $this->actingAs($agent)->get("/actes-naissance?acte={$foreign->id}")->assertNotFound();
        $this->actingAs($agent)->put("/actes-naissance/{$foreign->id}", $this->payload())->assertNotFound();
        $this->deliver($agent, $foreign)->assertNotFound();
    }

    public function test_an_agent_corrects_an_act_and_the_change_is_audited(): void
    {
        $agent = User::factory()->mairie()->create();
        $this->actingAs($agent)->post('/actes-naissance', $this->payload());
        $acte = ActeNaissance::sole();

        $this->actingAs($agent)->put("/actes-naissance/{$acte->id}", $this->payload(['last_name' => 'Keïta']))->assertRedirect('/actes-naissance?acte='.$acte->id);

        $this->assertSame('Keïta', $acte->fresh()->last_name);
        $log = ActivityLog::where('action', 'actes-naissance.updated')->sole();
        $this->assertSame(['last_name' => 'Keita'], $log->old_values);
        $this->assertSame(['last_name' => 'Keïta'], $log->new_values);
    }

    public function test_the_admin_national_reads_but_never_writes(): void
    {
        $admin = User::factory()->adminNational()->create();
        $acte = ActeNaissance::factory()->create(['last_name' => 'Supervise']);

        $this->actingAs($admin)->get('/actes-naissance?q=Supervise')->assertOk()->assertSee('Supervise')->assertDontSee('Enregistrer et imprimer');
        $this->actingAs($admin)->post('/actes-naissance', $this->payload())->assertForbidden();
        $this->actingAs($admin)->put("/actes-naissance/{$acte->id}", $this->payload())->assertForbidden();
        $this->deliver($admin, $acte)->assertForbidden();
    }

    public function test_deleting_is_an_exception_permission_and_a_soft_delete(): void
    {
        $agent = User::factory()->mairie()->create();
        $acte = ActeNaissance::factory()->create(['mairie_id' => $agent->mairie_id]);

        $this->actingAs($agent)->delete("/actes-naissance/{$acte->id}")->assertForbidden();

        $agent->givePermissionTo('actes-naissance.delete');
        $this->actingAs($agent)->delete("/actes-naissance/{$acte->id}")->assertRedirect('/actes-naissance');
        $this->assertSoftDeleted($acte);
    }

    public function test_delivering_an_extract_keeps_a_record_logs_it_and_triggers_printing(): void
    {
        $agent = User::factory()->mairie()->create();
        $acte = ActeNaissance::factory()->create(['mairie_id' => $agent->mairie_id]);

        $this->deliver($agent, $acte)->assertRedirect('/actes-naissance?acte='.$acte->id)->assertSessionHas('print_extrait');

        $extrait = ExtraitNaissance::sole();
        $this->assertSame($acte->id, $extrait->acte_naissance_id);
        $this->assertSame($agent->id, $extrait->user_id);
        $this->assertSame($agent->mairie_id, $extrait->mairie_id);
        $this->assertSame(1, ActivityLog::where('action', 'actes-naissance.extract_delivered')->count());
        $this->assertSame(0, ActivityLog::where('action', 'actes-naissance.created')->where('subject_type', ExtraitNaissance::class)->count());

        // L'écran suivant lance l'impression dans un iframe caché.
        $this->actingAs($agent)->get('/actes-naissance?acte='.$acte->id)->assertSee(route('actes-naissance.extrait', $extrait), false);
    }

    public function test_delivery_requires_the_certifying_officer_and_a_past_date(): void
    {
        $agent = User::factory()->mairie()->create();
        $acte = ActeNaissance::factory()->create(['mairie_id' => $agent->mairie_id]);

        $this->deliver($agent, $acte, ['cert_officer_name' => '', 'cert_delivered_at' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors(['cert_officer_name', 'cert_delivered_at']);
        $this->assertSame(0, ExtraitNaissance::count());
    }

    public function test_the_printed_extract_follows_the_official_layout_in_typewriter_style(): void
    {
        $agent = User::factory()->mairie()->create();
        $acte = ActeNaissance::factory()->create([
            'mairie_id' => $agent->mairie_id, 'act_number' => '720', 'act_year' => 1990, 'centre_type' => 'secondaire', 'centre_name' => 'Sébénikoro',
            'first_names' => 'Fatoumata', 'last_name' => 'Keita', 'birth_date' => '1990-07-10', 'gender' => 'femme', 'nina' => '12345678901234A',
            'father_first_names' => null, 'father_last_name' => null, 'father_profession' => null, 'father_domicile' => null,
            'drawn_up_at' => '2010-09-07',
        ]);
        $this->deliver($agent, $acte, ['cert_delivered_at' => '2014-10-02']);
        $extrait = ExtraitNaissance::sole();

        $page = $this->actingAs($agent)->get("/actes-naissance/extraits/{$extrait->id}")->assertOk();

        $page->assertSee('REPUBLIQUE DU MALI')->assertSee('Un Peuple - Un But - Une Foi')
            ->assertSee("EXTRAIT D'ACTE DE NAISSANCE N°", false)
            ->assertSee('NINA :')
            ->assertSeeInOrder(['1 Prénom(s)', 'FATOUMATA', '2 Nom', 'KEITA', '3 Date de Naissance', '10 JUILLET 1990', '5 Sexe', 'FÉMININ'])
            ->assertSeeInOrder(['Père', '6 Prénom(s)', '/', 'Mère', '10 Prénom(s)'])
            ->assertSeeInOrder(['16 Date d\'établissement de l\'acte', '07/09/2010'], false)
            ->assertSeeInOrder(['Certifié le présent extrait conforme', '720', '1990', 'SECONDAIRE', 'SÉBÉNIKORO', 'MARIAM DIARRA', '02/10/2014'])
            ->assertSee('special-elite-regular.ttf', false)
            ->assertSee('cachet et signature')
            ->assertSee('size: A4 portrait', false);

        $this->assertSame(1, ActivityLog::where('action', 'actes-naissance.extract_printed')->count());
    }

    public function test_an_extract_of_another_mairie_cannot_be_printed(): void
    {
        $agent = User::factory()->mairie()->create();
        $other = User::factory()->mairie()->create();
        $acte = ActeNaissance::factory()->create(['mairie_id' => $other->mairie_id]);
        $this->deliver($other, $acte);
        $extrait = ExtraitNaissance::query()->acrossMairies()->sole();

        $this->actingAs($agent)->get("/actes-naissance/extraits/{$extrait->id}")->assertNotFound();
    }

    /** Réglages d'état civil de la mairie (signataire, centre, en-tête), saisis une fois. */
    private function configure(User $agent): void
    {
        $this->actingAs($agent)->put('/actes-naissance/reglages', [
            'civil_region' => 'District de Bamako', 'civil_cercle' => 'Bamako', 'civil_commune' => 'Commune IV',
            'civil_centre_type' => 'secondaire', 'civil_centre_name' => 'Sébénikoro',
            'civil_officer_name' => 'Mariam Diarra', 'civil_officer_title' => 'Officier de l\'état civil', 'civil_birth_place' => 'Bamako',
        ])->assertRedirect('/actes-naissance');
    }

    public function test_the_signatories_and_header_are_configured_once_per_mairie(): void
    {
        $agent = User::factory()->mairie()->create();
        $this->configure($agent);

        $mairie = $agent->mairie->fresh();
        $this->assertTrue($mairie->civilReady());
        $this->assertSame('Mariam Diarra', $mairie->civil_officer_name);
        $this->actingAs($agent)->get('/actes-naissance')->assertOk()->assertDontSee('Réglez une fois l\'état civil', false)->assertSee('Centre Secondaire de Sébénikoro');

        $this->actingAs(User::factory()->adminNational()->create())->put('/actes-naissance/reglages', ['civil_centre_name' => 'X'])->assertForbidden();
    }

    public function test_only_the_child_and_parents_are_typed_the_rest_is_filled_automatically(): void
    {
        $agent = User::factory()->mairie()->create();
        $this->configure($agent);

        $this->actingAs($agent)->post('/actes-naissance', [
            'first_names' => 'Fatoumata', 'last_name' => 'Keita', 'birth_date' => '2026-11-01', 'gender' => 'femme',
            'father_first_names' => 'Bakary', 'father_domicile' => 'Sébénikoro',
            'mother_first_names' => 'Aminata', 'mother_last_name' => 'Coulibaly', 'print' => '1',
        ])->assertSessionHasNoErrors()->assertSessionHas('print_extrait');

        $acte = ActeNaissance::sole();
        $this->assertSame('1', $acte->act_number, 'Premier acte du registre de l\'année.');
        $this->assertSame((int) date('Y'), $acte->act_year);
        $this->assertSame(now()->toDateString(), $acte->drawn_up_at->toDateString());
        $this->assertSame('Sébénikoro', $acte->centre_name);
        $this->assertSame('Commune IV', $acte->commune);
        $this->assertSame('Bamako', $acte->birth_place);
        $this->assertSame('Mariam Diarra', $acte->officer_name);
        $this->assertSame('Keita', $acte->father_last_name, 'Le père prend le nom de l\'enfant.');
        $this->assertSame('Sébénikoro', $acte->mother_domicile, 'La mère a le domicile du père.');

        $extrait = ExtraitNaissance::sole();
        $this->assertSame('Mariam Diarra', $extrait->officer_name);
        $this->assertSame('Sébénikoro', $extrait->centre_name);

        // Le numéro suivant du registre est attribué tout seul.
        $this->actingAs($agent)->post('/actes-naissance', [
            'first_names' => 'Moussa', 'last_name' => 'Diallo', 'birth_date' => '2026-11-02', 'gender' => 'homme',
            'mother_first_names' => 'Fanta', 'mother_last_name' => 'Sidibé',
        ])->assertSessionHasNoErrors();
        $this->assertSame('2', ActeNaissance::latest('id')->first()->act_number);
    }

    public function test_an_existing_act_prints_its_extract_in_one_click(): void
    {
        $agent = User::factory()->mairie()->create();
        $acte = ActeNaissance::factory()->create(['mairie_id' => $agent->mairie_id, 'last_name' => 'Traoré']);

        $this->actingAs($agent)->get('/actes-naissance?q=Traoré')->assertSee('Imprimer l\'extrait', false);
        $this->actingAs($agent)->from('/actes-naissance')->post("/actes-naissance/{$acte->id}/extrait")->assertSessionHasErrors('reglages');
        $this->assertSame(0, ExtraitNaissance::count());

        $this->configure($agent);
        $this->actingAs($agent)->post("/actes-naissance/{$acte->id}/extrait")->assertSessionHas('print_extrait');

        $extrait = ExtraitNaissance::sole();
        $this->assertSame('Mariam Diarra', $extrait->officer_name);
        $this->assertSame(now()->toDateString(), $extrait->delivered_at->toDateString());
        $this->assertSame(1, ActivityLog::where('action', 'actes-naissance.extract_delivered')->count());
    }

    public function test_correcting_an_act_keeps_what_is_not_retyped(): void
    {
        $agent = User::factory()->mairie()->create();
        $acte = ActeNaissance::factory()->create(['mairie_id' => $agent->mairie_id, 'act_number' => '77', 'centre_name' => 'Ancien Centre', 'last_name' => 'Keita']);

        $this->actingAs($agent)->put("/actes-naissance/{$acte->id}", ['last_name' => 'Keïta'])->assertSessionHasNoErrors();

        $acte->refresh();
        $this->assertSame('Keïta', $acte->last_name);
        $this->assertSame('77', $acte->act_number);
        $this->assertSame('Ancien Centre', $acte->centre_name);
    }

    public function test_the_extract_prints_on_the_official_preprinted_form_with_values_only(): void
    {
        $agent = User::factory()->mairie()->create();
        $this->configure($agent);
        $this->actingAs($agent)->put('/actes-naissance/reglages', [
            'civil_centre_type' => 'principal', 'civil_centre_name' => 'Sébénikoro', 'civil_officer_name' => 'Mariam Diarra',
            'civil_officer_title' => 'Officier', 'civil_print_mode' => 'preimprime', 'civil_print_offset_x' => '1,5', 'civil_print_offset_y' => '-2',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1.5, $agent->mairie->fresh()->civil_print_offset_x);

        $acte = ActeNaissance::factory()->create(['mairie_id' => $agent->mairie_id, 'first_names' => 'Baga']);
        $this->actingAs($agent)->post("/actes-naissance/{$acte->id}/extrait");
        $extrait = ExtraitNaissance::sole();

        // Par défaut, le mode réglé pour la mairie ; l'agent peut basculer pour une impression.
        $this->actingAs($agent)->get("/actes-naissance/extraits/{$extrait->id}")->assertOk()
            ->assertSee('<body class="preimprime">', false)
            ->assertSee('translate(1.5mm, -2mm)', false)
            ->assertSee('BAGA');
        $this->actingAs($agent)->get("/actes-naissance/extraits/{$extrait->id}?mode=blanc")->assertOk()
            ->assertSee('<body class="blanc">', false);
    }
}
