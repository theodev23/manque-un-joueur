<?php

namespace App\Tests\Controller;

use App\Entity\Participation;
use App\Entity\Rencontre;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RencontreControllerTest extends WebTestCase
{
    // Le test ci-dessous vérifie que les visiteurs non connectés sont redirigés vers la page de connexion lorsqu'ils tentent d'accéder à la page de création d'une rencontre.
    public function testUnVisiteurDoitSeConnecterPourOrganiserUneRencontre(): void
    {
        // Crée un client HTTP pour simuler un navigateur.
        $client = static::createClient();

        // Tente d'accéder à la page de création d'une rencontre sans être connecté.
        $client->request('GET', '/rencontre/nouvelle');

        // Vérifie que la réponse est une redirection vers la page de connexion.
        self::assertResponseRedirects('/login');

        // La redirection doit être suivie pour vérifier que la page de connexion s'affiche correctement.
        $client->followRedirect();

        // Vérifie que la page de connexion s'affiche correctement et que les champs de formulaire pour le nom d'utilisateur et le mot de passe sont présents.
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="_username"]');
        self::assertSelectorExists('input[name="_password"]');
    }

    // Le test ci-dessous vérifie qu'un utilisateur ne puisse pas modifier la rencontre organisée par un autre utilisateur.
    public function testUnUtilisateurNePeutPasModifierLaRencontreDUnAutre(): void
    {
        // Crée un client HTTP pour simuler un navigateur.
        $client = static::createClient();

        // Vérifie que le test s'exécute bien sur la base de données de test.
        $this->verifierBaseDeTest();

        // Crée deux utilisateurs : un organisateur et un autre joueur.
        $organisateur = $this->creerUtilisateur('Organisateur test');
        $autreUtilisateur = $this->creerUtilisateur('Joueur test');

        // Crée une rencontre organisée par l'organisateur.
        $rencontre = $this->creerRencontre(
            $organisateur,
            'Rencontre pour le test des autorisations'
        );

        // Enregistre les entités créées en base de données.
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();

        // Récupère les identifiants des entités pour les utiliser dans le test et pour le nettoyage des données après le test.
        $rencontreId = $rencontre->getId();
        $organisateurId = $organisateur->getId();
        $autreUtilisateurId = $autreUtilisateur->getId();

        try {
            // L’autre utilisateur tente d’accéder au formulaire de modification de la rencontre organisée par l’organisateur.
            $client->loginUser($autreUtilisateur, 'main');
            $client->request('GET', '/rencontre/' . $rencontreId . '/modifier');

            // Vérifie que la réponse est un code 403 Forbidden, ce qui signifie que l’accès est refusé.
            self::assertResponseStatusCodeSame(403);

            // L’organisateur peut accéder au formulaire de modification de sa propre rencontre.
            $client->loginUser($organisateur, 'main');
            $client->request('GET', '/rencontre/' . $rencontreId . '/modifier');

            // Vérifie que la réponse est réussie et que le formulaire de modification de la rencontre est présent.
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('form[name="rencontre"]');
        } finally {
            // Nettoie les données créées pour le test afin de ne pas laisser de traces dans la base de données.
            $this->nettoyerDonnees(
                $rencontreId,
                [$organisateurId, $autreUtilisateurId]
            );
        }
    }

    // Le test ci-dessous vérifie que lorsqu'une rencontre est annulée pendant qu'un joueur a ouvert le formulaire de participation, la soumission de ce formulaire après l'annulation ne crée pas de participation.
    public function testUneRencontreAnnuleeRefuseUneNouvelleParticipation(): void
    {
        // Crée un client HTTP pour simuler un navigateur.
        $client = static::createClient();

        // Vérifie que le test s'exécute bien sur la base de données de test.
        $this->verifierBaseDeTest();

        // Crée un organisateur et un joueur pour le test.
        $organisateur = $this->creerUtilisateur('Organisateur test');
        $joueur = $this->creerUtilisateur('Joueur test');

        // Crée une rencontre organisée par l'organisateur.
        $rencontre = $this->creerRencontre(
            $organisateur,
            'Rencontre annulée pendant une inscription'
        );

        // Enregistre les entités créées en base de données.
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();

        // Récupère les identifiants des entités pour les utiliser dans le test et pour le nettoyage des données après le test.
        $rencontreId = $rencontre->getId();
        $organisateurId = $organisateur->getId();
        $joueurId = $joueur->getId();

        try {
            // Le joueur ouvre la page avant l’annulation.
            $client->loginUser($joueur, 'main');
            $crawler = $client->request('GET', '/rencontre/' . $rencontreId);

            // Vérifie que la page s’affiche correctement.
            self::assertResponseIsSuccessful();

            // Récupère le formulaire de participation avant l’annulation.
            $formulaire = $crawler
                ->selectButton('Demander à participer')
                ->form();

            // L’organisateur annule la rencontre.
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $rencontreEnBase = $entityManager->find(Rencontre::class, $rencontreId);

            // Vérifie que la rencontre existe en base de données avant de l’annuler.
            self::assertNotNull($rencontreEnBase);

            // Annule la rencontre et enregistre le changement en base de données.
            $rencontreEnBase->annuler();
            $entityManager->flush();
            $entityManager->clear();

            // Le joueur soumet le formulaire obtenu avant l’annulation.
            $client->submit($formulaire);

            // Vérifie que la réponse est une redirection vers la page de la rencontre, ce qui signifie que la participation n’a pas été enregistrée.
            self::assertResponseRedirects('/rencontre/' . $rencontreId);

            // Suivre la redirection pour vérifier que la page de la rencontre s’affiche correctement et que le message d’annulation est présent.
            $client->followRedirect();

            // Vérifie que la page s’affiche correctement et que le message d’erreur indiquant que la rencontre est annulée est présent.
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains(
                '.alert-danger',
                'Cette rencontre est annulée.'
            );

            // Vérifie qu’aucune participation n’a été enregistrée pour cette rencontre.
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();

            // Vérifie que le nombre de participations pour cette rencontre est bien de 0, ce qui confirme qu’aucune participation n’a été créée après l’annulation.
            self::assertSame(
                0,
                $entityManager->getRepository(Participation::class)->count([
                    'rencontre' => $rencontreId,
                ])
            );
        } finally {
            // Nettoie les données créées pour le test afin de ne pas laisser de traces dans la base de données.
            $this->nettoyerDonnees(
                $rencontreId,
                [$organisateurId, $joueurId]
            );
        }
    }

    // Le test ci-dessous vérifie que lorsqu'une rencontre n'a qu'une seule place disponible, une seule demande de participation peut être acceptée.
    public function testUneSeuleDemandePeutEtreAccepteePourLaDernierePlace(): void
    {
        // Crée un client HTTP pour simuler un navigateur.
        $client = static::createClient();

        // Vérifie que le test s'exécute bien sur la base de données de test.
        $this->verifierBaseDeTest();

        // Crée un organisateur et deux joueurs pour le test.
        $utilisateurs = [
            'organisateur' => $this->creerUtilisateur('Organisateur test'),
            'joueur1' => $this->creerUtilisateur('Joueur 1'),
            'joueur2' => $this->creerUtilisateur('Joueur 2'),
        ];

        // Crée une rencontre avec une seule place disponible.
        $rencontre = $this->creerRencontre(
            $utilisateurs['organisateur'],
            'Deux demandes pour une seule place',
            1
        );

        // Crée deux demandes de participation pour la rencontre.
        $premiereDemande = $this->creerParticipation(
            $utilisateurs['joueur1'],
            $rencontre
        );

        $secondeDemande = $this->creerParticipation(
            $utilisateurs['joueur2'],
            $rencontre
        );

        // Enregistre les entités créées en base de données.
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();

        // Récupère les identifiants des entités pour les utiliser dans le test et pour le nettoyage des données après le test.
        $rencontreId = $rencontre->getId();
        $premiereDemandeId = $premiereDemande->getId();
        $secondeDemandeId = $secondeDemande->getId();

        // Récupère les identifiants des utilisateurs pour le nettoyage des données après le test.
        $utilisateurIds = array_map(
            static fn (User $utilisateur): int => $utilisateur->getId(),
            $utilisateurs
        );

        try {
            // L’organisateur se connecte et tente d’accepter les deux demandes de participation.
            $client->loginUser($utilisateurs['organisateur'], 'main');
            $crawler = $client->request('GET', '/rencontre/' . $rencontreId);

            // Vérifie que la page s’affiche correctement.
            self::assertResponseIsSuccessful();

            // Récupère les formulaires d’acceptation pour les deux demandes de participation.
            $premierFormulaire = $crawler
                ->filter('form[action="/participation/' . $premiereDemandeId . '/accepter"]')
                ->selectButton('Accepter')
                ->form();

            $secondFormulaire = $crawler
                ->filter('form[action="/participation/' . $secondeDemandeId . '/accepter"]')
                ->selectButton('Accepter')
                ->form();

            // L’organisateur soumet le formulaire pour accepter la première demande.
            $client->submit($premierFormulaire);

            // Vérifie que la réponse est une redirection vers la page de la rencontre
            self::assertResponseRedirects('/rencontre/' . $rencontreId);

            // Suivre la redirection pour vérifier que la page de la rencontre s’affiche correctement et que le message de succès est présent.
            $client->followRedirect();

            // Vérifie que la page s’affiche correctement et que le message de succès indiquant que la demande a été acceptée est présent.
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains(
                '.alert-success',
                'La demande a été acceptée.'
            );

            // L’organisateur soumet le formulaire pour accepter la deuxième demande.
            $client->submit($secondFormulaire);

            // Vérifie que la réponse est une redirection vers la page de la rencontre.
            self::assertResponseRedirects('/rencontre/' . $rencontreId);

            // Suivre la redirection pour vérifier que la page de la rencontre s’affiche correctement et que le message d’erreur est présent.
            $client->followRedirect();

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains(
                '.alert-danger',
                'Cette rencontre est complète.'
            );

            // Vérifie que la première demande a été acceptée et que la deuxième demande est toujours en attente, et que la rencontre n’a plus de places disponibles.
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();

            $premiereDemande = $entityManager->find(
                Participation::class,
                $premiereDemandeId
            );
            $secondeDemande = $entityManager->find(
                Participation::class,
                $secondeDemandeId
            );

            self::assertNotNull($premiereDemande);
            self::assertNotNull($secondeDemande);

            self::assertSame(
                Participation::STATUT_ACCEPTEE,
                $premiereDemande->getStatut()
            );
            self::assertSame(
                Participation::STATUT_EN_ATTENTE,
                $secondeDemande->getStatut()
            );

            // Vérifie qu’il n’y a qu’une seule participation acceptée pour cette rencontre.
            self::assertSame(
                1,
                $entityManager->getRepository(Participation::class)->count([
                    'rencontre' => $rencontreId,
                    'statut' => Participation::STATUT_ACCEPTEE,
                ])
            );

            // Vérifie que la rencontre n’a plus de places disponibles.
            $rencontreEnBase = $entityManager->find(Rencontre::class, $rencontreId);

            // Vérifie que la rencontre existe en base de données.
            self::assertNotNull($rencontreEnBase);
            self::assertSame(0, $rencontreEnBase->getPlacesRestantes());
        } finally {
            // Nettoie les données créées pour le test afin de ne pas laisser de traces dans la base de données.
            $this->nettoyerDonnees($rencontreId, $utilisateurIds);
        }
    }

    // Le test ci-dessous vérifie que lorsqu’un joueur annule sa participation acceptée, une place est libérée et l’organisateur peut accepter une autre demande en attente.
    public function testAnnulerUneParticipationAccepteeLibereUnePlace(): void
    {
        // Crée un client HTTP pour simuler un navigateur.
        $client = static::createClient();

        // Vérifie que le test s'exécute bien sur la base de données de test.
        $this->verifierBaseDeTest();

        // Crée un organisateur et deux joueurs pour le test.
        $utilisateurs = [
            'organisateur' => $this->creerUtilisateur('Organisateur test'),
            'joueur1' => $this->creerUtilisateur('Joueur 1'),
            'joueur2' => $this->creerUtilisateur('Joueur 2'),
        ];

        // Crée une rencontre avec une seule place disponible.
        $rencontre = $this->creerRencontre(
            $utilisateurs['organisateur'],
            'Une place libérée après une annulation',
            1
        );

        // Le premier joueur a déjà une participation acceptée.
        $participationAcceptee = $this->creerParticipation(
            $utilisateurs['joueur1'],
            $rencontre,
            Participation::STATUT_ACCEPTEE
        );

        // Le deuxième joueur a une demande de participation en attente.
        $demandeEnAttente = $this->creerParticipation(
            $utilisateurs['joueur2'],
            $rencontre
        );

        // Enregistre les entités créées en base de données.    
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();

        // Récupère les identifiants des entités pour les utiliser dans le test et pour le nettoyage des données après le test.
        $rencontreId = $rencontre->getId();
        $participationAccepteeId = $participationAcceptee->getId();
        $demandeEnAttenteId = $demandeEnAttente->getId();
        $organisateurId = $utilisateurs['organisateur']->getId();

        // Récupère les identifiants des utilisateurs pour le nettoyage des données après le test.
        $utilisateurIds = array_map(
            static fn (User $utilisateur): int => $utilisateur->getId(),
            $utilisateurs
        );

        try {
            // Le premier joueur annule sa participation acceptée.
            $client->loginUser($utilisateurs['joueur1'], 'main');
            $client->request('GET', '/rencontre/' . $rencontreId);

            // Vérifie que la page s’affiche correctement.
            self::assertResponseIsSuccessful();

            // Soumet le formulaire d’annulation de la participation.
            $client->submitForm('Annuler ma participation');

            // Vérifie que la réponse est une redirection vers la page de la rencontre.
            self::assertResponseRedirects('/rencontre/' . $rencontreId);

            $client->followRedirect();

            // Vérifie que la page s’affiche correctement et que le message de succès indiquant que la participation a été annulée est présent.
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains(
                '.alert-success',
                'Votre participation a bien été annulée.'
            );

            // Vérifie que la participation du premier joueur a été supprimée et que la rencontre a une place disponible.
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();

            // Vérifie que la participation du premier joueur n’existe plus en base de données.
            self::assertNull(
                $entityManager->find(Participation::class, $participationAccepteeId)
            );

            // Vérifie que la rencontre a une place disponible après l’annulation.
            $rencontreEnBase = $entityManager->find(Rencontre::class, $rencontreId);

            // Vérifie que la rencontre existe en base de données.
            self::assertNotNull($rencontreEnBase);
            self::assertSame(1, $rencontreEnBase->getPlacesRestantes());

            // L’organisateur accepte la demande de participation en attente du deuxième joueur.
            $organisateur = $entityManager->find(User::class, $organisateurId);

            // Vérifie que l’organisateur existe en base de données.
            self::assertNotNull($organisateur);

            // L’organisateur se connecte et accède à la page de la rencontre.
            $client->loginUser($organisateur, 'main');
            $crawler = $client->request('GET', '/rencontre/' . $rencontreId);

            // Vérifie que la page s’affiche correctement.
            self::assertResponseIsSuccessful();

            // Récupère le formulaire d’acceptation de la demande de participation en attente du deuxième joueur.
            $formulaire = $crawler
                ->filter('form[action="/participation/' . $demandeEnAttenteId . '/accepter"]')
                ->selectButton('Accepter')
                ->form();

            // Soumet le formulaire pour accepter la demande de participation en attente.
            $client->submit($formulaire);

            // Vérifie que la réponse est une redirection vers la page de la rencontre.
            self::assertResponseRedirects('/rencontre/' . $rencontreId);

            // Suivre la redirection pour vérifier que la page de la rencontre s’affiche correctement et que le message de succès est présent.
            $client->followRedirect();

            // Vérifie que la page s’affiche correctement et que le message de succès indiquant que la demande a été acceptée est présent.
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains(
                '.alert-success',
                'La demande a été acceptée.'
            );

            // Vérifie que la demande de participation en attente du deuxième joueur a été acceptée et que la rencontre n’a plus de places disponibles.
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();

            
            $demandeEnBase = $entityManager->find(
                Participation::class,
                $demandeEnAttenteId
            );

            self::assertNotNull($demandeEnBase);
            self::assertSame(
                Participation::STATUT_ACCEPTEE,
                $demandeEnBase->getStatut()
            );

            $rencontreEnBase = $entityManager->find(Rencontre::class, $rencontreId);

            self::assertNotNull($rencontreEnBase);
            self::assertSame(0, $rencontreEnBase->getPlacesRestantes());

            // Vérifie qu’il n’y a qu’une seule participation acceptée pour cette rencontre.
            self::assertSame(
                1,
                $entityManager->getRepository(Participation::class)->count([
                    'rencontre' => $rencontreId,
                    'statut' => Participation::STATUT_ACCEPTEE,
                ])
            );
        } finally {
            // Nettoie les données créées pour le test afin de ne pas laisser de traces dans la base de données.
            $this->nettoyerDonnees($rencontreId, $utilisateurIds);
        }
    }


    // Le test ci-dessous vérifie qu’un joueur ne peut pas annuler la participation d’un autre joueur.
    public function testUnJoueurNePeutPasAnnulerLaParticipationDUnAutre(): void
    {
        // Crée un client HTTP pour simuler un navigateur.
        $client = static::createClient();

        // Vérifie que le test s'exécute bien sur la base de données de test.
        $this->verifierBaseDeTest();

        // Crée un organisateur, un joueur propriétaire de la participation et un autre joueur pour le test.
        $organisateur = $this->creerUtilisateur('Organisateur test');
        $proprietaire = $this->creerUtilisateur('Joueur inscrit');
        $autreJoueur = $this->creerUtilisateur('Autre joueur');

        // Crée une rencontre organisée par l’organisateur.
        $rencontre = $this->creerRencontre(
            $organisateur,
            'Protection de l’annulation des participations'
        );

        // Crée une participation acceptée pour le joueur propriétaire.
        $participation = $this->creerParticipation(
            $proprietaire,
            $rencontre,
            Participation::STATUT_ACCEPTEE
        );

        // Enregistre les entités créées en base de données.
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();

        // Récupère les identifiants des entités pour les utiliser dans le test et pour le nettoyage des données après le test.
        $rencontreId = $rencontre->getId();
        $participationId = $participation->getId();
        $proprietaireId = $proprietaire->getId();

        // Récupère les identifiants des utilisateurs pour le nettoyage des données après le test.
        $utilisateurIds = [
            $organisateur->getId(),
            $proprietaireId,
            $autreJoueur->getId(),
        ];

        try {
            // Le joueur propriétaire de la participation se connecte et accède à la page de la rencontre.
            $client->loginUser($proprietaire, 'main');
            $crawler = $client->request('GET', '/rencontre/' . $rencontreId);

            // Vérifie que la page s’affiche correctement.
            self::assertResponseIsSuccessful();

            // Récupère le formulaire d’annulation de la participation.
            $formulaire = $crawler
                ->selectButton('Annuler ma participation')
                ->form();

            // L’autre joueur tente de soumettre le formulaire d’annulation de la participation du joueur propriétaire.
            $client->loginUser($autreJoueur, 'main');
            $client->submit($formulaire);

            // Vérifie que la réponse est un code 403 Forbidden, ce qui signifie que l’accès est refusé.
            self::assertResponseStatusCodeSame(403);

            // Vérifie que la participation du joueur propriétaire est toujours présente et que son statut est toujours accepté.
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();

            $participationEnBase = $entityManager->find(
                Participation::class,
                $participationId
            );

            // Vérifie que la participation existe en base de données.
            self::assertNotNull($participationEnBase);
            self::assertSame(
                Participation::STATUT_ACCEPTEE,
                $participationEnBase->getStatut()
            );
            self::assertSame(
                $proprietaireId,
                $participationEnBase->getUtilisateur()->getId()
            );

            // Le joueur propriétaire de la participation se connecte à nouveau et soumet le formulaire d’annulation de sa participation.
            $proprietaire = $entityManager->find(User::class, $proprietaireId);

            // Vérifie que le joueur propriétaire existe en base de données.
            self::assertNotNull($proprietaire);

            // Le joueur propriétaire soumet le formulaire d’annulation de sa participation.
            $client->loginUser($proprietaire, 'main');
            $client->submit($formulaire);

            // Vérifie que la réponse est une redirection vers la page de la rencontre.
            self::assertResponseRedirects('/rencontre/' . $rencontreId);

            $client->followRedirect();

            // Vérifie que la page s’affiche correctement et que le message de succès indiquant que la participation a été annulée est présent.
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains(
                '.alert-success',
                'Votre participation a bien été annulée.'
            );

            // Vérifie que la participation du joueur propriétaire a été supprimée.
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();

            self::assertNull(
                $entityManager->find(Participation::class, $participationId)
            );
        } finally {
            // Nettoie les données créées pour le test afin de ne pas laisser de traces dans la base de données.
            $this->nettoyerDonnees($rencontreId, $utilisateurIds);
        }
    }


    public function testAnnulationNotifieUniquementLesParticipantsAcceptesSansDoublon(): void
    {
        // Crée un client HTTP pour simuler un navigateur.
        $client = static::createClient();
        $this->verifierBaseDeTest();

        // Crée un organisateur et trois joueurs avec différents statuts de participation.
        $utilisateurs = [
            'organisateur' => $this->creerUtilisateur('Organisateur test'),
            'accepte' => $this->creerUtilisateur('Joueur accepté'),
            'enAttente' => $this->creerUtilisateur('Joueur en attente'),
            'refuse' => $this->creerUtilisateur('Joueur refusé'),
        ];

        // Crée une rencontre organisée par l’organisateur.
        $rencontre = $this->creerRencontre(
            $utilisateurs['organisateur'],
            'Rencontre pour le test des e-mails'
        );

        // Crée des participations avec différents statuts pour les joueurs.
        $this->creerParticipation(
            $utilisateurs['accepte'],
            $rencontre,
            Participation::STATUT_ACCEPTEE
        );

        $this->creerParticipation(
            $utilisateurs['enAttente'],
            $rencontre,
            Participation::STATUT_EN_ATTENTE
        );

        $this->creerParticipation(
            $utilisateurs['refuse'],
            $rencontre,
            Participation::STATUT_REFUSEE
        );

        // Enregistre les entités créées en base de données.
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();

        // Récupère les identifiants des entités pour les utiliser dans le test et pour le nettoyage des données après le test.
        $rencontreId = $rencontre->getId();
        $emailJoueurAccepte = $utilisateurs['accepte']->getEmail();

        $utilisateurIds = [];
        foreach ($utilisateurs as $cle => $utilisateur) {
            $utilisateurIds[$cle] = $utilisateur->getId();
        }

        try {
            // L’organisateur se connecte et annule la rencontre.
            $client->loginUser($utilisateurs['organisateur'], 'main');

            $crawler = $client->request(
                'GET',
                '/rencontre/' . $rencontreId . '/annuler'
            );

            // Vérifie que la page s’affiche correctement.
            self::assertResponseIsSuccessful();
            // Vérifie qu’aucun e-mail n’a été envoyé avant l’annulation.
            self::assertEmailCount(0);

            // Récupère le formulaire d’annulation de la rencontre.
            $formulaire = $crawler
                ->filter('form[action="/rencontre/' . $rencontreId . '/annuler"]')
                ->form();

            // Première soumission : la rencontre est annulée.
            $client->submit($formulaire);

            // Vérifie que la réponse est une redirection vers la page de la rencontre.
            self::assertResponseRedirects('/rencontre/' . $rencontreId);

            // Vérifie qu’un seul e-mail a été envoyé à la suite de l’annulation.
            self::assertEmailCount(1);

            // Récupère le message e-mail envoyé.
            $email = self::getMailerMessage();

            // Vérifie que le message est bien une instance de l’e-mail Symfony.
            self::assertInstanceOf(\Symfony\Component\Mime\Email::class, $email);

            // Vérifie que l’e-mail a été envoyé uniquement au joueur accepté et qu’il n’y a pas de doublons.
            self::assertCount(1, $email->getTo());
            self::assertSame(
                $emailJoueurAccepte,
                $email->getTo()[0]->getAddress()
            );
            
            // Vérifie qu’il n’y a pas de destinataires en copie (Cc) ou en copie cachée (Bcc).
            self::assertCount(0, $email->getCc());
            self::assertCount(0, $email->getBcc());

            // Vérifie le sujet et le corps de l’e-mail.
            self::assertEmailSubjectContains(
                $email,
                'Annulation de votre rencontre'
            );
            self::assertEmailHtmlBodyContains(
                $email,
                'Rencontre pour le test des e-mails'
            );

            
            // Vérifie que la rencontre est bien marquée comme annulée en base de données.
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();

            // Récupère la rencontre en base de données.
            $rencontreEnBase = $entityManager->find(Rencontre::class, $rencontreId);

            // Vérifie que la rencontre existe en base de données.
            self::assertNotNull($rencontreEnBase);
            // Vérifie que la rencontre est bien annulée.
            self::assertTrue($rencontreEnBase->isAnnulee());

            $client->followRedirect();

            // Vérifie que la page s’affiche correctement et que le message de succès indiquant que la rencontre a été annulée est présent.
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains(
                '.alert-success',
                'La rencontre a bien été annulée.'
            );

            // Rejoue le même formulaire pour simuler une deuxième tentative.
            $client->submit($formulaire);

            self::assertResponseRedirects('/rencontre/' . $rencontreId);

            // Cette deuxième requête ne doit produire aucun nouvel e-mail.
            self::assertEmailCount(0);

            $client->followRedirect();

            // Vérifie que la page s’affiche correctement et que le message d’erreur indiquant que la rencontre est déjà annulée est présent.
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains(
                '.alert-danger',
                'Cette rencontre est déjà annulée.'
            );
        } finally {
            $this->nettoyerDonnees($rencontreId, $utilisateurIds);
        }
    }


    // Le test ci-dessous vérifie que l’API REST pour les rencontres fonctionne correctement, en particulier la liste des rencontres et le détail d’une rencontre.
    public function testApiRencontres(): void
    {
        // Crée un client HTTP pour simuler un navigateur.
        $client = static::createClient();
        $this->verifierBaseDeTest();

        // Crée un organisateur pour les rencontres.
        $organisateur = $this->creerUtilisateur('Organisateur API');

        // Des villes uniques évitent les interférences avec d’autres données.
        $suffixe = bin2hex(random_bytes(6));
        $ville = 'Ville API ' . $suffixe;
        $autreVille = 'Autre ville API ' . $suffixe;

        // Crée plusieurs rencontres avec différents statuts et villes.
        $rencontreAVenir = $this->creerRencontre(
            $organisateur,
            'Rencontre API à venir'
        );
        $rencontreAVenir->setVille($ville);

        $rencontreAutreVille = $this->creerRencontre(
            $organisateur,
            'Rencontre API dans une autre ville'
        );
        $rencontreAutreVille->setVille($autreVille);

        $rencontrePassee = $this->creerRencontre(
            $organisateur,
            'Rencontre API passée'
        );
        $rencontrePassee->setVille($ville);
        $rencontrePassee->setDateHeure(
            new \DateTimeImmutable('-2 days', new \DateTimeZone('UTC'))
        );

        $rencontreAnnulee = $this->creerRencontre(
            $organisateur,
            'Rencontre API annulée'
        );
        $rencontreAnnulee->setVille($ville);
        $rencontreAnnulee->annuler();

        // Enregistre les entités créées en base de données.
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();

        // Récupère les identifiants des entités pour les utiliser dans le test et pour le nettoyage des données après le test.
        $organisateurId = $organisateur->getId();
        $idAVenir = $rencontreAVenir->getId();
        $idAutreVille = $rencontreAutreVille->getId();
        $idPassee = $rencontrePassee->getId();
        $idAnnulee = $rencontreAnnulee->getId();

        // Liste des rencontres à supprimer après le test.
        $rencontreIds = [
            $idAVenir,
            $idAutreVille,
            $idPassee,
            $idAnnulee,
        ];

        try {
            // 1. La liste est accessible sans connexion et renvoie du JSON.
            $client->request('GET', '/api/rencontres');

            // Vérifie que la réponse est un code 200 OK.
            self::assertResponseStatusCodeSame(200);
            // Vérifie que le type de contenu de la réponse est bien JSON.
            self::assertResponseHeaderSame('content-type', 'application/json');

            // Décode le contenu JSON de la réponse en tableau associatif.
            $donnees = json_decode(
                $client->getResponse()->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            // Vérifie que la clé 'rencontres' est présente dans le tableau de données.
            $idsRetournes = array_column($donnees['rencontres'], 'id');

            // Vérifie que les rencontres à venir et dans une autre ville sont présentes dans la liste, tandis que la rencontre passée et annulée ne le sont pas.
            self::assertContains($idAVenir, $idsRetournes);
            self::assertContains($idAutreVille, $idsRetournes);
            self::assertNotContains($idPassee, $idsRetournes);
            self::assertNotContains($idAnnulee, $idsRetournes);

            // 2. Le filtre conserve uniquement la rencontre de la ville demandée.
            $client->request('GET', '/api/rencontres', [
                'ville' => $ville,
            ]);

            // Vérifie que la réponse est un code 200 OK.
            self::assertResponseStatusCodeSame(200);

            // Décode le contenu JSON de la réponse en tableau associatif.
            $donnees = json_decode(
                $client->getResponse()->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            // Vérifie que la liste contient exactement une rencontre correspondant à la ville demandée.
            self::assertCount(1, $donnees['rencontres']);
            // Vérifie que l’ID de la rencontre dans la liste correspond à l’ID de la rencontre à venir dans la ville demandée.
            self::assertSame($idAVenir, $donnees['rencontres'][0]['id']);

            // 3. Une ville sans rencontre donne une liste vide.
            $client->request('GET', '/api/rencontres', [
                'ville' => 'Ville absente ' . $suffixe,
            ]);

            // Vérifie que la réponse est un code 200 OK.
            self::assertResponseStatusCodeSame(200);

            // Décode le contenu JSON de la réponse en tableau associatif.
            $donnees = json_decode(
                $client->getResponse()->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            // Vérifie que la liste des rencontres est vide pour une ville sans rencontre.
            self::assertSame(['rencontres' => []], $donnees);

            // 4. Le détail d’une rencontre à venir est accessible et contient les bonnes informations.
            $client->request('GET', '/api/rencontres/' . $idAVenir);

            // Vérifie que la réponse est un code 200 OK et que le type de contenu est JSON.
            self::assertResponseStatusCodeSame(200);
            // Vérifie que le type de contenu de la réponse est bien JSON.
            self::assertResponseHeaderSame('content-type', 'application/json');

            // Décode le contenu JSON de la réponse en tableau associatif.
            $donnees = json_decode(
                $client->getResponse()->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            // On récupère le tableau situé sous la clé 'rencontre' pour plus de lisibilité.
            $detail = $donnees['rencontre'];

            // On vérifie que les informations correspondent bien.
            self::assertSame($idAVenir, $detail['id']);
            self::assertSame('Rencontre API à venir', $detail['titre']);
            self::assertSame($ville, $detail['ville']);
            self::assertSame(3, $detail['placesRestantes']);
            self::assertFalse($detail['annulee']);

            // Vérifie que l’organisateur est représenté uniquement par son pseudo,
            // sans autre information comme son adresse e-mail ou son mot de passe.
            self::assertSame(
                ['pseudo' => 'Organisateur API'],
                $detail['organisateur']
            );

            // L’objectif ici est de vérifier que notre API publique ne renvoie pas la liste des demandes de participation.
            // Concrètement, cette assertion PHPUnit vérifie que le tableau $detail ne contient pas de clé nommée participations
            self::assertArrayNotHasKey('participations', $detail);

            // 5. Le détail d’une rencontre annulée reste consultable.
            $client->request('GET', '/api/rencontres/' . $idAnnulee);

            // Vérifie que l’API accepte la consultation : HTTP 200 signifie « OK ».
            self::assertResponseStatusCodeSame(200);

            // Convertit le contenu JSON de la réponse en tableau associatif PHP.
            // Une exception sera déclenchée si le JSON est invalide.
            $donnees = json_decode(
                $client->getResponse()->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            // Vérifie que l’API indique bien que cette rencontre est annulée.
            self::assertTrue($donnees['rencontre']['annulee']);

            // 6. Supprime une rencontre du test pour obtenir un ID inexistant.

            // Récupère l’EntityManager actuel après les requêtes du navigateur de test.
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);

            // Détache les entités déjà chargées pour relire la rencontre depuis la base.
            $entityManager->clear();

            // Recherche la rencontre que nous allons supprimer.
            $rencontreASupprimer = $entityManager->find(
                Rencontre::class,
                $idAVenir
            );

            // Vérifie qu’elle existe avant de demander sa suppression.
            self::assertNotNull($rencontreASupprimer);

            // Programme la suppression, puis l’exécute en base avec flush().
            $entityManager->remove($rencontreASupprimer);
            $entityManager->flush();

            // Demande le détail de la rencontre qui vient d’être supprimée.
            $client->request('GET', '/api/rencontres/' . $idAVenir);

            // Vérifie que l’API répond « introuvable » avec le statut HTTP 404.
            self::assertResponseStatusCodeSame(404);
            self::assertResponseHeaderSame('content-type', 'application/json');

            $donnees = json_decode(
                $client->getResponse()->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            // Vérifie que la réponse contient exactement le message d’erreur attendu.
            self::assertSame(
                ['erreur' => 'Cette rencontre n’existe pas.'],
                $donnees
            );
        } finally {
            // Ce bloc nettoie les données même si une assertion du bloc try échoue.

            // Supprime d’abord les rencontres créées pour ce test.
            foreach ($rencontreIds as $rencontreId) {
                $this->nettoyerDonnees($rencontreId, []);
            }

            // Toutes ses rencontres ayant été supprimées, l’organisateur peut être supprimé.
            $this->nettoyerDonnees($idAVenir, [$organisateurId]);
        }
    }





    // Les méthodes suivantes sont des utilitaires pour créer des entités et nettoyer la base de test.
    private function verifierBaseDeTest(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        // Vérifier la base utilisée avant toute écriture.
        self::assertSame(
            'manque_un_joueur_test',
            $entityManager->getConnection()->fetchOne('SELECT DATABASE()')
        );
    }

    private function creerUtilisateur(string $pseudo): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $utilisateur = new User();
        $utilisateur->setEmail(
            'test-' . bin2hex(random_bytes(8)) . '@example.com'
        );
        $utilisateur->setPseudo($pseudo);
        $utilisateur->setPassword(
            $passwordHasher->hashPassword($utilisateur, 'MotDePasseTest123!')
        );

        $entityManager->persist($utilisateur);

        return $utilisateur;
    }

    private function creerRencontre(
        User $organisateur,
        string $titre,
        int $placesRecherchees = 3
    ): Rencontre {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $rencontre = new Rencontre();
        $rencontre->setTitre($titre);
        $rencontre->setVille('Montpellier');
        $rencontre->setLieu('Terrain de test');
        $rencontre->setDateHeure(
            new \DateTimeImmutable('+2 days', new \DateTimeZone('UTC'))
        );
        $rencontre->setPlacesRecherchees($placesRecherchees);
        $rencontre->setOrganisateur($organisateur);

        $entityManager->persist($rencontre);

        return $rencontre;
    }

    private function creerParticipation(
        User $joueur,
        Rencontre $rencontre,
        string $statut = Participation::STATUT_EN_ATTENTE
    ): Participation {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $participation = new Participation();
        $participation->setUtilisateur($joueur);
        $participation->setStatut($statut);

        // Mettre à jour les deux côtés de la relation en mémoire.
        $rencontre->addParticipation($participation);

        $entityManager->persist($participation);

        return $participation;
    }

    /**
     * @param array<array-key, int> $utilisateurIds
     */
    private function nettoyerDonnees(int $rencontreId, array $utilisateurIds): void
    {
        // Récupérer le gestionnaire actuel après les requêtes du navigateur.
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        // Supprimer d’abord les participations liées à la rencontre.
        $participations = $entityManager
            ->getRepository(Participation::class)
            ->findBy(['rencontre' => $rencontreId]);

        foreach ($participations as $participation) {
            $entityManager->remove($participation);
        }

        $entityManager->flush();

        // Supprimer ensuite la rencontre.
        $rencontre = $entityManager->find(Rencontre::class, $rencontreId);

        if ($rencontre !== null) {
            $entityManager->remove($rencontre);
            $entityManager->flush();
        }

        // Supprimer enfin les utilisateurs créés pour ce test.
        foreach ($utilisateurIds as $utilisateurId) {
            $utilisateur = $entityManager->find(User::class, $utilisateurId);

            if ($utilisateur !== null) {
                $entityManager->remove($utilisateur);
            }
        }

        $entityManager->flush();
    }
}