@extends('layouts.admin')

@section('title', 'Caisse')

{{-- Caisse : l'agent voit ses recettes et ce qu'il doit verser ; le caissier (ou le maire) voit toute la caisse du
     jour, reçoit le versement de chaque agent et imprime l'état journalier. --}}
@php($fcfa = fn (int $amount) => number_format($amount, 0, ',', ' ').' FCFA')

@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Caisse</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $full ? 'Caisse de la mairie' : 'Ma caisse' }}</li>
                </ol>
            </nav>
        </div>
        <form method="GET" action="{{ route('caisse.index') }}" class="ms-auto d-flex gap-2">
            <input type="date" name="date" value="{{ $day->toDateString() }}" max="{{ date('Y-m-d') }}" class="form-control form-control-sm" onchange="this.form.submit()">
            @if ($full)
                <a href="{{ route('caisse.etat', ['date' => $day->toDateString()]) }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm text-nowrap"><i class='bx bx-printer me-1'></i>État journalier</a>
            @endif
        </form>
    </div>
    <hr />

    <div class="row row-cols-1 row-cols-md-{{ 1 + count($totals) }} g-3 mb-3">
        <div class="col">
            <div class="card radius-10 bg-primary bg-gradient mb-0"><div class="card-body text-white">
                <p class="mb-0">Encaissé le {{ $day->format('d/m/Y') }}</p>
                <h4 class="my-1 text-white">{{ $fcfa($recettes->sum('amount')) }}</h4>
                <p class="mb-0 small">{{ $recettes->count() }} recette(s)</p>
            </div></div>
        </div>
        @foreach ($totals as $total)
            <div class="col">
                <div class="card radius-10 mb-0"><div class="card-body">
                    <p class="mb-0 text-muted">{{ $total['label'] }}</p>
                    <h4 class="my-1">{{ $fcfa($total['amount']) }}</h4>
                    <p class="mb-0 small text-muted">{{ $total['count'] }} délivré(s)</p>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header card-header-brand">
            <h6 class="mb-0 text-white"><i class='bx bx-wallet me-2'></i>{{ $full ? 'À VERSER PAR AGENT' : 'CE QUE JE DOIS VERSER' }}</h6>
        </div>
        <div class="card-body">
            @forelse ($aVerser as $row)
                <div class="nv-panel d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                    <div>
                        <div class="fw-semibold">{{ $row['agent']?->name ?? '—' }}</div>
                        <div class="small text-muted">{{ $row['agent']?->roleName()?->label() }} · {{ $row['nombre'] }} recette(s) non versée(s)</div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="fs-5 fw-bold">{{ $fcfa($row['total']) }}</span>
                        @can('caisse.receive')
                            <form method="POST" action="{{ route('caisse.receive') }}" class="js-receive" data-name="{{ $row['agent']?->name }}" data-amount="{{ $fcfa($row['total']) }}">
                                @csrf
                                <input type="hidden" name="agent_id" value="{{ $row['agent']?->id }}">
                                <button type="submit" class="btn btn-primary"><i class='bx bx-check-circle me-1'></i>Recevoir le versement</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="text-muted">Rien à verser : toutes les recettes ont été versées.</div>
            @endforelse
            @error('agent')<div class="alert alert-danger mt-2 mb-0">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="card">
        <div class="card-header card-header-brand">
            <h6 class="mb-0 text-white"><i class='bx bx-receipt me-2'></i>RECETTES DU {{ $day->format('d/m/Y') }}</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table" id="recettes-table">
                    <thead>
                        <tr>
                            <th>QUITTANCE</th>
                            <th>HEURE</th>
                            <th>OBJET</th>
                            @if ($full)<th>AGENT</th>@endif
                            <th>MONTANT</th>
                            <th>VERSÉE</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recettes as $recette)
                            <tr>
                                <td class="fw-semibold">{{ $recette->quittance() }}</td>
                                <td>{{ $recette->encaissee_at->format('H:i') }}</td>
                                <td class="cell-wrap text-break">{{ $recette->label }}</td>
                                @if ($full)<td>{{ $recette->agent?->name }}</td>@endif
                                <td class="fw-semibold">{{ $fcfa($recette->amount) }}</td>
                                <td>
                                    @if ($recette->versement_id)
                                        <span class="badge bg-success-subtle text-success">Versée</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-dark">Chez l'agent</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($versements->isNotEmpty())
        <div class="card">
            <div class="card-header card-header-brand">
                <h6 class="mb-0 text-white"><i class='bx bx-transfer me-2'></i>VERSEMENTS DU {{ $day->format('d/m/Y') }}</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        @foreach ($versements as $versement)
                            <tr>
                                <td>{{ $versement->received_at->format('H:i') }}</td>
                                <td>{{ $versement->agent?->name }}</td>
                                <td>reçu par {{ $versement->receiver?->name }}</td>
                                <td>{{ $versement->recettes_count }} recette(s)</td>
                                <td class="fw-bold text-end">{{ $fcfa($versement->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        $('#recettes-table').DataTable({ scrollX: false, order: [[0, 'desc']] });

        $(document).on('submit', '.js-receive', function (event) {
            event.preventDefault();
            const form = this;
            Swal.fire({
                icon: 'question',
                title: 'Recevoir le versement ?',
                text: 'Confirmez avoir reçu ' + $(form).data('amount') + ' de ' + $(form).data('name') + '.',
                showCancelButton: true,
                confirmButtonText: 'Argent reçu',
                cancelButtonText: 'Annuler',
            }).then((result) => { if (result.isConfirmed) form.submit(); });
        });
    </script>
@endpush
