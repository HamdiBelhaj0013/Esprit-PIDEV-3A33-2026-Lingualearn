<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260210153048 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove lesson table and its relation, update quiz and user_lesson_status tables';
    }

    public function up(Schema $schema): void
    {
        // 1️⃣ Supprimer d'abord la contrainte de clé étrangère
        $this->addSql('ALTER TABLE user_lesson_status DROP FOREIGN KEY FK_E618381BCDF80196');

        // 2️⃣ Supprimer l’index lié à la clé étrangère
        $this->addSql('DROP INDEX IDX_E618381BCDF80196 ON user_lesson_status');

        // 3️⃣ Supprimer la colonne lesson_id
        $this->addSql('ALTER TABLE user_lesson_status DROP lesson_id');

        // 4️⃣ Maintenant on peut supprimer la table lesson
        $this->addSql('DROP TABLE lesson');

        // 5️⃣ Modifier la table quiz
        $this->addSql('ALTER TABLE quiz ADD created_at DATETIME DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, CHANGE description description LONGTEXT DEFAULT NULL');

        // 6️⃣ Modifier completed_at
        $this->addSql('ALTER TABLE user_lesson_status CHANGE completed_at completed_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Recréer la table lesson
        $this->addSql('CREATE TABLE lesson (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Annuler les modifications de quiz
        $this->addSql('ALTER TABLE quiz DROP created_at, DROP updated_at, CHANGE description description TEXT DEFAULT NULL');

        // Recréer la colonne lesson_id
        $this->addSql('ALTER TABLE user_lesson_status ADD lesson_id INT NOT NULL, CHANGE completed_at completed_at DATETIME NOT NULL');

        // Remettre la clé étrangère
        $this->addSql('ALTER TABLE user_lesson_status ADD CONSTRAINT FK_E618381BCDF80196 FOREIGN KEY (lesson_id) REFERENCES lesson (id)');

        // Recréer l’index
        $this->addSql('CREATE INDEX IDX_E618381BCDF80196 ON user_lesson_status (lesson_id)');
    }
}
