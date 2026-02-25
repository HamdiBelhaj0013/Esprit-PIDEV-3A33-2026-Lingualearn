<?php

namespace App\Module\Support\Service;

use App\Module\Support\Entity\Reclamation;
use App\Module\Support\Repository\ReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;

class SlaService
{
    public function __construct(
        private ReclamationRepository  $reclamationRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * À appeler via une commande cron ou un subscriber
     * Marque tous les tickets en retard
     */
    public function checkAndMarkLateTickets(): int
    {
        $tickets = $this->reclamationRepository->findActiveTickets();
        $count   = 0;

        foreach ($tickets as $ticket) {
            if (!$ticket->isLate() && $ticket->getSlaDeadline() && new \DateTime() > $ticket->getSlaDeadline()) {
                $ticket->setIsLate(true);
                $count++;
            }
        }

        if ($count > 0) {
            $this->entityManager->flush();
        }

        return $count;
    }

    /**
     * Statistiques SLA pour le dashboard
     */
    public function getSlaStats(): array
    {
        $all     = $this->reclamationRepository->findAll();
        $late    = array_filter($all, fn($r) => $r->isLate());
        $onTime  = array_filter($all, fn($r) => !$r->isLate() && in_array($r->getStatus(), ['RESOLVED', 'CLOSED']));
        $active  = array_filter($all, fn($r) => in_array($r->getStatus(), ['PENDING', 'IN_PROGRESS']));

        $byPriority = [
            'URGENT' => count(array_filter($all, fn($r) => $r->getPriority() === 'URGENT')),
            'HIGH'   => count(array_filter($all, fn($r) => $r->getPriority() === 'HIGH')),
            'MEDIUM' => count(array_filter($all, fn($r) => $r->getPriority() === 'MEDIUM')),
            'LOW'    => count(array_filter($all, fn($r) => $r->getPriority() === 'LOW')),
        ];

        $slaRate = count($all) > 0
            ? round((count($onTime) / count($all)) * 100, 1)
            : 0;

        return [
            'total_late'   => count($late),
            'total_on_time'=> count($onTime),
            'total_active' => count($active),
            'by_priority'  => $byPriority,
            'sla_rate'     => $slaRate,
            'late_tickets' => array_values($late),
        ];
    }

    /**
     * Retourne les tickets urgents ou en retard pour alertes
     */
    public function getCriticalTickets(): array
    {
        $all = $this->reclamationRepository->findAll();
        return array_values(array_filter($all, fn($r) =>
            ($r->getPriority() === 'URGENT' || $r->isLate())
            && in_array($r->getStatus(), ['PENDING', 'IN_PROGRESS'])
        ));
    }
}