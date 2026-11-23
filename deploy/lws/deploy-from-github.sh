#!/usr/bin/env bash
# Déploiement depuis GitHub, à lancer EN SSH sur LWS :
#   bash ~/e-commune_src/deploy/lws/deploy-from-github.sh
# Récupère la dernière version de main, construit l'archive (vendor --no-dev) puis lance install.sh.
# Variables : PHP_BIN (défaut : php), BRANCH (défaut : main).
set -euo pipefail

SRC="$(cd "$(dirname "$0")/../.." && pwd)"
BRANCH="${BRANCH:-main}"
export PHP_BIN="${PHP_BIN:-php}"

# Composer : celui du système, sinon ~/composer.phar (téléchargé au besoin), toujours lancé avec PHP_BIN.
if [ -z "${COMPOSER_CMD:-}" ]; then
    if command -v composer >/dev/null 2>&1; then
        COMPOSER_CMD="$PHP_BIN $(command -v composer)"
    else
        [ -f "$HOME/composer.phar" ] || {
            echo "→ Téléchargement de composer.phar"
            "$PHP_BIN" -r "copy('https://getcomposer.org/installer', '$HOME/composer-setup.php');"
            "$PHP_BIN" "$HOME/composer-setup.php" --install-dir="$HOME" --filename=composer.phar --quiet
            rm -f "$HOME/composer-setup.php"
        }
        COMPOSER_CMD="$PHP_BIN $HOME/composer.phar"
    fi
fi
export COMPOSER_CMD

echo "→ git pull ($BRANCH)"
git -C "$SRC" fetch --quiet origin "$BRANCH"
git -C "$SRC" checkout --quiet "$BRANCH"
git -C "$SRC" reset --hard --quiet "origin/$BRANCH"

bash "$SRC/deploy/build-lws.sh"

RELEASE="$HOME/e-commune_release"
rm -rf "$RELEASE" && mkdir -p "$RELEASE"
tar -xzf "$SRC/deploy/dist/e-commune_deploy.tar.gz" -C "$RELEASE"
bash "$RELEASE/lws/install.sh"
