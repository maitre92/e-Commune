<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UpdateCivilSettingsRequest;
use App\Models\Mairie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Réglages de l'état civil de la mairie depuis l'application mobile (écran « Réglages de l'état civil ») :
 * mêmes champs et même validation que la fenêtre du Web (UpdateCivilSettingsRequest). Réservé aux comptes qui
 * ont `actes-naissance.configure` ou `actes-mariage.configure` (maire, agent polyvalent, délégation).
 */
class EtatCivilSettingsController extends Controller
{
    /** Champs renvoyés et acceptés : ceux du formulaire Web. */
    private const FIELDS = [
        'civil_officer_name', 'civil_officer_title', 'civil_centre_type', 'civil_centre_name',
        'civil_region', 'civil_cercle', 'civil_arrondissement', 'civil_commune', 'civil_birth_place',
        'civil_extract_price', 'civil_marriage_extract_price',
        'civil_print_mode', 'civil_print_offset_x', 'civil_print_offset_y',
    ];

    public function show(Request $request): JsonResponse
    {
        $mairie = $request->user()->mairie;
        abort_if($mairie === null, 422, 'Ce compte n\'est rattaché à aucune mairie.');

        return response()->json(['data' => $this->payload($mairie)]);
    }

    public function update(UpdateCivilSettingsRequest $request): JsonResponse
    {
        $mairie = $request->user()->mairie;
        $mairie->update($request->validated());

        return response()->json([
            'message' => 'Réglages de l\'état civil enregistrés.',
            'data' => $this->payload($mairie->refresh()),
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Mairie $mairie): array
    {
        $values = [];
        foreach (self::FIELDS as $field) {
            $value = $mairie->{$field};
            $values[$field] = $value instanceof \BackedEnum ? $value->value : $value;
        }

        // Valeurs proposées par défaut, comme dans la fenêtre du Web.
        $values['civil_centre_type'] ??= 'principal';
        $values['civil_officer_title'] ??= 'Officier de l\'état civil';
        $values['civil_print_mode'] ??= 'blanc';

        return $values + ['mairie' => $mairie->name, 'pret' => $mairie->civilReady()];
    }
}
