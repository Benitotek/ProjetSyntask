<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250824110518 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project ADD date_archived DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE est_archive is_archived TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE project RENAME INDEX idx_2fb3d0eef10643cf TO IDX_2FB3D0EE82D0CA31');
        $this->addSql('ALTER TABLE task ADD nb_sous_taches INT DEFAULT NULL, CHANGE title title VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE task_list CHANGE couleur couleur VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE user ADD last_login_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP last_login_at');
        $this->addSql('ALTER TABLE project DROP date_archived, CHANGE is_archived est_archive TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE project RENAME INDEX idx_2fb3d0ee82d0ca31 TO IDX_2FB3D0EEF10643CF');
        $this->addSql('ALTER TABLE task DROP nb_sous_taches, CHANGE title title VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE task_list CHANGE couleur couleur VARCHAR(10) DEFAULT NULL');
    }
}
