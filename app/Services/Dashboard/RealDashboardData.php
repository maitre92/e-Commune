<?php

namespace App\Services\Dashboard;

use App\Enums\DemandeVgtStatus;
use App\Enums\RoleName;
use App\Models\ActeMariage;
use App\Models\ActeNaissance;
use App\Models\ActivityLog;
use App\Models\DemandeVgt;
use App\Models\ExtraitNaissance;
use App\Models\Mairie;
use App\Models\Moto;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Tableau de bord : chiffres des vignettes et de l'état civil, tâches à traiter, activité récente. Tout passe par
 * les modèles cloisonnés (BelongsToMairie) : un agent ne voit que sa mairie, l'administration voit tout.
 */
class RealDashboardData
{
    /**
     * @return array{cards: list<array<string, mixed>>, quick: list<array<string, mixed>>, tables: list<array<string, mixed>>, alerts: list<array<string, mixed>>, hero: array<string, mixed>, tasks: list<array<string, mixed>>, pipeline: array<string, mixed>|null}
     */
    public function for(User $user): array
    {
        $cards = [];
        $alerts = [];
        $tables = [];

        if ($user->isSuperadmin()) {
            $activeSuperadmins = User::role(RoleName::Superadmin->value)->active()->count();

            $cards[] = $this->card('Mairies', (string) Mairie::count(), Mairie::active()->count().' active(s)', 'bx bx-building-house', 'warning', true);
            $cards[] = $this->card('Utilisateurs', (string) User::count(), User::active()->count().' actif(s)', 'bx bx-group', 'info');
            $cards[] = $this->card('Connexions aujourd\'hui', (string) ActivityLog::where('action', 'auth.login')->where('created_at', '>=', now()->startOfDay())->count(), null, 'bx bx-log-in-circle', 'dark');

            if ($activeSuperadmins < 2) {
                $alerts[] = ['class' => 'alert-warning', 'icon' => 'bx bx-error', 'title' => 'Un seul superadmin actif.', 'text' => 'Créez le second compte superadmin (un compte individuel par personne) : le dernier superadmin actif est protégé et ne peut pas être désactivé.'];
            }

            $tables[] = [
                'title' => 'DERNIÈRES ACTIONS', 'icon' => 'bx bx-list-check',
                'columns' => ['Date', 'Auteur', 'Action'],
                'rows' => ActivityLog::latest('id')->limit(8)->get()->map(fn (ActivityLog $log) => [
                    $log->created_at->format('d/m/Y H:i'), $log->user_name ?? '—', $log->description,
                ])->all(),
            ];
        } else {
            if ($user->can('users.view')) {
                $visible = User::visibleTo($user);
                $cards[] = $this->card('Utilisateurs actifs', (string) $visible->clone()->active()->count(), $visible->clone()->count().' au total', 'bx bx-group', 'primary');
            }
            if ($user->can('mairies.view')) {
                $cards[] = $this->card('Mairies', (string) Mairie::count(), Mairie::active()->count().' active(s)', 'bx bx-building-house', 'success');
            }
        }

        [$tasks, $pipeline] = $this->vignettes($user, $cards, $tables);
        $this->etatCivil($user, $cards, $tables);

        return ['cards' => $cards, 'quick' => $this->quick($user), 'tables' => $tables, 'alerts' => $alerts, 'hero' => $this->hero($user), 'tasks' => $tasks, 'pipeline' => $pipeline];
    }

    /**
     * Nombre de vignettes qui attendent une action de cet utilisateur (pastille du menu).
     */
    public function pendingDemandesCount(User $user): int
    {
        if (! $user->can('demandes-vgt.view') || $user->mairie_id === null) {
            return 0;
        }

        $counts = $this->statusCounts($user);
        $pending = 0;

        foreach (['demandes-vgt.confirm_payment' => DemandeVgtStatus::EnAttente, 'demandes-vgt.confirm_retrait' => DemandeVgtStatus::Payee] as $permission => $status) {
            if ($user->can($permission)) {
                $pending += (int) ($counts[$status->value] ?? 0);
            }
        }

        return $pending;
    }

    /**
     * @return array<string, mixed>
     */
    private function hero(User $user): array
    {
        $now = now()->locale('fr');

        return [
            'greeting' => ($now->hour >= 18 ? 'Bonsoir' : 'Bonjour').', '.$user->name,
            'subtitle' => trim(($user->roleName()?->label() ?? '').($user->mairie ? ' · '.$user->mairie->name : '')),
            'date' => ucfirst($now->translatedFormat('l j F Y')),
        ];
    }

    /**
     * Vignettes : chiffres, tâches (paiements à confirmer, cartes à remettre) et parcours par statut.
     *
     * @param  list<array<string, mixed>>  $cards
     * @param  list<array<string, mixed>>  $tables
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>|null}
     */
    private function vignettes(User $user, array &$cards, array &$tables): array
    {
        $tasks = [];

        if ($user->can('motos.view')) {
            $motos = Moto::count();
            $current = Moto::where('vgt_year', '>=', (int) date('Y'))->count();
            $cards[] = $this->card('Motos enregistrées', (string) $motos, $current.' avec vignette '.date('Y'), 'bx bx-cycling', 'info');
        }

        if (! $user->can('demandes-vgt.view')) {
            return [$tasks, null];
        }

        $counts = $this->statusCounts($user);
        $total = (int) $counts->sum();
        $count = fn (DemandeVgtStatus $status): int => (int) ($counts[$status->value] ?? 0);
        $link = fn (DemandeVgtStatus $status): array => ['route' => 'demandes-vgt.index', 'params' => ['statut' => $status->value]];

        if ($user->can('demandes-vgt.confirm_payment') && $user->mairie_id) {
            $tasks[] = ['label' => 'Paiements à confirmer', 'hint' => 'Vignettes enregistrées, non payées', 'count' => $count(DemandeVgtStatus::EnAttente), 'icon' => 'bx bx-money', 'color' => 'warning'] + $link(DemandeVgtStatus::EnAttente);
        }
        if ($user->can('demandes-vgt.confirm_retrait') && $user->mairie_id) {
            $tasks[] = ['label' => 'Cartes à remettre', 'hint' => 'Payées, prêtes à imprimer', 'count' => $count(DemandeVgtStatus::Payee), 'icon' => 'bx bx-id-card', 'color' => 'success'] + $link(DemandeVgtStatus::Payee);
        }

        $cards[] = $this->card('Vignettes '.date('Y'), (string) DemandeVgt::visibleFor($user)->where('vgt_year', (int) date('Y'))->count(), $total.' au total', 'bx bx-id-card', 'warning', true);

        $collected = DemandeVgt::visibleFor($user)->whereIn('status', [DemandeVgtStatus::Payee->value, DemandeVgtStatus::Retiree->value])
            ->where('payment_confirmed_at', '>=', now()->startOfMonth())->selectRaw('coalesce(sum(base_amount + surcharge_amount), 0) as amount')->value('amount');
        $cards[] = $this->card('Encaissé ce mois', number_format((int) $collected, 0, ',', ' ').' FCFA', 'Paiements de vignettes confirmés', 'bx bx-wallet', 'dark');

        $pipeline = [
            'total' => $total,
            'segments' => collect(DemandeVgtStatus::cases())->map(fn (DemandeVgtStatus $status) => [
                'label' => $status->label(), 'count' => $count($status), 'class' => $status->value,
                'percent' => $total > 0 ? round($count($status) / $total * 100, 1) : 0,
            ] + $link($status))->all(),
        ];

        $tables[] = [
            'title' => 'DERNIÈRES VIGNETTES', 'icon' => 'bx bx-id-card',
            'columns' => ['Date', 'Moto', 'Année', 'Statut'],
            'rows' => DemandeVgt::with('moto')->visibleFor($user)->latest('id')->limit(8)->get()->map(fn (DemandeVgt $demande) => [
                $demande->created_at->format('d/m/Y'), $demande->moto?->identifier() ?? '—', (string) $demande->vgt_year,
                ['badge' => $demande->status->label(), 'class' => $demande->status->badgeClass()],
            ])->all(),
        ];

        return [$tasks, $pipeline];
    }

    /**
     * État civil : actes enregistrés, extraits délivrés, derniers actes.
     *
     * @param  list<array<string, mixed>>  $cards
     * @param  list<array<string, mixed>>  $tables
     */
    private function etatCivil(User $user, array &$cards, array &$tables): void
    {
        if ($user->can('actes-mariage.view')) {
            $cards[] = $this->card('Actes de mariage', (string) ActeMariage::visibleFor($user)->count(), ActeMariage::visibleFor($user)->where('created_at', '>=', now()->startOfMonth())->count().' saisi(s) ce mois', 'bx bx-heart', 'danger');
        }

        if (! $user->can('actes-naissance.view')) {
            return;
        }

        $cards[] = $this->card('Actes de naissance', (string) ActeNaissance::visibleFor($user)->count(), ActeNaissance::visibleFor($user)->where('created_at', '>=', now()->startOfMonth())->count().' saisi(s) ce mois', 'bx bx-baby-carriage', 'primary');
        $cards[] = $this->card('Extraits délivrés ce mois', (string) $this->extraits($user)->where('delivered_at', '>=', now()->startOfMonth()->toDateString())->count(), $this->extraits($user)->count().' au total', 'bx bx-printer', 'success');

        $tables[] = [
            'title' => 'DERNIERS ACTES DE NAISSANCE', 'icon' => 'bx bx-baby-carriage',
            'columns' => ['N°', 'Enfant', 'Né(e) le'],
            'rows' => ActeNaissance::visibleFor($user)->latest('id')->limit(8)->get()->map(fn (ActeNaissance $acte) => [
                "{$acte->act_number}/{$acte->act_year}", $acte->fullName(), $acte->birth_date->format('d/m/Y'),
            ])->all(),
        ];
    }

    /** @return Collection<string, int> */
    /** Extraits : tous pour le maire, ceux qu'il a délivrés pour un agent. */
    private function extraits(User $user)
    {
        return ExtraitNaissance::query()->when(! $user->can('actes-naissance.view_all'), fn ($query) => $query->where('user_id', $user->id));
    }

    private function statusCounts(User $user)
    {
        return DemandeVgt::visibleFor($user)->toBase()->selectRaw('status, count(*) as total')->whereNull('deleted_at')->groupBy('status')->pluck('total', 'status');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function quick(User $user): array
    {
        $candidates = [
            ['demandes-vgt.index', 'demandes-vgt.view', 'Vignettes', 'bx bx-id-card'],
            ['actes-naissance.index', 'actes-naissance.view', 'Actes de naissance', 'bx bx-baby-carriage'],
            ['actes-mariage.index', 'actes-mariage.view', 'Actes de mariage', 'bx bx-heart'],
            ['caisse.index', 'caisse.view_own', 'Caisse', 'bx bx-wallet'],
            ['users.index', 'users.view', 'Agents', 'bx bx-group'],
            ['mairies.index', 'mairies.view', 'Mairies', 'bx bx-building-house'],
            ['audit.index', 'audit.view', 'Audit', 'bx bx-list-check'],
        ];

        $quick = [];
        // Le maire crée ses agents depuis l'accueil, en un clic.
        if ($user->roleName() === RoleName::Maire && $user->can('users.create')) {
            $quick[] = ['label' => 'Créer un agent', 'icon' => 'bx bx-user-plus', 'route' => 'users.index', 'params' => ['nouveau' => 1]];
        }
        foreach ($candidates as [$route, $permission, $label, $icon]) {
            if ($user->can($permission)) {
                $quick[] = ['label' => $label, 'icon' => $icon, 'route' => $route, 'params' => []];
            }
        }

        return $quick;
    }

    /**
     * @return array<string, mixed>
     */
    private function card(string $label, string $value, ?string $sub, string $icon, string $color, bool $darkText = false): array
    {
        return ['label' => $label, 'value' => $value, 'sub' => $sub, 'icon' => $icon, 'color' => $color, 'module' => null, 'darkText' => $darkText];
    }
}
