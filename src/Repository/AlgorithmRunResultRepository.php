<?php

namespace App\Repository;

use App\Entity\AlgorithmRunResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method AlgorithmRunResult|null find($id, $lockMode = null, $lockVersion = null)
 * @method AlgorithmRunResult|null findOneBy(array $criteria, array $orderBy = null)
 * @method AlgorithmRunResult[]    findAll()
 * @method AlgorithmRunResult[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AlgorithmRunResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlgorithmRunResult::class);
    }

    // /**
    //  * @return AlgorithmRunResult[] Returns an array of AlgorithmRunResult objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?AlgorithmRunResult
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
