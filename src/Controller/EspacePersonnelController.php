<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ParticipationRepository;
use App\Repository\RencontreRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class EspacePersonnelController extends AbstractController
{
    #[Route('/mon-espace', name: 'app_espace_personnel', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(RencontreRepository $rencontreRepository, ParticipationRepository $participationRepository, #[CurrentUser] User $user): Response
    {
        $rencontres = $rencontreRepository->findBy(['organisateur' => $user], ['dateHeure' => 'DESC']);
        $participations = $participationRepository->findBy(['utilisateur' => $user], ['dateDemande' => 'DESC']);

        return $this->render('espace_personnel/index.html.twig', [
            'rencontres' => $rencontres,
            'participations' => $participations,
        ]);
    }
}