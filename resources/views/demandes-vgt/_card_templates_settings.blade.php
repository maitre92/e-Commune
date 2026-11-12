{{-- Réglages de la carte de vignette : modèle de l'année (un seul par année), puis logo et monument de chaque mairie — modèle présélectionné, logo et monument — gardé par `mairies.update`. --}}
<div class="modal fade" id="cardTemplatesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class='bx bx-id-card me-2'></i>Carte de vignette : modèle de l'année, logo et monument</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body p-4">
                @if ($vgtMairie)
                <div class="nv-panel-title">Modèle de l'année — {{ $vgtMairie->name }}</div>
                <p class="small text-muted">Toutes les vignettes de la mairie pour une même année ont le même modèle. Choisissez-le avant la première vignette de l'année : il ne change plus ensuite.</p>
                <div class="row g-3 mb-4">
                    @foreach ($yearModels as $year => $yearModel)
                        <div class="col-md-6">
                            <div class="nv-panel h-100">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold">Vignettes {{ $year }}</span>
                                    @if ($yearModel['locked'])
                                        <span class="badge bg-secondary-subtle"><i class='bx bx-lock-alt'></i> Figé</span>
                                    @endif
                                </div>
                                <div class="nv-preview-card mx-auto mb-2" style="width:242px;height:153px;overflow:hidden">
                                    <iframe class="vgt-year-preview" data-year="{{ $year }}" src="about:blank" data-base="{{ route('demandes-vgt.model-preview', '__T__') }}?face=recto&annee={{ $year }}" title="Aperçu {{ $year }}" tabindex="-1" style="width:323px;height:204px;border:0;transform:scale(.75);transform-origin:0 0"></iframe>
                                </div>
                                <form method="POST" action="{{ route('demandes-vgt.year-model', $year) }}" class="d-flex gap-2">
                                    @csrf
                                    @method('PUT')
                                    <select class="form-select vgt-year-select" name="card_template" data-year="{{ $year }}" @disabled($yearModel['locked'])>
                                        @foreach (\App\Enums\VgtCardTemplate::cases() as $template)
                                            <option value="{{ $template->value }}" @selected($yearModel['template'] === $template)>{{ $template->label() }}</option>
                                        @endforeach
                                    </select>
                                    @unless ($yearModel['locked'])
                                        <button type="submit" class="btn btn-primary" title="Fixer ce modèle pour {{ $year }}"><i class='bx bx-save'></i></button>
                                    @endunless
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>

                @endif

                <div class="nv-panel-title">{{ $mairies->count() > 1 ? 'Logo et monument de chaque mairie' : 'Logo et monument de la mairie' }}</div>
                @forelse ($mairies as $mairieOption)
                    @include('demandes-vgt._card_settings_block', ['mairieOption' => $mairieOption, 'back' => 'demandes'])
                @empty
                    <p class="text-muted mb-0">Aucune mairie active pour l'instant.</p>
                @endforelse
                <p class="small text-muted mb-0">Les modèles et les armoiries par défaut sont des dessins d'après de vraies vignettes (à faire valider par les autorités avant mise en production).</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Aperçu du modèle choisi pour chaque année (chargé à l'ouverture de la modale).
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('cardTemplatesModal');
        const refresh = (select) => {
            const frame = modal.querySelector('.vgt-year-preview[data-year="' + select.dataset.year + '"]');
            frame.src = frame.dataset.base.replace('__T__', select.value);
        };
        modal.addEventListener('shown.bs.modal', () => modal.querySelectorAll('.vgt-year-select').forEach(refresh));
        modal.querySelectorAll('.vgt-year-select').forEach((select) => select.addEventListener('change', () => refresh(select)));
    });
</script>

@if ($errors->has('logo') || $errors->has('monument'))
    <script>
        window.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('cardTemplatesModal')).show());
    </script>
@endif
