<?php

/*
| Manifeste du module « users » (D6, D7). Une clé par permission `module.action` ; `roles` donne, par rôle, les
| permissions par défaut (`defaults`, synchronisées par permissions:sync) et celles attribuables en exception
| (`optional`) ; `navigation` alimente le menu (les entrées dont la route n'existe pas encore sont ignorées).
*/

return [
    'label' => 'Utilisateurs',

    'permissions' => [
        'view' => 'Voir les utilisateurs',
        'create' => 'Créer un utilisateur',
        'update' => 'Modifier un utilisateur',
        'delete' => 'Supprimer un utilisateur',
        'activate' => 'Activer ou désactiver un utilisateur',
        'reset_password' => 'Réinitialiser un mot de passe',
        'revoke_access' => 'Révoquer les sessions et jetons',
    ],

    'reserved' => [],

    'roles' => [
        'admin_national' => [
            'defaults' => ['view', 'create', 'update', 'activate', 'reset_password', 'revoke_access'],
            'optional' => ['delete'],
        ],
        // Le maire crée et gère les agents de sa mairie (UserPolicy, User::visibleTo).
        'maire' => [
            'defaults' => ['view', 'create', 'update', 'activate', 'reset_password', 'revoke_access'],
            'optional' => ['delete'],
        ],
    ],

    // Pas d'entrée de menu : les comptes se gèrent dans Paramètres → Utilisateurs (le maire y crée ses agents ;
    // raccourci « Créer un agent » sur son tableau de bord).
    'navigation' => [],
];
