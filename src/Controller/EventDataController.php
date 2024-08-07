<?php declare( strict_types=1 );

namespace App\Controller;

use App\Model\Event;
use App\Model\Job;
use App\Repository\EventRepository;
use App\Service\JobHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
// phpcs:ignore MediaWiki.Classes.UnusedUseStatement.UnusedUse
use Symfony\Component\Routing\Annotation\Route;

/**
 * The EventDataController handles the event data page, export options, and statistics generation.
 */
class EventDataController extends EntityController {

	/***********
	 * REPORTS *
	 ***********/

	/**
	 * Lists individual revisions that make up the Event.
	 * @Route("/programs/{programId}/events/{eventId}/revisions", name="Revisions")
	 * @Route("/programs/{programId}/{eventId}/revisions", name="RevisionsLegacy")
	 * @param EventRepository $eventRepo
	 * @param JobHandler $jobHandler
	 * @param int $revisionsPerPage
	 * @return Response
	 */
	public function revisionsReportAction(
		EventRepository $eventRepo,
		JobHandler $jobHandler,
		int $revisionsPerPage
	): Response {
		// Kill any old, stale jobs.
		$jobHandler->handleStaleJobs( $this->event );

		$ret = [
			'gmTitle' => $this->event->getDisplayTitle(),
			'program' => $this->program,
			'event' => $this->event,
			'isOrganizer' => $this->authUserIsOrganizer( $this->program ),
		];

		$offset = null;

		// If the format is not HTML, we show all revisions, and don't need an overall COUNT.
		$format = $this->request->query->get( 'format', 'html' );
		if ( $format === 'html' || $format == '' ) {
			// The get() default above doesn't work when the 'format' parameter is blank.
			$format = 'html';

			// The pagination number, where page 1 starts with row 0.
			// @TODO Temporarily disabled for now. Needs to be converted to timestamp-based pagination.
			//$page = (int)$this->request->query->get('offset', 1);
			$page = 1;

			// Actual row OFFSET used in the query.
			$offset = max( $page - 1, 0 ) * $revisionsPerPage;

			$ret = array_merge( [
				'numRevisions' => $eventRepo->getNumRevisions( $this->event ),
				'numResultsPerPage' => $revisionsPerPage,
				'offset' => $page,
				'job' => $this->event->getJob(),
			], $ret );
		}

		$ret['revisions'] = $eventRepo->getRevisions( $this->event, $offset, $revisionsPerPage );

		return $this->getFormattedResponse( $format, 'revisions', $ret );
	}

	/**
	 * Event Summary report.
	 * @Route("/programs/{programId}/events/{eventId}/summary", name="EventSummary")
	 * @return Response
	 */
	public function eventSummaryReportAction(): Response {
		$format = $this->request->query->get( 'format' ) === 'csv' ? 'csv' : 'wikitext';
		return $this->getFormattedResponse( $format, 'event_summary', [ 'event' => $this->event ] );
	}

	/**
	 * Pages Created report.
	 * @Route("/programs/{programId}/events/{eventId}/pages-created", name="EventPagesCreated")
	 * @param EventRepository $eventRepo
	 * @return Response
	 */
	public function pagesCreatedReportAction( EventRepository $eventRepo ): Response {
		$format = $this->request->query->get( 'format' ) === 'csv' ? 'csv' : 'wikitext';

		$userIds = $this->event->getParticipantIds();
		$usernames = array_column(
			$eventRepo->getUsernamesFromIds( $userIds ),
			'user_name'
		);

		return $this->getFormattedResponse( $format, 'pages_created', [
			'event' => $this->event,
			'pagesCreated' => $eventRepo->getPagesData( $this->event, $usernames, EventRepository::PAGES_CREATED ),
		] );
	}

	/**
	 * Pages improved report.
	 * @Route("/programs/{programId}/events/{eventId}/pages-improved", name="EventPagesImproved")
	 * @param EventRepository $eventRepo
	 * @return Response
	 */
	public function pagesImprovedReportAction( EventRepository $eventRepo ): Response {
		$format = $this->request->query->get( 'format' ) === 'csv' ? 'csv' : 'wikitext';

		$userIds = $this->event->getParticipantIds();
		$usernames = array_column(
			$eventRepo->getUsernamesFromIds( $userIds ),
			'user_name'
		);

		return $this->getFormattedResponse( $format, 'pages_improved', [
			'event' => $this->event,
			'pagesImproved' => $eventRepo->getPagesData( $this->event, $usernames, EventRepository::PAGES_IMPROVED ),
		] );
	}

	/**
	 * Get the rendered template for the requested format.
	 * @param string $format One of 'html', 'csv' or 'wikitext'.
	 * @param string $template E.g. 'revisions' or 'event_summary'.
	 * @param mixed[] $params Data that should be passed to the view.
	 * @return Response
	 */
	private function getFormattedResponse( string $format, string $template, array $params ): Response {
		$formatMap = [
			'wikitext' => 'text/plain',
			'csv' => 'text/csv',
		];

		$response = $this->render( "events/$template.$format.twig", $params );

		$contentType = $formatMap[$format] ?? 'text/html';
		$response->headers->set( 'Content-Type', $contentType );

		// Prettier file names for CSV.
		$eventName = $this->getFilenameFriendlyEventName();
		if ( $format === 'csv' && $eventName !== '' ) {
			$response->headers->set(
				'Content-Disposition',
				"attachment; filename=\"{$template}_{$eventName}.csv\""
			);
		}

		return $response;
	}

	/*************************
	 * GENERATING STATISTICS *
	 *************************/

	/**
	 * Endpoint to create a Job to calculate and store statistics for the event. This is called only via AJAX.
	 * A Job is created and will be ran immediately if there is quota. Otherwise it will later be ran via cron.
	 * @Route("/events/process/{eventId}", name="EventProcess", requirements={"id" = "\d+"}, methods={"POST"})
	 * @param JobHandler $jobHandler The job handler service, provided by Symfony dependency injection.
	 * @return Response
	 * @throws AccessDeniedHttpException
	 * @throws NotFoundHttpException
	 */
	public function generateStatsAction( JobHandler $jobHandler ): Response {
		$job = $this->event->getJob();

		// Start new Job unless one already exists, or if the existing Job failed (users are allowed to retry).
		if ( $job === false || $job->hasFailed() ) {
			if ( $job === false ) {
				// Create a new Job for the Event.
				$job = new Job( $this->event );
			} else {
				// Use same Job if it already exists.
				$job->setStatus( Job::STATUS_QUEUED );
			}

			// Flush to the database.
			$this->em->persist( $job );
			$this->em->flush();

			// End the session, allowing the user to navigate away from the page.
			// JavaScript will poll for job status and update the view accordingly.
			$this->requestStack->getSession()->save();

			// To process the job in this web request, bump execution time limit to 60 minutes.
			set_time_limit( Job::TIME_LIMIT );

			// Attempt to start the job immediately (if there's quota and it's not too heavy).
			if ( !$this->isHeavyEvent( $this->event ) ) {
				$jobHandler->spawn( $job );
			}
		}

		// Return empty response. The client will never see it anyway since the session was closed.
		return new Response( '', Response::HTTP_NO_CONTENT );
	}

	/**
	 * Returns the status of the Job associated with the given event.
	 * @Route("/events/job-status/{eventId}", name="EventJobStatus", requirements={"id" = "\d+"})
	 * @return JsonResponse
	 * @throws NotFoundHttpException
	 */
	public function jobStatusAction(): JsonResponse {
		$job = $this->event->getJob();

		// Happens when job has completed. This could also happen if there is/was no job at all,
		// but it's up to the client to only call this action after a job was started.
		if ( $job === false ) {
			return new JsonResponse( [
				'status' => 'complete',
			], Response::HTTP_OK );
		}

		if ( Job::STATUS_QUEUED == $job->getStatus() ) {
			$status = 'queued';
		} elseif ( Job::STATUS_FAILED_TIMEOUT === $job->getStatus() ) {
			$status = 'failed-timeout';
		} elseif ( Job::STATUS_FAILED_UNKNOWN === $job->getStatus() ) {
			$status = 'failed-unknown';
		} else {
			$status = 'started';
		}

		return new JsonResponse( [
			'id' => $job->getId(),
			'status' => $status,
		], Response::HTTP_OK );
	}

	/**
	 * Delete jobs associated with the given Event.
	 * @Route("/events/delete-job/{eventId}", name="EventDeleteJob", methods={"DELETE"})
	 * @return JsonResponse
	 */
	public function deleteJobAction(): JsonResponse {
		$job = $this->event->getJob();

		if ( $job !== false ) {
			$this->event->clearJobs();
			$this->em->persist( $this->event );
			$this->em->flush();
		}

		return new JsonResponse( [], Response::HTTP_NO_CONTENT );
	}

	/**
	 * Returns name of the current event filtered from characters that are problematic in filenames
	 * @return string
	 */
	private function getFilenameFriendlyEventName(): string {
		return trim( preg_replace( '/[-\/\\:;*?|<>%#"]+/', '-', $this->event->getTitle() ) );
	}

	/**
	 * Determines whether the given event is likely to be heavy enough to warrant to be processed via a cronjob.
	 *
	 * @param Event $event
	 * @return bool
	 */
	private function isHeavyEvent( Event $event ): bool {
		$diff = $event->getStart()->diff( $event->getEnd() );
		$hours = $diff->days * 24 + $diff->h + $diff->i / 60;

		return $event->getCategories()->count() * $hours > 1 ||
			$event->getNumParticipants() * $hours > 60 * 24;
	}
}
