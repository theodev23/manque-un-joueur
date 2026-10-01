# Il nous manque un joueur

Cette application Symfony a pour objectif de faciliter la mise en relation de passionnés de football afin qu'ils puissent jouer des matchs ensemble.

Plus précisément, un utilisateur peut publier une rencontre en précisant le lieu, la date et le nombre de joueurs recherchés. Les autres utilisateurs peuvent demander à participer, puis l’organisateur accepte ou refuse les demandes.

## Fonctionnalités

- Inscription et connexion.
- Consultation des rencontres à venir et recherche par ville.
- Création, modification et annulation de ses rencontres.
- Demandes de participation et gestion des réponses par l’organisateur.
- Annulation de sa participation.
- Espace personnel pour retrouver ses rencontres et ses demandes.

L’application vérifie également les droits des utilisateurs et les places disponibles. Les inscriptions sont fermées lorsque la rencontre a commencé ou a été annulée.

## Technologies

- PHP 8.4 et Symfony 7.4
- Doctrine ORM et MariaDB 10.4
- Twig et CSS
- PHPUnit pour les tests fonctionnels

## Installation

Après avoir récupéré le projet, installer les dépendances :

```bash
composer install
```

Créer un fichier `.env.local` à la racine et y renseigner la connexion à la base de données. Adapter cet exemple à son environnement :

```dotenv
DATABASE_URL="mysql://utilisateur:mot_de_passe@127.0.0.1:3306/manque_un_joueur?serverVersion=10.4.28-MariaDB&charset=utf8mb4"
```

Le serveur MariaDB doit être démarré avant d’exécuter les commandes suivantes :

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

Pour lancer l’application avec la CLI Symfony :

```bash
symfony server:start
```

Ouvrir ensuite l’adresse indiquée dans le terminal. Créer un compte depuis la page d’inscription pour organiser une première rencontre.

## Données de démonstration

Pour créer des comptes fictifs, des rencontres et des participations :

```bash
php bin/console doctrine:fixtures:load --env=dev
```

Attention : cette commande supprime les données existantes de la base de développement avant de charger les données fictives.

Les comptes `alex@example.com`, `sam@example.com` et `camille@example.com` utilisent le mot de passe `DemoFoot2026!`.

Les données sont définies dans `src/DataFixtures/AppFixtures.php` et peuvent être modifiées. Les dates des rencontres sont calculées à chaque chargement.

## Tests

Les tests utilisent une base séparée : `manque_un_joueur_test`.

Si nécessaire, créer un fichier `.env.test.local` avec les paramètres de connexion adaptés. Conserver `manque_un_joueur` dans l’URL : la configuration Doctrine ajoute automatiquement le suffixe `_test`.

Préparer la base de test :

```bash
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test
```

Puis lancer les tests :

```bash
php bin/phpunit
```

Les huit tests fonctionnels couvrent notamment les droits d’accès, les places disponibles, l’annulation des participations et l’inscription des utilisateurs.

## Auteur

Théo Devarenne