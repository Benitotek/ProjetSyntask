<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250529085338 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE task_list (id INT AUTO_INCREMENT NOT NULL, project_id INT DEFAULT NULL, nom VARCHAR(30) NOT NULL, description VARCHAR(255) NOT NULL, position DOUBLE PRECISION NOT NULL, INDEX IDX_377B6C63166D1F9C (project_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE task_list ADD CONSTRAINT FK_377B6C63166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE task ADD task_list_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE task ADD CONSTRAINT FK_527EDB25224F3C61 FOREIGN KEY (task_list_id) REFERENCES task_list (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_527EDB25224F3C61 ON task (task_list_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE task DROP FOREIGN KEY FK_527EDB25224F3C61
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE task_list DROP FOREIGN KEY FK_377B6C63166D1F9C
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE task_list
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_527EDB25224F3C61 ON task
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE task DROP task_list_id
        SQL);
    }
}
