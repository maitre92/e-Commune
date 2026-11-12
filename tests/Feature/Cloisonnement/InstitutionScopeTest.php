<?php

namespace Tests\Feature\Cloisonnement;

use App\Exceptions\MissingInstitutionException;
use App\Models\Concerns\BelongsToMairie;
use App\Models\Mairie;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/** Modèle de test cloisonné par mairie, adossé à la table users (aucune DDL dans les tests). */
class ScopedByMairie extends Model
{
    use BelongsToMairie;

    protected $table = 'users';

    protected $guarded = [];
}

/**
 * Cloisonnement fail-closed par mairie : l'absence d'institution ne signifie jamais « tout voir ».
 */
class InstitutionScopeTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private Mairie $a;

    private Mairie $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Mairie::factory()->create();
        $this->b = Mairie::factory()->create();
        User::factory()->mairie($this->a)->count(2)->create();
        User::factory()->mairie($this->b)->count(3)->create();
    }

    public function test_an_agent_only_sees_the_rows_of_their_mairie(): void
    {
        $this->actingAs(User::factory()->mairie($this->a)->create());

        $this->assertSame(3, ScopedByMairie::count()); // 2 agents + l'agent lui-même
        $this->assertSame([$this->a->id], ScopedByMairie::pluck('mairie_id')->unique()->values()->all());
    }

    public function test_national_roles_see_everything(): void
    {
        foreach ([$this->superadmin(), User::factory()->adminNational()->create()] as $user) {
            $this->actingAs($user);
            $this->assertSame(User::count(), ScopedByMairie::count());
        }
    }

    public function test_an_account_without_role_nor_institution_sees_nothing_fail_closed(): void
    {
        $this->actingAs(User::factory()->create());
        $this->assertSame(0, ScopedByMairie::count());
    }

    public function test_an_unauthenticated_request_sees_nothing(): void
    {
        $this->assertSame(0, ScopedByMairie::count());
    }

    public function test_the_explicit_bypass_is_available_and_findable(): void
    {
        $this->actingAs(User::factory()->mairie($this->a)->create());

        $this->assertSame(User::whereNotNull('mairie_id')->count(), ScopedByMairie::acrossMairies()->whereNotNull('mairie_id')->count());
    }

    public function test_the_mairie_is_filled_from_the_author_on_creation(): void
    {
        $this->actingAs(User::factory()->mairie($this->a)->create());

        $row = ScopedByMairie::create(['name' => 'X', 'phone' => '+22370000777', 'password' => 'not-a-hash']);

        $this->assertSame($this->a->id, $row->mairie_id);
    }

    public function test_creating_without_any_institution_throws_instead_of_violating_the_database(): void
    {
        // Un compte sans mairie (superadmin, admin national) contourne la permission (Gate::before) mais pas
        // cette contrainte : logique métier, jamais atteinte en usage normal.
        $this->actingAs($this->superadmin());

        $this->expectException(MissingInstitutionException::class);
        ScopedByMairie::create(['name' => 'Y', 'phone' => '+22370000779', 'password' => 'not-a-hash']);
    }

    public function test_user_visible_to_is_fail_closed(): void
    {
        $agent = User::factory()->mairie($this->a)->create();
        $admin = User::factory()->adminNational()->create();
        $superadmin = $this->superadmin();

        $this->assertSame(User::count(), User::visibleTo($superadmin)->count());
        $this->assertFalse(User::visibleTo($admin)->whereKey($superadmin->id)->exists(), 'L\'admin national ne voit pas les superadmins.');
        $this->assertTrue(User::visibleTo($admin)->whereKey($agent->id)->exists());
        $this->assertSame(0, User::visibleTo($agent)->count(), 'Un agent de mairie ne gère aucun compte.');
        $this->assertSame(0, User::visibleTo(User::factory()->create())->count());
    }
}
