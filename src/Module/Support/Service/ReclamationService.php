<?php
// src/Module/Support/Service/ReclamationService.php

namespace App\Module\Support\Service;

use App\Module\Support\Entity\Reclamation;
use App\Module\Support\Entity\SupportResponse;
use App\Module\Support\Enum\TicketStatus;
use App\Module\Support\Repository\ReclamationRepository;
use App\Module\Support\Repository\SupportResponseRepository;
use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ReclamationService
{
    private EntityManagerInterface $entityManager;
    private ReclamationRepository $reclamationRepository;
    private SupportResponseRepository $responseRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        ReclamationRepository $reclamationRepository,
        SupportResponseRepository $responseRepository
    ) {
        $this->entityManager = $entityManager;
        $this->reclamationRepository = $reclamationRepository;
        $this->responseRepository = $responseRepository;
    }

    // CRUD
    public function create(Reclamation $reclamation): void
    {
        $this->entityManager->persist($reclamation);
        $this->entityManager->flush();
    }

    public function update(Reclamation $reclamation): void
    {
        $this->entityManager->flush();
    }

    public function delete(Reclamation $reclamation): void
    {
        $this->entityManager->remove($reclamation);
        $this->entityManager->flush();
    }

    public function find(int $id): ?Reclamation
    {
        return $this->reclamationRepository->find($id);
    }

    public function findAll(): array
    {
        return $this->reclamationRepository->findAll();
    }

    // Méthodes spécifiques
    public function getUserReclamations(User $user): array
    {
        return $this->reclamationRepository->findBy(['user' => $user], ['submittedAt' => 'DESC']);
    }

    public function findByStatus(string $status): array
    {
        return $this->reclamationRepository->findBy(['status' => $status], ['submittedAt' => 'DESC']);
    }

    public function addResponse(Reclamation $reclamation, SupportResponse $response): void
    {
        $response->setReclamation($reclamation);
        $response->setRespondedAt(new \DateTime());
        
        $this->entityManager->persist($response);
        $this->entityManager->flush();
    }

    public function canModify(Reclamation $reclamation, ?User $user = null): bool
    {
        // When authentication is disabled, allow modification if the ticket is PENDING
        if ($user === null) {
            return $reclamation->getStatus() === TicketStatus::PENDING->value;
        }

        // If user provided, ensure owner and pending
        return $reclamation->getUser() && $reclamation->getUser()->getId() === $user->getId()
            && $reclamation->getStatus() === TicketStatus::PENDING->value;
    }

    public function searchPaginated(?string $search, ?string $status, ?User $user, string $sort = 'submittedAt', string $order = 'DESC', int $page = 1, int $limit = 10): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('r')->from(Reclamation::class, 'r');

        if ($search) {
            $qb->andWhere('r.subject LIKE :search OR r.messageBody LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($status) {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $status);
        }

        if ($user) {
            $qb->andWhere('r.user = :user')
               ->setParameter('user', $user);
        }

        $allowedSortFields = ['submittedAt', 'status', 'subject'];
        if (!in_array($sort, $allowedSortFields)) {
            $sort = 'submittedAt';
        }
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy('r.' . $sort, $order);

        // Count total
        $countQb = clone $qb;
        $countQb->select('COUNT(r.id)');
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
    public function getStatistics(): array
    {
        $all = $this->findAll();
        
        return [
            'total' => count($all),
            'pending' => count(array_filter($all, fn($r) => $r->getStatus() === TicketStatus::PENDING)),
            'in_progress' => count(array_filter($all, fn($r) => $r->getStatus() === TicketStatus::IN_PROGRESS)),
            'resolved' => count(array_filter($all, fn($r) => $r->getStatus() === TicketStatus::RESOLVED)),
            'closed' => count(array_filter($all, fn($r) => $r->getStatus() === TicketStatus::CLOSED)),
        ];
    }
}