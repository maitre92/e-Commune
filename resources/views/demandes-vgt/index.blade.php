@extends('layouts.admin')

@section('title', 'Vignettes')

@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Vignettes</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Vignettes</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    @can('create', \App\Models\DemandeVgt::class)
        @include('demandes-vgt._guichet')
    @endcan

    <div class="card">
        <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h6 class="mb-0 text-white"><i class='bx bx-list-ul me-2'></i>VIGNETTES DÉLIVRÉES</h6>
            <div class="d-flex align-items-center gap-2">
                @if (auth()->user()->can('mairies.update') || auth()->user()->can('demandes-vgt.card_settings'))
                    <button type="button" class="btn btn-light btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#cardTemplatesModal">
                        <i class='bx bx-id-card'></i> Modèle de la vignette
                    </button>
                @endif
            </div>
        </div>
        <div class="card-body">
            @php
                $counts = $demandes->groupBy(fn ($d) => $d->status->value)->map->count();
            @endphp
            <div class="nv-chips mb-3" id="demandes-status-filter">
                <button type="button" class="nv-chip active" data-status="">Toutes <b>{{ $demandes->count() }}</b></button>
                @foreach (\App\Enums\DemandeVgtStatus::cases() as $case)
                    <button type="button" class="nv-chip" data-key="{{ $case->value }}" data-status="{{ $case->label() }}">{{ $case->label() }} <b>{{ $counts[$case->value] ?? 0 }}</b></button>
                @endforeach
            </div>
            <div class="table-responsive">
                <table class="table" id="demandes-vgt-table">
                    <thead>
                        <tr>
                            <th>DATE</th>
                            <th>MOTO</th>
                            <th>DÉTENTEUR</th>
                            <th>ANNÉE</th>
                            <th>{{ auth()->user()->can('demandes-vgt.view_all') ? 'AGENT' : 'MAIRIE' }}</th>
                            <th>MONTANT</th>
                            <th>STATUT</th>
                            <th width="10%">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($demandes as $demande)
                            <tr>
                                <td>{{ $demande->created_at->format('d/m/Y') }}</td>
                                <td><div class="fw-semibold">{{ $demande->moto->identifier() }}</div><div class="small text-muted">{{ $demande->category_label ?? $demande->moto->type_or_brand }}</div></td>
                                <td><div>{{ $demande->holderName() ?: '—' }}</div><div class="small text-muted">{{ $demande->holderPhone() }}</div></td>
                                <td>{{ $demande->vgt_year }}</td>
                                <td>{{ auth()->user()->can('demandes-vgt.view_all') ? ($demande->agent?->name ?? '—') : $demande->mairie->name }}</td>
                                <td>
                                    <div class="fw-semibold">{{ number_format($demande->totalAmount(), 0, ',', ' ') }} FCFA</div>
                                    @if ($demande->is_late)
                                        <span class="badge bg-warning-subtle" title="Majoration de {{ number_format($demande->surcharge_amount, 0, ',', ' ') }} FCFA incluse">Arriéré</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $demande->status->badgeClass() }}">{{ $demande->status->label() }}</span>
                                    @if ($demande->status === \App\Enums\DemandeVgtStatus::Payee && $demande->payment_confirmed_at)
                                        <div class="small text-muted">le {{ $demande->payment_confirmed_at->format('d/m/Y') }}</div>
                                    @endif
                                    @if ($demande->status === \App\Enums\DemandeVgtStatus::Retiree && $demande->retrait_date)
                                        <div class="small text-muted">le {{ $demande->retrait_date->format('d/m/Y') }}</div>
                                    @endif
                                </td>
                                <td class="d-flex gap-1">
                                    @if (in_array($demande->status, [\App\Enums\DemandeVgtStatus::Payee, \App\Enums\DemandeVgtStatus::Retiree], true))
                                        <a href="{{ route('demandes-vgt.card', $demande->id) }}" class="btn btn-primary btn-sm nv-preview" data-src="{{ route('demandes-vgt.card', $demande->id) }}" data-title="Vignette {{ $demande->vgt_year }} — {{ $demande->moto->identifier() }}" title="Aperçu de la carte (recto et verso)">
                                            <i class='bx bx-show'></i>
                                        </a>
                                    @endif
                                    @can('correct', $demande)
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#correctVgt-{{ $demande->id }}" title="Corriger (N° Engin, détenteur) et réimprimer">
                                            <i class='bx bx-edit'></i>
                                        </button>
                                    @endcan
                                    @can('update', $demande)
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editDemandeVgt-{{ $demande->id }}" title="Modifier">
                                            <i class='bx bx-edit'></i>
                                        </button>
                                    @endcan
                                    @can('confirmPayment', $demande)
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#confirmPaiement-{{ $demande->id }}" title="Confirmer le paiement">
                                            <i class='bx bx-money'></i>
                                        </button>
                                    @endcan
                                    @if (in_array($demande->status, [\App\Enums\DemandeVgtStatus::Payee, \App\Enums\DemandeVgtStatus::Retiree], true) && \Illuminate\Support\Facades\Gate::allows('view', $demande))
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#retraitVgt-{{ $demande->id }}" title="Carte de vignette : impression et retrait">
                                            <i class='bx bx-id-card'></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    @foreach ($demandes as $demande)
        @can('update', $demande)
            @php
                $editFailed = $errors->any() && old('demande_vgt_id') == $demande->id;
            @endphp
            <div class="modal fade" id="editDemandeVgt-{{ $demande->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('demandes-vgt.update', $demande) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="demande_vgt_id" value="{{ $demande->id }}">
                            @include('demandes-vgt._form', ['demande' => $demande, 'failed' => $editFailed, 'idSuffix' => '-'.$demande->id])
                        </form>
                    </div>
                </div>
            </div>

            @if ($editFailed)
                <script>
                    window.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('editDemandeVgt-{{ $demande->id }}')).show());
                </script>
            @endif
        @endcan


        @can('confirmPayment', $demande)
            @php
                $paiementFailed = $errors->any() && old('paiement_demande_id') == $demande->id;
            @endphp
            <div class="modal fade" id="confirmPaiement-{{ $demande->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('demandes-vgt.confirm-payment', $demande) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="paiement_demande_id" value="{{ $demande->id }}">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class='bx bx-money me-2'></i>{{ $demande->moto->identifier() }} — {{ $demande->vgt_year }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                            </div>
                            <div class="modal-body p-4">
                                <p class="mb-3">Détenteur : <strong>{{ $demande->holderName() ?: '—' }}</strong><br>
                                   Montant : <strong>{{ number_format($demande->totalAmount(), 0, ',', ' ') }} FCFA</strong></p>
                                <label for="payment_confirmed_at{{ $demande->id }}" class="form-label fw-semibold">Date de paiement</label>
                                <input type="date" class="form-control @if ($paiementFailed && $errors->has('payment_confirmed_at')) is-invalid @endif" id="payment_confirmed_at{{ $demande->id }}" name="payment_confirmed_at" value="{{ $paiementFailed ? old('payment_confirmed_at') : date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
                                @if ($paiementFailed && $errors->has('payment_confirmed_at'))<div class="invalid-feedback">{{ $errors->first('payment_confirmed_at') }}</div>@endif
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                <button type="submit" class="btn btn-primary">Confirmer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @if ($paiementFailed)
                <script>
                    window.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('confirmPaiement-{{ $demande->id }}')).show());
                </script>
            @endif
        @endcan

        @if (in_array($demande->status, [\App\Enums\DemandeVgtStatus::Payee, \App\Enums\DemandeVgtStatus::Retiree], true) && \Illuminate\Support\Facades\Gate::allows('view', $demande))
            @include('demandes-vgt._retrait')
        @endif

        @if (\Illuminate\Support\Facades\Gate::allows('correct', $demande))
            @php
                $correctFailed = $errors->any() && old('correct_demande_id') == $demande->id;
            @endphp
            <div class="modal fade" id="correctVgt-{{ $demande->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('demandes-vgt.correct', $demande) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="correct_demande_id" value="{{ $demande->id }}">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class='bx bx-edit me-2'></i>Corriger la vignette {{ $demande->vgt_year }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                            </div>
                            <div class="modal-body p-4">
                                <label for="ch-{{ $demande->id }}" class="form-label fw-bold">N° Engin</label>
                                <input type="text" id="ch-{{ $demande->id }}" name="chassis_number" value="{{ $correctFailed ? old('chassis_number') : $demande->moto?->chassis_number }}" class="form-control form-control-lg text-uppercase fw-bold @if ($correctFailed && $errors->has('chassis_number')) is-invalid @endif" required>
                                @if ($correctFailed && $errors->has('chassis_number'))<div class="invalid-feedback">{{ $errors->first('chassis_number') }}</div>@endif
                                <label for="holder-{{ $demande->id }}" class="form-label mt-3">Nom du détenteur <span class="text-muted">(facultatif)</span></label>
                                <input type="text" id="holder-{{ $demande->id }}" name="holder_name" value="{{ $correctFailed ? old('holder_name') : $demande->holder_name }}" class="form-control">
                                <label for="hphone-{{ $demande->id }}" class="form-label mt-3">Numéro du détenteur <span class="text-muted">(facultatif)</span></label>
                                <input type="tel" id="hphone-{{ $demande->id }}" name="holder_phone" value="{{ $correctFailed ? old('holder_phone') : \App\Support\PhoneNumber::display($demande->holder_phone) }}" class="form-control @if ($correctFailed && $errors->has('holder_phone')) is-invalid @endif" placeholder="70 00 12 34">
                                @if ($correctFailed && $errors->has('holder_phone'))<div class="invalid-feedback">{{ $errors->first('holder_phone') }}</div>@endif
                                <p class="small text-muted mt-3 mb-0">Le montant ne change pas. La carte corrigée s'imprime aussitôt ; la correction est inscrite au journal.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                <button type="submit" class="btn btn-primary"><i class='bx bx-printer me-1'></i>Corriger et réimprimer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @if ($correctFailed)
                <script>
                    window.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('correctVgt-{{ $demande->id }}')).show());
                </script>
            @endif
        @endif
    @endforeach

    @can('manageTarifs', \App\Models\DemandeVgt::class)
        @include('demandes-vgt._tarifs')
    @endcan

    @if (auth()->user()->can('mairies.update') || auth()->user()->can('demandes-vgt.card_settings'))
        @include('demandes-vgt._card_templates_settings')
    @endif
    @include('partials.preview-modal')
@endsection

@push('scripts')
    <script>
        const demandesTable = $('#demandes-vgt-table').DataTable({ scrollX: false, order: [[0, 'desc']] });


        // Filtre par statut (pastilles au-dessus du tableau).
        const wantedStatus = new URLSearchParams(window.location.search).get('statut');
        document.querySelectorAll('#demandes-status-filter .nv-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                document.querySelectorAll('#demandes-status-filter .nv-chip').forEach((other) => other.classList.toggle('active', other === chip));
                demandesTable.column(6).search(chip.dataset.status || '', false, true).draw();
            });
        });
        document.querySelector('#demandes-status-filter .nv-chip[data-key="' + wantedStatus + '"]')?.click();


        // Impression d'une carte (modèle de son année) : chargée dans un iframe caché, puis impression.
        const printCard = (src) => {
            const iframe = document.createElement('iframe');
            iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
            iframe.addEventListener('load', () => {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
                setTimeout(() => iframe.remove(), 60000);
            });
            iframe.src = src;
            document.body.appendChild(iframe);
        };
        document.querySelectorAll('.vgt-print').forEach((button) => button.addEventListener('click', () => printCard(button.dataset.src)));
        @if ($errors->any() && old('tarifs_form'))
            new bootstrap.Modal(document.getElementById('tarifsVgtModal')).show();
        @endif
        @if (session('print_card'))
            // Vignette tout juste délivrée au guichet : impression directe de la carte.
            printCard(@json(session('print_card')));
        @endif
    </script>
@endpush
