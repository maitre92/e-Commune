@extends('layouts.admin')

@section('title', 'Actes de mariage')

{{-- Comme les actes de naissance : (1) retrouver un acte et imprimer son extrait en un clic ; (2) saisir un nouvel
     acte directement sur le modèle d'extrait d'acte de mariage — en-tête, n°, dates, officier et certification
     viennent des réglages de la mairie (saisis une fois). --}}
@php
    $user = auth()->user();
    $canSave = $acte ? $user->can('update', $acte) : $user->can('create', \App\Models\ActeMariage::class);
    $canPrint = $canSave && $user->can('actes-mariage.deliver');
    $canConfigure = $user->can('actes-mariage.configure') && $mairie !== null;
    $ready = $mairie?->civilReady() ?? false;
    $failed = $errors->any() && ! old('reglages');
    $v = function (string $field, $fallback = '') use ($failed, $acte) {
        $value = $failed ? old($field) : ($acte?->{$field} ?? $fallback);

        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            default => $value,
        };
    };
    $err = fn (string $field) => $failed && $errors->has($field) ? 'is-invalid' : '';
    $text = fn (string $name, string $placeholder = '', bool $required = false) => new \Illuminate\Support\HtmlString(
        '<input type="text" name="'.e($name).'" id="f-'.e($name).'" value="'.e($v($name)).'" placeholder="'.e($placeholder).'" class="'.$err($name).'"'.($required ? ' required' : '').($canSave ? '' : ' disabled').'>'
    );
    $date = fn (string $name, bool $required = false, string $title = '') => new \Illuminate\Support\HtmlString(
        '<input type="date" name="'.e($name).'" id="f-'.e($name).'" value="'.e($v($name)).'" max="'.date('Y-m-d').'" title="'.e($title).'" class="'.$err($name).'"'.($required ? ' required' : '').($canSave ? '' : ' disabled').'>'
    );
    $choice = fn (string $name, string $enum) => new \Illuminate\Support\HtmlString(collect($enum::cases())->map(fn ($case) =>
        '<label><input type="radio" name="'.e($name).'" value="'.e($case->value).'"'.($v($name) === $case->value ? ' checked' : '').' required'.($canSave ? '' : ' disabled').'><span>'.e(mb_strtoupper($case->label())).'</span></label>'
    )->implode(''));
    $centreLabel = $mairie?->civil_centre_name ? 'Centre '.\App\Enums\CentreEtatCivilType::tryFrom((string) $mairie->civil_centre_type)?->label().' de '.$mairie->civil_centre_name : null;
    $defaultPlace = $mairie?->civil_birth_place ?: $mairie?->civil_centre_name;
@endphp

@push('styles')
    <style>
        @font-face { font-family: 'Special Elite'; src: url('{{ asset('assets/fonts/special-elite-regular.ttf') }}') format('truetype'); font-display: swap; }
        .act-sheet { max-width: 860px; margin: 0 auto; background: #fdfcf8; border: 2px solid #1f2937; padding: 14px; color: #111; }
        .act-sheet .act-box { border: 1.5px solid #1f2937; padding: 6px 10px; }
        .act-sheet .act-box + .act-box, .act-sheet .act-parent + .act-box, .act-sheet .act-box + .act-parent, .act-sheet .act-parent + .act-parent { margin-top: 8px; }
        .act-top { text-align: center; font-weight: 800; font-size: .8rem; line-height: 1.35; margin-bottom: 6px; }
        .act-top small { display: block; font-weight: 700; }
        .act-title { text-align: center; font-weight: 900; font-family: Arial, sans-serif; font-size: 1.15rem; margin: 6px 0 2px; }
        .act-sub { text-align: center; font-size: .78rem; color: #4b5563; margin-bottom: 8px; }
        .act-row { display: grid; grid-template-columns: 200px minmax(0, 1fr); align-items: end; gap: 6px; padding: 5px 0; border-bottom: 1px solid #9ca3af; font-weight: 700; font-size: .8rem; }
        .act-row:last-child { border-bottom: 0; }
        .act-pair { display: grid; grid-template-columns: minmax(0, 1fr) 140px; gap: 8px; }
        .act-parent { display: grid; grid-template-columns: 36px minmax(0, 1fr); gap: 8px; }
        .act-parent-label { border: 1.5px solid #1f2937; display: flex; align-items: center; justify-content: center; writing-mode: vertical-rl; transform: rotate(180deg); font-family: Georgia, serif; font-weight: 700; font-size: 1.05rem; }
        .act-sex { display: flex; gap: 8px; }
        .act-sex label { font-family: 'Special Elite', 'Courier New', monospace; font-weight: 400; font-size: 1rem; border: 1px solid #9ca3af; border-radius: 6px; padding: 4px 14px; cursor: pointer; }
        .act-sex input { display: none; }
        .act-sex input:checked + span { color: #fff; }
        .act-sex label:has(input:checked) { background: #1d4e89; border-color: #1d4e89; color: #fff; }
        .act-sheet input, .act-sheet select { width: 100%; min-width: 0; border: 0; border-bottom: 1px dotted #4b5563; border-radius: 0; background: transparent; padding: 3px 4px;
            font-family: 'Special Elite', 'Courier New', monospace; font-size: 1.05rem; font-style: normal; color: #1b1b1d; text-transform: uppercase; }
        .act-sheet input[type="date"], .act-sheet input[type="time"], .act-sheet select { text-transform: none; }
        .act-sheet input:focus, .act-sheet select:focus { outline: 2px solid rgba(29, 78, 137, .25); background: #fff; }
        .act-sheet input.is-invalid { background: rgba(220, 53, 69, .08); border-bottom-color: #dc3545; }
        .act-sheet ::placeholder { color: #b8b2a5; text-transform: none; font-family: Arial, sans-serif; font-size: .78rem; }
        .act-two { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .act-more { margin-top: 8px; font-size: .8rem; }
        .act-more summary { cursor: pointer; color: #1d4e89; font-weight: 600; }
        .act-actions { position: sticky; bottom: 0; z-index: 3; display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; padding: 12px; margin-top: 12px; background: rgba(255,255,255,.94); border-top: 1px solid #e5e7eb; }
        .act-fixed { font-family: 'Special Elite', 'Courier New', monospace; font-weight: 400; font-size: 1.05rem; padding: 3px 4px; }
        .act-group { display: grid; grid-template-columns: 36px minmax(0, 1fr); gap: 8px; margin-top: 8px; }
        .act-sub2 { font-size: .72rem; color: #4b5563; font-weight: 700; text-transform: uppercase; margin: 6px 0 0; }
        @media (max-width: 767.98px) {
            .act-sheet { padding: 8px; }
            .act-row, .act-pair, .act-parent, .act-group, .act-two { grid-template-columns: 1fr; }
            .act-parent-label { writing-mode: initial; transform: none; min-height: 30px; }
            .act-actions .btn { flex: 1 1 100%; }
        }
    </style>
@endpush

@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">État civil</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Actes de mariage</li>
                </ol>
            </nav>
        </div>
        @if ($canConfigure)
            <button type="button" class="btn btn-outline-primary btn-sm ms-auto" data-bs-toggle="modal" data-bs-target="#civilSettingsModal"><i class='bx bx-cog me-1'></i>Réglages de l'état civil</button>
        @endif
    </div>
    <hr />

    @if ($mairie && ! $ready)
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
            <i class='bx bx-info-circle fs-4'></i>
            <div class="flex-grow-1"><strong>Réglez une fois l'état civil de la mairie</strong> (centre et officier signataire) : ils seront repris sur chaque acte et chaque extrait, sans ressaisie.</div>
            @if ($canConfigure)
                <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#civilSettingsModal">Régler maintenant</button>
            @endif
        </div>
    @endif

    {{-- 1. Retrouver un acte et imprimer son extrait en un clic --}}
    <div class="card">
        <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h6 class="mb-0 text-white"><i class='bx bx-search me-2'></i>RETROUVER UN ACTE DE MARIAGE — IMPRIMER L'EXTRAIT</h6>
            @if ($acte || $term !== '')
                <a href="{{ route('actes-mariage.index') }}" class="btn btn-light btn-sm"><i class='bx bx-plus'></i> Nouvel acte</a>
            @endif
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('actes-mariage.index') }}" class="d-flex flex-wrap gap-2">
                <input type="search" name="q" value="{{ $term }}" class="form-control form-control-lg flex-grow-1" style="min-width: 200px; max-width: 560px" placeholder="N° de l'acte, nom ou prénom de l'époux ou de l'épouse" @if (! $acte) autofocus @endif>
                <button type="submit" class="btn btn-primary btn-lg"><i class='bx bx-search me-1'></i>Rechercher</button>
            </form>

            @if ($term !== '')
                <div class="mt-3">
                    @forelse ($results as $result)
                        <div class="nv-panel d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                            <a href="{{ route('actes-mariage.index', ['acte' => $result->id]) }}" class="text-reset text-decoration-none">
                                <span class="fw-semibold">{{ $result->couple() }}</span>
                                <span class="small text-muted d-block">Acte n° {{ $result->act_number }}/{{ $result->act_year }} · célébré le {{ $result->celebrated_at->format('d/m/Y') }} · {{ $result->centreLabel() }}</span>
                            </a>
                            <span class="d-flex gap-2">
                                <a href="{{ route('actes-mariage.preview', $result) }}" class="btn btn-outline-primary nv-preview" data-src="{{ route('actes-mariage.preview', $result) }}" data-title="Extrait de mariage — {{ $result->couple() }}" data-deliver="{{ $result->id }}" title="Aperçu de l'extrait (rien n'est délivré)"><i class='bx bx-show'></i></a>
                                @can('deliver', $result)
                                    <form method="POST" action="{{ route('actes-mariage.deliver', $result) }}" data-deliver="{{ $result->id }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary" @disabled(! $ready) title="{{ $ready ? 'Extrait certifié par '.$mairie->civil_officer_name : 'Réglez d\'abord l\'état civil' }}"><i class='bx bx-printer me-1'></i>Imprimer l'extrait</button>
                                    </form>
                                @endcan
                            </span>
                        </div>
                    @empty
                        <div class="alert alert-secondary mb-0">Aucun acte trouvé pour « {{ $term }} » : saisissez-le ci-dessous.</div>
                    @endforelse
                </div>
            @endif
            @error('reglages')<div class="alert alert-danger mt-3 mb-0">{{ $message }}</div>@enderror
        </div>
    </div>

    {{-- 2. Saisir un acte de mariage sur le modèle de l'extrait --}}
    <div class="card">
        <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h6 class="mb-0 text-white"><i class='bx bx-heart me-2'></i>{{ $acte ? 'ACTE N° '.$acte->act_number.'/'.$acte->act_year.' — '.mb_strtoupper($acte->couple()) : 'NOUVEL ACTE DE MARIAGE' }}</h6>
            @if ($acte && $acte->extraits->isNotEmpty())
                <span class="badge bg-light text-dark">{{ $acte->extraits->count() }} extrait(s) délivré(s)</span>
            @endif
        </div>
        <div class="card-body">
            @if ($failed)
                <div class="alert alert-danger"><i class='bx bx-error me-1'></i>{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ $acte ? route('actes-mariage.update', $acte) : route('actes-mariage.store') }}" id="acte-form">
                @csrf
                @if ($acte) @method('PUT') @endif

                <div class="act-sheet">
                    <div class="act-top">REPUBLIQUE DU MALI<small>Un Peuple - Un But - Une Foi</small></div>
                    <div class="act-title">EXTRAIT D'ACTE DE MARIAGE</div>
                    <div class="act-sub">
                        @if ($acte)
                            N° {{ $acte->act_number }} de {{ $acte->act_year }} · {{ $acte->centreLabel() }}
                        @else
                            {{ $centreLabel ?? 'Centre à régler' }} · n° et date remplis automatiquement
                        @endif
                    </div>

                    <div class="act-parent">
                        <div class="act-parent-label">Époux</div>
                        <div class="act-box">
                            <label class="act-row"><span>1 Prénom(s) :</span>{{ $text('husband_first_names', '', true) }}</label>
                            <label class="act-row"><span>2 Nom :</span>{{ $text('husband_last_name', '', true) }}</label>
                            <label class="act-row"><span>3 Date de Naissance :</span>{{ $date('husband_birth_date', true) }}</label>
                            <label class="act-row"><span>4 Localité de Naissance :</span>{{ $text('husband_birth_place', '', true) }}</label>
                            <div class="act-row"><span>5 Sexe :</span><span class="act-fixed">MASCULIN</span></div>
                            <label class="act-row"><span>6 Profession :</span>{{ $text('husband_profession') }}</label>
                            <label class="act-row"><span>7 Domicile :</span>{{ $text('husband_domicile') }}</label>
                        </div>
                    </div>

                    <div class="act-parent">
                        <div class="act-parent-label">Épouse</div>
                        <div class="act-box">
                            <label class="act-row"><span>8 Prénom(s) :</span>{{ $text('wife_first_names', '', true) }}</label>
                            <label class="act-row"><span>9 Nom :</span>{{ $text('wife_last_name', '', true) }}</label>
                            <label class="act-row"><span>10 Date de Naissance :</span>{{ $date('wife_birth_date', true) }}</label>
                            <label class="act-row"><span>11 Localité de Naissance :</span>{{ $text('wife_birth_place', '', true) }}</label>
                            <div class="act-row"><span>12 Sexe :</span><span class="act-fixed">FÉMININ</span></div>
                            <label class="act-row"><span>13 Profession :</span>{{ $text('wife_profession') }}</label>
                            <label class="act-row"><span>14 Domicile :</span>{{ $text('wife_domicile') }}</label>
                        </div>
                    </div>

                    <div class="act-parent">
                        <div class="act-parent-label">Filiation</div>
                        <div class="act-box">
                            <div class="act-sub2">De l'époux</div>
                            <div class="act-two">
                                <label class="act-row"><span>15 Père, prénom(s) :</span>{{ $text('husband_father_first_names') }}</label>
                                <label class="act-row"><span>Nom :</span>{{ $text('husband_father_last_name', 'même nom que l\'époux') }}</label>
                                <label class="act-row"><span>16 Mère, prénom(s) :</span>{{ $text('husband_mother_first_names') }}</label>
                                <label class="act-row"><span>Nom :</span>{{ $text('husband_mother_last_name') }}</label>
                            </div>
                            <div class="act-sub2">De l'épouse</div>
                            <div class="act-two">
                                <label class="act-row"><span>15 Père, prénom(s) :</span>{{ $text('wife_father_first_names') }}</label>
                                <label class="act-row"><span>Nom :</span>{{ $text('wife_father_last_name', 'même nom que l\'épouse') }}</label>
                                <label class="act-row"><span>16 Mère, prénom(s) :</span>{{ $text('wife_mother_first_names') }}</label>
                                <label class="act-row"><span>Nom :</span>{{ $text('wife_mother_last_name') }}</label>
                            </div>
                        </div>
                    </div>

                    <div class="act-box">
                        <label class="act-row"><span>18 Date de célébration :</span>{{ $date('celebrated_at', false, 'Vide : aujourd\'hui') }}</label>
                        <label class="act-row"><span>17 Date de déclaration :</span>{{ $date('declared_at', false, 'Vide : même date que la célébration') }}</label>
                        <label class="act-row"><span>19 Localité du Mariage :</span>{{ $text('marriage_place', $defaultPlace ? 'par défaut : '.$defaultPlace : '', ! $defaultPlace && ! $acte) }}</label>
                        <div class="act-row"><span>20 Régime Matrimonial :</span><div class="act-sex flex-wrap">{{ $choice('regime', \App\Enums\RegimeMatrimonial::class) }}</div></div>
                        <div class="act-row"><span>21 Option Matrimoniale :</span><div class="act-sex flex-wrap">{{ $choice('option', \App\Enums\OptionMatrimoniale::class) }}</div></div>
                    </div>

                    {{-- Rempli automatiquement (réglages de la mairie, n° suivant, date du jour) : à ouvrir seulement pour
                         recopier un ancien acte du registre papier. --}}
                    <details class="act-more" @if ($failed && $errors->hasAny(['act_number', 'act_year', 'drawn_up_at', 'centre_name', 'officer_name', 'officer_title'])) open @endif>
                        <summary>N°, date et officier de l'acte — remplis automatiquement (ouvrir pour recopier un ancien acte)</summary>
                        <div class="act-box mt-2">
                            <label class="act-row"><span>N° de l'acte :</span>{{ $text('act_number', 'automatique (n° suivant)') }}</label>
                            <label class="act-row"><span>Année du registre :</span><input type="number" name="act_year" value="{{ $v('act_year') }}" placeholder="automatique" min="1900" max="{{ date('Y') }}" class="{{ $err('act_year') }}" @disabled(! $canSave)></label>
                            <label class="act-row"><span>24 Date d'établissement :</span>{{ $date('drawn_up_at', false, 'Vide : aujourd\'hui') }}</label>
                            <label class="act-row"><span>22 Officier de l'état civil :</span>{{ $text('officer_name', $mairie?->civil_officer_name ? 'réglé : '.$mairie->civil_officer_name : '') }}</label>
                            <label class="act-row"><span>23 Qualité :</span>{{ $text('officer_title', $mairie?->civil_officer_title ? 'réglé : '.$mairie->civil_officer_title : '') }}</label>
                            <label class="act-row"><span>Centre (autre que celui réglé) :</span>{{ $text('centre_name', $mairie?->civil_centre_name ? 'réglé : '.$mairie->civil_centre_name : '') }}</label>
                        </div>
                    </details>
                </div>

                @if ($canSave)
                    <div class="act-actions">
                        @if ($canPrint)
                            <button type="submit" name="print" value="1" class="btn btn-primary btn-lg" @disabled(! $ready) title="{{ $ready ? '' : 'Réglez d\'abord l\'état civil' }}"><i class='bx bx-printer me-1'></i>Enregistrer et imprimer</button>
                        @endif
                        <button type="submit" class="btn btn-outline-primary btn-lg"><i class='bx bx-save me-1'></i>Enregistrer seulement</button>
                    </div>
                @endif
            </form>

            @if ($acte && $acte->extraits->isNotEmpty())
                <div class="mt-3 small">
                    <span class="text-muted">Extraits déjà délivrés :</span>
                    @foreach ($acte->extraits->sortByDesc('id') as $extrait)
                        <a href="{{ route('actes-mariage.extrait', $extrait) }}" target="_blank" rel="noopener" class="badge bg-primary-subtle text-primary text-decoration-none ms-1"><i class='bx bx-printer'></i> {{ $extrait->delivered_at->format('d/m/Y') }}</a>
                    @endforeach
                </div>
            @endif
            @if ($acte)
                @can('delete', $acte)
                    <form method="POST" action="{{ route('actes-mariage.destroy', $acte) }}" class="js-delete-acte mt-3 text-end" data-name="{{ $acte->activityLabel() }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class='bx bx-trash'></i> Supprimer cet acte</button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    {{-- 3. Derniers actes saisis --}}
    @if ($recent->isNotEmpty())
        <div class="card">
            <div class="card-header card-header-brand">
                <h6 class="mb-0 text-white"><i class='bx bx-time-five me-2'></i>DERNIERS ACTES DE MARIAGE SAISIS</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table" id="actes-table">
                        <thead>
                            <tr>
                                <th>N° / ANNÉE</th>
                                <th>ÉPOUX</th>
                                <th>CÉLÉBRÉ LE</th>
                                <th>EXTRAITS</th>
                                <th width="10%">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recent as $row)
                                <tr>
                                    <td>{{ $row->act_number }} / {{ $row->act_year }}</td>
                                    <td class="cell-wrap text-break"><span class="fw-semibold">{{ $row->husbandName() }}</span><div class="small text-muted">et {{ $row->wifeName() }}</div></td>
                                    <td>{{ $row->celebrated_at->format('d/m/Y') }}</td>
                                    <td>{{ $row->extraits_count }}</td>
                                    <td class="d-flex gap-1">
                                        @can('deliver', $row)
                                            <form method="POST" action="{{ route('actes-mariage.deliver', $row) }}" data-deliver="{{ $row->id }}">
                                                @csrf
                                                <button type="submit" class="btn btn-primary btn-sm" title="Imprimer l'extrait" @disabled(! $ready)><i class='bx bx-printer'></i></button>
                                            </form>
                                        @endcan
                                        <a href="{{ route('actes-mariage.preview', $row) }}" class="btn btn-primary btn-sm nv-preview" data-src="{{ route('actes-mariage.preview', $row) }}" data-title="Extrait de mariage — {{ $row->couple() }}" data-deliver="{{ $row->id }}" title="Aperçu de l'extrait"><i class='bx bx-show'></i></a>
                                        <a href="{{ route('actes-mariage.index', ['acte' => $row->id]) }}" class="btn btn-primary btn-sm" title="Ouvrir pour modifier"><i class='bx bx-edit'></i></a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($canConfigure)
        @include('etat-civil._settings_modal', ['settingsRoute' => route('actes-mariage.settings')])
    @endif
    @include('partials.preview-modal')
@endsection

@push('scripts')
    <script>
        $('#actes-table').DataTable({ scrollX: false, order: [], paging: false, info: false, searching: false });

        // Reprises automatiques visibles pendant la saisie (le serveur applique la même règle si la case reste vide).
        const mirror = (from, to) => {
            const source = document.getElementById('f-' + from);
            const target = document.getElementById('f-' + to);
            if (!source || !target) { return; }
            const sync = () => { target.placeholder = source.value ? source.value.toUpperCase() : target.dataset.placeholder; };
            target.dataset.placeholder = target.placeholder;
            source.addEventListener('input', sync);
            sync();
        };
        mirror('husband_last_name', 'husband_father_last_name');
        mirror('wife_last_name', 'wife_father_last_name');

        $(document).on('submit', '.js-delete-acte', function (event) {
            event.preventDefault();
            const form = this;
            Swal.fire({
                icon: 'warning',
                title: 'Supprimer cet acte ?',
                text: 'L\'acte ' + $(form).data('name') + ' sera retiré du registre (récupérable, son numéro reste pris).',
                showCancelButton: true,
                confirmButtonText: 'Supprimer',
                cancelButtonText: 'Annuler',
            }).then((result) => { if (result.isConfirmed) form.submit(); });
        });

        @if (session('print_extrait'))
            // Extrait tout juste délivré : la page de l'extrait s'imprime elle-même dans un iframe caché (?print=1).
            (() => {
                const iframe = document.createElement('iframe');
                iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
                iframe.addEventListener('load', () => setTimeout(() => iframe.remove(), 60000));
                iframe.src = @json(session('print_extrait')) + '?print=1';
                document.body.appendChild(iframe);
            })();
        @endif
    </script>
@endpush
