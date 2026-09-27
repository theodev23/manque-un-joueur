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
use App\Repository\RencontreRepository;

final class RencontreController extends AbstractController
{
    #[Route('/rencontre', name: 'app_rencontre', methods: ['GET'])]
    public function index(RencontreRepository $rencontreRepository): Response
    {
        $rencontres = $rencontreRepository->findRencontresAVenir();

        return $this->render('rencontre/index.html.twig', [
            'rencontres' => $rencontres,
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
    public function afficher(int $id, RencontreRepository $rencontreRepository): Response
    {
        $rencontre = $rencontreRepository->find($id);

        if ($rencontre === null) {
            throw $this->createNotFoundException('Cette rencontre n’existe pas.');
        }

        return $this->render('rencontre/show.html.twig', [
            'rencontre' => $rencontre,
        ]);
    }
}