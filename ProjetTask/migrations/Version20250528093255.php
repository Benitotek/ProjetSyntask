<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250528093255 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE project (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(30) NOT NULL, statut JSON NOT NULL, date_creation DATETIME NOT NULL, date_maj DATETIME NOT NULL, date_butoir DATETIME NOT NULL, date_reelle DATETIME NOT NULL, description VARCHAR(255) NOT NULL, reference VARCHAR(50) NOT NULL, budget NUMERIC(8, 2) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE project_managers (project_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_94076BDD166D1F9C (project_id), INDEX IDX_94076BDDA76ED395 (user_id), PRIMARY KEY(project_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project_managers ADD CONSTRAINT FK_94076BDD166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project_managers ADD CONSTRAINT FK_94076BDDA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user CHANGE statut statut JSON NOT NULL, CHANGE role role JSON NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE project_managers DROP FOREIGN KEY FK_94076BDD166D1F9C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project_managers DROP FOREIGN KEY FK_94076BDDA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE project
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE project_managers
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user CHANGE statut statut LONGTEXT NOT NULL COMMENT '(DC2Type:array)', CHANGE role role LONGTEXT NOT NULL COMMENT '(DC2Type:array)'
        SQL);
    }
}
