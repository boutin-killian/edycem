<?php

namespace App\Repository;

use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Task|null find($id, $lockMode = null, $lockVersion = null)
 * @method Task|null findOneBy(array $criteria, array $orderBy = null)
 * @method Task[]    findAll()
 * @method Task[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Task::class);
    }

    public function getCountTasks()
    {
        return $this->_em->createQueryBuilder()
            ->select('COUNT(u)')
            ->from($this::getEntityName(), 'u')
            ->where('u.isValidate = 1')
            ->getQuery()
            ->setMaxResults(1)
            ->getSingleScalarResult();
    }

    public function findAllWithFields($fields = 'task', $where = '1 = 1')
    {
        return $this->_em->createQueryBuilder()
            ->select($fields)
            ->from($this::getEntityName(), 'task')
            ->from('App\Entity\Activity', 'a')
            ->where($where)
            ->getQuery()
            ->getResult();
    }
}
