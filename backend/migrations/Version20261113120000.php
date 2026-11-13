<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Имена индексов и ключей совпадают с генерируемыми Doctrine — иначе schema:validate не пройдёт.
 */
final class Version20261113120000 extends AbstractMigration
{
    private const string TABLE_OPTIONS = 'DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';

    public function getDescription(): string
    {
        return 'Доменная модель: пользователи, дети, кружки, филиалы, расписание, активности, заявки; refresh-токены';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Миграция рассчитана на MySQL.',
        );

        $this->addSql('CREATE TABLE users (
            id BINARY(16) NOT NULL,
            email VARCHAR(180) NOT NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(100) NOT NULL,
            roles JSON NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email),
            PRIMARY KEY (id)
        ) '.self::TABLE_OPTIONS);

        $this->addSql('CREATE TABLE child (
            id BINARY(16) NOT NULL,
            parent_id BINARY(16) NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) DEFAULT NULL,
            age SMALLINT NOT NULL,
            gender VARCHAR(8) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_22B35429727ACA70 (parent_id),
            PRIMARY KEY (id)
        ) '.self::TABLE_OPTIONS);

        $this->addSql('CREATE TABLE club (
            id BINARY(16) NOT NULL,
            owner_id BINARY(16) DEFAULT NULL,
            source_activity_id BINARY(16) DEFAULT NULL,
            slug VARCHAR(120) NOT NULL,
            name VARCHAR(100) NOT NULL,
            categories JSON NOT NULL,
            emoji VARCHAR(16) NOT NULL,
            color VARCHAR(7) NOT NULL,
            short_description VARCHAR(160) NOT NULL,
            full_description LONGTEXT NOT NULL,
            age_min SMALLINT NOT NULL,
            age_max SMALLINT NOT NULL,
            visit_type VARCHAR(16) NOT NULL,
            price_type VARCHAR(16) NOT NULL,
            price_amount INT NOT NULL,
            tags JSON NOT NULL,
            is_published TINYINT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_B8EE3872989D9B62 (slug),
            UNIQUE INDEX UNIQ_B8EE3872BDBF3036 (source_activity_id),
            INDEX IDX_B8EE38727E3C61F9 (owner_id),
            PRIMARY KEY (id)
        ) '.self::TABLE_OPTIONS);

        $this->addSql('CREATE TABLE branch (
            id BINARY(16) NOT NULL,
            club_id BINARY(16) NOT NULL,
            source_activity_branch_id BINARY(16) DEFAULT NULL,
            name VARCHAR(100) NOT NULL,
            address VARCHAR(255) NOT NULL,
            phone VARCHAR(32) DEFAULT NULL,
            schedule_note VARCHAR(255) DEFAULT NULL,
            position INT NOT NULL,
            INDEX IDX_BB861B1F61190A32 (club_id),
            INDEX IDX_BB861B1FB1940060 (source_activity_branch_id),
            PRIMARY KEY (id)
        ) '.self::TABLE_OPTIONS);

        $this->addSql('CREATE TABLE schedule_slot (
            id BINARY(16) NOT NULL,
            branch_id BINARY(16) DEFAULT NULL,
            activity_branch_id BINARY(16) DEFAULT NULL,
            day VARCHAR(2) NOT NULL,
            time_from TIME NOT NULL,
            time_to TIME NOT NULL,
            INDEX IDX_4C46003CDCD6CC49 (branch_id),
            INDEX IDX_4C46003C3BCEEBFE (activity_branch_id),
            PRIMARY KEY (id)
        ) '.self::TABLE_OPTIONS);

        $this->addSql('CREATE TABLE activity (
            id BINARY(16) NOT NULL,
            owner_id BINARY(16) NOT NULL,
            moderated_by_id BINARY(16) DEFAULT NULL,
            name VARCHAR(100) NOT NULL,
            category VARCHAR(16) NOT NULL,
            emoji VARCHAR(16) NOT NULL,
            description LONGTEXT NOT NULL,
            age_min SMALLINT NOT NULL,
            age_max SMALLINT NOT NULL,
            visit_type VARCHAR(16) NOT NULL,
            price_type VARCHAR(16) NOT NULL,
            price_amount INT NOT NULL,
            tags JSON NOT NULL,
            status VARCHAR(16) NOT NULL,
            moderation_comment LONGTEXT DEFAULT NULL,
            moderated_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_AC74095A7E3C61F9 (owner_id),
            INDEX IDX_AC74095A8EDA19B0 (moderated_by_id),
            INDEX idx_activity_status (status),
            PRIMARY KEY (id)
        ) '.self::TABLE_OPTIONS);

        $this->addSql('CREATE TABLE activity_branch (
            id BINARY(16) NOT NULL,
            activity_id BINARY(16) NOT NULL,
            name VARCHAR(100) NOT NULL,
            address VARCHAR(255) NOT NULL,
            phone VARCHAR(32) DEFAULT NULL,
            position INT NOT NULL,
            INDEX IDX_E9F0FB1F81C06096 (activity_id),
            PRIMARY KEY (id)
        ) '.self::TABLE_OPTIONS);

        $this->addSql('CREATE TABLE enrollment (
            id BINARY(16) NOT NULL,
            user_id BINARY(16) NOT NULL,
            club_id BINARY(16) NOT NULL,
            branch_id BINARY(16) DEFAULT NULL,
            child_id BINARY(16) DEFAULT NULL,
            status VARCHAR(16) NOT NULL,
            reject_reason VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            status_changed_at DATETIME NOT NULL,
            INDEX IDX_DBDCD7E1A76ED395 (user_id),
            INDEX IDX_DBDCD7E161190A32 (club_id),
            INDEX IDX_DBDCD7E1DCD6CC49 (branch_id),
            INDEX IDX_DBDCD7E1DD62C21B (child_id),
            INDEX idx_enrollment_club_status (club_id, status),
            PRIMARY KEY (id)
        ) '.self::TABLE_OPTIONS);

        $this->addSql('CREATE TABLE refresh_tokens (
            id INT AUTO_INCREMENT NOT NULL,
            refresh_token VARCHAR(128) NOT NULL,
            username VARCHAR(255) NOT NULL,
            valid DATETIME NOT NULL,
            family VARCHAR(32) DEFAULT NULL,
            family_valid DATETIME DEFAULT NULL,
            UNIQUE INDEX UNIQ_9BACE7E1C74F2195 (refresh_token),
            INDEX IDX_REFRESH_TOKENS_FAMILY (family),
            PRIMARY KEY (id)
        ) '.self::TABLE_OPTIONS);

        $this->addSql('ALTER TABLE child ADD CONSTRAINT FK_22B35429727ACA70 FOREIGN KEY (parent_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE club ADD CONSTRAINT FK_B8EE38727E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE club ADD CONSTRAINT FK_B8EE3872BDBF3036 FOREIGN KEY (source_activity_id) REFERENCES activity (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE branch ADD CONSTRAINT FK_BB861B1F61190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE branch ADD CONSTRAINT FK_BB861B1FB1940060 FOREIGN KEY (source_activity_branch_id) REFERENCES activity_branch (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE schedule_slot ADD CONSTRAINT FK_4C46003CDCD6CC49 FOREIGN KEY (branch_id) REFERENCES branch (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE schedule_slot ADD CONSTRAINT FK_4C46003C3BCEEBFE FOREIGN KEY (activity_branch_id) REFERENCES activity_branch (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A8EDA19B0 FOREIGN KEY (moderated_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE activity_branch ADD CONSTRAINT FK_E9F0FB1F81C06096 FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE enrollment ADD CONSTRAINT FK_DBDCD7E1A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE enrollment ADD CONSTRAINT FK_DBDCD7E161190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE enrollment ADD CONSTRAINT FK_DBDCD7E1DCD6CC49 FOREIGN KEY (branch_id) REFERENCES branch (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE enrollment ADD CONSTRAINT FK_DBDCD7E1DD62C21B FOREIGN KEY (child_id) REFERENCES child (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Миграция рассчитана на MySQL.',
        );

        $this->addSql('ALTER TABLE child DROP FOREIGN KEY FK_22B35429727ACA70');
        $this->addSql('ALTER TABLE club DROP FOREIGN KEY FK_B8EE38727E3C61F9');
        $this->addSql('ALTER TABLE club DROP FOREIGN KEY FK_B8EE3872BDBF3036');
        $this->addSql('ALTER TABLE branch DROP FOREIGN KEY FK_BB861B1F61190A32');
        $this->addSql('ALTER TABLE branch DROP FOREIGN KEY FK_BB861B1FB1940060');
        $this->addSql('ALTER TABLE schedule_slot DROP FOREIGN KEY FK_4C46003CDCD6CC49');
        $this->addSql('ALTER TABLE schedule_slot DROP FOREIGN KEY FK_4C46003C3BCEEBFE');
        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095A7E3C61F9');
        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095A8EDA19B0');
        $this->addSql('ALTER TABLE activity_branch DROP FOREIGN KEY FK_E9F0FB1F81C06096');
        $this->addSql('ALTER TABLE enrollment DROP FOREIGN KEY FK_DBDCD7E1A76ED395');
        $this->addSql('ALTER TABLE enrollment DROP FOREIGN KEY FK_DBDCD7E161190A32');
        $this->addSql('ALTER TABLE enrollment DROP FOREIGN KEY FK_DBDCD7E1DCD6CC49');
        $this->addSql('ALTER TABLE enrollment DROP FOREIGN KEY FK_DBDCD7E1DD62C21B');
        $this->addSql('DROP TABLE enrollment');
        $this->addSql('DROP TABLE schedule_slot');
        $this->addSql('DROP TABLE branch');
        $this->addSql('DROP TABLE club');
        $this->addSql('DROP TABLE activity_branch');
        $this->addSql('DROP TABLE activity');
        $this->addSql('DROP TABLE child');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE refresh_tokens');
    }

    /**
     * DDL в MySQL неявно фиксирует транзакцию.
     */
    public function isTransactional(): bool
    {
        return false;
    }
}
