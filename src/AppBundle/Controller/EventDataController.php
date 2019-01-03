<?php
/**
 * This file contains only the EventDataController class.
 */

declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Model\Event;
use AppBundle\Model\Job;
use AppBundle\Repository\EventRepository;
use AppBundle\Service\JobHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The EventDataController handles the event data page, export options,
 * and statistics generation.
 */
class EventDataController extends EntityController
{
    /**********************
     * BROWSING REVISIONS *
     **********************/

    /**
     * Lists individual revisions that make up the Event.
     * @Route("/programs/{programId}/events/{eventId}/revisions", name="Revisions")
     * @Route("/programs/{programId}/{eventId}/revisions", name="RevisionsLegacy")
     * @param EventRepository $eventRepo
     * @param JobHandler $jobHandler
     * @return Response
     */
    public function revisionsAction(EventRepository $eventRepo, JobHandler $jobHandler): Response
    {
        // Kill any old, stale jobs.
        $jobHandler->handleStaleJobs($this->event);

        // Redirect to event page if statistics have not yet been generated.
        if (null === $this->event->getUpdated()) {
            return $this->redirectToRoute('Event', [
                'programId' => $this->program->getId(),
                'eventId' => $this->event->getId(),
            ]);
        }

        $ret = [
            'gmTitle' => $this->event->getDisplayTitle(),
            'program' => $this->program,
            'event' => $this->event,
            'isOrganizer' => $this->authUserIsOrganizer($this->program),
        ];

        $limit = $offset = null;

        // If the format is not HTML, we show all revisions, and don't need an overall COUNT.
        $format = $this->request->query->get('format', 'html');
        if ('html' === $format || '' == $format) {
            // The get() default above doesn't work when the 'format' parameter is blank.
            $format = 'html';

            // The pagination number, where page 1 starts with row 0.
            $page = (int)$this->request->query->get('offset', 1);

            // Number of rows per page.
            $limit = $this->container->getParameter('app.revisions_per_page');

            // Actual row OFFSET used in the query.
            $offset = max($page - 1, 0) * $limit;

            $ret = array_merge([
                'numRevisions' => $eventRepo->getNumRevisions($this->event),
                'numResultsPerPage' => $limit,
                'offset' => $page,
                'job' => $this->event->getJobs()->first(),
            ], $ret);
        }

        $ret['revisions'] = $eventRepo->getRevisions($this->event, $offset, $limit);

        return $this->getFormattedRevisionsResponse($format, $ret);
    }

    /**
     * Get the rendered template for the requested format.
     * @param string $format One of 'html', 'csv' or 'wikitext'
     * @param mixed[] $ret Data that should be passed to the view.
     * @return Response
     */
    private function getFormattedRevisionsResponse(string $format, array $ret): Response
    {
        $formatMap = [
            'wikitext' => 'text/plain',
            'csv' => 'text/csv',
        ];

        $response = $this->render("events/revisions.$format.twig", $ret);

        $contentType = $formatMap[$format] ?? 'text/html';
        $response->headers->set('Content-Type', $contentType);

        return $response;
    }

    /*************************
     * GENERATING STATISTICS *
     *************************/

    /**
     * Endpoint to create a Job to calculate and store statistics for the event. This is called only via AJAX.
     * A Job is created and will be ran immediately if there is quota. Otherwise it will later be ran via cron.
     * @Route("/events/process/{eventId}", name="EventProcess", requirements={"id" = "\d+"})
     * @param JobHandler $jobHandler The job handler service, provided by Symfony dependency injection.
     * @param int $eventId The ID of the event to process.
     * @param EventRepository $eventRepo
     * @return Response
     * @throws AccessDeniedHttpException
     * @throws NotFoundHttpException
     */
    public function generateStatsAction(JobHandler $jobHandler, int $eventId, EventRepository $eventRepo): Response
    {
        // Only respond to AJAX.
        if (!$this->request->isXmlHttpRequest()) {
            throw new AccessDeniedHttpException('This endpoint is for internal use only.');
        }

        /** @var Event $event */
        $event = $eventRepo->find($eventId);

        if (null === $event) {
            throw new NotFoundHttpException();
        }

        /** @var Job|false $job */
        $job = $this->event->getJobs()->first();

        // Start new Job unless one already exists, or if the existing Job failed (users are allowed to retry).
        if (false === $job || $job->hasFailed()) {
            // Clear the old jobs.
            $event->clearJobs();

            if (false === $job) {
                // Create a new Job for the Event.
                $job = new Job($event);
            } else {
                // Use same Job if it already exists.
                $job->setStatus(Job::STATUS_QUEUED);
            }

            // Flush to the database.
            $this->em->persist($job);
            $this->em->flush();

            // End the session, allowing the user to navigate away from the page.
            // JavaScript will poll for job status and update the view accordingly.
            $this->session->save();

            // Attempt to start the job immediately (if there's quota).
            $jobHandler->spawn($job);
        }

        // Return empty response. The client will never see it anyway since the session was closed.
        return new Response();
    }

    /**
     * Returns the status of the Job associated with the given event.
     * @Route("/events/job-status/{eventId}", name="EventJobStatus")
     * @param int $eventId
     * @param EventRepository $eventRepo
     * @return JsonResponse
     * @throws NotFoundHttpException
     */
    public function jobStatusAction(int $eventId, EventRepository $eventRepo): JsonResponse
    {
        // Find the Event.
        /** @var Event $event */
        $event = $eventRepo->find($eventId);

        if (null === $event) {
            throw new NotFoundHttpException();
        }

        /** @var Job $job */
        $job = $event->getJobs()->first();

        // Happens when job has completed. This could also happen if there is/was no job at all,
        // but it's up to the client to only call this action after a job was started.
        if (false === $job) {
            return new JsonResponse([
                'status' => 'complete',
            ], Response::HTTP_OK);
        }

        if (Job::STATUS_QUEUED == $job->getStatus()) {
            $status = 'queued';
        } elseif (Job::STATUS_FAILED_TIMEOUT === $job->getStatus()) {
            $status = 'failed-timeout';
        } elseif (Job::STATUS_FAILED_UNKNOWN === $job->getStatus()) {
            $status = 'failed-unknown';
        } else {
            $status = 'started';
        }

        return new JsonResponse([
            'id' => $job->getId(),
            'status' => $status,
        ], Response::HTTP_OK);
    }
}
