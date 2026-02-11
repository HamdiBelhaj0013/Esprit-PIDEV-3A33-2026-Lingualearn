<?php

declare(strict_types=1);

namespace App\Module\PedagogicalContent\Repository;

use App\Module\PedagogicalContent\Entity\Course;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Course>
 */
class CourseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Course::class);
    }

    /**
     * @return Course[] Returns an array of Course objects
     */
    public function findByLanguage(int $languageId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.platformLanguage = :languageId')
            ->setParameter('languageId', $languageId)
            ->orderBy('c.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Course[] Returns published courses
     */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->setParameter('status', 'Published')
            ->orderBy('c.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}