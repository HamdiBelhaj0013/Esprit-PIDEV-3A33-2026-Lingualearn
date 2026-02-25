<?php

namespace App\Module\Support\EventSubscriber;

use App\Module\Support\Entity\Reclamation;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\GuardEvent;

class TicketWorkflowSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            // Guard : empêche certaines transitions
            'workflow.support_ticket.guard.resolve'          => 'guardResolve',
            // Completed : actions après chaque transition
            'workflow.support_ticket.completed.resolve'      => 'onResolved',
            'workflow.support_ticket.completed.close'        => 'onClosed',
            'workflow.support_ticket.completed.reopen'       => 'onReopened',
        ];
    }

    // ── Empêche de résoudre un ticket sans réponse ──
    public function guardResolve(GuardEvent $event): void
    {
        /** @var Reclamation $reclamation */
        $reclamation = $event->getSubject();

        if ($reclamation->getResponses()->isEmpty()) {
            $event->setBlocked(true, 'Impossible de résoudre un ticket sans réponse.');
        }
    }

    // ── Actions après résolution ──
    public function onResolved(CompletedEvent $event): void
    {
        /** @var Reclamation $reclamation */
        $reclamation = $event->getSubject();
        // resolvedAt est déjà géré dans setStatus()
    }

    // ── Actions après fermeture ──
    public function onClosed(CompletedEvent $event): void
    {
        /** @var Reclamation $reclamation */
        $reclamation = $event->getSubject();
        // SLA stop
        $reclamation->setIsLate(false);
    }

    // ── Actions après réouverture ──
    public function onReopened(CompletedEvent $event): void
    {
        /** @var Reclamation $reclamation */
        $reclamation = $event->getSubject();
        // Recalcule le SLA
        $reclamation->calculateSlaDeadline();
    }
}