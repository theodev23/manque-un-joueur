<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RegistrationControllerTest extends WebTestCase
{
    public function testUneAdresseEmailInvalideEmpecheLaCreationDuCompte(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        self::assertSame(
            'manque_un_joueur_test',
            $entityManager->getConnection()->fetchOne('SELECT DATABASE()')
        );

        // Une valeur unique, volontairement dépourvue de @.
        $emailInvalide = 'adresse-invalide-' . bin2hex(random_bytes(8));

        $nombreUtilisateursAvant = $entityManager
            ->getRepository(User::class)
            ->count([]);

        try {
            $client->request('GET', '/register');

            self::assertResponseIsSuccessful();

            // Tous les autres champs sont valides.
            $client->submitForm('Créer mon compte', [
                'registration_form[pseudo]' => 'Joueur test',
                'registration_form[email]' => $emailInvalide,
                'registration_form[plainPassword]' => 'MotDePasseTest123!',
                'registration_form[agreeTerms]' => '1',
            ]);

            self::assertResponseStatusCodeSame(422);
            self::assertSelectorTextContains(
                'form[name="registration_form"]',
                'Veuillez saisir une adresse e-mail valide.'
            );

            // Vérifier que le refus n’a créé aucun compte.
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();

            $repository = $entityManager->getRepository(User::class);

            self::assertNull(
                $repository->findOneBy(['email' => $emailInvalide])
            );
            self::assertSame(
                $nombreUtilisateursAvant,
                $repository->count([])
            );
        } finally {
            // Nettoyer même si une régression a permis la création du compte.
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();

            $utilisateur = $entityManager
                ->getRepository(User::class)
                ->findOneBy(['email' => $emailInvalide]);

            if ($utilisateur !== null) {
                $entityManager->remove($utilisateur);
                $entityManager->flush();
            }
        }
    }

    public function testUneInscriptionValideCreeUnCompteAvecUnMotDePasseHache(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        self::assertSame(
            'manque_un_joueur_test',
            $entityManager->getConnection()->fetchOne('SELECT DATABASE()')
        );

        $email = 'inscription-' . bin2hex(random_bytes(8)) . '@example.com';
        $pseudo = 'Nouveau joueur';
        $motDePasse = 'MotDePasseTest123!';

        $urlAccueil = static::getContainer()
            ->get(UrlGeneratorInterface::class)
            ->generate('app_home');

        try {
            $client->request('GET', '/register');

            self::assertResponseIsSuccessful();

            // Soumettre le véritable formulaire avec des données valides.
            $client->submitForm('Créer mon compte', [
                'registration_form[pseudo]' => $pseudo,
                'registration_form[email]' => $email,
                'registration_form[plainPassword]' => $motDePasse,
                'registration_form[agreeTerms]' => '1',
            ]);

            self::assertResponseRedirects($urlAccueil);

            $client->followRedirect();

            self::assertResponseIsSuccessful();

            // Relire le compte enregistré en base.
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();

            $utilisateur = $entityManager
                ->getRepository(User::class)
                ->findOneBy(['email' => $email]);

            self::assertNotNull($utilisateur);
            self::assertSame($email, $utilisateur->getEmail());
            self::assertSame($pseudo, $utilisateur->getPseudo());
            self::assertContains('ROLE_USER', $utilisateur->getRoles());

            // Le mot de passe ne doit pas être stocké en clair.
            self::assertNotSame($motDePasse, $utilisateur->getPassword());

            // Le hachage enregistré doit correspondre au mot de passe saisi.
            $passwordHasher = static::getContainer()->get(
                UserPasswordHasherInterface::class
            );

            self::assertTrue(
                $passwordHasher->isPasswordValid($utilisateur, $motDePasse)
            );
        } finally {
            // Supprimer uniquement le compte créé par ce test.
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $entityManager->clear();

            $utilisateur = $entityManager
                ->getRepository(User::class)
                ->findOneBy(['email' => $email]);

            if ($utilisateur !== null) {
                $entityManager->remove($utilisateur);
                $entityManager->flush();
            }
        }
    }
}