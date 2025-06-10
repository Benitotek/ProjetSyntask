<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250610194052 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE project_user (project_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_B4021E51166D1F9C (project_id), INDEX IDX_B4021E51A76ED395 (user_id), PRIMARY KEY(project_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project_user ADD CONSTRAINT FK_B4021E51166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project_user ADD CONSTRAINT FK_B4021E51A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project_managers DROP FOREIGN KEY FK_94076BDD166D1F9C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project_managers DROP FOREIGN KEY FK_94076BDDA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE project_managers
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project ADD chef_de_projet_id INT DEFAULT NULL, CHANGE statut statut VARCHAR(20) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE2C9E1458 FOREIGN KEY (chef_de_projet_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_2FB3D0EE2C9E1458 ON project (chef_de_projet_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE task ADD task_list_id INT DEFAULT NULL, CHANGE title title VARCHAR(100) NOT NULL, CHANGE description description VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE task ADD CONSTRAINT FK_527EDB25224F3C61 FOREIGN KEY (task_list_id) REFERENCES task_list (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_527EDB25224F3C61 ON task (task_list_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE task_list ADD CONSTRAINT FK_377B6C63166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user ADD is_verified TINYINT(1) NOT NULL, CHANGE statut statut VARCHAR(20) NOT NULL, CHANGE email email VARCHAR(180) NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE project_managers (project_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_94076BDD166D1F9C (project_id), INDEX IDX_94076BDDA76ED395 (user_id), PRIMARY KEY(project_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project_managers ADD CONSTRAINT FK_94076BDD166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON UPDATE NO ACTION ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project_managers ADD CONSTRAINT FK_94076BDDA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project_user DROP FOREIGN KEY FK_B4021E51166D1F9C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project_user DROP FOREIGN KEY FK_B4021E51A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE project_user
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE task DROP FOREIGN KEY FK_527EDB25224F3C61
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_527EDB25224F3C61 ON task
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE task DROP task_list_id, CHANGE title title VARCHAR(50) NOT NULL, CHANGE description description VARCHAR(255) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user DROP is_verified, CHANGE statut statut JSON NOT NULL, CHANGE email email VARCHAR(50) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project DROP FOREIGN KEY FK_2FB3D0EE2C9E1458
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_2FB3D0EE2C9E1458 ON project
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project DROP chef_de_projet_id, CHANGE statut statut JSON NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE task_list DROP FOREIGN KEY FK_377B6C63166D1F9C
        SQL);
    }
}
