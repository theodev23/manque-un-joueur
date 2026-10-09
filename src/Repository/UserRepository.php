<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

// La classe étend ServiceEntityRepository, qui fournit des méthodes de base pour interagir avec la base de données, comme find(), findAll(), findBy(), etc.
// Elle implémente PasswordUpgraderInterface, qui permet de mettre à jour le mot de passe haché d’un utilisateur lorsque le mot de passe est modifié ou que l’algorithme de hachage change. Cela garantit que les mots de passe des utilisateurs restent sécurisés.
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    // Cette méthode est appelée automatiquement par Symfony lorsque le mot de passe d’un utilisateur est mis à jour. Elle vérifie que l’utilisateur est bien une instance de User, puis met à jour le mot de passe haché et enregistre les modifications en base de données.
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
}
