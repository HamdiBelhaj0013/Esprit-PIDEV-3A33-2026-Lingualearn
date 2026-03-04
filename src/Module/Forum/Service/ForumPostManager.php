<?php

namespace App\Module\Forum\Service;

use App\Module\Forum\Entity\ForumPost;
use App\Module\Forum\Entity\ForumReply;

/**
 * Service métier du module Forum.
 * Centralise les règles de validation des ForumPost et ForumReply.
 */
class ForumPostManager
{
    // =========================================================================
    // RÈGLES MÉTIER — ForumPost
    // =========================================================================

    /**
     * Règle 1 : Le titre doit avoir entre 5 et 255 caractères.
     * Règle 2 : Le contenu doit avoir au moins 20 caractères.
     * Règle 3 : Le platformLanguageId doit être un entier positif valide (1-5).
     * Règle 4 : Un post désactivé ne peut pas recevoir de réponses.
     *
     * @throws \InvalidArgumentException si une règle est violée
     */
    public function validatePost(ForumPost $post): bool
    {
        $title   = trim((string) $post->getTitle());
        $content = trim((string) $post->getContent());
        $langId  = $post->getPlatformLanguageId();

        if (strlen($title) < 5) {
            throw new \InvalidArgumentException('Le titre doit contenir au moins 5 caractères.');
        }

        if (strlen($title) > 255) {
            throw new \InvalidArgumentException('Le titre ne peut pas dépasser 255 caractères.');
        }

        if (strlen($content) < 20) {
            throw new \InvalidArgumentException('Le contenu doit contenir au moins 20 caractères.');
        }

        if ($langId === null || $langId <= 0 || $langId > 5) {
            throw new \InvalidArgumentException('La langue sélectionnée est invalide.');
        }

        return true;
    }

    /**
     * Règle : Un post inactif ne peut pas être affiché publiquement.
     *
     * @throws \RuntimeException si le post est inactif
     */
    public function assertPostIsPublishable(ForumPost $post): bool
    {
        if (!$post->isActive()) {
            throw new \RuntimeException('Ce post est inactif et ne peut pas être publié.');
        }

        return true;
    }

    /**
     * Règle : Le viewCount ne peut jamais être négatif.
     *
     * @throws \RuntimeException si le viewCount résulterait en négatif
     */
    public function incrementView(ForumPost $post): void
    {
        if ($post->getViewCount() < 0) {
            throw new \RuntimeException('Le compteur de vues ne peut pas être négatif.');
        }

        $post->incrementViewCount();
    }

    // =========================================================================
    // RÈGLES MÉTIER — ForumReply
    // =========================================================================

    /**
     * Règle 1 : Le contenu de la réponse doit avoir entre 10 et 5000 caractères.
     * Règle 2 : La réponse doit être liée à un post actif.
     * Règle 3 : Une réponse inactive ne peut pas être marquée comme meilleure réponse.
     *
     * @throws \InvalidArgumentException si une règle est violée
     */
    public function validateReply(ForumReply $reply): bool
    {
        $content = trim((string) $reply->getContent());

        if (strlen($content) < 10) {
            throw new \InvalidArgumentException('La réponse doit contenir au moins 10 caractères.');
        }

        if (strlen($content) > 5000) {
            throw new \InvalidArgumentException('La réponse ne peut pas dépasser 5000 caractères.');
        }

        $post = $reply->getPost();

        if ($post === null) {
            throw new \InvalidArgumentException('La réponse doit être associée à un post.');
        }

        if (!$post->isActive()) {
            throw new \InvalidArgumentException('Impossible de répondre à un post inactif.');
        }

        return true;
    }

    /**
     * Règle : Une réponse inactive ne peut pas être marquée meilleure réponse.
     *
     * @throws \RuntimeException si la réponse est inactive
     */
    public function markAsBestAnswer(ForumReply $reply): void
    {
        if (!$reply->isActive()) {
            throw new \RuntimeException('Une réponse inactive ne peut pas être marquée comme meilleure réponse.');
        }

        $reply->setIsBestAnswer(true);
    }

    /**
     * Règle : Le type de réaction doit être parmi les valeurs autorisées.
     *
     * @throws \InvalidArgumentException si le type est invalide
     */
    public function validateReactionType(string $type): bool
    {
        $allowed = ['like', 'dislike', 'useful', 'funny'];

        if (!in_array($type, $allowed, true)) {
            throw new \InvalidArgumentException(
                sprintf('Type de réaction invalide : "%s". Valeurs autorisées : %s', $type, implode(', ', $allowed))
            );
        }

        return true;
    }
}