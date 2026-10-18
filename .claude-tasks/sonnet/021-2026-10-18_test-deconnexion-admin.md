# 021 — Test fonctionnel de la déconnexion du back-office

- **Modèle** : Sonnet (exécuté par Opus, dans la continuité de la tâche 020)
- **Justification** : écriture de tests fonctionnels.

## Résumé

Premier test du projet (`WebTestCase`). Il couvre la correction de la tâche 020, pour les deux
façons de se connecter (la session est partagée entre les pare-feux `admin` et `main`) :

1. connexion par le formulaire `/admin/login` (jeton du pare-feu `admin`) ;
2. connexion par `POST /api/login` comme React (jeton du pare-feu `main`, cas du bug).

Pour chacune : les liens « Déconnexion » de `/admin` pointent vers `/admin/logout` (jamais
`/api/logout`), la déconnexion redirige vers `FRONT_URL`, puis `/admin` renvoie vers
`/admin/login` et `/api/me` répond 401.

`.env.test` fixe `FRONT_URL=http://localhost:5173` (un autre hôte) : le test vérifie ainsi que
la redirection n'est pas remplacée par « / » (protection de HttpUtils contournée par
`DeconnexionAdminListener`).

L'administrateur de test est créé dans `setUp()` puis supprimé dans `tearDown()`.

## Fichiers modifiés

- `tests/Controller/Admin/DeconnexionAdminTest.php` (nouveau)
- `.env.test`

## Vérification

- `php bin/phpunit` : OK (2 tests, 25 assertions).
- Contre-épreuves : avec le `DashboardController` d'avant la correction (90851df), le cas
  « connexion depuis le site » échoue (lien `/api/logout`) ; sans `DeconnexionAdminListener`,
  les deux cas échouent.

## Note environnement local

L'utilisateur MariaDB `app` ne peut pas créer `instruments_test`. Base créée une fois en root :
`CREATE DATABASE instruments_test; GRANT ALL ON instruments_test.* TO 'app'@'%';`
puis `APP_ENV=test php bin/console doctrine:migrations:migrate`.
