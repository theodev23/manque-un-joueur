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
