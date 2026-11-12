<?php

/*
|--------------------------------------------------------------------------
| Sidebar : entrées regroupées ou absentes
|--------------------------------------------------------------------------
|
| `registre` : les écrans du registre des motos (propriétaires, motos) n'ont pas chacun un lien dans le menu :
| une seule entrée y mène, et une barre d'onglets (partials/registre-tabs) permet de passer de l'un à l'autre.
| Chaque onglet n'apparaît que si l'utilisateur a la permission de la route.
|
| `embedded_planned` : modules de `config/planned_modules.php` déjà livrés à l'intérieur d'un autre écran.
|
*/

return [

    'registre' => [
        'label' => 'Registre motos',
        'icon' => 'bx bx-cycling',
        'landing' => 'motos.index',
        'tabs' => [
            ['label' => 'Propriétaires', 'icon' => 'bx bx-user-pin', 'route' => 'proprietaires.index', 'permission' => 'proprietaires.view', 'active' => 'proprietaires.*'],
            ['label' => 'Motos', 'icon' => 'bx bx-cycling', 'route' => 'motos.index', 'permission' => 'motos.view', 'active' => 'motos.*'],
        ],
    ],

    'embedded_planned' => [],

];
