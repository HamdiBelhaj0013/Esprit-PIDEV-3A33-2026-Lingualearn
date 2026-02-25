<?php

namespace App\Module\Support\Repository;

use App\Module\Support\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function findRecent(int $limit = 50): array
    {
        return $this->findBy([], ['createdAt' => 'DESC'], $limit);
    }

    public function countByAction(string $action): int
    {
        return $this->count(['action' => $action]);
    }
}