<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Métier Avancé #4 — Détection de doublons de questions
 * Ajout de la colonne `embedding` (JSON) dans test_question
 */
final class Version20260225AddEmbeddingToTestQuestion extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add embedding (JSON) column to test_question for AI duplicate detection';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE test_question ADD embedding JSON DEFAULT NULL COMMENT "(DC2Type:json)"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE test_question DROP COLUMN embedding');
    }
}
