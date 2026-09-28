#!/bin/sh
set -e

# Symfony 7.4 LTS (--webapp) installé automatiquement au premier démarrage
SYMFONY_VERSION="${SYMFONY_VERSION:-7.4.*}"

if [ "$1" = 'php-fpm' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then

    # Premier lancement : aucun projet Symfony présent → installation
    if [ ! -f composer.json ]; then
        echo "Installation de Symfony ${SYMFONY_VERSION} (webapp)..."
        rm -rf /tmp/symfony
        composer create-project "symfony/skeleton:${SYMFONY_VERSION}" /tmp/symfony \
            --prefer-dist --no-progress --no-interaction
        cd /tmp/symfony
        # Empêche Flex de générer ses propres fichiers Docker (PostgreSQL par défaut)
        composer config --json extra.symfony.docker false
        composer require webapp --no-progress --no-interaction
        cp -Rpn /tmp/symfony/. /var/www/app/
        cd /var/www/app
        rm -rf /tmp/symfony
    fi

    # Dépendances manquantes (clone frais du dépôt)
    if [ -z "$(ls -A vendor 2>/dev/null)" ]; then
        composer install --prefer-dist --no-progress --no-interaction
    fi

    # Migrations Doctrine en attente
    if [ -d migrations ] && [ "$(find migrations -iname '*.php' -print -quit)" ]; then
        php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
    fi
fi

exec docker-php-entrypoint "$@"
