<?php

namespace App\Module\Support\Repository;

use App\Module\Support\Entity\Reclamation;
use App\Module\UserManagement\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReclamationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reclamation::class);
    }

    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('r.submittedAt', 'DESC')
            ->getQuery()->getResult();
    }

    public function findByStatus(?string $status): array
    {
        $qb = $this->createQueryBuilder('r')->orderBy('r.submittedAt', 'DESC');
        if ($status) {
            $qb->andWhere('r.status = :status')->setParameter('status', $status);
        }
        return $qb->getQuery()->getResult();
    }

    public function save(Reclamation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) $this->getEntityManager()->flush();
    }

    public function remove(Reclamation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) $this->getEntityManager()->flush();
    }

    public function countByUserSince(User $user, \DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.user = :user')
            ->andWhere('r.submittedAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->getQuery()->getSingleScalarResult();
    }

    public function findOldestInWindow(User $user, \DateTimeInterface $since): ?Reclamation
    {
        return $this->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.submittedAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->orderBy('r.submittedAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    // ── Nouveaux pour SLA & Priorité ──
    public function findActiveTickets(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.status IN (:statuses)')
            ->setParameter('statuses', ['PENDING', 'IN_PROGRESS'])
            ->orderBy('r.priority', 'DESC')
            ->addOrderBy('r.submittedAt', 'ASC')
            ->getQuery()->getResult();
    }

    public function findLateTickets(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.isLate = true')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('statuses', ['PENDING', 'IN_PROGRESS'])
            ->orderBy('r.priority', 'DESC')
            ->getQuery()->getResult();
    }

    public function findByPriority(string $priority): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.priority = :priority')
            ->setParameter('priority', $priority)
            ->orderBy('r.submittedAt', 'ASC')
            ->getQuery()->getResult();
    }

    // ── Satisfaction ──
    public function getAverageSatisfaction(): float
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.satisfactionScore)')
            ->where('r.satisfactionScore IS NOT NULL')
            ->getQuery()->getSingleScalarResult();
        return round((float) $result, 2);
    }

    public function findRatedTickets(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.satisfactionScore IS NOT NULL')
            ->orderBy('r.satisfactionRatedAt', 'DESC')
            ->getQuery()->getResult();
    }

    public function getSatisfactionDistribution(): array
    {
        $results = $this->createQueryBuilder('r')
            ->select('r.satisfactionScore as score, COUNT(r.id) as count')
            ->where('r.satisfactionScore IS NOT NULL')
            ->groupBy('r.satisfactionScore')
            ->orderBy('r.satisfactionScore', 'ASC')
            ->getQuery()->getResult();

        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($results as $row) {
            $distribution[$row['score']] = (int) $row['count'];
        }
        return $distribution;
    }
}