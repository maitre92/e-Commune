<?php

/*
| Manifeste du module « actes-mariage » : registre numérique des actes de mariage de la mairie (rubriques 1 à 24
| de l'extrait d'acte de mariage) et délivrance d'extraits imprimés au gabarit de l'extrait malien, en frappe
| machine à écrire. Cloisonné par mairie (ActeMariage utilise BelongsToMairie). Mêmes réglages d'état civil que les
| actes de naissance (en-tête, officier signataire), saisis une fois ; prix de l'extrait propre au mariage.
*/

return [
    'label' => 'Actes de mariage',

    'permissions' => [
        'view' => 'Voir les actes de mariage',
        'create' => 'Enregistrer un acte de mariage',
        'update' => 'Modifier un acte de mariage',
        'delete' => 'Supprimer un acte de mariage',
        'deliver' => 'Délivrer un extrait d\'acte de mariage',
        'configure' => 'Régler l\'état civil de la mairie (en-tête, officier signataire)',
        'view_all' => 'Voir les actes de mariage saisis par tous les agents',
    ],

    'reserved' => [],

    'roles' => [
        'secretaire_general' => [
            'defaults' => ['view', 'view_all'],
            'optional' => [],
        ],
        'maire' => [
            'defaults' => ['view', 'create', 'update', 'deliver', 'configure', 'view_all'],
            'optional' => ['delete'],
        ],
        'mairie' => [
            'defaults' => ['view', 'create', 'update', 'deliver', 'configure'],
            'optional' => ['delete'],
        ],
        'agent_etat_civil' => [
            'defaults' => ['view', 'create', 'update', 'deliver'],
            'optional' => ['delete'],
        ],
        'admin_national' => [
            'defaults' => ['view', 'view_all'],
            'optional' => [],
        ],
    ],

    'navigation' => [
        ['label' => 'Actes de mariage', 'icon' => 'bx bx-heart', 'route' => 'actes-mariage.index', 'permission' => 'actes-mariage.view', 'active' => 'actes-mariage.*', 'order' => 72],
    ],
];
