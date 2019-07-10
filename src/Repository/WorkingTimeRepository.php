<?php

namespace App\Repository;

use App\Entity\WorkingTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method WorkingTime|null find($id, $lockMode = null, $lockVersion = null)
 * @method WorkingTime|null findOneBy(array $criteria, array $orderBy = null)
 * @method WorkingTime[]    findAll()
 * @method WorkingTime[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WorkingTimeRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, WorkingTime::class);
    }

    public function getCountWTs()
    {
        return $this->_em->createQueryBuilder()
            ->select('COUNT(u)')
            ->from($this::getEntityName(), 'u')
            ->where('u.isValidate = 1')
            ->getQuery()
            ->setMaxResults(1)
            ->getSingleScalarResult();
    }

    public function findAllWithFields($fields = 'working_time')
    {
        return $this->_em->createQueryBuilder()
            ->select($fields)
            ->from($this::getEntityName(), 'working_time')
            ->getQuery()
            ->getResult();
    }

    public function getMostUsed($whereMostUsed)
    {
        $query = $this->_em->createQuery(
            'SELECT IDENTITY(wt.project) as id
            FROM App\Entity\WorkingTime wt
            WHERE wt.isValidate = 1
            AND wt.project IN ('.$whereMostUsed.')
            GROUP BY wt.project
            ORDER BY SUM(wt.spentTime) DESC'
        );

        // returns an array of Product objects
        return $query->execute();
    }
}
