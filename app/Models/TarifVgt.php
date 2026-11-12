<?php

namespace App\Models;

use App\Models\Concerns\BelongsToMairie;
use App\Models\Concerns\LogsActivity;
use Database\Factories\TarifVgtFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tarif de la vignette d'une catégorie de moto selon sa cylindrée (« 51 à 125 cm³ »…), réglé par chaque mairie
 * (le maire par défaut, ou qui il a délégué : `demandes-vgt.manage_tarifs`). Le libellé (`type_or_brand`) est la
 * catégorie inscrite sur la moto et imprimée sur la carte ; il ne change plus après création (les motos y sont
 * rattachées par ce libellé) — seuls la tranche, le montant et l'activation se modifient.
 */
#[Fillable(['mairie_id', 'type_or_brand', 'cylinder_min', 'cylinder_max', 'amount', 'is_active'])]
class TarifVgt extends Model
{
    /** @use HasFactory<TarifVgtFactory> */
    use BelongsToMairie, HasFactory, LogsActivity;

    protected $table = 'tarifs_vgt';

    public const DEFAULT_AMOUNT = 6000;

    /** Grille de départ d'une mairie (montants à ajuster par le maire). */
    public const DEFAULT_GRID = [
        ['type_or_brand' => '50 cm³ et moins', 'cylinder_min' => null, 'cylinder_max' => 50, 'amount' => 6000],
        ['type_or_brand' => '51 à 125 cm³', 'cylinder_min' => 51, 'cylinder_max' => 125, 'amount' => 6000],
        ['type_or_brand' => 'Plus de 125 cm³', 'cylinder_min' => 126, 'cylinder_max' => null, 'amount' => 12000],
    ];

    public function activityModule(): string
    {
        return 'demandes-vgt';
    }

    protected function casts(): array
    {
        return ['amount' => 'integer', 'cylinder_min' => 'integer', 'cylinder_max' => 'integer', 'is_active' => 'boolean'];
    }

    /** Ordre d'affichage : par cylindrée croissante. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByRaw('COALESCE(cylinder_min, 0)')->orderByRaw('COALESCE(cylinder_max, 65535)')->orderBy('type_or_brand');
    }

    /**
     * Grille de la mairie ; une mairie qui n'a encore aucun tarif reçoit la grille de départ.
     *
     * @return Collection<int, self>
     */
    public static function gridFor(int $mairieId, bool $activeOnly = false): Collection
    {
        $query = static::query()->acrossMairies()->where('mairie_id', $mairieId);

        if (! (clone $query)->exists()) {
            foreach (self::DEFAULT_GRID as $row) {
                static::create($row + ['mairie_id' => $mairieId, 'is_active' => true]);
            }
        }

        return $query->when($activeOnly, fn (Builder $q) => $q->where('is_active', true))->ordered()->get();
    }

    /** Tarif d'une catégorie de la mairie (par son libellé), ou null. */
    public static function findForMairie(int $mairieId, ?string $label): ?self
    {
        if ($label === null || trim($label) === '') {
            return null;
        }

        return static::query()->acrossMairies()->where('mairie_id', $mairieId)->where('type_or_brand', trim($label))->first();
    }

    /** « 51 à 125 cm³ », « jusqu'à 50 cm³ », « plus de 125 cm³ » (tranche réglée). */
    public function rangeLabel(): string
    {
        return match (true) {
            $this->cylinder_min !== null && $this->cylinder_max !== null => "{$this->cylinder_min} à {$this->cylinder_max} cm³",
            $this->cylinder_max !== null => "jusqu'à {$this->cylinder_max} cm³",
            $this->cylinder_min !== null => "à partir de {$this->cylinder_min} cm³",
            default => '',
        };
    }

    public function activityNoun(): string
    {
        return 'Tarif de vignette';
    }

    public function activityLabel(): string
    {
        return $this->type_or_brand;
    }
}
