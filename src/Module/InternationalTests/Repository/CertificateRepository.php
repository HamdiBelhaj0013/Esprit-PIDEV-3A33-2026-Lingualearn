<?php

namespace App\Module\InternationalTests\Repository;

use App\Module\InternationalTests\Entity\Certificate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CertificateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Certificate::class);
    }

    public function findByUniqueCode(string $code): ?Certificate
    {
        return $this->findOneBy(['uniqueCode' => $code]);
    }

    public function findByUserAndLanguage(int $userId, int $languageId): ?Certificate
    {
        return $this->findOneBy([
            'user' => $userId,
            'language' => $languageId
        ]);
    }
}

