{{-- Carte de vignette : modale de synthèse ; la carte s'imprime dans le modèle de son année (un seul par année). --}}
@php
    $status = $demande->status;
    $proprietaire = $demande->moto->proprietaire;
    $retraitFailed = $errors->any() && old('retrait_demande_id') == $demande->id;
    $canConfirmRetrait = $status === \App\Enums\DemandeVgtStatus::Payee && \Illuminate\Support\Facades\Gate::allows('confirmRetrait', $demande);
    $rank = ['en_attente' => 0, 'payee' => 1, 'retiree' => 2][$status->value] ?? 0;
    $steps = [
        ['icon' => 'bx-file', 'label' => 'Vignette enregistrée', 'date' => $demande->created_at],
        ['icon' => 'bx-money', 'label' => 'Paiement confirmé', 'date' => $demande->payment_confirmed_at],
        ['icon' => 'bx-id-card', 'label' => 'Carte remise', 'date' => $demande->retrait_date],
    ];
@endphp
<div class="modal fade" id="retraitVgt-{{ $demande->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('demandes-vgt.confirm-retrait', $demande) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="retrait_demande_id" value="{{ $demande->id }}">
                <div class="modal-header">
                    <h5 class="modal-title"><i class='bx bx-id-card me-2'></i>Vignette {{ $demande->vgt_year }} — {{ $demande->moto->identifier() }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body p-4">
                    <ol class="nv-steps mb-4">
                        @foreach ($steps as $index => $step)
                            <li class="{{ $index <= $rank ? 'done' : '' }} {{ $index === $rank ? 'current' : '' }}">
                                <span class="nv-step-dot"><i class='bx {{ $step['icon'] }}'></i></span>
                                <span class="nv-step-label">{{ $step['label'] }}</span>
                                <span class="nv-step-date">{{ $index <= $rank && $step['date'] ? $step['date']->format('d/m/Y') : '—' }}</span>
                            </li>
                        @endforeach
                    </ol>

                    <div class="row g-3">
                        <div class="col-md-7">
                            <div class="nv-panel h-100">
                                <div class="nv-panel-title">Moto et propriétaire</div>
                                <div class="nv-plate">{{ $demande->moto->identifier() }}</div>
                                <dl class="nv-dl">
                                    <dt>Catégorie</dt><dd>{{ $demande->category_label ?? $demande->moto->type_or_brand ?? '—' }}</dd>
                                    <dt>Couleur</dt><dd>{{ $demande->moto->color ?? '—' }}</dd>
                                    <dt>Détenteur</dt><dd>{{ $demande->holderName() ?: '—' }}</dd>
                                    <dt>Téléphone</dt><dd>{{ $proprietaire?->phone ?? '—' }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="nv-panel h-100">
                                <div class="nv-panel-title">Montant</div>
                                <dl class="nv-dl">
                                    <dt>Vignette {{ $demande->vgt_year }}</dt><dd>{{ number_format($demande->base_amount, 0, ',', ' ') }} FCFA</dd>
                                    @if ($demande->is_late)
                                        <dt>Majoration</dt><dd>{{ number_format($demande->surcharge_amount, 0, ',', ' ') }} FCFA</dd>
                                    @endif
                                    <dt class="fw-bold">Total</dt><dd class="fw-bold fs-6">{{ number_format($demande->totalAmount(), 0, ',', ' ') }} FCFA</dd>
                                    <dt>Mairie</dt><dd>{{ $demande->mairie->name }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        @if ($canConfirmRetrait)
                            <label for="retrait_date{{ $demande->id }}" class="form-label fw-semibold">Date de retrait de la carte</label>
                            <input type="date" class="form-control @if ($retraitFailed && $errors->has('retrait_date')) is-invalid @endif" id="retrait_date{{ $demande->id }}" name="retrait_date" value="{{ $retraitFailed ? old('retrait_date') : date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
                            @if ($retraitFailed && $errors->has('retrait_date'))<div class="invalid-feedback">{{ $errors->first('retrait_date') }}</div>@endif
                        @elseif ($status === \App\Enums\DemandeVgtStatus::Retiree)
                            <div class="alert alert-success mb-0 py-2"><i class='bx bx-check-circle me-1'></i>Carte remise le <strong>{{ $demande->retrait_date?->format('d/m/Y') }}</strong>.</div>
                        @else
                            <div class="alert alert-secondary mb-0 py-2">Carte pas encore remise au propriétaire.</div>
                        @endif
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="vgt-print btn btn-outline-primary me-auto" data-src="{{ route('demandes-vgt.card', $demande->id) }}"><i class='bx bx-printer me-1'></i>Imprimer la carte ({{ $demande->cardTemplate()->label() }})</button>
                    @if ($canConfirmRetrait)
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Confirmer le retrait</button>
                    @else
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>

@if ($retraitFailed)
    <script>
        window.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('retraitVgt-{{ $demande->id }}')).show());
    </script>
@endif
