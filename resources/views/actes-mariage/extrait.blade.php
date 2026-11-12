{{-- Extrait d'acte de mariage imprimable (A4), au gabarit de l'extrait malien (rubriques 1 à 24) : cadre et libellés
     imprimés, valeurs en frappe de machine à écrire. Le cachet et la signature restent à apposer à la main.
     Mêmes modes que l'extrait de naissance : « blanc » (modèle complet) ou « preimprime » (valeurs seules). --}}
@php
    $up = fn (?string $value): string => $value === null || trim($value) === '' ? '' : mb_strtoupper(trim($value));
    $d = fn (?\Carbon\CarbonInterface $date): string => $date ? $date->format('d-m-Y') : '';
    $mairie = $acte->mairie;
    $mode = in_array(request('mode'), ['blanc', 'preimprime'], true) ? request('mode') : ($mairie?->civil_print_mode ?? 'blanc');
    $offsetX = (float) ($mairie?->civil_print_offset_x ?? 0);
    $offsetY = (float) ($mairie?->civil_print_offset_y ?? 0);
    $switchUrl = fn (string $to) => request()->fullUrlWithQuery(['mode' => $to, 'print' => null]);
    $parent = fn (string $label, ?string $first, ?string $last) => ['label' => $label, 'first' => $up($first) ?: '/', 'last' => $up($last) ?: '/'];
    $filiation = [
        'Époux' => [$parent('15 Père', $acte->husband_father_first_names, $acte->husband_father_last_name), $parent('16 Mère', $acte->husband_mother_first_names, $acte->husband_mother_last_name)],
        'Épouse' => [$parent('15 Père', $acte->wife_father_first_names, $acte->wife_father_last_name), $parent('16 Mère', $acte->wife_mother_first_names, $acte->wife_mother_last_name)],
    ];
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Extrait d'acte de mariage n° {{ $acte->act_number }} — {{ $acte->couple() }}</title>
    <style>
        @font-face { font-family: 'Special Elite'; src: url('{{ asset('assets/fonts/special-elite-regular.ttf') }}') format('truetype'); font-display: block; }
        /* Marge de page nulle : la feuille fait exactement une page A4 et le navigateur n'a plus de place pour ses
           en-têtes et pieds de page (titre, adresse, date, « 1 sur 2 »). Les marges sont recréées dans la feuille. */
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        html, body { margin: 0; padding: 0; }
        body { background: #e9e6df; font-family: Arial, Helvetica, sans-serif; color: #111; }

        .toolbar { position: sticky; top: 0; z-index: 5; display: flex; gap: 8px; align-items: center; justify-content: center; padding: 10px; background: #123a63; color: #fff; font-size: 13px; }
        .toolbar button { font: inherit; border: 0; border-radius: 6px; padding: 7px 14px; cursor: pointer; background: #fff; color: #123a63; font-weight: 600; }
        .toolbar button.ghost { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,.6); }
        .toolbar span { opacity: .8; }

        .sheet { width: 210mm; height: 297mm; margin: 14px auto; padding: 5mm 9mm; background: #fdfcf8; box-shadow: 0 4px 24px rgba(0,0,0,.18); position: relative; overflow: hidden; }
        .frame { border: 1.6px solid #111; margin: 0; padding: 2.2mm; height: 100%; display: flex; flex-direction: column; gap: 1.5mm; overflow: hidden; }
        .box { border: 1.2px solid #111; }

        /* En-tête */
        .head { padding: 2.5mm 4mm 2mm; position: relative; }
        .head-grid { display: flex; justify-content: space-between; gap: 6mm; }
        .admin { flex: 1 1 auto; font-size: 9.5pt; font-weight: 700; line-height: 1; }
        .admin .row { display: flex; align-items: flex-end; margin-bottom: 1.6mm; }
        .admin .lbl { white-space: nowrap; }
        .line { flex: 1 1 auto; border-bottom: 1px solid #111; min-height: 5mm; margin-left: 1.5mm; position: relative; display: flex; align-items: flex-end; }
        .line.short { flex: 0 0 34mm; }
        .republic { flex: 0 0 62mm; text-align: center; font-size: 9.5pt; font-weight: 700; line-height: 1.35; padding-top: 1mm; }
        .republic small { display: block; font-weight: 700; }
        .republic .dash { letter-spacing: 1.5px; font-weight: 400; }
        .title { display: flex; align-items: flex-end; justify-content: center; margin: 2.5mm 0 .5mm; font-size: 16.5pt; font-weight: 900; letter-spacing: .2px; }
        .title .line { flex: 0 0 46mm; }
        .nina { display: flex; align-items: center; justify-content: center; gap: 3mm; font-size: 9.5pt; font-weight: 700; margin-bottom: 2mm; }
        .nina .cells { display: flex; }
        .nina .cell { width: 5.6mm; height: 6.6mm; border: 1px solid #111; border-left-width: 0; display: flex; align-items: center; justify-content: center; }
        .nina .cell:first-child { border-left-width: 1px; }

        /* Rubriques */
        .rows .r { display: flex; align-items: flex-end; min-height: 6mm; padding: 0 2.5mm .9mm; border-bottom: 1px solid #111; font-size: 9.6pt; font-weight: 700; }
        .rows .r:last-child { border-bottom: 0; }
        .rows .r.tall { min-height: 15mm; flex-direction: column; align-items: stretch; justify-content: flex-end; }
        .rows .r.tall .sub { display: flex; align-items: flex-end; min-height: 7mm; }
        .rows .r.tall .sub + .sub { border-top: 1px solid #111; margin: 0 -2.5mm; padding: 0 2.5mm; }
        .lab { white-space: nowrap; padding-right: 3mm; }
        .val { flex: 1 1 auto; min-height: 5mm; display: flex; align-items: flex-end; }
        .parent { display: flex; }
        .parent .side { flex: 0 0 9mm; border-right: 1.2px solid #111; display: flex; align-items: center; justify-content: center; }
        .parent .side b { writing-mode: vertical-rl; transform: rotate(180deg); font-family: Georgia, 'Times New Roman', serif; font-size: 14pt; letter-spacing: .5px; }
        .parent .rows { flex: 1 1 auto; margin-left: 2mm; border-left: 1.2px solid #111; }

        /* Certification */
        .certif { padding: 2.5mm 4mm 2mm; font-size: 9.6pt; font-style: italic; line-height: 1; position: relative; flex: 1 1 auto; }
        .certif .c { display: flex; align-items: flex-end; margin-bottom: 2mm; white-space: nowrap; }
        .certif .c b { font-weight: 700; }
        .dots { flex: 1 1 auto; border-bottom: 1.4px dotted #111; min-height: 5.4mm; margin: 0 1.2mm; display: flex; align-items: flex-end; justify-content: center; font-style: normal; }
        .dots.w25 { flex: 0 0 24mm; } .dots.w30 { flex: 0 0 32mm; } .dots.w45 { flex: 0 0 46mm; }
        .certif-foot { display: flex; justify-content: space-between; align-items: flex-start; gap: 6mm; }
        .certif-foot .c { flex: 0 0 auto; }
        .sign { width: 62mm; text-align: center; font-style: normal; font-weight: 700; font-size: 9.6pt; }
        .sign .stamp { height: 17mm; border: 1px dashed #b9b3a6; border-radius: 50%; width: 17mm; margin: 1mm auto 0; display: flex; align-items: center; justify-content: center; color: #b9b3a6; font-weight: 400; font-size: 7pt; font-style: italic; }
        .issuer { position: absolute; right: 3mm; top: 50%; transform: translateY(-50%) rotate(180deg); writing-mode: vertical-rl; font-size: 6.5pt; color: #333; }

        /* Filiation : « FILIATION » | « ÉPOUX » / « ÉPOUSE » | « 15 Père » / « 16 Mère » | Prénom(s), Nom */
        .fil { display: flex; }
        .fil > .side { flex: 0 0 9mm; }
        .fil .who { flex: 1 1 auto; display: flex; flex-direction: column; gap: 1mm; margin-left: 2mm; }
        .fil .who-row { display: flex; }
        .fil .who-row > .side { flex: 0 0 7mm; border: 1.2px solid #111; display: flex; align-items: center; justify-content: center; }
        .fil .who-row > .side b { writing-mode: vertical-rl; transform: rotate(180deg); font-family: Georgia, 'Times New Roman', serif; font-size: 9pt; }
        .fil .par { flex: 1 1 auto; display: flex; flex-direction: column; margin-left: 1.5mm; gap: .8mm; }
        .fil .par-row { display: flex; }
        .fil .par-row > .pl { flex: 0 0 20mm; border: 1.2px solid #111; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 10pt; }
        .fil .par-row > .rows { flex: 1 1 auto; margin-left: 1.5mm; border: 1.2px solid #111; }
        .fil .par-row .r { min-height: 5mm; }
        .side-big { border-right: 1.2px solid #111; display: flex; align-items: center; justify-content: center; }
        .side-big b { writing-mode: vertical-rl; transform: rotate(180deg); font-family: Georgia, 'Times New Roman', serif; font-size: 13pt; letter-spacing: .5px; }

        /* Frappe de machine à écrire */
        .tw { font-family: 'Special Elite', 'Courier New', Courier, monospace; font-weight: 400; font-style: normal; font-size: 11.8pt; letter-spacing: .6px; color: #1b1b1d; line-height: 1; white-space: pre; display: inline-block; }
        .tw .ch { display: inline-block; }
        .title .tw { font-size: 15pt; }
        .nina .tw { font-size: 12pt; letter-spacing: 0; }
        .certif .tw { font-size: 12.4pt; }

        @media print {
            /* La feuille est légèrement réduite (196 × 278 mm) pour tenir sur une seule page aussi bien en A4 qu'en
               « Lettre US » (279 mm de haut), le format souvent proposé par défaut quand on enregistre en PDF. */
            html, body { width: auto; height: auto; margin: 0; overflow: hidden; background: #fff; }
            .toolbar { display: none; }
            .sheet { zoom: .935; margin: 0 auto; box-shadow: none; background: #fff; break-inside: avoid; break-after: avoid; page-break-after: avoid; }
            .sign .stamp { border-color: transparent; color: transparent; }
        }

        /* Formulaire pré-imprimé : le cadre, les libellés et les lignes sont déjà sur le papier officiel ; seules les
           valeurs tapées s'impriment, aux mêmes emplacements, décalées du réglage de la mairie. */
        @if (! empty($fromApp))
            /* Impression depuis l'application mobile : le téléphone impose ses marges (souvent ~10 mm) et ignore
               « margin: 0 » ; la feuille est donc réduite à 186 × 262 mm, qui tient dans une page A4 même avec ~17 mm de marges. */
            @page { size: A4 portrait; margin: 10mm; }
            .toolbar { display: none; }
            html, body { width: auto; height: auto; overflow: visible; background: #fff; }
            /* Même modèle réduit à 177 × 249 mm : tient sur une page A4 ou « Lettre US » (PDF du téléphone), même
               avec 15 mm de marges. */
            .sheet { zoom: .86; width: 206mm; height: 290mm; margin: 0 auto; padding: 0; box-shadow: none; background: #fff; }
            .issuer { right: -4mm; }
        @endif

        body.preimprime .sheet, body.preimprime .sheet * { border-color: transparent !important; background: transparent !important; box-shadow: none !important; }
        body.preimprime .frame { transform: translate({{ $offsetX }}mm, {{ $offsetY }}mm); }
        body.preimprime .issuer, body.preimprime .sign .stamp { visibility: hidden; }
        @media screen {
            /* À l'écran, le formulaire reste visible en filigrane pour contrôler les emplacements. */
            body.preimprime .sheet { background: #fff !important; }
            body.preimprime .sheet, body.preimprime .sheet * { border-color: rgba(0, 0, 0, .12) !important; }
            body.preimprime .sheet :not(.tw):not(.ch) { color: rgba(0, 0, 0, .25); }
            body.preimprime .tw, body.preimprime .tw .ch { color: #1b1b1d; }
        }
        @media print {
            body.preimprime .sheet :not(.tw):not(.ch) { color: transparent !important; }
            body.preimprime .tw, body.preimprime .tw .ch { color: #1b1b1d !important; }
        }
        .toolbar a { color: #fff; font-weight: 600; text-decoration: none; padding: 7px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,.5); }
        .toolbar a.on { background: #fff; color: #123a63; }
    </style>
</head>
<body class="{{ $mode }}">
    <div class="toolbar">
        @if (! $extrait->exists)
            <span style="background:#fcd116;color:#111;padding:6px 10px;border-radius:6px;font-weight:700">Aperçu — extrait pas encore délivré</span>
        @else
            <button type="button" onclick="window.print()">Imprimer l'extrait</button>
        @endif
        <a href="{{ $switchUrl('blanc') }}" class="{{ $mode === 'blanc' ? 'on' : '' }}">Feuille blanche</a>
        <a href="{{ $switchUrl('preimprime') }}" class="{{ $mode === 'preimprime' ? 'on' : '' }}">Formulaire pré-imprimé</a>
        <button type="button" class="ghost" onclick="window.close()">Fermer</button>
        <span>{{ $mode === 'preimprime' ? 'Valeurs seules : placez le formulaire officiel dans l\'imprimante.' : 'Format A4, une seule page.' }}</span>
    </div>

    <div class="sheet">
        <div class="frame">
            <div class="box head">
                <div class="head-grid">
                    <div class="admin">
                        <div class="row"><span class="lbl">REGION DE :</span><span class="line"><span class="tw">{{ $up($acte->region) }}</span></span></div>
                        <div class="row"><span class="lbl">CERCLE DE :</span><span class="line"><span class="tw">{{ $up($acte->cercle) }}</span></span></div>
                        <div class="row"><span class="lbl">ARRONDISSEMENT DE :</span><span class="line"><span class="tw">{{ $up($acte->arrondissement) }}</span></span></div>
                        <div class="row"><span class="lbl">COMMUNE DE :</span><span class="line"><span class="tw">{{ $up($acte->commune) }}</span></span></div>
                        <div class="row">
                            <span class="lbl">CENTRE :</span><span class="line short"><span class="tw">{{ $up($acte->centre_type->label()) }}</span></span>
                            <span class="lbl" style="margin-left:1.5mm">DE :</span><span class="line"><span class="tw">{{ $up($acte->centre_name) }}</span></span>
                        </div>
                    </div>
                    <div class="republic">
                        REPUBLIQUE DU MALI
                        <small>Un Peuple - Un But - Une Foi</small>
                        <span class="dash">- - - - - - - - -</span>
                    </div>
                </div>
                <div class="title">EXTRAIT D'ACTE DE MARIAGE N°<span class="line"><span class="tw">{{ $up($acte->act_number) }}-{{ $acte->act_year }}</span></span></div>
            </div>

            <div class="parent">
                <div class="box side"><b>ÉPOUX</b></div>
                <div class="box rows">
                    <div class="r"><span class="lab">1 Prénom(s):</span><span class="val"><span class="tw">{{ $up($acte->husband_first_names) }}</span></span></div>
                    <div class="r"><span class="lab">2 Nom:</span><span class="val"><span class="tw">{{ $up($acte->husband_last_name) }}</span></span></div>
                    <div class="r"><span class="lab">3 Date de Naissance :</span><span class="val"><span class="tw">{{ $d($acte->husband_birth_date) }}</span></span></div>
                    <div class="r"><span class="lab">4 Localité de Naissance :</span><span class="val"><span class="tw">{{ $up($acte->husband_birth_place) }}</span></span></div>
                    <div class="r"><span class="lab">5 Sexe :</span><span class="val"><span class="tw">MASCULIN</span></span></div>
                    <div class="r"><span class="lab">6 Profession :</span><span class="val"><span class="tw">{{ $up($acte->husband_profession) ?: '/' }}</span></span></div>
                    <div class="r"><span class="lab">7 Domicile :</span><span class="val"><span class="tw">{{ $up($acte->husband_domicile) ?: '/' }}</span></span></div>
                </div>
            </div>

            <div class="parent">
                <div class="box side"><b>ÉPOUSE</b></div>
                <div class="box rows">
                    <div class="r"><span class="lab">8 Prénom(s):</span><span class="val"><span class="tw">{{ $up($acte->wife_first_names) }}</span></span></div>
                    <div class="r"><span class="lab">9 Nom:</span><span class="val"><span class="tw">{{ $up($acte->wife_last_name) }}</span></span></div>
                    <div class="r"><span class="lab">10 Date de Naissance :</span><span class="val"><span class="tw">{{ $d($acte->wife_birth_date) }}</span></span></div>
                    <div class="r"><span class="lab">11 Localité de Naissance :</span><span class="val"><span class="tw">{{ $up($acte->wife_birth_place) }}</span></span></div>
                    <div class="r"><span class="lab">12 Sexe :</span><span class="val"><span class="tw">FÉMININ</span></span></div>
                    <div class="r"><span class="lab">13 Profession :</span><span class="val"><span class="tw">{{ $up($acte->wife_profession) ?: '/' }}</span></span></div>
                    <div class="r"><span class="lab">14 Domicile :</span><span class="val"><span class="tw">{{ $up($acte->wife_domicile) ?: '/' }}</span></span></div>
                </div>
            </div>

            <div class="fil">
                <div class="box side side-big"><b>FILIATION</b></div>
                <div class="who">
                    @foreach ($filiation as $who => $parents)
                        <div class="who-row">
                            <div class="side"><b>{{ mb_strtoupper($who) }}</b></div>
                            <div class="par">
                                @foreach ($parents as $p)
                                    <div class="par-row">
                                        <div class="pl">{{ $p['label'] }}</div>
                                        <div class="rows">
                                            <div class="r"><span class="lab">Prénom(s):</span><span class="val"><span class="tw">{{ $p['first'] }}</span></span></div>
                                            <div class="r"><span class="lab">Nom:</span><span class="val"><span class="tw">{{ $p['last'] }}</span></span></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="box rows">
                <div class="r"><span class="lab">17 Date de Déclaration</span><span class="val"><span class="tw">{{ $d($acte->declared_at) }}</span></span></div>
                <div class="r"><span class="lab">18 Date de célébration</span><span class="val"><span class="tw">{{ $d($acte->celebrated_at) }}</span></span></div>
                <div class="r"><span class="lab">19 Localité du Mariage</span><span class="val"><span class="tw">{{ $up($acte->marriage_place) }}</span></span></div>
                <div class="r"><span class="lab">20 Régime Matrimonial</span><span class="val"><span class="tw">{{ $up($acte->regime->label()) }}</span></span></div>
                <div class="r"><span class="lab">21 Option Matrimoniale</span><span class="val"><span class="tw">{{ $up($acte->option->label()) }}</span></span></div>
                <div class="r"><span class="lab">22 Prénom(s) et Nom de l'Officier de l'état civil</span><span class="val"><span class="tw">{{ $up($acte->officer_name) }}</span></span></div>
                <div class="r"><span class="lab">23 Qualité</span><span class="val"><span class="tw">{{ $up($acte->officer_title) }}</span></span></div>
                <div class="r"><span class="lab">24 Date d'établissement de l'acte</span><span class="val"><span class="tw">{{ $d($acte->drawn_up_at) }}</span></span></div>
            </div>

            <div class="box certif">
                <div class="c">Certifié le présent extrait conforme à l'original n°<span class="dots w30"><span class="tw">{{ $up($acte->act_number) }}</span></span>de l'année<span class="dots w25"><span class="tw">{{ $acte->act_year }}</span></span></div>
                <div class="c">du Centre<span class="dots"><span class="tw">{{ $up($acte->centre_type->label()) }}</span></span>de<span class="dots"><span class="tw">{{ $up($acte->centre_name) }}</span></span></div>
                <div class="c"><b>Prénom(s) Nom et Qualité</b><span class="dots"><span class="tw">{{ $up($extrait->officer_name) }} — {{ $up($extrait->officer_title) }}</span></span></div>
                <div class="c">Officier de l'état civil du Centre<span class="dots w45"><span class="tw">{{ $up($extrait->centre_type->label()) }}</span></span>de<span class="dots"><span class="tw">{{ $up($extrait->centre_name) }}</span></span></div>
                <div class="certif-foot">
                    <div class="c">Date<span class="dots w45"><span class="tw">{{ $d($extrait->delivered_at) }}</span></span></div>
                    <div class="sign">
                        Officier de l'état civil
                        <div class="stamp">cachet et signature</div>
                    </div>
                </div>
            </div>
        </div>
        @if ($extrait->exists)
            <div class="issuer">{{ $acte->mairie?->name }} · extrait n° {{ $extrait->id }} délivré le {{ $extrait->delivered_at->format('d/m/Y') }}</div>
        @endif
    </div>

    <script>
        // Frappe de machine à écrire : chaque caractère est légèrement décalé, incliné et plus ou moins encré.
        // Générateur pseudo-aléatoire déterministe (graine = numéro de l'extrait de mariage) : l'aperçu et chaque
        // réimpression donnent exactement le même rendu.
        (function () {
            var seed = {{ (int) ($extrait->id ?? $acte->id) * 7919 + 17 }};
            function rand() { seed = (seed * 1103515245 + 12345) % 2147483648; return seed / 2147483648; }
            document.querySelectorAll('.tw').forEach(function (field) {
                var text = field.textContent;
                var baseline = (rand() - 0.5) * 1.6;
                field.textContent = '';
                field.style.transform = 'translateY(' + baseline.toFixed(2) + 'px)';
                Array.from(text).forEach(function (char) {
                    var span = document.createElement('span');
                    span.className = 'ch';
                    span.textContent = char;
                    if (char !== ' ') {
                        var dy = (rand() - 0.5) * 1.1;
                        var dx = (rand() - 0.5) * 0.6;
                        var rot = (rand() - 0.5) * 2.2;
                        var ink = 0.72 + rand() * 0.28;
                        span.style.transform = 'translate(' + dx.toFixed(2) + 'px,' + dy.toFixed(2) + 'px) rotate(' + rot.toFixed(2) + 'deg)';
                        span.style.opacity = ink.toFixed(2);
                        if (rand() > 0.86) { span.style.textShadow = '0.35px 0 0 rgba(27,27,29,.55)'; }
                    }
                    field.appendChild(span);
                });
            });

            if (new URLSearchParams(window.location.search).get('print') === '1') {
                (document.fonts ? document.fonts.ready : Promise.resolve()).then(function () {
                    setTimeout(function () { window.focus(); window.print(); }, 150);
                });
            }
        })();
    </script>
</body>
</html>
