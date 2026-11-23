# Publication d'e-Commune sur le Google Play Store

Tout ce qu'il faut pour la fiche Play Store est dans ce dossier :

| Élément exigé par Google | Fichier | Format |
|---|---|---|
| Icône de l'application | `play/icon-512.png` | 512 × 512 PNG |
| Bannière (« Image de présentation ») | `play/feature-graphic-1024x500.png` | 1024 × 500 PNG |
| Captures d'écran téléphone (2 à 8) | `play/screenshots/*.png` | 1080 × 2160 PNG |
| Textes de la fiche | ci-dessous | — |
| Politique de confidentialité | https://e-commune.kunnafoni-iufp.com/confidentialite | page Web |

Les icônes de l'application (lanceur, icône adaptative, icône monochrome Android 13+, écran de démarrage) sont dans
`../assets/images/`. Toutes les images viennent d'un seul dessin, `source/logo.js` ; pour les régénérer :

```sh
npm i -D playwright-core          # une fois
npm run store:images              # utilise /usr/bin/google-chrome (variable CHROME pour un autre chemin)
```

---

## 1. Construire l'application (EAS Build)

Une seule fois :

```sh
npm install -g eas-cli
eas login                 # compte Expo (gratuit) : https://expo.dev/signup
cd mobile
eas init                  # crée le projet Expo et ajoute son identifiant (projectId) dans app.json
```

Puis, à chaque version :

```sh
npm run build:play        # = eas build -p android --profile production → fichier .aab pour le Play Store
npm run build:apk         # = APK à installer directement sur un téléphone pour tester (profil preview)
```

- L'adresse de l'API (`EXPO_PUBLIC_API_URL=https://e-commune.kunnafoni-iufp.com/api/v1`) est fixée dans `eas.json`.
- **Clé de signature** : au premier build, répondre « Yes » à « Generate a new Android Keystore ». EAS la garde ;
  ne jamais la perdre (sans elle, impossible de publier une mise à jour). Sauvegarde : `eas credentials`.
- Le numéro de version interne (`versionCode`) augmente tout seul à chaque build (`autoIncrement`). Pour une version
  visible par les utilisateurs, changer `"version"` dans `app.json` (1.0.0 → 1.1.0).

## 2. Créer l'application dans la Play Console

1. Compte développeur Google Play : https://play.google.com/console (25 $ une fois, vérification d'identité).
2. **Créer une application** : nom `e-Commune`, langue par défaut **Français**, type **Application**, **Gratuite**.
3. **Important — comptes personnels récents** : Google impose un **test fermé avec au moins 12 testeurs pendant 14
   jours** avant d'ouvrir la production. Ajouter les agents de mairie (adresses Gmail) comme testeurs dès le début.
   (Un compte « Organisation » n'a pas cette obligation.)
4. Premier envoi : **Tests → Test interne → Créer une version**, téléverser le `.aab` téléchargé depuis expo.dev
   (le premier envoi doit être manuel ; ensuite `npm run submit:play` peut envoyer automatiquement, voir § 6).

## 3. Fiche du Play Store (Présence sur le Store → Fiche principale)

**Nom de l'application** (30 caractères max) :

```
e-Commune
```

**Description courte** (80 caractères max) :

```
Vignettes, actes de naissance et de mariage, caisse : la mairie sur téléphone.
```

**Description complète** (4000 caractères max) :

```
e-Commune est l'application des agents de mairie. Elle reprend, sur le téléphone, le travail du guichet : vendre
les vignettes de motos, établir les actes de naissance et de mariage, imprimer les extraits et tenir la caisse.

VIGNETTES DE MOTOS
• Recopier le N° Engin de l'ancienne vignette ou des papiers de la moto, choisir la cylindrée : le prix et la
  pénalité de retard sont calculés selon les tarifs de la mairie.
• « Encaisser et imprimer » : la carte de vignette s'imprime aussitôt, avec son QR Code de vérification.

ACTES DE NAISSANCE ET DE MARIAGE
• On ne tape que l'enfant et ses parents, ou les époux et leurs parents : numéro, année, dates, officier et
  localité se remplissent seuls.
• Un acte déjà saisi se retrouve en quelques lettres ; son extrait s'imprime en un clic.
• Le maire règle une fois l'état civil de la mairie (officier signataire, centre, en-tête, prix des extraits).

CAISSE
• Chaque vignette vendue et chaque extrait payant devient une recette numérotée, au nom de l'agent.
• L'agent voit ses recettes du jour ; le maire et le caissier voient toute la caisse.

IMPRESSION
• Aperçu avant impression, puis impression sur l'imprimante du téléphone (Wi-Fi, Bluetooth) ou en PDF.

SÉCURITÉ
• Connexion par numéro de téléphone et mot de passe ; comptes créés par la mairie.
• Chaque agent ne voit que ce que sa fonction lui permet ; chaque mairie ne voit que ses propres registres.
• Toutes les actions sont journalisées.

e-Commune est réservée aux agents des mairies équipées de la plateforme. Demandez votre compte à votre mairie.
```

**Éléments graphiques** : icône `play/icon-512.png`, image de présentation `play/feature-graphic-1024x500.png`,
captures `play/screenshots/` (dans l'ordre des numéros).

**Catégorie** : Productivité (ou Entreprise). **Tags** : administration, mairie.
**Coordonnées** : adresse e-mail de contact (obligatoire), site `https://e-commune.kunnafoni-iufp.com`.

## 4. Contenu de l'application (Règles → Contenu de l'application)

| Rubrique | Réponse |
|---|---|
| Politique de confidentialité | `https://e-commune.kunnafoni-iufp.com/confidentialite` (renseigner `APP_CONTACT_EMAIL` dans le `.env` du serveur pour y afficher un e-mail) |
| Accès à l'application | **Tout ou partie des fonctionnalités sont limitées** → donner un **compte de démonstration** (numéro + mot de passe) d'une mairie de test sur le serveur de production, avec la consigne : « Se connecter avec ce numéro et ce mot de passe ». Sans ce compte, Google refuse l'application. |
| Annonces | Non, l'application ne contient pas d'annonces |
| Classification du contenu | Questionnaire : catégorie « Utilitaire, productivité… », répondre Non partout → classée « Tout public / 3+ » |
| Public cible | 18 ans et plus |
| Application d'actualités | Non |
| Applications gouvernementales | Oui si la publication est faite au nom d'une mairie (Google peut demander une preuve) ; sinon Non |
| Fonctionnalités financières | Non (encaissement en espèces au guichet, aucun paiement dans l'application) |
| Santé | Non |

### Sécurité des données (Data safety)

- **Collecte ou partage de données** : Oui, collecte. **Partage avec des tiers** : Non.
- **Chiffrement en transit** : Oui (HTTPS).
- **Suppression des données** : sur demande à la mairie (les comptes sont créés et supprimés par la mairie).

| Type de données | Collectées | Finalité | Facultatif ? |
|---|---|---|---|
| Informations personnelles → Nom | Oui | Fonctionnalités de l'application, Gestion du compte | Obligatoire |
| Informations personnelles → Numéro de téléphone | Oui | Gestion du compte (identifiant de connexion) | Obligatoire |
| Informations personnelles → Autres infos (registres d'état civil saisis par l'agent) | Oui | Fonctionnalités de l'application | Obligatoire |
| Photos et vidéos → Photos | Oui | Personnalisation (photo de profil) | Facultatif |
| Informations financières → Autres (recettes de caisse) | Oui | Fonctionnalités de l'application | Obligatoire |
| Activité dans l'application → Autres actions | Oui | Sécurité, prévention des fraudes (journal) | Obligatoire |
| Position, contacts, audio, santé, identifiants publicitaires | **Non** | — | — |

## 5. Version de production

Après le test fermé (12 testeurs, 14 jours) : **Production → Créer une version** → choisir le même `.aab` (ou un
nouveau) → notes de version, par ex. :

```
Première version : vignettes de motos, actes de naissance et de mariage, réglages de l'état civil, caisse.
```

## 6. (Facultatif) Envoi automatique avec `eas submit`

Créer un compte de service Google Cloud avec accès à la Play Console (guide : https://docs.expo.dev/submit/android/),
enregistrer sa clé JSON sous `mobile/play-service-account.json` (ignoré par Git, ne jamais le commiter), puis :

```sh
npm run build:play && npm run submit:play     # envoie le dernier build sur la piste « internal » en brouillon
```
