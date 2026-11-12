<?php

/*
| Manifeste du module « proprietaires » : registre des propriétaires de motos de la mairie (nom, prénom, genre,
| adresse, téléphone, contact en cas d'urgence). Cloisonné par mairie (Proprietaire utilise BelongsToMairie).
*/

return [
    'label' => 'Propriétaires',

    'permissions' => [
        'view' => 'Voir les propriétaires',
        'create' => 'Créer un propriétaire',
        'update' => 'Modifier un propriétaire',
        'delete' => 'Supprimer un propriétaire',
    ],

    'reserved' => [],

    'roles' => [
        'maire' => [
            'defaults' => ['view', 'create', 'update', 'delete'],
            'optional' => [],
        ],
        'mairie' => [
            'defaults' => ['view', 'create', 'update', 'delete'],
            'optional' => [],
        ],
        'agent_vignette' => [
            'defaults' => ['view', 'create', 'update', 'delete'],
            'optional' => [],
        ],
    ],

    // Plus d'entrée de menu : au guichet, la moto s'enregistre toute seule avec son numéro de châssis (CH).
    // L'écran reste accessible par son adresse pour consulter ou corriger le registre.
    'navigation' => [],
];
