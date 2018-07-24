<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180724185741 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE event_category (ec_id INT AUTO_INCREMENT NOT NULL, ec_event_wiki_id INT NOT NULL, ec_category_id INT NOT NULL, INDEX ec_event_wiki (ec_event_wiki_id), UNIQUE INDEX ec_wikis (ec_category_id, ec_event_wiki_id), PRIMARY KEY(ec_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE event_category ADD CONSTRAINT FK_40A0F011ADC829A6 FOREIGN KEY (ec_event_wiki_id) REFERENCES event_wiki (ew_id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE event_category');
    }
}
