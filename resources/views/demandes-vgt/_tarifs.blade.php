{{-- Tarifs des vignettes de la mairie : une catégorie par tranche de cylindrée, et la pénalité de retard. Le maire
     par défaut, ou l'agent à qui il a délégué la permission (App\Http\Controllers\Web\Admin\TarifVgtController). --}}
@php
    $tf = $errors->any() && old('tarifs_form');
    $tv = fn (string $key, $default) => $tf ? old($key, $default) : $default;
    [$deadlineMonth, $deadlineDay] = array_map('intval', explode('-', $vgtMairie?->vgt_deadline ?: '03-31'));
    $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $mode = $tv('vgt_late_mode', ($vgtMairie?->vgt_late_mode ?? \App\Enums\VgtLateMode::Double)->value);
@endphp
<div class="modal fade" id="tarifsVgtModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('tarifs-vgt.save') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="tarifs_form" value="1">
                <div class="modal-header">
                    <h5 class="modal-title"><i class='bx bx-money me-2'></i>Tarifs des vignettes et pénalité de retard</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body p-4">
                    @if ($tf && $errors->any())
                        <div class="alert alert-danger py-2 small mb-3">{{ $errors->first() }}</div>
                    @endif

                    <h6 class="fw-bold mb-2">Prix selon la cylindrée</h6>
                    <p class="small text-muted mb-2">Le guichet propose les catégories actives. Une vignette déjà délivrée garde le montant payé.</p>
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>CATÉGORIE (IMPRIMÉE SUR LA CARTE)</th>
                                <th width="14%">DE (CM³)</th>
                                <th width="14%">À (CM³)</th>
                                <th width="20%">PRIX (FCFA)</th>
                                <th width="9%" class="text-center">ACTIVE</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tarifs as $tarif)
                                <tr>
                                    <td class="fw-semibold">{{ $tarif->type_or_brand }}</td>
                                    <td><input type="number" class="form-control form-control-sm" name="tarifs[{{ $tarif->id }}][cylinder_min]" value="{{ $tv("tarifs.{$tarif->id}.cylinder_min", $tarif->cylinder_min) }}" min="0" max="5000"></td>
                                    <td><input type="number" class="form-control form-control-sm" name="tarifs[{{ $tarif->id }}][cylinder_max]" value="{{ $tv("tarifs.{$tarif->id}.cylinder_max", $tarif->cylinder_max) }}" min="0" max="5000"></td>
                                    <td><input type="number" class="form-control form-control-sm fw-bold" name="tarifs[{{ $tarif->id }}][amount]" value="{{ $tv("tarifs.{$tarif->id}.amount", $tarif->amount) }}" min="0" required></td>
                                    <td class="text-center">
                                        <input type="hidden" name="tarifs[{{ $tarif->id }}][is_active]" value="0">
                                        <input type="checkbox" class="form-check-input" name="tarifs[{{ $tarif->id }}][is_active]" value="1" @checked((bool) $tv("tarifs.{$tarif->id}.is_active", $tarif->is_active))>
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="table-light">
                                <td><input type="text" class="form-control form-control-sm" name="new_label" value="{{ $tv('new_label', '') }}" placeholder="Nouvelle catégorie (ex. Tricycle)"></td>
                                <td><input type="number" class="form-control form-control-sm" name="new_cylinder_min" value="{{ $tv('new_cylinder_min', '') }}" min="0" max="5000"></td>
                                <td><input type="number" class="form-control form-control-sm" name="new_cylinder_max" value="{{ $tv('new_cylinder_max', '') }}" min="0" max="5000"></td>
                                <td><input type="number" class="form-control form-control-sm" name="new_amount" value="{{ $tv('new_amount', '') }}" min="0"></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>

                    <h6 class="fw-bold mt-4 mb-2">Pénalité de retard</h6>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label small mb-1">Date limite de paiement (chaque année)</label>
                            <div class="d-flex gap-2">
                                <select name="deadline_day" class="form-select">
                                    @foreach (range(1, 31) as $day)
                                        <option value="{{ $day }}" @selected((int) $tv('deadline_day', $deadlineDay) === $day)>{{ $day }}</option>
                                    @endforeach
                                </select>
                                <select name="deadline_month" class="form-select">
                                    @foreach ($months as $i => $month)
                                        <option value="{{ $i + 1 }}" @selected((int) $tv('deadline_month', $deadlineMonth) === $i + 1)>{{ $month }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Après la date limite</label>
                            <select name="vgt_late_mode" class="form-select" id="tarifs-late-mode">
                                @foreach (\App\Enums\VgtLateMode::cases() as $case)
                                    <option value="{{ $case->value }}" @selected($mode === $case->value)>{{ $case->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3" id="tarifs-late-amount">
                            <label class="form-label small mb-1">Majoration (FCFA)</label>
                            <input type="number" class="form-control" name="vgt_late_amount" value="{{ $tv('vgt_late_amount', $vgtMairie?->vgt_late_amount) }}" min="0">
                        </div>
                    </div>
                    <p class="small text-muted mt-2 mb-0">Une vignette d'une année déjà passée est toujours en retard. « Prix doublé » : la pénalité est égale au prix de la catégorie.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="submit" class="btn btn-primary"><i class='bx bx-save me-1'></i>Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>
@push('scripts')
    <script>
        (() => {
            const mode = document.getElementById('tarifs-late-mode');
            const amount = document.getElementById('tarifs-late-amount');
            const sync = () => amount.classList.toggle('d-none', mode.value !== 'fixe');
            mode?.addEventListener('change', sync);
            if (mode) sync();
        })();
    </script>
@endpush
