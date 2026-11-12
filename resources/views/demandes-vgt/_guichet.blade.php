{{-- Guichet vignette, comme au guichet réel : l'agent recopie le N° Engin de l'ancienne vignette ou
     des papiers de la moto, touche la cylindrée (présélectionnée si la moto est connue), le citoyen paie, la
     vignette s'imprime. Le montant affiché suit les tarifs et la pénalité de retard réglés par la mairie. --}}
@php
    $guichetFailed = $errors->any() && old('guichet');
    $g = fn (string $field, $default = '') => $guichetFailed ? old($field, $default) : $default;
    $currentYear = (int) date('Y');
    $years = range($currentYear + 1, $currentYear - 5);
    $lateMode = $vgtMairie?->vgt_late_mode ?? \App\Enums\VgtLateMode::Double;
    $lateYears = $vgtMairie ? collect($years)->filter(fn ($y) => \App\Services\Vgt\DemandeVgtPricing::isLate($vgtMairie, $y))->values() : collect();
    $selectedTarif = $g('type_or_brand', $guichetTarifs->count() === 1 ? $guichetTarifs->first()->type_or_brand : '');
    $fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ');
@endphp
<div class="card">
    <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h6 class="mb-0 text-white"><i class='bx bx-store me-2'></i>GUICHET — VIGNETTE {{ $currentYear }}</h6>
        @can('manageTarifs', \App\Models\DemandeVgt::class)
            <button type="button" class="btn btn-light btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#tarifsVgtModal">
                <i class='bx bx-money'></i> Tarifs et pénalités
            </button>
        @endcan
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('demandes-vgt.guichet') }}" id="guichet-form" data-lookup="{{ route('demandes-vgt.guichet-lookup') }}"
            data-late-mode="{{ $lateMode->value }}" data-late-amount="{{ (int) ($vgtMairie?->vgt_late_amount ?? 0) }}" data-late-years="{{ $lateYears->implode(',') }}">
            @csrf
            <input type="hidden" name="guichet" value="1">

            <label for="g-ch" class="form-label fw-bold">N° Engin</label>
            <input type="text" id="g-ch" name="chassis_number" value="{{ $g('chassis_number') }}"
                class="form-control form-control-lg text-uppercase fw-bold @if ($guichetFailed && $errors->has('chassis_number')) is-invalid @endif"
                style="font-size: 1.6rem; letter-spacing: 2px" placeholder="Recopier le N° Engin de l'ancienne vignette ou des papiers"
                autocomplete="off" autofocus required>
            @if ($guichetFailed && $errors->has('chassis_number'))<div class="invalid-feedback">{{ $errors->first('chassis_number') }}</div>@endif
            <div class="small mt-1 text-success d-none" id="g-known"></div>

            @if ($guichetTarifs->count() > 1)
                <div class="mt-3">
                    <div class="form-label fw-semibold mb-1">Cylindrée de la moto</div>
                    <div class="d-flex flex-wrap gap-2" id="g-tarifs">
                        @foreach ($guichetTarifs as $tarif)
                            <input type="radio" class="btn-check" name="type_or_brand" id="g-tarif-{{ $tarif->id }}" value="{{ $tarif->type_or_brand }}" data-amount="{{ $tarif->amount }}" @checked($selectedTarif === $tarif->type_or_brand)>
                            <label class="btn btn-outline-primary btn-lg" for="g-tarif-{{ $tarif->id }}">{{ $tarif->type_or_brand }} <span class="d-block small">{{ $fcfa($tarif->amount) }} F</span></label>
                        @endforeach
                    </div>
                    @if ($guichetFailed && $errors->has('type_or_brand'))<div class="text-danger small mt-1">{{ $errors->first('type_or_brand') }}</div>@endif
                </div>
            @elseif ($guichetTarifs->count() === 1)
                <input type="radio" class="d-none" name="type_or_brand" value="{{ $guichetTarifs->first()->type_or_brand }}" data-amount="{{ $guichetTarifs->first()->amount }}" checked>
            @endif

            <div class="row g-2 mt-2 align-items-end">
                <div class="col-md-4">
                    <label for="g-holder" class="form-label small mb-1">Nom du détenteur <span class="text-muted">(verso, facultatif)</span></label>
                    <input type="text" id="g-holder" name="holder_name" value="{{ $g('holder_name') }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="g-phone" class="form-label small mb-1">Numéro du détenteur <span class="text-muted">(verso)</span></label>
                    <input type="tel" id="g-phone" name="holder_phone" value="{{ $g('holder_phone') }}" class="form-control @if ($guichetFailed && $errors->has('holder_phone')) is-invalid @endif" placeholder="70 00 12 34">
                </div>
                <div class="col-6 col-md-2">
                    <label for="g-year" class="form-label small mb-1">Année</label>
                    <select id="g-year" name="vgt_year" class="form-select">
                        @foreach ($years as $year)
                            <option value="{{ $year }}" @selected((int) $g('vgt_year', $currentYear) === $year)>{{ $year }}{{ $year < $currentYear ? ' (arriéré)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 d-grid">
                    <button type="submit" class="btn btn-primary btn-lg"><i class='bx bx-printer me-1'></i>Encaisser et imprimer</button>
                </div>
            </div>

            @if ($guichetFailed && ! $errors->has('chassis_number') && ! $errors->has('type_or_brand'))
                <div class="text-danger small mt-2">{{ $errors->first() }}</div>
            @endif
            <div class="mt-3 p-2 rounded bg-light d-flex flex-wrap justify-content-between gap-2 align-items-center">
                <span class="small text-muted">
                    @if ($vgtMairie)
                        Date limite : {{ $vgtMairie->vgtDeadlineLabel() }} · après :
                        {{ match ($lateMode) { \App\Enums\VgtLateMode::Double => 'prix doublé', \App\Enums\VgtLateMode::Fixe => '+ '.$fcfa((int) $vgtMairie->vgt_late_amount).' FCFA', \App\Enums\VgtLateMode::Aucune => 'pas de pénalité' } }}
                    @endif
                </span>
                <span class="fs-5">À payer : <strong id="g-total">—</strong> <span class="small text-danger d-none" id="g-late">(dont pénalité de retard)</span></span>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('guichet-form');
            if (!form) return;
            const lateYears = (form.dataset.lateYears || '').split(',').filter(Boolean).map(Number);
            const fcfa = (n) => n.toLocaleString('fr-FR').replace(/ | /g, ' ') + ' FCFA';
            const refresh = () => {
                const chosen = form.querySelector('input[name="type_or_brand"]:checked');
                const year = Number(form.querySelector('#g-year').value);
                const late = lateYears.includes(year);
                if (!chosen) { document.getElementById('g-total').textContent = '—'; document.getElementById('g-late').classList.add('d-none'); return; }
                const base = Number(chosen.dataset.amount);
                const penalty = !late ? 0 : form.dataset.lateMode === 'double' ? base : form.dataset.lateMode === 'fixe' ? Number(form.dataset.lateAmount) : 0;
                document.getElementById('g-total').textContent = fcfa(base + penalty);
                document.getElementById('g-late').classList.toggle('d-none', penalty === 0);
            };
            form.querySelectorAll('input[name="type_or_brand"], #g-year').forEach((el) => el.addEventListener('change', refresh));
            refresh();

            // CH déjà connu : sa cylindrée est présélectionnée.
            const ch = form.querySelector('#g-ch');
            const known = document.getElementById('g-known');
            let timer;
            ch.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(async () => {
                    const value = ch.value.trim();
                    known.classList.add('d-none');
                    if (value.length < 3) return;
                    try {
                        const response = await fetch(form.dataset.lookup + '?cadre=' + encodeURIComponent(value), { headers: { Accept: 'application/json' } });
                        const data = await response.json();
                        if (!data.connue) return;
                        known.textContent = 'Moto déjà connue' + (data.derniere_vignette ? ' · dernière vignette ' + data.derniere_vignette : '') + (data.categorie ? ' · ' + data.categorie : '');
                        known.classList.remove('d-none');
                        const radio = [...form.querySelectorAll('input[name="type_or_brand"]')].find((r) => r.value === data.categorie);
                        if (radio) { radio.checked = true; refresh(); }
                    } catch (e) { /* hors ligne : l'agent choisit lui-même */ }
                }, 350);
            });
        })();
    </script>
@endpush
