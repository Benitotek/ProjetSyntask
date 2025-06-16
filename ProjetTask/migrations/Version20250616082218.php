<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250616082218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE user_project DROP FOREIGN KEY FK_77BECEE4166D1F9C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_project DROP FOREIGN KEY FK_77BECEE4A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_project
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project CHANGE date_creation date_creation DATETIME DEFAULT NULL, CHANGE date_maj date_maj DATETIME DEFAULT NULL, CHANGE date_butoir date_butoir DATETIME DEFAULT NULL, CHANGE date_reelle date_reelle DATETIME DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE task CHANGE date_creation date_creation DATETIME DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE task_list ADD position_column INT NOT NULL, ADD date_time DATETIME DEFAULT NULL, CHANGE project_id project_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user CHANGE date_creation date_creation DATETIME DEFAULT NULL, CHANGE date_maj date_maj DATETIME DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE user_project (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, project_id INT DEFAULT NULL, role VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_77BECEE4A76ED395 (user_id), INDEX IDX_77BECEE4166D1F9C (project_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_project ADD CONSTRAINT FK_77BECEE4166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_project ADD CONSTRAINT FK_77BECEE4A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE task_list DROP position_column, DROP date_time, CHANGE project_id project_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE task CHANGE date_creation date_creation DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user CHANGE date_creation date_creation DATETIME NOT NULL, CHANGE date_maj date_maj DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project CHANGE date_creation date_creation DATETIME NOT NULL, CHANGE date_maj date_maj DATETIME NOT NULL, CHANGE date_butoir date_butoir DATETIME NOT NULL, CHANGE date_reelle date_reelle DATETIME NOT NULL
        SQL);
    }
}
