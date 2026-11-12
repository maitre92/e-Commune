<?php

namespace Tests\Feature\Api;

use App\Models\ActeNaissance;
use App\Models\DemandeVgt;
use App\Models\Mairie;
use App\Models\Moto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * API de l'application mobile e-Commune : connexion de l'agent de mairie, guichet vignette (numéro de cadre →
 * carte), actes de naissance (modèle rempli → extrait). Mêmes règles et même cloisonnement que le Web.
 */
class MobileApiTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    private const PASSWORD = 'Guichet-Mobile-9';

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        // Avant la date limite (31 mars par défaut) : prix de base, sans pénalité de retard.
        $this->travelTo(now()->setDate(now()->year, 2, 15)->setTime(10, 0));
        $this->syncPermissions();
        $this->agent = User::factory()->mairie(Mairie::factory()->create(['name' => 'Mairie de Kati']))
            ->create(['phone' => '+22370000050', 'password' => self::PASSWORD]);
    }

    public function test_an_agent_logs_in_from_the_phone_and_gets_a_token(): void
    {
        $this->postJson('/api/v1/auth/login', ['phone' => '70 00 00 50', 'password' => self::PASSWORD, 'device_name' => 'Tecno'])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.role.name', 'mairie')
            ->assertJsonPath('user.institution.name', 'Mairie de Kati');
    }

    public function test_national_roles_cannot_use_the_mobile_app(): void
    {
        User::factory()->adminNational()->create(['phone' => '+22370000051', 'password' => self::PASSWORD]);

        $this->postJson('/api/v1/auth/login', ['phone' => '70 00 00 51', 'password' => self::PASSWORD, 'device_name' => 'x'])
            ->assertUnprocessable();
    }

    public function test_the_api_requires_a_token(): void
    {
        $this->getJson('/api/v1/vignettes/guichet?cadre=X')->assertUnauthorized();
        $this->getJson('/api/v1/actes')->assertUnauthorized();
    }

    public function test_the_vignette_counter_only_needs_the_chassis_number(): void
    {
        Moto::factory()->create(['mairie_id' => $this->agent->mairie_id, 'chassis_number' => 'LX125ABC9001', 'plate_number' => null, 'vgt_year' => now()->year - 1]);
        Sanctum::actingAs($this->agent);

        $this->getJson('/api/v1/vignettes/guichet')->assertOk()
            ->assertJsonPath('annee', now()->year)
            ->assertJsonPath('categories.0.nom', '50 cm³ et moins')
            ->assertJsonPath('categories.1.montant', 6000)
            ->assertJsonPath('retard.mode', 'double')
            ->assertJsonPath('retard.annees_en_retard.0', now()->year - 1);

        $response = $this->postJson('/api/v1/vignettes/guichet', ['chassis_number' => 'lx125 abc9001', 'type_or_brand' => '51 à 125 cm³'])
            ->assertCreated()->assertJsonPath('data.annee', now()->year)->assertJsonPath('data.moto.cadre', 'LX125ABC9001');

        $this->assertSame(now()->year, Moto::sole()->vgt_year);
        $card = $this->get($response->json('data.carte_url'))->assertOk();
        $this->assertStringContainsString('LX125ABC9001', $card->getContent());

        $this->postJson('/api/v1/vignettes/guichet', ['chassis_number' => 'LX125ABC9001', 'type_or_brand' => '51 à 125 cm³'])
            ->assertStatus(409)->assertJsonPath('code', 'already_issued');
    }

    public function test_an_unknown_moto_is_registered_with_its_chassis_number_only(): void
    {
        Sanctum::actingAs($this->agent);

        $this->postJson('/api/v1/vignettes/guichet', ['chassis_number' => 'NOUVEAU1', 'type_or_brand' => '51 à 125 cm³', 'holder_name' => 'Awa Traoré'])
            ->assertCreated()->assertJsonPath('data.detenteur', 'Awa Traoré')->assertJsonPath('data.moto.proprietaire', null);

        $this->postJson('/api/v1/vignettes/guichet', ['chassis_number' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('chassis_number');
    }

    public function test_the_api_is_cloisonnee_by_mairie(): void
    {
        $foreign = DemandeVgt::factory()->create(['status' => 'retiree']);
        $foreignActe = ActeNaissance::factory()->create();
        Sanctum::actingAs($this->agent);

        $this->get("/api/v1/vignettes/{$foreign->id}/carte")->assertNotFound();
        $this->getJson("/api/v1/actes/{$foreignActe->id}")->assertNotFound();
        $this->getJson('/api/v1/vignettes')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_an_act_is_filled_saved_and_its_extract_printed_from_the_phone(): void
    {
        Sanctum::actingAs($this->agent);

        $this->getJson('/api/v1/actes/nouveau')->assertOk()->assertJsonPath('data.pret', false);

        $response = $this->postJson('/api/v1/actes', [
            'centre_type' => 'principal', 'centre_name' => 'Kati', 'act_number' => '12', 'act_year' => 2020,
            'first_names' => 'Moussa', 'last_name' => 'Diallo', 'birth_date' => '2020-03-04', 'birth_place' => 'Kati', 'gender' => 'homme',
            'mother_first_names' => 'Fanta', 'mother_last_name' => 'Sidibé', 'officer_name' => 'Oumar Traoré', 'officer_title' => 'Officier',
            'drawn_up_at' => '2020-03-10', 'print' => true, 'cert_officer_name' => 'Mariam Diarra', 'cert_officer_title' => 'Officier',
            'cert_centre_type' => 'principal', 'cert_centre_name' => 'Kati', 'cert_delivered_at' => now()->toDateString(),
        ])->assertCreated()->assertJsonPath('data.last_name', 'Diallo')->assertJsonPath('data.gender', 'homme');

        $extrait = $this->get($response->json('extrait_url'))->assertOk();
        $this->assertStringContainsString('MOUSSA', $extrait->getContent());

        $this->getJson('/api/v1/actes?q=Diallo')->assertOk()->assertJsonPath('data.0.nom_complet', 'Moussa Diallo');

        $id = $response->json('data.id');
        $this->putJson("/api/v1/actes/{$id}", ['birth_date' => '2999-01-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('birth_date');

        // Réimpression en un clic : impossible tant que l'officier signataire n'est pas réglé, puis immédiate.
        $this->postJson("/api/v1/actes/{$id}/extrait")->assertUnprocessable();
        $this->agent->mairie->update(['civil_centre_type' => 'principal', 'civil_centre_name' => 'Kati', 'civil_officer_name' => 'Mariam Diarra', 'civil_officer_title' => 'Officier']);
        $url = $this->postJson("/api/v1/actes/{$id}/extrait")->assertCreated()->json('extrait_url');
        $this->assertStringContainsString('MARIAM DIARRA', $this->get($url)->getContent());
    }
}
