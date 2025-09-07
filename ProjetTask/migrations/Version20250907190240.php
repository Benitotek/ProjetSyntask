<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250907190240 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // 0) activity: ajouter created_at / updated_at seulement si absents
        $this->addSql("
        SET @col_activity_created_at := (
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activity' AND COLUMN_NAME = 'created_at'
        );
    ");
        $this->addSql("
        SET @stmt := IF(@col_activity_created_at = 0,
            'ALTER TABLE activity ADD created_at DATETIME DEFAULT NULL',
            'SELECT 1'
        );
    ");
        $this->addSql('PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;');

        $this->addSql("
        SET @col_activity_updated_at := (
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'activity' AND COLUMN_NAME = 'updated_at'
        );
    ");
        $this->addSql("
        SET @stmt := IF(@col_activity_updated_at = 0,
            'ALTER TABLE activity ADD updated_at DATETIME DEFAULT NULL',
            'SELECT 1'
        );
    ");
        $this->addSql('PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;');

        /* ==========================================================
       1) Assurer la colonne tags.user_id + index + FK
       ========================================================== */
        // 1.1) Ajouter tags.user_id si absent
        $this->addSql("
        SET @col_tags_user_id := (
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tags' AND COLUMN_NAME = 'user_id'
        );
    ");
        $this->addSql("
        SET @stmt := IF(@col_tags_user_id = 0,
            'ALTER TABLE tags ADD user_id INT DEFAULT NULL',
            'SELECT 1'
        );
    ");
        $this->addSql('PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;');

        // 1.2) Index sur tags.user_id si absent
        $this->addSql("
        SET @idx_tags_user := (
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tags' AND INDEX_NAME = 'IDX_6FBC9426A76ED395'
        );
    ");
        $this->addSql("
        SET @stmt := IF(@idx_tags_user = 0,
            'CREATE INDEX IDX_6FBC9426A76ED395 ON tags (user_id)',
            'SELECT 1'
        );
    ");
        $this->addSql('PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;');

        // 1.3) FK tags.user_id -> user(id) si absente
        $this->addSql("
        SET @fk_tags_user := (
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'FK_6FBC9426A76ED395'
        );
    ");
        $this->addSql("
        SET @stmt := IF(@fk_tags_user = 0,
            'ALTER TABLE tags ADD CONSTRAINT FK_6FBC9426A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)',
            'SELECT 1'
        );
    ");
        $this->addSql('PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;');

        /* ==========================================================
       2) Créer un utilisateur "system" minimal pour backfill
       ========================================================== */
        // Ton schéma user n'a pas de password requis, on crée un user minimal
        $this->addSql("
    INSERT INTO user (nom, prenom, email, mdp, is_active, is_verified, statut, role, roles)
SELECT 'System', 'User', 'system@syntask.local', '$2y$13$5uG7m0a7l7S7gKk1y7r8UOz5QX8k0Zc9aPpFqz3mGqH6Gm3y2lXyG', 1, 1, 'SYSTEM', 'SYSTEM', '[]'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM user WHERE email = 'system@syntask.local')");
        // Récupérer son id
        $this->addSql("SET @system_id := (SELECT id FROM user WHERE email = 'system@syntask.local' LIMIT 1);");

        /* ==========================================================
       3) Solidifier task_list.created_by_id: backfill + index + FK
       ========================================================== */
        // 3.1) Vérifier la colonne (d'après ton screen elle existe déjà)
        $this->addSql("
        SET @col_tasklist_cbi := (
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'task_list' AND COLUMN_NAME = 'created_by_id'
        );
    ");
        // 3.2) Si absente (par sécurité), l'ajouter
        $this->addSql("
        SET @stmt := IF(@col_tasklist_cbi = 0,
            'ALTER TABLE task_list ADD created_by_id INT DEFAULT NULL',
            'SELECT 1'
        );
    ");
        $this->addSql('PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;');

        // 3.3) Backfill NULL -> system
        $this->addSql("
        UPDATE task_list
        SET created_by_id = @system_id
        WHERE created_by_id IS NULL
    ");
        // 3.4) Backfill orphelins -> system
        $this->addSql("
        UPDATE task_list tl
        LEFT JOIN user u ON u.id = tl.created_by_id
        SET tl.created_by_id = @system_id
        WHERE tl.created_by_id IS NOT NULL AND u.id IS NULL
    ");

        // 3.5) Index si absent
        $this->addSql("
        SET @idx_tasklist_cbi := (
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'task_list' AND INDEX_NAME = 'IDX_377B6C63B03A8386'
        );
    ");
        $this->addSql("
        SET @stmt := IF(@idx_tasklist_cbi = 0,
            'CREATE INDEX IDX_377B6C63B03A8386 ON task_list (created_by_id)',
            'SELECT 1'
        );
    ");
        $this->addSql('PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;');

        // 3.6) FK si absente
        $this->addSql("
        SET @fk_tasklist_cbi := (
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'FK_377B6C63B03A8386'
        );
    ");
        $this->addSql("
        SET @stmt := IF(@fk_tasklist_cbi = 0,
            'ALTER TABLE task_list ADD CONSTRAINT FK_377B6C63B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)',
            'SELECT 1'
        );
    ");
        $this->addSql('PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;');

        // 3.7) Optionnel: rendre NOT NULL si tu veux forcer l'intégrité à l'avenir
        // Vu que ta colonne est actuellement NULL, on peut la rendre NOT NULL maintenant que tout est backfillé
        $this->addSql("
        SET @is_nullable_tasklist_cbi := (
            SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'task_list' AND COLUMN_NAME = 'created_by_id' LIMIT 1
        );
    ");
        $this->addSql("
        SET @stmt := IF(@is_nullable_tasklist_cbi = 'YES',
            'ALTER TABLE task_list MODIFY created_by_id INT NOT NULL',
            'SELECT 1'
        );
    ");
        $this->addSql('PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;');

        /* ==========================================================
       4) Solidifier task.created_by_id aussi (FK + backfill)
          - Ta table task a déjà created_by_id (NULL) sans FK visible.
       ========================================================== */
        // 4.1) Backfill NULL -> system
        $this->addSql("
        UPDATE task
        SET created_by_id = @system_id
        WHERE created_by_id IS NULL
    ");
        // 4.2) Backfill orphelins -> system
        $this->addSql("
        UPDATE task t
        LEFT JOIN user u ON u.id = t.created_by_id
        SET t.created_by_id = @system_id
        WHERE t.created_by_id IS NOT NULL AND u.id IS NULL
    ");
        // 4.3) Index si absent
        $this->addSql("
        SET @idx_task_cbi := (
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'task' AND INDEX_NAME = 'IDX_TASK_CREATED_BY_ID'
        );
    ");
        $this->addSql("
        SET @stmt := IF(@idx_task_cbi = 0,
            'CREATE INDEX IDX_TASK_CREATED_BY_ID ON task (created_by_id)',
            'SELECT 1'
        );
    ");
        $this->addSql('PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;');

        // 4.4) FK si absente
        $this->addSql("
        SET @fk_task_cbi := (
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'FK_TASK_CREATED_BY_ID'
        );
    ");
        $this->addSql("
        SET @stmt := IF(@fk_task_cbi = 0,
            'ALTER TABLE task ADD CONSTRAINT FK_TASK_CREATED_BY_ID FOREIGN KEY (created_by_id) REFERENCES user (id)',
            'SELECT 1'
        );
    ");
        $this->addSql('PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;');

        // 4.5) Optionnel: rendre NOT NULL si désiré
        // Laisse en NULL si tu veux permettre tâches sans créateur explicite
        /*
    $this->addSql("
        SET @is_nullable_task_cbi := (
            SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'task' AND COLUMN_NAME = 'created_by_id' LIMIT 1
        );
    ");
    $this->addSql("
        SET @stmt := IF(@is_nullable_task_cbi = 'YES',
            'ALTER TABLE task MODIFY created_by_id INT NOT NULL',
            'SELECT 1'
        );
    ");
    $this->addSql('PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;');
    */
    }


    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE tags DROP FOREIGN KEY FK_6FBC9426A76ED395');
        $this->addSql('DROP INDEX IDX_6FBC9426A76ED395 ON tags');
        $this->addSql('ALTER TABLE tags DROP user_id');
        $this->addSql('ALTER TABLE task_list DROP FOREIGN KEY FK_377B6C63B03A8386');
        $this->addSql('DROP INDEX IDX_377B6C63B03A8386 ON task_list');
        $this->addSql('ALTER TABLE user DROP is_deleted, DROP est_actif');
    }
}
