<?php

namespace App\Controller;

use App\Entity\Rencontre;
use App\Repository\RencontreRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ApiRencontreController extends AbstractController
{
    #[Route('/api/rencontres', name: 'app_api_rencontres', methods: ['GET'], format: 'json')]
    // Cette méthode retourne la liste des rencontres à venir, éventuellement filtrée par ville. Sans résultat, elle renvoie {"rencontres":[]}
    public function index(Request $request, RencontreRepository $rencontreRepository): JsonResponse {

        // Lit le filtre dans l’URL, par exemple ?ville=Montpellier.
        $ville = trim($request->query->get('ville', ''));

        // Réutilise la recherche existante.
        $rencontres = $rencontreRepository->findRencontresAVenir($ville);

        $donnees = [];

        // Prépare les données à retourner dans la réponse JSON.
        foreach ($rencontres as $rencontre) {
            $donnees[] = $this->preparerRencontre($rencontre);
        }

        return $this->json([
            'rencontres' => $donnees,
        ]);
    }

    #[Route('/api/rencontres/{id}', name: 'app_api_rencontre_show', requirements: ['id' => '\d+'], methods: ['GET'], format: 'json')]
    // Cette méthode retourne les détails d’une rencontre spécifique en fonction de son identifiant.
    // Si elle n’existe pas, elle renvoie une erreur JSON avec le statut HTTP 404.
    public function show(int $id, RencontreRepository $rencontreRepository): JsonResponse {

        $rencontre = $rencontreRepository->find($id);

        if ($rencontre === null) {
            return $this->json(
                ['erreur' => 'Cette rencontre n’existe pas.'],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->json([
            'rencontre' => $this->preparerRencontre($rencontre),
        ]);
    }

    // Méthode privée qui sélectionne les informations publiques exposées par l’API.
    // Les entités ne sont pas exposées directement pour éviter de divulguer des informations sensibles ou inutiles.
    private function preparerRencontre(Rencontre $rencontre): array
    {
        return [
            'id' => $rencontre->getId(),
            'titre' => $rencontre->getTitre(),
            'ville' => $rencontre->getVille(),
            'lieu' => $rencontre->getLieu(),
            'dateHeure' => $rencontre
                ->getDateHeure()
                ->setTimezone(new \DateTimeZone('Europe/Paris'))
                // format() convertit la date en chaîne de caractères au format ATOM (exemple : 2024-06-15T14:30:00+02:00).
                ->format(\DateTimeInterface::ATOM),
            'placesRecherchees' => $rencontre->getPlacesRecherchees(),
            'placesRestantes' => $rencontre->getPlacesRestantes(),
            'description' => $rencontre->getDescription(),
            'annulee' => $rencontre->isAnnulee(),
            'organisateur' => [
                'pseudo' => $rencontre->getOrganisateur()->getPseudo(),
            ],
        ];
    }
}