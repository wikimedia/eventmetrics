<?php
/**
 * This file contains only the EventController class.
 */

namespace AppBundle\Controller;

use AppBundle\Form\EventType;
use AppBundle\Form\ParticipantsType;
use AppBundle\Model\Event;
use AppBundle\Model\EventStat;
use AppBundle\Model\EventWiki;
use AppBundle\Model\Participant;
use AppBundle\Repository\EventRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The EventController handles showing, creating and editing events.
 */
class EventController extends EntityController
{
    /**
     * There is no list of events without a program to go with it.
     * @Route("/events", name="Events")
     * @Route("/events/", name="EventsSlash")
     * @return RedirectResponse
     */
    public function indexAction()
    {
        return new RedirectResponse($this->generateUrl('Programs'));
    }

    /**
     * Show a form to create a new event.
     * @Route("/programs/{programTitle}/new", name="NewEvent")
     * @Route("/programs/{programTitle}/new/", name="NewEventSlash")
     * @param Event $event Event to copy.
     * @return Response|RedirectResponse
     */
    public function newAction($event = null)
    {
        // $event is not null when copying an Event.
        if ($event === null) {
            $event = new Event($this->program);
            $eventWiki = new EventWiki($event);
            $event->addWiki($eventWiki);
        }

        // Handle the Form for the request, and redirect if they submitted.
        $form = $this->handleFormSubmission($event);

        if ($form instanceof RedirectResponse) {
            // Flash message will be shown at the top of the page.
            $this->addFlash('success', [
                'event-created',
                $event->getDisplayTitle(),
            ]);
            return $form;
        }

        return $this->render('events/new.html.twig', [
            'form' => $form->createView(),
            'event' => $event,
            'gmTitle' => 'create-new-program',
        ]);
    }

    /**
     * Show a form to edit the given event.
     * @Route("/programs/{programTitle}/edit/{eventTitle}", name="EditEvent")
     * @Route("/programs/{programTitle}/edit/{eventTitle}/", name="EditEventSlash")
     * @return Response|RedirectResponse
     */
    public function editAction()
    {
        // Add blank EventWiki in the form if one doesn't already exist.
        if ($this->event->getWikis()->isEmpty()) {
            $eventWiki = new EventWiki($this->event);
            $this->event->addWiki($eventWiki);
        }

        // Handle the Form for the request, and redirect if they submitted.
        $form = $this->handleFormSubmission($this->event);
        if ($form instanceof RedirectResponse) {
            // Flash message will be shown at the top of the page.
            $this->addFlash('success', [
                'event-updated',
                $this->event->getDisplayTitle(),
            ]);
            return $form;
        }

        return $this->render('events/edit.html.twig', [
            'form' => $form->createView(),
            'event' => $this->event,
            'gmTitle' => $this->event->getDisplayTitle(),
        ]);
    }

    /**
     * Copy the given Event and redirect to the NewEvent action, clearing out the title,
     * and getting new instances of associated entities.
     * @Route("/programs/{programTitle}/copy/{eventTitle}", name="CopyEvent")
     * @Route("/programs/{programTitle}/copy/{eventTitle}/", name="CopyEventSlash")
     * @return Response|RedirectResponse
     */
    public function copyAction()
    {
        $event = new Event(
            $this->program,
            null,
            $this->event->getStart(),
            $this->event->getEnd(),
            $this->event->getTimezone()
        );

        foreach ($this->event->getParticipants()->toArray() as $participant) {
            new Participant($event, $participant->getUserId());
        }

        foreach ($this->event->getWikis()->toArray() as $wiki) {
            // Don't copy child wikis, instead we'll be copying the parent family wiki.
            if (!$wiki->isChildWiki()) {
                new EventWiki($event, $wiki->getDomain());
            }
        }

        return $this->newAction($event);
    }

    /**
     * Delete an event.
     * @Route("/programs/{programTitle}/delete/{eventTitle}", name="DeleteEvent")
     * @Route("/programs/{programTitle}/delete/{eventTitle}/", name="DeleteEventSlash")
     * @return RedirectResponse
     */
    public function deleteAction()
    {
        // Flash message will be shown at the top of the page.
        $this->addFlash('danger', [
            'event-deleted',
            $this->event->getDisplayTitle(),
        ]);

        $this->em->remove($this->event);
        $this->em->flush();

        return $this->redirectToRoute('Program', [
            'programTitle' => $this->program->getTitle(),
        ]);
    }

    /**
     * Handle creation or updating of an Event on form submission.
     * @param Event $event
     * @return FormInterface|RedirectResponse
     */
    private function handleFormSubmission(Event $event)
    {
        $form = $this->createForm(EventType::class, $event, [
            'event' => $event,
        ]);
        $form->handleRequest($this->request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event = $form->getData();

            // Clear statistics and child wikis as the data will now be stale.
            $event->clearStatistics();
            $event->clearChildWikis();

            $this->em->persist($event);
            $this->em->flush();

            return $this->redirectToRoute('Program', [
                'programTitle' => $this->program->getTitle(),
            ]);
        } elseif ($form->isSubmitted() && !$form->isValid()) {
            $this->handleEventWikiErrors($form);
        }

        return $form;
    }

    /**
     * Consolidate errors of wikis associated with the event.
     * @param FormInterface $form
     */
    private function handleEventWikiErrors(FormInterface $form)
    {
        $numWikiErrors = count($form['wikis']->getErrors(true));
        if ($numWikiErrors > 0) {
            $form->addError(new FormError(
                // For the model-level, doesn't actually get rendered in the view.
                "$numWikiErrors wikis are invalid",
                // i18n arguments.
                'error-wikis',
                [$numWikiErrors]
            ));
        }
    }

    /**************
     * EVENT PAGE *
     **************/

    /**
     * Show a specific event.
     * @Route("/programs/{programTitle}/{eventTitle}", name="Event", requirements={
     *     "programTitle" = "^(?!new|edit|delete).*$",
     *     "eventTitle" = "^(?!(new|edit|delete|revisions)$)[^\/]+"
     * })
     * @Route("/programs/{programTitle}/{eventTitle}/", name="EventSlash", requirements={
     *     "programTitle" = "^(?!new|edit|delete).*$",
     *     "eventTitle" = "^(?!(new|edit|delete|revisions)$)[^\/]+"
     * })
     * @param EventRepository $eventRepo
     * @return Response
     */
    public function showAction(EventRepository $eventRepo)
    {
        // Handle the participants form for the request.
        $participantForm = $this->handleParticipantForm();
        if ($participantForm instanceof RedirectResponse) {
            // Flash message will be shown at the top of the page.
            $this->addFlash('success', [
                'event-updated',
                $this->event->getDisplayTitle(),
            ]);
            return $participantForm;
        }

        return $this->render('events/show.html.twig', [
            'gmTitle' => $this->event->getDisplayTitle(),
            'participantForm' => $participantForm->createView(),
            'program' => $this->program,
            'event' => $this->event,
            'stats' => $this->getEventStats($this->event),
            'isOrganizer' => $this->authUserIsOrganizer($this->program),
            'jobStatus' => $eventRepo->getJobStatus($this->event),
        ]);
    }

    /**
     * Get EventStats from the given Event. If there are none, empty EventStats
     * are returned for each metric type specified by EventStat::METRIC_TYPES,
     * with the default 'offset' values specified by Event::getAvailableMetrics().
     * This way we can show placeholders in the view.
     * @param Event $event
     * @return EventStat[]
     */
    private function getEventStats(Event $event)
    {
        if (count($event->getStatistics()) > 0) {
            return $event->getStatistics();
        }

        $availableMetrics = $event->getAvailableMetrics();
        $stats = [];

        foreach (EventStat::getMetricTypes() as $metric) {
            if (!in_array($metric, array_keys($availableMetrics))) {
                continue;
            }

            $stats[] = new EventStat($event, $metric, null, $availableMetrics[$metric]);
        }

        return $stats;
    }

    /**
     * Handle submission of form to add/remove participants.
     * @return FormInterface|RedirectResponse
     */
    private function handleParticipantForm()
    {
        $form = $this->createForm(ParticipantsType::class, $this->event, [
            'event' => $this->event,
        ]);
        $form->handleRequest($this->request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event = $form->getData();

            // Clear statistics and child wikis as the data will now be stale.
            $event->clearStatistics();
            $event->clearChildWikis();

            $this->em->persist($event);
            $this->em->flush();

            return $this->redirectToRoute('Event', [
                'programTitle' => $event->getProgram()->getTitle(),
                'eventTitle' => $event->getTitle(),
            ]);
        }

        return $form;
    }
}
