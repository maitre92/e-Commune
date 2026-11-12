<?php

namespace Tests\Feature\Users;

use App\Enums\RoleName;
use App\Models\ActivityLog;
use App\Models\Mairie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Écran Utilisateurs : liste cloisonnée (User::visibleTo), création (mot de passe temporaire affiché une fois),
 * modification, activation, réinitialisation, révocation, suppression — plafond de rôle partout. L'admin national
 * gère les agents de mairie ; un agent ne gère aucun compte.
 */
class UserScreenTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private User $superadmin;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->superadmin = $this->superadmin();
        $this->admin = User::factory()->adminNational()->create();
    }

    public function test_the_screen_requires_the_view_permission(): void
    {
        $this->get('/users')->assertRedirect('/login');
        $this->actingAs(User::factory()->mairie()->create())->get('/users')->assertForbidden();
        $this->actingAs($this->superadmin)->get('/users')->assertOk()->assertSee('UTILISATEURS');
    }

    public function test_it_follows_the_theme_template(): void
    {
        $this->actingAs($this->superadmin)->get('/users')
            ->assertSee('card-header card-header-brand', false)
            ->assertSee('modal fade', false);
    }

    public function test_an_admin_national_sees_everyone_but_superadmins(): void
    {
        User::factory()->adminNational()->create(['name' => 'Pair Admin']);
        User::factory()->mairie()->create(['name' => 'Un Agent']);

        $page = $this->actingAs($this->admin)->get('/users')->assertOk();
        $page->assertSee('Pair Admin')->assertSee('Un Agent')->assertDontSee($this->superadmin->name);
    }

    public function test_an_admin_national_creates_a_mairie_agent_with_a_mairie(): void
    {
        $mairie = Mairie::factory()->create();

        $this->actingAs($this->admin)
            ->post('/users', ['name' => 'Agent Mairie', 'phone' => '70 00 00 40', 'role' => 'mairie', 'mairie_id' => $mairie->id])
            ->assertRedirect('/users')
            ->assertSessionHas('temporary_password_for', 'Agent Mairie');

        $agent = User::where('phone', '+22370000040')->firstOrFail();
        $this->assertSame(RoleName::Mairie, $agent->roleName());
        $this->assertSame($mairie->id, $agent->mairie_id);
        $this->assertSame(1, ActivityLog::where('action', 'users.created')->where('subject_id', $agent->id)->count());
    }

    public function test_an_agent_cannot_create_accounts(): void
    {
        $agent = User::factory()->mairie()->create();

        $this->actingAs($agent)->post('/users', ['name' => 'X', 'phone' => '70 00 00 31', 'role' => 'mairie'])->assertForbidden();
        $this->assertFalse(User::where('phone', '+22370000031')->exists());
    }

    public function test_an_admin_national_cannot_create_an_admin_national_or_a_superadmin(): void
    {
        foreach (['admin_national', 'superadmin'] as $role) {
            $this->actingAs($this->admin)->post('/users', ['name' => 'Z', 'phone' => '70 00 00 3'.($role === 'superadmin' ? 3 : 4), 'role' => $role])
                ->assertSessionHasErrors('role');
        }
    }

    public function test_updating_name_and_phone(): void
    {
        $agent = User::factory()->mairie()->create(['name' => 'Ancien Nom']);

        $this->actingAs($this->admin)
            ->put("/users/{$agent->id}", ['name' => 'Nouveau Nom', 'phone' => $agent->phone])
            ->assertRedirect('/users');

        $this->assertSame('Nouveau Nom', $agent->fresh()->name);
    }

    public function test_an_admin_national_changes_the_mairie_of_an_agent(): void
    {
        $agent = User::factory()->mairie()->create();
        $newMairie = Mairie::factory()->create();

        $this->actingAs($this->admin)->put("/users/{$agent->id}", [
            'name' => $agent->name, 'phone' => $agent->phone, 'mairie_id' => $newMairie->id,
        ])->assertRedirect('/users');

        $this->assertSame($newMairie->id, $agent->fresh()->mairie_id);
    }

    public function test_an_admin_national_cannot_manage_a_peer_admin_national(): void
    {
        $peer = User::factory()->adminNational()->create(['name' => 'Pair']);

        $this->actingAs($this->admin)->put("/users/{$peer->id}", ['name' => 'Changé', 'phone' => $peer->phone])->assertForbidden();
        $this->actingAs($this->admin)->delete("/users/{$peer->id}")->assertForbidden();
        $this->assertSame('Pair', $peer->fresh()->name);
    }

    public function test_deactivating_revokes_sessions(): void
    {
        $agent = User::factory()->mairie()->create();
        DB::table('sessions')->insert(['id' => 'sess-u1', 'user_id' => $agent->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'x', 'last_activity' => time()]);

        $this->actingAs($this->admin)->put("/users/{$agent->id}", [
            'name' => $agent->name, 'phone' => $agent->phone, 'is_active' => '0',
        ])->assertRedirect('/users');

        $this->assertFalse($agent->fresh()->is_active);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $agent->id)->count());
    }

    public function test_resetting_the_password_shows_it_once(): void
    {
        $agent = User::factory()->mairie()->create();

        $this->actingAs($this->admin)->post("/users/{$agent->id}/reset-password")
            ->assertRedirect('/users')
            ->assertSessionHas('temporary_password');

        $this->assertTrue($agent->fresh()->must_change_password);
    }

    public function test_revoking_access_clears_sessions_without_deactivating(): void
    {
        $agent = User::factory()->mairie()->create();
        DB::table('sessions')->insert(['id' => 'sess-u2', 'user_id' => $agent->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'x', 'last_activity' => time()]);

        $this->actingAs($this->admin)->post("/users/{$agent->id}/revoke-access")->assertRedirect('/users');

        $this->assertTrue($agent->fresh()->is_active, 'Révoquer ne désactive pas le compte.');
        $this->assertSame(0, DB::table('sessions')->where('user_id', $agent->id)->count());
    }

    public function test_deleting_a_user_is_a_soft_delete_and_revokes_access(): void
    {
        $this->admin->givePermissionTo('users.delete');
        $agent = User::factory()->mairie()->create();

        $this->actingAs($this->admin)->delete("/users/{$agent->id}")->assertRedirect('/users');

        $this->assertSoftDeleted($agent);
    }

    public function test_write_actions_are_forbidden_without_their_specific_permission(): void
    {
        // L'agent de mairie n'a aucun défaut sur le module users (config/modules/users.php).
        $agent = User::factory()->mairie()->create();
        $other = User::factory()->mairie()->create();

        $this->actingAs($agent)->post("/users/{$other->id}/reset-password")->assertForbidden();
        // users.delete n'est pas un défaut de l'admin national : exception à accorder.
        $this->actingAs($this->admin)->delete("/users/{$other->id}")->assertForbidden();
    }

    public function test_the_last_active_superadmin_cannot_be_deactivated_or_deleted_from_the_screen(): void
    {
        $this->actingAs($this->superadmin)
            ->put("/users/{$this->superadmin->id}", ['name' => $this->superadmin->name, 'phone' => $this->superadmin->phone, 'is_active' => '0'])
            ->assertRedirect('/users')
            ->assertSessionHas('error');

        $this->assertTrue($this->superadmin->fresh()->is_active);

        $this->actingAs($this->superadmin)->delete("/users/{$this->superadmin->id}")
            ->assertRedirect('/users')
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($this->superadmin);
    }

    public function test_the_list_shows_role_chips_and_a_sheet_per_user_without_secrets(): void
    {
        $agent = User::factory()->mairie()->create(['name' => 'Agent Fiche']);

        $this->actingAs($this->admin)->get('/users')->assertOk()
            ->assertSee('users-filter', false)
            ->assertSee('data-token="role-agent"', false)
            ->assertDontSee('data-token="role-police"', false)
            ->assertSee('ficheUser-'.$agent->id, false)
            ->assertSee('Agent Fiche')
            ->assertDontSee($agent->password);
    }
}
