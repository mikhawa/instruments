# Raccourcis utiles pour le projet

## Habituels :
        dup
        ddo
        npm update
        uphp
        composer update
### Nouveaux depuis l'installation de sudo apt install make

```bash
sudo apt install make 
```
        make up          # construit les images et démarre les conteneurs
        make fixtures    # charge les données de démo (à faire la première fois ; vide la base)
        make down        # arrêter les conteneurs
        make help        # lister toutes les commandes
        make db-reset    # recréer la base et rejouer les migrations
        make test        # lancer PHPUnit
        make lint        # vérifier le code (PHP-CS-Fixer, lints Symfony, oxlint)
        make lint-fix    # corriger automatiquement le style PHP
        make sh          # ouvrir un shell dans le conteneur PHP


Accès ensuite :
- Site React : http://localhost:5173
- API Symfony : http://localhost:8080/api (documentation sur /api/docs)
- Administration : http://localhost:8080/admin, avec le compte admin@instruments.test / admin
- phpMyAdmin : http://localhost:8081
- Mailpit : http://localhost:8025


