<?php

namespace Tests\Feature\DemandesVgt;

use App\Enums\DemandeVgtStatus;
use App\Enums\RoleName;
use App\Enums\VgtLateMode;
use App\Models\ActivityLog;
use App\Models\DemandeVgt;
use App\Models\Mairie;
use App\Models\Moto;
use App\Models\Proprietaire;
use App\Models\TarifVgt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Écran Vignettes : la mairie enregistre la vignette d'une moto de son registre (à payer), confirme le paiement
 * (l'année de la moto avance), puis la remise de la carte. Cloisonné par mairie (BelongsToMairie).
 */
class DemandeVgtScreenTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Avant la date limite (31 mars par défaut) : prix de base, sans pénalité de retard.
        $this->travelTo(now()->setDate(now()->year, 2, 15)->setTime(10, 0));
        $this->syncPermissions();
    }

    private function motoFor(User $agent, array $attributes = []): Moto
    {
        $proprietaire = Proprietaire::factory()->create(['mairie_id' => $agent->mairie_id]);

        return Moto::factory()->create($attributes + [
            'mairie_id' => $agent->mairie_id, 'proprietaire_id' => $proprietaire->id, 'type_or_brand' => 'Sanili',
        ]);
    }

    private function demandeFor(User $agent, string $status, array $attributes = []): DemandeVgt
    {
        return DemandeVgt::factory()->create($attributes + [
            'mairie_id' => $agent->mairie_id, 'user_id' => $agent->id, 'moto_id' => $this->motoFor($agent)->id, 'status' => $status,
        ]);
    }

    public function test_the_screen_requires_the_view_permission(): void
    {
        $this->get('/demandes-vgt')->assertRedirect('/login');
        $this->actingAs(User::factory()->mairie()->create())->get('/demandes-vgt')->assertOk()->assertSee('VIGNETTES');
        $this->actingAs(User::factory()->adminNational()->create())->get('/demandes-vgt')->assertOk();
        $this->actingAs(User::factory()->create())->get('/demandes-vgt')->assertRedirect(); // sans rôle : refusé
    }

    public function test_it_follows_the_theme_template_and_has_no_validation_step_anymore(): void
    {
        $agent = User::factory()->mairie()->create();
        $this->demandeFor($agent, 'en_attente');

        $this->actingAs($agent)->get('/demandes-vgt')
            ->assertSee('card-header card-header-brand', false)
            ->assertSee('modal fade', false)
            ->assertSee('À payer')
            ->assertDontSee('Valider ou rejeter')
            ->assertDontSee('Code marchand');
    }

    public function test_an_agent_registers_a_vignette_for_the_current_year_at_the_base_tariff(): void
    {
        $agent = User::factory()->mairie()->create();
        $moto = $this->motoFor($agent);
        TarifVgt::create(['mairie_id' => $agent->mairie_id, 'type_or_brand' => 'Sanili', 'amount' => 6000]);

        $this->actingAs($agent)->post('/demandes-vgt', ['moto_id' => $moto->id, 'vgt_year' => now()->year])
            ->assertRedirect('/demandes-vgt')->assertSessionHas('status');

        $demande = DemandeVgt::sole();
        $this->assertSame($agent->mairie_id, $demande->mairie_id);
        $this->assertSame(DemandeVgtStatus::EnAttente, $demande->status);
        $this->assertSame(6000, $demande->base_amount);
        $this->assertFalse($demande->is_late);
        $this->assertSame(6000, $demande->totalAmount());
        // TarifVgt partage le module d'audit « demandes-vgt » : on distingue par subject_type.
        $this->assertSame(1, ActivityLog::where('action', 'demandes-vgt.created')->where('subject_type', DemandeVgt::class)->count());
    }

    public function test_a_past_year_vignette_gets_the_late_surcharge(): void
    {
        $agent = User::factory()->mairie()->create();
        $moto = $this->motoFor($agent);

        $this->actingAs($agent)->post('/demandes-vgt', ['moto_id' => $moto->id, 'vgt_year' => now()->year - 1])->assertRedirect('/demandes-vgt');

        $demande = DemandeVgt::sole();
        $this->assertTrue($demande->is_late);
        $this->assertSame($demande->base_amount, $demande->surcharge_amount, 'Règle par défaut : prix doublé.');
        $this->assertSame($demande->base_amount + $demande->surcharge_amount, $demande->totalAmount());
    }

    public function test_an_unknown_category_gets_the_default_tariff(): void
    {
        $agent = User::factory()->mairie()->create();
        $moto = $this->motoFor($agent); // catégorie « Sanili », absente de la grille de la mairie

        $this->actingAs($agent)->post('/demandes-vgt', ['moto_id' => $moto->id, 'vgt_year' => now()->year])->assertRedirect('/demandes-vgt');

        $this->assertSame(TarifVgt::DEFAULT_AMOUNT, DemandeVgt::sole()->base_amount);
        $this->assertFalse(TarifVgt::where('type_or_brand', 'Sanili')->exists());
    }

    public function test_a_moto_has_only_one_vignette_per_year(): void
    {
        $agent = User::factory()->mairie()->create();
        $moto = $this->motoFor($agent);
        DemandeVgt::factory()->create(['mairie_id' => $agent->mairie_id, 'moto_id' => $moto->id, 'vgt_year' => now()->year]);

        $this->actingAs($agent)->from('/demandes-vgt')->post('/demandes-vgt', ['moto_id' => $moto->id, 'vgt_year' => now()->year])
            ->assertSessionHasErrors('vgt_year');
        $this->assertSame(1, DemandeVgt::count());
    }

    public function test_an_agent_cannot_use_a_moto_of_another_mairie_nor_see_its_vignettes(): void
    {
        $agent = User::factory()->mairie()->create();
        $other = User::factory()->mairie()->create();
        $foreignMoto = $this->motoFor($other, ['plate_number' => 'ZZ 9999 ZZ']);
        DemandeVgt::factory()->create(['mairie_id' => $other->mairie_id, 'moto_id' => $foreignMoto->id]);

        $this->actingAs($agent)->get('/demandes-vgt')->assertOk()->assertDontSee('ZZ 9999 ZZ');
        $this->actingAs($agent)->from('/demandes-vgt')->post('/demandes-vgt', ['moto_id' => $foreignMoto->id, 'vgt_year' => now()->year])
            ->assertSessionHasErrors('moto_id');
        $this->assertSame(1, DemandeVgt::query()->acrossMairies()->count());
    }

    public function test_the_admin_national_sees_every_mairie_but_cannot_act(): void
    {
        $agent = User::factory()->mairie()->create();
        $demande = $this->demandeFor($agent, 'en_attente');
        $admin = User::factory()->adminNational()->create();

        $this->actingAs($admin)->get('/demandes-vgt')->assertOk()->assertSee($demande->moto->plate_number);
        $this->actingAs($admin)->put("/demandes-vgt/{$demande->id}/paiement", ['payment_confirmed_at' => now()->toDateString()])->assertForbidden();
    }

    public function test_an_unpaid_vignette_can_be_corrected_and_is_repriced(): void
    {
        $agent = User::factory()->mairie()->create();
        $demande = $this->demandeFor($agent, 'en_attente', ['vgt_year' => now()->year]);

        $this->actingAs($agent)->put("/demandes-vgt/{$demande->id}", ['moto_id' => $demande->moto_id, 'vgt_year' => now()->year - 1])
            ->assertRedirect('/demandes-vgt');

        $demande->refresh();
        $this->assertSame(now()->year - 1, $demande->vgt_year);
        $this->assertTrue($demande->is_late);
    }

    public function test_a_paid_vignette_can_no_longer_be_edited(): void
    {
        $agent = User::factory()->mairie()->create();
        $demande = $this->demandeFor($agent, 'payee');

        $this->actingAs($agent)->put("/demandes-vgt/{$demande->id}", ['moto_id' => $demande->moto_id, 'vgt_year' => now()->year - 1])
            ->assertForbidden();
    }

    public function test_confirming_the_payment_moves_the_moto_vignette_year_forward(): void
    {
        $agent = User::factory()->mairie()->create();
        $moto = $this->motoFor($agent, ['vgt_year' => now()->year - 2]);
        $demande = DemandeVgt::factory()->create(['mairie_id' => $agent->mairie_id, 'moto_id' => $moto->id, 'vgt_year' => now()->year]);

        $this->actingAs($agent)->put("/demandes-vgt/{$demande->id}/paiement", ['payment_confirmed_at' => now()->toDateString()])
            ->assertRedirect('/demandes-vgt');

        $this->assertSame(DemandeVgtStatus::Payee, $demande->fresh()->status);
        $this->assertSame(now()->year, $moto->fresh()->vgt_year);
        $this->assertTrue($moto->fresh()->isVgtCurrent());
    }

    public function test_paying_an_arrear_never_moves_the_moto_year_backwards(): void
    {
        $agent = User::factory()->mairie()->create();
        $moto = $this->motoFor($agent, ['vgt_year' => now()->year]);
        $demande = DemandeVgt::factory()->create(['mairie_id' => $agent->mairie_id, 'moto_id' => $moto->id, 'vgt_year' => now()->year - 3]);

        $this->actingAs($agent)->put("/demandes-vgt/{$demande->id}/paiement", ['payment_confirmed_at' => now()->toDateString()]);

        $this->assertSame(now()->year, $moto->fresh()->vgt_year);
    }

    public function test_payment_rules(): void
    {
        $agent = User::factory()->mairie()->create();
        $paid = $this->demandeFor($agent, 'payee');
        $pending = $this->demandeFor($agent, 'en_attente');
        $foreign = DemandeVgt::factory()->create(['status' => 'en_attente']);

        $this->actingAs($agent)->put("/demandes-vgt/{$paid->id}/paiement", ['payment_confirmed_at' => now()->toDateString()])->assertForbidden();
        $this->actingAs($agent)->put("/demandes-vgt/{$foreign->id}/paiement", ['payment_confirmed_at' => now()->toDateString()])->assertNotFound();
        $this->actingAs($agent)->put("/demandes-vgt/{$pending->id}/paiement", ['payment_confirmed_at' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('payment_confirmed_at');
        $this->assertSame(DemandeVgtStatus::EnAttente, $pending->fresh()->status);
    }

    public function test_an_agent_confirms_the_handover_of_a_paid_card(): void
    {
        $agent = User::factory()->mairie()->create();
        $demande = $this->demandeFor($agent, 'payee');

        $this->actingAs($agent)->put("/demandes-vgt/{$demande->id}/retrait", ['retrait_date' => now()->toDateString()])
            ->assertRedirect('/demandes-vgt');

        $this->assertSame(DemandeVgtStatus::Retiree, $demande->fresh()->status);
        $this->assertSame(now()->toDateString(), $demande->fresh()->retrait_date->toDateString());
    }

    public function test_handover_rules(): void
    {
        $agent = User::factory()->mairie()->create();
        $pending = $this->demandeFor($agent, 'en_attente');
        $paid = $this->demandeFor($agent, 'payee');

        $this->actingAs($agent)->put("/demandes-vgt/{$pending->id}/retrait", ['retrait_date' => now()->toDateString()])->assertForbidden();
        $this->actingAs($agent)->put("/demandes-vgt/{$paid->id}/retrait", ['retrait_date' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('retrait_date');
    }

    public function test_the_superadmin_cannot_register_since_it_has_no_mairie(): void
    {
        $moto = Moto::factory()->create();

        // La validation (« moto du registre de ma mairie ») échoue déjà pour lui (mairie_id = null ne matche jamais).
        $this->actingAs($this->superadmin())->from('/demandes-vgt')->post('/demandes-vgt', ['moto_id' => $moto->id, 'vgt_year' => now()->year])
            ->assertSessionHasErrors('moto_id');

        $this->assertSame(0, DemandeVgt::query()->acrossMairies()->count());
    }

    public function test_the_sidebar_badge_counts_what_the_agent_has_to_do(): void
    {
        $agent = User::factory()->mairie()->create();
        $this->demandeFor($agent, 'en_attente');
        $this->demandeFor($agent, 'payee');
        $this->demandeFor($agent, 'retiree');

        $this->actingAs($agent)->get('/demandes-vgt')->assertOk()->assertSee('title="2 à traiter"', false);
    }

    /** @return array<string, mixed> */
    private function tarifsPayload(User $maire, array $override = []): array
    {
        $tarifs = TarifVgt::gridFor($maire->mairie_id)->mapWithKeys(fn (TarifVgt $t) => [$t->id => [
            'cylinder_min' => $t->cylinder_min, 'cylinder_max' => $t->cylinder_max, 'amount' => $t->amount, 'is_active' => '1',
        ]])->all();

        return array_replace_recursive(['tarifs' => $tarifs, 'deadline_day' => 31, 'deadline_month' => 3, 'vgt_late_mode' => 'double', 'vgt_late_amount' => 2000], $override);
    }

    public function test_the_maire_sets_cylinder_prices_and_the_late_penalty(): void
    {
        $maire = User::factory()->state(['mairie_id' => Mairie::factory()->create()->id])->withRole(RoleName::Maire)->create();
        $this->actingAs($maire)->get('/demandes-vgt')->assertSee('Tarifs et pénalités')->assertSee('Pénalité de retard');

        $middle = TarifVgt::gridFor($maire->mairie_id)->firstWhere('type_or_brand', '51 à 125 cm³');
        $payload = $this->tarifsPayload($maire, [
            'tarifs' => [$middle->id => ['amount' => 7500]],
            'new_label' => 'Tricycle', 'new_amount' => 30000,
            'deadline_day' => 30, 'deadline_month' => 4, 'vgt_late_mode' => 'fixe', 'vgt_late_amount' => 3000,
        ]);

        $this->actingAs($maire)->put('/tarifs-vgt', $payload)->assertRedirect('/demandes-vgt')->assertSessionHas('status');

        $this->assertSame(7500, $middle->fresh()->amount);
        $this->assertSame(30000, TarifVgt::where('type_or_brand', 'Tricycle')->sole()->amount);
        $mairie = $maire->mairie->fresh();
        $this->assertSame('04-30', $mairie->vgt_deadline);
        $this->assertSame(VgtLateMode::Fixe, $mairie->vgt_late_mode);
        $this->assertSame(3000, $mairie->vgt_late_amount);
        $this->assertTrue(ActivityLog::where('subject_type', TarifVgt::class)->where('action', 'demandes-vgt.updated')->exists());
    }

    public function test_at_least_one_category_stays_active_and_ranges_are_coherent(): void
    {
        $maire = User::factory()->state(['mairie_id' => Mairie::factory()->create()->id])->withRole(RoleName::Maire)->create();
        $ids = TarifVgt::gridFor($maire->mairie_id)->pluck('id');

        $this->actingAs($maire)->put('/tarifs-vgt', $this->tarifsPayload($maire, ['tarifs' => $ids->mapWithKeys(fn ($id) => [$id => ['is_active' => '0']])->all()]))
            ->assertSessionHasErrors('tarifs');
        $this->actingAs($maire)->put('/tarifs-vgt', $this->tarifsPayload($maire, ['tarifs' => [$ids[0] => ['cylinder_min' => 200, 'cylinder_max' => 100]]]))
            ->assertSessionHasErrors('tarifs');
    }

    public function test_an_agent_cannot_manage_tarifs_until_the_maire_delegates_it(): void
    {
        $mairie = Mairie::factory()->create();
        $maire = User::factory()->state(['mairie_id' => $mairie->id])->withRole(RoleName::Maire)->create();
        $agent = User::factory()->state(['mairie_id' => $mairie->id])->withRole(RoleName::AgentVignette)->create();

        $this->actingAs($agent)->put('/tarifs-vgt', $this->tarifsPayload($maire))->assertForbidden();

        // Le maire coche « Gérer les tarifs des vignettes » dans la fenêtre de modification de l'agent.
        $this->actingAs($maire)->get('/users')->assertSee('Droits supplémentaires')->assertSee('Gérer les tarifs des vignettes');
        $this->actingAs($maire)->put("/users/{$agent->id}", [
            'name' => $agent->name, 'phone' => $agent->phone, 'role' => 'agent_vignette', 'is_active' => '1',
            'delegations_form' => '1', 'delegations' => ['demandes-vgt.manage_tarifs'],
        ])->assertRedirect('/users');

        $agent->refresh();
        $this->assertTrue($agent->can('demandes-vgt.manage_tarifs'));
        $this->assertTrue(ActivityLog::where('action', 'permissions.user_delegated')->exists());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($agent)->put('/tarifs-vgt', $this->tarifsPayload($maire))->assertRedirect('/demandes-vgt');

        // Décocher retire le droit.
        $this->actingAs($maire)->put("/users/{$agent->id}", [
            'name' => $agent->name, 'phone' => $agent->phone, 'role' => 'agent_vignette', 'is_active' => '1', 'delegations_form' => '1',
        ]);
        $this->assertFalse($agent->fresh()->hasDirectPermission('demandes-vgt.manage_tarifs'));
    }

    public function test_the_maire_cannot_delegate_a_permission_outside_the_agents_pool(): void
    {
        $mairie = Mairie::factory()->create();
        $maire = User::factory()->state(['mairie_id' => $mairie->id])->withRole(RoleName::Maire)->create();
        $agent = User::factory()->state(['mairie_id' => $mairie->id])->withRole(RoleName::AgentVignette)->create();

        $this->actingAs($maire)->put("/users/{$agent->id}", [
            'name' => $agent->name, 'phone' => $agent->phone, 'role' => 'agent_vignette', 'is_active' => '1',
            'delegations_form' => '1', 'delegations' => ['users.create', 'roles.view'],
        ]);

        $this->assertSame([], $agent->fresh()->getDirectPermissions()->pluck('name')->all());
    }
}
