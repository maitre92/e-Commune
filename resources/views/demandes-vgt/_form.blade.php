@php
    $v = fn (string $field, $default = '') => $failed ? old($field) : ($demande?->{$field} ?? $default);
    // Select2 en recherche (AJAX, MotoController::search()) : seule l'option déjà choisie est pré-rendue, le
    // reste se charge en tapant.
    $selectedMoto = $demande?->moto
        ?? ($failed && old('moto_id') ? \App\Models\Moto::with('proprietaire')->find(old('moto_id')) : null);
@endphp

<div class="modal-header">
    <h5 class="modal-title"><i class='bx bx-id-card me-2'></i>{{ $demande ? 'Modifier — '.$demande->moto->identifier() : 'Nouvelle vignette' }}</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
</div>
<div class="modal-body p-4">
    <div class="row g-3">
        <div class="col-md-8">
            <label for="moto_id{{ $idSuffix }}" class="form-label fw-semibold">Moto</label>
            <select class="form-select ajax-select" id="moto_id{{ $idSuffix }}" name="moto_id" data-search-url="{{ route('motos.search') }}" data-placeholder="Rechercher une moto (matricule ou propriétaire)…" required>
                @if ($selectedMoto)
                    <option value="{{ $selectedMoto->id }}" selected>{{ $selectedMoto->identifier() }}{{ $selectedMoto->proprietaire ? ' — '.$selectedMoto->proprietaire->fullName() : '' }}</option>
                @endif
            </select>
            @if ($failed && $errors->has('moto_id'))<div class="text-danger small mt-1">{{ $errors->first('moto_id') }}</div>@endif
            <small class="text-muted">La moto doit d'abord être enregistrée dans le registre (onglet Motos).</small>
        </div>
        <div class="col-md-4">
            <label for="vgt_year{{ $idSuffix }}" class="form-label fw-semibold">Année de la vignette</label>
            <input type="number" class="form-control @if ($failed && $errors->has('vgt_year')) is-invalid @endif" id="vgt_year{{ $idSuffix }}" name="vgt_year" value="{{ $v('vgt_year', date('Y')) }}" min="2000" max="{{ date('Y') + 1 }}" required>
            @if ($failed && $errors->has('vgt_year'))<div class="invalid-feedback">{{ $errors->first('vgt_year') }}</div>@endif
            <small class="text-muted">Année déjà passée = majoration pour arriéré.</small>
        </div>
    </div>
    <p class="small text-muted mt-3 mb-0"><i class='bx bx-info-circle me-1'></i>Le montant est calculé selon le genre de la moto (tarifs) et figé à l'enregistrement.</p>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
    <button type="submit" class="btn btn-primary">Enregistrer</button>
</div>
