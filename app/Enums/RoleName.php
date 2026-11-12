<?php

namespace App\Enums;

/**
 * Les rôles de e-Commune. Un utilisateur a exactement un rôle. Dans une mairie : le maire (gère ses agents, la
 * caisse et les réglages) et ses agents, chacun selon sa fonction.
 * Niveaux, plafond de rôle et canaux vivent dans config/role_hierarchy.php et config/channels.php.
 */
enum RoleName: string
{
    case Superadmin = 'superadmin';
    case AdminNational = 'admin_national';
    case Maire = 'maire';
    /** Secrétaire général : supervise la caisse et consulte vignettes et actes. */
    case SecretaireGeneral = 'secretaire_general';
    /** Agent polyvalent : vignettes et actes de naissance. */
    case Mairie = 'mairie';
    case AgentVignette = 'agent_vignette';
    case AgentEtatCivil = 'agent_etat_civil';
    case Caissier = 'caissier';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::AdminNational => 'Administrateur national',
            self::Maire => 'Maire',
            self::SecretaireGeneral => 'Secrétaire général',
            self::Mairie => 'Agent polyvalent',
            self::AgentVignette => 'Vendeur de vignettes',
            self::AgentEtatCivil => 'Agent d\'état civil',
            self::Caissier => 'Caissier / régisseur',
        };
    }

    public function level(): int
    {
        return (int) config("role_hierarchy.levels.{$this->value}", 0);
    }

    /**
     * Rôles que ce rôle peut créer et gérer (plafond de rôle).
     *
     * @return list<self>
     */
    public function manages(): array
    {
        return array_map(self::from(...), config("role_hierarchy.manages.{$this->value}", []));
    }

    public function canManage(self $target): bool
    {
        return in_array($target, $this->manages(), true);
    }

    /**
     * Type d'institution imposé à ce rôle : 'mairie' ou null (aucune).
     */
    public function institution(): ?string
    {
        return config("role_hierarchy.institution.{$this->value}");
    }

    /** Vrai si ce rôle peut utiliser ce canal ('web' ou 'api', config/channels.php). */
    public function allowsChannel(string $channel): bool
    {
        return in_array($this->value, config("channels.{$channel}", []), true);
    }

    /**
     * Canaux autorisés, pour l'affichage : « Web », « Web + mobile »…
     */
    public function channelsLabel(): string
    {
        $labels = array_filter(['Web' => $this->allowsChannel('web'), 'mobile' => $this->allowsChannel('api')]);

        return $labels === [] ? '—' : implode(' + ', array_keys($labels));
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
