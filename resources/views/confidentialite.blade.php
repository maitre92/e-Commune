<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f5132">
    <title>Politique de confidentialité - {{ config('app.name') }}</title>
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/fonts.css') }}" rel="stylesheet">
    <style>
        body { margin: 0; font-family: 'Inter', system-ui, sans-serif; background: #f3f6f4; color: #1f2937; }
        header { background: linear-gradient(135deg, #1e7a46, #0f5132); color: #fff; padding: 2rem 1rem 1.6rem; text-align: center; }
        header h1 { font-size: 1.5rem; font-weight: 800; margin: 0; }
        header p { margin: .4rem 0 0; opacity: .9; font-size: .9rem; }
        .stripe { display: flex; width: 150px; height: 5px; margin: .8rem auto 0; border-radius: 5px; overflow: hidden; }
        .stripe span { flex: 1; }
        main { max-width: 760px; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
        section { background: #fff; border-radius: 14px; padding: 1.1rem 1.25rem; margin-bottom: 1rem; box-shadow: 0 1px 3px rgba(15, 23, 42, .06); }
        h2 { font-size: 1.05rem; font-weight: 800; color: #0f5132; margin: 0 0 .6rem; }
        p, li { font-size: .95rem; line-height: 1.55; }
        ul { padding-left: 1.2rem; margin-bottom: 0; }
        footer { text-align: center; font-size: .8rem; color: #64748b; padding-bottom: 2rem; }
    </style>
</head>
<body>
    <header>
        <h1>Politique de confidentialité</h1>
        <p>{{ config('app.name') }} — plateforme Web et application mobile des agents de mairie</p>
        <div class="stripe"><span style="background:#14b53a"></span><span style="background:#fcd116"></span><span style="background:#ce1126"></span></div>
    </header>

    <main>
        <section>
            <h2>1. Qui sommes-nous ?</h2>
            <p>{{ config('app.name') }} est l'outil de gestion des mairies : vente des vignettes de motos, actes de naissance et de
                mariage, caisse. Il est réservé aux <strong>agents de mairie</strong> : les comptes sont créés par la mairie, il
                n'est pas possible de s'inscrire depuis l'application. Chaque mairie est responsable des données qu'elle y
                enregistre.</p>
        </section>

        <section>
            <h2>2. Données traitées</h2>
            <ul>
                <li><strong>Compte de l'agent</strong> : nom, numéro de téléphone (identifiant de connexion), mot de passe
                    (enregistré chiffré, jamais en clair), fonction et mairie, photo de profil facultative.</li>
                <li><strong>Registres de la mairie</strong> saisis par les agents : actes de naissance et de mariage (noms, dates,
                    lieux, parents), vignettes (numéro d'engin, cylindrée, nom et téléphone du détenteur s'ils sont donnés),
                    recettes de la caisse.</li>
                <li><strong>Journal de sécurité</strong> : chaque action (connexion, saisie, impression, encaissement) est
                    enregistrée avec sa date, le compte, l'adresse IP et le type d'appareil.</li>
            </ul>
        </section>

        <section>
            <h2>3. Autorisations de l'application mobile</h2>
            <ul>
                <li><strong>Appareil photo et photos</strong> : uniquement quand l'agent choisit sa photo de profil.</li>
                <li><strong>Internet</strong> : échanger avec le serveur de la mairie.</li>
                <li><strong>Impression</strong> : les extraits et cartes sont envoyés à l'imprimante choisie par l'agent
                    (Wi-Fi, Bluetooth ou PDF).</li>
            </ul>
            <p class="mb-0 mt-2">L'application ne collecte ni la position, ni les contacts, ni le micro. Elle ne contient ni
                publicité, ni outil de statistiques, ni traceur.</p>
        </section>

        <section>
            <h2>4. Utilisation et partage</h2>
            <p>Les données servent uniquement à tenir les registres de la mairie, délivrer les documents et suivre la caisse.
                Elles ne sont <strong>ni vendues, ni partagées</strong> avec des tiers, ni utilisées à des fins commerciales.
                Chaque mairie ne voit que ses propres données ; un agent ne voit que ce que sa fonction lui permet.</p>
        </section>

        <section>
            <h2>5. Sécurité et conservation</h2>
            <p>Les échanges sont chiffrés (HTTPS). Sur le téléphone, la session est gardée dans le stockage sécurisé du système.
                Les actes d'état civil et les recettes sont conservés aussi longtemps que la loi l'impose aux mairies. Un
                compte d'agent est désactivé par la mairie quand l'agent quitte ses fonctions.</p>
        </section>

        <section>
            <h2>6. Vos droits</h2>
            <p class="mb-0">Pour consulter, corriger ou supprimer vos données, ou supprimer votre compte d'agent, adressez-vous à
                @if (config('app.contact_email'))
                    votre mairie ou écrivez à <a href="mailto:{{ config('app.contact_email') }}">{{ config('app.contact_email') }}</a>.
                @else
                    votre mairie.
                @endif
                Les citoyens inscrits dans un registre s'adressent au service de l'état civil de leur mairie.</p>
        </section>
    </main>

    <footer>Dernière mise à jour : 23 novembre 2026</footer>
</body>
</html>
