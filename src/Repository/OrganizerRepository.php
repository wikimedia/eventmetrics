<?php

declare( strict_types=1 );

namespace App\Repository;

use App\Model\Organizer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Cache\CacheItemPoolInterface;
use Wikimedia\ToolforgeBundle\Service\ReplicasClient;

/**
 * This class supplies and fetches data for the Organizer class.
 * @codeCoverageIgnore
 */
class OrganizerRepository extends Repository {

	/**
	 * @param EntityManagerInterface $em
	 * @param CacheItemPoolInterface $cache
	 * @param ManagerRegistry $managerRegistry
	 * @param ReplicasClient $replicasClient
	 * @param ProgramRepository $programRepo
	 */
	public function __construct(
		EntityManagerInterface $em,
		CacheItemPoolInterface $cache,
		ManagerRegistry $managerRegistry,
		ReplicasClient $replicasClient,
		protected ProgramRepository $programRepo
	) {
		parent::__construct( $em, $cache, $managerRegistry, $replicasClient );
	}

	/**
	 * Class name of associated entity.
	 * Implements Repository::getEntityClass
	 * @return string
	 */
	public function getEntityClass(): string {
		return Organizer::class;
	}

	/**
	 * Fetch an Organizer by the given username.
	 * @param string $username
	 * @return Organizer
	 */
	public function getOrganizerByUsername( string $username ): Organizer {
		$userId = $this->getUserIdFromName( $username );

		// Username is invalid, so just return a new Organizer
		// without a user ID so that the Form can produce errors.
		if ( $userId === null ) {
			return new Organizer( $username );
		}

		$organizer = $this->findOneBy( [ 'userId' => $userId ] );

		if ( $organizer === null ) {
			$organizer = new Organizer( $userId );
		}

		$organizer->setUsername( $username );

		return $organizer;
	}

	/**
	 * Get unique metrics for all Programs created by this Organizer.
	 * @param Organizer $organizer
	 * @return string[]
	 */
	public function getUniqueMetrics( Organizer $organizer ): array {
		$metrics = [];
		foreach ( $organizer->getPrograms() as $program ) {
			$programMetrics = $this->programRepo->getUniqueMetrics( $program );

			foreach ( $programMetrics as $metric => $offset ) {
				if ( !isset( $metrics[$metric] ) ) {
					$metrics[$metric] = $offset;
				}
			}
		}

		return $metrics;
	}
}
