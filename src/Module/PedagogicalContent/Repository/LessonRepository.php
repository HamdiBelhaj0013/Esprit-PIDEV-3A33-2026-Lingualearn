<?php

declare(strict_types=1);

namespace App\Module\PedagogicalContent\Repository;

use App\Module\PedagogicalContent\Entity\Lesson;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lesson>
 */
class LessonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lesson::class);
    }

    /**
     * @return Lesson[] Returns an array of Lesson objects for a course
     */
    public function findByCourse(int $courseId): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.course = :courseId')
            ->setParameter('courseId', $courseId)
            ->orderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}