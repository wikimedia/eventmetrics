<?php
declare( strict_types=1 );

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to split the EventWiki pages blobs into two: one for pages created and one for pages edited.
 * @link https://phabricator.wikimedia.org/T206817
 */
final class Version20181213234624 extends AbstractMigration {

	public function up( Schema $schema ) : void {
		$this->abortIf( $this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on mysql.' );
		$sql = 'ALTER TABLE event_wiki'
			. ' CHANGE ew_pages ew_pages_created BLOB DEFAULT NULL,'
			. ' ADD ew_pages_edited BLOB DEFAULT NULL AFTER ew_pages_created';
		$this->addSql( $sql );
	}

	public function down( Schema $schema ) : void {
		$this->abortIf( $this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on mysql.' );
		$sql = 'ALTER TABLE event_wiki'
			. ' CHANGE ew_pages_created ew_pages BLOB DEFAULT NULL,'
			. ' DROP ew_pages_edited';
		$this->addSql( $sql );
	}
}
