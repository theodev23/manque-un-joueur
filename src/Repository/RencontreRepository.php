<?php

namespace App\Repository;

use App\Entity\Rencontre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

// RencontreRepository est un dépôt de données pour l’entité Rencontre. Il fournit des méthodes pour interagir avec la base de données, comme findRencontresAVenir() qui récupère les rencontres à venir, éventuellement filtrées par ville.
// Cette classe hérite de ServiceEntityRepository, qui fournit des méthodes de base pour interagir avec la base de données, comme find(), findAll(), findBy(), etc.
class RencontreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rencontre::class);
    }

    // Le QueryBuilder permet de construire une requête en utilisant les propriétés des entités.
    // On donne l'alias r à l'entité rencontre.
    // string $ville = '' rend le filtre facultatif.
    // setParameter() transmet la valeur séparément de la requête, pour éviter les injections SQL.
    public function findRencontresAVenir(string $ville = ''): array
    {
        $queryBuilder = $this->createQueryBuilder('r')
            ->andWhere('r.dateHeure > :maintenant')
            ->andWhere('r.annulee = :annulee')
            ->setParameter('maintenant', new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->setParameter('annulee', false)
            ->orderBy('r.dateHeure', 'ASC');

        $ville = trim($ville);

        if ($ville !== '') {
            $queryBuilder
                ->andWhere('LOWER(r.ville) = LOWER(:ville)')
                ->setParameter('ville', $ville);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }
}
