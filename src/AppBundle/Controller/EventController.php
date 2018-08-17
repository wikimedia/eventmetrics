<?php
/**
 * This file contains only the EventController class.
 */

namespace AppBundle\Controller;

use AppBundle\Controller\Traits\CategoryTrait;
use AppBundle\Controller\Traits\ParticipantTrait;
use AppBundle\Model\Event;
use AppBundle\Model\EventCategory;
use AppBundle\Model\EventStat;
use AppBundle\Model\EventWiki;
use AppBundle\Model\Participant;
use AppBundle\Repository\EventRepository;
use AppBundle\Repository\EventWikiRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * The EventController handles showing, creating and editing events.
 */
class EventController extends EntityController
{
    /**
     * Used purely to move out some of the logic to dedicated files.
     */
    use CategoryTrait;
    use ParticipantTrait;

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
        $form = $this->getFormForEvent($event);
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
     * @param Form $form
     */
    private function handleEventWikiErrors(Form $form)
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

    /**
     * Build a form for the given event.
     * @param Event $event
     * @return FormInterface
     */
    private function getFormForEvent(Event $event)
    {
        $builder = $this->createFormBuilder($event)
            ->add('title', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ]
            ])
            ->add('wikis', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'empty_data' => '',
                'required' => true,
                'constraints' => [new Valid(), new NotBlank()],
                'data' => $event->getOrphanWikisAndFamilies(),
                'data_class' => null,
            ])
            ->add('time', TextType::class, [
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'off',
                ],
            ])
            ->add('start', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
                'html5' => false,
                'view_timezone' => 'UTC',
                'constraints' => [new Valid()],
            ])
            ->add('end', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
                'html5' => false,
                'view_timezone' => 'UTC',
                'constraints' => [new Valid()],
            ])
            ->add('timezone', TimezoneType::class, [
                'choices' => $this->getTimezones(),
                'choice_loader' => null,
            ])
            ->add('submit', SubmitType::class)
            ->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'onEventPreSubmit']);

        $builder->get('wikis')
            ->addModelTransformer($this->getWikiCallbackTransformer($event));

        return $builder->getForm();
    }

    /**
     * Normalize the form data before submitting.
     * @param FormEvent $formEvent
     */
    public function onEventPreSubmit(FormEvent $formEvent)
    {
        $event = $formEvent->getData();

        // Remove duplicate and blank wikis.
        $event['wikis'] = array_filter(array_values(array_unique($formEvent->getData()['wikis'])));

        $formEvent->setData($event);
    }

    /**
     * Get options for the timezone dropdown, grouping by region and
     * also prefixing each option with the region.
     * @return string[]
     */
    private function getTimezones()
    {
        $timezones = [
            'UTC' => 'UTC',
        ];

        foreach (\DateTimeZone::listIdentifiers() as $timezone) {
            $region = str_replace('_', ' ', explode('/', $timezone)[0]);
            $displayTimezone = str_replace('_', ' ', $timezone);

            if ($region === 'UTC') {
                continue;
            }

            if (isset($timezones[$region])) {
                $timezones[$region][$displayTimezone] = $timezone;
            } else {
                $timezones[$region] = [
                    $displayTimezone => $timezone,
                ];
            }
        }

        return $timezones;
    }

    /**
     * Transform wiki data to or from the form.
     * @param Event $event
     * @return CallbackTransformer
     */
    private function getWikiCallbackTransformer(Event $event)
    {
        $eventWikiRepo = new EventWikiRepository($this->em);
        $eventWikiRepo->setContainer($this->container);

        return new CallbackTransformer(
            function (ArrayCollection $wikiObjects) {
                // To domain names for the form, from EventWikis.
                $wikis = $wikiObjects->toArray();
                return array_map(function (EventWiki $wiki) {
                    return $wiki->getDomain();
                }, $wikis);
            },
            function ($wikis) use ($event, $eventWikiRepo) {
                return array_filter(
                    $this->normalizeEventWikis($wikis, $event, $eventWikiRepo)
                );
            }
        );
    }

    /**
     * Take the list of wikis provided by the user (enwiki, en.wikipedia, or en.wikipedia.org)
     * and normalize them to the domain (en.wikipedia). This method then instantiates a new
     * EventWiki if one did not already exist.
     * @param string[] $wikis As retrieved by the form.
     * @param Event $event
     * @param EventWikiRepository $eventWikiRepo
     * @return EventWiki[]
     */
    private function normalizeEventWikis($wikis, Event $event, EventWikiRepository $eventWikiRepo)
    {
        return array_map(function ($wiki) use ($event, $eventWikiRepo) {
            $domain = $eventWikiRepo->getDomainFromEventWikiInput($wiki);

            $eventWiki = $eventWikiRepo->findOneBy([
                'event' => $event,
                'domain' => $domain,
            ]);

            if ($eventWiki === null) {
                $eventWiki = new EventWiki($event, $domain);
            }

            return $eventWiki;
        }, $wikis);
    }

    /**************
     * EVENT PAGE *
     **************/

    /**
     * Show a specific event.
     * @Route("/programs/{programTitle}/{eventTitle}", name="Event", requirements={
     *     "programTitle" = "^(?!new|edit|delete).*$",
     *     "eventTitle" = "^(?!(new|edit|delete|revisions|participants)$)[^\/]+"
     * })
     * @Route("/programs/{programTitle}/{eventTitle}/", name="EventSlash", requirements={
     *     "programTitle" = "^(?!new|edit|delete).*$",
     *     "eventTitle" = "^(?!(new|edit|delete|revisions|participants)$)[^\/]+"
     * })
     * @return Response
     */
    public function showAction()
    {
        // Handle the participant Form for the request.
        $participantForm = $this->handleParticipantForm();
        if ($participantForm instanceof RedirectResponse) {
            // Flash message will be shown at the top of the page.
            $this->addFlash('success', [
                'event-updated',
                $this->event->getDisplayTitle(),
            ]);
            return $participantForm;
        }

        // Handle the category Form for the request.
//        $categoryForm = $this->handleCategoryForm();
//        if ($categoryForm  instanceof RedirectResponse) {
//            // Flash message will be shown at the top of the page.
//            $this->addFlash('success', [
//                'event-updated',
//                $this->event->getDisplayTitle(),
//            ]);
//            return $categoryForm;
//        }

//        // Add blank category if none already exist, so there will be an emtpy row ready to fill out.
//        if ($this->event->getNumCategories() === 0) {
//            $categories[] = new EventCategory(new EventWiki($this->event));
//        }

        /** @var EventRepository $eventRepo */
        $eventRepo = $this->em->getRepository(Event::class);

        return $this->render('events/show.html.twig', [
            'gmTitle' => $this->event->getDisplayTitle(),
            'participantForm' => $participantForm->createView(),
            'categoryForm' => $this->getCategoryForm($this->event)->createView(),
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
}
