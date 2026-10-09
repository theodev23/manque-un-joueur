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
- Notification par e-mail des participants acceptés lorsque l’organisateur annule une rencontre.
- API publique en lecture seule pour consulter les rencontres au format JSON.

L’application vérifie également les droits des utilisateurs et les places disponibles. Les inscriptions sont fermées lorsque la rencontre a commencé ou a été annulée.

## Technologies

- PHP 8.4 et Symfony 7.4
- Doctrine ORM et MariaDB 10.4
- Twig et CSS
- Symfony Mailer pour l’envoi des e-mails
- Mailpit pour consulter les e-mails en développement
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

## E-mails en développement

Mailpit permet de consulter les e-mails envoyés par l’application en local, sans les transmettre à de véritables boîtes mail.

Sur macOS avec Homebrew, installer et démarrer Mailpit :

```bash
brew install mailpit
brew services start mailpit
```

Ajouter cette ligne dans `.env.local` :

```dotenv
MAILER_DSN=smtp://127.0.0.1:1025
```

Les e-mails sont consultables à l’adresse :

```text
http://localhost:8025
```

Lorsqu’une rencontre est annulée, chaque participant accepté reçoit un e-mail individuel. Les joueurs dont la demande est en attente ou refusée ne sont pas notifiés. Une nouvelle tentative d’annulation d’une rencontre déjà annulée ne déclenche aucun nouvel envoi.

L’envoi est effectué après l’enregistrement de l’annulation. Si un envoi échoue à cause d’une erreur de transport, l’annulation reste enregistrée, l’erreur est journalisée et un message avertit l’organisateur. Il n’y a pas de nouvelle tentative automatique d’envoi.

La logique d’envoi se trouve dans `src/Service/NotificationRencontreService.php` et le contenu de l’e-mail dans `templates/emails/rencontre_annulee.html.twig`.

## Données de démonstration

Pour créer des comptes fictifs, des rencontres et des participations :

```bash
php bin/console doctrine:fixtures:load --env=dev
```

Attention : cette commande supprime les données existantes de la base de développement avant de charger les données fictives.

Les comptes `alex@example.com`, `sam@example.com` et `camille@example.com` utilisent le mot de passe `DemoFoot2026!`.

Les données sont définies dans `src/DataFixtures/AppFixtures.php` et peuvent être modifiées. Les dates des rencontres sont calculées à chaque chargement.

## API

L’application propose une API publique en lecture seule. Les réponses sont au format JSON.

| Méthode      | Route |             Description |
|-----|-------------------|-----------------------------------------|
| GET | `/api/rencontres` | Liste les rencontres à venir et non annulées. |
| GET | `/api/rencontres?ville=Montpellier` | Filtre cette liste par ville. |
| GET | `/api/rencontres/{id}` | Affiche le détail d’une rencontre. |

La route `/api/rencontres` affiche uniquement les rencontres à venir et non annulées. Le paramètre `ville` permet de filtrer cette liste.

La route `/api/rencontres/{id}` permet de consulter une rencontre précise grâce à son identifiant, même si elle est passée ou annulée. Par exemple, une rencontre annulée disparaît de la liste, mais son détail reste accessible avec `"annulee": true`. Si aucune rencontre ne correspond à l’identifiant demandé, l’API renvoie une erreur au format JSON avec le statut HTTP 404.

L’API renvoie les informations de la rencontre et le pseudo de son organisateur. Elle ne transmet ni l’adresse e-mail ni le mot de passe de l’organisateur, ni la liste des participations.

Pour une liste, les résultats sont regroupés dans un tableau nommé `rencontres`. Si aucune rencontre ne correspond à la recherche, ce tableau est vide : `{"rencontres":[]}`. Pour le détail, les informations de la rencontre sont regroupées dans un objet nommé `rencontre`.

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

Les dix tests fonctionnels comprennent 141 assertions. Ils couvrent notamment les droits d’accès, les places disponibles, l’annulation des participations et l’inscription des utilisateurs.

Ils vérifient également les notifications d’annulation et l’API : liste des rencontres, filtre par ville, informations publiques du détail et réponse 404 pour une rencontre inexistante.

Pendant les tests, le transport `null://null`, configuré dans `config/packages/mailer.yaml`, permet de vérifier les messages générés sans envoyer de véritables e-mails. Mailpit n’a donc pas besoin d’être démarré pour lancer les tests.

## Auteur

Théo Devarenne