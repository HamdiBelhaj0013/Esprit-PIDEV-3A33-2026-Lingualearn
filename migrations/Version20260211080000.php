<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add quiz_id to exercice table and restore lesson table';
    }

    public function up(Schema $schema): void
    {
        // Add quiz_id to exercice
        $this->addSql('ALTER TABLE exercice ADD quiz_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE exercice ADD CONSTRAINT FK_E418C74D853A5168 FOREIGN KEY (quiz_id) REFERENCES quiz (id)');
        $this->addSql('CREATE INDEX IDX_E418C74D853A5168 ON exercice (quiz_id)');

        // Restore lesson table if it was dropped (it is still referenced by UserLessonStatus and has its own entity)
        $this->addSql('CREATE TABLE IF NOT EXISTS lesson (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Restore lesson_id in user_lesson_status if it was dropped
        // Check if column exists is not easy in pure SQL for migration without knowing the DB state, 
        // but based on Version20260210153048, it was dropped.
        $this->addSql('ALTER TABLE user_lesson_status ADD lesson_id INT NOT NULL');
        $this->addSql('ALTER TABLE user_lesson_status ADD CONSTRAINT FK_E618381BCDF80196 FOREIGN KEY (lesson_id) REFERENCES lesson (id)');
        $this->addSql('CREATE INDEX IDX_E618381BCDF80196 ON user_lesson_status (lesson_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_lesson_status DROP FOREIGN KEY FK_E618381BCDF80196');
        $this->addSql('DROP INDEX IDX_E618381BCDF80196 ON user_lesson_status');
        $this->addSql('ALTER TABLE user_lesson_status DROP lesson_id');
        
        $this->addSql('DROP TABLE lesson');

        $this->addSql('ALTER TABLE exercice DROP FOREIGN KEY FK_E418C74D853A5168');
        $this->addSql('DROP INDEX IDX_E418C74D853A5168 ON exercice');
        $this->addSql('ALTER TABLE exercice DROP quiz_id');
    }
}
