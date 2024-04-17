<?php declare( strict_types=1 );

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180901024720 extends AbstractMigration {
	public function up( Schema $schema ) : void {
		// this up() migration is auto-generated, please modify it to your needs
		$this->abortIf( $this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.' );

		$this->addSql( 'ALTER TABLE event_category DROP FOREIGN KEY FK_40A0F011ADC829A6' );
		$this->addSql( 'DROP INDEX ec_wikis ON event_category' );
		$this->addSql( 'DROP INDEX ec_event_wiki ON event_category' );
		$this->addSql( 'ALTER TABLE event_category ADD ec_event_id INT NOT NULL, ADD ec_title VARCHAR(255) NOT NULL, ADD ec_domain VARCHAR(255) NOT NULL, DROP ec_event_wiki_id, DROP ec_category_id' );
		$this->addSql( 'ALTER TABLE event_category ADD CONSTRAINT FK_40A0F011C9EA8DA0 FOREIGN KEY (ec_event_id) REFERENCES event (event_id)' );
		$this->addSql( 'CREATE INDEX ec_event ON event_category (ec_event_id)' );
		$this->addSql( 'CREATE UNIQUE INDEX ec_domains ON event_category (ec_title, ec_domain)' );
	}

	public function down( Schema $schema ) : void {
		// this down() migration is auto-generated, please modify it to your needs
		$this->abortIf( $this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.' );

		$this->addSql( 'ALTER TABLE event_category DROP FOREIGN KEY FK_40A0F011C9EA8DA0' );
		$this->addSql( 'DROP INDEX ec_event ON event_category' );
		$this->addSql( 'DROP INDEX ec_domains ON event_category' );
		$this->addSql( 'ALTER TABLE event_category ADD ec_category_id INT NOT NULL, DROP ec_title, DROP ec_domain, CHANGE ec_event_id ec_event_wiki_id INT NOT NULL' );
		$this->addSql( 'ALTER TABLE event_category ADD CONSTRAINT FK_40A0F011ADC829A6 FOREIGN KEY (ec_event_wiki_id) REFERENCES event_wiki (ew_id)' );
		$this->addSql( 'CREATE UNIQUE INDEX ec_wikis ON event_category (ec_category_id, ec_event_wiki_id)' );
		$this->addSql( 'CREATE INDEX ec_event_wiki ON event_category (ec_event_wiki_id)' );
	}
}
