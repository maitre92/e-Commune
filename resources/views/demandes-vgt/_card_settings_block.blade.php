{{-- Images de la carte propres à une mairie : logo (armoiries) et monument. Le modèle, lui, est fixé par année. Variables : $mairieOption, $back ('demandes' par défaut, ou 'mairies'). --}}
@php($back = $back ?? 'demandes')
<div class="nv-panel mb-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="fw-bold">{{ $mairieOption->name }}</div>
    </div>

    <div class="row g-3 align-items-end">
        <div class="col-md-6">
            @include('demandes-vgt._image_field', [
                'title' => 'Logo imprimé sur la carte', 'field' => 'logo', 'url' => $mairieOption->logoUrl(), 'default' => 'arms',
                'updateUrl' => route('mairies.logo.update', $mairieOption), 'resetUrl' => route('mairies.logo.reset', $mairieOption),
                'hint' => 'PNG, JPEG ou WebP, 100 × 100 px minimum, 1 Mo maximum.', 'back' => $back,
            ])
        </div>
        <div class="col-12"><hr class="my-1"></div>
        <div class="col-md-6">
            @include('demandes-vgt._image_field', [
                'title' => 'Image du monument (cartes 2025 et 2026)', 'field' => 'monument', 'url' => $mairieOption->monumentUrl(), 'default' => 'tower',
                'updateUrl' => route('mairies.monument.update', $mairieOption), 'resetUrl' => route('mairies.monument.reset', $mairieOption),
                'hint' => 'Photo réelle en portrait (idéalement PNG détouré), 2 Mo maximum.', 'back' => $back,
            ])
        </div>
    </div>
</div>
