<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Analyse de compétences : skills/difficulty sur exercice, time_spent sur attempt,
 * tables skill_profile et recommendation_session.
 */
final class Version20260224120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add skills/difficulty to exercice, time_spent_seconds to exercise_attempt, skill_profile and recommendation_session tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE exercice ADD skills JSON NOT NULL DEFAULT (\'[]\'), ADD difficulty SMALLINT NOT NULL DEFAULT 3');
        $this->addSql('ALTER TABLE exercise_attempt ADD time_spent_seconds INT DEFAULT NULL');

        $this->addSql('CREATE TABLE skill_profile (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            skill_code VARCHAR(100) NOT NULL,
            mastery SMALLINT NOT NULL DEFAULT 0,
            attempts_count INT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uq_skill_profile_user_skill (user_id, skill_code),
            INDEX idx_skill_profile_user (user_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_skill_profile_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE recommendation_session (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            weak_skill_codes JSON NOT NULL,
            recommended_exercise_ids JSON NOT NULL,
            ai_feedback LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_recommendation_session_user (user_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_recommendation_session_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE recommendation_session');
        $this->addSql('DROP TABLE skill_profile');
        $this->addSql('ALTER TABLE exercise_attempt DROP time_spent_seconds');
        $this->addSql('ALTER TABLE exercice DROP skills, DROP difficulty');
    }
}
