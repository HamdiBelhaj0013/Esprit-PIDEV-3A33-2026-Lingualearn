<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute last_quiz_score à user_lesson_status (dernier score affiché sur /learn/practice).
 */
final class Version20260220100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add last_quiz_score to user_lesson_status';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_lesson_status ADD last_quiz_score INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_lesson_status DROP last_quiz_score');
    }
}
