<?php

namespace App\Module\Support\Service;

use App\Module\Support\Entity\AuditLog;
use App\Module\Support\Entity\Reclamation;
use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class AuditLogService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function log(
        string       $action,
        string       $description,
        ?Reclamation $reclamation = null,
        ?User        $performedBy = null,
        ?string      $oldValue    = null,
        ?string      $newValue    = null,
        ?array       $metadata    = null
    ): void {
        $log = new AuditLog();
        $log->setAction($action);
        $log->setDescription($description);
        $log->setReclamation($reclamation);
        $log->setPerformedBy($performedBy);
        $log->setOldValue($oldValue);
        $log->setNewValue($newValue);
        $log->setMetadata($metadata);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    public function logCreated(Reclamation $reclamation, User $user): void
    {
        $this->log(
            AuditLog::ACTION_CREATED,
            "Réclamation #{$reclamation->getId()} créée par {$user->getFullName()} [Priorité: {$reclamation->getPriority()}]",
            $reclamation, $user, null, 'PENDING',
            ['subject' => $reclamation->getSubject(), 'priority' => $reclamation->getPriority()]
        );
    }

    public function logStatusChanged(Reclamation $reclamation, string $oldStatus, string $newStatus, User $admin): void
    {
        $this->log(
            AuditLog::ACTION_STATUS_CHANGED,
            "Statut changé de '{$oldStatus}' → '{$newStatus}' par {$admin->getFullName()}",
            $reclamation, $admin, $oldStatus, $newStatus
        );
    }

    public function logResponseAdded(Reclamation $reclamation, User $admin): void
    {
        $this->log(
            AuditLog::ACTION_RESPONSE_ADDED,
            "Réponse ajoutée par {$admin->getFullName()} sur la réclamation #{$reclamation->getId()}",
            $reclamation, $admin
        );
    }

    public function logUpdated(Reclamation $reclamation, User $user): void
    {
        $this->log(
            AuditLog::ACTION_UPDATED,
            "Réclamation #{$reclamation->getId()} modifiée par {$user->getFullName()}",
            $reclamation, $user
        );
    }

    public function logDeleted(int $reclamationId, User $user): void
    {
        $this->log(
            AuditLog::ACTION_DELETED,
            "Réclamation #{$reclamationId} supprimée par {$user->getFullName()}",
            null, $user, null, null,
            ['deleted_id' => $reclamationId]
        );
    }

    public function logUserBanned(User $bannedUser, ?User $performedBy = null): void
    {
        $this->log(
            AuditLog::ACTION_BANNED,
            "Utilisateur {$bannedUser->getFullName()} banni pour bad words",
            null, $performedBy ?? $bannedUser, null, null,
            ['banned_user_email' => $bannedUser->getEmail()]
        );
    }

    public function logSpamBlocked(User $user): void
    {
        $this->log(
            AuditLog::ACTION_SPAM_BLOCKED,
            "Tentative de spam bloquée pour {$user->getFullName()}",
            null, $user, null, null,
            ['email' => $user->getEmail()]
        );
    }

    public function logSatisfactionRated(Reclamation $reclamation, User $user, int $score): void
    {
        $this->log(
            'SATISFACTION_RATED',
            "Note {$score}/5 donnée par {$user->getFullName()} pour le ticket #{$reclamation->getId()}",
            $reclamation, $user, null, (string) $score,
            ['score' => $score]
        );
    }
}