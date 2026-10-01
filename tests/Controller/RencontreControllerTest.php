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
    public function testUnVisiteurDoitSeConnecterPourOrganiserUneRencontre(): void
    {
        $client = static::createClient();

        $client->request('GET', '/rencontre/nouvelle');

        self::assertResponseRedirects('/login');

        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="_username"]');
        self::assertSelectorExists('input[name="_password"]');
    }

    public function testUnUtilisateurNePeutPasModifierLaRencontreDUnAutre(): void
    {
        $client = static::createClient();

        $this->verifierBaseDeTest();

        $organisateur = $this->creerUtilisateur('Organisateur test');
        $autreUtilisateur = $this->creerUtilisateur('Joueur test');

        $rencontre = $this->creerRencontre(
            $organisateur,
            'Rencontre pour le test des autorisations'
        );

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();

        $rencontreId = $rencontre->getId();
        $organisateurId = $organisateur->getId();
        $autreUtilisateurId = $autreUtilisateur->getId();

        try {
            // Un autre joueur ne peut pas modifier la rencontre.
            $client->loginUser($autreUtilisateur, 'main');
            $client->request('GET', '/rencontre/' . $rencontreId . '/modifier');

            self::assertResponseStatusCodeSame(403);

            // L’organisateur peut accéder au formulaire de modification.
            $client->loginUser($organisateur, 'main');
            $client->request('GET', '/rencontre/' . $rencontreId . '/modifier');

            self::assertResponseIsSuccessful();
            self::assertSelectorExists('form[name="rencontre"]');
        } finally {
            $this->nettoyerDonnees(
                $rencontreId,
                [$organisateurId, $autreUtilisateurId]
            );
        }
    }

    public function testUneRencontreAnnuleeRefuseUneNouvelleParticipation(): void
    {
        $client = static::createClient();

        $this->verifierBaseDeTest();

        $organisateur = $this->creerUtilisateur('Organisateur test');
        $joueur = $this->creerUtilisateur('Joueur test');

        $rencontre = $this->creerRencontre(
            $organisateur,
            'Rencontre annulée pendant une inscription'
        );

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();

        $rencontreId = $rencontre->getId();
        $organisateurId = $organisateur->getId();
        $joueurId = $joueur->getId();

        try {
            // Le joueur ouvre la page avant l’annulation.
            $client->loginUser($joueur, 'main');
            $crawler = $client->request('GET', '/rencontre/' . $rencontreId);

            self::assertResponseIsSuccessful();

            // Conserver le formulaire, comme dans un onglet resté ouvert.
            $formulaire = $crawler
                ->selectButton('Demander à participer')
                ->form();

            // Annuler ensuite la rencontre en base.
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $rencontreEnBase = $entityManager->find(Rencontre::class, $rencontreId);

            self::assertNotNull($rencontreEnBase);

            $rencontreEnBase->annuler();
            $entityManager->flush();
            $entityManager->clear();

            // Le joueur soumet le formulaire obtenu avant l’annulation.
            $client->submit($formulaire);

            self::assertResponseRedirects('/rencontre/' . $rencontreId);

            $client->followRedirect();

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains(
                '.alert-danger',
                'Cette rencontre est annulée.'
            );

            // Aucune participation ne doit avoir été enregistrée.
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();

            self::assertSame(
                0,
                $entityManager->getRepository(Participation::class)->count([
                    'rencontre' => $rencontreId,
                ])
            );
        } finally {
            $this->nettoyerDonnees(
                $rencontreId,
                [$organisateurId, $joueurId]
            );
        }
    }

    public function testUneSeuleDemandePeutEtreAccepteePourLaDernierePlace(): void
    {
        $client = static::createClient();

        $this->verifierBaseDeTest();

        $utilisateurs = [
            'organisateur' => $this->creerUtilisateur('Organisateur test'),
            'joueur1' => $this->creerUtilisateur('Joueur 1'),
            'joueur2' => $this->creerUtilisateur('Joueur 2'),
        ];

        $rencontre = $this->creerRencontre(
            $utilisateurs['organisateur'],
            'Deux demandes pour une seule place',
            1
        );

        $premiereDemande = $this->creerParticipation(
            $utilisateurs['joueur1'],
            $rencontre
        );

        $secondeDemande = $this->creerParticipation(
            $utilisateurs['joueur2'],
            $rencontre
        );

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();

        $rencontreId = $rencontre->getId();
        $premiereDemandeId = $premiereDemande->getId();
        $secondeDemandeId = $secondeDemande->getId();

        $utilisateurIds = array_map(
            static fn (User $utilisateur): int => $utilisateur->getId(),
            $utilisateurs
        );

        try {
            $client->loginUser($utilisateurs['organisateur'], 'main');
            $crawler = $client->request('GET', '/rencontre/' . $rencontreId);

            self::assertResponseIsSuccessful();

            // Récupérer les deux formulaires avant la première acceptation.
            $premierFormulaire = $crawler
                ->filter('form[action="/participation/' . $premiereDemandeId . '/accepter"]')
                ->selectButton('Accepter')
                ->form();

            $secondFormulaire = $crawler
                ->filter('form[action="/participation/' . $secondeDemandeId . '/accepter"]')
                ->selectButton('Accepter')
                ->form();

            // La première demande prend la dernière place.
            $client->submit($premierFormulaire);

            self::assertResponseRedirects('/rencontre/' . $rencontreId);

            $client->followRedirect();

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains(
                '.alert-success',
                'La demande a été acceptée.'
            );

            // La seconde acceptation doit être bloquée côté serveur.
            $client->submit($secondFormulaire);

            self::assertResponseRedirects('/rencontre/' . $rencontreId);

            $client->followRedirect();

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains(
                '.alert-danger',
                'Cette rencontre est complète.'
            );

            // Vérifier les statuts réellement enregistrés.
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

            self::assertSame(
                1,
                $entityManager->getRepository(Participation::class)->count([
                    'rencontre' => $rencontreId,
                    'statut' => Participation::STATUT_ACCEPTEE,
                ])
            );

            $rencontreEnBase = $entityManager->find(Rencontre::class, $rencontreId);

            self::assertNotNull($rencontreEnBase);
            self::assertSame(0, $rencontreEnBase->getPlacesRestantes());
        } finally {
            $this->nettoyerDonnees($rencontreId, $utilisateurIds);
        }
    }

    public function testAnnulerUneParticipationAccepteeLibereUnePlace(): void
    {
        $client = static::createClient();

        $this->verifierBaseDeTest();

        $utilisateurs = [
            'organisateur' => $this->creerUtilisateur('Organisateur test'),
            'joueur1' => $this->creerUtilisateur('Joueur 1'),
            'joueur2' => $this->creerUtilisateur('Joueur 2'),
        ];

        $rencontre = $this->creerRencontre(
            $utilisateurs['organisateur'],
            'Une place libérée après une annulation',
            1
        );

        // Le premier joueur occupe la seule place.
        $participationAcceptee = $this->creerParticipation(
            $utilisateurs['joueur1'],
            $rencontre,
            Participation::STATUT_ACCEPTEE
        );

        // Le deuxième joueur attend une réponse.
        $demandeEnAttente = $this->creerParticipation(
            $utilisateurs['joueur2'],
            $rencontre
        );

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();

        $rencontreId = $rencontre->getId();
        $participationAccepteeId = $participationAcceptee->getId();
        $demandeEnAttenteId = $demandeEnAttente->getId();
        $organisateurId = $utilisateurs['organisateur']->getId();

        $utilisateurIds = array_map(
            static fn (User $utilisateur): int => $utilisateur->getId(),
            $utilisateurs
        );

        try {
            // Le premier joueur annule sa participation.
            $client->loginUser($utilisateurs['joueur1'], 'main');
            $client->request('GET', '/rencontre/' . $rencontreId);

            self::assertResponseIsSuccessful();

            $client->submitForm('Annuler ma participation');

            self::assertResponseRedirects('/rencontre/' . $rencontreId);

            $client->followRedirect();

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains(
                '.alert-success',
                'Votre participation a bien été annulée.'
            );

            // Vérifier la suppression et la place libérée.
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();

            self::assertNull(
                $entityManager->find(Participation::class, $participationAccepteeId)
            );

            $rencontreEnBase = $entityManager->find(Rencontre::class, $rencontreId);

            self::assertNotNull($rencontreEnBase);
            self::assertSame(1, $rencontreEnBase->getPlacesRestantes());

            // L’organisateur peut maintenant accepter le deuxième joueur.
            $organisateur = $entityManager->find(User::class, $organisateurId);

            self::assertNotNull($organisateur);

            $client->loginUser($organisateur, 'main');
            $crawler = $client->request('GET', '/rencontre/' . $rencontreId);

            self::assertResponseIsSuccessful();

            $formulaire = $crawler
                ->filter('form[action="/participation/' . $demandeEnAttenteId . '/accepter"]')
                ->selectButton('Accepter')
                ->form();

            $client->submit($formulaire);

            self::assertResponseRedirects('/rencontre/' . $rencontreId);

            $client->followRedirect();

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains(
                '.alert-success',
                'La demande a été acceptée.'
            );

            // Le deuxième joueur occupe désormais la place.
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

            self::assertSame(
                1,
                $entityManager->getRepository(Participation::class)->count([
                    'rencontre' => $rencontreId,
                    'statut' => Participation::STATUT_ACCEPTEE,
                ])
            );
        } finally {
            $this->nettoyerDonnees($rencontreId, $utilisateurIds);
        }
    }

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