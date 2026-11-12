{{-- État journalier de la caisse (A4 imprimable) : recettes du jour par agent et par type, versements reçus. --}}
@php
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ').' FCFA';
    $byAgent = $recettes->groupBy('user_id');
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>État de caisse du {{ $day->format('d/m/Y') }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #111; background: #eee; }
        .sheet { width: 210mm; min-height: 297mm; margin: 12px auto; padding: 14mm 14mm; background: #fff; }
        h1 { font-size: 15pt; margin: 0 0 2mm; text-align: center; }
        .sub { text-align: center; color: #444; margin-bottom: 6mm; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
        th, td { border: 1px solid #999; padding: 1.6mm 2mm; text-align: left; }
        th { background: #f0f0f0; font-size: 9pt; text-transform: uppercase; }
        td.n { text-align: right; white-space: nowrap; }
        .total td { font-weight: bold; background: #f7f7f7; }
        .signs { display: flex; justify-content: space-between; margin-top: 14mm; }
        .signs div { width: 45%; text-align: center; border-top: 1px solid #333; padding-top: 2mm; }
        .toolbar { text-align: center; padding: 10px; background: #123a63; }
        .toolbar button { font: inherit; border: 0; border-radius: 6px; padding: 7px 14px; cursor: pointer; font-weight: 600; }
        @media print { body { background: #fff; } .toolbar { display: none; } .sheet { margin: 0; } }
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Imprimer l'état</button></div>
    <div class="sheet">
        <h1>ÉTAT JOURNALIER DE CAISSE</h1>
        <div class="sub">{{ $mairie?->name ?? 'Toutes les mairies' }} — {{ ucfirst($day->locale('fr')->translatedFormat('l j F Y')) }}</div>

        <table>
            <thead><tr><th>Agent</th><th>Vignettes</th><th>Extraits naissance</th><th>Extraits mariage</th><th>Recettes</th><th>Montant</th></tr></thead>
            <tbody>
                @foreach ($byAgent as $rows)
                    <tr>
                        <td>{{ $rows->first()->agent?->name }}</td>
                        <td class="n">{{ $rows->where('type', \App\Enums\RecetteType::Vignette)->count() }}</td>
                        <td class="n">{{ $rows->where('type', \App\Enums\RecetteType::Extrait)->count() }}</td>
                        <td class="n">{{ $rows->where('type', \App\Enums\RecetteType::ExtraitMariage)->count() }}</td>
                        <td class="n">{{ $rows->count() }}</td>
                        <td class="n">{{ $fcfa($rows->sum('amount')) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td>Total</td>
                    <td class="n">{{ $recettes->where('type', \App\Enums\RecetteType::Vignette)->count() }}</td>
                    <td class="n">{{ $recettes->where('type', \App\Enums\RecetteType::Extrait)->count() }}</td>
                    <td class="n">{{ $recettes->where('type', \App\Enums\RecetteType::ExtraitMariage)->count() }}</td>
                    <td class="n">{{ $recettes->count() }}</td>
                    <td class="n">{{ $fcfa($recettes->sum('amount')) }}</td>
                </tr>
            </tbody>
        </table>

        <table>
            <thead><tr><th>Quittance</th><th>Heure</th><th>Objet</th><th>Agent</th><th>Montant</th></tr></thead>
            <tbody>
                @forelse ($recettes as $recette)
                    <tr>
                        <td>{{ $recette->quittance() }}</td>
                        <td>{{ $recette->encaissee_at->format('H:i') }}</td>
                        <td>{{ $recette->label }}</td>
                        <td>{{ $recette->agent?->name }}</td>
                        <td class="n">{{ $fcfa($recette->amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Aucune recette ce jour.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($versements->isNotEmpty())
            <table>
                <thead><tr><th>Versement reçu à</th><th>De</th><th>Par</th><th>Montant</th></tr></thead>
                <tbody>
                    @foreach ($versements as $versement)
                        <tr>
                            <td>{{ $versement->received_at->format('H:i') }}</td>
                            <td>{{ $versement->agent?->name }}</td>
                            <td>{{ $versement->receiver?->name }}</td>
                            <td class="n">{{ $fcfa($versement->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="signs"><div>Le caissier / régisseur</div><div>Le Maire</div></div>
    </div>
</body>
</html>
