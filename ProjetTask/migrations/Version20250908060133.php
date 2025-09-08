<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250908060133 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }
    public function up(Schema $schema): void
    {
        // Harmoniser la FK sur tags.user_id avec ON DELETE SET NULL
        $this->addSql("
        SET @has_fk := (
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND CONSTRAINT_NAME = 'FK_6FBC9426A76ED395'
        );
    ");
        // On drop/recreate systématiquement la contrainte pour garantir ON DELETE SET NULL
        $this->addSql("
        SET @stmt := IF(@has_fk = 1,
            'ALTER TABLE tags DROP FOREIGN KEY FK_6FBC9426A76ED395',
            'SELECT 1'
        );
    ");
        $this->addSql('PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;');

        $this->addSql("
        ALTER TABLE tags
        ADD CONSTRAINT FK_6FBC9426A76ED395
        FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL
    ");

        // Harmoniser la FK task.created_by_id (ici on la retire si Doctrine ne la souhaite plus)
        $this->addSql("
        SET @has_task_fk := (
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND CONSTRAINT_NAME = 'FK_527EDB25B03A8386'
        );
    ");
        $this->addSql("
        SET @stmt := IF(@has_task_fk = 1,
            'ALTER TABLE task DROP FOREIGN KEY FK_527EDB25B03A8386',
            'SELECT 1'
        );
    ");
        $this->addSql('PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;');

        // Supprimer l'index si présent (Doctrine le demande)
        $this->addSql("
        SET @has_task_idx := (
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'task'
              AND INDEX_NAME = 'IDX_TASK_CREATED_BY_ID'
        );
    ");
        $this->addSql("
        SET @stmt := IF(@has_task_idx = 1,
            'DROP INDEX IDX_TASK_CREATED_BY_ID ON task',
            'SELECT 1'
        );
    ");
        $this->addSql('PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;');
    }

    public function down(Schema $schema): void
    {
        // Recréer l'index si tu veux le restaurer
        $this->addSql("
        SET @has_task_idx := (
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'task'
              AND INDEX_NAME = 'IDX_TASK_CREATED_BY_ID'
        );
    ");
        $this->addSql("
        SET @stmt := IF(@has_task_idx = 0,
            'CREATE INDEX IDX_TASK_CREATED_BY_ID ON task (created_by_id)',
            'SELECT 1'
        );
    ");
        $this->addSql('PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;');

        // Recréer la FK task si tu veux (optionnel)
        $this->addSql("
        ALTER TABLE task
        ADD CONSTRAINT FK_527EDB25B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)
    ");

        // Revenir à une FK tags sans ON DELETE SET NULL (si besoin)
        $this->addSql("
        SET @has_fk := (
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND CONSTRAINT_NAME = 'FK_6FBC9426A76ED395'
        );
    ");
        $this->addSql("
        SET @stmt := IF(@has_fk = 1,
            'ALTER TABLE tags DROP FOREIGN KEY FK_6FBC9426A76ED395',
            'SELECT 1'
        );
    ");
        $this->addSql('PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;');

        $this->addSql("
        ALTER TABLE tags
        ADD CONSTRAINT FK_6FBC9426A76ED395
        FOREIGN KEY (user_id) REFERENCES user (id)
    ");
    }
}
