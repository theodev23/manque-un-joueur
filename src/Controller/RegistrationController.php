<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

// Cette classe gère l’inscription des utilisateurs. Elle contient une méthode register(...) qui crée un formulaire d’inscription, le traite et enregistre l’utilisateur en base de données si le formulaire est valide.
class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        // createForm() associe le formulaire à cet objet.
        $form = $this->createForm(RegistrationFormType::class, $user); 
        // handleRequest() récupère les données de la requête et les transmet au formulaire.
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // On récupère le mot de passe en clair saisi par l’utilisateur.
            $plainPassword = $form->get('plainPassword')->getData();

            // hashPassword() hache le mot de passe en clair et le stocke dans l’objet User.
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            // persist() indique à Doctrine que ce nouvel objet doit être enregistré en base de données.
            $entityManager->persist($user);

            // flush() exécute l’insertion en base de données.
            $entityManager->flush();
            
            // Après l’inscription, on redirige l’utilisateur vers la page d’accueil.
            return $this->redirectToRoute('app_home');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
