<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
}