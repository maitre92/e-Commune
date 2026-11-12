<?php

namespace App\Services\Dashboard;

use App\Models\User;

/**
 * Construit les données du tableau de bord d'un utilisateur (toujours réelles, cloisonnées par mairie).
 */
class DashboardService
{
    public function __construct(private readonly RealDashboardData $real) {}

    /**
     * @return array{mock: bool, cards: list<array<string, mixed>>, quick: list<array<string, mixed>>, tables: list<array<string, mixed>>, alerts: list<array<string, mixed>>, hero: array<string, mixed>, tasks: list<array<string, mixed>>, pipeline: array<string, mixed>|null}
     */
    public function for(User $user): array
    {
        return ['mock' => false] + $this->real->for($user);
    }
}
