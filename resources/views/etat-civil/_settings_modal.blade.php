{{-- Réglages de l'état civil de la mairie (en-tête, officier signataire, prix des extraits, papier), saisis une
     fois et partagés par les actes de naissance et de mariage. Variables : $mairie, $settingsRoute. --}}
@php
    $settingsFailed = $errors->any() && old('reglages');
    $s = fn (string $field) => $settingsFailed ? old($field) : $mairie->{$field};
@endphp
<div class="modal fade" id="civilSettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ $settingsRoute }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="reglages" value="1">
                <div class="modal-header">
                    <h5 class="modal-title"><i class='bx bx-cog me-2'></i>Réglages de l'état civil — {{ $mairie->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted">Saisis une seule fois : repris sur chaque nouvel acte (en-tête, officier) et sur chaque extrait imprimé (certification).</p>
                    @if ($settingsFailed)
                        <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
                    @endif
                    <div class="nv-panel-title">Officier signataire</div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-7"><label class="form-label small mb-1" for="s-officer">Prénom(s) et nom</label><input id="s-officer" name="civil_officer_name" value="{{ $s('civil_officer_name') }}" class="form-control" required></div>
                        <div class="col-md-5"><label class="form-label small mb-1" for="s-title">Qualité</label><input id="s-title" name="civil_officer_title" value="{{ $s('civil_officer_title') ?? 'Officier de l\'état civil' }}" class="form-control" required></div>
                    </div>
                    <div class="nv-panel-title">Centre d'état civil</div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4"><label class="form-label small mb-1" for="s-ctype">Centre</label>
                            <select id="s-ctype" name="civil_centre_type" class="form-select">
                                @foreach (\App\Enums\CentreEtatCivilType::cases() as $type)
                                    <option value="{{ $type->value }}" @selected(($s('civil_centre_type') ?? 'principal') === $type->value)>{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8"><label class="form-label small mb-1" for="s-cname">De (nom du centre)</label><input id="s-cname" name="civil_centre_name" value="{{ $s('civil_centre_name') }}" class="form-control" required></div>
                    </div>
                    <div class="nv-panel-title">En-tête de l'extrait</div>
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label small mb-1" for="s-region">Région de</label><input id="s-region" name="civil_region" value="{{ $s('civil_region') }}" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label small mb-1" for="s-cercle">Cercle de</label><input id="s-cercle" name="civil_cercle" value="{{ $s('civil_cercle') }}" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label small mb-1" for="s-arr">Arrondissement de</label><input id="s-arr" name="civil_arrondissement" value="{{ $s('civil_arrondissement') }}" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label small mb-1" for="s-commune">Commune de</label><input id="s-commune" name="civil_commune" value="{{ $s('civil_commune') }}" class="form-control"></div>
                        <div class="col-12"><label class="form-label small mb-1" for="s-birth">Localité proposée par défaut (naissance, mariage)</label><input id="s-birth" name="civil_birth_place" value="{{ $s('civil_birth_place') }}" class="form-control" placeholder="Ex. Bamako"></div>
                    </div>
                    <div class="nv-panel-title mt-3">Impression des extraits</div>
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label small mb-1" for="s-price">Prix de l'extrait de naissance (FCFA)</label><input id="s-price" name="civil_extract_price" type="number" min="0" step="25" value="{{ $s('civil_extract_price') ?? 0 }}" class="form-control"><small class="text-muted">Encaissé à chaque extrait délivré (0 : gratuit).</small></div>
                        <div class="col-md-6"><label class="form-label small mb-1" for="s-mprice">Prix de l'extrait de mariage (FCFA)</label><input id="s-mprice" name="civil_marriage_extract_price" type="number" min="0" step="25" value="{{ $s('civil_marriage_extract_price') ?? 0 }}" class="form-control"><small class="text-muted">0 : gratuit.</small></div>
                        <div class="col-md-6"><label class="form-label small mb-1" for="s-mode">Papier utilisé</label>
                            <select id="s-mode" name="civil_print_mode" class="form-select">
                                <option value="blanc" @selected($s('civil_print_mode') !== 'preimprime')>Feuille blanche — modèle complet de l'extrait (recommandé)</option>
                                <option value="preimprime" @selected($s('civil_print_mode') === 'preimprime')>Formulaire officiel déjà imprimé — valeurs seules</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3"><label class="form-label small mb-1" for="s-ox">Décalage → (mm)</label><input id="s-ox" name="civil_print_offset_x" type="number" step="0.5" min="-30" max="30" value="{{ $s('civil_print_offset_x') ?? 0 }}" class="form-control"></div>
                        <div class="col-6 col-md-3"><label class="form-label small mb-1" for="s-oy">Décalage ↓ (mm)</label><input id="s-oy" name="civil_print_offset_y" type="number" step="0.5" min="-30" max="30" value="{{ $s('civil_print_offset_y') ?? 0 }}" class="form-control"></div>
                        <div class="col-12 small text-muted">Pour régler l'alignement : imprimez un extrait en mode pré-imprimé sur une feuille blanche, posez-la sur un formulaire officiel à contre-jour, puis corrigez le décalage (positif = vers la droite / vers le bas).</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>
@if ($settingsFailed)
    <script>
        window.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('civilSettingsModal')).show());
    </script>
@endif
