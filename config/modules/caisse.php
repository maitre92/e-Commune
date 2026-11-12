<?php

/*
| Manifeste du module « caisse » : les recettes de la mairie. Chaque vignette vendue et chaque extrait d'acte de
| naissance payant crée une recette numérotée (quittance) au nom de l'agent qui a encaissé. En fin de journée,
| chaque agent verse sa caisse au caissier (ou au maire), qui enregistre le versement ; l'état journalier
| s'imprime. Cloisonné par mairie (Recette, Versement utilisent BelongsToMairie).
|
| `view_own` : l'agent voit ses propres recettes (ce qu'il doit verser). `view` : toute la caisse de la mairie.
| `receive` : enregistrer le versement d'un agent. Maire, secrétaire général et caissier voient toute la caisse ;
| `view` et `receive` s'attribuent aussi en exception à un agent (Paramètres → Attribution des permissions).
*/

return [
    'label' => 'Caisse',

    'permissions' => [
        'view_own' => 'Voir ses propres recettes',
        'view' => 'Voir la caisse de la mairie',
        'receive' => 'Enregistrer le versement d\'un agent',
    ],

    'reserved' => [],

    'roles' => [
        'secretaire_general' => [
            'defaults' => ['view_own', 'view', 'receive'],
            'optional' => [],
        ],
        'maire' => [
            'defaults' => ['view_own', 'view', 'receive'],
            'optional' => [],
        ],
        'caissier' => [
            'defaults' => ['view_own', 'view', 'receive'],
            'optional' => [],
        ],
        'mairie' => [
            'defaults' => ['view_own'],
            // Attribuable en exception (Paramètres → Attribution) à un agent chargé de suivre la caisse.
            'optional' => ['view', 'receive'],
        ],
        'agent_vignette' => [
            'defaults' => ['view_own'],
            // Attribuable en exception (Paramètres → Attribution) à un agent chargé de suivre la caisse.
            'optional' => ['view', 'receive'],
        ],
        'agent_etat_civil' => [
            'defaults' => ['view_own'],
            // Attribuable en exception (Paramètres → Attribution) à un agent chargé de suivre la caisse.
            'optional' => ['view', 'receive'],
        ],
        'admin_national' => [
            'defaults' => ['view'],
            'optional' => [],
        ],
    ],

    'navigation' => [
        ['label' => 'Caisse', 'icon' => 'bx bx-wallet', 'route' => 'caisse.index', 'permission' => 'caisse.view_own', 'active' => 'caisse.*', 'order' => 80],
    ],
];
