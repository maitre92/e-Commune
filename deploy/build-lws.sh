#!/usr/bin/env bash
# Construit l'archive de déploiement LWS à partir du dernier commit (HEAD) : rien de non commité n'est envoyé.
#   ./deploy/build-lws.sh      → deploy/dist/e-commune_deploy.tar.gz
# Contenu de l'archive :
#   e-commune_app/   code Laravel complet (vendor --no-dev inclus), SANS .env
#   public/          contenu à servir depuis ~/public_html/e-commune (index.php adapté à LWS)
#   lws/             install.sh, env.lws.example, htaccess.laravel
set -euo pipefail

# Sur le serveur LWS : COMPOSER_CMD="/opt/alt/php83/usr/bin/php $HOME/composer.phar" si besoin.
COMPOSER_CMD="${COMPOSER_CMD:-composer}"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/deploy/dist"
BUILD="$(mktemp -d)"
trap 'rm -rf "$BUILD"' EXIT

cd "$ROOT"
if [ -n "$(git status --porcelain)" ]; then
    echo "⚠  Modifications non commitées : elles NE seront PAS dans l'archive (build depuis HEAD)."
fi

APP="$BUILD/e-commune_app"
mkdir -p "$APP"
git archive HEAD | tar -x -C "$APP"

# Hors production : application mobile, tests, docs, outillage.
rm -rf "$APP/mobile" "$APP/tests" "$APP/docs" "$APP/deploy" \
       "$APP/phpunit.xml" "$APP/.editorconfig" "$APP/.npmrc" "$APP/.gitattributes" "$APP/.gitignore"

# GitHub renvoie parfois des erreurs passagères (504) : jusqu'à 3 essais (le cache Composer garde ce qui est déjà téléchargé).
for try in 1 2 3; do
    (cd "$APP" && COMPOSER_PROCESS_TIMEOUT=600 $COMPOSER_CMD install --no-dev --prefer-dist --optimize-autoloader \
        --no-interaction --no-progress --no-scripts) && break
    [ "$try" = 3 ] && { echo "✘ composer install a échoué 3 fois : relancer plus tard."; exit 1; }
    echo "⟳ Nouvel essai de composer install dans 10 s ($try/3)"; sleep 10
done
(cd "$APP" && $COMPOSER_CMD dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction >/dev/null)
# Les caches de dev (packages découverts localement avec les dépendances dev) ne doivent pas partir.
rm -f "$APP"/bootstrap/cache/*.php

# public/ part à part ; l'index.php LWS pointe vers ~/e-commune_app.
mv "$APP/public" "$BUILD/public"
rm -f "$BUILD/public/storage"
cp "$ROOT/deploy/lws/index.php" "$BUILD/public/index.php"

mkdir -p "$BUILD/lws"
cp "$ROOT/deploy/lws/install.sh" "$ROOT/deploy/lws/env.lws.example" "$ROOT/deploy/lws/htaccess.laravel" "$BUILD/lws/"
git rev-parse --short HEAD > "$BUILD/lws/REVISION"

mkdir -p "$DIST"
tar -czf "$DIST/e-commune_deploy.tar.gz" -C "$BUILD" e-commune_app public lws
echo "✔ $DIST/e-commune_deploy.tar.gz  (commit $(cat "$BUILD/lws/REVISION"), $(du -h "$DIST/e-commune_deploy.tar.gz" | cut -f1))"
