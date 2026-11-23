<?php

/*
|--------------------------------------------------------------------------
| Identité de l'application
|--------------------------------------------------------------------------
|
| Le nom est affiché en logo 3D bicolore dans la sidebar et sur la page de connexion :
| `logo.first` en bleu, `logo.second` en orange. Changer de nom = modifier ce fichier et APP_NAME dans .env.
| e-Commune : gestion numérique des vignettes de motos, des actes de naissance et de la caisse d'une mairie.
|
*/

return [

    'name' => env('APP_NAME', 'e-Commune'),

    'logo' => ['first' => 'e-', 'second' => 'COMMUNE'],

    // Mini logo affiché quand la sidebar est repliée.
    'mini' => 'eC',

    'tagline' => 'VIGNETTES · ÉTAT CIVIL · CAISSE',

    // Mairie de cette installation (page de connexion) : vide = la seule mairie active.
    'mairie_id' => env('MAIRIE_ID'),

    /*
    | Diaporama de la page de connexion (formulaire à droite, images qui défilent à gauche).
    | `file` : nom de l'image SANS extension, dans public/assets/images/login/ (jpg, jpeg, webp ou png, dans cet ordre).
    | `position` : cadrage de l'image (background-position CSS : « 30% 40% », « left center »…), car l'écran est en 16:9
    | et l'image ne l'est pas toujours. Une image absente n'est pas une erreur : dégradé bleu.
    | Images paysage (ratio ≥ 1,5), JPEG ≤ 400 Ko.
    */
    // Dossier des images du diaporama, relatif à public/ (surchargé par les tests).
    'login_images_directory' => 'assets/images/login',

    'login_slides' => [
        // Première diapositive : un stylo écrit la signification du « e » (électronique). `write` : titre écrit lettre à lettre.
        ['file' => 'slide-0', 'position' => 'center center', 'icon' => 'bx bx-pen', 'badge' => 'e-Commune', 'title' => '« e » comme électronique', 'text' => 'e-Commune, c\'est la commune numérique : la mairie et ses registres, sur ordinateur et sur téléphone.', 'write' => true],
        ['file' => 'slide-1', 'position' => '30% center', 'icon' => 'bx bx-id-card', 'badge' => 'Vignettes', 'title' => 'La vignette en une minute', 'text' => 'Le CH de la moto, l\'encaissement, la carte imprimée : le citoyen repart aussitôt.'],
        ['file' => 'slide-2', 'position' => '25% center', 'icon' => 'bx bx-book-content', 'badge' => 'État civil', 'title' => 'Les actes de naissance à portée de main', 'text' => 'Chaque acte est saisi une fois ; son extrait se réimprime en un clic.'],
        ['file' => 'slide-3', 'position' => 'center center', 'icon' => 'bx bx-wallet', 'badge' => 'Caisse', 'title' => 'Une caisse claire, chaque jour', 'text' => 'Chaque recette est numérotée, chaque versement enregistré : le maire suit tout.'],
    ],

];
