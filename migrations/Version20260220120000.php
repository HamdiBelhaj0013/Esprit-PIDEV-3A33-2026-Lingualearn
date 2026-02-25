<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add lesson_id to quiz for front /learn routes.
 */
final class Version20260220120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add lesson_id to quiz';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quiz ADD lesson_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE quiz ADD CONSTRAINT FK_A412FA92CDF80196 FOREIGN KEY (lesson_id) REFERENCES lesson (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_A412FA92CDF80196 ON quiz (lesson_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quiz DROP FOREIGN KEY FK_A412FA92CDF80196');
        $this->addSql('DROP INDEX IDX_A412FA92CDF80196 ON quiz');
        $this->addSql('ALTER TABLE quiz DROP lesson_id');
    }
}
