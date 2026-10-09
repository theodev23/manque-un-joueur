<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Ce test fonctionnel vérifie le comportement du contrôleur d’inscription. Il simule un navigateur qui envoie des requêtes HTTP à l’application et vérifie les réponses. Il teste notamment que la soumission d’un formulaire d’inscription avec une adresse e-mail invalide empêche la création du compte, et que la soumission d’un formulaire valide crée un compte avec un mot de passe haché.
final class RegistrationControllerTest extends WebTestCase
{
    // Cette méthode teste que la soumission d’un formulaire d’inscription avec une adresse e-mail invalide empêche la création du compte.
    public function testUneAdresseEmailInvalideEmpecheLaCreationDuCompte(): void
    {
        // Crée un client HTTP pour simuler un navigateur.
        $client = static::createClient();

        // Récupère l’EntityManager pour interagir avec la base de données.
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        // Vérifie que le test s’exécute bien sur la base de données de test.
        self::assertSame(
            'manque_un_joueur_test',
            $entityManager->getConnection()->fetchOne('SELECT DATABASE()')
        );

        // Génère une adresse e-mail invalide unique pour ce test.
        $emailInvalide = 'adresse-invalide-' . bin2hex(random_bytes(8));

        // Compte le nombre d’utilisateurs avant la soumission du formulaire.
        $nombreUtilisateursAvant = $entityManager
            ->getRepository(User::class)
            ->count([]);

        try {
            // Accède à la page d’inscription.
            $client->request('GET', '/register');

            // Vérifie que la page s’affiche correctement.
            self::assertResponseIsSuccessful();

            // Soumet le formulaire d’inscription avec une adresse e-mail invalide. Tous les autres champs sont remplis avec des valeurs valides.
            $client->submitForm('Créer mon compte', [
                'registration_form[pseudo]' => 'Joueur test',
                'registration_form[email]' => $emailInvalide,
                'registration_form[plainPassword]' => 'MotDePasseTest123!',
                'registration_form[agreeTerms]' => '1',
            ]);

            // Vérifie que la réponse HTTP indique une erreur de validation (422 Unprocessable Entity) et que le message d’erreur approprié est affiché dans le formulaire.
            self::assertResponseStatusCodeSame(422);
            self::assertSelectorTextContains(
                'form[name="registration_form"]',
                'Veuillez saisir une adresse e-mail valide.'
            );

            // Vérifier que le refus n’a créé aucun compte.
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);

            // clear() détache toutes les entités de l’EntityManager pour s’assurer que la requête suivante lit directement depuis la base de données.
            $entityManager->clear();

            // Récupère le repository User pour interroger la base de données.
            $repository = $entityManager->getRepository(User::class);

            // Vérifie qu’aucun utilisateur avec l’adresse e-mail invalide n’a été créé.
            self::assertNull(
                $repository->findOneBy(['email' => $emailInvalide])
            );

            // Vérifie que le nombre d’utilisateurs dans la base de données est resté le même qu’avant la soumission du formulaire.
            self::assertSame(
                $nombreUtilisateursAvant,
                $repository->count([])
            );
        } finally {
            // Supprimer uniquement le compte créé par ce test, s’il existe. Cela garantit que la base de données reste propre pour les autres tests.
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();

            // Récupère l’utilisateur avec l’adresse e-mail invalide, s’il existe.
            $utilisateur = $entityManager
                ->getRepository(User::class)
                ->findOneBy(['email' => $emailInvalide]);

            // Si un tel utilisateur existe, le supprimer de la base de données.
            if ($utilisateur !== null) {
                $entityManager->remove($utilisateur);
                $entityManager->flush();
            }
        }
    }

    // Cette méthode teste que la soumission d’un formulaire d’inscription valide crée un compte avec un mot de passe haché.
    public function testUneInscriptionValideCreeUnCompteAvecUnMotDePasseHache(): void
    {
        // Crée un client HTTP pour simuler un navigateur.
        $client = static::createClient();

        // Récupère l’EntityManager pour interagir avec la base de données.
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        // Vérifie que le test s’exécute bien sur la base de données de test.
        self::assertSame(
            'manque_un_joueur_test',
            $entityManager->getConnection()->fetchOne('SELECT DATABASE()')
        );

        // Génère une adresse e-mail unique pour ce test afin d’éviter les conflits avec d’autres tests ou données existantes.
        $email = 'inscription-' . bin2hex(random_bytes(8)) . '@example.com';
        $pseudo = 'Nouveau joueur';
        $motDePasse = 'MotDePasseTest123!';

        // Génère l’URL de redirection vers la page d’accueil après une inscription réussie.
        $urlAccueil = static::getContainer()
            ->get(UrlGeneratorInterface::class)
            ->generate('app_home');

        try {
            // Accède à la page d’inscription.
            $client->request('GET', '/register');

            // Vérifie que la page s’affiche correctement.
            self::assertResponseIsSuccessful();

            // Soumettre le véritable formulaire avec des données valides.
            $client->submitForm('Créer mon compte', [
                'registration_form[pseudo]' => $pseudo,
                'registration_form[email]' => $email,
                'registration_form[plainPassword]' => $motDePasse,
                'registration_form[agreeTerms]' => '1',
            ]);

            // Vérifie que la réponse HTTP est une redirection vers la page d’accueil.
            self::assertResponseRedirects($urlAccueil);

            // Suivre la redirection pour vérifier que la page d’accueil s’affiche correctement après l’inscription.
            $client->followRedirect();

            // Vérifie que la page d’accueil s’affiche correctement après la redirection.
            self::assertResponseIsSuccessful();

            // Vérifie que l’utilisateur a été créé en base de données avec les bonnes informations.
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();

            // Récupère l’utilisateur créé avec l’adresse e-mail utilisée pour l’inscription.
            $utilisateur = $entityManager
                ->getRepository(User::class)
                ->findOneBy(['email' => $email]);

            // Vérifie que l’utilisateur existe et que ses informations sont correctes.
            self::assertNotNull($utilisateur);
            self::assertSame($email, $utilisateur->getEmail());
            self::assertSame($pseudo, $utilisateur->getPseudo());
            self::assertContains('ROLE_USER', $utilisateur->getRoles());

            // Vérifie que le mot de passe stocké en base de données n’est pas le même que le mot de passe en clair saisi par l’utilisateur, ce qui indique qu’il a été haché correctement.
            self::assertNotSame($motDePasse, $utilisateur->getPassword());

            // Vérifie que le mot de passe haché correspond bien au mot de passe en clair saisi par l’utilisateur en utilisant le service UserPasswordHasherInterface.
            $passwordHasher = static::getContainer()->get(
                UserPasswordHasherInterface::class
            );

            // Vérifie que le mot de passe haché correspond au mot de passe en clair saisi par l’utilisateur.
            self::assertTrue(
                $passwordHasher->isPasswordValid($utilisateur, $motDePasse)
            );
        } finally {
            // Supprimer uniquement le compte créé par ce test, s’il existe. Cela garantit que la base de données reste propre pour les autres tests.
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();

            // Récupère l’utilisateur avec l’adresse e-mail utilisée pour l’inscription, s’il existe.
            $utilisateur = $entityManager
                ->getRepository(User::class)
                ->findOneBy(['email' => $email]);

            // Si un tel utilisateur existe, le supprimer de la base de données.
            if ($utilisateur !== null) {
                $entityManager->remove($utilisateur);
                $entityManager->flush();
            }
        }
    }
}