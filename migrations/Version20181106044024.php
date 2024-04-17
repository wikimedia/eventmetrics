<?php declare( strict_types=1 );

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181106044024 extends AbstractMigration {
	public function up( Schema $schema ) : void {
		// this up() migration is auto-generated, please modify it to your needs
		$this->abortIf( $this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.' );

		$this->addSql( 'ALTER TABLE event DROP INDEX event_title_program_uniq, ADD INDEX event_program_title (event_program_id, event_title)' );
		$this->addSql( 'DROP INDEX UNIQ_3BAE0AA76A2DC947 ON event' );
		$this->addSql( 'DROP INDEX program_title_uniq ON program' );
	}

	public function down( Schema $schema ) : void {
		// this down() migration is auto-generated, please modify it to your needs
		$this->abortIf( $this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.' );

		$this->addSql( 'ALTER TABLE event DROP INDEX event_program_title, ADD UNIQUE INDEX event_title_program_uniq (event_program_id, event_title)' );
		$this->addSql( 'CREATE UNIQUE INDEX UNIQ_3BAE0AA76A2DC947 ON event (event_title)' );
		$this->addSql( 'CREATE UNIQUE INDEX program_title_uniq ON program (program_title)' );
	}
}
