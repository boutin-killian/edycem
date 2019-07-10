<?php

namespace App\Repository;

use App\Entity\Job;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Job|null find($id, $lockMode = null, $lockVersion = null)
 * @method Job|null findOneBy(array $criteria, array $orderBy = null)
 * @method Job[]    findAll()
 * @method Job[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class JobRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Job::class);
    }

    public function getCountJobs()
    {
        return $this->_em->createQueryBuilder()
            ->select('COUNT(u)')
            ->from($this::getEntityName(), 'u')
            ->getQuery()
            ->setMaxResults(1)
            ->getSingleScalarResult();
    }

    public function findAllWithFields($fields = 'job')
    {
        return $this->_em->createQueryBuilder()
            ->select($fields)
            ->from($this::getEntityName(), 'job')
            ->getQuery()
            ->getResult();
    }
}
