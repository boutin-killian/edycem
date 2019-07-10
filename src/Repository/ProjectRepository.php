<?php

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Project|null find($id, $lockMode = null, $lockVersion = null)
 * @method Project|null findOneBy(array $criteria, array $orderBy = null)
 * @method Project[]    findAll()
 * @method Project[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function getCountProjects()
    {
        return $this->_em->createQueryBuilder()
            ->select('COUNT(u)')
            ->from($this::getEntityName(), 'u')
            ->where('u.isValidate = 1')
            ->getQuery()
            ->setMaxResults(1)
            ->getSingleScalarResult();
    }

    public function findAllWithFields($fields = 'project', $where = '1 = 1')
    {
        return $this->_em->createQueryBuilder()
            ->select($fields)
            ->from($this::getEntityName(), 'project')
            ->from('App\Entity\Job', 'j')
            ->where($where)
            ->getQuery()
            ->getResult();
    }

    public function findByWithFields($fields = 'project.id', $limit = 8)
    {
        $lastMonth = new \DateTime();
        $lastMonth->format('Y-m-d H:i:s');
        $lastMonth->modify( 'previous month' );
        return $this->_em->createQueryBuilder()
            ->select($fields)
            ->from($this::getEntityName(), 'project')
            ->where('project.created_at > :lastMonth')
            ->andWhere('project.isValidate = 1')
            ->setParameter('lastMonth', $lastMonth)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getLastProject($userId)
    {
        $query = $this->_em->createQuery(
            'SELECT p.id
            FROM App\Entity\WorkingTime wt
            LEFT JOIN wt.project p
            WHERE wt.user = :user_id
            AND p.isValidate = 1
            ORDER BY wt.id DESC'
        )
            ->setParameter('user_id', $userId)
            ->setMaxResults(1);

        // returns an array of Product objects
        return $query->execute();
    }

    public function getLastCreatedProject()
    {
        $query = $this->_em->createQuery(
            'SELECT p.id
            FROM App\Entity\Project p
            WHERE p.isValidate = 1
            ORDER BY p.created_at DESC'
        )
            ->setMaxResults(1);

        // returns an array of Product objects
        return $query->execute();
    }
}
