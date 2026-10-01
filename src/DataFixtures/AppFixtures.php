<?php

namespace App\DataFixtures;

use App\Entity\Participation;
use App\Entity\Rencontre;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Comptes fictifs destinés à la démonstration locale.
        $utilisateurs = [];

        foreach ([
            'alex' => 'Alex',
            'sam' => 'Sam',
            'camille' => 'Camille',
        ] as $identifiant => $pseudo) {
            $utilisateur = new User();
            $utilisateur->setEmail($identifiant . '@example.com');
            $utilisateur->setPseudo($pseudo);
            $utilisateur->setPassword(
                $this->passwordHasher->hashPassword(
                    $utilisateur,
                    'DemoFoot2026!'
                )
            );

            $manager->persist($utilisateur);
            $utilisateurs[$identifiant] = $utilisateur;
        }

        // Calculer les horaires en heure de Paris, puis les stocker en UTC.
        $reference = new \DateTimeImmutable(
            'today',
            new \DateTimeZone('Europe/Paris')
        );

        $donneesRencontres = [
            'montpellier' => [
                'titre' => 'Foot entre amis à Montpellier',
                'ville' => 'Montpellier',
                'lieu' => 'Stade de la Mosson',
                'jours' => '+3 days',
                'places' => 3,
                'organisateur' => 'alex',
                'description' => 'Match amical, tous niveaux bienvenus. Rendez-vous quinze minutes avant le début.',
                'annulee' => false,
            ],
            'nimes' => [
                'titre' => 'Une équipe à compléter à Nîmes',
                'ville' => 'Nîmes',
                'lieu' => 'Stade des Costières',
                'jours' => '+5 days',
                'places' => 1,
                'organisateur' => 'sam',
                'description' => 'Il nous manquait une personne : la rencontre est désormais complète.',
                'annulee' => false,
            ],
            'clermont' => [
                'titre' => 'Football à Clermont-l’Hérault',
                'ville' => 'Clermont-l’Hérault',
                'lieu' => 'espace sportif',
                'jours' => '+7 days',
                'places' => 2,
                'organisateur' => 'camille',
                'description' => 'Une rencontre pour jouer tranquillement en fin de journée.',
                'annulee' => false,
            ],
            'annulee' => [
                'titre' => 'Match annulé à Montpellier',
                'ville' => 'Montpellier',
                'lieu' => 'Stade de la Mosson',
                'jours' => '+4 days',
                'places' => 2,
                'organisateur' => 'alex',
                'description' => 'Rencontre annulée : le terrain ne sera pas disponible.',
                'annulee' => true,
            ],
            'passee' => [
                'titre' => 'Notre rencontre de la semaine dernière',
                'ville' => 'Nîmes',
                'lieu' => 'Stade des Costières',
                'jours' => '-7 days',
                'places' => 2,
                'organisateur' => 'sam',
                'description' => 'Une rencontre passée, conservée dans l’espace personnel.',
                'annulee' => false,
            ],
        ];

        $rencontres = [];

        foreach ($donneesRencontres as $cle => $donnees) {
            $date = $reference
                ->modify($donnees['jours'])
                ->setTime(18, 30)
                ->setTimezone(new \DateTimeZone('UTC'));

            $rencontre = new Rencontre();
            $rencontre->setTitre($donnees['titre']);
            $rencontre->setVille($donnees['ville']);
            $rencontre->setLieu($donnees['lieu']);
            $rencontre->setDateHeure($date);
            $rencontre->setPlacesRecherchees($donnees['places']);
            $rencontre->setDescription($donnees['description']);
            $rencontre->setOrganisateur(
                $utilisateurs[$donnees['organisateur']]
            );

            if ($donnees['annulee']) {
                $rencontre->annuler();
            }

            $manager->persist($rencontre);
            $rencontres[$cle] = $rencontre;
        }

        $donneesParticipations = [
            ['montpellier', 'sam', Participation::STATUT_ACCEPTEE],
            ['montpellier', 'camille', Participation::STATUT_EN_ATTENTE],
            ['nimes', 'alex', Participation::STATUT_ACCEPTEE],
            ['nimes', 'camille', Participation::STATUT_EN_ATTENTE],
            ['clermont', 'alex', Participation::STATUT_REFUSEE],
            ['annulee', 'sam', Participation::STATUT_ACCEPTEE],
            ['passee', 'alex', Participation::STATUT_ACCEPTEE],
            ['passee', 'camille', Participation::STATUT_ACCEPTEE],
        ];

        foreach ($donneesParticipations as [$cleRencontre, $joueur, $statut]) {
            $participation = new Participation();
            $participation->setUtilisateur($utilisateurs[$joueur]);
            $participation->setStatut($statut);

            // Les demandes précèdent les rencontres, y compris celle passée.
            $participation->setDateDemande(
                $reference
                    ->modify('-10 days')
                    ->setTime(12, 0)
                    ->setTimezone(new \DateTimeZone('UTC'))
            );

            $rencontres[$cleRencontre]->addParticipation($participation);

            $manager->persist($participation);
        }

        $manager->flush();
    }
}