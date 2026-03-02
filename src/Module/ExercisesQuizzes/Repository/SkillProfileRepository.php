<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Repository;

use App\Module\ExercisesQuizzes\Entity\SkillProfile;
use App\Module\UserManagement\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SkillProfile> */
class SkillProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SkillProfile::class);
    }

    /** @return SkillProfile[] */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.skillCode', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserAndSkill(User $user, string $skillCode): ?SkillProfile
    {
        return $this->createQueryBuilder('s')
            ->where('s.user = :user')
            ->andWhere('s.skillCode = :skillCode')
            ->setParameter('user', $user)
            ->setParameter('skillCode', $skillCode)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(SkillProfile $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
