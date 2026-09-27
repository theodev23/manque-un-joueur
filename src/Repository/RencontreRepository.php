<?php

namespace App\Repository;

use App\Entity\Rencontre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rencontre>
 */
class RencontreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rencontre::class);
    }

    // Le QueryBuilder permet de construire une requête en utilisant les propriétés des entités.
    // On donne l'alias r à l'entité rencontre.
    public function findRencontresAVenir(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.dateHeure > :maintenant')
            ->setParameter('maintenant', new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->orderBy('r.dateHeure', 'ASC')
            ->getQuery()
            ->getResult();
    }


//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Rencontre
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
