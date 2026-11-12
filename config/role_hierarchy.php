<?php

/*
|--------------------------------------------------------------------------
| Hiérarchie des rôles
|--------------------------------------------------------------------------
|
| Source unique des niveaux, du plafond de rôle et du type d'institution de
| chaque rôle. Le maire crée et gère les agents de SA mairie (vendeurs de
| vignettes, agents d'état civil, agents polyvalents, caissiers). Personne
| n'agit sur un compte de niveau supérieur ou égal au sien, sauf le superadmin.
|
*/

return [

    'levels' => [
        'superadmin' => 100,
        'admin_national' => 80,
        'maire' => 60,
        'secretaire_general' => 55,
        'mairie' => 50,
        'agent_vignette' => 50,
        'agent_etat_civil' => 50,
        'caissier' => 50,
    ],

    // Plafond de rôle : rôles qu'un rôle peut créer et gérer.
    'manages' => [
        'superadmin' => ['superadmin', 'admin_national', 'maire', 'secretaire_general', 'mairie', 'agent_vignette', 'agent_etat_civil', 'caissier'],
        'admin_national' => ['maire', 'secretaire_general', 'mairie', 'agent_vignette', 'agent_etat_civil', 'caissier'],
        'maire' => ['secretaire_general', 'mairie', 'agent_vignette', 'agent_etat_civil', 'caissier'],
        'secretaire_general' => [],
        'mairie' => [],
        'agent_vignette' => [],
        'agent_etat_civil' => [],
        'caissier' => [],
    ],

    // Rôles liés à une mairie (les autres n'en ont aucune).
    'institution' => [
        'maire' => 'mairie',
        'secretaire_general' => 'mairie',
        'mairie' => 'mairie',
        'agent_vignette' => 'mairie',
        'agent_etat_civil' => 'mairie',
        'caissier' => 'mairie',
    ],

];
