<?php

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
     * @return int Returns the total count of Course entities
     */
    public function countAll(): int
    {
        return $this->count([]);
    }
}
