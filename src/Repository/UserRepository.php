<?php

namespace App\Repository;

use App\Entity\User;

/**
 * UserRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends \Doctrine\ORM\EntityRepository
{
    public function getCountUsers()
    {
        return $this->_em->createQueryBuilder()
            ->select('COUNT(u)')
            ->from($this::getEntityName(), 'u')
            ->where('u.roles LIKE :roleUser')
            ->orWhere('u.roles NOT LIKE :roleOther')
            ->setParameter('roleUser', '%ROLE_USER%')
            ->setParameter('roleOther', '%ROLE_%')
            ->getQuery()
            ->setMaxResults(1)
            ->getSingleScalarResult();
    }
}