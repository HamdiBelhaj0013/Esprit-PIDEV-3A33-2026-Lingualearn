<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260219092348 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE exercice CHANGE options options JSON NOT NULL');
        $this->addSql('ALTER TABLE faq CHANGE subject subject VARCHAR(50) DEFAULT NULL, CHANGE category category VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE learning_stats CHANGE last_study_session last_study_session DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE lesson CHANGE vocabulary_data vocabulary_data JSON NOT NULL, CHANGE grammar_data grammar_data JSON NOT NULL');
        $this->addSql('ALTER TABLE mock_test CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE notifications CHANGE read_at read_at DATETIME DEFAULT NULL, CHANGE metadata metadata JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE quiz CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE test_question CHANGE options options JSON NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user_lesson_status CHANGE completed_at completed_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD is_verified TINYINT DEFAULT 0 NOT NULL, ADD email_verification_token VARCHAR(100) DEFAULT NULL, ADD email_verification_token_expires_at DATETIME DEFAULT NULL, ADD password_reset_token VARCHAR(100) DEFAULT NULL, ADD password_reset_token_expires_at DATETIME DEFAULT NULL, CHANGE roles roles JSON NOT NULL, CHANGE subscription_expiry subscription_expiry DATETIME DEFAULT NULL, CHANGE last_payment_status last_payment_status VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE exercice CHANGE options options LONGTEXT NOT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE faq CHANGE subject subject VARCHAR(50) DEFAULT \'NULL\', CHANGE category category VARCHAR(50) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE learning_stats CHANGE last_study_session last_study_session DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE lesson CHANGE vocabulary_data vocabulary_data LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE grammar_data grammar_data LONGTEXT NOT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE mock_test CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE notifications CHANGE read_at read_at DATETIME DEFAULT \'NULL\', CHANGE metadata metadata LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE quiz CHANGE created_at created_at DATETIME DEFAULT \'NULL\', CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE test_question CHANGE options options LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE updated_at updated_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE users DROP is_verified, DROP email_verification_token, DROP email_verification_token_expires_at, DROP password_reset_token, DROP password_reset_token_expires_at, CHANGE roles roles LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE subscription_expiry subscription_expiry DATETIME DEFAULT \'NULL\', CHANGE last_payment_status last_payment_status VARCHAR(50) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE user_lesson_status CHANGE completed_at completed_at DATETIME DEFAULT \'NULL\'');
    }
}
