<?php

/*
| Manifeste du module « demandes-vgt » : les vignettes (VGT) délivrées par la mairie. L'agent enregistre la
| vignette d'une moto du registre pour une année, confirme le paiement, imprime la carte (5 modèles) et confirme
| sa remise au propriétaire. Cloisonné par mairie (DemandeVgt utilise BelongsToMairie).
|
| Tarif selon la cylindrée, réglé par chaque mairie (table tarifs_vgt), et pénalité de retard de la mairie (date
| limite, prix doublé ou majoration fixe). `manage_tarifs` : le maire par défaut, qui peut la déléguer à n'importe
| quel agent de sa mairie (`optional`, fenêtre de modification de l'agent).
*/

return [
    'label' => 'Vignettes',

    'permissions' => [
        'view' => 'Voir les vignettes',
        'create' => 'Enregistrer une vignette',
        'update' => 'Modifier une vignette non payée',
        'manage_tarifs' => 'Gérer les tarifs des vignettes',
        'confirm_payment' => 'Confirmer le paiement d\'une vignette',
        'confirm_retrait' => 'Confirmer la remise de la carte de vignette',
        'view_all' => 'Voir les vignettes de tous les agents',
        'card_settings' => 'Choisir le modèle de carte de l\'année, le logo et le monument de la mairie',
    ],

    'reserved' => [],

    'roles' => [
        'secretaire_general' => [
            'defaults' => ['view', 'view_all'],
            'optional' => ['manage_tarifs', 'card_settings'],
        ],
        'maire' => [
            'defaults' => ['view', 'create', 'update', 'manage_tarifs', 'card_settings', 'confirm_payment', 'confirm_retrait', 'view_all'],
            'optional' => [],
        ],
        'mairie' => [
            'defaults' => ['view', 'create', 'update', 'confirm_payment', 'confirm_retrait'],
            'optional' => ['manage_tarifs', 'card_settings'],
        ],
        'agent_vignette' => [
            'defaults' => ['view', 'create', 'update', 'confirm_payment', 'confirm_retrait'],
            'optional' => ['manage_tarifs', 'card_settings'],
        ],
        'caissier' => [
            'defaults' => ['view', 'view_all'],
            'optional' => ['manage_tarifs', 'card_settings'],
        ],
        'admin_national' => [
            'defaults' => ['view', 'view_all'],
            'optional' => [],
        ],
    ],

    'navigation' => [
        ['label' => 'Vignettes', 'icon' => 'bx bx-id-card', 'route' => 'demandes-vgt.index', 'permission' => 'demandes-vgt.view', 'active' => 'demandes-vgt.*', 'order' => 60],
    ],
];
