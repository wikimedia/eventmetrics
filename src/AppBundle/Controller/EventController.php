<?php
/**
 * This file contains only the EventController class.
 */

declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Model\Event;
use AppBundle\Model\EventCategory;
use AppBundle\Model\EventWiki;
use AppBundle\Model\Participant;
use AppBundle\Service\JobHandler;
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
     * @return RedirectResponse
     */
    public function indexAction(): RedirectResponse
    {
        return new RedirectResponse($this->generateUrl('Programs'));
    }

    /**
     * Show a form to create a new event.
     * @Route("/programs/{programId}/events/new", name="NewEvent")
     * @param Event $event Event to copy.
     * @return Response|RedirectResponse
     */
    public function newAction(?Event $event = null): Response
    {
        // $event is not null when copying an Event.
        if (null === $event) {
            $event = new Event($this->program);
            $eventWiki = new EventWiki($event);
            $event->addWiki($eventWiki);
        }

        // Handle the Form for the request, and redirect if they submitted.
        $form = $this->handleFormSubmission($event, 'Event');

        if ($form instanceof RedirectResponse) {
            // Flash message will be shown at the top of the page.
            $this->addFlashMessage('success', 'event-created', [$event->getDisplayTitle()]);
            return $form;
        }

        return $this->render('events/new.html.twig', [
            'form' => $form->createView(),
            'event' => $event,
            'gmTitle' => 'create-new-event',
        ]);
    }

    /**
     * Show a form to edit the given event.
     * @Route("/programs/{programId}/events/{eventId}/edit", name="EditEvent")
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
        $form = $this->handleFormSubmission($this->event, 'Event');
        if ($form instanceof RedirectResponse) {
            // Flash message will be shown at the top of the page.
            $this->addFlashMessage('success', 'event-updated', [$this->event->getDisplayTitle()]);
            return $form;
        }

        return $this->render('events/edit.html.twig', [
            'form' => $form->createView(),
            'event' => $this->event,
            'gmTitle' => $this->event->getDisplayTitle(),
            'isOrganizer' => $this->authUserIsOrganizer($this->program),
        ]);
    }

    /**
     * Copy the given Event and redirect to the NewEvent action, clearing out the title,
     * and getting new instances of associated entities.
     * @Route("/programs/{programId}/events/{eventId}/copy", name="CopyEvent")
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

        /** @var EventCategory $category */
        foreach ($this->event->getCategories()->getIterator() as $category) {
            new EventCategory($event, $category->getTitle(), $category->getDomain());
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
     * @Route("/programs/{programId}/events/{eventId}/delete", name="DeleteEvent")
     * @return RedirectResponse
     */
    public function deleteAction(): RedirectResponse
    {
        // Flash message will be shown at the top of the page.
        $this->addFlashMessage('danger', 'event-deleted', [$this->event->getDisplayTitle()]);

        $this->em->remove($this->event);
        $this->em->flush();

        return $this->redirectToRoute('Program', [
            'programId' => $this->program->getId(),
        ]);
    }

    /**************
     * EVENT PAGE *
     **************/

    /**
     * Show a specific event.
     * @Route("/programs/{programId}/events/{eventId}", name="Event")
     * @Route("/programs/{programId}/{eventId}", name="EventLegacy", requirements={
     *     "eventId" = "^(?!(new|edit|delete|revisions)$)[^\/]+"
     * })
     * @param JobHandler $jobHandler
     * @return Response
     */
    public function showAction(JobHandler $jobHandler): Response
    {
        // Kill any old, stale jobs.
        $jobHandler->handleStaleJobs($this->event);

        /** @var FormView[] $forms */
        $forms = [];

        // Handle each form type (participants, categories, etc.).
        foreach (['Participants', 'Categories'] as $formType) {
            if ('Categories' === $formType) {
                // Add blank EventCategory in the form if one doesn't already exist.
                // @fixme: There is probably a better place to do this than here.
                if ($this->event->getCategories()->isEmpty()) {
                    $firstWiki = $this->event->getWikis()->first();
                    $defaultDomain = 1 === $this->event->getWikis()->count() &&  !$firstWiki->isFamilyWiki()
                        ? $firstWiki->getDomain()
                        : '';
                    $category = new EventCategory($this->event, '', $defaultDomain);
                    $this->event->addCategory($category);
                }
            }

            $form = $this->handleFormSubmission($this->event, $formType);

            if ($form instanceof RedirectResponse) {
                // Save was successful. Flash message will be shown at the top of the page.
                $this->addFlashMessage('success', 'event-updated', [$this->event->getDisplayTitle()]);
                return $form;
            }

            $forms[$formType] = $form->createView();
        }

        $filtersMissing = !$this->event->getNumParticipants() && !$this->event->getNumCategories(true);
        return $this->render('events/show.html.twig', [
            'gmTitle' => $this->event->getDisplayTitle(),
            'forms' => $forms,
            'program' => $this->program,
            'event' => $this->event,
            'isOrganizer' => $this->authUserIsOrganizer($this->program),
            'job' => $this->event->getJob(),
            'filtersMissing' => $filtersMissing,
            'wikisWithoutCats' => $this->event->getWikisWithoutCategories(),
        ]);
    }

    /****************
     * FORM HELPERS *
     ****************/

    /**
     * Handle submission of the given form type.
     * @param Event $event
     * @param string $type Plural form, matching AppBundle\Form\*, e.g. 'Participants' or 'Categories'.
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

            // Only put 'eventId' if redirecting to event page (otherwise '?eventId=Foo' would be in the URL).
            $urlParams = ['programId' => $event->getProgram()->getId()];
            if ('Event' === $redirect) {
                $urlParams['eventId'] = $event->getId();
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
        foreach (['wikis', 'categories'] as $type) {
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
