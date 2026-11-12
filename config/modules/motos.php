<?php

/*
| Manifeste du module « motos » : registre des motos de la mairie (matricule, couleur, genre ou marque, dernière
| année de vignette, attestation de vente), chacune liée à son propriétaire. Cloisonné par mairie (Moto utilise
| BelongsToMairie). Le matricule est unique au niveau national.
*/

return [
    'label' => 'Motos',

    'permissions' => [
        'view' => 'Voir les motos',
        'create' => 'Créer une moto',
        'update' => 'Modifier une moto',
        'delete' => 'Supprimer une moto',
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
