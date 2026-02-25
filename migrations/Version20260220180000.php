<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Quiz schedule (planification type Google Calendar) pour les étudiants.
 */
final class Version20260220180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add quiz_schedule table for student quiz planning';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE quiz_schedule (
            id INT AUTO_INCREMENT NOT NULL,
            student_id INT NOT NULL,
            quiz_id INT NOT NULL,
            scheduled_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            status VARCHAR(20) NOT NULL,
            note LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_quiz_schedule_student (student_id),
            INDEX IDX_quiz_schedule_quiz (quiz_id),
            INDEX IDX_quiz_schedule_status (status),
            PRIMARY KEY(id),
            CONSTRAINT FK_quiz_schedule_student FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT FK_quiz_schedule_quiz FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE quiz_schedule');
    }
}
