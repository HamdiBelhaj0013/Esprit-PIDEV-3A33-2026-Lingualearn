<?php

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
     * @return int Returns the total count of Lesson entities
     */
    public function countAll(): int
    {
        return $this->count([]);
    }
}
