# Déploiement e-Commune sur LWS (cPanel)

Même organisation que `siglab` :

```
/home/c2823388c/
├── e-commune_app/            code Laravel (hors web) + .env + storage/
│   └── public -> ~/public_html/e-commune
└── public_html/e-commune/    seul dossier servi (document root du sous-domaine)
    ├── index.php             charge ~/e-commune_app
    ├── .htaccess             = .htaccess.cpanel (PHP cPanel) + règles Laravel
    ├── .htaccess.cpanel      original cPanel, conservé
    ├── assets/  favicon.ico  robots.txt
    ├── storage -> ~/e-commune_app/storage/app/public
    └── php.ini  .user.ini  cgi-bin/  .well-known/   (cPanel, jamais touchés)
```

## Une seule fois, dans cPanel

1. **Version PHP** du sous-domaine `e-commune` : **8.3 ou plus** (MultiPHP Manager / « Sélectionner une version PHP »),
   extensions `pdo_mysql`, `mbstring`, `intl`, `fileinfo`, `gd`, `bcmath`.
2. **Base MySQL** : créer la base `c2823388c_e-commune`, l'utilisateur `c2823388c_e-commune` (mot de passe fort), lui
   donner **tous les privilèges** sur la base.
3. **SSL** : activer Let's Encrypt / AutoSSL sur le sous-domaine (le `.htaccess` force HTTPS).

## Déployer depuis GitHub (recommandé)

Tout se fait en SSH sur le serveur. Le dépôt `maitre92/e-Commune` est cloné dans `~/e-commune_src` (jamais servi).

**Une seule fois — clé de déploiement** (accès en lecture seule au dépôt privé) :

```sh
ssh-keygen -t ed25519 -f ~/.ssh/github_ecommune -N "" -C "lws-e-commune"
cat >> ~/.ssh/config <<'CFG'
Host github-ecommune
    HostName github.com
    User git
    IdentityFile ~/.ssh/github_ecommune
    IdentitiesOnly yes
CFG
chmod 600 ~/.ssh/config
cat ~/.ssh/github_ecommune.pub
```

Copier la clé affichée dans GitHub → dépôt `e-Commune` → Settings → Deploy keys → Add deploy key (sans « write
access »). Puis :

```sh
ssh -T git@github-ecommune            # « Hi maitre92/e-Commune! You've successfully authenticated »
git clone git@github-ecommune:maitre92/e-Commune.git ~/e-commune_src
```

**Chaque déploiement** (premier et mises à jour) :

```sh
bash ~/e-commune_src/deploy/lws/deploy-from-github.sh
# si `php -v` < 8.3 : PHP_BIN=/opt/alt/php83/usr/bin/php bash ~/e-commune_src/deploy/lws/deploy-from-github.sh
```

Il récupère `origin/main`, installe les dépendances (Composer du serveur, sinon `~/composer.phar` téléchargé une
fois), puis lance `install.sh` (voir « Premier passage » ci-dessous).

## Déployer par archive (sans GitHub)

Sur le poste, depuis le dépôt, une fois les changements **commités** (l'archive est construite depuis `HEAD`) :

```sh
./deploy/build-lws.sh                                   # → deploy/dist/e-commune_deploy.tar.gz
scp deploy/dist/e-commune_deploy.tar.gz c2823388c@<serveur-lws>:~/
```

(sans SSH : téléverser l'archive dans `/home/c2823388c` avec le Gestionnaire de fichiers cPanel.)

Sur le serveur (SSH) :

```sh
rm -rf ~/e-commune_release && mkdir ~/e-commune_release
tar -xzf ~/e-commune_deploy.tar.gz -C ~/e-commune_release
bash ~/e-commune_release/lws/install.sh
```

Si `php -v` en SSH est inférieur à 8.3 : `PHP_BIN=/opt/alt/php83/usr/bin/php bash ~/e-commune_release/lws/install.sh`
(adapter le chemin : `ls /opt/alt/ /opt/cpanel/ | grep php`).

### Premier passage

Le script copie le code, prépare `public_html/e-commune`, crée `~/e-commune_app/.env` depuis
[`lws/env.lws.example`](lws/env.lws.example), génère `APP_KEY`, puis **s'arrête**. Compléter le `.env` :

```sh
nano ~/e-commune_app/.env
# APP_URL=https://e-commune.<domaine>
# DB_DATABASE / DB_USERNAME / DB_PASSWORD
# SUPERADMIN_PASSWORD (fort, temporaire) + SUPERADMIN_1_NAME / SUPERADMIN_1_PHONE
```

puis relancer `bash ~/e-commune_release/lws/install.sh`. Il lance alors les migrations, la synchronisation des
permissions et met les caches (config, routes, vues). Les superadmins sont créés automatiquement après la migration ;
changement de mot de passe forcé à la première connexion.

### Mises à jour

Même commande. Le script met le site en maintenance, remplace le code **sans toucher** au `.env` ni à `storage/`
(photos, logos, journaux, sessions), migre, recache et remet le site en ligne. En cas d'erreur, le site reste en
maintenance : corriger puis relancer, ou `cd ~/e-commune_app && php artisan up`.

## Vérifier

- `https://e-commune.<domaine>/up` → page « Application up »
- page de connexion, connexion superadmin, téléversement d'un logo (vérifie le lien `storage`)
- journaux : `~/e-commune_app/storage/logs/laravel-*.log`

## Application mobile

Dans `mobile/.env` : `EXPO_PUBLIC_API_URL=https://e-commune.<domaine>/api/v1`, puis reconstruire l'application.
