
dup
ddo
npm update
uphp
composer update

        make up          # construit les images et démarre les conteneurs
        make fixtures    # charge les données de démo (à faire la première fois ; vide la base)
        make down        # arrêter les conteneurs
        make help        # lister toutes les commandes
        make db-reset    # recréer la base et rejouer les migrations
        make test        # lancer PHPUnit
        make sh          # ouvrir un shell dans le conteneur PHP


Accès ensuite :
- Site React : http://localhost:5173
- API Symfony : http://localhost:8080 (documentation sur /api/docs)
- Administration : http://localhost:8080/admin, avec le compte admin@instruments.test / admin
- phpMyAdmin : http://localhost:8081
- Mailpit : http://localhost:8025


