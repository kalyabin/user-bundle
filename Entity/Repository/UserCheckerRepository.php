<?php

namespace UserBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use UserBundle\Entity\UserCheckerEntity;


/**
 * Репозиторий для кодов проверки.
 *
 * @package UserBundle\Entity\Repository
 */
class UserCheckerRepository extends EntityRepository
{
    /**
     * Найти код проверки по идентификатору
     *
     * @param integer $id
     *
     * @return UserCheckerEntity
     */
    public function findOneById($id)
    {
        return $this->createQueryBuilder('c')
            ->where('c.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
