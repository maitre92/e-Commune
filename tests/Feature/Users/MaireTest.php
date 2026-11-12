<?php

namespace Tests\Feature\Users;

use App\Enums\RoleName;
use App\Models\ActivityLog;
use App\Models\Mairie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Le maire crée et gère les agents de SA mairie, chacun selon sa fonction ; chaque agent ne voit que ses écrans.
 */
class MaireTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private User $maire;

    protected function setUp(): void
    {
        parent::setUp();
        // Avant la date limite (31 mars par défaut) : prix de base, sans pénalité de retard.
        $this->travelTo(now()->setDate(now()->year, 2, 15)->setTime(10, 0));
        $this->syncPermissions();
        $this->maire = User::factory()->state(['mairie_id' => Mairie::factory()->create()->id])->withRole(RoleName::Maire)->create();
    }

    public function test_the_maire_creates_agents_of_every_function_in_his_own_mairie(): void
    {
        $other = Mairie::factory()->create();
        $roles = ['agent_vignette' => '70 00 00 61', 'agent_etat_civil' => '70 00 00 62', 'mairie' => '70 00 00 63', 'caissier' => '70 00 00 64', 'secretaire_general' => '70 00 00 65'];

        foreach ($roles as $role => $phone) {
            $this->actingAs($this->maire)->post('/users', ['name' => "Agent {$role}", 'phone' => $phone, 'role' => $role, 'mairie_id' => $other->id])
                ->assertRedirect('/users')->assertSessionHas('temporary_password');
        }

        foreach (array_keys($roles) as $role) {
            $agent = User::where('name', "Agent {$role}")->sole();
            $this->assertSame($role, $agent->roleName()->value);
            $this->assertSame($this->maire->mairie_id, $agent->mairie_id, 'Toujours sa propre mairie, quoi qu\'il soumette.');
        }
    }

    public function test_the_maire_cannot_create_another_maire_nor_national_roles(): void
    {
        foreach (['maire', 'admin_national', 'superadmin'] as $i => $role) {
            $this->actingAs($this->maire)->post('/users', ['name' => 'X', 'phone' => '70 00 00 7'.$i, 'role' => $role])->assertSessionHasErrors('role');
        }
    }

    public function test_the_maire_only_sees_and_manages_his_own_agents(): void
    {
        $mine = User::factory()->state(['mairie_id' => $this->maire->mairie_id])->withRole(RoleName::AgentVignette)->create(['name' => 'Mon Agent']);
        $foreign = User::factory()->state(['mairie_id' => Mairie::factory()->create()->id])->withRole(RoleName::AgentVignette)->create(['name' => 'Agent Ailleurs']);

        $this->actingAs($this->maire)->get('/users')->assertOk()->assertSee('Mon Agent')->assertDontSee('Agent Ailleurs');
        $this->actingAs($this->maire)->post("/users/{$mine->id}/reset-password")->assertSessionHas('temporary_password');
        $this->actingAs($this->maire)->post("/users/{$foreign->id}/reset-password")->assertForbidden();
    }

    public function test_each_agent_only_reaches_the_screens_of_his_function(): void
    {
        $mairieId = $this->maire->mairie_id;
        $vendeur = User::factory()->state(['mairie_id' => $mairieId])->withRole(RoleName::AgentVignette)->create();
        $etatCivil = User::factory()->state(['mairie_id' => $mairieId])->withRole(RoleName::AgentEtatCivil)->create();
        $caissier = User::factory()->state(['mairie_id' => $mairieId])->withRole(RoleName::Caissier)->create();

        $this->actingAs($vendeur)->get('/demandes-vgt')->assertOk();
        $this->actingAs($vendeur)->get('/actes-naissance')->assertForbidden();

        $this->actingAs($etatCivil)->get('/actes-naissance')->assertOk();
        $this->actingAs($etatCivil)->get('/demandes-vgt')->assertForbidden();

        $this->actingAs($caissier)->get('/caisse')->assertOk();
        $this->actingAs($caissier)->post('/demandes-vgt/guichet', ['chassis_number' => 'X', 'type_or_brand' => '51 à 125 cm³'])->assertForbidden();
    }

    public function test_the_login_page_presents_the_mairie_of_the_installation(): void
    {
        Mairie::query()->where('id', '!=', $this->maire->mairie_id)->delete();
        $name = $this->maire->mairie->name;

        $this->get('/login')->assertOk()->assertSee('<title>e-Commune — '.$name, false)->assertDontSee('RÉPUBLIQUE DU MALI');
        $this->getJson('/api/v1/health')->assertJsonPath('mairie', $name);
    }

    public function test_the_role_can_be_changed_when_editing_an_agent(): void
    {
        $agent = User::factory()->state(['mairie_id' => $this->maire->mairie_id])->withRole(RoleName::Mairie)->create();
        DB::table('sessions')->insert(['id' => 'sess-role', 'user_id' => $agent->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->maire)->get('/users')->assertSee('Rôle / fonction');
        $this->actingAs($this->maire)->put("/users/{$agent->id}", ['name' => $agent->name, 'phone' => $agent->phone, 'role' => 'caissier'])
            ->assertRedirect('/users')->assertSessionHasNoErrors();

        $agent->refresh();
        $this->assertSame(RoleName::Caissier, $agent->roleName());
        $this->assertSame($this->maire->mairie_id, $agent->mairie_id);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $agent->id)->count(), 'Nouveaux droits : le compte est reconnecté.');
        $this->assertSame(['role' => 'caissier'], ActivityLog::where('action', 'users.role_assigned')->latest('id')->first()->new_values);

        // Un maire ne nomme pas un autre maire.
        $this->actingAs($this->maire)->put("/users/{$agent->id}", ['name' => $agent->name, 'phone' => $agent->phone, 'role' => 'maire'])
            ->assertSessionHasErrors('role');
        $this->assertSame(RoleName::Caissier, $agent->fresh()->roleName());
    }

    public function test_the_superadmin_turns_an_agent_into_the_maire(): void
    {
        $agent = User::factory()->state(['mairie_id' => $this->maire->mairie_id])->withRole(RoleName::Mairie)->create();

        $this->actingAs($this->superadmin())->put("/users/{$agent->id}", ['name' => $agent->name, 'phone' => $agent->phone, 'role' => 'maire'])
            ->assertSessionHasNoErrors();

        $this->assertSame(RoleName::Maire, $agent->fresh()->roleName());
    }

    public function test_the_maire_also_sells_vignettes_and_sees_the_whole_cash_register_on_the_phone(): void
    {
        Sanctum::actingAs($this->maire);

        $this->getJson('/api/v1/vignettes/guichet')->assertOk();
        $this->postJson('/api/v1/vignettes/guichet', ['chassis_number' => 'CHMAIRE', 'type_or_brand' => '51 à 125 cm³'])->assertCreated();
        $this->getJson('/api/v1/caisse')->assertOk()
            ->assertJsonPath('toute_la_mairie', true)
            ->assertJsonPath('peut_recevoir', true)
            ->assertJsonPath('aujourd_hui.vignettes', 1)
            ->assertJsonPath('a_verser.0.agent', $this->maire->name);
    }

    public function test_an_agent_sees_only_his_own_cash_on_the_phone_and_cannot_receive(): void
    {
        $agent = User::factory()->state(['mairie_id' => $this->maire->mairie_id])->withRole(RoleName::AgentVignette)->create();
        Sanctum::actingAs($agent);

        $this->postJson('/api/v1/vignettes/guichet', ['chassis_number' => 'CHAGENT', 'type_or_brand' => '51 à 125 cm³'])->assertCreated();
        $this->getJson('/api/v1/caisse')->assertOk()
            ->assertJsonPath('toute_la_mairie', false)
            ->assertJsonPath('peut_recevoir', false)
            ->assertJsonPath('aujourd_hui.nombre', 1);
        $this->postJson('/api/v1/caisse/versements', ['agent_id' => $agent->id])->assertForbidden();
    }
}
