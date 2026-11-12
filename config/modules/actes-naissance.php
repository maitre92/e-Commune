<?php

/*
| Manifeste du module « actes-naissance » : registre numérique des actes de naissance de la mairie (rubriques 1
| à 16 de l'extrait d'acte de naissance) et délivrance d'extraits imprimés au gabarit de l'extrait malien,
| en frappe machine à écrire. Cloisonné par mairie (ActeNaissance utilise BelongsToMairie).
|
| `deliver` : certifier et imprimer un extrait (chaque délivrance est conservée et journalisée).
| `delete` : en exception seulement (un acte d'état civil ne se supprime normalement pas).
| `configure` : réglages d'état civil de sa mairie (en-tête, officier signataire), saisis une fois.
*/

return [
    'label' => 'Actes de naissance',

    'permissions' => [
        'view' => 'Voir les actes de naissance',
        'create' => 'Enregistrer un acte de naissance',
        'update' => 'Modifier un acte de naissance',
        'delete' => 'Supprimer un acte de naissance',
        'deliver' => 'Délivrer un extrait d\'acte de naissance',
        'configure' => 'Régler l\'état civil de la mairie (en-tête, officier signataire)',
        'view_all' => 'Voir les actes saisis par tous les agents',
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
        ['label' => 'Actes de naissance', 'icon' => 'bx bx-baby-carriage', 'route' => 'actes-naissance.index', 'permission' => 'actes-naissance.view', 'active' => 'actes-naissance.*', 'order' => 70],
    ],
];
