<?php

namespace Tests\Feature\Proprietaires;

use App\Models\ActivityLog;
use App\Models\Mairie;
use App\Models\Moto;
use App\Models\Proprietaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Écran Propriétaires : registre de la mairie, cloisonné par mairie (BelongsToMairie) — jamais visibles en
 * dehors de la mairie qui les a enregistrés.
 */
class ProprietaireScreenTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        $this->superadmin = $this->superadmin();
    }

    public function test_the_screen_requires_the_view_permission(): void
    {
        $this->get('/proprietaires')->assertRedirect('/login');
        $this->actingAs(User::factory()->adminNational()->create())->get('/proprietaires')->assertForbidden();
        $this->actingAs(User::factory()->mairie()->create())->get('/proprietaires')->assertOk()->assertSee('PROPRIÉTAIRES');
    }

    public function test_the_search_endpoint_matches_by_name_and_is_cloisonne(): void
    {
        $chef = User::factory()->mairie()->create();
        $mine = Proprietaire::factory()->create(['mairie_id' => $chef->mairie_id, 'first_name' => 'Awa', 'last_name' => 'Traoré']);
        Proprietaire::factory()->create(['first_name' => 'Awa', 'last_name' => 'Ailleurs']); // autre mairie

        $this->actingAs($chef)->getJson('/proprietaires/search?q=Awa')
            ->assertOk()
            ->assertExactJson(['results' => [['id' => $mine->id, 'text' => $mine->fullName()]]]);
    }

    public function test_it_follows_the_theme_template(): void
    {
        $this->actingAs(User::factory()->mairie()->create())->get('/proprietaires')
            ->assertSee('card-header card-header-brand', false)
            ->assertSee('modal fade', false);
    }

    public function test_a_agent_only_sees_the_owners_of_their_own_mairie(): void
    {
        $mine = Mairie::factory()->create();
        $other = Mairie::factory()->create();
        Proprietaire::factory()->create(['mairie_id' => $mine->id, 'first_name' => 'Visible']);
        Proprietaire::factory()->create(['mairie_id' => $other->id, 'first_name' => 'Invisible']);

        $chef = User::factory()->mairie($mine)->create();

        $this->actingAs($chef)->get('/proprietaires')->assertSee('Visible')->assertDontSee('Invisible');
    }

    public function test_a_agent_creates_an_owner_assigned_to_their_own_mairie(): void
    {
        $chef = User::factory()->mairie()->create();

        $this->actingAs($chef)->post('/proprietaires', [
            'first_name' => 'Awa', 'last_name' => 'Traoré', 'gender' => 'femme',
            'address' => 'Hamdallaye ACI 2000', 'phone' => '70 00 00 01', 'emergency_contact' => '70 00 00 02',
        ])->assertRedirect('/proprietaires')->assertSessionHas('status');

        $proprietaire = Proprietaire::sole();
        $this->assertSame('Awa', $proprietaire->first_name);
        $this->assertSame('+22370000001', $proprietaire->phone);
        $this->assertSame('+22370000002', $proprietaire->emergency_contact);
        $this->assertSame($chef->mairie_id, $proprietaire->mairie_id);
        $this->assertSame(1, ActivityLog::where('action', 'proprietaires.created')->count());
    }

    public function test_creating_from_the_motos_page_redirects_back_there_with_the_new_owner(): void
    {
        $chef = User::factory()->mairie()->create();

        $response = $this->actingAs($chef)->post('/proprietaires', [
            'first_name' => 'Awa', 'last_name' => 'Traoré', 'gender' => 'femme',
            'address' => 'Hamdallaye ACI 2000', 'phone' => '70 00 00 01', 'return_to' => 'motos',
        ])->assertSessionHas('status');

        $proprietaire = Proprietaire::sole();
        $response->assertRedirect(route('motos.index', ['new_proprietaire' => $proprietaire->id]));
    }

    public function test_validation_requires_the_mandatory_fields(): void
    {
        $chef = User::factory()->mairie()->create();

        $this->actingAs($chef)->from('/proprietaires')->post('/proprietaires', [])
            ->assertSessionHasErrors(['first_name', 'last_name', 'gender', 'address', 'phone']);

        $this->assertSame(0, Proprietaire::count());
    }

    public function test_the_emergency_contact_is_optional(): void
    {
        $chef = User::factory()->mairie()->create();

        $this->actingAs($chef)->post('/proprietaires', [
            'first_name' => 'Ali', 'last_name' => 'Cissé', 'gender' => 'homme',
            'address' => 'Badalabougou', 'phone' => '70 00 00 03',
        ])->assertRedirect('/proprietaires');

        $this->assertNull(Proprietaire::sole()->emergency_contact);
    }

    public function test_a_agent_cannot_create_for_another_mairie_even_by_forging_it(): void
    {
        $chef = User::factory()->mairie()->create();
        $other = Mairie::factory()->create();

        $this->actingAs($chef)->post('/proprietaires', [
            'first_name' => 'Test', 'last_name' => 'Forge', 'gender' => 'homme',
            'address' => 'Adresse', 'phone' => '70 00 00 04', 'mairie_id' => $other->id,
        ]);

        $this->assertSame($chef->mairie_id, Proprietaire::sole()->mairie_id);
    }

    public function test_updating_an_owner(): void
    {
        $chef = User::factory()->mairie()->create();
        $proprietaire = Proprietaire::factory()->create(['mairie_id' => $chef->mairie_id, 'last_name' => 'Ancien']);

        $this->actingAs($chef)->put("/proprietaires/{$proprietaire->id}", [
            'first_name' => $proprietaire->first_name, 'last_name' => 'Nouveau', 'gender' => $proprietaire->gender->value,
            'address' => $proprietaire->address, 'phone' => $proprietaire->phone,
        ])->assertRedirect('/proprietaires');

        $this->assertSame('Nouveau', $proprietaire->fresh()->last_name);
        $this->assertSame(1, ActivityLog::where('action', 'proprietaires.updated')->count());
    }

    public function test_a_agent_cannot_update_an_owner_of_another_mairie(): void
    {
        $chef = User::factory()->mairie()->create();
        $proprietaire = Proprietaire::factory()->create(); // autre mairie

        // 404 : le cloisonnement fail-closed bloque même la résolution de la route (§4.7).
        $this->actingAs($chef)->put("/proprietaires/{$proprietaire->id}", ['first_name' => 'X'])->assertNotFound();
    }

    public function test_deleting_an_owner_is_a_soft_delete(): void
    {
        $chef = User::factory()->mairie()->create();
        $proprietaire = Proprietaire::factory()->create(['mairie_id' => $chef->mairie_id]);

        $this->actingAs($chef)->delete("/proprietaires/{$proprietaire->id}")->assertRedirect('/proprietaires');

        $this->assertSoftDeleted($proprietaire);
        $this->assertSame(1, ActivityLog::where('action', 'proprietaires.deleted')->count());
    }

    public function test_the_superadmin_cannot_create_since_it_has_no_mairie(): void
    {
        // Gate::before (D16) laisse le superadmin franchir la permission proprietaires.create, mais il n'a
        // aucun mairie par construction (§4.2) : BelongsToMairie lève MissingInstitutionException
        // (logique métier, jamais contournée), câblée dans bootstrap/app.php à un message clair.
        $this->actingAs($this->superadmin)->from('/proprietaires')->post('/proprietaires', [
            'first_name' => 'X', 'last_name' => 'Y', 'gender' => 'homme', 'address' => 'Z', 'phone' => '70 00 00 09',
        ])->assertRedirect('/proprietaires')->assertSessionHas('error');

        $this->assertSame(0, Proprietaire::count());
    }

    public function test_the_list_groups_contacts_and_shows_each_owners_motos_with_filters(): void
    {
        $chef = User::factory()->mairie()->create();
        $withMoto = Proprietaire::factory()->create(['mairie_id' => $chef->mairie_id, 'first_name' => 'Awa', 'last_name' => 'Traore', 'emergency_contact' => '+22370009999']);
        Proprietaire::factory()->create(['mairie_id' => $chef->mairie_id, 'first_name' => 'Issa', 'last_name' => 'Keita']);
        $moto = Moto::factory()->create(['mairie_id' => $chef->mairie_id, 'proprietaire_id' => $withMoto->id, 'plate_number' => 'AB 1234 CD']);

        $this->actingAs($chef)->get('/proprietaires')->assertOk()
            ->assertSee('Urgence : +22370009999')
            ->assertSee('AB 1234 CD')
            ->assertSee('proprietaires-filter', false)
            ->assertDontSee('data-token="moto-volee"', false)
            ->assertSee('ficheProprietaire-'.$withMoto->id, false)
            ->assertDontSee('Déclarée volée')
            ->assertSee('Aucune moto enregistrée pour ce propriétaire.');
    }

    public function test_the_owner_sheet_lists_only_the_owners_own_motos(): void
    {
        $chef = User::factory()->mairie()->create();
        $owner = Proprietaire::factory()->create(['mairie_id' => $chef->mairie_id]);
        $other = Proprietaire::factory()->create(['mairie_id' => $chef->mairie_id]);
        Moto::factory()->create(['mairie_id' => $chef->mairie_id, 'proprietaire_id' => $owner->id, 'plate_number' => 'ZZ 1111 ZZ']);
        Moto::factory()->create(['mairie_id' => $chef->mairie_id, 'proprietaire_id' => $other->id, 'plate_number' => 'YY 2222 YY']);

        $page = $this->actingAs($chef)->get('/proprietaires')->getContent();
        $sheet = substr($page, strpos($page, 'id="ficheProprietaire-'.$owner->id.'"'));
        $sheet = substr($sheet, 0, strpos($sheet, 'modal-footer'));

        $this->assertStringContainsString('ZZ 1111 ZZ', $sheet);
        $this->assertStringNotContainsString('YY 2222 YY', $sheet);
    }
}
