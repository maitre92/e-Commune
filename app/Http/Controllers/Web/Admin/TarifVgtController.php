<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\SaveTarifsVgtRequest;
use App\Models\TarifVgt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Tarifs des vignettes de la mairie (catégories de cylindrée) et pénalité de retard, enregistrés en une fois
 * depuis l'écran Vignettes (bouton « Tarifs »). Le maire par défaut ; il peut déléguer la permission
 * `demandes-vgt.manage_tarifs` à un agent (fenêtre de modification de l'agent). Chaque changement est journalisé
 * (LogsActivity sur TarifVgt et Mairie). Les vignettes déjà délivrées gardent leur montant.
 */
class TarifVgtController extends Controller
{
    public function save(SaveTarifsVgtRequest $request): RedirectResponse
    {
        $mairie = $request->user()->mairie;
        $data = $request->validated();

        DB::transaction(function () use ($mairie, $data) {
            $grid = TarifVgt::gridFor($mairie->id)->keyBy('id');

            foreach ($data['tarifs'] ?? [] as $id => $row) {
                $grid->get((int) $id)?->update($row);
            }

            if (filled($data['new_label'] ?? null)) {
                TarifVgt::create([
                    'mairie_id' => $mairie->id,
                    'type_or_brand' => $data['new_label'],
                    'cylinder_min' => $data['new_cylinder_min'] ?? null,
                    'cylinder_max' => $data['new_cylinder_max'] ?? null,
                    'amount' => (int) $data['new_amount'],
                    'is_active' => true,
                ]);
            }

            $mairie->update([
                'vgt_deadline' => sprintf('%02d-%02d', $data['deadline_month'], $data['deadline_day']),
                'vgt_late_mode' => $data['vgt_late_mode'],
                'vgt_late_amount' => (int) ($data['vgt_late_amount'] ?? $mairie->vgt_late_amount),
            ]);
        });

        return redirect()->route('demandes-vgt.index')->with('status', 'Tarifs des vignettes enregistrés.');
    }
}
