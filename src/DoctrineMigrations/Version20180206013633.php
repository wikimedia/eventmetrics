<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180206013633 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE event_stat (es_id INT AUTO_INCREMENT NOT NULL, es_event_id INT NOT NULL, es_metric VARCHAR(32) NOT NULL, es_metric_offset INT DEFAULT NULL, es_value INT DEFAULT 0 NOT NULL, INDEX IDX_3F73B38AEE440C48 (es_event_id), UNIQUE INDEX es_event_metric (es_event_id, es_metric), PRIMARY KEY(es_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE event_stat ADD CONSTRAINT FK_3F73B38AEE440C48 FOREIGN KEY (es_event_id) REFERENCES event (event_id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE event_stat');
    }
}
