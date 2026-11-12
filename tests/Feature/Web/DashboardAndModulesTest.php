<?php

namespace Tests\Feature\Web;

use App\Models\ActeNaissance;
use App\Models\ActivityLog;
use App\Models\DemandeVgt;
use App\Models\ExtraitNaissance;
use App\Models\Mairie;
use App\Models\Moto;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use App\Support\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Tableau de bord (toujours réel), sidebar (vignettes, état civil, administration, Paramètres en bas) et
 * absence de modules « à venir » : e-Commune se limite aux vignettes et aux actes de naissance.
 */
class DashboardAndModulesTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
    }

    /** Le HTML de la sidebar seule (le corps de la page peut contenir les mêmes mots). */
    private function sidebar(string $html): string
    {
        preg_match('#<div class="sidebar-wrapper".*?</ul>\s*</div>#s', $html, $match);

        return $match[0] ?? '';
    }

    public function test_the_superadmin_supervision_shows_real_figures_and_the_single_superadmin_alert(): void
    {
        Mairie::factory()->count(2)->create();

        $this->actingAs($this->superadmin())->get('/')
            ->assertOk()
            ->assertSee('Supervision plateforme')
            ->assertSee('Un seul superadmin actif')
            ->assertSee('Vignettes '.date('Y'))
            ->assertSee('Actes de naissance')
            ->assertDontSee('Données fictives');
    }

    public function test_no_alert_when_two_superadmins_are_active(): void
    {
        $this->superadmin();
        $second = $this->superadmin();

        $this->actingAs($second)->get('/')->assertOk()->assertDontSee('Un seul superadmin actif');
    }

    public function test_an_agent_sees_their_tasks_and_figures_of_their_mairie_only(): void
    {
        $agent = User::factory()->mairie()->create();
        DemandeVgt::factory()->count(2)->create(['mairie_id' => $agent->mairie_id, 'user_id' => $agent->id, 'status' => 'en_attente']);
        DemandeVgt::factory()->create(['mairie_id' => $agent->mairie_id, 'user_id' => $agent->id, 'status' => 'payee']);
        DemandeVgt::factory()->count(5)->create(['status' => 'en_attente']); // autre mairie
        ActeNaissance::factory()->count(3)->create(['mairie_id' => $agent->mairie_id, 'user_id' => $agent->id]);
        ActeNaissance::factory()->count(4)->create(); // autre mairie

        $page = $this->actingAs($agent)->get('/')->assertOk()
            ->assertSee($agent->name)->assertSee('À traiter maintenant')
            ->assertSee('Paiements à confirmer')->assertSee('Cartes à remettre')
            ->assertSee('PARCOURS DES VIGNETTES', false)
            ->assertSee('DERNIERS ACTES DE NAISSANCE')
            ->assertDontSee('Utilisateurs actifs');

        $tasks = collect(app(DashboardService::class)->for($agent)['tasks'])->pluck('count', 'label');
        $this->assertSame(2, $tasks['Paiements à confirmer']);
        $this->assertSame(1, $tasks['Cartes à remettre']);
        $this->assertStringContainsString('3', $page->getContent());
    }

    public function test_the_encaisse_ce_mois_card_sums_confirmed_payments_of_the_mairie(): void
    {
        $agent = User::factory()->mairie()->create();
        DemandeVgt::factory()->create(['mairie_id' => $agent->mairie_id, 'user_id' => $agent->id, 'status' => 'payee', 'base_amount' => 6000, 'surcharge_amount' => 2000, 'payment_confirmed_at' => now()]);
        DemandeVgt::factory()->create(['mairie_id' => $agent->mairie_id, 'user_id' => $agent->id, 'status' => 'retiree', 'base_amount' => 5000, 'payment_confirmed_at' => now()]);
        DemandeVgt::factory()->create(['status' => 'payee', 'base_amount' => 99000, 'payment_confirmed_at' => now()]); // autre mairie

        $this->actingAs($agent)->get('/')->assertOk()->assertSee('13 000 FCFA');
    }

    public function test_the_extract_counter_only_counts_this_month(): void
    {
        $agent = User::factory()->mairie()->create();
        $acte = ActeNaissance::factory()->create(['mairie_id' => $agent->mairie_id]);
        foreach ([now(), now()->subMonths(2)] as $date) {
            ExtraitNaissance::create([
                'mairie_id' => $agent->mairie_id, 'acte_naissance_id' => $acte->id, 'user_id' => $agent->id, 'officer_name' => 'X', 'officer_title' => 'Y',
                'centre_type' => 'principal', 'centre_name' => 'Z', 'delivered_at' => $date->toDateString(),
            ]);
        }

        $cards = collect(app(DashboardService::class)->for($agent)['cards'])->pluck('value', 'label');
        $this->actingAs($agent);
        $cards = collect(app(DashboardService::class)->for($agent)['cards'])->pluck('value', 'label');
        $this->assertSame('1', $cards['Extraits délivrés ce mois']);
    }

    public function test_the_list_prefilters_by_status_from_the_dashboard_link(): void
    {
        $this->actingAs(User::factory()->mairie()->create())->get('/demandes-vgt?statut=payee')->assertOk()
            ->assertSee('data-key="payee"', false)->assertSee('URLSearchParams', false);
    }

    public function test_the_sidebar_groups_entries_by_domain_and_badges_pending_vignettes(): void
    {
        $agent = User::factory()->mairie()->create();
        DemandeVgt::factory()->count(3)->create(['mairie_id' => $agent->mairie_id, 'user_id' => $agent->id, 'status' => 'en_attente']);
        DemandeVgt::factory()->create(['mairie_id' => $agent->mairie_id, 'user_id' => $agent->id, 'status' => 'retiree']); // plus rien à faire

        $sidebar = $this->sidebar($this->actingAs($agent)->get('/')->getContent());
        $this->assertMatchesRegularExpression('#Vignettes.*Actes de naissance.*Caisse#s', $sidebar);
        $this->assertStringContainsString('title="3 à traiter"', $sidebar);
    }

    public function test_the_sidebar_has_no_badge_when_nothing_is_pending(): void
    {
        $this->actingAs(User::factory()->mairie()->create())->get('/')->assertOk()->assertDontSee('nv-badge', false);
    }

    public function test_the_moto_registry_is_no_longer_in_the_menu(): void
    {
        $agent = User::factory()->mairie()->create();

        $sidebar = $this->sidebar($this->actingAs($agent)->get('/')->getContent());
        $this->assertStringNotContainsString('Registre motos', $sidebar);
        $this->assertStringNotContainsString('href="'.route('motos.index').'"', $sidebar);
        // Toujours consultable par son adresse.
        $this->actingAs($agent)->get('/motos')->assertOk();
    }

    public function test_out_of_scope_modules_are_gone(): void
    {
        $page = $this->actingAs($this->superadmin())->get('/')->assertOk();

        $page->assertDontSee('Modules à venir')->assertDontSee('Commissariat')->assertDontSee('Motos retrouvées')
            ->assertDontSee('Déclarations')->assertDontSee('Contrôle de police')->assertDontSee('Notifications SMS');
        $this->assertSame([], config('planned_modules'));
        $this->get('/modules/paiements')->assertNotFound();
        $this->get('/commissariats')->assertNotFound();
        $this->getJson('/api/v1/informations')->assertNotFound();
        $this->getJson('/api/v1/controles?matricule=X')->assertNotFound();
    }

    public function test_each_role_sees_its_own_entries(): void
    {
        $agentSidebar = $this->sidebar($this->actingAs(User::factory()->mairie()->create())->get('/')->getContent());
        foreach (['Vignettes', 'Actes de naissance', 'Caisse'] as $label) {
            $this->assertStringContainsString($label, $agentSidebar);
        }
        $this->assertStringNotContainsString('Paramètres', $agentSidebar);

        // L'admin national supervise : vignettes et actes en lecture, pas le registre des motos.
        $adminSidebar = $this->sidebar($this->actingAs(User::factory()->adminNational()->create())->get('/')->getContent());
        $this->assertStringContainsString('Vignettes', $adminSidebar);
        $this->assertStringContainsString('Actes de naissance', $adminSidebar);
        $this->assertStringNotContainsString('Registre motos', $adminSidebar);
        $this->assertStringContainsString('Paramètres', $adminSidebar);
    }

    public function test_the_settings_entry_sits_below_every_other_menu(): void
    {
        $sidebar = $this->sidebar($this->actingAs($this->superadmin())->get('/')->getContent());

        $this->assertStringContainsString('Paramètres', $sidebar);
        $this->assertStringNotContainsString('>Rôles<', $sidebar);
        $this->assertGreaterThan(strrpos($sidebar, 'Actes de naissance'), strrpos($sidebar, 'Paramètres'));
    }

    public function test_permissions_audit_and_system_live_in_the_settings_menu(): void
    {
        $superadmin = $this->superadmin();

        $sidebar = $this->sidebar($this->actingAs($superadmin)->get('/')->getContent());
        foreach (['Attribution', 'bx-shield-alt-2', 'bx-user-check', 'bx-list-check', 'bx-server'] as $needle) {
            $this->assertStringNotContainsString($needle, $sidebar);
        }

        $this->get('/roles')->assertOk()->assertSee('PARAMÈTRES')
            ->assertSee('Permissions')->assertSee('Attribution des permissions')->assertSee('Mairies')->assertDontSee('Commissariats')
            ->assertSee(route('audit.index'))->assertSee(route('system.index'));
    }

    public function test_settings_stays_highlighted_on_every_settings_page(): void
    {
        $superadmin = $this->superadmin();

        foreach (['/roles', '/permissions', '/user-permissions', '/audit', '/system', '/users', '/mairies'] as $uri) {
            $sidebar = $this->sidebar($this->actingAs($superadmin)->get($uri)->getContent());
            $this->assertMatchesRegularExpression('#mm-active[^>]*>\s*<div class="parent-icon"><i class=\'bx bx-cog\'#', $sidebar, "Paramètres doit être actif sur $uri");
        }
    }

    public function test_the_settings_entry_target_is_not_hardcoded_to_roles(): void
    {
        $registry = app(ModuleRegistry::class);

        $this->assertNull($registry->firstAccessibleSettingsItem(User::factory()->mairie()->create()));
        $this->assertSame('users.index', $registry->firstAccessibleSettingsItem(User::factory()->adminNational()->create())['route']);
    }

    public function test_the_profile_shows_role_and_institution(): void
    {
        $agent = User::factory()->mairie(Mairie::factory()->create(['name' => 'Mairie de Test']))->create(['name' => 'Agent Test']);

        $this->actingAs($agent)->get('/profile')->assertOk()->assertSee('Agent Test')->assertSee('Agent polyvalent')->assertSee('Mairie de Test');
    }

    public function test_pages_never_reference_the_internet(): void
    {
        $agent = User::factory()->mairie()->create();
        $acte = ActeNaissance::factory()->create(['mairie_id' => $agent->mairie_id]);
        $extrait = ExtraitNaissance::create([
            'mairie_id' => $agent->mairie_id, 'acte_naissance_id' => $acte->id, 'officer_name' => 'X', 'officer_title' => 'Y',
            'centre_type' => 'principal', 'centre_name' => 'Z', 'delivered_at' => now()->toDateString(),
        ]);
        Moto::factory()->create(['mairie_id' => $agent->mairie_id]);

        $pages = $this->get('/login')->getContent();
        $this->actingAs($agent);
        foreach (['/', '/profile', '/motos', '/proprietaires', '/demandes-vgt', '/actes-naissance', "/actes-naissance/extraits/{$extrait->id}"] as $uri) {
            $pages .= $this->get($uri)->assertOk()->getContent();
        }

        preg_match_all('#(?:src|href|action)=["\'](https?://[^"\']+)#', $pages, $matches);
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $external = array_values(array_unique(array_filter($matches[1], fn ($url) => parse_url($url, PHP_URL_HOST) !== $appHost)));
        $this->assertSame([], $external, 'Ressource externe dans une page.');
        foreach (['fonts.googleapis', 'unpkg.com', 'jsdelivr', 'cdnjs', 'code.jquery.com'] as $cdn) {
            $this->assertStringNotContainsString($cdn, $pages);
        }
    }

    public function test_the_audit_table_of_the_supervision_lists_real_entries(): void
    {
        $superadmin = $this->superadmin();
        $this->actingAs($superadmin);
        User::factory()->adminNational()->create(['name' => 'Cible Test']);

        $this->get('/')->assertOk()->assertSee('DERNIÈRES ACTIONS')->assertSee('Cible Test');
        $this->assertGreaterThan(0, ActivityLog::count());
    }
}
