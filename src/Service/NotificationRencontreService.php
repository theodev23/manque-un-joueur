<?php

namespace App\Service;

use App\Entity\Participation;
use App\Entity\Rencontre;
use App\Repository\ParticipationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class NotificationRencontreService
{
    public function __construct(
        private MailerInterface $mailer, // Service pour envoyer des e-mails
        private ParticipationRepository $participationRepository, // Service pour accéder aux données de participation
        private LoggerInterface $logger, // Service pour enregistrer les messages de journalisation
    ) {
    }

    // Retourne le nombre d'envois ayant échoué.
    public function envoyerAnnulation(Rencontre $rencontre): int
    {
        // Récupère toutes les participations acceptées pour la rencontre donnée.
        $participations = $this->participationRepository->findBy([
            'rencontre' => $rencontre,
            'statut' => Participation::STATUT_ACCEPTEE,
        ]);

        $nombreEchecs = 0;

        // Parcourt chaque participation et envoie un e-mail d'annulation à chaque joueur.
        foreach ($participations as $participation) {
            $joueur = $participation->getUtilisateur();

            // Crée un e-mail en utilisant le service TemplatedEmail, qui permet d'utiliser un template Twig pour le contenu HTML de l'e-mail.
            $email = (new TemplatedEmail())
                ->from(new Address(
                    'contact@manque-un-joueur.example',
                    'Il nous manque un joueur'
                ))
                ->to($joueur->getEmail())
                ->subject('Annulation de votre rencontre')
                ->htmlTemplate('emails/rencontre_annulee.html.twig')
                // Passe les variables rencontre et joueur au template Twig pour personnaliser le contenu de l'e-mail.
                ->context([
                    'rencontre' => $rencontre,
                    'joueur' => $joueur,
                ]);

            // Tente d'envoyer l'e-mail et capture toute exception de transport qui pourrait survenir (par exemple, si le serveur de messagerie est indisponible). Cette méthode permet de continuer à envoyer les e-mails aux autres joueurs même si l'envoi échoue pour un joueur donné.
            try {
                $this->mailer->send($email);
            } catch (TransportExceptionInterface $exception) {
                $nombreEchecs++;

                // Enregistre un message d'erreur dans le journal avec des informations sur la rencontre, la participation et l'exception.
                $this->logger->error(
                    'Échec de l’envoi d’un e-mail d’annulation.',
                    [
                        'rencontre_id' => $rencontre->getId(),
                        'participation_id' => $participation->getId(),
                        'exception' => $exception,
                    ]
                );
            }
        }

        return $nombreEchecs;
    }
}