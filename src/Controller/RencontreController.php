<?php

namespace App\Controller;

use App\Entity\Rencontre;
use App\Entity\User;
use App\Form\RencontreType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Form\FormError;
use App\Repository\RencontreRepository;
use App\Entity\Participation;
use App\Repository\ParticipationRepository;
use Doctrine\DBAL\LockMode;


final class RencontreController extends AbstractController
{
    #[Route('/rencontre', name: 'app_rencontre', methods: ['GET'])]
    public function index(Request $request, RencontreRepository $rencontreRepository): Response
    {
        // $request->query récupère les paramètres de l’URL. Par exemple, pour /rencontre?ville=Montpellier, nous récupérons Montpellier
        $ville = trim($request->query->getString('ville'));

        $rencontres = $rencontreRepository->findRencontresAVenir($ville);

        return $this->render('rencontre/index.html.twig', [
            'rencontres' => $rencontres,
            'ville' => $ville,
        ]);
    }

    #[Route('/rencontre/nouvelle', name: 'app_rencontre_new', methods: ['GET', 'POST'])]
    // IsGranted('ROLE_USER) réserve cette action aux utilisateurs connectés possédant ce rôle.
    #[IsGranted('ROLE_USER')]
    // #[CurrentUser] User $user fournit directement l’utilisateur connecté
    public function nouvelle(Request $request, EntityManagerInterface $entityManager, #[CurrentUser] User $user): Response {
        $rencontre = new Rencontre();
        $rencontre->setOrganisateur($user);

        // On prépare le formulaire puis on traite la requête.
        $form = $this->createForm(RencontreType::class, $rencontre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($rencontre);
            $entityManager->flush();

            return $this->redirectToRoute('app_rencontre');
        }

        return $this->render('rencontre/new.html.twig', [
            'rencontreForm' => $form,
        ]);
    }

    #[Route('/rencontre/{id}', name: 'app_rencontre_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function afficher(int $id, RencontreRepository $rencontreRepository, ParticipationRepository $participationRepository, #[CurrentUser] ?User $user): Response
    {
        $rencontre = $rencontreRepository->find($id);

        if ($rencontre === null) {
            throw $this->createNotFoundException('Cette rencontre n’existe pas.');
        }

        $participation = null;

        if ($user !== null) {
            $participation = $participationRepository->findOneBy(['utilisateur' => $user, 'rencontre' => $rencontre]);
        }

        $inscriptionsOuvertes = !$rencontre->isAnnulee() && $rencontre->getDateHeure() > new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $demandes = [];

        if ($user !== null && $rencontre->getOrganisateur()->getId() === $user->getId()) {
            $demandes = $participationRepository->findBy(['rencontre' => $rencontre], ['dateDemande' => 'ASC']);
        }

        return $this->render('rencontre/show.html.twig', [
            'rencontre' => $rencontre,
            'participation' => $participation,
            'inscriptionsOuvertes' => $inscriptionsOuvertes,
            'demandes' => $demandes,
        ]);
    }

    #[Route('/rencontre/{id}/participer', name: 'app_rencontre_participer', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function participer(int $id, Request $request, RencontreRepository $rencontreRepository, ParticipationRepository $participationRepository, EntityManagerInterface $entityManager, #[CurrentUser] User $user): Response
    {
        $rencontre = $rencontreRepository->find($id);

        if ($rencontre === null) {
            throw $this->createNotFoundException('Cette rencontre n’existe pas.');
        }

        // Vérifie le jeton de protection transmis par le formulaire
        if (!$this->isCsrfTokenValid('participer_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Le formulaire est invalide.');
        }

        if ($rencontre->getOrganisateur()->getId() === $user->getId()) {
            throw $this->createAccessDeniedException('Vous organisez déjà cette rencontre.');
        }

        $erreur = $entityManager->wrapInTransaction(function () use (
            $entityManager,
            $participationRepository,
            $rencontre,
            $user
        ): ?string {
            $entityManager->refresh($rencontre, LockMode::PESSIMISTIC_WRITE);

            if ($rencontre->isAnnulee()) {
                return 'Cette rencontre est annulée.';
            }

            if ($rencontre->getDateHeure() <= new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
                return 'Cette rencontre a déjà commencé.';
            }

            $participationExistante = $participationRepository->findOneBy([
                'utilisateur' => $user,
                'rencontre' => $rencontre,
            ]);

            if ($participationExistante !== null) {
                return 'Vous avez déjà envoyé une demande pour cette rencontre.';
            }

            $nombreAcceptees = $participationRepository->count([
                'rencontre' => $rencontre,
                'statut' => Participation::STATUT_ACCEPTEE,
            ]);

            if ($rencontre->getPlacesRecherchees() <= $nombreAcceptees) {
                return 'Cette rencontre est complète. Vous ne pouvez plus envoyer de demande.';
            }

            $participation = new Participation();
            $participation->setUtilisateur($user);
            $participation->setRencontre($rencontre);

            $entityManager->persist($participation);

            return null;
        });

        if ($erreur !== null) {
            $this->addFlash('error', $erreur);
        } else {
            $this->addFlash('success', 'Votre demande de participation a été envoyée.');
        }

        return $this->redirectToRoute('app_rencontre_show', ['id' => $id]);
    }

    #[Route('/participation/{id}/refuser', name: 'app_participation_refuser', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function refuser(int $id, Request $request, ParticipationRepository $participationRepository, EntityManagerInterface $entityManager, #[CurrentUser] User $user): Response
    {
        $participation = $participationRepository->find($id);

        if ($participation === null) {
            throw $this->createNotFoundException('Cette demande n’existe pas.');
        }

        $rencontre = $participation->getRencontre();

        if ($rencontre->getOrganisateur()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Seul l’organisateur peut traiter cette demande.');
        }

        if (!$this->isCsrfTokenValid('refuser_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Le formulaire est invalide.');
        }

            $erreur = $entityManager->wrapInTransaction(function () use (
            $entityManager,
            $rencontre,
            $participation
        ): ?string {
            $entityManager->refresh($rencontre, LockMode::PESSIMISTIC_WRITE);
            $entityManager->refresh($participation, LockMode::PESSIMISTIC_WRITE);

            if ($rencontre->isAnnulee()) {
                return 'Cette rencontre est annulée : les demandes ne peuvent plus être traitées.';
            }

            if ($rencontre->getDateHeure() <= new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
                return 'Cette rencontre a déjà commencé.';
            }

            if ($participation->getStatut() !== Participation::STATUT_EN_ATTENTE) {
                return 'Cette demande a déjà été traitée.';
            }

            $participation->setStatut(Participation::STATUT_REFUSEE);

            return null;
        });

        if ($erreur !== null) {
            $this->addFlash('error', $erreur);
        } else {
            $this->addFlash('success', 'La demande a été refusée.');
        }

        return $this->redirectToRoute('app_rencontre_show', [
            'id' => $rencontre->getId(),
        ]);
    }

    #[Route('/participation/{id}/accepter', name: 'app_participation_accepter', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function accepter(int $id, Request $request, ParticipationRepository $participationRepository, EntityManagerInterface $entityManager, #[CurrentUser] User $user): Response
    {
        $participation = $participationRepository->find($id);

        if ($participation === null) {
            throw $this->createNotFoundException('Cette demande n’existe pas.');
        }

        $rencontre = $participation->getRencontre();

        if ($rencontre->getOrganisateur()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Seul l’organisateur peut traiter cette demande.');
        }

        if (!$this->isCsrfTokenValid('accepter_' . $id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Le formulaire est invalide.');
        }

        // Le code ci-dessous protège le cas où deux acceptations arrivent presque simultanément pour la dernière place.
        // Le verrou sur la rencontre oblige une autre exécution de cette méthode pour la même rencontre à attendre avant de vérifier les places. Doctrine fournit ces mécanismes pour gérer les opérations concurrentes.
        // wrapInTransaction regroupe les opérations dans une transaction et appelle automatiquement flush() avant de la valider
        // refresh(..., LockMode::PESSIMISTIC_WRITE) relit les données et les verrouille jusqu’à la fin de la transaction
        // function () use (...) définit une fonction anonyme qui peut utiliser les variables indiquées
        $erreur = $entityManager->wrapInTransaction(function () use ($entityManager, $rencontre, $participation): ?string {
            $entityManager->refresh($rencontre, LockMode::PESSIMISTIC_WRITE);
            $entityManager->refresh($participation, LockMode::PESSIMISTIC_WRITE);

            if ($rencontre->isAnnulee()) {
                return 'Cette rencontre est annulée : aucune modification n’est possible.';
            }

            if ($rencontre->getDateHeure() <= new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
                return 'Cette rencontre a déjà commencé.';
            }

            if ($participation->getStatut() !== Participation::STATUT_EN_ATTENTE) {
                return 'Cette demande a déjà été traitée.';
            }

            if ($rencontre->getPlacesRestantes() <= 0) {
                return 'Cette rencontre est complète.';
            }

            $participation->setStatut(Participation::STATUT_ACCEPTEE);

            // indique que l’acceptation a été effectuée
            return null;
        });

        if ($erreur !== null) {
            $this->addFlash('error', $erreur);
        } else {
            $this->addFlash('success', 'La demande a été acceptée.');
        }

        return $this->redirectToRoute('app_rencontre_show', ['id' => $rencontre->getId()]);
    }

    #[Route('/rencontre/{id}/modifier', name: 'app_rencontre_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function modifier(
        int $id,
        Request $request,
        RencontreRepository $rencontreRepository,
        ParticipationRepository $participationRepository,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): Response {
        $rencontre = $rencontreRepository->find($id);

        if ($rencontre === null) {
            throw $this->createNotFoundException('Cette rencontre n’existe pas.');
        }

        if ($rencontre->getOrganisateur()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException(
                'Seul l’organisateur peut modifier cette rencontre.'
            );
        }

        $maintenant = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if ($rencontre->getDateHeure() <= $maintenant) {
            $this->addFlash('error', 'Une rencontre déjà commencée ne peut plus être modifiée.');

            return $this->redirectToRoute('app_rencontre_show', ['id' => $id]);
        }

        if ($rencontre->isAnnulee()) {
            $this->addFlash('error', 'Une rencontre annulée ne peut plus être modifiée.');

            return $this->redirectToRoute('app_rencontre_show', ['id' => $id]);
        }

        // Le formulaire doit travailler sur une copie pour conserver les données enregistrées jusqu’à la fin des contrôles.
        $rencontreModifiee = clone $rencontre;

        $form = $this->createForm(RencontreType::class, $rencontreModifiee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
        $erreur = $entityManager->wrapInTransaction(function () use (
                $entityManager,
                $participationRepository,
                $rencontre,
                $rencontreModifiee
            ): ?string {
                // Même verrou que lors de l’acceptation d’une participation. Le verrou coordonne la modification avec l'action accepter() : le contrôle des places et l’enregistrement se font dans la même transaction. Doctrine exige une transaction pour ce type de verrou.
                $entityManager->refresh($rencontre, LockMode::PESSIMISTIC_WRITE);

                if ($rencontre->isAnnulee()) {
                    return 'Cette rencontre est annulée : aucune modification n’est possible.';
                }
                
                $maintenant = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

                // Vérification de la date actuellement enregistrée.
                if ($rencontre->getDateHeure() <= $maintenant) {
                    return 'Cette rencontre a déjà commencé et ne peut plus être modifiée.';
                }

                // Vérification de la nouvelle date proposée.
                if ($rencontreModifiee->getDateHeure() <= $maintenant) {
                    return 'La rencontre doit avoir lieu dans le futur.';
                }

                $nombreAcceptees = $participationRepository->count([
                    'rencontre' => $rencontre,
                    'statut' => Participation::STATUT_ACCEPTEE,
                ]);

                if ($rencontreModifiee->getPlacesRecherchees() < $nombreAcceptees) {
                    return sprintf(
                        'Le nombre de joueurs recherchés ne peut pas être inférieur à %d : autant de participations sont déjà acceptées.',
                        $nombreAcceptees
                    );
                }

                // Les contrôles sont passés : on applique les nouvelles valeurs.
                $rencontre->setTitre($rencontreModifiee->getTitre());
                $rencontre->setVille($rencontreModifiee->getVille());
                $rencontre->setLieu($rencontreModifiee->getLieu());
                $rencontre->setDateHeure($rencontreModifiee->getDateHeure());
                $rencontre->setPlacesRecherchees($rencontreModifiee->getPlacesRecherchees());
                $rencontre->setDescription($rencontreModifiee->getDescription());

                // wrapInTransaction() effectue automatiquement le flush().
                return null;
            });

            if ($erreur === null) {
                $this->addFlash('success', 'La rencontre a bien été modifiée.');

                return $this->redirectToRoute('app_rencontre_show', ['id' => $id]);
            }

            $form->addError(new FormError($erreur));
        }

        return $this->render('rencontre/edit.html.twig', [
            'rencontre' => $rencontre,
            'rencontreForm' => $form,
        ]);
    }

    #[Route('/participation/{id}/annuler', name: 'app_participation_annuler', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function annulerParticipation(
        int $id,
        Request $request,
        ParticipationRepository $participationRepository,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): Response {
        $participation = $participationRepository->find($id);

        if ($participation === null) {
            throw $this->createNotFoundException('Cette demande n’existe pas.');
        }

        if ($participation->getUtilisateur()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException(
                'Vous pouvez uniquement annuler votre propre participation.'
            );
        }

        if (!$this->isCsrfTokenValid(
            'annuler_' . $id,
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException('Le formulaire est invalide.');
        }

        $rencontre = $participation->getRencontre();
        $rencontreId = $rencontre->getId();

        $erreur = $entityManager->wrapInTransaction(function () use (
            $entityManager,
            $rencontre,
            $participation
        ): ?string {
            // Même ordre de verrouillage que dans accepter().
            $entityManager->refresh($rencontre, LockMode::PESSIMISTIC_WRITE);
            $entityManager->refresh($participation, LockMode::PESSIMISTIC_WRITE);

            if ($rencontre->isAnnulee()) {
                return 'Cette rencontre est annulée : aucune modification n’est possible.';
            }

            $maintenant = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

            if ($rencontre->getDateHeure() <= $maintenant) {
                return 'La rencontre a déjà commencé : vous ne pouvez plus annuler votre participation.';
            }

            if (!in_array($participation->getStatut(), [
                Participation::STATUT_EN_ATTENTE,
                Participation::STATUT_ACCEPTEE,
            ], true)) {
                return 'Seule une demande en attente ou acceptée peut être annulée.';
            }

            $entityManager->remove($participation);

            // La suppression sera effectuée par le flush() de wrapInTransaction().
            return null;
        });

        if ($erreur !== null) {
            $this->addFlash('error', $erreur);
        } else {
            $this->addFlash('success', 'Votre participation a bien été annulée.');
        }

        return $this->redirectToRoute('app_rencontre_show', [
            'id' => $rencontreId,
        ]);
    }

    // Cette route a deux usages :
    // - GET affiche la page de confirmation, sans modifier la rencontre.
    // - POST vérifie le jeton CSRF, verrouille la rencontre et enregistre l’annulation.
    #[Route('/rencontre/{id}/annuler', name: 'app_rencontre_annuler', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function annulerRencontre(
        int $id,
        Request $request,
        RencontreRepository $rencontreRepository,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user
    ): Response {
        $rencontre = $rencontreRepository->find($id);

        if ($rencontre === null) {
            throw $this->createNotFoundException('Cette rencontre n’existe pas.');
        }

        if ($rencontre->getOrganisateur()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException(
                'Seul l’organisateur peut annuler cette rencontre.'
            );
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(
                'annuler_rencontre_' . $id,
                $request->request->get('_token')
            )) {
                throw $this->createAccessDeniedException('Le formulaire est invalide.');
            }

            $erreur = $entityManager->wrapInTransaction(function () use (
                $entityManager,
                $rencontre
            ): ?string {
                $entityManager->refresh($rencontre, LockMode::PESSIMISTIC_WRITE);

                if ($rencontre->isAnnulee()) {
                    return 'Cette rencontre est déjà annulée.';
                }

                $maintenant = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

                if ($rencontre->getDateHeure() <= $maintenant) {
                    return 'Une rencontre déjà commencée ne peut plus être annulée.';
                }

                $rencontre->annuler();

                return null;
            });

            if ($erreur !== null) {
                $this->addFlash('error', $erreur);
            } else {
                $this->addFlash('success', 'La rencontre a bien été annulée.');
            }

            return $this->redirectToRoute('app_rencontre_show', ['id' => $id]);
        }

        // En GET, on affiche seulement la confirmation si l’annulation est possible.
        if ($rencontre->isAnnulee()) {
            $this->addFlash('error', 'Cette rencontre est déjà annulée.');

            return $this->redirectToRoute('app_rencontre_show', ['id' => $id]);
        }

        if ($rencontre->getDateHeure() <= new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
            $this->addFlash('error', 'Une rencontre déjà commencée ne peut plus être annulée.');

            return $this->redirectToRoute('app_rencontre_show', ['id' => $id]);
        }

        return $this->render('rencontre/annuler.html.twig', [
            'rencontre' => $rencontre,
        ]);
    }
}