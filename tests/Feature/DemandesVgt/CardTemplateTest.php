<?php

namespace Tests\Feature\DemandesVgt;

use App\Enums\RoleName;
use App\Enums\VgtCardTemplate;
use App\Models\DemandeVgt;
use App\Models\Mairie;
use App\Models\User;
use App\Models\VignetteYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Carte de vignette : un seul modèle par année (VignetteYear), figé dès la première vignette ; logo et monument
 * propres à chaque mairie. Ancien en-tête : (W13, App\Enums\VgtCardTemplate) — PROPOSITION TECHNIQUE, le cahier ne décrit
 * aucun visuel (voir CLAUDE.md §5). Réglage gardé par la permission `mairies.update` déjà du manifeste, pas
 * d'écran Mairies dédié (W2, pas encore construit).
 */
class CardTemplateTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
    }

    /** Fixe le modèle de l'année de la vignette, puis renvoie l'adresse de sa carte. */
    private function cardUrl(DemandeVgt $demande, string $template): string
    {
        VignetteYear::query()->acrossMairies()->updateOrCreate(['mairie_id' => $demande->mairie_id, 'year' => $demande->vgt_year], ['card_template' => $template]);

        return "/demandes-vgt/{$demande->id}/carte";
    }

    public function test_the_maire_chooses_one_model_per_year_for_his_mairie_frozen_by_the_first_vignette(): void
    {
        $mairie = Mairie::factory()->create();
        $maire = User::factory()->state(['mairie_id' => $mairie->id])->withRole(RoleName::Maire)->create();
        $other = Mairie::factory()->create();
        $next = (int) date('Y') + 1;

        $this->actingAs($maire)->get('/demandes-vgt')->assertSee('Modèle de la vignette')->assertSee("Modèle de l'année — ", false);
        $this->actingAs($maire)->put("/vignettes/annees/{$next}", ['card_template' => 'jaune'])->assertRedirect('/demandes-vgt');
        $this->assertSame(VgtCardTemplate::Jaune, VignetteYear::templateFor($next, $mairie->id));
        $this->assertSame(VgtCardTemplate::Officiel, VignetteYear::templateFor($next, $other->id), 'Chaque mairie a son propre modèle.');

        DemandeVgt::factory()->create(['mairie_id' => $mairie->id, 'vgt_year' => $next, 'status' => 'retiree']);
        $this->actingAs($maire)->put("/vignettes/annees/{$next}", ['card_template' => 'rose'])->assertSessionHas('error');
        $this->assertSame(VgtCardTemplate::Jaune, VignetteYear::templateFor($next, $mairie->id), 'Figé : deux modèles pour une même année sont impossibles.');

        $this->actingAs(User::factory()->mairie($mairie)->create())->put("/vignettes/annees/{$next}", ['card_template' => 'rose'])->assertForbidden();
    }

    public function test_every_card_of_a_year_uses_that_years_model(): void
    {
        $agent = User::factory()->mairie()->create();
        VignetteYear::create(['mairie_id' => $agent->mairie_id, 'year' => 2025, 'card_template' => 'ancien']);
        $a = DemandeVgt::factory()->create(['mairie_id' => $agent->mairie_id, 'user_id' => $agent->id, 'vgt_year' => 2025, 'status' => 'retiree']);
        $b = DemandeVgt::factory()->create(['mairie_id' => $agent->mairie_id, 'user_id' => $agent->id, 'vgt_year' => 2025, 'status' => 'payee']);

        foreach ([$a, $b] as $demande) {
            $this->actingAs($agent)->get("/demandes-vgt/{$demande->id}/carte")->assertOk()->assertSee('data-model="ancien"', false);
        }
        // Une année sans réglage reprend le dernier modèle réglé avant elle.
        $this->assertSame(VgtCardTemplate::Ancien, VignetteYear::templateFor(2026, $agent->mairie_id));
    }

    public function test_the_admin_previews_each_model_before_choosing(): void
    {
        $admin = User::factory()->adminNational()->create();

        foreach (VgtCardTemplate::cases() as $template) {
            $this->actingAs($admin)->get("/vignettes/modeles/{$template->value}")->assertOk()->assertSee('data-model="'.$template->value.'"', false);
        }
        $this->actingAs(User::factory()->mairie()->create())->get('/vignettes/modeles/officiel')->assertForbidden();
    }

    public function test_each_model_renders_the_real_card_of_the_demande(): void
    {
        $mairieModel = Mairie::factory()->create();
        $agent = User::factory()->state(['mairie_id' => $mairieModel->id])->withRole(RoleName::Maire)->create();
        $demande = DemandeVgt::factory()->create(['mairie_id' => $mairieModel->id, 'status' => 'payee']);

        foreach (VgtCardTemplate::cases() as $template) {
            $this->actingAs($agent)->get($this->cardUrl($demande, $template->value))
                ->assertOk()->assertSee('data-model="'.$template->value.'"', false)->assertSee($demande->moto->plate_number)->assertSee(str_pad((string) $demande->id, 6, '0', STR_PAD_LEFT));
        }

        $this->actingAs($agent)->get($this->cardUrl($demande, 'ancien'))->assertSee('data-model="ancien"', false)->assertDontSee('data-model="officiel"', false);
    }

    public function test_the_card_is_unavailable_for_an_unpaid_vignette_or_another_institution(): void
    {
        $mairieModel = Mairie::factory()->create();
        $agent = User::factory()->state(['mairie_id' => $mairieModel->id])->withRole(RoleName::Maire)->create();
        $paid = DemandeVgt::factory()->create(['mairie_id' => $mairieModel->id, 'status' => 'payee']);
        $pending = DemandeVgt::factory()->create(['mairie_id' => $mairieModel->id, 'status' => 'en_attente']);
        $foreign = DemandeVgt::factory()->create(['status' => 'payee']);

        $this->actingAs($agent)->get($this->cardUrl($pending, 'officiel'))->assertNotFound();
        $this->actingAs($agent)->get($this->cardUrl($foreign, 'officiel'))->assertNotFound();
    }

    public function test_the_card_requires_the_view_permission(): void
    {
        $demande = DemandeVgt::factory()->create(['status' => 'payee']);

        $this->get($this->cardUrl($demande, 'officiel'))->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get($this->cardUrl($demande, 'officiel'))->assertRedirect(); // compte sans rôle : refusé (fail-closed)
    }

    public function test_the_default_logo_is_the_drawn_arms_when_the_mairie_has_none(): void
    {
        $mairieModel = Mairie::factory()->create();
        $agent = User::factory()->state(['mairie_id' => $mairieModel->id])->withRole(RoleName::Maire)->create();
        $demande = DemandeVgt::factory()->create(['mairie_id' => $mairieModel->id, 'status' => 'payee']);

        $this->actingAs($agent)->get($this->cardUrl($demande, 'officiel'))->assertOk()
            ->assertSee('assets/images/vignette/arms-bamako.png', false);
    }

    public function test_the_admin_national_uploads_a_logo_that_replaces_the_default_on_every_model(): void
    {
        Storage::fake('public');
        $admin = User::factory()->adminNational()->create();
        $mairieModel = Mairie::factory()->create();
        $agent = User::factory()->state(['mairie_id' => $mairieModel->id])->withRole(RoleName::Maire)->create();
        $demande = DemandeVgt::factory()->create(['mairie_id' => $mairieModel->id, 'status' => 'payee']);

        $this->actingAs($admin)->post("/mairies/{$mairieModel->id}/logo-vgt", ['logo' => UploadedFile::fake()->image('logo.png', 300, 300)])
            ->assertRedirect('/demandes-vgt')->assertSessionHas('status');

        $path = $mairieModel->fresh()->logo_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        foreach (VgtCardTemplate::cases() as $template) {
            $this->actingAs($agent)->get($this->cardUrl($demande, $template->value))->assertOk()
                ->assertSee('storage/'.$path, false)->assertDontSee('assets/images/vignette/arms-', false);
        }
    }

    public function test_replacing_or_resetting_the_logo_deletes_the_previous_file(): void
    {
        Storage::fake('public');
        $admin = User::factory()->adminNational()->create();
        $mairieModel = Mairie::factory()->create();

        $this->actingAs($admin)->post("/mairies/{$mairieModel->id}/logo-vgt", ['logo' => UploadedFile::fake()->image('a.png', 200, 200)]);
        $first = $mairieModel->fresh()->logo_path;
        $this->actingAs($admin)->post("/mairies/{$mairieModel->id}/logo-vgt", ['logo' => UploadedFile::fake()->image('b.jpg', 200, 200)]);
        $second = $mairieModel->fresh()->logo_path;

        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->actingAs($admin)->delete("/mairies/{$mairieModel->id}/logo-vgt")->assertRedirect('/demandes-vgt');

        $this->assertNull($mairieModel->fresh()->logo_path);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_an_invalid_logo_is_rejected(): void
    {
        Storage::fake('public');
        $admin = User::factory()->adminNational()->create();
        $mairieModel = Mairie::factory()->create();

        foreach ([
            UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
            UploadedFile::fake()->image('big.png', 300, 300)->size(2048),
            UploadedFile::fake()->image('tiny.png', 40, 40),
        ] as $file) {
            $this->actingAs($admin)->from('/demandes-vgt')->post("/mairies/{$mairieModel->id}/logo-vgt", ['logo' => $file])->assertSessionHasErrors('logo');
        }

        $this->assertNull($mairieModel->fresh()->logo_path);
    }

    public function test_the_maire_changes_his_own_mairies_logo_but_not_another(): void
    {
        Storage::fake('public');
        $mairie = Mairie::factory()->create();
        $maire = User::factory()->state(['mairie_id' => $mairie->id])->withRole(RoleName::Maire)->create();

        $this->actingAs($maire)->post("/mairies/{$mairie->id}/logo-vgt", ['logo' => UploadedFile::fake()->image('logo.png', 300, 300)])->assertRedirect('/demandes-vgt');
        $this->assertNotNull($mairie->fresh()->logo_path);
        $this->actingAs($maire)->post('/mairies/'.Mairie::factory()->create()->id.'/logo-vgt', ['logo' => UploadedFile::fake()->image('logo.png', 300, 300)])->assertForbidden();
    }

    public function test_an_agent_cannot_change_a_mairies_logo(): void
    {
        Storage::fake('public');
        $chef = User::factory()->mairie()->create();
        $mairieModel = Mairie::factory()->create();

        $this->actingAs($chef)->post("/mairies/{$mairieModel->id}/logo-vgt", ['logo' => UploadedFile::fake()->image('logo.png', 300, 300)])->assertForbidden();
        $this->actingAs($chef)->delete("/mairies/{$mairieModel->id}/logo-vgt")->assertForbidden();
    }

    public function test_the_monument_image_is_configurable_with_a_default_drawing(): void
    {
        Storage::fake('public');
        $admin = User::factory()->adminNational()->create();
        $mairieModel = Mairie::factory()->create();
        $agent = User::factory()->state(['mairie_id' => $mairieModel->id])->withRole(RoleName::Maire)->create();
        $demande = DemandeVgt::factory()->create(['mairie_id' => $mairieModel->id, 'status' => 'payee']);

        $this->actingAs($agent)->get($this->cardUrl($demande, 'officiel'))->assertOk()
            ->assertSee('viewBox="0 0 100 150"', false)->assertDontSee('alt="Monument"', false);

        $this->actingAs($admin)->post("/mairies/{$mairieModel->id}/monument-vgt", ['monument' => UploadedFile::fake()->image('monument.jpg', 736, 1104)])
            ->assertRedirect('/demandes-vgt')->assertSessionHas('status');

        $path = $mairieModel->fresh()->monument_path;
        Storage::disk('public')->assertExists($path);

        foreach (['officiel', 'jaune'] as $model) {
            $this->actingAs($agent)->get($this->cardUrl($demande, $model))->assertOk()
                ->assertSee('storage/'.$path, false)->assertSee('alt="Monument"', false)->assertDontSee('viewBox="0 0 100 150"', false);
        }

        $this->actingAs($admin)->delete("/mairies/{$mairieModel->id}/monument-vgt")->assertRedirect('/demandes-vgt');
        $this->assertNull($mairieModel->fresh()->monument_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_an_invalid_monument_image_or_an_unauthorised_user_is_rejected(): void
    {
        Storage::fake('public');
        $admin = User::factory()->adminNational()->create();
        $mairieModel = Mairie::factory()->create();

        $this->actingAs($admin)->from('/demandes-vgt')->post("/mairies/{$mairieModel->id}/monument-vgt", ['monument' => UploadedFile::fake()->create('m.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('monument');
        $this->actingAs($admin)->from('/demandes-vgt')->post("/mairies/{$mairieModel->id}/monument-vgt", ['monument' => UploadedFile::fake()->image('big.jpg', 800, 1200)->size(3072)])->assertSessionHasErrors('monument');
        $this->assertNull($mairieModel->fresh()->monument_path);

        $this->actingAs(User::factory()->mairie()->create())->post("/mairies/{$mairieModel->id}/monument-vgt", ['monument' => UploadedFile::fake()->image('m.jpg', 300, 400)])->assertForbidden();
    }

    public function test_the_card_settings_are_also_reachable_from_the_mairies_screen(): void
    {
        $admin = User::factory()->adminNational()->create();
        $mairieModel = Mairie::factory()->create(['name' => 'Mairie de Test']);

        $this->actingAs($admin)->get('/mairies')->assertOk()
            ->assertSee('cardSettings-'.$mairieModel->id, false)
            ->assertSee('Carte VGT — Mairie de Test')
            ->assertSee(route('mairies.logo.update', $mairieModel), false)
            ->assertSee('name="back" value="mairies"', false);
    }
}
