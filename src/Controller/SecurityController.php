<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

// Ce contrôleur gère l’authentification des utilisateurs. Il contient deux méthodes : login() pour afficher le formulaire de connexion et traiter la soumission, et logout() pour gérer la déconnexion.
class SecurityController extends AbstractController
{
    // login() affiche le formulaire de connexion et traite la soumission. Elle utilise AuthenticationUtils pour récupérer l’erreur éventuelle et le dernier e-mail saisi, afin de les afficher dans la page.
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    // logout() est une méthode vide, car la déconnexion est gérée par le système de sécurité de Symfony. Elle est interceptée par la clé logout du pare-feu, qui redirige l’utilisateur vers la page d’accueil après la déconnexion.
    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
