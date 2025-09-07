<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250907132443 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // 0) Variables
        $table = 'task_list';
        $column = 'created_by_id';
        $fkName = 'FK_377B6C63B03A8386';
        $indexName = 'IDX_377B6C63B03A8386';

        // 1) Ajouter la colonne si elle n'existe pas
        $this->addSql("
        SET @col_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$table}'
              AND COLUMN_NAME = '{$column}'
        );
    ");
        $this->addSql("
        SET @stmt := IF(@col_exists = 0,
            'ALTER TABLE {$table} ADD {$column} INT DEFAULT NULL',
            'SELECT 1'
        );
    ");
        $this->addSql("PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;");

        // 2) Créer un 'system user' si inexistant
        $this->addSql("
        INSERT INTO user (email, roles, password, prenom, nom)
        SELECT 'system@syntask.local', '[]', '', 'System', 'User'
        FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM user WHERE email = 'system@syntask.local')
    ");

        // 3) Backfill: mettre created_by_id pour les lignes NULL
        $this->addSql("
        UPDATE {$table} tl
        LEFT JOIN user u ON u.id = tl.{$column}
        SET tl.{$column} = (SELECT id FROM user WHERE email = 'system@syntask.local' LIMIT 1)
        WHERE tl.{$column} IS NULL
    ");

        // 4) Rendre la colonne NOT NULL si elle existe et est encore nullable
        $this->addSql("
        SET @is_nullable := (
            SELECT IS_NULLABLE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$table}'
              AND COLUMN_NAME = '{$column}'
            LIMIT 1
        );
    ");
        $this->addSql("
        SET @stmt := IF(@is_nullable = 'YES',
            'ALTER TABLE {$table} MODIFY {$column} INT NOT NULL',
            'SELECT 1'
        );
    ");
        $this->addSql("PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;");

        // 5) Ajouter la FK si elle n'existe pas
        $this->addSql("
        SET @fk_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND CONSTRAINT_NAME = '{$fkName}'
        );
    ");
        $this->addSql("
        SET @stmt := IF(@fk_exists = 0,
            'ALTER TABLE {$table} ADD CONSTRAINT {$fkName} FOREIGN KEY ({$column}) REFERENCES user (id)',
            'SELECT 1'
        );
    ");
        $this->addSql("PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;");

        // 6) Ajouter l’index si non existant
        $this->addSql("
        SET @idx_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$table}'
              AND INDEX_NAME = '{$indexName}'
        );
    ");
        $this->addSql("
        SET @stmt := IF(@idx_exists = 0,
            'CREATE INDEX {$indexName} ON {$table} ({$column})',
            'SELECT 1'
        );
    ");
        $this->addSql("PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;");
    }

    public function down(Schema $schema): void
    {
        $table = 'task_list';
        $column = 'created_by_id';
        $fkName = 'FK_377B6C63B03A8386';
        $indexName = 'IDX_377B6C63B03A8386';

        // Drop FK si existe
        $this->addSql("
        SET @fk_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND CONSTRAINT_NAME = '{$fkName}'
        );
    ");
        $this->addSql("
        SET @stmt := IF(@fk_exists = 1,
            'ALTER TABLE {$table} DROP FOREIGN KEY {$fkName}',
            'SELECT 1'
        );
    ");
        $this->addSql("PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;");

        // Drop index si existe
        $this->addSql("
        SET @idx_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$table}'
              AND INDEX_NAME = '{$indexName}'
        );
    ");
        $this->addSql("
        SET @stmt := IF(@idx_exists = 1,
            'DROP INDEX {$indexName} ON {$table}',
            'SELECT 1'
        );
    ");
        $this->addSql("PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;");

        // Drop column si existe
        $this->addSql("
        SET @col_exists := (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$table}'
              AND COLUMN_NAME = '{$column}'
        );
    ");
        $this->addSql("
        SET @stmt := IF(@col_exists = 1,
            'ALTER TABLE {$table} DROP {$column}',
            'SELECT 1'
        );
    ");
        $this->addSql("PREPARE stmt FROM @stmt; EXECUTE stmt; DEALLOCATE PREPARE stmt;");
    }
}
