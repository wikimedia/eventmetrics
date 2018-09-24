<?php
/**
 * This file contains only the EventController class.
 */

declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Model\Event;
use AppBundle\Model\EventStat;
use AppBundle\Model\EventWiki;
use AppBundle\Model\Participant;
use AppBundle\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
    public function indexAction(): RedirectResponse
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
    public function newAction($event = null): Response
    {
        // $event is not null when copying an Event.
        if ($event === null) {
            $event = new Event($this->program);
            $eventWiki = new EventWiki($event);
            $event->addWiki($eventWiki);
        }

        // Handle the Form for the request, and redirect if they submitted.
        $form = $this->handleFormSubmission($event, 'Event', 'Program');

        if ($form instanceof RedirectResponse) {
            // Flash message will be shown at the top of the page.
            $this->addFlashMessage('success', 'event-created', [$event->getDisplayTitle()]);
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
    public function editAction(): Response
    {
        // Add blank EventWiki in the form if one doesn't already exist.
        if ($this->event->getWikis()->isEmpty()) {
            $eventWiki = new EventWiki($this->event);
            $this->event->addWiki($eventWiki);
        }

        // Handle the Form for the request, and redirect if they submitted.
        $form = $this->handleFormSubmission($this->event, 'Event', 'Program');
        if ($form instanceof RedirectResponse) {
            // Flash message will be shown at the top of the page.
            $this->addFlashMessage('success', 'event-updated', [$this->event->getDisplayTitle()]);
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
    public function copyAction(): Response
    {
        $event = new Event(
            $this->program,
            null,
            $this->event->getStart(),
            $this->event->getEnd(),
            $this->event->getTimezone()
        );

        /** @var Participant $participant */
        foreach ($this->event->getParticipants()->getIterator() as $participant) {
            new Participant($event, $participant->getUserId());
        }

        /** @var EventWiki $wiki */
        foreach ($this->event->getWikis()->getIterator() as $wiki) {
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
    public function deleteAction(): RedirectResponse
    {
        // Flash message will be shown at the top of the page.
        $this->addFlashMessage('danger', 'event-deleted', [$this->event->getDisplayTitle()]);

        $this->em->remove($this->event);
        $this->em->flush();

        return $this->redirectToRoute('Program', [
            'programTitle' => $this->program->getTitle(),
        ]);
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
    public function showAction(EventRepository $eventRepo): Response
    {
        /** @var FormView[] $forms */
        $forms = [];

        // Handle each form type (participants, etc.).
        foreach (['Participants'] as $formType) {
            $form = $this->handleFormSubmission($this->event, $formType);

            if ($form instanceof RedirectResponse) {
                // Save was successful. Flash message will be shown at the top of the page.
                $this->addFlashMessage('success', 'event-updated', [$this->event->getDisplayTitle()]);
                return $form;
            }

            $forms[$formType] = $form->createView();
        }

        return $this->render('events/show.html.twig', [
            'gmTitle' => $this->event->getDisplayTitle(),
            'forms' => $forms,
            'program' => $this->program,
            'event' => $this->event,
            'stats' => $this->getEventStats($this->event),
            'isOrganizer' => $this->authUserIsOrganizer($this->program),
            'jobStatus' => $eventRepo->getJobStatus($this->event),
        ]);
    }

    /**
     * Get EventStats from the given Event. If there are none, empty EventStats are returned for each metric type
     * specified by EventStat::METRIC_TYPES, with the default 'offset' values specified by Event::getAvailableMetrics().
     * This way we can show placeholders in the view.
     * @param Event $event
     * @return Collection|EventStat[]
     */
    private function getEventStats(Event $event): Collection
    {
        if (count($event->getStatistics()) > 0) {
            return $event->getStatistics();
        }

        $availableMetrics = $event->getAvailableMetrics();
        $stats = new ArrayCollection();

        foreach (EventStat::getMetricTypes() as $metric) {
            if (!in_array($metric, array_keys($availableMetrics))) {
                continue;
            }

            $stats->add(
                new EventStat($event, $metric, null, $availableMetrics[$metric])
            );
        }

        return $stats;
    }

    /****************
     * FORM HELPERS *
     ****************/

    /**
     * Handle submission of the given form type.
     * @param Event $event
     * @param string $type Plural form, matching AppBundle\Form\*, e.g. 'Participants'.
     * @param string $redirect
     * @return FormInterface|RedirectResponse
     */
    private function handleFormSubmission(Event $event, string $type, string $redirect = 'Event')
    {
        $form = $this->createForm('AppBundle\Form\\'.$type.'Type', $event, [
            // Used because for some types (ParticipantsType), a reference to the Event is needed in form handling.
            // This is different than the 2nd argument to createForm(), which is used only to fill in the factory.
            'event' => $event,
        ]);
        $form->handleRequest($this->request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Event $event */
            $event = $form->getData();

            // Clear statistics and child wikis as the data will now be stale.
            $event->clearStatistics();
            $event->clearChildWikis();

            $this->em->persist($event);
            $this->em->flush();

            // Only put 'eventTitle' if redirecting to event page (otherwise '?eventTitle=Foo' would be in the URL).
            $urlParams = ['programTitle' => $event->getProgram()->getTitle()];
            if ($redirect === 'Event') {
                $urlParams['eventTitle'] = $event->getTitle();
            }

            return $this->redirectToRoute($redirect, $urlParams);
        } elseif ($form->isSubmitted() && !$form->isValid()) {
            $this->consolidateErrors($form);
        }

        return $form;
    }

    /**
     * Consolidate errors of wikis associated with the Event.
     * @param FormInterface $form
     */
    private function consolidateErrors(FormInterface $form): void
    {
        // @see TitleUserTrait::validateUsers() for where errors are assigned for invalid participants.
        // TODO: Probably would be nice to do the error assignments in one place (maybe not possible).
        foreach (['wikis'] as $type) {
            // Each form may not contain all fields we're consolidating errors for.
            if (!isset($form[$type])) {
                continue;
            }

            // Count the child form elements that are invalid. We can't use Form::getErrors() because the violations
            // may not exist on $form[$type]
            // We intentionally don't use Form::getErrors()
            $numErrors = 0;
            foreach ($form->get($type) as $field) {
                if (!$field->isValid()) {
                    $numErrors++;
                }
            }

            if ($numErrors > 0) {
                $form->addError(new FormError(
                    // For the model-level, doesn't actually get rendered in the view.
                    "$numErrors $type are invalid",
                    // i18n arguments.
                    "error-$type",
                    [$numErrors]
                ));
            }
        }
    }
}
