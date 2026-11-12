{{-- Carte de vignette autonome (recto + verso, CR80) : chargée pour l'impression et l'aperçu des modèles. --}}
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Carte VGT — {{ $demande->moto->identifier() }}</title>
    <style>
        @font-face { font-family: 'Archivo Black'; src: url('{{ asset('assets/fonts/archivo-black-latin-400-normal.woff2') }}') format('woff2'); font-weight: 400; font-display: block; }
        @page { size: 85.6mm 54mm; margin: 0; }
        html, body { margin: 0; padding: 0; background: transparent; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @if ($face === 'recto') .vc-v { display: none; } @endif
        @if ($face === 'verso') .vc-r { display: none; } @endif
        @media screen { html, body { overflow: hidden; } }
    </style>
</head>
<body>
    @include($template->view(), ['demande' => $demande, 'holderName' => $demande->holderName(), 'holderPhone' => $demande->holderPhone()])
    <script src="{{ asset('assets/plugins/qrcode/qrcode.js') }}"></script>
    <script>
        document.querySelectorAll('[data-qr]').forEach(function (box) {
            var qr = qrcode(0, 'M');
            qr.addData(box.getAttribute('data-qr'));
            qr.make();
            box.innerHTML = qr.createSvgTag({ cellSize: 1, margin: 0, scalable: true });
        });
    </script>
</body>
</html>
