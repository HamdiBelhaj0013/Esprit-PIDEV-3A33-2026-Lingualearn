<?php

namespace App\Module\Forum\Repository;

use App\Module\Forum\Entity\ForumReply;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumReply>
 */
class ForumReplyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumReply::class);
    }

    /**
     * @return int Returns the total count of ForumReply entities
     */
    public function countAll(): int
    {
        return $this->count([]);
    }
}
