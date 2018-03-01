<?php
/**
 * This file contains only the EventController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormError;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Valid;
use AppBundle\Model\Event;
use AppBundle\Model\EventStat;
use AppBundle\Model\EventWiki;
use AppBundle\Model\Participant;
use AppBundle\Model\Program;
use AppBundle\Repository\EventWikiRepository;
use AppBundle\Repository\ParticipantRepository;

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
     * @return Response|RedirectResponse
     */
    public function newAction()
    {
        $event = new Event($this->program);
        $eventWiki = new EventWiki($event);
        $event->addWiki($eventWiki);

        // Handle the Form for the request, and redirect if they submitted.
        $form = $this->handleFormSubmission($event);
        if ($form instanceof RedirectResponse) {
            // Flash message will be shown at the top of the page.
            $this->addFlash('success', /** @scrutinizer ignore-type */ [
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
        if (count($this->event->getWikis()) === 0) {
            $eventWiki = new EventWiki($this->event);
            $this->event->addWiki($eventWiki);
        }

        // Handle the Form for the request, and redirect if they submitted.
        $form = $this->handleFormSubmission($this->event);
        if ($form instanceof RedirectResponse) {
            // Flash message will be shown at the top of the page.
            $this->addFlash('success', /** @scrutinizer ignore-type */ [
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
     * Delete an event.
     * @Route("/programs/{programTitle}/delete/{eventTitle}", name="DeleteEvent")
     * @Route("/programs/{programTitle}/delete/{eventTitle}/", name="DeleteEventSlash")
     * @return RedirectResponse
     */
    public function deleteAction()
    {
        // Flash message will be shown at the top of the page.
        $this->addFlash('danger', /** @scrutinizer ignore-type */ [
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
     * @param  Event $event
     * @return Form|RedirectResponse
     */
    private function handleFormSubmission(Event $event)
    {
        $form = $this->getFormForEvent($event);
        $form->handleRequest($this->request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event = $form->getData();
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
     * @param  Form $form
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
     * @param  Event $event
     * @return Form
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
                'delete_empty' => true,
                'empty_data' => '',
                'required' => false,
                'constraints' => [new Valid()],
            ])
            ->add('time', TextType::class, [
                'mapped' => false,
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
            ->add('submit', SubmitType::class);

        $builder->get('wikis')
            ->addModelTransformer($this->getWikiCallbackTransformer($event));

        return $builder->getForm();
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
            function ($wikiObjects) {
                $wikis = $wikiObjects->toArray();
                return array_map(function ($wiki) {
                    return $wiki->getDomain();
                }, $wikis);
            },
            function ($wikis) use ($event, $eventWikiRepo) {
                return $this->normalizeEventWikis($wikis, $event, $eventWikiRepo);
            }
        );
    }

    /**
     * Take the list of wikis provided by the user (enwiki, en.wikipedia, or en.wikipedia.org)
     * and normalize them to the database name (enwiki). This method also instantiates a new
     * EventWiki if one did not already exist.
     * @param  string[]            $wikis As retrieved by the form.
     * @param  Event               $event
     * @param  EventWikiRepository $eventWikiRepo
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

    /********************
     * PARTICIPANT FORM *
     ********************/

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
     * @return Response
     */
    public function showAction()
    {
        // Handle the Form for the request.
        $form = $this->handleParticipantForm();
        if ($form instanceof RedirectResponse) {
            // Flash message will be shown at the top of the page.
            $this->addFlash('success', /** @scrutinizer ignore-type */ [
                'event-updated',
                $this->event->getDisplayTitle(),
            ]);
            return $form;
        }

        return $this->render('events/show.html.twig', [
            'gmTitle' => $this->event->getDisplayTitle(),
            'form' => $form->createView(),
            'program' => $this->program,
            'event' => $this->event,
            'stats' => $this->getEventStats($this->event),
            'isOrganizer' => $this->authUserIsOrganizer($this->program),
        ]);
    }

    /**
     * Get EventStats from the given Event. If there are none, empty EventStats
     * are returned for each metric type specified by EventStat::METRIC_TYPES,
     * with the default 'offset' values sepcified by Event::getAvailableMetrics().
     * This way we can show placeholders in the view.
     * @param Event $event
     * @return EventStat[]
     */
    private function getEventStats(Event $event)
    {
        if (count($event->getStatistics()) > 0) {
            return $event->getStatistics();
        }

        return array_map(function ($metric) use ($event) {
            $offset = Event::getAvailableMetrics()[$metric];
            return new EventStat($event, $metric, null, $offset);
        }, EventStat::getMetricTypes());
    }

    /**
     * Handle submission of form to add/remove participants.
     * @param  Event $event
     * @return Form|RedirectResponse
     */
    private function handleParticipantForm()
    {
        $form = $this->getParticipantForm($this->event);
        $form->handleRequest($this->request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event = $form->getData();
            $this->em->persist($event);
            $this->em->flush();

            return $this->redirectToRoute('Event', [
                'programTitle' => $event->getProgram()->getTitle(),
                'eventTitle' => $event->getTitle(),
            ]);
        }

        return $form;
    }

    /**
     * Get the participant list form. Shown on the 'show' page.
     * @param  Event $event
     * @return Form
     */
    private function getParticipantForm(Event $event)
    {
        $builder = $this->createFormBuilder($event)
            ->add('participants', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' => true,
                'required' => false,
                'constraints' => [new Valid()],
            ])
            ->add('new_participants', TextareaType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('submit', SubmitType::class)
            ->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'onParticipantPreSubmit']);

        $builder->get('participants')
            ->addModelTransformer($this->getParticipantCallbackTransformer($event));

        return $builder->getForm();
    }

    /**
     * Format data before the participant form is submitted.
     * @param FormEvent $formEvent
     */
    public function onParticipantPreSubmit(FormEvent $formEvent)
    {
        $event = $formEvent->getData();

        /**
         * Parse new usernames from the textarea, removing
         * carriage returns and extraneous spacing.
         * @var string[]
         */
        $newParUsernames = explode("\n", $event['new_participants']);

        $participants = isset($event['participants']) ? $event['participants'] : [];

        // Combine usernames from inputs and textarea, removing duplicates and blank values.
        $event['participants'] = array_filter(array_unique(
            array_merge($participants, $newParUsernames)
        ));

        // Now normalize all the usernames and sort alphabetically.
        // TODO: Refactor this out, doing the same for Organizers to a Program.
        // Need to somehow hook into a callback in the model layer before validations are ran.
        $event['participants'] = array_map(function ($username) {
            return ucfirst(trim(str_replace('_', ' ', str_replace("\r", '', $username))));
        }, $event['participants']);
        sort($event['participants']);

        // Now unset new_participants so they aren't duplicated in the returned form.
        unset($event['new_participants']);

        $formEvent->setData($event);
    }

    /**
     * Given a row from ParticipantRepository::getRowsFromUsernames(),
     * find or instantiate a new Participant.
     * @param  Event    $event
     * @param  string[] $row As fetched from ParticipantRepository::getRowsFromUsernames().
     * @param  ParticipantRepository $participantRepo
     * @return Participant[]
     */
    private function getParticipantFromRow(Event $event, $row, ParticipantRepository $participantRepo)
    {
        if ($row['user_id'] === null) {
            // Username is invalid, so just return a new Participant
            // without a user ID so that the form can produce errors.
            $participant = new Participant($event);
        } else {
            // Otherwise we need find the one that exists in grantmetrics.
            $participant = $participantRepo->findOneBy([
                'userId' => $row['user_id'],
                'event' => $event,
            ]);

            if ($participant === null) {
                // Participant doesn't exist in grantmetrics yet,
                // so we'll create a new, blank Participant.
                $participant = new Participant($event);
                $participant->setUserId($row['user_id']);
            }
        }

        $participant->setUsername($row['user_name']);

        return $participant;
    }

    /**
     * Transform participant data to or from the form.
     * This essentially pulls in the username from the user ID,
     * and sets the user ID before persisting so that the username
     * can be validated.
     * @param Event $event
     * @return CallbackTransformer
     */
    private function getParticipantCallbackTransformer(Event $event)
    {
        return new CallbackTransformer(
            // Transform to the form.
            function ($participantObjects) {
                $parIds = array_map(function ($participant) {
                    return $participant->getUserId();
                }, $participantObjects->toArray());

                $eventRepo = $this->em->getRepository(Event::class);
                $eventRepo->setContainer($this->container);

                $usernames = array_column($eventRepo->getUsernamesFromIds($parIds), 'user_name');
                sort($usernames);
                return $usernames;
            },
            // Transform from the form.
            function ($participantNames) use ($event) {

                /** @var ParticipantRepository Repo for a Participant */
                $participantRepo = $this->em->getRepository(Participant::class);
                $participantRepo->setContainer($this->container);

                // Get the rows for each requested username.
                $rows = $participantRepo->getRowsFromUsernames($participantNames);

                // Should be in alphabetical order, the same order we'll show it
                // to the user in the returned form.
                usort($rows, function ($a, $b) {
                    return strnatcmp($a['user_name'], $b['user_name']);
                });

                $participants = [];

                // Create or get Participants from the usernames.
                foreach ($rows as $row) {
                    $participant = $this->getParticipantFromRow($event, $row, $participantRepo);
                    $event->addParticipant($participant);
                    $participants[] = $participant;
                }

                return $participants;
            }
        );
    }
}
