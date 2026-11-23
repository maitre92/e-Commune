# e-Commune

Gestion numérique d'une mairie : vignettes de motos, actes de naissance, actes de mariage et caisse.

- **Vignettes de motos** — comme au guichet réel : l'agent recopie le **N° Engin** de l'ancienne vignette ou des
  papiers de la moto, touche la **cylindrée** (présélectionnée si la moto est déjà connue), le citoyen paie,
  « Encaisser et imprimer ». Nom et numéro du détenteur (imprimés au verso) sont facultatifs. Les **tarifs par
  cylindrée** et la **pénalité de retard** (date limite, prix doublé, majoration fixe ou aucune) sont réglés par
  chaque mairie (bouton « Tarifs et pénalités »). **Un seul modèle de carte par année et par mairie**, choisi par
  le maire (bouton « Modèle de la vignette ») et figé dès la première vignette de l'année. Une vignette se corrige
  (N° Engin mal recopié) et se réimprime ; l'œil en montre l'aperçu.
- **Actes de naissance** et **actes de mariage** — l'officier signataire, le centre et l'en-tête sont **réglés une
  fois** par mairie (« Réglages de l'état civil »). On ne tape que l'enfant et ses parents, ou les époux et leurs
  parents : n°, année, dates, officier et localité se remplissent seuls ; « Enregistrer et imprimer » sort l'extrait
  en A4 (une page), en frappe de machine à écrire. Un acte déjà saisi se retrouve et son extrait s'imprime **en un
  clic** ; l'œil en montre l'aperçu sans rien délivrer.
- **Caisse** — l'agent qui délivre encaisse : chaque vignette vendue et chaque extrait payant (prix fixés par le
  maire) devient une recette numérotée (quittance) à son nom. Chaque agent ne voit que ses recettes ; le maire, le
  secrétaire général et le caissier voient toute la caisse, reçoivent le versement de chaque agent et impriment
  l'état journalier.

Rôles : **superadmin**, **administrateur national** (mairies, supervision), et dans chaque mairie : le **maire**
(crée et gère ses agents, règle les tarifs, le modèle de vignette et l'état civil, voit toute la caisse ; peut
déléguer certains droits à un agent), le **secrétaire général**, le **caissier / régisseur**, le **vendeur de
vignettes**, l'**agent d'état civil** et l'**agent polyvalent**. Un agent ne voit que les vignettes et les actes
qu'il a lui-même établis. Tout est journalisé.

## Plateforme Web (Laravel)

Prérequis : PHP ≥ 8.3, Composer, MariaDB/MySQL.

```sh
composer install
cp .env.example .env            # renseigner SUPERADMIN_* (jamais dans un dépôt)
php artisan key:generate
# créer les bases db_g_mairie et db_g_mairie_test (utf8mb4_unicode_ci)
php artisan migrate --seed
php artisan permissions:sync
php artisan storage:link
php artisan serve --host=0.0.0.0   # --host pour que les téléphones joignent l'API
php artisan test                   # base db_g_mairie_test
```

Connexion par numéro de téléphone. L'administrateur crée les mairies et leurs maires ; chaque maire crée ses
agents (Paramètres → Utilisateurs).

## Application mobile (`mobile/`, Expo)

Pour les agents de mairie : onglets **Accueil**, **Vignette**, **Naissance**, **Mariage**, **Caisse** et
**Profil** (photo, nom, mot de passe). Aperçu avant impression ; l'impression passe par l'imprimante du téléphone
(Wi-Fi, Bluetooth) ou un PDF.

```sh
cd mobile
npm install --legacy-peer-deps
cp .env.example .env    # EXPO_PUBLIC_API_URL=http://<IP de l'ordinateur>:8000/api/v1 (jamais « localhost »)
npx expo start          # scanner le QR code avec Expo Go
npm run typecheck
```

Publication sur le **Google Play Store** (build EAS, icônes, bannière, captures, textes de la fiche, sécurité des
données) : voir [mobile/store/README.md](mobile/store/README.md). Politique de confidentialité publique :
`/confidentialite`.

## À valider avant mise en production

- Modèles de carte de vignette et extraits d'actes : à faire valider par les autorités (aucun sceau ni signature
  n'est reproduit ; armoiries et monument de la carte sont des dessins par défaut, remplaçables par mairie).
- Grille de tarifs par cylindrée et règle de retard de chaque commune (grille de départ : 6 000 FCFA jusqu'à
  125 cm³, 12 000 FCFA au-delà ; prix doublé après le 31 mars).
- Police « Special Elite » (Apache 2.0, `public/assets/licenses/`) pour la frappe machine à écrire.
