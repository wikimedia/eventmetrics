<?php
/**
 * This file contains only the EventDataController class.
 */

namespace AppBundle\Controller;

use AppBundle\Model\Event;
use AppBundle\Model\Job;
use AppBundle\Repository\EventRepository;
use AppBundle\Service\JobHandler;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

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
     * @Route("/programs/{programTitle}/{eventTitle}/revisions", name="Revisions")
     * @Route("/programs/{programTitle}/{eventTitle}/revisions/", name="RevisionsSlash")
     * @return Response
     */
    public function revisionsAction(EventRepository $eventRepo)
    {
        // Redirect to event page if statistics have not yet been generated.
        if (null === $this->event->getUpdated()) {
            return $this->redirectToRoute('Event', [
                'programTitle' => $this->program->getTitle(),
                'eventTitle' => $this->event->getTitle(),
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
        if ($format === 'html' || $format == '') {
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
            ], $ret);
        }

        $ret['revisions'] = $eventRepo->getRevisions($this->event, $offset, $limit);

        return $this->getFormattedRevisionsResponse($format, $ret);
    }

    /**
     * Get the rendered template for the requested format.
     * @param string $format One of 'html', 'csv' or 'wikitext'
     * @param array $ret Data that should be passed to the view.
     * @return Response
     */
    private function getFormattedRevisionsResponse($format, array $ret)
    {
        $formatMap = [
            'wikitext' => 'text/plain',
            'csv' => 'text/csv',
        ];

        $response = $this->render("events/revisions.$format.twig", $ret);

        $contentType = isset($formatMap[$format]) ? $formatMap[$format] : 'text/html';
        $response->headers->set('Content-Type', $contentType);

        return $response;
    }

    /*************************
     * GENERATING STATISTICS *
     *************************/

    /**
     * Endpoint to create a Job to calculate and store statistics for the event.
     * If there is quota, the job will be ran immediately and the results returned as JSON.
     * Otherwise, a job is created and it will later be ran via cron.
     * @Route("/events/process/{eventId}", name="EventProcess", requirements={"id" = "\d+"})
     * @Route("/events/process/{eventId}/", name="EventProcessSlash", requirements={"id" = "\d+"})
     * @param JobHandler $jobHandler The job handler service, provided by Symfony dependency injection.
     * @param int $eventId The ID of the event to process.
     * @return JsonResponse
     * @throws AccessDeniedHttpException
     * @throws NotFoundHttpException
     * Coverage done on the ProcessEventCommand itself to avoid overhead of the request stack,
     * and also because this action can only be called via AJAX.
     */
    public function generateStatsAction(JobHandler $jobHandler, $eventId, EventRepository $eventRepo)
    {
        // Only respond to AJAX.
        if (!$this->request->isXmlHttpRequest()) {
            throw new AccessDeniedHttpException('This endpoint is for internal use only.');
        }

        // Find the Event.
        /** @var Event $event */
        $event = $eventRepo->findOneBy(['id' => $eventId]);

        if ($event === null) {
            throw new NotFoundHttpException();
        }

        // Check if a Job already exists. This is difficult to test, so we'll ignore...
        // @codeCoverageIgnoreStart
        if ($event->hasJob()) {
            /** @var Job $job */
            $job = $event->getJobs()[0];

            return new JsonResponse(
                [
                    'error' => 'A job with ID '.$job->getId().' already exists'.
                        ' for the event: '.$event->getDisplayTitle(),
                    'status' => $job->getStarted() ? 'running' : 'queued',
                ],
                Response::HTTP_ACCEPTED
            );
        }
        // @codeCoverageIgnoreEnd

        $response = $this->createJobAndGetResponse($jobHandler, $event);
        $response->setEncodingOptions(JSON_NUMERIC_CHECK);
        return $response;
    }

    /**
     * Create a Job for the given Event, and return the JSON response.
     * @param JobHandler $jobHandler The job handler service.
     * @param Event $event
     * @return JsonResponse
     * Coverage done on the ProcessEventCommand itself to avoid overhead of the request stack,
     * and also because this action can only be called via AJAX.
     * @codeCoverageIgnore
     */
    private function createJobAndGetResponse(JobHandler $jobHandler, Event $event)
    {
        // Create a new Job for the Event, and flush to the database.
        $job = new Job($event);
        $this->em->persist($job);
        $this->em->flush();

        $stats = $jobHandler->spawn($job);

        if (is_array($stats)) {
            return new JsonResponse(
                [
                    'success' => 'Statistics for event '.$event->getId().
                        ' successfully generated.',
                    'status' => 'complete',
                    'data' => $stats,
                ],
                Response::HTTP_OK
            );
        }

        return new JsonResponse(
            [
                'success' => 'Job has successfully been queued.',
                'status' => 'queued',
            ],
            Response::HTTP_ACCEPTED
        );
    }
}
