<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add difficulty and skill_codes to quiz for display and skill analysis.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quiz ADD difficulty SMALLINT DEFAULT 3 NOT NULL, ADD skill_codes JSON DEFAULT \'[]\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quiz DROP difficulty, DROP skill_codes');
    }
}
