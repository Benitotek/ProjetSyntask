<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250614103606 extends AbstractMigration
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
            CREATE TABLE task_user (task_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_FE2042328DB60186 (task_id), INDEX IDX_FE204232A76ED395 (user_id), PRIMARY KEY(task_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project_user ADD CONSTRAINT FK_B4021E51166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project_user ADD CONSTRAINT FK_B4021E51A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE task_user ADD CONSTRAINT FK_FE2042328DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE task_user ADD CONSTRAINT FK_FE204232A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reset_password_request CHANGE requested_at requested_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE expires_at expires_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE task_list ADD position_column INT NOT NULL, ADD date_time DATETIME DEFAULT NULL, CHANGE project_id project_id INT NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE project_user DROP FOREIGN KEY FK_B4021E51166D1F9C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE project_user DROP FOREIGN KEY FK_B4021E51A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE task_user DROP FOREIGN KEY FK_FE2042328DB60186
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE task_user DROP FOREIGN KEY FK_FE204232A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE project_user
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE task_user
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE task_list DROP position_column, DROP date_time, CHANGE project_id project_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reset_password_request CHANGE requested_at requested_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE expires_at expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
    }
}
