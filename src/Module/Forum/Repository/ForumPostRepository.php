<?php

namespace App\Module\Forum\Repository;

use App\Module\Forum\Entity\ForumPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumPost>
 */
class ForumPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumPost::class);
    }

    /**
     * @return int Returns the total count of ForumPost entities
     */
    public function countAll(): int
    {
        return $this->count([]);
    }
}
