<?php

declare(strict_types=1);

namespace App\Module\PedagogicalContent\Repository;

use App\Module\PedagogicalContent\Entity\PlatformLanguage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlatformLanguage>
 */
class PlatformLanguageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlatformLanguage::class);
    }

    /**
     * @return PlatformLanguage[] Returns enabled languages
     */
    public function findEnabled(): array
    {
        return $this->createQueryBuilder('pl')
            ->andWhere('pl.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('pl.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}