<?php

/*
|--------------------------------------------------------------------------
| Canaux d'accès par rôle
|--------------------------------------------------------------------------
|
| Web : session, navigateur. API : jetons Bearer, application mobile e-Commune
| (mobile/), pour le maire et tous ses agents (guichets, caisse).
|
*/

return [

    'web' => ['superadmin', 'admin_national', 'maire', 'secretaire_general', 'mairie', 'agent_vignette', 'agent_etat_civil', 'caissier'],

    'api' => ['maire', 'secretaire_general', 'mairie', 'agent_vignette', 'agent_etat_civil', 'caissier'],

];
