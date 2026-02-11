<?php

namespace App\Module\Support\Repository;

use App\Module\Support\Entity\SupportResponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SupportResponseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupportResponse::class);
    }

    public function findByReclamation(int $reclamationId): array
    {
        return $this->createQueryBuilder('sr')
            ->where('sr.reclamation = :reclamationId')
            ->setParameter('reclamationId', $reclamationId)
            ->orderBy('sr.respondedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(SupportResponse $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SupportResponse $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}