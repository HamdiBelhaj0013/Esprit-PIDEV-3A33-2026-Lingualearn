<?php
// src/Module/Support/Service/FAQService.php

namespace App\Module\Support\Service;

use App\Module\Support\Entity\FAQ;
use App\Module\Support\Repository\FAQRepository;
use Doctrine\ORM\EntityManagerInterface;

class FAQService
{
    private EntityManagerInterface $entityManager;
    private FAQRepository $faqRepository;

    public function __construct(EntityManagerInterface $entityManager, FAQRepository $faqRepository)
    {
        $this->entityManager = $entityManager;
        $this->faqRepository = $faqRepository;
    }

    public function create(FAQ $faq): void
    {
        $this->entityManager->persist($faq);
        $this->entityManager->flush();
    }

    public function update(FAQ $faq): void
    {
        $this->entityManager->flush();
    }

    public function delete(FAQ $faq): void
    {
        $this->entityManager->remove($faq);
        $this->entityManager->flush();
    }

    public function find(int $id): ?FAQ
    {
        return $this->faqRepository->find($id);
    }

    public function findAll(): array
    {
        return $this->faqRepository->findAll();
    }

    public function findBySubject(?string $subject): array
    {
        if (!$subject) {
            return $this->findAll();
        }
        return $this->faqRepository->findBy(['subject' => $subject], ['submittedAt' => 'DESC']);
    }

    public function searchPaginated(?string $search, ?string $subject, string $sort = 'submittedAt', string $order = 'DESC', int $page = 1, int $limit = 10): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('f')->from(FAQ::class, 'f');

        if ($search) {
            $qb->andWhere('f.question LIKE :search OR f.answer LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($subject) {
            $qb->andWhere('f.subject = :subject')
               ->setParameter('subject', $subject);
        }

        $allowedSortFields = ['submittedAt', 'question', 'subject', 'category'];
        if (!in_array($sort, $allowedSortFields)) {
            $sort = 'submittedAt';
        }
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy('f.' . $sort, $order);

        // Count total
        $countQb = clone $qb;
        $countQb->select('COUNT(f.id)');
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        // Pagination
        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        $items = $qb->getQuery()->getResult();

        return [
            'items' => $items,
            'total' => $total,
            'pages' => (int) ceil($total / $limit),
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public function getFAQsBySubject(?string $subject): array
    {
        return $this->findBySubject($subject);
    }

    public function getSubjects(): array
    {
        $faqs = $this->findAll();
        $subjects = [];
        
        foreach ($faqs as $faq) {
            if ($faq->getSubject() && !in_array($faq->getSubject(), $subjects)) {
                $subjects[] = $faq->getSubject();
            }
        }
        
        return $subjects;
    }
}