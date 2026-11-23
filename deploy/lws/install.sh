#!/usr/bin/env bash
# Installation / mise à jour d'e-Commune sur LWS (à lancer en SSH sur le serveur).
#   tar -xzf ~/e-commune_deploy.tar.gz -C ~/e-commune_release && bash ~/e-commune_release/lws/install.sh
# Variables : PHP_BIN (défaut : php), APP_DIR, PUBLIC_DIR.
set -euo pipefail

RELEASE="$(cd "$(dirname "$0")/.." && pwd)"
APP_DIR="${APP_DIR:-$HOME/e-commune_app}"
PUBLIC_DIR="${PUBLIC_DIR:-$HOME/public_html/e-commune}"
PHP_BIN="${PHP_BIN:-php}"
artisan() { (cd "$APP_DIR" && "$PHP_BIN" artisan "$@"); }

echo "→ Révision $(cat "$RELEASE/lws/REVISION" 2>/dev/null || echo '?')"

# 1. PHP >= 8.3 en ligne de commande (sinon : PHP_BIN=/opt/alt/php83/usr/bin/php bash install.sh)
"$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' || {
    echo "✘ $PHP_BIN est en PHP $("$PHP_BIN" -r 'echo PHP_VERSION;'). Il faut PHP >= 8.3 (PHP_BIN=...)."; exit 1; }
[ -d "$PUBLIC_DIR" ] || { echo "✘ $PUBLIC_DIR introuvable (créer le sous-domaine dans cPanel)."; exit 1; }

FIRST_INSTALL=0
[ -f "$APP_DIR/.env" ] || FIRST_INSTALL=1

# 2. Mise en maintenance pendant la mise à jour
if [ "$FIRST_INSTALL" = 0 ]; then
    artisan down --retry=30 || true
    trap 'echo "✘ Échec : le site reste en maintenance. Corriger puis relancer, ou : php artisan up"' ERR
fi

# 3. Code : remplacé, sauf .env et storage/ (fichiers téléversés, logs, sessions)
mkdir -p "$APP_DIR"
rsync -a --delete --exclude='/.env' --exclude='/storage/' --exclude='/public' "$RELEASE/e-commune_app/" "$APP_DIR/"
rsync -a --ignore-existing "$RELEASE/e-commune_app/storage/" "$APP_DIR/storage/"
chmod -R u+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# 4. Dossier public (on ne touche pas aux fichiers cPanel : php.ini, .user.ini, cgi-bin, .well-known)
rsync -a --exclude='/.htaccess' --exclude='/storage' "$RELEASE/public/" "$PUBLIC_DIR/"
[ -f "$PUBLIC_DIR/.htaccess.cpanel" ] || cp "$PUBLIC_DIR/.htaccess" "$PUBLIC_DIR/.htaccess.cpanel"
cat "$PUBLIC_DIR/.htaccess.cpanel" "$RELEASE/lws/htaccess.laravel" > "$PUBLIC_DIR/.htaccess"
ln -sfn "$APP_DIR/storage/app/public" "$PUBLIC_DIR/storage"
# public_path() correct aussi en ligne de commande (artisan)
[ -L "$APP_DIR/public" ] || { rm -rf "$APP_DIR/public"; ln -s "$PUBLIC_DIR" "$APP_DIR/public"; }

# 5. .env : créé depuis le modèle au premier passage, puis à compléter
if [ "$FIRST_INSTALL" = 1 ]; then
    cp "$RELEASE/lws/env.lws.example" "$APP_DIR/.env"
    chmod 600 "$APP_DIR/.env"
    artisan key:generate --force
    echo
    echo "⚠  Premier déploiement : compléter $APP_DIR/.env (APP_URL, DB_*, SUPERADMIN_*)"
    echo "   puis relancer ce script."
    exit 0
fi
grep -q '^DB_PASSWORD=.\+' "$APP_DIR/.env" || { echo "✘ DB_PASSWORD vide dans $APP_DIR/.env"; exit 1; }

# 6. Base de données, permissions, caches
artisan config:clear
artisan migrate --force
artisan optimize:clear
artisan permissions:sync
artisan optimize

artisan up
trap - ERR
echo "✔ e-Commune déployé ($(cat "$RELEASE/lws/REVISION" 2>/dev/null))."
