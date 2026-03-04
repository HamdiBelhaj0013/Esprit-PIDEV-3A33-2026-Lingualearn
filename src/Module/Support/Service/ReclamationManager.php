<?php

namespace App\Module\Support\Service;

use App\Module\Support\Entity\Reclamation;

class ReclamationManager
{
    /**
     * Valide les règles métier d'une réclamation.
     * Lance une InvalidArgumentException si une règle est violée.
     */
    public function validate(Reclamation $reclamation): bool
    {
        // Règle 1 : Le sujet est obligatoire
        if (empty($reclamation->getSubject())) {
            throw new \InvalidArgumentException('Le sujet de la réclamation est obligatoire.');
        }

        // Règle 2 : Le corps du message est obligatoire
        if (empty($reclamation->getMessageBody())) {
            throw new \InvalidArgumentException('Le message de la réclamation est obligatoire.');
        }

        // Règle 3 : La priorité doit être valide
        $prioritesValides = [
            Reclamation::PRIORITY_LOW,
            Reclamation::PRIORITY_MEDIUM,
            Reclamation::PRIORITY_HIGH,
            Reclamation::PRIORITY_URGENT,
        ];
        if (!in_array($reclamation->getPriority(), $prioritesValides, true)) {
            throw new \InvalidArgumentException(
                'La priorité est invalide. Valeurs acceptées : LOW, MEDIUM, HIGH, URGENT.'
            );
        }

        // Règle 4 : Le score de satisfaction doit être entre 1 et 5 (si renseigné)
        $score = $reclamation->getSatisfactionScore();
        if ($score !== null && ($score < 1 || $score > 5)) {
            throw new \InvalidArgumentException(
                'Le score de satisfaction doit être compris entre 1 et 5.'
            );
        }

        // Règle 5 : resolvedAt doit être après submittedAt (si les deux sont renseignés)
        if ($reclamation->getResolvedAt() !== null && $reclamation->getSubmittedAt() !== null) {
            if ($reclamation->getResolvedAt() <= $reclamation->getSubmittedAt()) {
                throw new \InvalidArgumentException(
                    'La date de résolution doit être postérieure à la date de soumission.'
                );
            }
        }

        return true;
    }

    /**
     * Vérifie si la réclamation peut être notée par l'utilisateur.
     */
    public function canRate(Reclamation $reclamation): bool
    {
        return $reclamation->canBeRated();
    }

    /**
     * Détecte automatiquement la priorité selon les mots-clés du message.
     */
    public function detectAndSetPriority(Reclamation $reclamation): string
    {
        $priority = $reclamation->detectPriority();
        $reclamation->setPriority($priority);
        return $priority;
    }
}